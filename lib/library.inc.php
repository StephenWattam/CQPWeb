<?php
/*
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-10 Andrew Hardie and contributors
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







/* this file contains a library of useful functions */








/*
TODO
If mysql extension does not exist, include fake-mysql.inc.php to restore the functions
that are actually used and emulate them via mysqli.
*/
/* this is global code in a library file; normally a no-no.
it -only- addresses what files need ot be included and which don't */
if  (!extension_loaded('mysql'))
{
	if (!class_exists('mysqli', false))
		exit('Fatal error: neither mysql nor mysqli is available.');
	else
		require_once('../lib/fake-mysql.inc.php');
}



/* connect/disconnect functions */

/**
 * Creates a global connection to a CQP child process.
 */
function connect_global_cqp()
{
	global $cqp;
	global $cqpweb_tempdir;
	global $corpus_cqp_name;
	global $path_to_cwb;
	global $cwb_registry;
	global $print_debug_messages;

	/* connect to CQP */
	$cqp = new CQP($path_to_cwb, $cwb_registry);
	/* select an error handling function */
	$cqp->set_error_handler("exiterror_cqp");
	/* set CQP's temporary directory */
	$cqp->execute("set DataDirectory '/$cqpweb_tempdir'");
	/* select corpus */
	$cqp->set_corpus($corpus_cqp_name);
	/* note that corpus must be (RE)SELECTED after calling "set DataDirectory" */
	
	if ($print_debug_messages)
		$cqp->set_debug_mode(true);
}

/**
 * Disconnects the global CQP child process.
 */
function disconnect_global_cqp()
{
	global $cqp;
	if (isset($cqp))
	{
		$cqp->disconnect();
		unset($GLOBALS['cqp']);
	}
}


/**
 * This function refreshes CQP's internal list of queries currently existing in its data directory
 * 
 * NB should this perhaps be part of the CQP object model?
 * (as perhaps should set DataDirectory!)
 */
function refresh_directory_global_cqp()
{
	global $cqp;
	global $cqpweb_tempdir;
	global $corpus_cqp_name;
	
	if (isset($cqp))
	{
		// question: will next line work if we have no read access to root?
		$cqp->execute("set DataDirectory '/'");
		$cqp->execute("set DataDirectory '/$cqpweb_tempdir'");
		$cqp->set_corpus($corpus_cqp_name);
		// Question: is this still necessary?
	}
}

/**
 * Creates a global variable $mysql_link containing a connection to the CQPweb
 * database, using the settings in the config file.
 */
function connect_global_mysql()
{
	global $mysql_link;
	global $mysql_server;
	global $mysql_webuser;
	global $mysql_webpass;
	global $mysql_schema;
	global $utf8_set_required;
	
	$mysql_link = mysql_connect($mysql_server, $mysql_webuser, $mysql_webpass);
	
	if (! $mysql_link)
		exiterror_fullpage('mySQL did not connect - please try again later!');
	
	mysql_select_db($mysql_schema, $mysql_link);
	
	/* utf-8 setting is dependent on a variable defined in config.inc.php */
	if ($utf8_set_required)
		mysql_query("SET NAMES utf8", $mysql_link);
}
/**
 * Disconnects from the MySQL server.
 * 
 * Scripts could easily disconnect mysql_link locally. So this function
 * only exists so there is function-name-symmetry, and (less anally-retentively) so 
 * a script never really has to use mysql_link in the normal way of things. As
 * a consequence mysql_link is entirely contained within this module.
 */
function disconnect_global_mysql()
{
	global $mysql_link;
	if(isset($mysql_link))
		mysql_close($mysql_link);
}

/**
 * Disconnects from both cqp & mysql, assuming standard global variable names are used.
 */
function disconnect_all()
{
	disconnect_global_cqp();
	disconnect_global_mysql();
}



/**
 * Does a MySQL query on the CQPweb database, with error checking.
 * 
 * Note - this function should replace all direct calls to mysql_query,
 * thus avoiding duplication of error-checking code.
 * 
 * Returns the result resource.
 */ 
