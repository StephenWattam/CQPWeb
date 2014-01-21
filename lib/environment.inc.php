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




/* ------------------------------- */
/* Constant definitions for CQPweb */
/* ------------------------------- */


/* 
 * version number of CQPweb 
 */
define('CQPWEB_VERSION', '3.1.1');

/*
 * FLAGS for cqpweb_startup_environment()
 */
 
define('CQPWEB_STARTUP_NO_FLAGS',              0);
define('CQPWEB_STARTUP_DONT_CONNECT_CQP',      1);
define('CQPWEB_STARTUP_DONT_CONNECT_MYSQL',    2);
define('CQPWEB_STARTUP_DONT_CHECK_URLTEST',    4);
define('CQPWEB_STARTUP_CHECK_ADMIN_USER',      8);
define('CQPWEB_STARTUP_ALLOW_ANONYMOUS_ACCESS',16);


/*
 * Run location constants 
 */

define('RUN_LOCATION_CORPUS',                  0);
define('RUN_LOCATION_MAINHOME',                1);
define('RUN_LOCATION_ADM',                     2);
define('RUN_LOCATION_USR',                     3);
define('RUN_LOCATION_CLI',                     4);


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
define('USER_STATUS_PASSWORD_EXPIRED',         3);


/*
 * privilege types
 */

define('PRIVILEGE_TYPE_NO_PRIVILEGE',          0);	/* can be used to indicate absence of one or more privileges; not used in the DB */
define('PRIVILEGE_TYPE_CORPUS_RESTRICTED',     1);
define('PRIVILEGE_TYPE_CORPUS_NORMAL',         2);
define('PRIVILEGE_TYPE_CORPUS_FULL',           3);
/* note that the above 4 definitions create a greater-than/less-than sequence. Intentionally so. */











/*
 * THE FOLLOWING IS TEMP CODE TO PRESERVE THE OLD CONFIG SYSTEM WHILE IMPLEMENTING THE NEW
 */

/* include defaults and settings */
if (file_exists("settings.inc.php"))
	require( '' . "settings.inc.php");	/* concatenate to avoid annoying bug warning */
require('../lib/config.inc.php');
require('../lib/defaults.inc.php');















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
	
	public function __construct($run_location)
	{	
		/* import config variables from the global state of the config file */
		if (file_exists("settings.inc.php"))
			require( '' . "settings.inc.php");
		require('../lib/config.inc.php');
		require('../lib/defaults.inc.php');
		// TODO: some of the "settings" should be on the $Corpus object and not here. For now they are on both. */
		
		/* transfer imported variables to object members */
		$variables = get_defined_vars();
		unset(	$variables['GLOBALS'], $variables['_SERVER'], $variables['_GET'],
				$variables['_POST'],   $variables['_FILES'],  $variables['_COOKIE'],
				$variables['_SESSION'],$variables['_REQUEST'],$variables['_ENV'] 
				);
		foreach ($variables as $k => $v)
			$this->$k = $v;
		/* this also creates run_location as a member.... */

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
		
		/* CSS action based on run_location */
		switch ($this->run_location)
		{
		case RUN_LOCATION_MAINHOME:     $this->css_path = $this->css_path_for_homepage;     break;
		case RUN_LOCATION_ADM:          $this->css_path = $this->css_path_for_adminpage;    break;
		case RUN_LOCATION_USR:          $this->css_path = $this->css_path_for_userpage;     break;
		/* tacit default: RUN_LOCATION_CORPUS, where the $Corpus object takes responsibility for
		 * setting the global $Config css_path appropriately. */
		}
	}
}


/**
 * Class of which each run of CQPweb should only ever have ONE - it represents the logged in user.
 * 
 * The instantiation should always be the global $User object.
 * 
 */ 
class CQPwebEnvUser 
{
	/** Is there a logged in user? (bool) */
	public $logged_in;
	
	/** full array of privileges (db objects) available to this user (individually or via group) */
	public $privileges;
 
	public function __construct()
	{
		global $Config;
		
// TODO temp code. Delete when global username no longer needed.
global $username;

		/* if this environment is in a CLI script, count us as being logged in as the first admin user */ 
		if (PHP_SAPI == 'cli')
		{
			list($username) = list_superusers();
			$this->logged_in = true; 
		}
		else
		{
			/* look for logged on user */
			if (isset($_COOKIE[$Config->cqpweb_cookie_name]))
			{
				if (false === ($username = check_user_cookie_token($_COOKIE[$Config->cqpweb_cookie_name])))
				{
					/* no one is logged in */
					$username = '__unknown_user';
					// TODO maybe change the above?
					$this->logged_in = false;
				}
				else
				{
					$this->logged_in = true;
					/* we don't need to re-send the cookie. But we do need to touch it in cache. */
					touch_cookie_token($_COOKIE[$Config->cqpweb_cookie_name]);
					/* cookie tokens which don't get touched will eventually get old enough to be deleted */
				}
			}
		}


		/* now we know whether we are logged in and if so, who we are, set up the user information */
		if ($this->logged_in)
		{
			/* Update the last-seen date (on every hit from user's browser!) */
			touch_user($username);
			
			/* import database fields as object members. */
			foreach ( ((array)get_user_info($username)) as $k => $v)
				if (!isset($this->$k))
					$this->$k = $v;
			/* will also import $username --> $User->username which is canonical way to acces it. */
			/* the "if isset" above is a bit paranoid on my part. Can probably dispose of it later..... TODO */
		}
		
		/* finally: look for a full list of privileges that this user has. */
		$this->privileges = ($this->logged_in ? get_collected_user_privileges($username) : array());	
	}
	
