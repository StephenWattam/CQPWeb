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
 * This file contains several things:
 * 
 * (1) Constant definitions for the system.
 * 
 * (2) The three global objects ($Config, $User, $Corpus) into which everything is stuffed.
 * 
 * (3) The environment startup and shutdown functions that need to be called to get things moving.
 * 
 */


/*
 * THE FOLLOWING IS TEMP CODE TO PRESERVE THE OLD CONFIG SYSTEM WHILE IMPLEMENTING THE NEW
 */

/* include defaults and settings */
if (file_exists("settings.inc.php"))
	require( '' . "settings.inc.php");	/* concatenate to avoid annoying bug warning */
require('../lib/config.inc.php');
require("../lib/defaults.inc.php");




/* ------------------------------- */
/* Constant definitions for CQPweb */
/* ------------------------------- */


/* 
 * version number of CQPweb 
 */
define('CQPWEB_VERSION', '3.1.0');

/*
 * FLAGS for cqpweb_startup_environment()
 */
 
define('CQPWEB_STARTUP_NO_FLAGS',              0);
define('CQPWEB_STARTUP_DONT_CONNECT_CQP',      1);
define('CQPWEB_STARTUP_DONT_CONNECT_MYSQL',    2);
define('CQPWEB_STARTUP_DONT_CHECK_URLTEST',    4);
define('CQPWEB_STARTUP_CHECK_ADMIN_USER',      8);

/* 
 * plugin type constants 
 */

define('PLUGIN_TYPE_UNKNOWN',                  0);
define('PLUGIN_TYPE_ANNOTATOR',                1);
define('PLUGIN_TYPE_FORMATCHECKER',            2);
define('PLUGIN_TYPE_TRANSLITERATOR',           4);
define('PLUGIN_TYPE_POSTPROCESSOR',            8);
define('PLUGIN_TYPE_ANY',                      1|2|4|8);


/*
 * user account state constants
 */

define('USER_STATUS_UNVERIFIED',               0);
define('USER_STATUS_ACTIVE',                   1);
define('USER_STATUS_SUSPENDED',                2);



/* --------------------- *
 * Global object classes *
 * --------------------- */



/**
 * Class of which each run of CQPweb should only ever have ONE - it holds config settings as public variables
 * (sometimes hierarchically using other objects).
 * 
 * The instantiation should always be the global $Config object.
 * 
 * Has only one function, its constructor, which loads all the config settings.
 * 
 * Config settings in the database are NOT loaded by the constructor.
 * 
 * 
 */ 
class CQPwebEnvConfig
{
	/* we don't declare any members - the constructor function creates them dynamically */
	
	public function __construct()
	{	
		/* import config variables from the global state of the config file */
		if (file_exists("settings.inc.php"))
			require( '' . "settings.inc.php");
		require('../lib/config.inc.php');
		require('../lib/defaults.inc.php');
		
		$variables = get_defined_vars();
		foreach ($variables as $k => $v)
			$this->$k = $v;
			
		/* check compulsory config variables */
		$compulsory_config_variables = array(
				'superuser_username',
				'mysql_webuser',
				'mysql_webpass',
				'mysql_schema',
				'mysql_server',
				'cqpweb_tempdir',
				'cqpweb_uploaddir',
				'cwb_datadir',
				'cwb_registry'
			);
		foreach ($compulsory_config_variables as $which)
			if (!isset($this->$which))
				exiterror_general("CRITICAL ERROR: \$$which has not been set in the configuration file.");

		/* and now, let's organise the directory variables into something saner */
		$this->dir = new stdClass;
		$this->dir->cache = $this->cqpweb_tempdir;
		unset($this->cqpweb_tempdir);
		$this->dir->upload = $this->cqpweb_uploaddir;
		unset($this->cqpweb_uploaddir);
		$this->dir->index = $this->cwb_datadir;
		unset($this->cwb_datadir);
		$this->dir->registry = $this->cwb_registry;
		unset($this->cwb_registry);
	}
}


/**
 * Class of which each run of CQPweb should only ever have ONE - it represents the logged in user.
 * 
 * The instantiation should always be the global $User object.
 * 
 * Has only one function, its constructor, which loads all the info. * 
 */ 
