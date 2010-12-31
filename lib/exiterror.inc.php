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
 * The exiterror module prints out an error page and performs necessary
 * error-actions before shutting down CQPweb.
 * 
 * Functions outside the module should always call one of the functions
 * that builds up a message template, e.g. exiterror_general.
 * 
 * These functions in turn call the ones that do formatting etc.
 */
 

//////////// TODO reformat these functions and associated CSS to produce a nice page like BNCweb's
//////////// ideally based on tables rather than errormessage paras

/**
 * Writes the start of an error page, if and only if nothing has been sent back
 * via HTTP yet.
 * 
 * If the HTTP response headers have been sent, it does nothing.
 * 
 * Used by other exiterror functions (can be called unconditionally).
 */
function exiterror_beginpage($page_title = NULL)
{
	global $debug_messages_textonly;
	global $css_path;
	
	if (! isset($page_title))
		$page_title = "CQPweb has encountered an error!";
	
	if (headers_sent())
		return;
	
	if ($debug_messages_textonly)
	{
		header("Content-Type: text/plain; charset=utf-8");
		echo "$page_title\n";
		for ($i= 0, $n = strlen($page_title); $i < $n ; $i++)
			echo '=';
		echo "\n\n";
	}
	else
	{
		header('Content-Type: text/html; charset=utf-8');
		
		?><html>
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php echo $page_title; ?></title>
			<link rel="stylesheet" type="text/css" href="<?php echo $css_path; ?>" />
		</head>
		<body>
		<?php
	}
}

/**
 * Function internal to the exiterror module.
 * 
 * Prints error message lines in either plaintext or HTML.
 * 
 * (The actual HTML that causes the formatting of the error page is here.)
 */
function exiterror_printlines($lines)
{
	global $debug_messages_textonly;
	
	$before = ($debug_messages_textonly ? '' : '<p class="errormessage">');
	$after  = ($debug_messages_textonly ? "\n\n" : "</p>\n\n");

	if ($debug_messages_textonly)
		foreach($lines as &$l)
			$l = wordwrap($l, 72);
	
	foreach($lines as &$l)
		echo $before . $l . $after;
}

/**
 * Function internal to exiterror module.
 * 
 * Prints a footer iff we're in HTML context; then kills CQPweb.
 * 
 * If $backlink is true, a link to the home page for the corpus is included.
 */
function exiterror_endpage($backlink = false)
{
	global $debug_messages_textonly;
	
	if ( ! $debug_messages_textonly)
	{
		if ($backlink)
		{
			?>
			<hr/>
			<p class="errormessage"><a href="index.php">Back to corpus home page.</p>
			<?php
		}
		print_footer();

	}
	
	disconnect_all();
	exit();
}

/**
 * Function internal to exiterror module.
 * 
 * Add s aascript/line location to a specified array of error messages.
 */
function exiterror_msg_location(&$array, $script=NULL, $line=NULL)
{
	global $debug_messages_textonly; 		
	if (isset($script, $line))
		$array[] = ($debug_messages_textonly ? 
					"... in file $script line $line." : 
					"... in file <b>$script</b> line <b>$line</b>."
					);
}




/** Obsolete function now that exiterror_general does the same thing. */
function exiterror_fullpage($errormessage, $script=NULL, $line=NULL)
{
	exiterror_general($errormessage, $script, $line);
}

/**
 * Primary function to be called by other modules.
 * 
 * Prints the specified error messages (with location of error if we're told that)
 * and then exits.
 * 
 * The error message is allowed to be an array of paragraphs.
 */
function exiterror_general($errormessage, $script=NULL, $line=NULL)
{
	$msg[] = "CQPweb encountered an error and could not continue.";
	if (is_array($errormessage))
		$msg = array_merge($msg, $errormessage);
	else
		$msg[] = $errormessage;
	exiterror_msg_location($msg, $script, $line);
	
	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}

function exiterror_bad_url()
{
	$msg[] = "We're sorry, but CQPweb could not read your full URL.";
	$msg[] = "Proxy servers sometimes truncate URLs, try again without a proxy server!";
	
	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage(true);
}

function exiterror_cacheoverload()
{
	$msg[] = "CRITICAL ERROR - CACHE OVERLOAD!";
	$msg[] = "CQPweb tried to clear cache space but failed!";
	$msg[] = "Please report this error to the system administrator.";
	
	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}


/** used for freqtable overloads too */
function exiterror_dboverload()
{
	$msg[] = "CRITICAL ERROR - DATABASE CACHE OVERLOAD!";
	$msg[] = "CQPweb tried to clear database cache space but failed!";
	$msg[] = "Please report this error to the system administrator.";
	
	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}


function exiterror_toomanydbprocesses($process_type)
{
	global $mysql_process_limit;
	global $mysql_process_name;

	$msg[]= "Too many database processes!";
	$msg[] = "There are already {$mysql_process_limit[$process_type]} " 
		. "{$mysql_process_name[$process_type]}	databases being compiled.";
	$msg[] = "Please use the Back-button of your browser and try again in a few moments.";
	
	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}



function exiterror_mysqlquery($errornumber, $errormessage, $script=NULL, $line=NULL)
{
	$msg[] = "A mySQL query did not run successfully!";
	$msg[] = "Error # $errornumber: $errormessage ";
	exiterror_msg_location($msg, $script, $line);
	
	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}

function exiterror_mysqlquery_show($errornumber, $errormessage, $origquery, $script=NULL, $line=NULL)
{
	$msg[] = "A mySQL query did not run successfully!";
	$msg[] = "Error # $errornumber: $errormessage ";
	$msg[] = "Original query: \n\n$origquery\n\n";
	exiterror_msg_location($msg, $script, $line);

	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}

function exiterror_parameter($errormessage, $script=NULL, $line=NULL)
{
	$msg[] = "A script was passed a badly-formed parameter set!";
	$msg[] = $errormessage;
	exiterror_msg_location($msg, $script, $line);

	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}



function exiterror_arguments($argument, $errormessage, $script=NULL, $line=NULL)
{
	$msg[] = "A function was passed an invalid argument type!";
	$msg[] = "Argument value was $argument. Problem:";
	$msg[] = $errormessage;
	exiterror_msg_location($msg, $script, $line);

	exiterror_beginpage();
	exiterror_printlines($msg);
	exiterror_endpage();
}




/** CQP error messages in exiterror format. */
function exiterror_cqp($error_array)
{
	$msg[] = "CQP sent back these error messages:";
	$msg = array_merge($msg, $error_array);
	exiterror_beginpage('CQPweb -- CQP reports errors!');
	exiterror_printlines($msg);
	exiterror_endpage();
}



/** Obsolete now that adding a page header is automatic. */
function exiterror_cqp_full($error_array)
{
	exiterror_cqp($error_array);
}

?>