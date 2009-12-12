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






/* this file contain functions that deal with the cache, saved queries, temp files, etc. */



/* returns if a CQP temp file exists with that qname in its filename */
function cqp_file_exists($qname)
{
	global $cqpweb_tempdir;
	global $corpus_cqp_name;
	return file_exists("/$cqpweb_tempdir/$corpus_cqp_name:$qname");
}


/* returns size of a CQP temp file (including 0 if said file existeth not) */
function cqp_file_sizeof($qname)
{
	global $cqpweb_tempdir;
	global $corpus_cqp_name;
	$s = filesize("/$cqpweb_tempdir/$corpus_cqp_name:$qname");
	return ( $s == false ? 0 : $s );
}

function cqp_file_unlink($qname)
{
	global $cqpweb_tempdir;
	global $corpus_cqp_name;
	$f = "/$cqpweb_tempdir/$corpus_cqp_name:$qname";
	if ( file_exists($f) )
		unlink($f);
}

function cqp_file_copy($oldqname, $newqname)
{
	global $cqpweb_tempdir;
	global $corpus_cqp_name;
	$of = "/$cqpweb_tempdir/$corpus_cqp_name:$oldqname";
	$nf = "/$cqpweb_tempdir/$corpus_cqp_name:$newqname";
	if ( file_exists($of) && ! file_exists($nf) )
		copy($of, $nf);
}




/* returns a blank associative array with named keys for each for the fields */
function blank_cache_assoc()
{
// shouldn't I just create an array, to avoid the overhead of a mysql call?
	global $mysql_link;
	$sql_query = 'select * from saved_queries limit 1';

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

	$blank = array();
	$n = mysql_num_fields($result);
	for ( $i = 0 ; $i < $n ; $i++ )
	{
		$str = mysql_field_name($result, $i);
		$blank[$str] = "";
	}
	return $blank;
}




/* makes sure that the name you are about to put into cache is unique */
function qname_unique($qname)
{
	if (! is_string($qname))
		exiterror_arguments($qname, 'qname_unique() requires a string as argument $qname!',
			__FILE__, __LINE__);

	global $mysql_link;
	
	while (1)
	{
		$sql_query = 'select query_name from saved_queries where query_name = \''
			. mysql_real_escape_string($qname) . '\' limit 1';
	
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);

		if (mysql_num_rows($result) == 0)
			break;
		else
		{
			unset($result);
			$qname .= '_';
		}
	}
	return $qname;	
}





/* write a query to the cache table : assumption - this query has just been run in CQP     */
/* returns FALSE if there was some kind of error creating the cache record, otherwise TRUE */
/* note: will not write if the cache file does not exist */
function cache_query($qname, $cqp_query, $restrictions, $subcorpus, $postprocess, 
	$num_of_solutions, $num_of_texts, $simple_query, $qmode, $link=NULL)
{
	global $mysql_link;
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
	$cqp_query = mysql_real_escape_string($cqp_query);
	$restrictions = mysql_real_escape_string($restrictions);
	$subcorpus = mysql_real_escape_string($subcorpus);
	$postprocess = mysql_real_escape_string($postprocess);
	/* this so postoprocess can be inserted without '' below */
	$postprocess = ( $postprocess === '' ? 'NULL' :  "'$postprocess'" );
	$simple_query = mysql_real_escape_string($simple_query);
	
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
		
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

	return true;
}






/* checks the cache for a query that matches this $qname as its $query_name  */
/* DOESN'T check theData or anything like that; returns one of the following: */
/* FALSE                if query with that name not found */
/* an ASSOCIATIVE ARRAY containing the SQL record (for printing, etc.) if the query was found */
function check_cache_qname($qname)
{
	global $mysql_link;
	
	$sql_query = "SELECT * from saved_queries where query_name = '" 
		. mysql_real_escape_string($qname) . "' limit 1";
		
	$result = mysql_query($sql_query, $mysql_link);
	
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
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
		$result = mysql_query($sql_query, $mysql_link);

		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		
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
	global $mysql_link;
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
		limit 1";

	$result = mysql_query($sql_query, $mysql_link);

	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

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
		$result = mysql_query($sql_query, $mysql_link);

		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		
		return false;
	}
}






function update_cached_query($record)
{
	global $mysql_link;

	/* argument must be an array */
	if (! is_array($record))
		exiterror_arguments("'record' array", 'update_cached_query() requires an associative array as argument $record!',
			__FILE__, __LINE__);
			
	/* check the incoming array has query_name defined - this is needed for the WHERE */
	if (!isset($record['query_name']) || $record['query_name'] == "")
		exiterror_arguments("'record' array", 'update_cached_query() requires the $record! to have a set \'query_name\'',
			__FILE__, __LINE__);

	/* get a blank associative array (for the key names - don't     */
	/* rely on incoming array to have all and only the correct keys */
	$sql_record = blank_cache_assoc();
	
	$sql_query = 'UPDATE saved_queries SET ';
	$first = true;
	
	foreach ($sql_record as $key => $m)
	{
		/* never update the datestamp - this happens automatically */
		if ($key == 'date_of_saving')
			continue;

		if (isset($record[$key]) && $record[$key] != "" )
		{
			/* don't update if it's not set or if it's a zero string */
			if ($first)
				$first = false;
			else
				$sql_query .= ', ';
			$sql_query .= "$key = '" . mysql_real_escape_string($record[$key]) . '\'';
		}
	}
	$sql_query .= " WHERE query_name = '" . mysql_real_escape_string($record['query_name']) . "'";

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

	/* no need to return anything - the update either works, or CQPweb dies */
}