function do_mysql_query($sql_query)
{
	global $mysql_link;
	global $print_debug_messages;

	if ($print_debug_messages)
	{
		print_debug_message("About to run the following MySQL query:\n\n$sql_query\n");
		$start_time = time();
	}	
	$result = mysql_query($sql_query, $mysql_link);
	
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link));
			
	if ($print_debug_messages)
		print_debug_message("The query ran successfully in " . (time() - $start_time) . " seconds.\n");
		
	return $result;
}


/**
 * Does a mysql query and puts the result into an output file.
 * 
 * This works regardless of whether the mysql server program (mysqld)
 * is allowed to write files or not.
 * 
 * The mysql $query should be of the form "select [somthing] FROM [table] [other conditions]" 
 * -- that is, it MUST NOT contain "into outfile $filename", and the FROM must be in capitals. 
 * 
 * The output file is specified by $filename - this must be a full absolute path.
 * 
 * Typically used to create a dump file (new format post CWB2.2.101)
 * for input to CQP e.g. in the creation of a postprocessed query. 
 * 
 * Its return value is the number of rows written to file. In case of problem,
 * exiterror_* is called here.
 */
function do_mysql_outfile_query($query, $filename)
{
	global $mysql_has_file_access;
	global $mysql_link;
	global $print_debug_messages;
	
	if ($mysql_has_file_access)
	{
		/* We should use INTO OUTFILE */
		
		$into_outfile = 'INTO OUTFILE "' . mysql_real_escape_string($filename) . '" FROM ';
		$replaced = 0;
		$query = str_replace("FROM ", $into_outfile, $query, $replaced);
		
		if ($replaced != 1)
			exiterror_mysqlquery('no_number',
				'A query was prepared which does not contain FROM, or contains multiple instances of FROM: ' 
				. $query , __FILE__, __LINE__);
		
		if ($print_debug_messages)
			print_debug_message("About to run the following MySQL query:\n\n$query\n");
		$result = mysql_query($query);
		if ($result == false)
			exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link));
		else
		{
			if ($print_debug_messages)
				print_debug_message("The query ran successfully.\n");
			return mysql_affected_rows($mysql_link);
		}
	}
	else 
	{
		/* we cannot use INTO OUTFILE, so run the query, and write to file ourselves */
		if ($print_debug_messages)
			print_debug_message("About to run the following MySQL query:\n\n$query\n");
		$result = mysql_unbuffered_query($query, $mysql_link); /* avoid memory overhead for large result sets */
		if ($result == false)
			exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link));
		if ($print_debug_messages)
			print_debug_message("The query ran successfully.\n");
	
		if (!($fh = fopen($filename, 'w'))) 
			exiterror_general("Could not open file for write ( $filename )", __FILE__, __LINE__);
		
		$rowcount = 0;
		
		while ($row = mysql_fetch_row($result)) 
		{
			fputs($fh, implode("\t", $row) . "\n");
			$rowcount++;
		}
		
		fclose($fh);
		
		return $rowcount;
	}
}



/* the next two functions are really just for convenience */

/** Turn off indexing for a given MySQL table. */
function database_disable_keys($arg)
{
	$arg = mysql_real_escape_string($arg);
	do_mysql_query("alter table $arg disable keys");
}
/** Turn on indexing for a given MySQL table. */
function database_enable_keys($arg)
{
	$arg = mysql_real_escape_string($arg);
	do_mysql_query("alter table $arg enable keys");
}






/**
 * returns an integer containing the RAM limit to be passed to CWB programs that
 * allow a RAM limit to be set - note, the flag (-M or whatever) is not returned,
 * just the number of megabytes as an integer.
 */
function get_cwb_memory_limit()
{
	global $cwb_max_ram_usage;
	global $cwb_max_ram_usage_cli;
	
	return ((php_sapi_name() == 'cli') ? $cwb_max_ram_usage_cli : $cwb_max_ram_usage);
}




/**
 * currently, this function just wraps pre_echo, or echoes naked to the command line 
 * but we might want to create a more HTML-table-friendly version later.
 */
function print_debug_message($message)
{
	if (php_sapi_name() == 'cli')
		echo $message. "\n\n";
	else
		pre_echo($message);
}


