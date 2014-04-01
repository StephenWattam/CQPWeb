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






/** @file this file contain functions that deal with the cache, saved queries, temp files, etc. */



/** Returns true if a CQP temp file exists with that qname in its filename, otherwise false */
function cqp_file_exists($qname)
{
	return file_exists(cqp_file_path($qname));
}


/** returns size of a CQP temp file (including 0 if said file existeth not) */
function cqp_file_sizeof($qname)
{
	$s = filesize(cqp_file_path($qname));
	return ( $s === false ? 0 : $s );
}

/**
 * Removes any CQP-query file in the cache with $qname in its filename after
 * the ":" (i.e. works across corpora); returns true if the file existed and
 * was deleted, false if it did not exist or if deletion failed.
 */
function cqp_file_unlink($qname)
{
	if (false === ($f = cqp_file_path($qname)))
		return false;
	if ( file_exists($f) )
		return unlink($f);
	else
		return false;
}

/**
 * Copies the cache file corresponding to oldqname as newqname; if a file
 * relating to newqname exists already, it will NOT be overwritten.
 * 
 * Returns true on success and false on failure.
 */
function cqp_file_copy($oldqname, $newqname)
{
	$of = cqp_file_path($oldqname);
	$nf = preg_replace("/:$oldqname\z/", ":$newqname", $of);
	if ( file_exists($of) && ! file_exists($nf) )
		return copy($of, $nf);
	else
		return false;
}

/**
 * Returns the path on disk of the cache file corresponding to the specified
 * query identifier (qname), or false if no such file appears to exist.
 * 
 * Works across corpora.
 */
function cqp_file_path($qname)
{
	global $Config;
	
	$globbed = glob("{$Config->dir->cache}/*:$qname");
	if (empty($globbed))
		return false;
	else
		return $globbed[0];
}


/** Returns a blank associative array with named keys for each for the fields */
function blank_cache_assoc()
{
	$result = do_mysql_query('select * from saved_queries limit 1');

	$blank = array();
	$n = mysql_num_fields($result);
	for ( $i = 0 ; $i < $n ; $i++ )
	{
		$str = mysql_field_name($result, $i);
		$blank[$str] = "";
	}
	return $blank;
}




/** 
 * Makes sure that the name you are about to put into cache is unique.
 * 
 * Keeps adding random letters to the end of it if it is not.
 * By "Unique" we mean "unique across all corpora"; since all new qnames
 * should be based on the instance name, and the instance name should be
 * time-unique on a microsecond scale, this is really just belt-and-braces.
 * 
 * Typical usage: $qname = qname_unique($qname); 
 */
function qname_unique($qname)
{

	while (true)
	{
		$sql_query = 'select query_name from saved_queries where query_name = \''
			. mysql_real_escape_string($qname) . '\' limit 1';
	
		$result = do_mysql_query($sql_query);

		if (mysql_num_rows($result) == 0)
			break;

		$qname .= chr(rand(0x41,0x5a));
	}
	return $qname;	
}







/**
 * Write a query to the cache table : assumption - this query has just been run in CQP.
 * 
 * returns FALSE if there was some kind of error creating the cache record, otherwise TRUE.
 * note: will not write if the cache file does not exist 
 */
function cache_query($qname, $cqp_query, $restrictions, $subcorpus, $postprocess, 
	$num_of_solutions, $num_of_texts, $simple_query, $qmode, $link=NULL)
{
	global $corpus_sql_name;
	global $username;

	/* check existence of the file */
	if (!cqp_file_exists($qname))
		return false;

	/* values that are calculated here */
	$file_size = cqp_file_sizeof($qname);
	$time_now = time();
	
	/* values that are made safe here */
	$qname = mysql_real_escape_string($qname);
	$simple_query = mysql_real_escape_string($simple_query);
	$cqp_query = mysql_real_escape_string($cqp_query);
	$restrictions = mysql_real_escape_string($restrictions);
	$subcorpus = mysql_real_escape_string($subcorpus);
	$postprocess = mysql_real_escape_string($postprocess);
	/* this so postprocess can be inserted without '' below */
	$postprocess = ( $postprocess === '' ? 'NULL' :  "'$postprocess'" );
	
	/* all others should have been checked before passing */

	$sql_query = "insert into saved_queries 
		( query_name, user, corpus, cqp_query, restrictions, 
		subcorpus, postprocess, time_of_query, hits, hit_texts,
		simple_query, query_mode, file_size, saved )
		values
		( '$qname', '$username', '$corpus_sql_name', '$cqp_query', '$restrictions', 
		'$subcorpus', $postprocess, '$time_now', '$num_of_solutions', '$num_of_texts', 
		'$simple_query', '$qmode', '$file_size', 0 )
		";
		
	do_mysql_query($sql_query);

	return true;
}