/* delete a single, named query from cache */
function delete_cached_query($qname)
{
	global $mysql_link;

	/* argument must be a string */
	if (! is_string($qname))
		exiterror_arguments($qname, 'delete_cached_query() requires a string as argument $qname!',
			__FILE__, __LINE__);

	$sql_query = 'DELETE from saved_queries where query_name = \'' 
		. mysql_real_escape_string($qname) . '\'';

	cqp_file_unlink($qname);

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

	/* no need to return anything - the update either works, or CQPweb dies */
}





function copy_cached_query($oldqname, $newqname)
{
	global $mysql_link;

	/* both arguments must be a string */
	if (! is_string($oldqname))
		exiterror_arguments($oldqname, 'copy_cached_query() requires a string as argument $oldqname!',
			__FILE__, __LINE__);
	if (! is_string($newqname))
		exiterror_arguments($newqname, 'copy_cached_query() requires a string as argument $newqname!',
			__FILE__, __LINE__);
			
	if ($oldqname == $newqname)
		exiterror_arguments($newqname, '$oldqname and $newqname cannot be identical in copy_cached_query()!',
			__FILE__, __LINE__);
			
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
	
	$sql_query = "insert into saved_queries ( $fieldstring ) values ( $valuestring )";

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}








/* does nothing to the specified query, but refreshes its time_of_query / date_of_saving to = now */
function touch_cached_query($qname)
{
	global $mysql_link;
	
	if (! is_string($qname))
		exiterror_arguments($qname, 'touch_cached_query() requires a string as argument $qname!',
			__FILE__, __LINE__);
		
	$time_now = time();
	
	$sql_query = "update saved_queries set time_of_query = $time_now where query_name = '$qname'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), 
			__FILE__, __LINE__);
}





/* note: this function works ACROSS CORPORA */
function delete_cached_queries($protect_user_saved = true)
{
	/* delete cached queries if the (configurable) limit has been reached */
	
	/* this func refers to $cache_size_limit; this is set in defaults.inc */
	/* but can be overridden by an individual corpus's settings.inc       */
	
	/* by default, this function does not delete usersaved queries        */
	/* this can be overriden by passing it "false"                        */
	/* and is automatically overridden if enough space cannot be cleared  */
	/* just by deleting non-user-saved queries                            */

	global $mysql_link;
	global $cache_size_limit;
	global $cqpweb_tempdir;

	if (!is_bool($protect_user_saved))
		exiterror_arguments($protect_user_saved, 
			"delete_cached_queries() needs a bool (or nothing) as its argument!", __FILE__, __LINE__);
	
	/* step one: how many bytes in size is the CQP cache RIGHT NOW? */
	$sql_query = "select sum(file_size) from saved_queries";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	$row_array = mysql_fetch_array($result);
	$current_size = $row_array[0];
	unset($result);
	unset($row_array);
	

	if ($current_size > $cache_size_limit) 
	{
		/* the cache has exceeded its size limit, ergo: */
		
		/* step two: how many bytes do we need to delete? */
		$toDelete_size = $current_size - $cache_size_limit;
		
		/* step three: get a list of deletable files */
		$sql_query = "select query_name, file_size, corpus from saved_queries"
			. ($protect_user_saved ? " where saved = 0" : "")
			. " order by time_of_query asc";
			
		$del_result = mysql_query($sql_query, $mysql_link);
		
		if ($del_result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);

		/* step four: delete files from the list until we've deleted enough */
		$mysql_deletelist = array();
		while ($toDelete_size > 0)
		{
			/* get the next most recent file from the savedQueries list */
			if ( ! ($current_del_row = mysql_fetch_row($del_result)) )
				break;
			
			$globbed = glob("/$cqpweb_tempdir/*:" . $current_del_row[0]);
			$current_path_to_delete = $globbed[0];

			if (file_exists($current_path_to_delete))
			{
				/* if the said file exists, delete it */
				unlink($current_path_to_delete) ;
				/* and reduce the number of bytes we still have to remove */
				$toDelete_size = $toDelete_size - $current_del_row[1];
			}
			/* and add it to the mySQL deletelist regardless of whether or */
			/* not the file was there */
			$mysql_deletelist[] = $current_del_row[0];
		}
		
		/* step five: remove the references to those files from mySQL */
		foreach ($mysql_deletelist as $d)
		{
			$sql_query = "DELETE FROM saved_queries WHERE query_name = '$d'";
			$result = mysql_query($sql_query, $mysql_link);
			if ($result == false) 
				exiterror_mysqlquery(mysql_errno($mysql_link), 
					mysql_error($mysql_link), __FILE__, __LINE__);
			unset($result);
		}
		
		/* have the above deletions done the trick? */
		if ($toDelete_size > 0)
		{	
			/* deleting all the queries that could be deleted didn't work! */
			/* last ditch: if user-saved-queries were protected, unprotect them and try again. */
			/* Note, if it doesn't work unprotected, then the self-call will abort CQPweb */
			if ($protect_user_saved)
			{
				$protect_user_saved = false;
				delete_cached_queries(false);
			}
			else
				exiterror_cacheoverload();
		}
	} /* endif the cache has exceeded its size limit */
	
	/* no "else" - if the cache hasn't exceeded its size limit, */
	/* this function just returns without doing anything        */
}






