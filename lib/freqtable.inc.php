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





/**
 * @file
 * 
 * Library of functions for dealing with frequency tables for corpora and
 * subcorpora.
 * 
 * These are stored (largely) in MySQL.
 * 
 * Frequency table naming convention:
 * 
 * for a corpus:	freq_corpus_{$corpus}_{$att}
 * 
 * for a subcorpus:	freq_sc_{$corpus}_{$instance_name}_{$att}
 * 
 */



/**
 * Creates MySQL frequency tables for each attribute in a corpus;
 * any pre-existing tables are deleted.
 */
function corpus_make_freqtables()
{
	global $path_to_cwb;
	global $cwb_registry;
	global $corpus_sql_name;
	global $corpus_sql_collation;
	global $corpus_cqp_name;
	global $cqpweb_tempdir;
	global $username;
	global $cqp;
	
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
	do_mysql_query($sql_query);

	$sql_query = "CREATE TABLE $temp_tablename (
		freq int(11) unsigned default NULL";
	foreach ($attribute as $att)
		$sql_query .= ",
			$att varchar(210) NOT NULL";
	foreach ($attribute as $att)
		$sql_query .= ",
			key ($att)";
	$sql_query .= "
		) CHARACTER SET utf8 COLLATE $corpus_sql_collation";

	do_mysql_query($sql_query);
	

	/* for convenience, $filename is absolute */
	$filename = "/$cqpweb_tempdir/____$temp_tablename.tbl";

	/* now, use cwb-scan-corpus to prepare the input */	
	$cwb_command = "/$path_to_cwb/cwb-scan-corpus -r /$cwb_registry -o $filename -q $corpus_cqp_name";
	foreach ($attribute as $att)
		$cwb_command .= " $att";
	$status = 0;
	exec($cwb_command . ' 2>&1', $junk, $status);
	if ($status != 0)
		exiterror_general("cwb-scan-corpus error!\n" . implode("\n", $junk), __FILE__, __LINE__);
	unset($junk);
	
	/* We need to check if the CorpusCharset is other than ASCII/UTF8. 
	 * If it is, we need to call the library function that runs over it with iconv. */
	if (($corpus_charset = $cqp->get_corpus_charset()) != 'utf8')
	{
		$utf8_filename = $filename .'.utf8.tmp';
		
		change_file_encoding($filename, 
		                     $utf8_filename, 
		                     CQP::translate_corpus_charset_to_iconv($corpus_charset), 
		                     CQP::translate_corpus_charset_to_iconv('utf8') . '//TRANSLIT');
		
		unlink($filename);
		rename($utf8_filename, $filename);
		/* so now, either way, we need to work further on $filename. */
	}


	database_disable_keys($temp_tablename);
	do_mysql_infile_query($temp_tablename, $filename, true);
	database_enable_keys($temp_tablename);

	unlink($filename);

	/* ok - the temporary, ungrouped frequency table is in memory 
	 * each line is a unique binary line across all the attributes
	 * it needs grouping differently for each attribute 
	 * (this will also take care of putting 'the', 'The' and 'THE' together
	 * if the collation does that) */

	foreach ($attribute as $att)
	{
		$sql_tablename = "freq_corpus_{$corpus_sql_name}_$att";

		do_mysql_query("DROP TABLE if exists $sql_tablename");

		$sql_query = "CREATE TABLE $sql_tablename (
			freq int(11) unsigned default NULL,
			item varchar(210) NOT NULL,
			primary key (item)
			) CHARACTER SET utf8 COLLATE $corpus_sql_collation";
		do_mysql_query($sql_query);
		
		database_disable_keys($sql_tablename);
		$sql_query = "
			INSERT INTO $sql_tablename 
				select sum(freq) as f, $att as item 
					from $temp_tablename
					group by $att";

		do_mysql_query($sql_query);
		database_enable_keys($sql_tablename);
	}

	/* delete temporary ungrouped table */
	do_mysql_query("DROP TABLE if exists $temp_tablename");
}







/**
 * Creates frequency lists for a --subsection only-- of the current corpus, 
 * ie a restriction or subcorpus.
 * 
 * Note that the specification of a subcorpus trumps restrictions. 
 * (As elsewhere, e.g. when a query happens.) 
 */