	public function is_admin()
	{
		return ( PHP_SAPI=='cli' || ($this->logged_in && user_is_superuser($this->username)) );
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
	
	/** This is set to a privilege constant to indicate what level of privilege the currently-logged-on used has. */
	public $access_level;
	
	public function __construct()
	{
		/* first: try to identify the corpus. */
		// dirty hack. TODO to be scrubbed once the settings are not in global scope. Deduce from URL instead.
		global $corpus_sql_name;
		$this->name = $corpus_sql_name;
		if (!empty($this->name))
			$this->specified = true;
		/* if specified is not true, then $Config->run_location will tell you where we are running from. */


		/* only go hunting for more info on the $Corpus if one is actually specified...... */
		if ($this->specified)
		{
			// the corpus settings are already in global space, but let's get them into the object too
			// eventually, they will go into corpus_info and this clunky hack can be deleted....
			// TODO
			require( '' . "settings.inc.php");	/* concatenate to avoid annoying bug warning */
			$variables = get_defined_vars();
			unset(	$variables['GLOBALS'], $variables['_SERVER'], $variables['_GET'],
					$variables['_POST'],   $variables['_FILES'],  $variables['_COOKIE'],
					$variables['_SESSION'],$variables['_REQUEST'],$variables['_ENV'] 
					);
			foreach ($variables as $k => $v)
				$this->$k = $v;
			
	
			/* some settings then transfer to $Config */
			global $Config;
			if (isset($this->css_path))
			{
				$Config->css_path = $this->css_path;
				unset($this->css_path);
			}
		
			/* import database fields as object members. */
			$result = do_mysql_query("select * from corpus_info where corpus = '$this->name'");
			foreach (mysql_fetch_assoc($result) as $k => $v)
				if (!isset($this->$k))
					$this->$k = $v;
			/* the "if" above is a bit paranoid. Can probably dispose of it later..... TODO */

			/* finally, since we are in a corpus, we need to ascertain (a) whether the user is allowed
			 * to access this corpus; (b) at what level the access is. */
			$this->ascertain_access_level();
			
			if ($this->access_level == PRIVILEGE_TYPE_NO_PRIVILEGE)
			{
				/* redirect to a page telling them they do not have the privilege to access this corpus. */ 
				set_next_absolute_location("../usr/index.php?thisQ=accessDenied&corpusDenied={$this->name}&uT=y");
				cqpweb_shutdown_environment();
				exit;
				/* otherwise, we know that the user has some sort of access to the corpus, and we can continue */
			}
		}
	}
	
	/**
	 * Sets up the access_level member to the privilege type indicating 
	 * the HIGHEST level of access to whihc the currently-logged-in user
	 * is entitled for this corpus.
	 */ 
	private function ascertain_access_level()
	{
		global $User;
		
		/* superusers have full access to everything. */
		if ($User->is_admin())
		{
			$this->access_level = PRIVILEGE_TYPE_CORPUS_FULL;
			return;
		}
		
		/* otherwise we must dig through the privilweges owned by this user. */
		
		/* start by assuming NO access. Then look for the highest privilege this user has. */
		$this->access_level = PRIVILEGE_TYPE_NO_PRIVILEGE;

		foreach($User->privileges as $p)
		{
			switch($p->type)
			{
			/* a little trick: we know that these constants are 3, 2, 1 respectively,
			 * so we can do the following: */
			case PRIVILEGE_TYPE_CORPUS_FULL:
			case PRIVILEGE_TYPE_CORPUS_NORMAL:
			case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
				if (in_array($this->name, $p->scope_object))
					if ($p->type > $this->access_level)
						$this->access_level = $p->type;
				break;
			default:
				break;
			}
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
function cqpweb_startup_environment($flags = CQPWEB_STARTUP_NO_FLAGS, $run_location = RUN_LOCATION_CORPUS)
{
	if ($run_location == RUN_LOCATION_CLI)
		if (php_sapi_name() != 'cli')
			exit("Critical error: Cannot run CLI scripts over the web!\n");
	
	/* -------------- *
	 * TRANSFROM HTTP *
	 * -------------- */

	/* the very first thing we do is set up _GET, _POST etc. .... */

	/* MAGIC QUOTES, BEGONE! */
	
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
	
	/* WE ALWAYS USE GET! */
	
	/* sort out our incoming variables.... */
	foreach($_POST as $k=>$v)
		$_GET[$k] = $v;
	/* now, we can be sure that any bits of the system that rely on $_GET being there will work. */	
	
	
	/* -------------- *
	 * GLOBAL OBJECTS *
	 * -------------- */
	
	
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
	$Config = new CQPwebEnvConfig($run_location);
	
	
	
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


	/* now the DB is connected, we can do the other two global objects. */

	$User   = new CQPwebEnvUser();
	
	$Corpus = new CQPwebEnvCorpus();

	
	// TODO make this dependent on debug status
	ob_implicit_flush(true);



		
	

	/* We do the following AFTER starting up the global objects, because without it, 
	 * we don't have the CSS path for exiterror. */
	if (($flags & CQPWEB_STARTUP_DONT_CHECK_URLTEST) || PHP_SAPI=='cli')
		;
	else
		if (!url_string_is_valid())
			exiterror_bad_url();
	if ($flags & CQPWEB_STARTUP_CHECK_ADMIN_USER)
		if (!$User->is_admin())
			exiterror_general("You do not have permission to use this part of CQPweb.");
	
	/* end of function cqpweb_startup_environment */
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
}



?>
