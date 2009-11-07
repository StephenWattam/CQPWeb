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





/* frequency table naming convention:

   for a corpus:	freq_corpus_{$corpus}_{$att}

   for a subcorpus:	freq_sc_{$corpus}_{$subcorpus}_$att

   */





/* this creates mySQL frequency tables for each attribute in a corpus */
/* pre-existing tables are deleted */
function corpus_make_freqtables()
{
	global $path_to_cwb;
	global $cwb_registry;
	global $corpus_sql_name;
	global $corpus_cqp_name;
	global $mysql_link;
	global $mysql_tempdir;
	global $username;
	
	/* only superusers are allowed to do this! */
	if (! user_is_superuser($username))
		return;
	
	/* list of attributes on which to make frequency tables */
	$attribute[] = 'word';
	foreach (get_corpus_annotations() as $a => $junk)
		$attribute[] = $a;

	unset($junk);
	
	/* create a temporary table */
	$temp_tablename = "temporary_freq_corpus_{$corpus_sql_name}";
	$sql_query = "DROP TABLE if exists $temp_tablename";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);	

	$sql_query = "CREATE TABLE $temp_tablename (
		freq int(11) unsigned default NULL";
	foreach ($attribute as $att)
		$sql_query .= ",
			$att varchar(210) NOT NULL";
	foreach ($attribute as $att)
		$sql_query .= ",
			key ($att)";
	$sql_query .= "
		) CHARACTER SET utf8 COLLATE utf8_general_ci";

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);	
	unset($result);	

	/* for convenience, $filename is absolute */
	$filename = "/$mysql_tempdir/____$temp_tablename.tbl";

	/* now, use cwb-scan-corpus to prepare the input */	
	$cwb_command = "/$path_to_cwb/cwb-scan-corpus -r /$cwb_registry -o $filename -q $corpus_cqp_name";
	foreach ($attribute as $att)
	$cwb_command .= " $att";
	exec($cwb_command, $junk, $status);
	if ($status != 0)
		exiterror_general("cwb-scan-corpus error!", __FILE__, __LINE__);
	unset($junk);


	database_disable_keys($temp_tablename);
	$sql_query = load_data_infile()." '$filename' INTO TABLE $temp_tablename FIELDS ESCAPED BY ''";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false)
		exiterror_mysqlquery(mysql_errno($mysql_link),
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);	
	database_enable_keys($temp_tablename);

	unlink($filename);

	/* ok - the temporary, ungrouped frequency table is in memory */
	/* each line is a unique binary line across all the attributes */
	/* it needs grouping differently for each attribute */
	/* (this will also take care of putting 'the', 'The' and 'THE' together */
	

	foreach ($attribute as $att)
	{
		$sql_tablename = "freq_corpus_{$corpus_sql_name}_$att";

		$sql_query = "DROP TABLE if exists $sql_tablename";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);	
		unset($result);

		$sql_query = "CREATE TABLE $sql_tablename (
			freq int(11) unsigned default NULL,
			item varchar(210) NOT NULL,
			primary key (item)
			) CHARACTER SET utf8 COLLATE utf8_general_ci";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		unset($result);
		
		database_disable_keys($sql_tablename);
		$sql_query = "
			INSERT INTO $sql_tablename 
				select sum(freq) as f, $att as item 
					from $temp_tablename
					group by $att";
//show_var($sql_query);
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		unset($result);
		database_enable_keys($sql_tablename);

	}

	/* delete temporary ungrouped table */
	$sql_query = "DROP TABLE if exists $temp_tablename";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}












