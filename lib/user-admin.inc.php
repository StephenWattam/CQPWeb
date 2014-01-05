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





/* include defaults and settings */
require('../lib/environment.inc.php');


/* library files */
require('../lib/user-lib.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/library.inc.php');

/**
 * @file
 * 
 * Receiver script for a whole bunch of actions relating to users.
 * 
 * Some come via redirect from various forms; others come via admin action.
 * 
 * The actions are controlled via switcyh and mostly work by sorting through
 * the "_GET" parameters, and then calling the underlying functions
 * (mostly in user-lib).
 */


$script_called_from_admin = (isset ($_GET['userFunctionFromAdmin']) && $_GET['userFunctionFromAdmin'] == 1); 


/* a slightly tricky one: either admin did it, in which case we need admin login; or new user did it, 
 * in which case we do not need any login at all ........... */
cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP 
						| ($script_called_from_admin ? CQPWEB_STARTUP_CHECK_ADMIN_USER : CQPWEB_STARTUP_ALLOW_ANONYMOUS_ACCESS));
/* BUT NOTE, some of the script below will re-impose the user-test. */

if ($script_called_from_admin)
{
	$Config->css_path = $Config->css_path_for_adminpage;
	$Config->run_location = 'adm';
}
else
{
	$Config->css_path = $Config->css_path_for_userpage;
	$Config->run_location = 'usr';
}

$script_mode = isset($_GET['userAction']) ? $_GET['userAction'] : false; 

switch ($script_mode)
{
	/*
	 * Cases in this switch are grouped according to the TYPE OF USER ACCESS.
	 * 
	 * First, come the ones where NO LOGIN IS REQUIRED.
	 * 
	 * So no additional check is required other than the one done at environment startup.
	 * 
	 */
	
case 'userLogin':

	//TODO

	if (isset($_GET['locationAfter']))
		$next_location = $_GET['locationAfter'];
	else
		$next_location = '../usr/index.php?thisQ=welcome';
	break;


case 'userLogout':

	// TODO
	
	/* redirect to mainhome */
	$next_location = '../usr/index.php?thisQ=logoutDone';
	break;




	
case 'newUser':

	/* CREATE NEW USER ACCOUNT */

	if (!isset($_GET['newUsername'],$_GET['newPassword'],$_GET['newEmail']))
		exiterror_general("Missing information: you must specify a username, password and email address to create an account!");
	
	$new_username = trim($_GET['newUsername']);

	if (0 < preg_match('/\W/', $new_username))
		exiterror_general("The username you specified contains an illegal character: only letters, numbers and underscore are allowed.");
	
	if (0 < mysql_num_rows(do_mysql_query("select id from user_info where username = '$new_username'")))
		exiterror_general("The username you specified is not available: please go back and specify another!");

	/* allow anything in password except empty string */
	$password = $_GET['newPassword'];
	if (empty($password))
		exiterror_general("The password cannot be an empty string!");		
	if (! $script_called_from_admin)
	{
		// TODO: check for the standard password-typed-twice thing.
	}
	
	$email = trim($_GET['newEmail']);
	if (empty($email))
		exiterror_general("The email address for a new account cannot be an empty string!");
	
	/* OK, all 3 things now collected, so we can call the sharp-end function... */
	
	add_new_user($new_username, $password, $email);
	
	/* verification status: do we email? do we change it? */
	if ($script_called_from_admin)
	{
		/* look for extra _GET parameter.... */
		if (!isset($_GET['verifyType']))
			/* it SHOULDN'T be absent! but let's just guess. */
			$verify_type = ($Config->cqpweb_no_internet ? 'no:DontVerify' : 'yes');
		else
			$verify_type = $_GET['verifyType'];
	}
	else
		/* with a user-self-create, we always request verification via email */
		$verify_type = 'yes';

	switch($verify_type)
	{
	case 'yes':
		send_user_verification_email($new_username);
		break;
	case 'no:Verify':
		change_user_status($new_username, USER_STATUS_ACTIVE);
		break;
	case 'no:DontVerify':
	default:
		/* do nowt. */
		break;
	}
	
	// tODO auto group assign using regexen
	
	/* and redirect out */
	
	if ($script_called_from_admin)
		$next_location = "index.php?thisF=showMessage&message=" . urlencode("User account '$new_username' has been created.") . "&uT=y";
	else
		$next_location = ""; /* i.e. {BASE}/usr */
	break;


case 'verifyUser':

	/* incoming check for user verification link; DOES NOT originate from admin interface. */
	
	$key = trim($_GET['v']);

	if (0 < preg_match('/^[abcdef1234567890]{32}$/',$key))
	{
		$next_location = 'index.php?thisQ=verify&verifyScreenType=badlink&uT=y';
		break;
	}
	
	if (false === ($the_username = resolve_user_verification_key($key)))
		exiterror_general("That activation code was not recognised. Go back and try again, or request a new verification email.");
	else
	{
		change_user_status($the_username, USER_STATUS_ACTIVE);
		unset_user_verification_key($the_username);
	}

	$next_location = 'index.php?thisQ=verify&verifyScreenType=success&uT=y';
	break;


case 'resendVerifyEmail':

	/* re-send a verification email, w/ a new activation code */
	
	break;

case 'resetUserPassword':

	/* change a user's password to the new value specified. */
	
	/* nb dos nto count as requiring a loing, since that is only one of THREE ways this function can be accessed */
	
	
	/* if the user is logged in, they must supply the old password */
	//TODO;
	
	/* if the user is not logged in, they must provide a suitable verification key */
	// TODO
	
	// finally, if the user is admin, they can do what they damn well please
	//TODO

	break;


	/*
	 * 
	 * now come the cases where A USER LOGIN IS REQUIRED and WE ERROR-MESSAGE IF IT WAS NOT THERE 
	 * 
	 */



case 'revisedUserSettings':

	/* change user's interface preferences */


	update_multiple_user_settings($username, parse_get_user_settings());
	$next_location = 'index.php?thisQ=userSettings&uT=y';
	break;

	
	/*
	 * 
	 * Finally, defualt is an unconditional abort, so it really doesn't matter whether or not one is logged in.
	 * 
	 */
	
default:

	/* dodgy parameter: ERROR out. */
	exiterror_general("A badly-formed user administration operation was requested!"); 
	break;
}


if (isset($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */


/** Gets all "newSetting" parameters from $_GET and sanitises for correct type of input. */
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
			case 'thin_default_reproducible':
				$settings[$m[1]] = (bool)$v;
				break;
			
			/* string settings */
			case 'realname':
			case 'email':
				/* This will be sanitised at the DB interface level. */
				 $settings[$m[1]] = $v;
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



?>