/* checks the cache for a query that matches this $qname as its $query_name  */
/* DOESN'T check theData or anything like that; returns one of the following: */
/* FALSE                if query with that name not found */
/* an ASSOCIATIVE ARRAY containing the SQL record (for printing, etc.) if the query was found */
function check_cache_qname($qname)
{
	$sql_query = "SELECT * from saved_queries where query_name = '" 
		. mysql_real_escape_string($qname) . "' limit 1";
		
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) == 0)
		return false;
	
	$cache_record = mysql_fetch_assoc($result);

	if (cqp_file_exists($qname))
		return $cache_record;
	else
	{
		/* the sql record of the query with that name exists, but the file doesn't */
		$sql_query = "DELETE FROM saved_queries where query_name = '" 
			. mysql_real_escape_string($qname) . "'";
		do_mysql_query($sql_query);
		
		return false;
	}
}



/* checks the cache for a query that matches the specified parameters */
/* DOESN'T check qname at all; returns one of the following: */
/* FALSE                if query with that name not found */
/* an ASSOCIATIVE ARRAY containing the SQL record (for printing, etc.) if the query was found */
/* if "postprocess" is not specified as a parameter, it assumes NULL is sought */
function check_cache_parameters($cqp_query, $restrictions, $subcorpus, $postprocess = '')
{
	global $corpus_sql_name;

	$cqp_query = mysql_real_escape_string($cqp_query);
	$restrictions = mysql_real_escape_string($restrictions);
	$subcorpus = mysql_real_escape_string($subcorpus);
	$postprocess = mysql_real_escape_string($postprocess);
	$postprocess_cond = ( $postprocess === '' ? 'is NULL' : "collate utf8_bin = '$postprocess'" );
	/* no need to check for no_restriction and no_subcorpus - these will have been written in */
	/* when the query was cached */

	$sql_query = "SELECT * from saved_queries 
		where corpus collate utf8_bin = '$corpus_sql_name'
		and cqp_query collate utf8_bin = '$cqp_query'
		and restrictions collate utf8_bin = '$restrictions' 
		and subcorpus collate utf8_bin = '$subcorpus'
		and postprocess $postprocess_cond
		and saved = 0
		limit 1";

	$result = do_mysql_query($sql_query);

	if (mysql_num_rows($result) == 0)
		return false;

	$cache_record = mysql_fetch_assoc($result);

	if (cqp_file_exists($cache_record['query_name']))
		return $cache_record;
	else
	{
		/* the sql record of the query with that cqp_query exists, but the file doesn't */
		$sql_query = "DELETE FROM saved_queries where query_name = '" 
			. $cache_record['query_name'] . "'";
		$result = do_mysql_query($sql_query);
		
		return false;
	}
}





/**
 * Updates one or more fields for an entry in the query cache log.
 * 
 * Argument should be an associative array with $key=>$val where each $key
 * is the column name in the table saved_queries.
 * 
 * Note that the fields to be updated are all treated as if they are strings.
 * (Nonstring fields shouldn't, in theory, be being updated...)
 * 
 * Note that as things are, this function DOES NOT update fields whose entry
 * in the array is an empty string. Such fields are left as they are - they are 
 * NOT changed to an empty string! 
 * 
 * This means the argument can just be an associative array of the fields to
 * change, it doesn't need to have been created using blank_cache_assoc.
 * 
 */