function subsection_make_freqtables($subcorpus = 'no_subcorpus', $restriction = 'no_restriction')
{
	global $corpus_sql_name;
	global $corpus_sql_collation;
	global $corpus_cqp_name;
	global $cqpweb_tempdir;
	global $instance_name;
	global $path_to_cwb;
	global $cwb_registry;
	global $username;
	global $cqp;
	
	/* this clause implements the override (get_freq_index_postitionlist_for_subsection does this too
	 * but we need the variables overridden here....) */
	if ($subcorpus != 'no_subcorpus')
		$restriction = 'no_restriction';
	
	/* list of attributes on which to make frequency tables */
	$attribute[] = 'word';
	foreach (get_corpus_annotations() as $a => $junk)
		$attribute[] = $a;
	unset($junk, $a);


	/* From the unique instance name, create a freqtable base name */
	$freqtables_base_name = freqtable_name_unique("freq_sc_{$corpus_sql_name}_{$instance_name}");


	/* register this script as working to create a freqtable, after checking there is room for it */
	if ( check_db_max_processes('freqtable') === false )
		exiterror_toomanydbprocesses('freqtable');
	register_db_process($freqtables_base_name, 'freqtable');


	/* first step: save regions to be scanned to a temp file */
	$regionfile = new CQPInterchangeFile("/$cqpweb_tempdir");
	$region_list_array = get_freq_index_postitionlist_for_subsection($subcorpus, $restriction);
	
	foreach ($region_list_array as $reg)
		$regionfile->write("{$reg[0]}\t{$reg[1]}\n");

	unset($region_list_array);
	$regionfile->finish();

	/* second step we can get ready to build the intermediate table in MySQL */
	$temp_table = "__freqmake_temptable_$instance_name";
	$temp_table_loadfile = "/$cqpweb_tempdir/__infile$temp_table";
	
	/* Check cache contents. (We do this before building, in order that we don't overflow the cache
	 * by TOO much in the intermediate step when the new freq table is being built.) */
	delete_saved_freqtables();


	/* run command to extract the frequency lines for those bits of the corpus */
	$cmd_scancorpus = "/$path_to_cwb/cwb-scan-corpus -r /$cwb_registry -F __freq "
		. "-R " . $regionfile->get_filename()
		. " {$corpus_cqp_name}__FREQ";
	foreach ($attribute as $att)
		$cmd_scancorpus .= " $att+0";
	$cmd_scancorpus .= " > $temp_table_loadfile";
	
	exec($cmd_scancorpus);
	
	/* close and delete the temp file containing the text regions */
	$regionfile->close();
	
	/* We need to check if the CorpusCharset is other than ASCII/UTF8. 
	 * If it is, we need to open & cycle iconv on the whole thing.     */
	if (($corpus_charset = $cqp->get_corpus_charset()) != 'utf8')
	{
		$utf8_filename = $temp_table_loadfile .'.utf8.tmp';
		
		change_file_encoding($temp_table_loadfile, 
		                     $utf8_filename, 
		                     CQP::translate_corpus_charset_to_iconv($corpus_charset), 
		                     CQP::translate_corpus_charset_to_iconv('utf8') . '//TRANSLIT');
		
		unlink($temp_table_loadfile);
		rename($utf8_filename, $temp_table_loadfile);
	}
	
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
	$sql_query .= ") CHARACTER SET utf8 COLLATE $corpus_sql_collation";
	do_mysql_query($sql_query);

	
	/* import the base frequency list */
	database_disable_keys($temp_table);
	do_mysql_infile_query($temp_table, $temp_table_loadfile, true);
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
			) CHARACTER SET utf8 COLLATE $corpus_sql_collation";
		do_mysql_query($sql_query);


		/* and fill it */
		database_disable_keys($att_sql_name);
		$sql_query = "insert into $att_sql_name 
			select sum(freq), $att from $temp_table
			group by $att";
		do_mysql_query($sql_query);
		database_enable_keys($att_sql_name);

	} /* end foreach $attribute */
	
	/* dump temporary table */
	do_mysql_query("drop table $temp_table");

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
	do_mysql_query($sql_query);


	/* NB: freqtables share the dbs' register/unregister functions, with process_type 'freqtable' */
	unregister_db_process();


	/* Check cache contents AGAIN (in case the newly built frequency table has overflowed the cache limit */
	delete_saved_freqtables();

	
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







/** 
 * Makes sure that the name you are about to give to a freqtable is unique. 
 * 
 * Keeps adding random letters to the end of it if it is not. The name returned
 * is therefore deifnitely always unique across all corpora.
 */
function freqtable_name_unique($name)
{
	while (true)
	{
		$sql_query = 'select freqtable_name from saved_freqtables where freqtable_name = \''
			. mysql_real_escape_string($name) . '\' limit 1';

		$result = do_mysql_query($sql_query);

		if (mysql_num_rows($result) == 0)
			break;
		else
		{
			unset($result);
			$name .= chr(rand(0x41,0x5a));
		}
	}
	return $name;
}