/**
 * Echoes a string, but with HTML 'pre' tags (ideal for debug messages)
 */
function pre_echo($s)
{
	echo "<pre>\n$s\n</pre>";
}

/**
 * Imports the settings for a corpus into global variable space.
 * 
 * If there is an active CQP object, it is set to use that corpus.
 */
function import_settings_as_global($corpus)
{
	$data = file_get_contents("../$corpus/settings.inc.php");
	
	/* get list of variables and create global references */
	preg_match_all('/\$(\w+)\W/', $data, $m, PREG_PATTERN_ORDER);
	foreach($m[1] as $v)
	{
		global $$v;	
	}
	include("../$corpus/settings.inc.php");
	
	/* one special one */
	global $cqp;
	if (isset($cqp, $corpus_cqp_name))
		$cqp->set_corpus($corpus_cqp_name);
}


/** 
 * This function removes any existing start/end anchors from a regex
 * and adds new ones.
 */
function regex_add_anchors($s)
{
	$s = preg_replace('/^\^/', '', $s);
	$s = preg_replace('/^\\A/', '', $s);
	$s = preg_replace('/\$$/', '', $s);
	$s = preg_replace('/\\[Zz]$/', '', $s);
	return '^' . $s . '$';
}

/**
 * Converts an integer to a string with commas every three digits.
 */

function make_thousands($number)
{
// TODO replace with builtin function number_format
	$string = "$number";
	$length = strlen($string);

	$figure = $length % 3;
	if ($figure == 0)
		$figure = 3;
	
	$new_string = "";
	
	for ( $j = 0 ; $j < ($length + 1) ; $j++ )
	{
		$figure--;		
		$new_string .= substr($string, $j, 1);
		if ($figure == 0) 
		{
			$new_string .= ',';
			$figure = 3;
		}
	}
	return rtrim($new_string, ",");
}



/*
 * Returns the time as a float using PHP's microtime function.
 *
 * A simple function, copied from php.net, originally to replicate PHP5
 * behaviour in PHP4, now it just wraps the PHP5 builtin function.
 * /
Function no longer used. Replaced with direct calls to microtime.
function microtime_float()
{
	return microtime(true);
}*/



/**
 * Replacement for htmlspecialcharacters which DOESN'T change & to &amp; if it is already part of
 * an entity; otherwise equiv to htmlspecialchars($string, ENT_COMPAT, 'UTF-8', false) 
 */
function cqpweb_htmlspecialchars($string)
{
	$string = str_replace('&', '&amp;', $string);
	$string = str_replace('<', '&lt;', $string);
	$string = str_replace('>', '&gt;', $string);
	$string = str_replace('"', '&quot;', $string);

	return preg_replace('/&amp;(\#?\w+;)/', '&$1', $string);
}


/**
 * Removes any characters that match PCRE \W from a string.
 *  
 * A "handle" can only contain word characters.
 */
function cqpweb_handle_enforce($string)
{
	return preg_replace('/\W/', '', $string);
}

/**
 * Returns true iff there are no non-word characters (i.e. no \W) in the argument string.
 */
function cqpweb_handle_check($string)
{
	return (bool) preg_match('/\W/', $string);
}



/**
 * This function creates absolute URLs from relative ones by adding the relative
 * URL argument $u to the real URL of the directory in which the script is running.
 * 
 * The URL of the currently-running script's containing directory is worked out  
 * in one of two ways. If the global configuration variable "$cqpweb_root_url" is
 * set, this address is taken, and the corpus handle (SQL version, IE lowercase, which 
 * is the same as the subdirectory that accesses the corpus) is added. If no SQL
 * corpus handle exists, nothing is added to $cqpweb_root_url.
 * 
 * If $cqpweb_root_url is not set, the function tries to work out the containing
 * directory by extracting values from the global $_SERVER array.
 * 
 * $u will be treated as a relative address  (as explained above) if it does not 
 * begin with "http" and as an absolute address if it does.
 * 
 * Note, this "absolute" in the sense of having a server specified at the start, 
 * it can still contain relativising elements such as '/../' etc.
 */
