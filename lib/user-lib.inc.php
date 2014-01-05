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
 * ==============
 * USER FUNCTIONS
 * ==============
 */


function user_name_to_id($user)
{
	$user = mysql_real_escape_string($user);
	$result = do_mysql_query("select id from user_info where group_name = '$user'");
	if (mysql_num_rows($result) < 1)
		exiterror_general("Invalid user name specified at database level: $user");
	list($id) = mysql_fetch_row($result);
	return $id;
}

function user_id_to_name($id)
{
	$id = (int)$id;
	$result = do_mysql_query("select username from user_info where id = $id");
	if (mysql_num_rows($result) < 1)
		exiterror_general("Invalid user ID specified at database level: $id");
	list($name) = mysql_fetch_row($result);
	return $name;
}

/**
 * returns flat array of usernames
 */
function get_list_of_users()
{
	$result = do_mysql_query("select username from user_info");
	$u = array();
	while (false !== ($r = mysql_fetch_row($result)))
		$u[] = $r[0];	
	return $u;
}

/**
 * Adds a new user with the specified username, password and email.
 * 
 */
function add_new_user($username, $password, $email, $initial_status = USER_STATUS_UNVERIFIED, $expiry_time = 0)
{
	global $Config;
	
	/* checks, e.g. on usernames containing non-word characters, must be performed at a higher level. 
	 * Here, we only check for database safety. */
	$username = mysql_real_escape_string($username);
	$email = mysql_real_escape_string($email);	
	
	/* no need to check password, since we create a passhash from the password & don't store it */
	$passhash = generate_new_hash_from_password($password);


	$sql_query = "INSERT INTO user_info (
		username,
		realname,
		email,
		passhash,
		acct_status,
		expiry_time,
		conc_kwicview,
		conc_corpus_order,
		cqp_syntax,
		context_with_tags,
		use_tooltips,
		thin_default_reproducible,
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
		'$email',
		'$passhash',
		$initial_status,
		0,
		1,
		1,
		0,
		0,
		1,
		1,
		{$Config->default_calc_stat},
		{$Config->default_colloc_minfreq},
		{$Config->default_colloc_minfreq},
		" . (-1 * ($Config->default_colloc_range-2)) . ",
		" . ($Config->default_colloc_range-2) . ",
		{$Config->default_max_dbsize},
		'au'
		)"
		;
		
	do_mysql_query($sql_query);
	
	/* check for automatic group ownership */
	
	foreach (list_group_regexen() as $group => $regex)
		if (0 < preg_match("/$regex/", $email))
			add_user_to_group($username, $group);
}




/**
 * Add a whole number of users.
 * 
 * The argument is a matrix (array of arrays).
 * 
 * Each inner array has:
 *    0 => username
 *    1 => password
 *    2 => email
 * 
 */
function add_multiple_users($data_matrix)
{
	// TODO : not sure how much use this function will actually be.
	foreach($data_matrix as $arr)
		add_new_user($arr[0], $arr[1], $arr[2]);
}



/**
 * Deletes a specified user account (and all its saved and categorised queries)
 * 
 * If the username passed in is an empty string,
 * it will return without doing anything; all non-word
 * characters are removed for database safety.
 */
function delete_user($user)
{
	global $Config;
	
	/* db sanitise */
	$user = preg_replace('/\W/', '', $user);
	if (empty($user))
		return;
	
	$id = user_name_to_id($user);
	
	/* unjoin all groups */
	do_mysql_query("delete from user_memberships where user_id = $id");

	/* when we have user privileges, we'll need to delete them as well... */
	// TODO
	
	/* delete uploaded files (and directory) */
	$d = $Config->dir->upload . '/' . $user;
	if (is_dir($d))
		recursive_delete_directory($d);
	
	/* delete user saved queries and categorised queries */
	$result = do_mysql_query("select query_name, saved from saved_queries where saved > 0 and user = $user");
	while (false !== ($q = mysql_fetch_object($result)))
	{
		if ($q->saved == 2)
		{
			/* catquery */
			$inner_result = do_mysql_query("select dbname from saved_catqueries where catquery_name='{$q->query_name}'");
			list($dbname) = mysql_fetch_row($inner_result);			
			do_mysql_query("drop table if exists $dbname");
			do_mysql_query("delete from saved_catqueries where catquery_name='{$q->query_name}'");
		}
		/* for both catquery and saved query */
		delete_cached_query($q->query_name);
	}

	/* delete user itself */
	do_mysql_query("delete from user_info where id = $id");
}



