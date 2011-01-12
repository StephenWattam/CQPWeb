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









function get_user_setting($username, $field)
{
	static $cache;

	if (isset($cache[$username]))
		return $cache[$username][$field];

	$sql_query = "SELECT * from user_settings WHERE username = '$username'";
	
	$result = do_mysql_query($sql_query);
		
	if (mysql_num_rows($result) == 0)
	{
		create_user_record($username);
		return get_user_setting($username, $field);
	}
	else
	{
		$cache[$username] = mysql_fetch_assoc($result);
		return $cache[$username][$field];
	}
}

/** 
 * Returns an object (stdClass with members corresponding to the
 * fields of the user_settings table in the database) containing
 * the specified user's data.
 * 
 * If $autocreate is set to false, then the function will return
 * false in case of a nonexistent user. If it is set to true
 * (the default value) then an empty user record will be created
 * for that user. 
 */  
function get_all_user_settings($username, $autocreate = true)
{	
	static $cache;
	
	$autocreate = (bool) $autocreate;
	
	if (isset($cache[$username]))
		return $cache[$username];
	
	$sql_query = "SELECT * from user_settings WHERE username = '$username'";
	
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) == 0)
	{
		if ($autocreate)
		{
			create_user_record($username);
			return get_all_user_settings($username);
		}
		else
			return false;
	}
	else
	{
		$cache[$username] = mysql_fetch_object($result);
		return $cache[$username];
	}
}


// TODO next two functions prob should do sanitisation as elsewhere in
// cqpweb that is done at the lowest possible level, ie at poitn where the SQL queries are composed.
/** 
 * note: neither of the following functions sanitises input 
 * this MUST be done beforehand, e.g. using parse_get_user_settings() 
 */
function update_user_setting($username, $field, $setting)
{
	$sql_query = "UPDATE user_settings SET $field = '$setting' WHERE username = '$username'";
	
	do_mysql_query($sql_query);
}

/** does not sanitise the parameters... */
function update_multiple_user_settings($username, $settings)
{	
	$sql_query = "UPDATE user_settings SET ";
	
	foreach ($settings as $field => $value)
		$sql_query .= "$field = '$value', ";
	
	$sql_query = substr($sql_query, 0, strlen($sql_query)-2);
	
	$sql_query .= " WHERE username = '$username'";
	
	$result = do_mysql_query($sql_query);
}

function create_user_record($username)
{
	global $default_max_dbsize;
	global $default_colloc_range;
	global $default_calc_stat;
	global $default_colloc_minfreq;
	
	$sql_query = "INSERT INTO user_settings (
		username,
		realname,
		conc_kwicview,
		conc_corpus_order,
		cqp_syntax,
		context_with_tags,
		use_tooltips,
		coll_statistic,
		coll_freqtogether,
		coll_freqalone,
		coll_from,
		coll_to,
		max_dbsize,
		linefeed
		)
		VALUES
		(
		'$username',
		'unknown person',
		1,
		1,
		0,
		0,
		1,
		$default_calc_stat,
		$default_colloc_minfreq,
		$default_colloc_minfreq,
		" . (-1 * ($default_colloc_range-2)) . ",
		" . ($default_colloc_range-2) . ",
		$default_max_dbsize,
		'au')";
		
	do_mysql_query($sql_query);
}


function delete_user_record($username)
{
	$username = mysql_real_escape_string($username);
	do_mysql_query("DELETE FROM user_settings where username = '$username'");
}


function get_user_linefeed($username)
{
	$current = get_user_setting($username, 'linefeed');
	
	if ($current == NULL || $current == '' || $current = 'au')
		$current = guess_user_linefeed($username);

	switch ($current)
	{
	case 'd':	return "\r";
	case 'a':	return "\n";
	case 'da':	return "\r\n";
	default:	return "\r\n";		/* shouldn't be possible to get here */
	}
}