/* nuclear option - deletes all temp files, and removes their record from the saved_queries table */
function clear_cache($protect_user_saved = true)
{
	/* delete the entire cache, plus any files in the temp directory */
		
	/* by default, this function does not delete usersaved queries        */
	/* this can be overriden by passing it "false"                        */
	/* and is automatically overridden if enough space cannot be cleared  */
	/* just by deleting non-user-saved queries                            */

	global $mysql_link;
	global $cqpweb_tempdir;
	
	/* this function can take a long time to run, so turn off the limits */
	php_execute_time_unlimit();
	
	/* in case arg comes in as a string */
	if ($protect_user_saved == '1' || $protect_user_saved == '0')
		$protect_user_saved = (bool)$protect_user_saved;
	
	if (!is_bool($protect_user_saved))
		exiterror_arguments($protect_user_saved, 
			"clear_cache() needs a bool (or nothing) as its argument!", __FILE__, __LINE__);

	
	/* get a list of deletable files */
	$sql_query = "select query_name from saved_queries" 
		. ($protect_user_saved ? " where saved = 0" : "");
		
	$del_result = mysql_query($sql_query, $mysql_link);
	if ($del_result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);	

	/* delete files */
	$mysql_deletelist = array();
	while (($current_del_row = mysql_fetch_row($del_result)) !== false)
	{		
		$globbed = glob("/$cqpweb_tempdir/*:" . $current_del_row[0]);
		$current_path_to_delete = $globbed[0];

		if (file_exists($current_path_to_delete))
			/* if the said file exists, delete it */
			unlink($current_path_to_delete);

		/* and add it to the mySQL deletelist regardless of whether or not the file was there */
		$mysql_deletelist[] = $current_del_row[0];
	}


	/* remove the references to those files from mySQL */
	foreach ($mysql_deletelist as $d)
	{
		$sql_query = "DELETE FROM saved_queries WHERE query_name = '$d'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		unset($result);
	}

	/* are there any files left in the temp directory? */
	foreach(glob("/$cqpweb_tempdir/*") as $file)
	{
		/* was this file protected on the previous pass? */
		preg_match('/\A([^:]*:)(.*)\z/', $file, $m);

		$sql_query = "select query_name from saved_queries where query_name = '" . $m[2] ."'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		
		/* if this file wasn't protected, then delete it */
		if (mysql_num_rows($result) < 1)
			unlink($file);
		unset($result);
	}
	
	php_execute_time_relimit();
}







function history_insert($instance_name, $cqp_query, $restrictions, $subcorpus, $simple_query, $qmode)
{
	global $mysql_link;
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

	$result = mysql_query($sql_query, $mysql_link);

	if ($result == false)
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}


function history_delete($instance_name)
{
	global $mysql_link;

	$instance_name = mysql_real_escape_string($instance_name);
	
	$sql_query = "delete from query_history where instance_name = '$instance_name'";
	$result = mysql_query($sql_query, $mysql_link);

	if ($result == false)
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}


function history_update_hits($instance_name, $hits)
{
	global $mysql_link;

	if (! is_int($hits) )
		exiterror_arguments("-->$hits<--", 'history_update_hits() requires an integer as argument $hits!',
			__FILE__, __LINE__);

	$sql_query = "update query_history SET hits = $hits where instance_name = '$instance_name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}





/* this will normally be accessed by the superuser using execute.php */
/* like the cache delete functions, it operates across usernames and across corpora */
function delete_old_query_history($weeks = '__DEFAULT', $max = '__DEFAULT')
{
	global $mysql_link;
	global $history_weekstokeep;
	global $history_maxentries;

	if ($weeks == '__DEFAULT')
		$weeks = $history_weekstokeep;
	else if (! is_int($weeks) )
		exiterror_arguments($weeks, 
			"delete_old_query_history() needs an int for both arguments (or no args at all)!", 
				__FILE__, __LINE__);
	if ($max == '__DEFAULT')
		$max = $history_maxentries;
	else if (! is_int($max) )
		exiterror_arguments($max, 
			"delete_old_query_history() needs an int for both arguments (or no args at all)!", 
				__FILE__, __LINE__);
	
	$stopdate = date('Ymd', time()-($weeks * 7 * 24 * 60 * 60));
	
	$sql_query = "delete from query_history where date_of_query < $stopdate";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}


?>