function url_absolutify($u)
{
	global $cqpweb_root_url;
	global $corpus_sql_name;
	
	if (preg_match('/\Ahttps?:/', $u))
		/* address is already absolute */
		return $u;
	else
	{
		/* make address absolute by adding server of this script plus folder path of this URI  
		 * this may not be foolproof, because it assumes that the path will always lead to the 
		 * folder in which the current php script is located -- but should work for most cases 
		 */
		if (empty($cqpweb_root_url))
			return ($_SERVER['HTTPS'] ? 'https://' : 'http://') 
				. $_SERVER['HTTP_HOST'] 
				. preg_replace('/\/[^\/]*\z/', '/', $_SERVER['REQUEST_URI']) 
				. $u;
		else
			return $cqpweb_root_url 
				. ( (!empty($corpus_sql_name)) ? $corpus_sql_name.'/' : '' ) 
				. $u; 
	}
}



/** 
 * Checks whether the current script has $_GET['uT'] == "y" 
 * (&uT=y is the terminating element of all valid CQPweb URIs).
 * 
 * "uT" is short for "urlTest", by the way.
 */
function url_string_is_valid()
{
	return (array_key_exists('uT', $_GET) && $_GET['uT'] == 'y');
}




/**
 * Returns a string of "var=val&var=val&var=val".
 * 
 * $changes = array of arrays, 
 * where each array consists of [0] a field name  
 *                            & [1] the new value.
 * 
 * If [1] is an empty string, that pair is not included.
 * 
 * WARNING: adds values that weren't already there at the START of the string.
 * 
 */
function url_printget($changes = "Nope!")
{
	$change_me = is_array($changes);

	$first = true;
	foreach ($_GET as $key => $val)
	{
		if (!empty($string))
			$string .= '&';

		if ($change_me)
		{
			$newval = $val;

			foreach ($changes as &$c)
				if ($key == $c[0])
				{
					$newval = $c[1];
					$c[0] = '';
				}
			/* only add the new value if the change array DID NOT contain a zero-length string */
			/* otherwise remove the last-added & */
			if ($newval != "")
				$string .= $key . '=' . urlencode($newval);
			else
				$string = preg_replace('/&\z/', '', $string);
				
		}
		else
			$string .= $key . '=' . urlencode($val);
		/* urlencode needed here since $_GET appears to be un-makesafed automatically */
	}
	if ($change_me)
	{
		$extra = '';
		foreach ($changes as &$c)
			if ($c[0] != '' && $c[1] != '')
				$extra .= $c[0] . '=' . $c[1] . '&';
		$string = $extra . $string;
		
	}
	return $string;
}

/**
 * Returns a string of "&lt;input type="hidden" name="key" value="value" /&gt;..."
 * 
 * $changes = array of arrays, 
 * where each array consists of [0] a field name  
 *                            & [1] the new value.
 * 
 * If [1] is an empty string, that pair is not included.
 *  
 * WARNING: adds values that weren't there at the START of the string.
 */
function url_printinputs($changes = "Nope!")
{
	$change_me = is_array($changes);

	$string = '';
	foreach ($_GET as $key => $val)
	{
		if ($change_me)
		{
			$newval = $val;
			foreach ($changes as &$c)
				if ($key == $c[0])
				{
					$newval = $c[1];
					$c[0] = '';
				}
			/* only add the new value if the change array DID NOT contain a zero-length string */
			if ($newval != '')
				$string .= '<input type="hidden" name="' . $key . '" value="' 
					. htmlspecialchars($newval, ENT_QUOTES, 'UTF-8') . '" />
					';
		}
		else
			$string .= '<input type="hidden" name="' . $key . '" value="' 
				. htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '" />';

		/* note: should really be htmlspecialchars($val, ENT_QUOTES, UTF-8, false)
		   etc. BUT the last parameter (whcih turns off the effect on existing entities)
		   is PHP >=5.2.3 only 
		*/
	}

	if ($change_me)
	{
		$extra = '';
		foreach ($changes as &$c)
			if ($c[0] !== '' && $c[1] !== '')
				$extra .= '<input type="hidden" name="' . $c[0] . '" value="' 
					. htmlspecialchars($c[1], ENT_QUOTES, 'UTF-8') . '" />';
		$string = $extra . $string;
	}
	return $string;
}



