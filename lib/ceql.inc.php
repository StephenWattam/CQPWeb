<?php
/*
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-today Andrew Hardie and contributors
 *
 * See http://cwb.sourceforge.net/cqpweb.php
 *
 * This file is part of CQPweb.
 * 
 * CQPweb is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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


/** @file <pre>

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
   
   Here is the hash table for the Oxford Simplified Tagset (thanks Stefan!):
   
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

** the attribute that will be searched if "{.../...}" is used in simple queries.
   In this BNC, this is "lemma". CEQL by default doesn't have a parameter for this. 
   So cqpwebCEQL adds one. It is called 'combo_attribute'.
   IF combo_attribute is not defined, it uses the SECONDARY ANNOTATION and the TERTIARY ANNOTAION.

** the lookup table for s-attributes (XML). Again, a hash table. It should contain the names of all
   the allowable s-attributes mapped to 1. For the default, you are supposed to always have at least
   { "s" => 1 }
   since we'd expect a CWB corpus to have at least s-tags. Unfortunately, for CQPweb,
   this can't be guaranteed. But if there aren't any, then at least nothing will go wrong.

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

</pre>
*/





/**
 * Builds a Perl script that can be used to run the CEQL parser
 * for the given "Simple Query".
 * 
 * Case sensitivity mode must be specified separately.
 */
