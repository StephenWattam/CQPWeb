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




/** makes sure that the name you are about to give to a db is unique */
function dbname_unique($dbname)
{
	if (! is_string($dbname))
		exiterror_arguments($dbname, 'dbname_unique() requires a string as argument $dbname!',
			__FILE__, __LINE__);
	
	while (1)
	{
		$sql_query = 'select dbname from saved_dbs where dbname = \''
			. mysql_real_escape_string($dbname) . '\' limit 1';
	
		$result = do_mysql_query($sql_query);

		if (mysql_num_rows($result) == 0)
			break;
		else
			$dbname .= chr(rand(0x41,0x5a));
	}
	return $dbname;	
}





/** creates a db in mysql for the named query of the specified type & returns its name*/
function create_db($db_type, $qname, $cqp_query, $restrictions, $subcorpus, $postprocess)
{
	global $cqp;
	global $cqpweb_tempdir;
	global $corpus_sql_name;
	global $corpus_cqp_name;
	
	global $username;
	
	global $colloc_db_premium;
	
	/* db_type-specific variables that the calling script must set if the db is of the relevent type */
	global $colloc_atts;
	global $colloc_range;
	
	/* check the connection to CQP */
	if (isset($cqp))
		$cqp_was_set = true;
	else
	{
		$cqp_was_set = false;
		connect_global_cqp();
	}


	$cqp->execute("set Context 0");


	/* create a name for the database */
	$dbname = dbname_unique('db_' . $db_type . '_' . $qname);
	
	/* register this script as working to create a DB, after checking there is room for it */
	if ( check_db_max_processes($db_type) === false )
		exiterror_toomanydbprocesses($db_type);
		
	register_db_process($dbname, $db_type);


	/* double-check that no table of this name exists */
	do_mysql_query("DROP TABLE IF EXISTS $dbname");
	

	/* call a function to delete dbs if they are taking up too much space*/
	delete_saved_dbs();


	/* get this user's distribution db size limit from their username details */
	$table_max = get_user_setting($username, 'max_dbsize');


	$num_of_rows = $cqp->querysize($qname);


	if ($db_type == 'colloc')
		$num_of_rows *= $colloc_db_premium;


	if ($num_of_rows > $table_max)
		exiterror_general("The action you have requested uses up a lot of diskspace.\n"
			. "Your limit is currently set to $table_max instances.\n"
			. "Please contact your system administrator if you need access to the information you requested.");


	/* name for a file containing table with result of tabulation command*/
	$tabfile = "/$cqpweb_tempdir/tab_{$db_type}_$qname";
	/* name for a file containing the awk script */
	$awkfile = "/$cqpweb_tempdir/awk_{$db_type}_$qname";

	if (is_file($tabfile))
		unlink($tabfile);
	if (is_file($awkfile))
		unlink($awkfile);

	/* get the tabulate, awk, and create table commands for this type of database */
	$commands = db_commands($dbname, $db_type, $qname);
	
	if ($commands['awk'])
	{
		/* if an awk script to intervene between cqp and mysql has been returned,
		 * create an awk script file ... */
		file_put_contents($awkfile, $commands['awk']);
		$tabulate_dest = "\"| awk -f '$awkfile' > '$tabfile'\"";
	}
	else
		$tabulate_dest = "'$tabfile'";



	/* create the empty table */
	do_mysql_query($commands['create']);


	/* create the tabulation */
	$cqp->execute("{$commands['tabulate']} > $tabulate_dest");
	
	/* We need to check if the CorpusCharset is other than ASCII/UTF8. 
	 * If it is, we need to call the library function that runs over it with iconv. */
	if (($corpus_charset = $cqp->get_corpus_charset()) != 'utf8')
	{
		$utf8_filename = $tabfile .'.utf8.tmp';
		
		change_file_encoding($tabfile, 
		                     $utf8_filename, 
		                     CQP::translate_corpus_charset_to_iconv($corpus_charset), 
		                     CQP::translate_corpus_charset_to_iconv('utf8') . '//TRANSLIT');
		
		unlink($tabfile);
		rename($utf8_filename, $tabfile);
		/* so now, either way, we need to work further on $tabfile. */
	}

	do_mysql_query("Alter table $dbname disable keys");

	do_mysql_infile_query($dbname, $tabfile, true);
	
	do_mysql_query("Alter table $dbname enable keys");


	/* and delete the file from which the table was created, plus the awk-script if there was one */
	if (is_file($tabfile))
		unlink($tabfile);
	if (is_file($awkfile))
		unlink($awkfile);


	/* now create a record of the db */

	$sql_query = "INSERT INTO saved_dbs (
			dbname, 
			user,
			create_time, 
			cqp_query,
			restrictions,
			subcorpus,
			postprocess,
			colloc_atts,
			colloc_range,
			" . /*sort_position,*/ "
			corpus,
			db_type,
			db_size
		) VALUES (
			'$dbname', 
			'$username',
			" . time() . ",
			'" . mysql_real_escape_string($cqp_query) . "',
			'" . mysql_real_escape_string($restrictions) . "',
			'" . mysql_real_escape_string($subcorpus) . "',
			"  . ( (bool)$postprocess ? "'$postprocess'" : 'NULL' ) . ",
			'" . ($db_type == 'colloc' ? mysql_real_escape_string($colloc_atts) : '') . "',
			"  . ($db_type == 'colloc' ? $colloc_range : '0') . ",
			"  . /*($db_type == 'sort' ? $sort_position : '0') . ", */ "
			'$corpus_sql_name',
			'$db_type',
			" . get_db_size($dbname) . "
		)";	
		/* note: sort position doesn't currently get used, so I have commented it out: sort databases are in corpus order */

		/* nb - don't need to real-escape db_type because exiterror will have been called by now
		   if it was a dodgy value */
	do_mysql_query($sql_query);
	
	
	unregister_db_process();


	if (!$cqp_was_set)
		disconnect_global_cqp();

	return $dbname;
}








