<?php
/**
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-9 Andrew Hardie
 *
 * See http://www.ling.lancs.ac.uk/activities/713/
 *
 * This file is part of CQPweb.
 * 
 * CQPweb is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * CQPweb is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */







/* ceql.inc.php -- functions which interface with perl and give CQPweb access to the CEQL parser. */


/** 

CEQL Parser Parameters for CQPweb
---------------------------------

A CEQL parser can be told to accept the following varieties of attribute:

** the attribute used to search for things that come after "_" in simple queries. 
   In the BNC, this is "pos". CEQL stores it as the 'pos_attribute' parameter.
   In CQPweb, this is referred to as the PRIMARY ANNOTATION.
   
** the attribute that will be searched if "{ ... }" is used in simple queries.
   In the BNC, this is "hw". CEQL stores it as the 'lemma_attribute' parameter.
   In CQPweb, this is referred to as the SECONDARY ANNOTATION. 

** the attribute that will be searched if "_{ ... }" is used in simple queries.
   In the BNC, this is "class". CEQL stores it as the 'simple_pos_attribute' parameter.
   In CQPweb, this is referred to as the TERTIARY ANNOTATION.
   But note, the tertiary annotation is not accessed directly. See next parameter.

** the lookup table for "_{ ... }". Note that the contents of this are not directly searched for.
   Rather, there is a hash table (== associative array) mapping a set of ALIASES to REGULAR EXPRESSIONS.
   It is these regexes that are actually searched for in the tertiary annotation.
   So, you can have more aliases than actual tags.
   Here is the hash table for the Oxford Simplified Tagset (thanks Stefan!)
   my $table = { 
           "A" => "ADJ",
           "ADJ" => "ADJ",
           "N" => "SUBST",
           "SUBST" => "SUBST",
           "V" => "VERB",
           "VERB" => "VERB",
           "ADV" => "ADV",
           "ART" => "ART",
           "CONJ" => "CONJ",
           "INT" => "INTERJ",
           "INTERJ" => "INTERJ",
           "PREP" => "PREP",
           "PRON" => "PRON",
           '$' => "STOP",
           "STOP" => "STOP",
           "UNC" => "UNC",
          };

** the attribute that will be searched if "{.../...} is used in simple queries.
   In this BNC, this is "lemma". CEQL by default doesn't have a parameter for this. 
   So cqpwebCEQL adds one. It is called 'combo_attribute'.
   IF combo_attribute is not defined, it uses the SECONDARY ANNOTATION and the TERTIARY ANNOTAION.

** the lookup table for s-attributes (XML). Again, a hash table. It should contain the names of all
   the allowable s-attributes mapped to 1. for the default, you are supposed to always have at least
   { "s" => 1 }
   since for a corpus to be used in CWB it should have at least <s> tags. Unfortunately, for CQPweb,
   this can't be guaranteed. But if there aren't any, then at least nothing will go wrong

** there are 2 other parameters:
   default_ignore_case : 0 means case is not ignored, 1 means it is
   default_ignore_diac : 0 means diacritics (accents) are not ignored, 1 means they are


** Note that the CEQL parser does not by default support "{.../...}" queries.

   In BNCweb, this is added in by overruling the "lemma_pattern" member function inherited from the 
   CEQL module. What it does is look in either "lemma" for AAA_BBB or in "hw" for AAA, depending on 
   whether a lemmatag or just a lemmaform was there in the original.
   
   In CQPweb, they are treated as follows. {...} is treated as a search of the secondary annotation,
   i.e. the one specified in CEQL's 'lemma_attribute' parameter. BUT...
   
   {.../...} may be treated either as a search of two different annotations, or of a combo annotation.
   
   IF it's a combo annotation, then the CEQL parameter 'combo_attribute' is used.
   IF it's not, then it is the SECONDARY ANNOTATION and the TERTIARY ANNOTAION that are used.

*/






