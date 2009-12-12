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







/* this file contains a library of useful functions */







/* HOUSEKEPING FUNCTIONS */



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
		$cqp->execute("set DataDirectory '/'");
		$cqp->execute("set DataDirectory '/$cqpweb_tempdir'");
		$cqp->set_corpus($corpus_cqp_name);
		// Question: is this still necessary?
	}
}

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
	{
		exiterror_fullpage('mySQL did not connect - please try again later!');
	}
	
	mysql_select_db($mysql_schema, $mysql_link);
	
	/* utf-8 setting is dependent on a variable defined in settings.inc.php */
	if ($utf8_set_required)
		mysql_query("SET NAMES utf8", $mysql_link);	
}

/**
 * disconnect from both cqp & mysql, assuming standard variable names are used
 */
function disconnect_all()
{
	global $cqp;
	global $mysql_link;
	if (isset($cqp))
		disconnect_global_cqp();
	if(isset($mysql_link))
		mysql_close($mysql_link);
}

/**
 * note - this function should replace all direct calls to mysql_query,
 * thus avoiding duplication of error-checking code.
 */ 
function do_mysql_query($sql_query)
{
	global $mysql_link;
	global $print_debug_messages;

	if ($print_debug_messages)
		print_debug_message("About to run the following MySQL query:\n\n$sql_query\n");
		
	$result = mysql_query($sql_query, $mysql_link);
	
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
			
	if ($print_debug_messages)
		print_debug_message("The query ran successfully.\n");
		
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
			exiterror_mysqlquery(mysql_errno($mysql_link),
				mysql_error($mysql_link), __FILE__, __LINE__);
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
			exiterror_mysqlquery(mysql_errno($mysql_link),
				mysql_error($mysql_link), __FILE__, __LINE__);
		if ($print_debug_messages)
			print_debug_message("The query ran successfully.\n");
	
		if (!($fh = fopen($filename, 'w'))) 
		{
			mysql_free_result($result);
			exiterror_general("Could not open file for write ( $filename )", __FILE__, __LINE__);
		}
		
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

/**
 * currently, this function just wraps pre_echo; 
 * but we might want to create a more HTML-table-friendly version later.
 */
function print_debug_message($message)
{
	pre_echo($message);
}


/**
 * Echoes a string, but with HTML 'pre' tags (ideal for debug messages)
 */
function pre_echo($s)
{
	echo "<pre>\n$s\n</pre>";
}




function make_thousands($number)
{
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



/* Simple function to replicate PHP 5 behaviour - copied from php.net */
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}



/* replacement for htmlspecialcharacters which DOESN'T change & to &amp; if it is already part of */
/* an entity; otherwise equiv to htmlspecialchars($string, ENT_COMPAT, 'UTF-8', false) */
function cqpweb_htmlspecialchars($string)
{
	$string = str_replace('&', '&amp;', $string);
	$string = str_replace('<', '&lt;', $string);
	$string = str_replace('>', '&gt;', $string);
	$string = str_replace('"', '&quot;', $string);

	return preg_replace('/&amp;(\#?\w+;)/', '&$1', $string);
}


/*
 * A "handle" can only be a word character.
 */
function cqpweb_handle_enforce($string)
{
	return preg_replace('/\W/', '', $string);
}

function cqpweb_handle_check($string)
{
	return (bool) preg_match('/\W/', $string);
}



/* $u will be treated as a relative address if it does not begin with "http"
   and as an absolute address if it does 
   */
function url_absolutify($u)
{
	/// todo: change to substr to save wear and tear on the regex engine
	if (preg_match('/\Ahttp/', $u))
		/* address is already absolute */
		return $u;
	else
		/* make address absolute by adding server of this script plus folder path of this URI  */
		/* this may not be foolproof, because it assumes that the path will always lead to the */
		/* folder in which the current php script is located -- but should work for most cases */
		return 'http://' . $_SERVER['SERVER_NAME'] 
			. preg_replace('/\/[^\/]*\z/', '/', $_SERVER['REQUEST_URI']) . $u;
}



/* url_string_is_valid() - checks whether current script has $_GET[uT] == "y" */
function url_string_is_valid()
{
	if (! array_key_exists('uT', $_GET) )
		return false;
	if ($_GET['uT'] != 'y')
		return false;
	return true;
}




/**
 * returns a string of "var=val&var=val&var=val"
 * 
 * $changes must be an array of arrays, where each array consists of [0] a field name & [1] the new value
 * 
 * if [1] is an empty string, that pair is not included
 * 
 * WARNING: adds values that weren't there at the START of the string
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
		/* is urlencode needed here? */
		/* presumably so, since $_GET appears to be un-makesafed automatically */
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

/* returns a string of "<input type="hidden" name="key" value= */
/* changes = array of arrays */
/* where each array consists of [0] a field name  */
/* 						& [1] the new value */
/* if [1] is an empty string, that pair is not included */
/* WARNING: adds values that weren't there at the START of the string */
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
	
	if (in_array($username, $a))
		return true;
	else
		return false;
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


// THIS IS A DEBUG FUNCTION - COMMENT OUT WHEN I GO TO PRODUCTION
function show_var(&$var, $scope=false, $prefix='unique', $suffix='value')
{
	/* some code off the web to get the variable name */
	if($scope)	$vals = $scope;
	else			$vals = $GLOBALS;
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

// THIS IS A DEBUG FUNCTION - COMMENT OUT WHEN I GO TO PRODUCTION
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
	
	<p class="errormessage">&nbsp;<br/>
		&nbsp; <br/>
		We are sorry, but that part of CQPweb has not been built yet.
	</p>
	
	</body>
	</html>
	<?php
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


// this shouldn't now be needed and can be deleted as cqp.inc.php does not use these constants any more
/*
function create_pipe_handle_constants()
{
	define("IN",  0);	/* stdin,  i.e. input  TO   the child * /
	define("OUT", 1);	/* stdout, i.e. output FROM the child * /
	define("ERR", 2);	/* stderr, i.e. errors FROM the child * /
}
*/



/**
 * Runs a script in perl and returns up to 10Kb of text written to STDOUT
 * by that perl script, or an empty string if Perl writes nothing to STDOUT.
 * 
 * It reads STDERR if nothing is written to STDOUT.
 * 
 * This function is not currently used.
 * 
 * script_path	path to the script, relative to current PHP script (string)
 * arguments	anything to add after the script name (string)
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







/* returns an array of category names */
function catquery_list_categories($qname)
{
	global $mysql_link;
	$sql_query = "select category_list from saved_catqueries where catquery_name = '"
		. mysql_real_escape_string($qname)
		.'\'';
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	list($list) = mysql_fetch_row($result);
	return explode('|', $list);
}


/* returns an array of category values for a given catquery, with ints (reference numbers) indexing strings (category names */
/* from and to parameters are INCLUSIVE */
function catquery_get_categorisation_table($qname, $from, $to)
{
	global $mysql_link;
	
	/* find out the dbname from the saved_catqueries table */
	$dbname = catquery_find_dbname($qname);
	
	$from = (int)$from;
	$to = (int)$to;
	
	$sql_query = "select refnumber, category from $dbname where refnumber >= $from and refnumber <= $to";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
			
	$a = array();
	while ( ($row = mysql_fetch_row($result)) !== false)
		$a[(int)$row[0]] = $row[1];
	
	return $a;

}



function catquery_find_dbname($qname)
{
	global $mysql_link;

	$qname = mysql_real_escape_string($qname);
	$sql_query = "select dbname from saved_catqueries where catquery_name ='$qname'";
	$result = mysql_query($sql_query, $mysql_link);
	
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	if (mysql_num_rows($result) < 1)
		exiterror_general("The categorised query <em>$qname</em> could nto be found in the database.", __FILE__, __LINE__);
	list($dbname) = mysql_fetch_row($result);

	return $dbname;
}


function print_footer()
{
	global $username;
	global $corpus_title;
	
	if (isset($corpus_title))
	{
		$help_cell = '<td align="center" class="cqpweb_copynote">
			<a class="cqpweb_copynote_link" href="help.php" target="_NEW">Corpus and tagset help</a>
		</td>';
	}
	?>
	<hr/>
	<table class="concordtable" width="100%">
		<tr>
			<td align="left" class="cqpweb_copynote">
				CQPweb v<?php echo CQPWEB_VERSION;?> &#169; 2008-2009
			</td>
			<?php echo $help_cell; ?>
			<td align="right" class="cqpweb_copynote">
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





function database_disable_keys($arg)
{
	global $mysql_link;
	$sql_query = "alter table $arg disable keys";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false)
		exiterror_mysqlquery(mysql_errno($mysql_link),
			mysql_error($mysql_link), __FILE__, __LINE__);
}
function database_enable_keys($arg)
{
	global $mysql_link;
	$sql_query = "alter table $arg enable keys";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false)
		exiterror_mysqlquery(mysql_errno($mysql_link),
			mysql_error($mysql_link), __FILE__, __LINE__);
}










function add_system_message($header, $content)
{
	global $mysql_link;
	global $instance_name;
	$sql_query = "insert into system_messages set 
		header = '" . mysql_real_escape_string($header) . "', 
		content = '" . mysql_real_escape_string($content) . "', 
		message_id = '$instance_name'";
	/* timestamp is defaulted */
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

}

function delete_system_message($message_id)
{
	global $mysql_link;
	$message_id = preg_replace('/\W/', '', $message_id);
	$sql_query = "delete from system_messages where message_id = '$message_id'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}

function display_system_messages()
{
	global $mysql_link;
	global $instance_name;
	global $username;
	global $this_script;
	
	$su = user_is_superuser($username);

	$sql_query = "select * from system_messages order by timestamp desc";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	

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



function recursive_delete_directory($path)
{
	$files_to_delete = scandir($path);
	foreach($files_to_delete as &$f)
	{
		if ($f == '.' || $f == '..')
			continue;
		if (is_dir($f))
		{
			recursive_delete_directory("$path/$f");
			continue;
		}
		unlink("$path/$f");
	}
	rmdir($path);
}



/**
 * this function stores values in a table that would be too big to send via GET
 * instead, they are referenced in the web form by their id code (which is passed by get) and
 * retrieved by the scrip that processes the user input.
 * 
 * The return value is the id code that you should use in the web form.
 * 
 * Things stored in the longvalues table are deleted when they are 5 days old.
 * 
 * The retrieval function is longvalue_retrieve
 *  
 */
function longvalue_store($value)
{
	global $instance_name;
	global $mysql_link;
	
	/* clear out old longvalues */
	$sql_query = "delete from system_longvalues where timestamp < DATE_SUB(NOW(), INTERVAL 5 DAY)";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);		
	
	$value = mysql_real_escape_string($value);
	
	$sql_query = "insert into system_longvalues (id, value) values ('$instance_name', '$value')";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);	
	
	return $instance_name;
}


/**
 * Retrieval function for values stored with longvalue_store (q.v.)
 */
function longvalue_retrieve($id)
{
	global $mysql_link;
	
	$id = mysql_real_escape_string($id);
	
	$sql_query = "select value from system_longvalues where id = '$id'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_general('The referenced values was not found in the dataabse. Please redo from scratch!', 
			__FILE__, __LINE__);	
	
	$r = mysql_fetch_row($result);
		
	return $r[0];
}






?>