/* invalid values of $pp cause CQPweb to default back to $default_per_page  */
function prepare_per_page($pp)
{
	global $default_per_page;
	
	if ( is_string($pp) )
		$pp = strtolower($pp);
	
	switch($pp)
	{
	/* extra values valid in concordance.php */
	case 'count':
	case 'all':
		if (strpos($_SERVER['PHP_SELF'], 'concordance.php') !== false)
			;
		else
			$pp = $default_per_page;
		break;

	default:
		if (is_numeric($pp))
			settype($pp, 'integer');
		else
			$pp = $default_per_page;
		break;
	}
	return $pp;
}


function prepare_page_no($n)
{
	if (is_numeric($n))
	{
		settype($n, 'integer');
		return $n;
	}
	else
		return 1;
}





function user_is_superuser($username)
{
	/* superusers are determined in the config file */
	global $superuser_username;
	
	$a = explode('|', $superuser_username);
	
	return in_array($username, $a);
}



function php_execute_time_unlimit($switch_to_unlimited = true)
{
	static $orig_limit = 30;

	if ($switch_to_unlimited)
	{
		$orig_limit = (int)ini_get('max_execution_time');
		set_time_limit(0);
	}
	else
	{
		set_time_limit($orig_limit);
	}
}

function php_execute_time_relimit()
{
	php_execute_time_unlimit(false);
}


/** THIS IS A DEBUG FUNCTION */
function show_var(&$var, $scope=false, $prefix='unique', $suffix='value')
{
	/* some code off the web to get the variable name */
	if($scope)	$vals = $scope;
	else		$vals = $GLOBALS;
	$old = $var;
	$var = $new = $prefix.rand().$suffix;
	$vname = FALSE;
	foreach($vals as $key => $val) 
	{
		if($val === $new) $vname = $key;
	}
	$var = $old;


	echo "\n<pre>-->\$$vname<--\n";
	var_dump($var);
	echo "</pre>";
}

/** THIS IS A DEBUG FUNCTION */
function dump_mysql_result($result)
{
	$s = '<table class="concordtable"><tr>';
	$n = mysql_num_fields($result);
	for ( $i = 0 ; $i < $n ; $i++ )
		$s .= "<th class='concordtable'>" 
			. mysql_field_name($result, $i)
			. "</th>";
	$s .=  '</tr>
		';
	
	while ( ($r = mysql_fetch_row($result)) !== false )
	{
		$s .= '<tr>';
		foreach($r as $c)
			$s .= "<td class='concordgeneral'>$c</td>\n";
		$s .= '</tr>
			';
	}
	$s .= '</table>';
	
	return $s;
}




function coming_soon_page()
{
	global $corpus_title;
	global $css_path;
	?>
	<html>
	<head>
	<?php
	
	/* initialise variables from settings files in local scope */
	/* -- they will prob not have been initialised in global scope anyway */
	
	require("settings.inc.php");
	
	echo '<title>' . $corpus_title . ' -- unfinished function!</title>';
	echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
	?>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	</head>
	<body>

	<?php
	coming_soon_finish_page();
}


function coming_soon_finish_page()
{
	?>
	<p class="errormessage">&nbsp;<br/>
		&nbsp; <br/>
		We are sorry, but that part of CQPweb has not been built yet.
	</p>
	
	</body>
	</html>
	<?php
}



/**
 * Runs a script in perl and returns up to 10Kb of text written to STDOUT
 * by that perl script, or an empty string if Perl writes nothing to STDOUT.
 * 
 * It reads STDERR if nothing is written to STDOUT.
 * 
 * This function is not currently used.
 * 
 * script_path	   path to the script, relative to current PHP script (string)
 * arguments	   anything to add after the script name (string)
 * select_maxtime  time to wait for PErl to respond
 * 
 */