function update_user_password($user, $new_password)
{
	$user = mysql_real_escape_string($user);

	if (empty($new_password))
		exiterror_general("Cannot set password to empty string!");
	$new_passhash = generate_new_hash_from_password($new_password);
	
	do_mysql_query("update user_info set passhash = '$new_passhash' where username = '$user'");
}


/**
 * Sends out an account verification email,
 * with a freshly-generated verification key.
 * 
 * The verification key goes into the db.
 */
function send_user_verification_email($user)
{
	/* create key and set in database */
	$verify_key = set_user_verification_key($user);
	
	list($email,$realname) = mysql_fetch_row(do_mysql_query("select email, realname from user_info where username='$user'"));
	
	if (empty($realname) || $realname == 'unknown person')
	{
		$realname = 'User';
		$user_address = $email;
	}
	else
		$user_address = "$realname <$email>";
	
	$verify_url = url_absolutify('../usr/redirect.php?redirect=verifyUser&v=' . urlencode($verify_key) . '&uT=y');
	
	$body = <<<HERE
Dear $realname,

A new user account has been created on our CQPweb server in
association with your email address.

To validate this new account, and confirm as yours the address to
which this email was sent, please visit the following link:

$verify_url

If your email client disables external links, copy and paste 
the address above into a web browser.

If CQPweb cannot read your verification code successfully
from the link, it will ask you for a verification key. 
In that case, copy-and-paste the following 32-letter code:

$verify_key 

If you DID NOT create this account, or request it to be created
on your behalf, then all you need to do is ignore this email; 
the account will then never be activated.

Yours sincerely,

The CQPweb User Administration System


HERE;
	
	if (!empty($Config->cqpweb_root_url))
		$body .= $Config->cqpweb_root_url . "\n"; 
	
	send_cqpweb_email($user_address, 'CQPweb: please verify user account creation!', $body);
}

/**
 * Sets a key to verify either an account or a password reset.
 * 
 * Creates and returns a 32-byte key, which is also stored in the DB for the 
 * specified user.
 * 
 * Note this function does not check for the reality of the user -
 * if a nonexistent user is specified, then the result will be no change
 * to the DB, and the key returned will be useless.
 */
function set_user_verification_key($user)
{
	$user = mysql_real_escape_string($user);
	
	$key = md5(uniqid($user,true));

	do_mysql_query("update user_info set verify_key = '$key' where username = '$user'");
	
	return $key;
}

/**
 * Removes a user verification key for the given user, setting the entry in the DB to NULL.
 */
function unset_user_verification_key($user)
{
	$user = mysql_real_escape_string($user);
	
	do_mysql_query("update user_info set verify_key = NULL where username = '$user'");
}

/**
 * Gets the username associated with a given verification key, if one exists. 
 * 
 * If the key does not exist, returns false.
 */
function resolve_user_verification_key($key)
{
	$key = mysql_real_escape_string($key);
	$result = do_mysql_query("select username from user_info where verify_key = '$key'");
	if (1 > mysql_num_rows($result))
		return false;
	else
	{
		list($u) = mysql_fetch_row($result);
		return $u;
	}
}

/**
 * Resets the user account status: 2nd arg must be one of the USER_STATUS_* constants.
 */
function change_user_status($user, $new_status)
{
	$new_status = (int) $new_status;
	$user = mysql_real_escape_string($user);
	
	/* do nothing if mew status not a valid status constant */
	switch ($new_status)
	{
	case USER_STATUS_UNVERIFIED:
	case USER_STATUS_ACTIVE:
	case USER_STATUS_SUSPENDED:
		do_mysql_query("update user_info set acct_status = $new_status where username = '$user'");
		break;
	default:
		/* do nothing */
		break;
	}
}