class CQPwebEnvUser 
{
	/** Is there a logged in user? (bool) */
	public $logged_in = false;
	/* we start by assuming no one is logged in, then see if someone is */
	
	public function __construct()
	{

// temp code. Delete as soon as we're off Apache.
global $username;
$username = ( isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] :  '__unknown_user' );
if ($username != '__unknown_user') $this->logged_in = true;
		
		
		/* import database fields as object members. */
		if ($this->logged_in)
		{
			$result = do_mysql_query("select * from user_info where username = '$username'");
			foreach (mysql_fetch_assoc($result) as $k => $v)
				if (!isset($this->$k))
					$this->$k = $v;
			/* the "if isset" above is a bit paranoid on my part. Can probably dispose of it later..... TODO */
		}
	}
}



/**
 * Class of which each run of CQPweb should only ever have ONE - it represents the current corpus.
 * 
 * The instantiation should always be the global $Corpus object.
 * 
 * Has only one function, its constructor, which loads all the info. * 
 */ 
class CQPwebEnvCorpus 
{
	/** are we running within a particular corpus ? */
	public $specified = false;
	
	/** is this a corpus created by and belonging to an individual user? */
	public $is_user_corpus = false;
	 
	public function __construct()
	{
		global $Config;
		global $corpus_sql_name;
		
		/* first: try to identify the corpus. */
// note that eventually all the corpus settings will end up in the DB or coming via http, rather than using the following hack:
		$this->name = $corpus_sql_name;
		if (!empty($this->name))
			$this->specified = true;


		
		
		/* import database fields as object members. */
		if ($this->specified)
		{
			$result = do_mysql_query("select * from corpus_info where corpus = '$this->name'");
			foreach (mysql_fetch_assoc($result) as $k => $v)
				if (!isset($this->$k))
					$this->$k = $v;
			/* the "if" above is a bit paranoid on my part. Can probably dispose of it later..... TODO */
		}
	}
}


/* ============================== *
 * Startup and shutdown functions *
 * ============================== */



/**
 * Declares a plugin for later use.
 *
 * This function will normally be used only in the config file.
 * It does not do any error checking, that is done later by the plugin
 * autoload function.
 * 
 * TODO: it would be handy to move this function elsewhere as it is messy to have it in environment.
 * 
 * @param class                The classname of the plugin. This should be the same as the
 *                             file that contains it, minus .php.
 * @param type                 The type of plugin. One of the following constants:
 *                             PLUGIN_TYPE_ANNOTATOR,
 *                             PLUGIN_TYPE_FORMATCHECKER,
 *                             PLUGIN_TYPE_TRANSLITERATOR,
 *                             PLUGIN_TYPE_POSTPROCESSOR.
 * @param path_to_config_file  What it says on the tin; optional.
 * @return                     No return value.
 */
function declare_plugin($class, $type, $path_to_config_file = NULL)
{
	global $plugin_registry;
	if (!isset($plugin_registry))
		$plugin_registry = array();
	
	$temp = new stdClass();
	
	$temp->class = $class;
	$temp->type  = $type;
	$temp->path  = $path_to_config_file;
	
	$plugin_registry[] = $temp;
}




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
	
	
	// TODO, move into here the setup of plugins
	// (so this is done AFTER all functions are imported, not
	// in the defaults.inc.php file)

	// TODO, move into here setting the HTTP response headers, charset and the like???
	// (make dependent on whether we are writing plaintext or an HTML response?
	// (do we want a flag CQPWEB_STARTUP_NONINTERACTIVE for when HTML response is NTO wanted?
	
	/* create global settings options */
	$Config = new CQPwebEnvConfig();
	
	
//var_dump($Config);
	
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

	/* now the DB is connected, we can do the next two. */

	$User   = new CQPwebEnvUser();
	
	$Corpus = new CQPwebEnvCorpus();

	
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

	/* We do the following after starting up the global objects, because without it, 
	 * we don't have the CSS path for exiterror. */
	if (($flags & CQPWEB_STARTUP_DONT_CHECK_URLTEST) || PHP_SAPI=='cli')
		;
	else
		if (!url_string_is_valid())
			exiterror_bad_url();
	if ($flags & CQPWEB_STARTUP_CHECK_ADMIN_USER)
		if (!user_is_superuser($User->username))
			exiterror_general("You do not have permission to use this part of CQPweb.");
	

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