/** gets the combined size of all freqtables relating to a specific subcorpus */
function get_freqtable_size($freqtable_name)
{	
	$size = 0;
	
	$sql_query = "SHOW TABLE STATUS LIKE '$freqtable_name%'";
	/* note the " % " */

	$result = do_mysql_query($sql_query);

	while ( ($info = mysql_fetch_assoc($result)) !== false)
		$size += ($info['Data_length'] + $info['Index_length']);

	return $size;
}




/** updates the timestamp (note it's an int, not really a TIMESTAMP as per mySQL!) */
function touch_freqtable($freqtable_name)
{	
	$freqtable_name = mysql_real_escape_string($freqtable_name);
		
	$time_now = time();
	
	$sql_query = "update saved_freqtables set create_time = $time_now 
		where freqtable_name = '$freqtable_name'";
	do_mysql_query($sql_query);
}



/**
 * Returns the record (associative array) of the freqtable cluster for the subcorpus
 * OR returns false.
 * 
 * Note this function DOESN'T check usernames, whereas check_freqtable_subcorpus() DOES (by default).
 */
function check_freqtable_restriction($restrictions)
{
	global $corpus_sql_name;

	/* especially important because restrictions often contain single quotes */
	$restrictions = mysql_real_escape_string($restrictions);

	$sql_query = "select * from saved_freqtables 
		where corpus = '$corpus_sql_name' and restrictions = '$restrictions'";

	$result = do_mysql_query($sql_query);

	if (mysql_num_rows($result) > 0)
		return mysql_fetch_assoc($result);
	else 
	{
		/* check for a subcorpus whose freq list was based on these restrictions */
		$sql_query = "select subcorpus_name from saved_subcorpora 
			where corpus = '$corpus_sql_name' 
			and restrictions = '$restrictions' 
			and subcorpus_name !='__last_restrictions'";
		$result = do_mysql_query($sql_query);
		if (mysql_num_rows($result) > 0)
		{
			/* someone has a subcorp with these restrictions, 
			 * so let's see if it has a freqtable..... */
			while(false !== ($r = mysql_fetch_row($result)))
				if (false !== ($check = check_freqtable_subcorpus($r[0], false)))
					return $check;
			return false;
		}
		else
			/* no one has a subcorp with these restrictions */
			return false;
	}
}


/**
 * Returns the record (associative array) of the freqtable cluster for the subcorpus
 * OR returns false. 
 * 
 * If $with_username_check is true, only a freqtable for a subcorpus owned by the current 
 * user will be returned. If false, it will look for freqtables of subcorpora owned by other users too.
 */
function check_freqtable_subcorpus($subcorpus_name, $with_user_name_check = true)
{
	global $corpus_sql_name;
	global $username;
	
	$userline = ($with_user_name_check ? "and user = '$username'" : '');
	
	$subcorpus_name = mysql_real_escape_string($subcorpus_name);
	
	$sql_query = "select * from saved_freqtables 
		where corpus = '$corpus_sql_name' 
		$userline
		and subcorpus = '$subcorpus_name'";
	$result = do_mysql_query($sql_query);

	if (mysql_num_rows($result) == 0)
		return false;
	else 
		return mysql_fetch_assoc($result);
}





/**
 * Deletes a "cluster" of freq tables relating to a particular subsection, + their entry
 * in the saved_freqtables list.
 */
function delete_freqtable($freqtable_name)
{
	$freqtable_name = mysql_real_escape_string($freqtable_name);
	
	/* delete no tables if this is not a real entry in the freqtable db
	 * (this check should in theory not be needed, but let's make sure) */
	$result = do_mysql_query("select freqtable_name from saved_freqtables where freqtable_name = '$freqtable_name'");
	if (1 > mysql_num_rows($result))
		return;
	
	$result = do_mysql_query("show tables like '{$freqtable_name}_%'");

	while ( ($r = mysql_fetch_row($result)) !== false )
		do_mysql_query("drop table if exists ${r[0]}");
	
	do_mysql_query("delete from saved_freqtables where freqtable_name = '$freqtable_name'");
}






/** 
 * Checks the size of the cache of saved frequency tables, and if it is higher
 * than the size limit (in global config variable $mysql_freqtables_size_limit),
 * then old frequency tables are deleted until the size falls below the said limit.
 * 
 * By default public frequency tables will not be deleted from the cache, unless
 * there is no other way to get the cache down to size. If you want public
 * frequency tables to be equally "vulnerable", pass in false as the argument.
 * 
 * Note: this function works ACROSS CORPORA.
 * 
 * @see $mysql_freqtables_size_limit
 */