function update_cached_query($record)
{
	/* argument must be an array */
	if (! is_array($record))
		exiterror_arguments("'record' array", 'update_cached_query() requires an associative array as argument $record!',
			__FILE__, __LINE__);
			
	/* check the incoming array has query_name defined - this is needed for the WHERE */
	if (!isset($record['query_name']) || $record['query_name'] == "")
		exiterror_arguments("'record' array", 'update_cached_query() requires the $record! to have a set \'query_name\'',
			__FILE__, __LINE__);

	/* get a blank associative array (for the key names - don't
	 * rely on incoming array to have all and only the correct keys) */
	$sql_record = blank_cache_assoc();
	
	$sql_query = 'UPDATE saved_queries SET ';
	$first = true;
	
	foreach ($sql_record as $key => $m)
	{
		/* never update the datestamp - this happens automatically */
		if ($key == 'date_of_saving')
			continue;

		/* don't update if it's not set or if it's a zero string */
		if (isset($record[$key]) && $record[$key] != "" )
		{
			if ($first)
				$first = false;
			else
				$sql_query .= ', ';
			$sql_query .= "$key = '" . mysql_real_escape_string($record[$key]) . '\'';
		}
	}
	$sql_query .= " WHERE query_name = '" . mysql_real_escape_string($record['query_name']) . "'";

	do_mysql_query($sql_query);
}

// TODO: This is a clunky way to set a field to NULL. 
//TODO: Not currently used. Is there a need for this?
function null_cached_query_field($qname, $field)
{
	$field = mysql_real_escape_string($field);
	$qname = mysql_real_escape_string($qname);
	do_mysql_query("UPDATE saved_queries SET $field = NULL where query_name = $qname");	
}





/**
 * Delete a single, specified query from cache. 
 * 
 * Note that qname is unique across corpora.
 */
function delete_cached_query($qname)
{
	/* argument must be a string */
	if (! is_string($qname))
		exiterror_arguments($qname, 'delete_cached_query() requires a string as argument $qname!',
			__FILE__, __LINE__);

	$sql_query = "DELETE from saved_queries where query_name = '" 
		. mysql_real_escape_string($qname) . '\'';

	cqp_file_unlink($qname);

	do_mysql_query($sql_query);

	/* no need to return anything - the update either works, or CQPweb dies */
}





function copy_cached_query($oldqname, $newqname)
{
	/* both arguments must be a string */
	if (! is_string($oldqname))
		exiterror_arguments($oldqname, 'copy_cached_query() requires a string as argument $oldqname!',
			__FILE__, __LINE__);
	if (! is_string($newqname))
		exiterror_arguments($newqname, 'copy_cached_query() requires a string as argument $newqname!',
			__FILE__, __LINE__);
			
	if ($oldqname == $newqname)
		exiterror_arguments($newqname, '$oldqname and $newqname cannot be identical in copy_cached_query()!');
			
	/* doesn't copy if the $newqname already exists */	
	if (is_array(check_cache_qname($newqname)))
		return;

	$cache_record = check_cache_qname($oldqname);

	/* or indeed if the oldqname doesn't exist */
	if ($cache_record === false)
		return;
	
	/* copy the file */
	cqp_file_copy($oldqname, $newqname);
	
	/* copy the mysql record */
	$cache_record['query_name'] = $newqname;
	
	$fieldstring = '';
	$valuestring = '';
	$first = true;

	foreach ($cache_record as $att => $val)
	{
		if ($att == 'date_of_saving')
			continue; /* it is a timestamp */
		if ($first)
			$first = false;
		else
		{
			$fieldstring .= ', ';
			$valuestring .= ', ';
		}
		$fieldstring .= $att;
		$valuestring .= "'" . mysql_real_escape_string($val) . "'";
	}

	do_mysql_query("insert into saved_queries ( $fieldstring ) values ( $valuestring )");
}








/** Does nothing to the specified query, but refreshes its time_of_query / date_of_saving to = now */
function touch_cached_query($qname)
{
	if (! is_string($qname))
		exiterror_arguments($qname, 'touch_cached_query() requires a string as argument $qname!',
			__FILE__, __LINE__);
		
	$time_now = time();
	
	$sql_query = "update saved_queries set time_of_query = $time_now where query_name = '$qname'";
	do_mysql_query($sql_query);

}