function get_ceql_script_for_perl($query, $case_sensitive)
{
	global $corpus_sql_name;
	
	$sql_query = "select primary_annotation, secondary_annotation, tertiary_annotation, 
		tertiary_annotation_tablehandle, combo_annotation 
		from corpus_metadata_fixed 
		where corpus = '$corpus_sql_name'";
		
	$result = do_mysql_query($sql_query);
	
	list($name_of_primary_annotation,
		$name_of_secondary_annotation,
		$name_of_tertiary_annotation,
		$name_of_table_of_3ary_mappings,
		$name_of_combo_annotation)
			= mysql_fetch_row($result);
	
	$string_with_table_of_3ary_mappings = lookup_tertiary_mappings($name_of_table_of_3ary_mappings);
	
	/* we accept the overhead of multiple str_replace() calls for the sake of not having
	 * to escape quote marks and dollar signs all over the shop! */
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
		$cqp_query = $CEQL->Parse(<<\'END_OF_CEQL_QUERY\');

##~~string_of_query_here~~##

END_OF_CEQL_QUERY

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

	/* if there is a tertiary annotation AND a tertiary annotation hash table, specify them  */
	/* (these are needed as a pair; note, the mapping table is always set, but may be false) */
	if (isset($name_of_tertiary_annotation) && $string_with_table_of_3ary_mappings != false)
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
	global $instance_name;
	
	global $restrictions;
	global $subcorpus;
	
	
	/* return as is if nothing but whitespace */
	if (preg_match('/^\s*$/', $query) > 0)
		return $query;

	/* create the script that will be bunged to perl */
	/* note, this function ALSO accepts an XML table, but this isn't implemented yet */
	$script = get_ceql_script_for_perl($query, $case_sensitive);

	
	if ( ! run_perl_script($script, $cqp_query, $ceql_errors))
		exiterror_cqp_full(array("The CEQL parser could not be run (problem with perl)!"));


	if ( empty($cqp_query) )
	{
		/* if conversion fails, add to history & then add syntax error code */
		/* and then call an error -- script terminates */

		history_insert($instance_name, $query, $restrictions, $subcorpus, $query,
			($case_sensitive ? 'sq_case' : 'sq_nocase'));
		history_update_hits($instance_name, -1);

		array_unshift($ceql_errors, "<u>Syntax error</u>", "Sorry, your simple query
	        ' $query ' contains a syntax error.");
	        
		print_debug_message("Error in perl script for CEQL: this was the script\n\n$script\n\n");
		
		exiterror_cqp_full($ceql_errors);
	}
	return $cqp_query;
}


/**
 * Runs the specified perl script (by piping to STDIN); collects Perl's
 * STDOUT (and, if STDOUT is empty, STDERR) and places it at the referenced arguments.
 *
 * Note that $output will always be overwritten by a single string, possibly
 * empty. Iff $output is empty, $errors wil be overwritten by an array of lines
 * from STDERR.
 *
 * $script is actually a script, not a path to a script on disk!
 *
 * Maximum output length is currently 10240 bytes.
 */
function run_perl_script($script, &$output, &$errors)
{
	global $path_to_perl;
	global $cwb_extra_perl_directories;

	$io_settings = array(
		0 => array("pipe", "r"), // stdin 
		1 => array("pipe", "w"), // stdout 
		2 => array("pipe", "w")  // stderr 
	); 
	
	$cmd = "/$path_to_perl/perl";
	foreach($cwb_extra_perl_directories as $d)
		$cmd .= " -I \"/$d\"";
	
	if (is_resource($process = proc_open($cmd, $io_settings, $handles))) 
	{
		/* write the script to perl's stdin */
		fwrite($handles[0], $script);
		fclose($handles[0]);

		/* read output */
		if (stream_select($r=array($handles[1]), $w=NULL, $e=NULL, 10) > 0 )
			$output = fread($handles[1], 10240);
		// TODO, possibly : should it check STDERR even when we have got output?
		if (empty($output))
		{
			if (stream_select($r=array($handles[2]), $w=NULL, $e=NULL, 10) > 0 )
				$errors = explode("\n", fread($handles[2], 10240));
		}

		fclose($handles[1]);
		fclose($handles[2]);
		proc_close($process);
		return true;
	}
	else
		return false;
}








/**
 * Returns the Perl string of the specified mapping table.
 * 
 * Return value is false if the mapping table was not found.
 */
function lookup_tertiary_mappings($mapping_table_id)
{
	$mapping_table_id = mysql_real_escape_string($mapping_table_id);
	
	$result = do_mysql_query("select mappings from annotation_mapping_tables where id = '$mapping_table_id'");
	
	$r = mysql_fetch_row($result);
	
	if ($r === false)
		return false;
	else
		return $r[0];
}


/**
 * Returns a list of available mapping tables as an array of the form handle => name;
 * or an empty array if no mapping tables were found in the database.
 */
function get_list_of_tertiary_mapping_tables()
{
	$result = do_mysql_query('select id, name from annotation_mapping_tables');
	$list = array();
	while ( ($r = mysql_fetch_object($result)) !== false)
		$list[$r->id] = $r->name;
	return $list;
}

/**
 * Returns an array of mapping tables as objects with the following
 * public members:
 *   ->id
 *   ->name
 *   ->mappings
 * 
 * All mapping tables currently available (whether custom or builtin)
 * are returned.
 */
function get_all_tertiary_mapping_tables()
{
	$result = do_mysql_query('select * from annotation_mapping_tables');
	$list = array();
	while ( ($r = mysql_fetch_object($result)) !== false)
		$list[] = $r;
	return $list;
}



/**
 * Adds a CEQL mapping table to the database.
 */
function add_tertiary_mapping_table($id, $name, $mappings)
{
	$id = mysql_real_escape_string($id);
	$name = mysql_real_escape_string($name);
	/* NB hopefully this will take care of multiple-escaping! */
	$mappings = mysql_real_escape_string($mappings);
	do_mysql_query("insert into annotation_mapping_tables (id, name, mappings) values ('$id', '$name', '$mappings')");
}

/**
 * Drops a CEQL mapping table from the database.
 */
function drop_tertiary_mapping_table($id)
{
	$id = mysql_real_escape_string($id);
	do_mysql_query("delete from annotation_mapping_tables where id = '$id'");
}



/**
 * Regenerates the built-in mapping tables.
 */ 
function regenerate_builtin_mapping_tables()
{
	/* default ids and names are contained here */
	$id_and_name = get_builtin_mapping_table_names();
	foreach($id_and_name as $id => $name)
	{
		/* this should handle multiple escaping... I think! */
		$code = get_builtin_mapping_table($id);
		drop_tertiary_mapping_table($id);
		add_tertiary_mapping_table($id, $name, $code);
	}
}








/*
 * The last two functions are really resource holders.
 */


/**
 * Gets an assoc array of names of builtin mapping tables: the keys are
 * the IDs fo the mapping tables.
 */
function get_builtin_mapping_table_names()
{
	return array(
		'oxford_simplified_tags' => 'Oxford Simplified Tagset (English)',
		'russian_mystem_wordclasses' => 'MyStem Wordclasses',
		'german_tiger_tags' => 'TIGER tagset for German',
		'simplified_nepali_tags' => 'Oxford Simplified Tagset (Nepali)'
		);
}


/**
 * Gets an assoc array of the actual code for the builtin mapping tables:
 * the keys are the IDs of the mapping tables.
 * 
 * NB. as per the rules for mapping tables, each code blob has to be a perl
 * hash table, exactly as it would be written into the code in each case.
 * The hash contains a set of aliases keyed to regexes (CQP-style regexes, 
 * i.e. PCRE-syntax with auto-anchoring at the start and end of the string).
 * 
 * If you add a new builtin table, be careful about quote escapes: remember
 * the string will be embedded into a perl script so escapes within perl
 * strings need to be double-escaped.
 */
function get_builtin_mapping_table($mapping_table_id)
{
	/* this function is effectively a collection of the tertiary mappings
	 * (ie simple-tag or tag-lemma aliases) that CQPweb allows */
	switch ($mapping_table_id)
	{
	/* note, these should be perl code exactly as it would be written into the perl script */
	/* a perl hash table in each case; aliases keyed to regexes; slash escape single quotes, natch */
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
			"S-PRO" => "S-PRO",p
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
			"N" => "N",
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


?>