function get_ceql_script_for_perl($query, $case_sensitive)
{
	global $mysql_link;
	global $corpus_sql_name;
	
	$sql_query = "select primary_annotation, secondary_annotation, tertiary_annotation, 
		tertiary_annotation_tablehandle, combo_annotation 
		from corpus_metadata_fixed 
		where corpus = '$corpus_sql_name'";
		
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false || mysql_num_rows($result) == 0) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	list($name_of_primary_annotation,$name_of_secondary_annotation,$name_of_tertiary_annotation,
		$name_of_table_of_3ary_mappings,$name_of_combo_annotation)
			= mysql_fetch_row($result);
	
	$string_with_table_of_3ary_mappings = lookup_tertiary_mappings($name_of_table_of_3ary_mappings);
	
	$script = '
		require "../lib/perl/cqpwebCEQL.pm";
		
		our $CEQL = new cqpwebCEQL;
		
		
		#~~primary_annotation_command~~#
		#~~secondary_annotation_command~~#
		#~~tertiary_annotation_command~~#
		#~~tertiary_annotation_table_command~~#
		#~~combo_annotation_command~~#
		#~~xml_annotation_command~~#
		
		$CEQL->SetParam("default_ignore_case", ##~~case_sensitivity_here~~##);
		$cqp_query = $CEQL->Parse("##~~string_of_query_here~~##");
		if (not defined $cqp_query) 
		{
			@error_msg = $CEQL->ErrorMessage;
			foreach $a(@error_msg)
			{
				print STDERR "$a\n";
			}
		}
		else
		{
			print $cqp_query;
		}
		';
		
// important thing to check : that NULL values in mysql will come out as NULLs and not strings containing NULL -- cos that is what is being checked here.
// i haven't checked this but everything seems to be working as it should

	/* if a primary annotation exists, specify it */
	if (isset($name_of_primary_annotation))
		$script = str_replace('#~~primary_annotation_command~~#',
			"\$CEQL->SetParam(\"pos_attribute\", \"$name_of_primary_annotation\"); ", $script);
	else
		$script = str_replace('#~~primary_annotation_command~~#', '', $script);

	/* if a secondary annotation exists, specify it */
	if (isset($name_of_secondary_annotation))
		$script = str_replace('#~~secondary_annotation_command~~#',
			"\$CEQL->SetParam(\"lemma_attribute\", \"$name_of_secondary_annotation\"); ", $script);
	else
		$script = str_replace('#~~secondary_annotation_command~~#', '', $script);

	/* if there is a tertiary annotation AND a tertiary annotation hash table, specify them */
	/* these are needed as a pair */
	if (isset($name_of_tertiary_annotation, $string_with_table_of_3ary_mappings))
	{
		$script = str_replace('#~~tertiary_annotation_command~~#',
			"\$CEQL->SetParam(\"simple_pos_attribute\", \"$name_of_tertiary_annotation\"); ", $script);
		$script = str_replace('#~~tertiary_annotation_table_command~~#',
			"\$CEQL->SetParam(\"simple_pos\", $string_with_table_of_3ary_mappings); ", $script);
	}
	else
	{
		$script = str_replace('#~~tertiary_annotation_command~~#', '', $script);
		$script = str_replace('#~~tertiary_annotation_table_command~~#', '', $script);
	}

	/* if a combo annotation is given, specify it */
	if (isset($name_of_combo_annotation))
		$script = str_replace('#~~combo_annotation_command~~#',
			"\$CEQL->SetParam(\"combo_attribute\", \"$name_of_combo_annotation\"); ", $script);
	else
		$script = str_replace('#~~combo_annotation_command~~#', '', $script);
	
	/* if there is an allowed-xml table, specify it */
	if (isset($string_with_table_of_xml_to_insert))
		$script = str_replace('#~~xml_annotation_command~~#',
			"\$CEQL->SetParam(\"s_attributes\", $string_with_table_of_xml_to_insert); ", $script);
	else
		$script = str_replace('#~~xml_annotation_command~~#', '', $script);	

	/* finally, insert the query itself and the case sensitivity */
	$script = str_replace('##~~case_sensitivity_here~~##', ($case_sensitive ? '0' : '1'), $script);
	$script = str_replace('##~~string_of_query_here~~##', $query, $script);

	return $script;	
}