/* delete cached queries if the (configurable) limit has been reached */

/* this func refers to $Config->cache_size_limit; this is set in defaults.inc */
/* but can be overridden by an individual corpus's settings.inc       */

/* by default, this function does not delete usersaved queries        */
/* this can be overriden by passing it "false"                        */
/* and is automatically overridden if enough space cannot be cleared  */
/* just by deleting non-user-saved queries                            */
/////TODO the above behaviour seems dodgy and has been turned off
/////user queries shouldn't just be silently deleted!
function delete_cache_overflow($protect_user_saved = true)
{
	global $Config;

	$protect_user_saved = (bool)$protect_user_saved;
	
	/* step one: how many bytes in size is the CQP cache RIGHT NOW? */
	$result = do_mysql_query("select sum(file_size) from saved_queries");
	list($current_size) = mysql_fetch_row($result);
	unset($result);
	

	if ($current_size > $Config->cache_size_limit)
	{
		/* the cache has exceeded its size limit, ergo: */
		
		/* step two: how many bytes do we need to delete? */
		$toDelete_size = $current_size - $Config->cache_size_limit;
		
		/* step three: get a list of deletable files */
		$sql_query = "select query_name, file_size from saved_queries"
			. ($protect_user_saved ? " where saved = 0" : "")
			. " order by time_of_query asc";
			
		$del_result = do_mysql_query($sql_query);

		/* step four: delete files from the list until we've deleted enough */
		while ($toDelete_size > 0)
		{
			/* get the next most recent file from the savedQueries list */
			if ( ! ($current_del_row = mysql_fetch_row($del_result)) )
				break;

			delete_cached_query($current_del_row[0]);
			$toDelete_size -= $current_del_row[1];
		}
		
		/* have the above deletions done the trick? */
		if ($toDelete_size > 0)
		{	
			// the following is commented out because user-saves should not be silently deleted!
			/* deleting all the queries that could be deleted didn't work! 
			   last ditch: if user-saved-queries were protected, unprotect them and try again. 
			   Note, if it doesn't work unprotected, then the self-call will abort CQPweb * /
			if ($protect_user_saved)
			{
				$protect_user_saved = false;
				delete_cache_overflow(false);
			}
			else*/
				exiterror_cacheoverload();
		}
	} /* endif the cache has exceeded its size limit */
	
	/* no "else" - if the cache hasn't exceeded its size limit,
	 * this function just returns without doing anything */
}






/* nuclear option - deletes all temp files, and removes their record from the saved_queries table */
/* delete the entire cache, plus any files in the temp directory */
function clear_cache($protect_user_saved = true)
{
	global $Config;
	
	/* this function can take a long time to run, so turn off the limits */
	php_execute_time_unlimit();
	
	/* in case arg comes in as a string or int */
	$protect_user_saved = (bool)$protect_user_saved;
	
	/* get a list of deletable queries */
	$sql_query = "select query_name from saved_queries" 
		. ($protect_user_saved ? " where saved = 0" : "");
		
	$del_result = do_mysql_query($sql_query);

	/* delete queries */
	while (($current_del_row = mysql_fetch_row($del_result)) !== false)
		delete_cached_query($current_del_row[0]);


	/* are there any files left in the temp directory? */
	foreach(glob("{$Config->dir->cache}/*") as $file)
	{
		/* was this file protected on the previous pass? if so, it will still be in the DB */
		preg_match('/\A([^:]*:)(.*)\z/', $file, $m);
		$result = do_mysql_query("select query_name from saved_queries where query_name = '{$m[2]}'");
		
		/* if this file wasn't protected, then delete it */
		if (mysql_num_rows($result) < 1)
			unlink($file);
		unset($result);
	}
	
	php_execute_time_relimit();
}




/**
 * Checks a proposed save name to see if it is in use. 
 * 
 * Save names cannot be duplicated within a (user + corpus) combination.
 * 
 * Returns true if the save name is already in use; otherwise false
 */