function db_commands($dbname, $db_type, $qname)
{
	global $corpus_sql_collation;
	global $max_textid_length;
	
	switch($db_type)
	{
	case 'dist':
		/* IMPORTANT NOTE on the tabulate command for distributions
		   
		   I could have just used "group" and made the amount of info to be cached in SQL much smaller
		   HOWEVER at some point in the future it may be possible to build in exploitation of s-attributes
		   in which case the per-solution listing will be needed
		*/
		$tabulate_command = "tabulate $qname match text_id, match, matchend";
		$awk_script = false;
		$create_statement = "CREATE TABLE $dbname (
			text_id varchar($max_textid_length),
			beginPosition int,
			endPosition int,
			refnumber MEDIUMINT AUTO_INCREMENT,
			key(refnumber),
			key(text_id)
			) CHARACTER SET utf8 COLLATE utf8_bin";
			/* note the use of a binary collation for distribution DBs, since
			 * they always contain text_ids, not word or tag material.
			 */
		break;
	
	
	case 'colloc':
		global $colloc_atts;
		global $colloc_range;
		$att_array = ( $colloc_atts == '' ? NULL : explode('~', $colloc_atts) );
		$num_of_atts = count($att_array) + 1;	/* count returns 0 if $att_array is NULL */
		
		/* create tabulate command */
		$tabulate_command = "tabulate $qname match, matchend, match text_id";

		for ($c = -$colloc_range ; $c <= -1 ; $c++)
		{
			$tabulate_command .= ", match[$c] word";
			if ($att_array !== NULL)
				foreach ($att_array as $att)
					$tabulate_command .= ", match[$c] $att";
		}
		for ($c = 1 ; $c <= $colloc_range ; $c++)
		{
			$tabulate_command .= ", matchend[$c] word";
			if ($att_array !== NULL)
				foreach ($att_array as $att)
					$tabulate_command .= ", matchend[$c] $att";
		}
	
		/* create awk field variables */
		$af['match'] = '$1';
		$af['matchend'] = '$2';
		$af['text_id'] = '$3';
		/* the next field after the pre-sets above: $f increments after every use */
		$f = 4;

		/* and now use them to write the awk script that converts one row to many */
		$awk_script = 'BEGIN{ OFS = FS = "\t" }' . "\n";
		
		for ($i = -$colloc_range ; $i <= $colloc_range ; $i++)
		/* start at 1 cos no data in the tabulate output for $i == 0  */
		{
			if ($i == 0)
				continue;
			/* mysql fields:          text_id       beginPosition    endPosition    refnumber dist  word */
			$awk_script .= "{ print {$af['text_id']}, {$af['match']}, {$af['matchend']}, NR-1, $i, \${$f}";
			$f++;	/* increment $f after each field is set */
			if ($att_array !== NULL)
				foreach($att_array as $att)
				{
					$awk_script .= ", \${$f}";
					$f++;
				}
			$awk_script .= " }\n";
		}
		
		$create_statement = "CREATE TABLE $dbname (
			text_id varchar($max_textid_length),
			beginPosition int,
			endPosition int,
			refnumber BIGINT NOT NULL,
			dist SMALLINT NOT NULL,
			word varchar(40) NOT NULL
			";
		/* add a field for each positional attribute */
		if ($att_array !== NULL)
			foreach ($att_array as $att)
				$create_statement .= ",
					$att varchar(40) NOT NULL";
		$create_statement .= "
			) CHARACTER SET utf8 COLLATE $corpus_sql_collation";
		
		break;


	case 'sort':
		$att = get_corpus_metadata('primary_annotation');
		$no_att = (($att === NULL || $att === '') ? true : false);

		$tabulate_command = "tabulate $qname "
		    . "match[-5] word, match[-4] word, match[-3] word, match[-2] word, match[-1] word, "
		    . "matchend[1] word, matchend[2] word, matchend[3] word, matchend[4] word, matchend[5] word, "
		    . ($no_att ? '' : "match[-5] $att, match[-4] $att, match[-3] $att, match[-2] $att, match[-1] $att, ")
		    . ($no_att ? '' : "matchend[1] $att, matchend[2] $att, matchend[3] $att, matchend[4] $att, matchend[5] $att, ")
		    . "match .. matchend word, "
			. ($no_att ? '' : "match .. matchend $att, ")
		    . "match text_id, match, matchend";
		
		$awk_script = false;
		
		$att_fields = $no_att ? '' :
			"tagbefore5 varchar(40) NOT NULL,
			tagbefore4 varchar(40) NOT NULL,
			tagbefore3 varchar(40) NOT NULL,
			tagbefore2 varchar(40) NOT NULL,
			tagbefore1 varchar(40) NOT NULL,
			tagafter1 varchar(40) NOT NULL,
			tagafter2 varchar(40) NOT NULL,
			tagafter3 varchar(40) NOT NULL,
			tagafter4 varchar(40) NOT NULL,
			tagafter5 varchar(40) NOT NULL,";
		$att_nodefield = $no_att ? '' : "tagnode varchar(200) NOT NULL,";
			
		$create_statement = "CREATE TABLE $dbname (
			before5 varchar(40) NOT NULL,
			before4 varchar(40) NOT NULL,
			before3 varchar(40) NOT NULL,
			before2 varchar(40) NOT NULL,
			before1 varchar(40) NOT NULL,
			after1 varchar(40) NOT NULL,
			after2 varchar(40) NOT NULL,
			after3 varchar(40) NOT NULL,
			after4 varchar(40) NOT NULL,
			after5 varchar(40) NOT NULL,
			$att_fields
			node varchar(200) NOT NULL,
			$att_nodefield
			text_id varchar($max_textid_length),
			beginPosition int,
			endPosition int,
			refnumber MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key(refnumber)
			) CHARACTER SET utf8 COLLATE $corpus_sql_collation";

		break;
	
	case 'catquery':
			
		$tabulate_command = "tabulate $qname match, matchend";
		$awk_script = false;
		$create_statement = "CREATE TABLE $dbname (
			beginPosition int,
			endPosition int,
			refnumber MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category varchar(40),
			key(refnumber),
			key(category)
			) CHARACTER SET utf8 COLLATE $corpus_sql_collation";
		
		break;

	default:
		exiterror_general("db_commands was called with database type '$db_type'.
			This is not a recognised type of database!", __FILE__, __LINE__);
		break;
	}

	return array(
		'tabulate'	=> $tabulate_command, 
		'awk' 		=> $awk_script, 
		'create' 	=> $create_statement
		);
}