function perl_interface($script_path, $arguments, $select_maxtime='!')
{
	global $path_to_perl;
	
	if (!is_int($select_maxtime))
		$select_maxtime = 10;
	
	if (! file_exists($script_path) )
		return "ERROR: perl script could not be found.";
		
	$call = "/$path_to_perl/perl $script_path $arguments";
	
	$io_settings = array(
		0 => array("pipe", "r"), // stdin 
		1 => array("pipe", "w"), // stdout 
		2 => array("pipe", "w")  // stderr 
	); 
	
	$process = proc_open($call, $io_settings, $handles);

	if (is_resource($process)) 
	{
		/* returns stdout, if stdout is empty, returns stderr */
		if (stream_select($r=array($handles[1]), $w=NULL, $e=NULL, $select_maxtime) > 0 )
			$output = fread($handles[1], 10240);
		else if (stream_select($r=array($handles[2]), $w=NULL, $e=NULL, $select_maxtime) > 0 )
			$output = fread($handles[2], 10240);
		else
			$output = "";

		fclose($handles[0]);    
		fclose($handles[1]);    
		fclose($handles[2]);    
		proc_close($process);
		
		return $output;
	}
	else
		return "ERROR: perl interface could not be created.";
}





// TODO next 3 functions seem a bit non-general to be in the library.... catquery.inc.php?

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
 * Creates a table row for the index-page left-hand-side menu, which is either a link,
 * or a greyed-out entry if the variable specified as $current_query is equal to
 * the link handle. It is returned as a string, -not- immediately echoed.
 *
 * This is the version for adminhome.
 */
function print_menurow_admin($link_handle, $link_text)
{
	global $thisF;
	return print_menurow_backend($link_handle, $link_text, $thisF, 'thisF');
}
/**
 * Creates a table row for the index-page left-hand-side menu, which is either a link,
 * or a greyed-out entry if the variable specified as $current_query is equal to
 * the link handle. It is returned as a string, -not- immediately echoed.
 *
 * This is the version for the normal user-facing index.
 */
function print_menurow_index($link_handle, $link_text)
{
	global $thisQ;
	return print_menurow_backend($link_handle, $link_text, $thisQ, 'thisQ');
}
function print_menurow_backend($link_handle, $link_text, $current_query, $http_varname)
{
	$s = "\n<tr>\n\t<td class=\"";
	if ($current_query != $link_handle)
		$s .= "concordgeneral\">\n\t\t<a class=\"menuItem\""
			. " href=\"index.php?$http_varname=$link_handle&uT=y\">";
	else 
		$s .= "concordgrey\">\n\t\t<a class=\"menuCurrentItem\">";
	$s .= "$link_text</a>\n\t</td>\n</tr>\n";
	return $s;
}


/**
 * Creates a page footer for CQPweb.
 * 
 * Pass in the string "admin" for an admin-logon link. 
 * Default link is to a help page.
 */ 
function print_footer($link = 'help')
{
	global $username;
	
	if ($link == 'help')
	{
		$help_cell = '<td align="center" class="cqpweb_copynote" width="33%">
			<a class="cqpweb_copynote_link" href="help.php" target="_NEW">Corpus and tagset help</a>
		</td>';
	}
	else if ($link == 'admin')
	{
		/* use the help cell for an admin logon link instead */
		$help_cell = '<td align="center" class="cqpweb_copynote" width="33%">
			<a href="adm"  class="cqpweb_copynote_link" >[Admin logon]</a>
		</td>';	
	}
	else
	{
		$help_cell = '<td align="center" class="cqpweb_copynote" width="33%">
			&nbsp;
		</td>';
	}
	?>
	<hr/>
	<table class="concordtable" width="100%">
		<tr>
			<td align="left" class="cqpweb_copynote" width="33%">
				CQPweb v<?php echo CQPWEB_VERSION; ?> &#169; 2008-2010
			</td>
			<?php echo $help_cell; ?>  
			<td align="right" class="cqpweb_copynote" width="33%">
				<?php
				if ($username == '__unknown_user')
					echo 'You are not logged in';
				else
					echo "You are logged in as user [$username]";
				?>
			</td>
		</tr>
	</table>
	<script language="JavaScript" type="text/javascript" src="../lib/javascript/wz_tooltip.js">
	</script>
	</body>
</html>
	<?php
}