function save_name_in_use($save_name)
{
	global $corpus_sql_name;
	global $username;
	$save_name = mysql_real_escape_string($save_name);
	
	$result = do_mysql_query("select query_name from saved_queries 
								where corpus = '$corpus_sql_name' and user = '$username' and save_name = '$save_name'");
	
	return (mysql_num_rows($result) > 0);
}






/**
 * Given the name of a categorised query, this function returns an array of 
 * names of categories that exist in that query.
 */
function catquery_list_categories($qname)
{
	$sql_query = "select category_list from saved_catqueries where catquery_name = '"
		. mysql_real_escape_string($qname)
		.'\'';
	$result = do_mysql_query($sql_query);
	list($list) = mysql_fetch_row($result);
	return explode('|', $list);
}


/**
 * Returns an array of category values for a given catquery, with ints (reference 
 * numbers) indexing strings (category names).
 *
 * The from and to parameters specify the range of refnumbers in the catquery
 * that is desired to be returned; they are to be INCLUSIVE.
 */
function catquery_get_categorisation_table($qname, $from, $to)
{
	/* find out the dbname from the saved_catqueries table */
	$dbname = catquery_find_dbname($qname);
	
	$from = (int)$from;
	$to = (int)$to;
	
	$sql_query = "select refnumber, category from $dbname where refnumber >= $from and refnumber <= $to";
	$result = do_mysql_query($sql_query);
			
	$a = array();
	while ( ($row = mysql_fetch_row($result)) !== false)
		$a[(int)$row[0]] = $row[1];
	
	return $a;
}


/**
 * Returns a string containing the dbname associated with the given catquery.
 */
function catquery_find_dbname($qname)
{
	$qname = mysql_real_escape_string($qname);
	$sql_query = "select dbname from saved_catqueries where catquery_name ='$qname'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) < 1)
		exiterror_general("The categorised query <em>$qname</em> could nto be found in the database.", 
			__FILE__, __LINE__);
	list($dbname) = mysql_fetch_row($result);

	return $dbname;
}









/**
 * Adds a trace of a query performed by the user to the query history.
 * 
 * Note that the "hits" field is set by default to -3; scripts should update
 * this later if the query is successful.
 */
function history_insert($instance_name, $cqp_query, $restrictions, $subcorpus, $simple_query, $qmode)
{
	global $corpus_sql_name;
	global $username;

	$escaped_cqp_query = mysql_real_escape_string($cqp_query);
	$escaped_restrictions = mysql_real_escape_string($restrictions);
	$escaped_subcorpus = mysql_real_escape_string($subcorpus);
	$escaped_simple_query = mysql_real_escape_string($simple_query);
	
	$sql_query = "insert into query_history (instance_name, user, corpus, cqp_query, restrictions, 
		subcorpus, hits, simple_query, query_mode) 
		values ('$instance_name', '$username', '$corpus_sql_name', '$escaped_cqp_query', '$escaped_restrictions', 
		'$escaped_subcorpus', -3, '$escaped_simple_query', '$qmode')";

	do_mysql_query($sql_query);
}

/**
 * Deletes the history entry with the specified instance name.
 */
function history_delete($instance_name)
{
	$instance_name = mysql_real_escape_string($instance_name);
	$sql_query = "delete from query_history where instance_name = '$instance_name'";
	do_mysql_query($sql_query);
}

/**
 * Sets the number of hits associated with a given instance name in the query history.
 */
function history_update_hits($instance_name, $hits)
{
	if (! is_int($hits) )
		exiterror_arguments("-->$hits<--", 'history_update_hits() requires an integer as argument $hits!',
			__FILE__, __LINE__);

	$sql_query = "update query_history SET hits = $hits where instance_name = '$instance_name'";
	do_mysql_query($sql_query);
}





///**
// * This function clears the query history of old queries.
// * 
// * Any entry whose date_of_query is older than the time given (integer, unix time) will be deleted
// * from the table.
// * 
// * Access to this would normally be superuser only. It operates across usernames and across corpora.
// */
//function history_purge_old_queries($date)
//{
//	$stopdate = date('Ymd', (int)$date);
//
//	do_mysql_query("delete from query_history where date_of_query < $stopdate");
//}


?>
