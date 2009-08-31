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





/* functions for doing stuff with CWB */



////////////////////////////// this file may not implement the best way of divvying up the freqtable  functions.................... */






/* check if a cwb-frequency-"corpus" exists for the specified lowercase name */
/* only for subcorpora, not for corpora */
function check_cwb_freq_index($corpus_name)
{
	global $mysql_link;

	$sql_name = $corpus_name . '__freq'; 
	$mysql_table = "freq_text_index_$corpus_name";
	
	$sql_query = "show tables";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	while ( ($r = mysql_fetch_row($result)) !== false)
		$a[] = $r[0];

	unset($result);

	return ( in_array($mysql_table, $a) && cwb_corpus_exists($sql_name) ) ;
}





function make_cwb_freq_index()
{
	global $corpus_sql_name;
	global $corpus_cqp_name;
	global $mysql_link;
	global $cqp_tempdir;
	global $mysql_tempdir;
	global $cwb_datadir;
	global $cwb_registry;
	global $path_to_cwb;
	global $username;
	
	/* only superusers are allowed to do this! */
	if (! user_is_superuser($username))
		return;
	
	
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

	/* names of the created corpus and various paths for commands */
	$freq_corpus_sql_name = $corpus_sql_name . '__freq';
	$freq_corpus_cqp_name = strtoupper($freq_corpus_sql_name);
	
	$datadir = "/$cwb_datadir/$freq_corpus_sql_name";
	$regfile = "/$cwb_registry/$freq_corpus_sql_name";

	/* delete any previously existing corpus of this name, or make the data directory ready */
	if (! is_dir($datadir) )
	{
		mkdir($datadir);
		chmod($datadir, 0777);
	}
	else
		cwb_uncreate_corpus($freq_corpus_sql_name);


	/* open a pipe **from** cwb-decode and another **to** cwb-encode */
	$cmd_decode = "/$path_to_cwb/cwb-decode -r /$cwb_registry -C $corpus_cqp_name $p_att_line -S text_id";
//echo '<pre>' . $cmd_decode . '</pre>';
	$source = popen($cmd_decode, 'r');


	$cmd_encode = "/$path_to_cwb/cwb-encode -d $datadir -R $regfile $p_att_line_no_word -P __freq -S text:0+id ";
//show_var($cmd_encode);
	$dest = popen($cmd_encode, 'w');

if (!is_resource($source) || !is_resource($dest) ) echo '<pre>one of the pipes didnae open properly </pre>';
//echo '<pre>';
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
					$freq_corpus_cqp_name! -- creation aborted",
					__FILE__, __LINE__);
			
			fputs($dest, "<text id=\"$current_id\">\n");
			arsort($F);
			
			foreach ($F as $l => $c)
				fputs($dest, "$l\t$c\n");
			fputs($dest, "</text>\n");
			unset($current_id, $F);
		}
		else
		{
			/* if we're at the point of waiting for a text_id, and we got this, then ABORT! */
			if ( ! isset($current_id) )
				exiterror_general("Unexpected line outside &lt;text&gt; tags while creating corpus 
					$freq_corpus_cqp_name! -- creation aborted",
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
	$cmd_makeall  = "/$path_to_cwb/cwb-makeall      -r /$cwb_registry -V $freq_corpus_cqp_name ";
	$cmd_huffcode = "/$path_to_cwb/cwb-huffcode     -r /$cwb_registry -A $freq_corpus_cqp_name ";
	$cmd_pressrdx = "/$path_to_cwb/cwb-compress-rdx -r /$cwb_registry -A $freq_corpus_cqp_name ";


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
	  last thing is to create a file of indexes of the text_ids in this "corpus"
	  contains 3 whitespace delimited fields: begin_index - end_index - text_id.
	  
	  This then goes into a mysql table which corresponds to the __freq cwb corpus.
	*/
	$index_filename = "/$mysql_tempdir/{$corpus_sql_name}_freqdb_index.tbl";
	
	$s_decode_cmd = "/$path_to_cwb/cwb-s-decode -r /$cwb_registry $freq_corpus_cqp_name -S text_id > $index_filename";
	exec($s_decode_cmd);
//	chmod($index_filename, 0777);
	
	/* now, create a mysql table with text begin-&-end-point indexes for this cwb-indexed corpus */
	/* a table which is subsequently used in the process of making the subcorpus freq lists */
// is it??

	$freq_text_index = "freq_text_index_$corpus_sql_name";
	
	$sql_query = "drop table if exists $freq_text_index";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);
	
	$sql_query = "CREATE TABLE `$freq_text_index` 
		(
			`start` int(11) unsigned NOT NULL,
			`end` int(11) unsigned NOT NULL,
			`text_id` varchar(50) NOT NULL,
			KEY `text_id` (`text_id`)
		) 
		CHARACTER SET utf8 COLLATE utf8_general_ci";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);

	$sql_query = "load data infile '$index_filename' into table $freq_text_index";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);

	unlink($index_filename);


	/* turn the limit back on */
	php_execute_time_relimit();
}





?>