/**
 * Create a system message that will appear below the main "Standard Query"
 * box (and also on the hompage).
 */
function add_system_message($header, $content)
{
	global $instance_name;
	$sql_query = "insert into system_messages set 
		header = '" . mysql_real_escape_string($header) . "', 
		content = '" . mysql_real_escape_string($content) . "', 
		message_id = '$instance_name'";
	/* timestamp is defaulted */
	do_mysql_query($sql_query);
}

/**
 * Delete the system message associated with a particular message_id.
 *
 * The message_id is the user/timecode assigned to the system message when it 
 * was created.
 */
function delete_system_message($message_id)
{
	$message_id = preg_replace('/\W/', '', $message_id);
	$sql_query = "delete from system_messages where message_id = '$message_id'";
	do_mysql_query($sql_query);
}

/**
 * Print out the system messages in HTML, including links to dete them.
 */
function display_system_messages()
{
	global $instance_name;
	global $username;
	global $this_script;
	
	$su = user_is_superuser($username);

	$result = do_mysql_query("select * from system_messages order by timestamp desc");
	
	if (mysql_num_rows($result) == 0)
		return;
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="<?php echo ($su ? 3 : 2) ; ?>" class="concordtable">
				System messages
			</th>
		</tr>
	<?php
	
	
	while ( ($r = mysql_fetch_object($result)) !== false)
	{
		?>
		<tr>
			<td rowspan="2" class="concordgrey" nowrap="nowrap">
				<?php echo substr($r->timestamp, 0, 10); ?>
			</td>
			<td class="concordgeneral">
				<strong>
					<?php echo htmlentities(stripslashes($r->header)); ?>
				</strong>
			</td>
		<?php
		if ($su)
		{
			echo '
			<td rowspan="2" class="concordgeneral" nowrap="nowrap">
				<a class="menuItem" onmouseover="return escape(\'Delete this system message\')"
				href="execute.php?function=delete_system_message&args='
				. $r->message_id .
				'&locationAfter=' . $this_script . '&uT=y">
					[x]
				</a>
			</td>';
		}
		?>
		</tr>
		<tr>
			<td class="concordgeneral">
				<?php echo str_replace("\n", '<br/>', htmlentities(stripslashes($r->content))); ?>
			</td>
		</tr>			
		<?php
	}
	echo '</table>';
}


/**
 * Convenience function to delete a specified directory, plus everything in it.
 */
function recursive_delete_directory($path)
{
	$files_to_delete = scandir($path);
	foreach($files_to_delete as &$f)
	{
		if ($f == '.' || $f == '..')
			;
		else if (is_dir("$path/$f"))
			recursive_delete_directory("$path/$f");
		else
			unlink("$path/$f");
	}
	rmdir($path);
}



/**
 * This function stores values in a table that would be too big to send via GET.
 *
 * Instead, they are referenced in the web form by their id code (which is passed 
 * by get) and retrieved by the script that processes the user input.
 * 
 * The return value is the id code that you should use in the web form.
 * 
 * Things stored in the longvalues table are deleted when they are 5 days old.
 * 
 * The retrieval function is longvalue_retrieve().
 *  
 */
function longvalue_store($value)
{
	global $instance_name;
	
	/* clear out old longvalues */
	$sql_query = "delete from system_longvalues where timestamp < DATE_SUB(NOW(), INTERVAL 5 DAY)";
	do_mysql_query($sql_query);
	
	$value = mysql_real_escape_string($value);
	
	$sql_query = "insert into system_longvalues (id, value) values ('$instance_name', '$value')";
	do_mysql_query($sql_query);

	return $instance_name;
}


/**
 * Retrieval function for values stored with longvalue_store.
 */
function longvalue_retrieve($id)
{	
	$id = mysql_real_escape_string($id);
	
	$sql_query = "select value from system_longvalues where id = '$id'";
	$result = do_mysql_query($sql_query);
	
	$r = mysql_fetch_row($result);
		
	return $r[0];
}






?>