function guess_user_linefeed($user_to_guess)
{
	if ($user_to_guess != $_SERVER['REMOTE_USER'])
		return 'da';
	/* da is the default guess when no guess can be made, because Windows dominates the OS market.
	 * and *nix people are more likely to be computer literate enough to fix it ;-P */
	
	/* a and d are symbols, of course, for \n and \r respectively. */
	if (strpos($_SERVER['HTTP_USER_AGENT'], 'Windows') !== false)
		return 'da';
	else if (strpos($_SERVER['HTTP_USER_AGENT'], 'Macintosh')   !== false || 
			 strpos($_SERVER['HTTP_USER_AGENT'], 'Mac_PowerPC') !== false)
	{
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'OS X') !== false)
			return 'a';		/* cos OS X is like *nix */
		else
			return 'd';		/* cos old Macs aren't */
	}
	else /* unix or linux prolly */
		return 'a';
}


/** Gets all "newSetting" parameters from $_GET and sanitises for mySQL */
function parse_get_user_settings()
{
	$settings = array();
	foreach($_GET as $k => $v)
	{
		if (preg_match('/^newSetting_(\w+)$/', $k, $m) > 0)
		{
			switch($m[1])
			{
			/* boolean settings */
			case 'conc_kwicview':
			case 'conc_corpus_order':
			case 'cqp_syntax':
			case 'context_with_tags':
			case 'use_tooltips':
				$settings[$m[1]] = (bool)$v;
				break;
			
			/* string settings */
			case 'realname':
			case 'email':
				$settings[$m[1]] = mysql_real_escape_string($v);
				break;
			
			/* integer settings */
			case 'coll_statistic':
			case 'coll_freqtogether':
			case 'coll_freqalone':
			case 'coll_from':
			case 'coll_to':
			case 'max_dbsize':
				$settings[$m[1]] = (int)$v;
				break;
				
			/* patterned settings */
			case 'linefeed':
				if (preg_match('/^(da|d|a|au)$/', $v) > 0)
					$settings[$m[1]] = $v;
				break;
			case 'username':
				$settings[$m[1]] = preg_replace('/\W/', '', $m[1]);
				break;
			}
		} 
	}
	return $settings;
}


function user_macro_create($username, $macro_name, $macro_body)
{
	$username = mysql_real_escape_string($username);
	$macro_name = mysql_real_escape_string($macro_name);
	$macro_body = mysql_real_escape_string($macro_body);
	
	/* convert any \r to \n and delete multiple \n */
	$macro_body = str_replace("\r", "\n", $macro_body);
	$macro_body = "\n" . str_replace("\n\n", "\n", $macro_body);
	
	/* deduce macro_num_args by matching all strings of form $\d+ */
	preg_match_all('|[^\\\\]\$(\d+)|', $macro_body, $m, PREG_PATTERN_ORDER);
	
	$top_mentioned_arg = -1;
	
	foreach($m[1] as $num)
		if ($num > $top_mentioned_arg)
			$top_mentioned_arg = $num;
	
	/* The $\d references count from zero so if $1 is top mentioned, num args is actually 2 */
	$macro_num_args = $top_mentioned_arg + 1;
	
	/* delete macro if already exists */
	user_macro_delete($username, $macro_name, $macro_num_args);
	
	$sql_query = "INSERT INTO user_macros
		(username, macro_name, macro_num_args, macro_body)
		values
		('$username', '$macro_name', $macro_num_args, '$macro_body')";
	
	do_mysql_query($sql_query);
}

function user_macro_delete($username, $macro_name, $macro_num_args)
{
	$username = mysql_real_escape_string($username);
	$macro_name = mysql_real_escape_string($macro_name);
	$macro_num_args = (int)$macro_num_args;
	
	do_mysql_query("delete from user_macros 
						where username='$username' 
						and macro_name='$macro_name'
						and macro_num_args = $macro_num_args");
}


/**
 * Load all macros for the specified user. 
 */
function user_macro_loadall($username)
{
	global $cqp;
	
	$username = mysql_real_escape_string($username);

	$result = do_mysql_query("select * from user_macros where username='$username'");

	while (false !== ($r = mysql_fetch_object($result)))
	{
		$block = "define macro {$r->macro_name}({$r->macro_num_args}) ' ";
		/* nb. Rather than use str_replace here, maybe use the CQP:: escape method? */
		$block .= str_replace("'", "\\'", strtr($r->macro_body, "\t\r\n", "   ")) . " '";
		$cqp->execute($block);
	}
}



?>