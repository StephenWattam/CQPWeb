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


/*
 * This file contains the main script for the CQPweb API-via-HTTP.
 * 
 * It processes incoming requests and calls other bits of CQPweb in
 * such a way as to send back the results of each function in
 * accordance with the API documentation.
 * 
 * This is generally as plain text (easily explode()-able or otherwise
 * manipulable within Perl or PHP).
 */

/**
 * @file
 * 
 * This file contains two things:
 * 
 * (1) The environment startup and shutdown functions that need to be called to get things moving.
 * 
 * (2) The three global objects ($Config, $User, $Corpus) into which everything is stuffed.
 * 
 * 
 */


/* include defaults and settings */
if (file_exists("settings.inc.php"))
	require( '' . "settings.inc.php");	/* concatenate to avoid annoying bug warning */
require("../lib/defaults.inc.php");



/*
 * FLAGS for cqpweb_startup_environment()
 */
 
define('CQPWEB_STARTUP_NO_FLAGS',             0);
define('CQPWEB_STARTUP_DONT_CONNECT_CQP',     1);
define('CQPWEB_STARTUP_DONT_CONNECT_MYSQL',   2);
define('CQPWEB_STARTUP_DONT_CHECK_URLTEST',   4);


/**
 * Function that starts up CQPweb and sets up the required environment.
 * 
 * All scripts that require the environment should call this function.
 * 
 * It should be called *after* the inclusion of most functions, but
 * *before* the inclusion of admin functions (if any).
 * 
 * Ultimately, this function will be used instead of the various "setup
 * stuff" that uis currently done repeatedly, per-script.
 * 
 * Pass in bitwise-OR'd flags to control the behaviour. 
 * 
 * TODO When we have the new user system, this function will prob get bigger
 * and bigger. Also when the system can be silent for the web-api, this
 * function will deal with it. As a result it will prob
 * be necessary to move this function, as well as the equiv shutdown
 * function, into a file of its own. (startup.inc.php) together with
 * depedencies like session setup functions, the control flag constants, etc.
 */
function cqpweb_startup_environment($flags = CQPWEB_STARTUP_NO_FLAGS)
{
	/** Global object containing information on system configuration. */
	global $Config;
	/** Global object containing information on the current user account. */
	global $User;
	/** Global object containing information on the current corpus. */
	global $Corpus;
	
	// TODO ,move into here the getting of the username
	// TODO, a call to session_start() -- and other cookie/login stuff -
	// prob belongs here.
	
	// TODO, move into here the setup of plugins
	// (so this is done AFTER all functions are imported, not
	// in the defaults.inc.php file)

	// TODO, move into here setting the HTTP response headers, charset and the like???
	// (make dependent on whether we are writing plaintext or an HTML response?
	// (do we want a flag CQPWEB_STARTUP_NONINTERACTIVE for when HTML response is NTO wanted?
	
	// TODO likewise have an implicit policy on ob_*_flush() usage in different scirpts.

	/*
	 * The flags are for "dont" because we assume the default behaviour
	 * is to need both a DB connection and a slave CQP process.
	 * 
	 * If one or both is not required, a script can be passed in to 
	 * save the connection (not much of a saving in the case of the DB,
	 * potentially quite a performance boost for the slave process.)
	 */
	if ($flags & CQPWEB_STARTUP_DONT_CONNECT_CQP)
		;
	else
		connect_global_cqp();
	
	if ($flags & CQPWEB_STARTUP_DONT_CONNECT_MYSQL)
		;
	else
		connect_global_mysql();

	
	/* create global settings options (these may have their own classes later on ) */
	$Config = new stdClass();
	$User   = new stdClass();
	$Corpus = new stdClass();
	
	
	// TODO make this dependent on debug status
	ob_implicit_flush(true);


	/* --------------------- */
	/* MAGIC QUOTES, BEGONE! */
	/* --------------------- */	
	
	/* In PHP > 5.4 magic quotes don't exist, but that's OK, because the function in the test will always
	 * return false. We also don't worry about multidimensional arrays, since CQPweb doesn't use them. 
	 * The test function also returns false if we are working in the CLI environment. */
	
	if (get_magic_quotes_gpc()) 
	{
		foreach ($_POST as $k => $v) 
		{
			unset($_POST[$k]);
			$_POST[stripslashes($k)] = stripslashes($v);
		}
		foreach ($_GET as $k => $v) 
		{
			unset($_GET[$k]);
			$_GET[stripslashes($k)] = stripslashes($v);
		}
	}

	/* We do the following after starting up the global objects, because without it, we don't have the CSS path. */
	if ($flags & CQPWEB_STARTUP_DONT_CHECK_URLTEST)
		;
	else
	{
		if (!url_string_is_valid())
			exiterror_bad_url();
	}
}

/**
 * Performs shutdown and cleanup for the CQPweb system.
 * 
 * The only thing that it will not do is finish off HTML. 
 * The script should do that separately -- BEFORE calling this function.
 * 
 * All scripts should finish by calling this function.
 */
function cqpweb_shutdown_environment()
{	
	/* these funcs have their own "if" clauses so can be called here unconditionally... */
	disconnect_global_cqp();
	disconnect_global_mysql();
	
	/* delete the global objects - in case they need to be rebuilt. */
	global $Config;
	global $User;
	global $Corpus;
	
	unset($Config, $User, $Corpus);
}



?>