function process_simple_query($query, $case_sensitive)
{
	global $username;
	global $corpus_sql_name;
	
	global $restrictions;
	global $subcorpus;
	
	global $path_to_perl;
	
	global $print_debug_messages;
	
	/* return as is if nothing but whitespace */
	if (preg_match('/^\s*$/', $query) > 0)
		return $query;

	/* create the script that will be bunged to perl */
	/* note, this function ALSO accepts an XML table, but this isn't implemented yet */
	$script = get_ceql_script_for_perl($query, $case_sensitive);


	$cqp_query = '';
	$ceql_errors = array();
		
	$io_settings = array(
		0 => array("pipe", "r"), // stdin 
		1 => array("pipe", "w"), // stdout 
		2 => array("pipe", "w")  // stderr 
	); 
	
	if (is_resource($process = proc_open("/$path_to_perl/perl", $io_settings, $handles))) 
	{
		/* write the script to perl's stdin */
		fwrite($handles[0], $script);
		fclose($handles[0]);

		if (stream_select($r=array($handles[1]), $w=NULL, $e=NULL, 10) > 0 )
			$cqp_query = fread($handles[1], 10240);
		if ($cqp_query === '')
		{
			if (stream_select($r=array($handles[2]), $w=NULL, $e=NULL, 10) > 0 )
				$ceql_errors = explode("\n", fread($handles[2], 10240));
		}

		fclose($handles[1]);
		fclose($handles[2]);
		proc_close($process);
	}
	else
		exiterror_cqp_full(array("The CEQL parser could not be run (problem with perl)!"));



	if ( ! isset($cqp_query) || $cqp_query == "")
	{
		/* if conversion fails, add to history & then add syntax error code */
		/* and then call an error -- script terminates */

		history_insert($instance_name, $query, $restrictions, $subcorpus, $query,
			($case_sensitive ? 'sq_case' : 'sq_nocase'));
		history_update_hits($instance_name, -1);

		array_unshift($ceql_errors, "<u>Syntax error</u>", "Sorry, your simple query
	        ' $query ' contains a syntax error.");
		if ($print_debug_messages)
			print_debug_message("Error in perl script for CEQL: this was the script\n\n$script");
		exiterror_cqp_full($ceql_errors);
	}
	return $cqp_query;
}





function lookup_tertiary_mappings($mapping_table_name)
{
	/* this function is effectively a collection of the tertiary mappings */
	/* (ie simple-tag or tag-lemma aliases) that CQPweb allows */
	switch ($mapping_table_name)
	{
	/* note, these should be perl code exactly as it would be written into the perl script */
	/* a perl hash table in each case; aliases keyed to regexes */
	case 'oxford_simplified_tags':
		return '{ 
           "A" => "ADJ",
           "ADJ" => "ADJ",
           "N" => "SUBST",
           "SUBST" => "SUBST",
           "V" => "VERB",
           "VERB" => "VERB",
           "ADV" => "ADV",
           "ART" => "ART",
           "CONJ" => "CONJ",
           "INT" => "INTERJ",
           "INTERJ" => "INTERJ",
           "PREP" => "PREP",
           "PRON" => "PRON",
           \'$\' => "STOP",
           "STOP" => "STOP",
           "UNC" => "UNC"
          }';
	case 'russian_mystem_wordclasses':
		/* note, first come the NORMAL russian classes, then the "aliases" */
		return '{ 
			"S" => "S",
			"V" => "V",
			"A" => "A",
			"PUNCT" => "PUNCT",
			"PR" => "PR",
			"SENT" => "SENT",
			"CONJ" => "CONJ",
			"S-PRO" => "S-PRO",
			"PART" => "PART",
			"A-PRO" => "A-PRO",
			"ADV-PRO" => "ADV-PRO",
			"ADV" => "ADV",
			"FW" => "FW",
			"INTJ" => "INTJ",
			"NUM" => "NUM",
			"PRAEDIC" => "PRAEDIC",
			"PARENTH" => "PARENTH",
			"A-NUM" => "A-NUM",
			"COM" => "COM",

			"ADJ" => "A",
			"NOUN" => "S",
			"N" => "S",
			"SUBST" => "S",
			"VERB" => "V",
			"INT" => "INTJ",
			"INTERJ" => "INTJ",
			"PREP" => "PR",
			\'$\' => "(PUNCT|SENT)",
			"STOP" => "(PUNCT|SENT)"
          }';
	case 'simplified_nepali_tags':
		/* no particular order */
		return '{ 
			"A" => "ADJ",
			"ADJ" => "ADJ",
			"SUBST" => "N",
			"N" => "SUBST",
			"V" => "VERB",
			"VERB" => "VERB",
			"ADV" => "ADV",
			"DEM" => "DEM",
			"CONJ" => "CONJ",
			"POSTP" => "POSTP",
			"PRON" => "PRON",
			"PART" => "PART",
			\'$\' => "PUNC",
			"PUNC" => "PUNC",
			"MISC" => "MISC"
			}';
	case 'german_tiger_tags':
		return '{
			"ADJ" => "ADJ.*",
			"SUBST" => "N.*",
			"NOUN" => "N.*",
			"VERB" => "V.*",
			"N" => "N.*",
			"V" => "V.*",
			"PRON" => "P.*",
			"AP" => "AP.*",
			"ADP" => "AP.*",
			"AUX" => "VA.*",
			"ADJA" => "ADJA",
			"ADJD" => "ADJD",
			"ADV" => "ADV",
			"APPO" => "APPO",
			"APPR" => "APPR",
			"APPRART" => "APPRART",
			"APZR" => "APZR",
			"ART" => "ART",
			"CARD" => "CARD",
			"FM" => "FM",
			"ITJ" => "ITJ",
			"KOKOM" => "KOKOM",
			"KON" => "KON",
			"KOUI" => "KOUI",
			"KOUS" => "KOUS",
			"NE" => "NE",
			"NN" => "NN",
			"NNE" => "NNE",
			"PDAT" => "PDAT",
			"PDS" => "PDS",
			"PIAT" => "PIAT",
			"PIS" => "PIS",
			"PPER" => "PPER",
			"PPOSAT" => "PPOSAT",
			"PPOSS" => "PPOSS",
			"PRELAT" => "PRELAT",
			"PRELS" => "PRELS",
			"PRF" => "PRF",
			"PROAV" => "PROAV",
			"PTKA" => "PTKA",
			"PTKANT" => "PTKANT",
			"PTKNEG" => "PTKNEG",
			"PTKVZ" => "PTKVZ",
			"PTKZU" => "PTKZU",
			"PWAT" => "PWAT",
			"PWAV" => "PWAV",
			"PWS" => "PWS",
			"TRUNC" => "TRUNC",
			"VAFIN" => "VAFIN",
			"VAIMP" => "VAIMP",
			"VAINF" => "VAINF",
			"VAPP" => "VAPP",
			"VMFIN" => "VMFIN",
			"VMINF" => "VMINF",
			"VMPP" => "VMPP",
			"VVFIN" => "VVFIN",
			"VVIMP" => "VVIMP",
			"VVINF" => "VVINF",
			"VVIZU" => "VVIZU",
			"VVPP" => "VVPP",
			"XY" => "XY",
			"YB" => "YB",
			"YI" => "YI",
			"YK" => "YK"
		}';

	default:
		return NULL;
	}
}

