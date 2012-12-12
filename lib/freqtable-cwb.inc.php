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





/* functions for doing stuff with CWB freq indexes */



////////////////////// this file may not implement the best way of divvying up the freqtable  functions.................... */

// TODO does this even merit a separate file? Why not into freqtable.inc.php?




/* check if a cwb-frequency-"corpus" exists for the specified lowercase name */
/* only for subcorpora, not for corpora */
	// I have no idea what the comment on the rpeceding line actually means...
function check_cwb_freq_index($corpus_name)
{
	$lowercase_name = $corpus_name . '__freq'; 
	$mysql_table = "freq_text_index_$corpus_name";
	
	$result = do_mysql_query("show tables");
	while ( ($r = mysql_fetch_row($result)) !== false)
		$a[] = $r[0];

	unset($result);

	return ( in_array($mysql_table, $a) && cwb_corpus_exists($lowercase_name) ) ;
}





function make_cwb_freq_index()
{
	global $corpus_sql_name;
	global $corpus_cqp_name;
	global $cqpweb_tempdir;
	global $cwb_datadir;
	global $cwb_registry;
	global $path_to_cwb;
	global $username;
	
	/* only superusers are allowed to do this! */
	if (! user_is_superuser($username))
		return;
	
	/* disallow this function for corpora with only one text */
	list($count_of_texts_in_corpus) 
		= mysql_fetch_row(do_mysql_query("select count(*) from text_metadata_for_$corpus_sql_name"));
	if ($count_of_texts_in_corpus < 2)
		exiterror_general("This corpus only contains one text. 
			Using a CWB frequency text-index is therefore neither necessary nor desirable.");
	
	/* this function may take longer than the script time limit */
	php_execute_time_unlimit();
	
	
	/* list of attributes on which to make frequency tables */
	$attribute[] = 'word';
	$p_att_line = '-P word ';
	$p_att_line_no_word = '';
	foreach (get_corpus_annotations() as $a => $junk)
	{
		if ($a == '__freq')
			/* very unlikely, but... */
			exiterror_general("you've got a p-att called __freq!!", __FILE__, __LINE__);
		$attribute[] = $a;
		$p_att_line .= "-P $a ";
		$p_att_line_no_word .= "-P $a ";
	}

	/* names of the created corpus (lowercase, upppercase) and various paths for commands */
	$freq_corpus_cqp_name_lc = strtolower($corpus_cqp_name) . '__freq';
	$freq_corpus_cqp_name_uc = strtoupper($freq_corpus_cqp_name_lc);
	
	$datadir = "/$cwb_datadir/$freq_corpus_cqp_name_lc";
	$regfile = "/$cwb_registry/$freq_corpus_cqp_name_lc";

	
	/* character set to use when encoding the new corpus */
	$cqp = new CQP("/$path_to_cwb", "/$cwb_registry");
	$cqp->set_error_handler('exiterror_cqp');
	$cqp->set_corpus($corpus_cqp_name);
	$charset = $cqp->get_corpus_charset();
	unset($cqp);


	/* delete any previously existing corpus of this name, then make the data directory ready */
	if (is_dir($datadir))
		cwb_uncreate_corpus($freq_corpus_cqp_name_lc);

	mkdir($datadir);
	chmod($datadir, 0777);

	/* open a pipe **from** cwb-decode and another **to** cwb-encode */
	$cmd_decode = "/$path_to_cwb/cwb-decode -r /$cwb_registry -C $corpus_cqp_name $p_att_line -S text_id";

	$source = popen($cmd_decode, 'r');

	$cmd_encode = "/$path_to_cwb/cwb-encode -d $datadir -c $charset -R $regfile $p_att_line_no_word -P __freq -S text:0+id ";

	$dest = popen($cmd_encode, 'w');

// TODO proper error handling please.
	if (!is_resource($source) || !is_resource($dest) )
		echo '<pre>one of the pipes did not open properly </pre>';

	/* for each line in the decoded output ... */
	while ( ($line = fgets($source)) !== false)
	{
		/* in case of whitespace... */
		$line = trim($line);
		
		if (preg_match('/^<text_id\s+(\w+)>$/', $line, $m) > 0)
		{
			/* extract the id from the preceding regex using (\w+) */
			$current_id = $m[1];
			$F = array();
		}
		else if ($line == '</text_id>')
		{
			/* do the things to be done at the end of each text */
			
			if ( ! isset($current_id) )
				exiterror_general("Unexpected </text> tag while creating corpus 
					$freq_corpus_cqp_name_uc! -- creation aborted",
					__FILE__, __LINE__);
			
			fputs($dest, "<text id=\"$current_id\">\n");
			arsort($F);
			
			foreach ($F as $l => &$c)
				fputs($dest, "$l\t$c\n");
			fputs($dest, "</text>\n");
			unset($current_id, $F);
		}
		else
		{
			/* if we're at the point of waiting for a text_id, and we got this, then ABORT! */
			if ( ! isset($current_id) )
				exiterror_general("Unexpected line outside &lt;text&gt; tags while creating corpus 
					$freq_corpus_cqp_name_uc! -- creation aborted",
					__FILE__, __LINE__);
			
			/* otherwise... */

			/* at the equivalent point in BNCweb-encoding, $line is split and then joined on \t */
			/* don't know why - it can't have any effect */
			if (isset($F[$line]))
				$F[$line]++;
			else
				$F[$line] = 1;
			/* whew! that's gonna be hell for memory allocation in the bigger texts */
		}
	}	/* end of while */
	
	/* close the pipes */
	pclose($source);
	pclose($dest);
	
	/* system commands for everything else that needs to be done to make it a good corpus */

	$mem_flag = '-M ' . get_cwb_memory_limit();
	$cmd_makeall  = "/$path_to_cwb/cwb-makeall $mem_flag -r /$cwb_registry -V $freq_corpus_cqp_name_uc ";
	$cmd_huffcode = "/$path_to_cwb/cwb-huffcode          -r /$cwb_registry -A $freq_corpus_cqp_name_uc ";
	$cmd_pressrdx = "/$path_to_cwb/cwb-compress-rdx      -r /$cwb_registry -A $freq_corpus_cqp_name_uc ";



	/* make the indexes & compress */
	exec($cmd_makeall,  $output);
	exec($cmd_huffcode, $output);
	exec($cmd_pressrdx, $output);



	/* delete the intermediate files that we were told we could delete */
	foreach ($output as $o)
		if (preg_match('/!! You can delete the file <(.*)> now./', $o, $m) > 0)
			if (is_file($m[1]))
				unlink($m[1]);
	unset ($output);
	
	/* the new CWB frequency-list-by-text "corpus" is now finished! */


	/*
	 * last thing is to create a file of indexes of the text_ids in this "corpus"
	 * contains 3 whitespace delimited fields: begin_index - end_index - text_id.
	 * 
	 * This then goes into a mysql table which corresponds to the __freq cwb corpus.
	 */
	$index_filename = "/$cqpweb_tempdir/{$corpus_sql_name}_freqdb_index.tbl";
	
	$s_decode_cmd = "/$path_to_cwb/cwb-s-decode -r /$cwb_registry $freq_corpus_cqp_name_uc -S text_id > $index_filename";
	exec($s_decode_cmd);
//	chmod($index_filename, 0777);
	
	/* make sure the $index_filename is utf8 */
	// TODO : adapt for latin2 and other variants.
	if ($charset == 'latin1')
	{
		$index_filename_new = $index_filename . '.utf8';
		
		$source = fopen($index_filename, 'r');
		$dest = fopen($index_filename_new, 'w');
		
		while ( ($line = fgets($source)) !== false)
			fputs($dest, utf8_encode($line));
			// TODO replace with iconv
		
		fclose($source);
		fclose($dest);
		
		unlink($index_filename);
		$index_filename = $index_filename_new;
	}
	
	/* now, create a mysql table with text begin-&-end-point indexes for this cwb-indexed corpus */
	/* a table which is subsequently used in the process of making the subcorpus freq lists */
// is it??

	$freq_text_index = "freq_text_index_$corpus_sql_name";
	
	do_mysql_query("drop table if exists $freq_text_index");

	
	$creation_query = "CREATE TABLE `$freq_text_index` 
		(
			`start` int(11) unsigned NOT NULL,
			`end` int(11) unsigned NOT NULL,
			`text_id` varchar(50) NOT NULL,
			KEY `text_id` (`text_id`)
		) 
		CHARACTER SET utf8 COLLATE utf8_bin";
	do_mysql_query($creation_query);

	do_mysql_infile_query($freq_text_index, $index_filename);
	/* NB we don't have to worry about the character encoding of the infile as it contains
	 * only integers and ID codes - so, all ASCII. */

	unlink($index_filename);


	/* turn the limit back on */
	php_execute_time_relimit();
}




/**
 * Turns a corpus subsection definition (subcorpus or restriction) into a series of
 * corpus positon pairs corresponding to the CWB frequency index corpus (NOT the corpus
 * itself).
 *  
 * Note that the specification of a subcorpus trumps restrictions. 
 * (As elsewhere, e.g. when a query happens.) 
 * 
 * @return   An array of arrays where each array contains two ints
 *           (start cpos, end cpos of the region).
 */
function get_freq_index_postitionlist_for_subsection($subcorpus = 'no_subcorpus', $restriction = 'no_restriction')
{
	global $corpus_sql_name;
	global $username;
	
	/* Check whether the specially-indexed cwb per-file freqlist corpus exists */
	if ( ! check_cwb_freq_index($corpus_sql_name) )
		exiterror_general("No CWB frequency-by-text index exists for corpus $corpus_sql_name!", 
			__FILE__, __LINE__);
	/* because if it doesn't exist, we can't get the positions from it! */
	
	/* this clause implements the override - the caller may or may not have done this already */
	if ($subcorpus != 'no_subcorpus')
		$restriction = 'no_restriction';
	

	/* now, we need a list of all the text ids within the subsection */
	if ($restriction != 'no_restriction')
	{
		/* this is NOT a named subcorpus, rather it is an anonymous subsection 
		 * of the corpus whose freq list is being compiled ... */
		$text_list = translate_restrictions_to_text_list($restriction);
	}
	else
	{
		/* a subcorpus has been named: check that it exists */
		$sql_query = "select * from saved_subcorpora where subcorpus_name = '"
			. mysql_real_escape_string($subcorpus) . "' 
			and corpus = '$corpus_sql_name'
			and user   = '$username'";
		$result = do_mysql_query($sql_query);
		if (mysql_num_rows($result) < 1)
			exiterror_arguments($subcorpus, 'This subcorpus doesn\'t appear to exist!',
				__FILE__, __LINE__);

		$r = mysql_fetch_assoc($result);
		
		if ( $r['text_list'] != '' )
			$text_list = $r['text_list'];
		else
			$text_list = translate_restrictions_to_text_list($r['restrictions']);
	}

	/* Whether restriction or sc, we now have a list of texts in the corpus, for which we need the 
	 * start-and-end positions in the FREQ TABLE CORPUS (NOT the actual corpus).
	 * 
	 * We can't just do a query for "start,end WHERE text_id is ... or text_id is ..." because
	 * this will overflow the max packet size for a server data transfer if the text list is
	 * long. So, instead, let's do it a hundred at a time.
	 * 
	 * (This is a dirty hack; there may be a much better way involving a join with the 
	 * main text metadata table or something. But, hey ho.)
	 */

	$position_list = array();

	foreach(array_chunk(explode(' ', $text_list), 20) as $chunk_of_texts)
	{		
		/* first step: convert list of texts to an sql where clause */
		$textid_whereclause = translate_textlist_to_where($chunk_of_texts, true);
		
		/* second step: get that chunk's begin-and-end points in the specially-indexed cwb per-file freqlist corpus */
		$sql_query = "select start, end from freq_text_index_$corpus_sql_name where $textid_whereclause";
		$result = do_mysql_query($sql_query);
		
		/* third step: store regions to be scanned in output array */
		while ( ($r = mysql_fetch_row($result)) !== false )
			$position_list[] = $r;
	}
	
	/* All position lists must be ASC sorted for CWB to make sense of them. The list we have built from
	 * MySQL may or may not be pre-sorted depending on the original history of the text-list... */
	$position_list = sort_positionlist($position_list);
	
	return $position_list;
}




?>