function delete_saved_freqtables($protect_public_freqtables = true)
{
	global $mysql_freqtables_size_limit;
	
	if (!is_bool($protect_public_freqtables))
		exiterror_arguments($protect_public_freqtables, 
			"delete_saved_freqtables() needs a bool (or nothing) as its argument!", 
			__FILE__, __LINE__);

	/* step one: how many bytes in size is the freqtable cache RIGHT NOW? */
	$result = do_mysql_query("select sum(ft_size) from saved_freqtables");
	list($current_size) = mysql_fetch_row($result);

	if ($current_size <= $mysql_freqtables_size_limit)
		return;

	/* step 2 : get a list of deletable freq tables */
	$sql_query = "select freqtable_name, ft_size from saved_freqtables 
		" . ( $protect_public_freqtables ? " where public = 0" : "") . " 
		order by create_time asc";
	$del_result = do_mysql_query($sql_query);

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







/** Dumps all cached freq tables from the database (unconditional cache clear). */
function clear_freqtables()
{
	$del_result = do_mysql_query("select freqtable_name from saved_freqtables");

	while ($current_ft_to_delete = mysql_fetch_assoc($del_result))
		delete_freqtable($current_ft_to_delete['freqtable_name']);
}






function publicise_this_corpus_freqtable($description)
{
	global $corpus_sql_name;

	$description = mysql_real_escape_string($description);
	
	$sql_query = "update corpus_metadata_fixed set public_freqlist_desc = '$description'
		where corpus = '$corpus_sql_name'";
		
	do_mysql_query($sql_query);
}




function unpublicise_this_corpus_freqtable()
{
	global $corpus_sql_name;
	
	$sql_query = "update corpus_metadata_fixed set public_freqlist_desc = NULL
		where corpus = '$corpus_sql_name'";
		
	do_mysql_query($sql_query);
}






function publicise_freqtable($name, $switch_public_on = true)
{
	global $username;

	/* only superusers are allowed to do this! */
	if (! user_is_superuser($username))
		return;

	$name = mysql_real_escape_string($name);
	
	$sql_query = "update saved_freqtables set public = " . ($switch_public_on ? 1 : 0) . " 
		where freqtable_name = '$name'";
	
	do_mysql_query($sql_query);

}


/* this is just for convenience */
function unpublicise_freqtable($name)
{
	publicise_freqtable($name, false);
}




/**
 * Works across the system: returns an array of records, ie an array of associative arrays
 * which could be empty.
 * 
 * the reason it returns an array of records rather than a list of names is that with just a 
 * list of names there would be no way to get at the freqtable_name that is the key ident    
 */
function list_public_freqtables()
{
	$result = do_mysql_query("select * from saved_freqtables where public = 1");

	$public_list = array();
	
	while ( ($r = mysql_fetch_assoc($result)) !== false)
		$public_list[] = $r;
	
	return $public_list;
}


/** Returns an assoc array of corpus handles with public descriptions; works across the system */
function list_public_whole_corpus_freqtables()
{
	$sql_query = "select corpus, public_freqlist_desc from corpus_metadata_fixed 
		where public_freqlist_desc IS NOT NULL";
		
	$result = do_mysql_query($sql_query);

	$list = array();
	while ( ($r = mysql_fetch_assoc($result)) !== false)
		$list[] = $r;
	
	return $list;
}



/**
 * Returns a list of handles of subcorpora belonging to this corpus and this user.
 * 
 * Nb -- it's not a list of assoc-array-format records - just an array of names - could be empty. 
 */
function list_freqtabled_subcorpora()
{
	global $corpus_sql_name;
	global $username;

	$sql_query = "select subcorpus from saved_freqtables 
		where corpus = '$corpus_sql_name' and user = '$username' and subcorpus != 'no_subcorpus'";
	$result = do_mysql_query($sql_query);

	$list = array();
	while ( ($r = mysql_fetch_row($result)) !== false)
		$list[] = $r[0];
	
	return $list;
}


/**
 * Find the freqtable name for a given subcorpus belonging to this user and this corpus. 
 * 
 * Returns false if it was not found.
 */
function get_subcorpus_freqtable($subcorpus)
{
	global $corpus_sql_name;
	global $username;
	
	$subcorpus = mysql_real_escape_string($subcorpus);
	
	$sql_query = "select freqtable_name from saved_freqtables 
		where corpus = '$corpus_sql_name' and user = '$username' and subcorpus = '$subcorpus'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) < 1)
		return false;
	
	list($name) = mysql_fetch_row($result);
	
	return $name;
}





?>