/* create frequency lists for a --subsection only-- of the current corpus, */
/* ie a restriction or subcorpus */
/* note that the specification of a subcorpus trumps restrictions */
/* this is essentially like a query */
/* note also that no check for "already exists" is performed */
/* this must be done beforehand */
function subsection_make_freqtables($subcorpus = 'no_subcorpus', $restriction = 'no_restriction')
{
	global $corpus_sql_name;
	global $corpus_cqp_name;
	global $mysql_link;
	global $mysql_tempdir;
	global $instance_name;
	global $path_to_cwb;
	global $cwb_registry;
	global $username;
	
	/* this clause implements the override */
	if ($subcorpus != 'no_subcorpus')
		$restriction = 'no_restriction';
	
	/* list of attributes on which to make frequency tables */
	$attribute[] = 'word';
	foreach (get_corpus_annotations() as $a => $junk)
		$attribute[] = $a;

	unset($junk);

	/* now, we need a where-clause for all the text ids within the subsection */
	/* we also need to work out the base-name of this set of freq tables */
	if ($restriction != 'no_restriction')
	{
		/* this is NOT a named subcorpus, rather it is a subsection of the corpus whose freq list */
		/* is being compiled : so the name is just random */
		$text_list = translate_restrictions_to_text_list($restriction);
		$freqtables_base_name = "freq_sc_{$corpus_sql_name}_{$instance_name}";
	}
	else
	{
		/* a subcorpus has been named: check that it exists */
		$sql_query = "select * from saved_subcorpora where subcorpus_name = '"
			. mysql_real_escape_string($subcorpus) . "' 
			and corpus = '$corpus_sql_name'
			and user   = '$username'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		if (mysql_num_rows($result) < 1)
			exiterror_arguments($subcorpus, 'This subcorpus doesn\'t appear to exist!',
				__FILE__, __LINE__);

		$r = mysql_fetch_assoc($result);
		unset($result);
		
		if ( $r['text_list'] != '' )
			$text_list = $r['text_list'];
		else
			$text_list = translate_restrictions_to_text_list($r['restrictions']);

		$freqtables_base_name = "freq_sc_{$corpus_sql_name}_{$subcorpus}";

// is there a possibility of $subcorpus being  __last_restrictions?? depends whether that has been converted
// to restrictions in all cases where this function is called or not !!
	}
	/* and finish off the freqtable base name */
	$freqtables_base_name = freqtable_name_unique($freqtables_base_name);


	/* register this script as working to create a freqtable, after checking there is room for it */
	if ( check_db_max_processes('freqtable') === false )
		exiterror_toomanydbprocesses('freqtable');
	register_db_process($freqtables_base_name, 'freqtable');


		
	/* call a function to delete saved freqtables if there are too many of them */
	delete_saved_freqtables();


	/* whether restriction or sc, we now have a list of texts in the corpus, for which we need the */
	/* start-and-end positions in the FREQ TABLE CORPUS (as opposed ot the actual corpus) */

	/* first step: convert list of texts to an sql where clause */
	$textid_whereclause = translate_textlist_to_where($text_list);
	
	/* second step: check whether the specially-indexed cwb per-file freqlist corpus exists */
	if ( ! check_cwb_freq_index($corpus_sql_name) )
		exiterror_general("No CWB frequency-by-text index exists for corpus $corpus_sql_name!", 
			__FILE__, __LINE__);
	
	
	/* get the list of being-and-end points in the specially-indexed cwb per-file freqlist corpus */
	$sql_query = "select start, end from freq_text_index_$corpus_sql_name where $textid_whereclause";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	while ( ($r = mysql_fetch_row($result)) !== false )
		$regions[] = $r;
	unset($result);
	
	$n_regions = count($regions);
	
	/* store regions to be scanned in a temporary file */
	$regionfile = new CWBTempFile("/$mysql_tempdir/cwbscan_temp_$instance_name");
	foreach ($regions as $region)
		$regionfile->write(implode("\t", $region) . "\n");
	$regionfile->finish();
	$regionfile_filename = $regionfile->get_filename();
	
	
	$temp_table = "__freqmake_temptable_$instance_name";
	$temp_table_loadfile = "/$mysql_tempdir/__infile$temp_table";
	
	/* prepare command to extract the frequency lines for those bits of the corpus */
	$cmd_scancorpus = "/$path_to_cwb/cwb-scan-corpus -r /$cwb_registry -F __freq "
		. "-R $regionfile_filename {$corpus_cqp_name}__FREQ";
	foreach ($attribute as $att)
		$cmd_scancorpus .= " $att+0";
	$cmd_scancorpus .= " > $temp_table_loadfile";
	
	/* and run it */
	exec($cmd_scancorpus);
	
	/* close and delete the temp file containing the text regions*/
	$regionfile->close();
	
	/* ok, now to transfer that into mysql */
	
	/* set up temporary table for subcorpus frequencies */
	$sql_query = "CREATE TABLE `$temp_table` (
	   `freq` int(11) NOT NULL default 0";
	foreach ($attribute as $att)
		$sql_query .= ",
			`$att` varchar(210) NOT NULL default ''";
	foreach ($attribute as $att)
		$sql_query .= ",
			key(`$att`)";
	$sql_query .= ") CHARACTER SET utf8 COLLATE utf8_general_ci";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);
	
	/* import the base frequency list */
	database_disable_keys($temp_table);
	$sql_query = load_data_infile()." '$temp_table_loadfile' into table $temp_table fields escaped by ''";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);
	database_enable_keys($temp_table);
	
	unlink($temp_table_loadfile);
	

	/* now, create separate frequency lists for each att from the master table */

	foreach ($attribute as $att)
	{
		$att_sql_name = "{$freqtables_base_name}_{$att}";
		
		/* create the table */
		$sql_query = "create table $att_sql_name (
			freq int(11) unsigned default NULL,
			item varchar(210) NOT NULL,
			key(item)
			) CHARACTER SET utf8 COLLATE utf8_general_ci";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		unset($result);

		/* and fill it */
		database_disable_keys($att_sql_name);
		$sql_query = "insert into $att_sql_name 
			select sum(freq), $att from $temp_table
			group by $att";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		unset($result);
		database_enable_keys($att_sql_name);

	} /* end foreach $attribute */
	
	/* dump temporary table */
	$sql_query = "drop table $temp_table";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);
	
	$thistime = time();
	$thissize = get_freqtable_size($freqtables_base_name);

	$sql_query = "insert into saved_freqtables (
			freqtable_name,
			corpus,
			user,
			restrictions,
			subcorpus,
			create_time,
			ft_size
		) values (
			'$freqtables_base_name',
			'$corpus_sql_name',
			'$username',
			'" . mysql_real_escape_string($restriction) . "',
			'$subcorpus',
			$thistime,
			$thissize
		)";
		/* restriction must be escaped because it contains single quotes */
		/* no need to set `public`: it sets itself to 0 by default */

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);


	/* NB: freqtables share the dbs' register/unregister functions, with process_type 'freqtable' */
	unregister_db_process();
	
	/* return as an assoc array a copy of what has just gone into saved_freqtables */
	/* most of this will never be used, but data management is key */
	return array (
		'freqtable_name' => $freqtables_base_name,
		'corpus' => $corpus_sql_name,
		'user' => $username,
		'restrictions' => $restriction,
		'subcorpus' => $subcorpus,
		'create_time' => $thistime,
		'ft_size' => $thissize,
		'public' => 0
		);
} /* end of function subsection_make_freqtables() */