/**
 * Retrieves a given setting for a particular user.
 * 
 * Note that it's not necessary for the user to be the same person
 * as the user logged-on in the environment (global $User).
 */
function get_user_setting($username, $field)
{
	return get_all_user_settings($username)->$field;
}

/** 
 * Returns an object (stdClass with members corresponding to the
 * fields of the user_info table in the database) containing
 * the specified user's data.
 * 
 * Returns false in case of a nonexistent user.
 * 
 * Note that it's not necessary for the user to be the same person
 * as the user logged-on in the environment (global $User).
 */  
function get_all_user_settings($username)
{	
	static $cache;
		
	if (isset($cache[$username]))
		return $cache[$username];
	
	$username = mysql_real_escape_string($username);
	
	$result = do_mysql_query("SELECT * from user_info WHERE username = '$username'");
	
	if (mysql_num_rows($result) == 0)
			return false;
	else
	{
		$cache[$username] = mysql_fetch_object($result);
		return $cache[$username];
	}
}


/** 
 * Updfate a user setting relating to the user interface tweaks.
 * TODO change name?
 */
function update_user_setting($username, $field, $setting)
{
	$field = mysql_real_escape_string($field);
	$setting = mysql_real_escape_string($field);
	$username = mysql_real_escape_string($username);
	
	/* only certain fields are allowed to be changed via this function. */
	$fields_allowed = array(
		'conc_kwicview',
		'conc_corpus_order',
		'cqp_syntax',
		'context_with_tags',
		'use_tooltips',
		'coll_statistic',
		'coll_freqtogether',
		'coll_freqalone',
		'coll_from',
		'coll_to',
		'max_dbsize',
		'linefeed',
		'thin_default_reproducible',
	);
	
	/* nb. This treats all values as string, although most are ints, but it seems to work... */
	do_mysql_query("UPDATE user_info SET $field = '$setting' WHERE username = '$username'");
}

/** 
 * Update many user-interface settings all at once.
 * TODO change name?
 * 
 * @param $settings 
 */