/** does nothing to the specified db, but refreshes its create_time to = now */
function touch_db($dbname)
{
	$dbname = mysql_real_escape_string($dbname);
		
	$time_now = time();
	
	do_mysql_query("update saved_dbs set create_time = $time_now where dbname = '$dbname'");
}





function get_db_size($dbname)
{
	$info = mysql_fetch_assoc(do_mysql_query("SHOW TABLE STATUS LIKE '$dbname'"));

	return $info['Data_length'] + $info['Index_length'];
}


/**
 * returns AN ASSOCIATIVE ARRAY for the located db's record
 * or false if it could not be found 
 */
function check_dblist_dbname($dbname)
{
	$sql_query = "SELECT * from saved_dbs where dbname = '" 
		. mysql_real_escape_string($dbname) . "' limit 1";
		
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) == 0)
		return false;
	
	return mysql_fetch_assoc($result);
}



/**
 * returns AN ASSOCIATIVE ARRAY for the located db's record 
 * or false if it could not be found 
 */
function check_dblist_parameters($db_type, $cqp_query, $restrictions, $subcorpus, $postprocess,
	$colloc_atts = '', $colloc_range = 0, $sort_position = 0)
{
	global $corpus_sql_name;
	
	/* set up the options that are particular to certain types of db */
	switch ($db_type)
	{
		case 'dist':
			/* the function will prob have been called with default values, but just in case: */
			$colloc_atts = '';
			$colloc_range = 0;
			$sort_position = 0;
			break;
		case 'colloc':
			if ($colloc_range == 0)
				exiterror_general("The collocation range cannot be zero!", __FILE__, __LINE__);
			/* just in case not defaulted */
			$sort_position = 0;
			break;
		case 'sort':
			// for now ......... till i work out what s_p is for
			// do I even need sort_position
			$sort_position = 0;
			break;
		default:
			exiterror_general("check_dblist_parameters was called with database type '$db_type'.
				This is not a recognised type of database!", __FILE__, __LINE__);
	}
	
	/* now look for a db that matches all of that! */
	$sql_query = "SELECT * from saved_dbs 
		where db_type = '$db_type' and corpus = '$corpus_sql_name"
		. "' and cqp_query = '" . mysql_real_escape_string($cqp_query) 
		. "' and restrictions = '" . mysql_real_escape_string($restrictions)
		. "' and subcorpus = '" . mysql_real_escape_string($subcorpus) 
		. "' and postprocess " . ( (bool)$postprocess ? "= '$postprocess'" : 'is NULL' )
		. "  and colloc_atts = '" . mysql_real_escape_string($colloc_atts)
		. "' and colloc_range = $colloc_range"
		. "  and sort_position = $sort_position"
		. "  limit 1";

	$result = do_mysql_query($sql_query);

	
	if (mysql_num_rows($result) == 0)
		return false;
	
	return mysql_fetch_assoc($result);
}



/**
 * Deletes a database from the system.
 */
function delete_db($dbname)
{
	$dbname = mysql_real_escape_string($dbname);
	do_mysql_query("DROP TABLE IF EXISTS $dbname");
	do_mysql_query("DELETE FROM saved_dbs where dbname = '$dbname'");
}


/**
 * Deletes all databases that are associated with a given query-name.
 * 
 * This would typically be done if the query itself is being deleted
 * and we DON'T want to save associated DBs for a future similar query
 * e.g. if we're overwriting a separated-out catquery.
 */
function delete_dbs_of_query($qname)
{
	$qname = mysql_real_escape_string($qname);	
	
	$result = do_mysql_query("select dbname from saved_dbs where dbname like 'db_%_$qname'");
	while (false !== ($r = mysql_fetch_row($result)))
		delete_db($r[0]);
}


/** 
 * note: this function works ACROSS CORPORA && across types of db (except catquery) 
 */
function delete_saved_dbs()
{
	global $mysql_db_size_limit;
	
	/* step one: how many bytes in size is the db cache RIGHT NOW? */
	$sql_query = "select sum(db_size) from saved_dbs";
	list($current_size) = mysql_fetch_row(do_mysql_query($sql_query));

	if ($current_size <= $mysql_db_size_limit)
		return;
	
	/* step 2 : get a list of deletable tables 
	 * note that catquery dbnames are excluded 
	 * they must be deleted via their special table 
	 * because otherwise entries are left in that table */
	$sql_query = "select dbname, db_size from saved_dbs 
		where saved = 0 
		and dbname not like 'db_catquery%'
		order by create_time asc";
	$del_result = do_mysql_query($sql_query);

	while ($current_size > $mysql_db_size_limit)
	{
		if ( ! ($current_db_to_delete = mysql_fetch_assoc($del_result)) )
			break;
		
		delete_db($current_db_to_delete['dbname']);
		$current_size -= $current_db_to_delete['db_size'];
	}
	
	if ($current_size > $mysql_db_size_limit)
		exiterror_dboverload();
}



/**
 * dump all cached dbs from the database INCLUDING ones where the "saved" field is flagged
 */
function clear_dbs($type = '__NOTYPE')
{
	
	$sql_query = "select dbname from saved_dbs";
	if ($type != '__NOTYPE')
		$sql_query .= " where db_type = '" . mysql_real_escape_string($type) . "'";

	$del_result = do_mysql_query($sql_query);

	while ($current_db_to_delete = mysql_fetch_assoc($del_result))
		delete_db($current_db_to_delete['dbname']);
}













/* process functions */



/** 
 * Checks for maximum number of concurrent processes of the sepcified type;
 * returns true if there is space for another process, false if there is not.
 */
function check_db_max_processes($process_type)
{
	global $mysql_process_limit;
	
	$sql_query = "select process_id from mysql_processes where process_type = '"
		. mysql_real_escape_string($process_type) . "'";
	$result = do_mysql_query($sql_query);
	
	$current_processes = mysql_num_rows($result);
	
	if ($current_processes >= $mysql_process_limit[$process_type])
	{
		/* check whether there are dead entries in the mysql_processes table */
		$dead_processes = 0 ;
		
		$os_pids = shell_exec( 'ps -e' );
		
		/* check each row of the result */
		while (($pidrow = mysql_fetch_row($result)) !== false)
		{
			if (preg_match("/\s{$pidrow[0]}\s/", $os_pids) == 0)
			{
				/* the pid was NOT found on the list from the OS */
				$dead_processes++;
				unregister_db_process($pidrow[0]);
			}
		}
		if ($dead_processes > 0)
			return true;			/* the list was full, but 1+ process was found to be dead */
		else
			return false;			/* the list was full, and no dead processes */
	}
	else
		return true;				/* the list was not full */
}
	


/** 
 * Adds the current instance of PHP's process-id to a list of concurrent db processes.
 * 
 * Note: is dbname actually needed? it's prob for diagnostic purposes and it does no harm.
 */
function register_db_process($dbname, $process_type, $process_id = '___THIS_SCRIPT')
{
	$dbname = mysql_real_escape_string($dbname);
	$process_type = mysql_real_escape_string($process_type);
	if ($process_id == '___THIS_SCRIPT')
		$process_id = getmypid();
	else
		$process_id = mysql_real_escape_string($process_id);
	$begin_time = time();
	$sql_query = "insert into mysql_processes (dbname, begin_time, process_type, process_id)
		values ('$dbname', $begin_time, '$process_type', '$process_id' )";
	do_mysql_query($sql_query);
	//TODO maybe also record the instance name, and the MySQL connection-id?
}



/** Declares a process run by the current script complete; removes it from the list of db processes */
function unregister_db_process($process_id = '___THIS_SCRIPT')
{
	if ($process_id == '___THIS_SCRIPT')
		$process_id = getmypid();
	else
		$process_id = (int)$process_id;
	do_mysql_query("delete from mysql_processes where process_id = '$process_id'");
}



?>