/* makes sure that the name you are about to give to a freqtable is unique */
function freqtable_name_unique($name)
{
	if (! is_string($name))
		exiterror_arguments($name, 'freqtable_name_unique() requires a string as argument $name!',
			__FILE__, __LINE__);

	global $mysql_link;
	
	while (1)
	{
		$sql_query = 'select freqtable_name from saved_freqtables where freqtable_name = \''
			. mysql_real_escape_string($name) . '\' limit 1';

		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);

		if (mysql_num_rows($result) == 0)
			break;
		else
		{
			unset($result);
			$name .= '0';
		}
	}
	return $name;
}






/* get the combined size of all freqtables relating to a specific subcorpus */
function get_freqtable_size($freqtable_name)
{
	global $mysql_link;
	
	$size = 0;
	
	$sql_query = "SHOW TABLE STATUS LIKE '$freqtable_name%'";
	/* note the " % " */

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	while ( ($info = mysql_fetch_assoc($result)) !== false)
		$size += ($info['Data_length'] + $info['Index_length']);

	return $size;
}




/* update the timestamp (note it's an int not really a TIMESTAMP selon mySQL!) */
function touch_freqtable($freqtable_name)
{
	global $mysql_link;
	
	if (! is_string($freqtable_name))
		exiterror_arguments($freqtable_name, 'touch_freqtable() requires a string as argument $dbname!',
			__FILE__, __LINE__);
		
	$time_now = time();
	
	$sql_query = "update saved_freqtables set create_time = $time_now 
		where freqtable_name = '$freqtable_name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);
}



/* returns the record (associative array) of the freqtable cluster for the subcorpus */
/* OR returns false */
/* note this function DOESNT check usernames, whereas check_freqtable_subcorpus DOES */
function check_freqtable_restriction($restrictions)
{
	global $corpus_sql_name;
	global $mysql_link;

	/* especially important because restricitons often contain single quotes */
	$restrictions = mysql_real_escape_string($restrictions);

	$sql_query = "select * from saved_freqtables 
		where corpus = '$corpus_sql_name' and restrictions = '$restrictions'";

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);

	if (mysql_num_rows($result) == 0)
		return false;
	else 
		return mysql_fetch_assoc($result);
}


/* returns the record (associative array) of the freqtable cluster for the subcorpus */
/* OR returns false */
function check_freqtable_subcorpus($subcorpus_name)
{
	global $corpus_sql_name;
	global $mysql_link;
	global $username;
	
	$subcorpus_name = mysql_real_escape_string($subcorpus_name);
	
	$sql_query = "select * from saved_freqtables 
		where corpus = '$corpus_sql_name' 
		and user = '$username' 
		and subcorpus = '$subcorpus_name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);

	if (mysql_num_rows($result) == 0)
		return false;
	else 
		return mysql_fetch_assoc($result);
}





/* delete a "cluster" of freq tables relating to a particular subsection, + their saved_fts entry */
function delete_freqtable($freqtable_name)
{
	global $mysql_link;
	
	$sql_query = "delete from saved_freqtables where freqtable_name = '$freqtable_name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);
	unset($result);
	
	$sql_query = "show tables like '$freqtable_name%'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);
	while ( ($r = mysql_fetch_row($result)) !== false )
		$to_del[] = $r[0];
	unset($result);
	foreach ($to_del as $d)
	{
		$sql_query = "drop table if exists $d";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
				__FILE__, __LINE__);
		unset($result);
	}
}