function update_multiple_user_settings($username, $settings)
{	
	foreach ($settings as $field => $value)
		update_user_setting($username, $field, $value);
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



/*
 * =======================
 * LOGON-RELATED FUNCTIONS
 * =======================
 */






/**
 * For creating new passwords. Returns the hash to store in the database.
 */
function generate_new_hash_from_password($password)
{
	/* we are using BLOWFISH with 2^10 iterations, so start of salt always same: */
	$salt = '$2a$10$';
	/* get 22 (pseudo-)random bytes */
	$salt_language = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	for ($i = 0; $i < 22 ; $i++)
		$salt .= $salt_language[rand(0,63)];

	return crypt($password, $salt);
}



/**
 * Checks whether a given cookie token is for a valid login.
 * 
 * Returns the username if it is, and returns false if it isn't.
 */
function check_user_cookie_token($token)
{
	$token = mysql_real_escape_string($token);

	$result = do_mysql_query("select user_id from user_cookie_tokens where token = '$token'");

	if (mysql_num_rows($result) < 1)
		return false;
	else
	{
		list($u_id) = mysql_fetch_row($result);
		return user_id_to_name($u_id);
	}
}

/**
 * Creates a new pseudorandom string of letters and numbers (32 chars in length).
 */
function generate_new_cookie_token()
{
	/* NB. this might be crackable... */
	$token_language = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_~|';
	
	while (1)
	{
		$token = '';
		for ($i = 0; $i < 32 ; $i++)
			$token .= $token_language[rand(0,64)];
		if (false === check_user_cookie_token($token))
			break;
	}
	
	return $token;
}

/**
 * Resets the expiry time of a cookie token to the maximum 
 * logon persist time in the future.
 */
function touch_cookie_token($token)
{
	global $Config;
	$expiry = time() + $Config->cqpweb_cookie_max_persist;
	$token = mysql_real_escape_string($token);
	do_mysql_query("update user_cookie_tokens set expiry = $expiry where token = '$token'");
}

/**
 * Creates a DB entry for the specified cookie token,
 * signifiying a login of the username given.
 */
function register_new_cookie_token($username, $token)
{
	$u_id = user_id_from_name($username);

	global $Config;
	$expiry = time() + $Config->cqpweb_cookie_max_persist;
	
	$token = mysql_real_escape_string($token);
	do_mysql_query("insert into user_cookie_tokens (token, user_id, expiry) values ('$token', $u_id, $expiry)");
}

/**
 * Deletes all cookie tokens whose expiry time is in the past.
 */
function cleanup_expired_cookie_tokens()
{
	do_mysql_query("delete from user_cookie_tokens where expiry < " . time() );
}

/**
 * Deletes a specified cookie token.
 */
function delete_cookie_token($token)
{
	$token = mysql_real_escape_string($token);
	do_mysql_query("delete from user_cookie_tokens where token = '$token'");
}






/*
 * ====================
 * USER GROUP FUNCTIONS
 * ====================
 */




function group_name_to_id($group)
{
	assert_not_reserved_group($group);
	$group = mysql_real_escape_string($group);
	$result = do_mysql_query("select id from user_groups where group_name = '$group'");
	if (mysql_num_rows($result) < 1)
		exiterror_general("Invalid group name specified at database level: $group");
	list($id) = mysql_fetch_row($result);
	return $id;
}

function group_id_to_name($id)
{
	$id = (int)$id;
	$result = do_mysql_query("select group_name from user_groups where id = $id");
	if (mysql_num_rows($result) < 1)
		exiterror_general("Invalid group ID specified at database level: $id");
	list($name) = mysql_fetch_row($result);
	return $name;
}

/**
 * Assertion: causes an error abort if this group is one of the "reserved" 
 * group names (i.e. magic, can't be deleted from the database etc.)
 */
function assert_not_reserved_group($group)
{
	if ($group == 'superusers' || $group == 'everybody')
		exiterror("An illegal operation was attempted on one of the system-reserved groups, namely $group.");
}

/**
 * returns flat array of group names
 */
function get_list_of_groups()
{
	$result = do_mysql_query("select group_name from user_groups");
	$g = array();
	while (false !== ($r = mysql_fetch_row($result)))
		$g[] = $r[0];	
	return $g;
}



/**
 * Returns flat array of usernames
 */
function list_users_in_group($group)
{
	global $Config;
	
	/* specials: user membership not recorded in user_memberships table */
	if ($group == 'superusers')
		return explode('|', $Config->superuser_username);
	else if ($group == 'everybody')
		$sql = "select username from user_info";
	else
	{
		$g = group_name_to_id($group);
		$sql = "select user_info.username from user_memberships 
				inner join user_info on user_info.id = user_memberships.user_id where group_id = $g";
	}
	
	$result = do_mysql_query($sql);

	$users = array();
	
	while (false !== ($r = mysql_fetch_row($result)))
		$users[] = $r[0];
	
	return $users;
}

function get_group_info($group)
{
	$group = mysql_real_escape_string($group);
	$result = do_mysql_query("select * from user_groups where group_name = $group");
	if (1 > mysql_num_rows($result))
		exiterror_general("Info requested for non-existent group $group!");
	return mysql_fetch_object($result);
}

function get_all_groups_info()
{
	$result = do_mysql_query("select * from user_groups");
	$all = array();
	while (false !== ($o = mysql_fetch_object($result)))
		$all[] = $o;
	return $all;
}		

function add_user_to_group($user, $group, $expiry_time = 0)
{
	assert_not_reserved_group($group);
	$g = group_name_to_id($group);
	$u = user_name_to_id($user);
	$expiry_time = (int) $expiry_time;
	do_mysql_query("insert into user_memberships (user_id,group_id,expiry_time)values($u,$g,$expiry_time)");
}


function remove_user_from_group($user, $group)
{
	assert_not_reserved_group($group);
	$g = group_name_to_id($group);
	$u = user_name_to_id($user);
	do_mysql_query("delete from user_memberships where group_id = $g and user_id = $u");
}


/**
 * Returns associative array of groupname => group_autojoin_regex
 *  
 */
function list_group_regexen()
{
	$result = do_mysql_query("select group_name, autojoin_regex from user_groups");
	$list = array();
	while (false !== ($o = mysql_fetch_object($result)))
	{
		if (empty($o->autojoin_regex))
			continue;
		else
			$list[$o->group_name] = $o->autojoin_regex;
	}
	return $list;
}



//TODO
function add_new_group($group)
{

}




function delete_group($group)
{
	assert_not_reserved_group($group);
	
	$g = group_name_to_id($group);
	
	/* delete all memberships */
	do_mysql_query("delete from user_memberships where group_id = $g");
	
	/* delete group */
	do_mysql_query("delete from user_groups where id = $g");
}



function deny_group_access_to_corpus($corpus, $group)
{
//	$group = preg_replace('/\W/', '', $group);
//	
//	if ($corpus == '' || $group == '')
//		return;
//	if (! file_exists("../$corpus/.htaccess"))
//		return;
//	/* having got here, we know the $corpus variable is OK */
//	
//	/* don't check group in the same way -- we want to be  */
//	/* able to disallow access to nonexistent groups       */
//
//	$apache = get_apache_object(realpath("../$corpus"));
//	$apache->load();
//	$apache->disallow_group($group);
//	$apache->save();
}

function give_group_access_to_corpus($corpus, $group)
{
//	if ($corpus == '' || $group == '')
//		return;
//	if (! file_exists("../$corpus/.htaccess"))
//		return;
//	/* having got here, we know the $corpus variable is OK */
//
//	$apache = get_apache_object(realpath("../$corpus"));
//	$group_list = $apache->list_groups();
//	if (!in_array($group, $group_list))
//		return;
//	/* and having survived that, we know group is OK too */
//	
//	$apache->load();
//	$apache->allow_group($group);
//	$apache->save();
}

/**
 * Function wrapping multiple calls to give_group_access_to_corpus()
 * and deny_group_access_to_corpus().
 * 
 * $corpora_to_grant is a string of individual corpora, 
 * delimited by |
 * 
 * Any corpus not in that list -- access is denied.
 * 
 */ 
function update_group_access_rights($group, $corpora_to_grant)
{
	$to_grant = explode('|', $corpora_to_grant);
	
	foreach($to_grant as $c)
		give_group_access_to_corpus($c, $group);
	
	unset($c);
	
	foreach(list_corpora() as $c)
		if (!in_array($c, $to_grant))
			deny_group_access_to_corpus($c, $group);
}

function clone_group_access_rights($from_group, $to_group)
{
	/* checks for group validity */
	if ($from_group == $to_group)
		return;
	$apache = get_apache_object('nopath');
	$group_list = $apache->list_groups();
	if (!in_array($from_group, $group_list))
		return;
	if (!in_array($to_group, $group_list))
		return;
	
	$list_of_corpora = list_corpora();
	foreach ($list_of_corpora as $c)
	{
		$apache->set_path_to_web_directory("../$c");
		$apache->load();
		if ( in_array($from_group, $apache->get_allowed_groups()) )
			/* allow */
			$apache->allow_group($to_group);
		else
			/* deny */
			$apache->disallow_group($to_group);
		$apache->save();
	}
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
		(user, macro_name, macro_num_args, macro_body)
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
						where user='$username' 
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

	$result = do_mysql_query("select * from user_macros where user='$username'");

	while (false !== ($r = mysql_fetch_object($result)))
	{
		$block = "define macro {$r->macro_name}({$r->macro_num_args}) ' ";
		/* nb. Rather than use str_replace here, maybe use the CQP:: escape method? */
		$block .= str_replace("'", "\\'", strtr($r->macro_body, "\t\r\n", "   ")) . " '";
		$cqp->execute($block);
	}
}



?>