/* TODO would it be better to have this in the mySQL???  and the function with the actual tables ??? */
function get_list_of_tertiary_mapping_tables()
{
	return array(
		'oxford_simplified_tags' => 'Oxford Simplified Tagset',
		'russian_mystem_wordclasses' => 'MyStem Wordclasses',
		'german_tiger_tags' => 'TIGER tagset for German'
		);
}




/*
// archive only
function old_process_simple_query($query, $case_sensitive)
{
	global $username;
	global $corpus_sql_name;
	
	global $restrictions;
	global $subcorpus;
	
	/* return as is if nothing but whitespace * /
	if (preg_match('/^\s*$/', $query) > 0)
		return $query;


	/* call the parser * /
// note: is an escape needed here for the contents of $query?
	$cqp_query = perl_interface("../lib/perl/parse_simple_query.pl", "\"$query\" $case_sensitive");
	
	if ( ! isset($cqp_query) || $cqp_query == "")
	{
		/* if conversion fails, add to history & then add syntax error code * /
		/* and then call an error -- script terminates * /

		history_insert($instance_name, $query, $restrictions, $subcorpus, $query,
			($case_sensitive ? 'sq_case' : 'sq_nocase'));
		history_update_hits($instance_name, -1);

		exiterror_cqp_full(array("<u>Syntax error</u>", "Sorry, your simple query
	        ' $query ' contains a syntax error."));
	}
	return $cqp_query;
}
*/



?>