/* note: this function works ACROSS CORPORA */
function delete_saved_freqtables($protect_public_freqtables = true)
{
	global $mysql_link;
	global $mysql_freqtables_size_limit;
	
	if (!is_bool($protect_public_freqtables))
		exiterror_arguments($protect_public_freqtables, 
			"delete_saved_freqtables() needs a bool (or nothing) as its argument!", 
			__FILE__, __LINE__);

	/* step one: how many bytes in size is the freqtable cache RIGHT NOW? */
	$sql_query = "select sum(ft_size) from saved_freqtables";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	$row_array = mysql_fetch_array($result);
	$current_size = $row_array[0];
	unset($result, $row_array);

	if ($current_size <= $mysql_freqtables_size_limit)
		return;

	/* step 2 : get a list of deletable freq tables */
	$sql_query = "select freqtable_name, ft_size from saved_freqtables 
		" . ( $protect_public_freqtables ? " where public = 0" : "") . " 
		order by create_time asc";
	$del_result = mysql_query($sql_query, $mysql_link);
	if ($del_result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);


	while ($current_size > $mysql_freqtables_size_limit)
	{
		if ( ! ($current_ft_to_delete = mysql_fetch_assoc($del_result)) )
			break;
		
		delete_freqtable($current_ft_to_delete['freqtable_name']);
		$current_size -= $current_ft_to_delete['ft_size'];
	}
	
	if ($current_size > $mysql_freqtables_size_limit)
	{
		if ($protect_public_freqtables)
			delete_saved_freqtables(false);
		if ($current_size > $mysql_freqtables_size_limit)
			exiterror_dboverload();
	}
}







/* dump all cached freq tables from the database */
function clear_freqtables()
{
	global $mysql_link;
	
	$sql_query = "select freqtable_name from saved_freqtables";

	$del_result = mysql_query($sql_query, $mysql_link);

	if ($del_result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

	while ($current_ft_to_delete = mysql_fetch_assoc($del_result))
		delete_freqtable($current_ft_to_delete['freqtable_name']);
}






function publicise_this_corpus_freqtable($description)
{
	global $mysql_link;
	global $corpus_sql_name;

// not needed cos of magic quotes
//	$description = mysql_real_escape_string($description);
	
	$sql_query = "update corpus_metadata_fixed set public_freqlist_desc = '$description'
		where corpus = '$corpus_sql_name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}




function unpublicise_this_corpus_freqtable()
{
	global $mysql_link;
	global $corpus_sql_name;
	
	$sql_query = "update corpus_metadata_fixed set public_freqlist_desc = NULL
		where corpus = '$corpus_sql_name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}






function publicise_freqtable($name, $switch_public_on = true)
{
	global $username;
	global $mysql_link;

	/* only superusers are allowed to do this! */
	if (! user_is_superuser($username))
		return;

	$name = mysql_real_escape_string($name);
	
	$sql_query = "update saved_freqtables set public = " . ($switch_public_on ? 1 : 0) . " 
		where freqtable_name = '$name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}


/* this is just for convenience */
function unpublicise_freqtable($name)
{
	publicise_freqtable($name, false);
}




/* works across the system: returns an array of records, ie an array of associative arrays */
/* which could be empty */
/* the reason it returns an array of records rather than a list of names is that with just a */
/* list of names there would be no way to get at the freqtable_name that is the key ident    */
function list_public_freqtables()
{
	global $mysql_link;

	$sql_query = "select * from saved_freqtables where public = 1";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);

	$public_list = array();
	
	while ( ($r = mysql_fetch_assoc($result)) !== false)
		$public_list[] = $r;
	
	return $public_list;
}


/* works across the system, returns an assoc array of corpus handles  with public descriptions */
function list_public_whole_corpus_freqtables()
{
	global $mysql_link;

	$sql_query = "select corpus, public_freqlist_desc from corpus_metadata_fixed 
		where public_freqlist_desc IS NOT NULL";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);

	$list = array();
	while ( ($r = mysql_fetch_assoc($result)) !== false)
		$list[] = $r;
	
	return $list;
}



/* returns a list of handles of subcorpora belonging to this corpus and this user */
/* nb -- it's not a list of assoc-array-format records - just of names - could be empty */
function list_freqtabled_subcorpora()
{
	global $corpus_sql_name;
	global $mysql_link;
	global $username;

	$sql_query = "select subcorpus from saved_freqtables 
		where corpus = '$corpus_sql_name' and user = '$username' and subcorpus != 'no_subcorpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);

	$list = array();
	while ( ($r = mysql_fetch_row($result)) !== false)
		$list[] = $r[0];
	
	return $list;
}








?>