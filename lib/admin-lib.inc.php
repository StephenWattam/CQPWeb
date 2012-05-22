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
 * This file contains functions used in the administration of CQPweb.
 * 
 * It should generally not be included into scripts unless the user
 * is a sysadmin.
 */

// TODO -- check this against cwb_uncreate_corpus and prevent duplication of functionality
/**
 * Main corpus-deletion function.
 */
function delete_corpus_from_cqpweb($corpus)
{
	global $cwb_registry;
	global $cwb_datadir;
	$corpus = mysql_real_escape_string($corpus);
	
	if (empty($corpus))
		exiterror_general('No corpus specified. Cannot delete. Aborting.', __FILE__, __LINE__);	

	/* get the cwb name of the corpus, etc.: use require() so script dies if settings not found. */
	require("../$corpus/settings.inc.php");
	
	/* we can trust strtolower() because CWB standards define identifiers as ASCII */
	$corpus_cwb_lower = strtolower($corpus_cqp_name);
	
	/* check the corpus is actually there to delete */
	$result = do_mysql_query("select corpus, cwb_external from corpus_metadata_fixed where corpus = '$corpus'");
	if (mysql_num_rows($result) < 1)
		return;
	
	/* do we also want to delete the CWB data? */
	list($junk, $cwb_external) = mysql_fetch_row($result);
	$also_delete_cwb = !( (bool)$cwb_external);
	

	/* delete all saved queries, frequency tables, and dbs associated with this corpus */
	$result = do_mysql_query("select query_name from saved_queries where corpus = '$corpus'");
	while (($r = mysql_fetch_row($result)) !== false)
		delete_cached_query($r[0]);

	$result = do_mysql_query("select dbname from saved_dbs where corpus = '$corpus'");
	while (($r = mysql_fetch_row($result)) !== false)
		delete_db($r[0]);

	$result = do_mysql_query("select freqtable_name from saved_freqtables where corpus = '$corpus'");
	while (($r = mysql_fetch_row($result)) !== false)
		delete_freqtable($r[0]);
	
	/* delete the actual subcorpora */
	do_mysql_query("delete from saved_subcorpora where corpus = '$corpus'");
	
	/* delete main frequency tables */
	$result = do_mysql_query("select handle from annotation_metadata where corpus = '$corpus'");
	while (($r = mysql_fetch_row($result)) !== false)
		do_mysql_query("drop table if exists freq_corpus_{$corpus}_{$r[0]}");
	do_mysql_query("drop table if exists freq_corpus_{$corpus}_word");
	
	/* delete CWB freq-index table */
	do_mysql_query("drop table if exists freq_text_index_$corpus");

	/* delete the entries from corpus_metadata_fixed / variable / annotation */
	do_mysql_query("delete from corpus_metadata_fixed where corpus = '$corpus'");
	do_mysql_query("delete from corpus_metadata_variable where corpus = '$corpus'");
	do_mysql_query("delete from annotation_metadata where corpus = '$corpus'");

	
	/* clear the text metadata (see below) */
	delete_text_metadata_for($corpus);

	/* delete the web directory */
	recursive_delete_directory("../$corpus");
	
	/* what this implies is that a registry file / data WON'T be deleted 
	 * unless CQPweb created them in the first place -- even if they are in
	 * the CQPweb standard registry / data locations. */
	if ($also_delete_cwb)
	{
		/* delete the CWB registry and data */
		unlink("/$cwb_registry/$corpus_cwb_lower");
		recursive_delete_directory("/$cwb_datadir/$corpus_cwb_lower");
	}
	/* if they exist, delete the CWB registry and data for its __freq */
	if (file_exists("/$cwb_registry/{$corpus_cwb_lower}__freq"))
	{
		unlink("/$cwb_registry/{$corpus_cwb_lower}__freq");
		recursive_delete_directory("/$cwb_datadir/{$corpus_cwb_lower}__freq");
	}
	/* note, __freq deletion is not conditional on cwb_external -> also_delete_cwb
	 * because __freq corpora are ALWAYS created by CQPweb itself.
	 */
}




/**
 * Adds default htaccess files to the builtin subdirectories; removes
 * the autoconfig script if it exists.
 */
function restore_system_security()
{
	/* folders: adm, css, doc, lib; plus root folder */
	
	/* four of those are easy */
	if (file_exists('../.htaccess'))
		unlink('../.htaccess');
	if (file_exists('../doc/.htaccess'))
		unlink('../doc/.htaccess');
	if (file_exists('../css/.htaccess'))
		unlink('../css/.htaccess');
	if (file_exists('../rss/.htaccess'))
		unlink('../rss/.htaccess');
	
	/* adm needs a standard .htaccess which just allows superusers */
	if (file_exists('../adm/.htaccess'))
		unlink('../adm/.htaccess');
	$adm = get_apache_object(realpath('../adm'));
	$adm->allow_group('superusers');
	$adm->save();
	chmod("../adm/.htaccess", 0664);
	unset($adm);
	
	/* lib needs the same, but easier cos NO ONE allowed */
	if (file_exists('../lib/.htaccess'))
		unlink('../lib/.htaccess');
	file_put_contents('../lib/.htaccess', "deny from all");
	chmod("../lib/.htaccess", 0664);
	/* but javascript within lib needs to be allowed */
	if (file_exists('../lib/javascript/.htaccess'))
		unlink('../lib/javascript/.htaccess');
	file_put_contents('../lib/javascript/.htaccess', "allow from all");
	chmod("../lib/javascript/.htaccess", 0664);

	/* bin needs a total block also */
	if (file_exists('../bin/.htaccess'))
		unlink('../bin/.htaccess');
	file_put_contents('../bin/.htaccess', "deny from all");

	
	/* check that the autoconfig script in the root folder is GONE */
	if (file_exists('../cqpweb-autoconfig.php'))
		unlink('../cqpweb-autoconfig.php');
	if (file_exists('../cqpweb-autoconfig.php.gz'))
		unlink('../cqpweb-autoconfig.php.gz');
}

/**
 * Adds a new user.
 * 
 * Note the function can also be used to update the password. If the user already exists,
 * default values will not be reinserted into the user database.
 */
function add_new_user($username, $password, $email = NULL)
{
	$apache = get_apache_object('nopath');
	
	$username = preg_replace('/\W/', '', $username);
	$password = preg_replace('/\W/', '', $password);
	
	if ($username === '' || $password === '')
		exiterror_fullpage("Usernames and passwords can only contain letters, numbers and underscores.",
			__FILE__, __LINE__);
	
	$apache->new_user($username, $password);

	/* start by creating a blank entry for the user (or, retrieving an existing entry) */
	$profile = get_all_user_settings($username);
	
	/* then, overwrite the password as stored in the database */
	global $password_more_security;
	$db_password = ($password_more_security ? $apache->get_user_hashword($username) : $password);
	update_user_setting($username, 'password', $db_password);
	
	/* and if email has been passed into the function, likewise overwrite it */
	if (isset($email))
		update_user_setting($username, 'email', mysql_real_escape_string($email));
}

function add_batch_of_users($username_root, $number_in_batch, $password, $autogroup, $different_passwords = false)
{
	global $create_password_function;
	global $password_more_security;

	$apache = get_apache_object('nopath');

	$autogroup = preg_replace('/\W/', '', $autogroup);
	$password = preg_replace('/\W/', '', $password);
	if ($autogroup !== '')
	{
		$group_list = $apache->list_groups();
		if (!in_array($autogroup, $group_list))
			$apache->new_group($autogroup);
	}
	
	$number_in_batch = (int)$number_in_batch;
	
	/* get passwords and begin the text-file write */
	if ($different_passwords)
	{
		$password_list = $create_password_function($number_in_batch+2);
		header("Content-Type: text/plain; charset=utf-8");
		header("Content-disposition: attachment; filename=passwords_for_{$username_root}_batch.txt");
		echo "The following user accounts have been created.\n\nUsername\tPassword\n\n";
	}
	
	for ($i = 1 ; $i <= $number_in_batch; $i++)
	{
		if ($different_passwords)
		{
			$this_password = ($different_passwords ? $password_list[$i] : $password);
			echo "$username_root$i\t$this_password\n";
		}
		else
			$this_password = $password;
		
		$apache->new_user("$username_root$i", $this_password);
		if ($autogroup !== '')
			$apache->add_user_to_group("$username_root$i", $autogroup);

		$profile = get_all_user_settings("$username_root$i");
	
		/* then, overwrite the password as stored in the database */
		$db_password = ($password_more_security ? $apache->get_user_hashword("$username_root$i") : $this_password);
		update_user_setting("$username_root$i", 'password', $db_password);
	}
}

/**
 * Deletes a specified user account.
 * 
 * If the username passed in is an empty string,
 * it will return without doing anything; all non-word
 * characters are removed for database safety.
 */
function delete_user($user)
{
	$user = preg_replace('/\W/', '', $user);
	if (empty($user))
		return;
	$apache = get_apache_object('nopath');
	$apache->delete_user($user);
	delete_user_record($user);
}


/**
 * Deletes accounts of all usernames consisting of $prefix plus a string of one or more digits. 
 * 
 * @param $prefix  The prefix of the accounts to be deleted. Normally, these will be a bundle
 *                 of accounts created with batch.
 */
function delete_user_batch($prefix)
{
	$apache = get_apache_object('nopath');
	$prefix = preg_replace('/\W/', '', $prefix);
	if ($prefix === '')
		return;
	foreach ($apache->list_users() as $user)
	{
		if (preg_match("/^$prefix\d+$/", $user) > 0)
		{
			$apache->delete_user($user);
			delete_user_record($user);
		}
	}	
}


function add_user_to_group($user, $group)
{
	$apache = get_apache_object('nopath');

	
	$user = preg_replace('/\W/', '', $user);
	$group = preg_replace('/\W/', '', $group);
	
	if ($user === '' || $group === '')
		exiterror_fullpage("Invalid username or group name!",
			__FILE__, __LINE__);

	$apache->add_user_to_group($user, $group);
}

function remove_user_from_group($user, $group, $superuseroverride = false)
{
	/* block the removal of users from "superusers" unless specific override given */
	if ($group == 'superusers' && $superuseroverride !== true)
		return;
	
	if (empty($user))
		exiterror_fullpage("Unspecified username - cannot continue.");
	if (empty($group))
		exiterror_fullpage("Unspecified user group - cannot continue.");
	
	$apache = get_apache_object('nopath');
	$apache->delete_user_from_group($user, $group);
}

function delete_group($group)
{
	/* block the removal of "superusers" */
	if ($group == 'superusers')
		return;
	$apache = get_apache_object('nopath');
	$apache->delete_group($group);
}

function create_group($group)
{
	$apache = get_apache_object('nopath');
	$apache->new_group($group);
	/* note the apache object does input sanitation so no need to bother here */
}


function deny_group_access_to_corpus($corpus, $group)
{
	$group = preg_replace('/\W/', '', $group);
	
	if ($corpus == '' || $group == '')
		return;
	if (! file_exists("../$corpus/.htaccess"))
		return;
	/* having got here, we know the $corpus variable is OK */
	
	/* don't check group in the same way -- we want to be  */
	/* able to disallow access to nonexistent groups       */

	$apache = get_apache_object(realpath("../$corpus"));
	$apache->load();
	$apache->disallow_group($group);
	$apache->save();
}

function give_group_access_to_corpus($corpus, $group)
{
	if ($corpus == '' || $group == '')
		return;
	if (! file_exists("../$corpus/.htaccess"))
		return;
	/* having got here, we know the $corpus variable is OK */

	$apache = get_apache_object(realpath("../$corpus"));
	$group_list = $apache->list_groups();
	if (!in_array($group, $group_list))
		return;
	/* and having survived that, we know group is OK too */
	
	$apache->load();
	$apache->allow_group($group);
	$apache->save();
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

/**
 * Creates an Apache interface-object (that deals with htacces,s htpasswd, and htgroup
 * files) with the right settings for use in CQPweb.
 */
function get_apache_object($path_to_web_directory)
{
	global $path_to_apache_utils;
	global $cqpweb_accessdir;
	
	$obj = new apache_htaccess();
		
	$obj->set_AuthName('CQPweb');
	
	$obj->set_path_to_apache_password_utility_directory("/$path_to_apache_utils");
	$obj->set_path_to_groups_file("/$cqpweb_accessdir/.htgroup");
	$obj->set_path_to_password_file("/$cqpweb_accessdir/.htpasswd");
	
	$obj->set_path_to_web_directory($path_to_web_directory);

	return $obj;
}


function update_text_metadata_values_descriptions()
{
	global $update_text_metadata_values_descriptions_info;

	foreach($update_text_metadata_values_descriptions_info['actions'] as &$current_action)
	{
		$sql_query = "update text_metadata_values set description='{$current_action['new_desc']}' 
			where corpus       = '{$update_text_metadata_values_descriptions_info['corpus']}' 
			and   field_handle = '{$current_action['field_handle']}'
			and   handle       = '{$current_action['value_handle']}'";
		do_mysql_query($sql_query);
	}
}

/* NB there's a function in metadata.inc.php that does something very similar to this */
/* but this one takes its input from a global variable so that it can be called by admin-execute */
function update_corpus_metadata_fixed()
{
	global $update_corpus_metadata_info;
	
	$sql_query = "update corpus_metadata_fixed set ";
	$first = true;
	
	foreach ($update_corpus_metadata_info as $key => &$val)
	{
		$update_corpus_metadata_info[$key] = mysql_real_escape_string($val);
		if ($key == 'corpus')
			continue;
		$sql_query .= ($first ? '' : ', ');
		$sql_query .= "$key = '{$update_corpus_metadata_info[$key]}'";
		$first = false;
	}
	
	$sql_query .= " where corpus = '{$update_corpus_metadata_info['corpus']}'";

	do_mysql_query($sql_query);
}

/**
 * Adds an attribute-value pair to the variable-metadata table.
 * 
 * Note, there is no requirement for attribute names to be unique.
 */
function add_variable_corpus_metadata($corpus, $attribute, $value)
{
	$corpus = mysql_real_escape_string($corpus);
	$attribute = mysql_real_escape_string($attribute);
	$value = mysql_real_escape_string($value);
	
	$sql_query = "insert into corpus_metadata_variable (corpus, attribute, value) values
		('$corpus', '$attribute', '$value')";
	do_mysql_query($sql_query);
}

/**
 * Deletes an attribute-value pair from the variable-metadata table.
 * 
 * The pair to be deleted must both be specified, as well as the corpus,
 * because there is no requirement that attribute names be unique.
 */
function delete_variable_corpus_metadata($corpus, $attribute, $value)
{
	$corpus = mysql_real_escape_string($corpus);
	$attribute = mysql_real_escape_string($attribute);
	$value = mysql_real_escape_string($value);
	
	$sql_query = "delete from corpus_metadata_variable 
			where corpus    = '$corpus'
			and   attribute = '$attribute'
			and   value     = '$value'";
	do_mysql_query($sql_query);
}




/**
 * Creates a javascript function with $n password candidates that will write
 * one of its candidates to id=passwordField on each call.
 */
function print_javascript_for_password_insert($password_function = NULL, $n = 49)
{
	/* JavaScript function to insert a new string from the initialisation array */
	global $create_password_function;
	
	if (empty($password_function))
		$password_function = $create_password_function;

	foreach ($password_function($n) as $pwd)
		$raw_array[] = "'$pwd'";
	$array_initialisers = implode(',', $raw_array);
	
	return "

	<script type=\"text/javascript\">
	<!--
	function insertPassword()
	{
		if ( typeof insertPassword.index == 'undefined' ) 
		{
			/* Not here before ... perform the initilization */
			insertPassword.index = 0;
		}
		else
			insertPassword.index++;
	
		if ( typeof insertPassword.passwords == 'undefined' ) 
		{
			insertPassword.passwords = new Array( $array_initialisers);
		}
	
		document.getElementById('passwordField').value = insertPassword.passwords[insertPassword.index];
	}
	//-->
	</script>
	
	";
}


/**
 * password_insert_internal is the default function for CQPweb candidate passwords.
 * 
 * To get nicer candidate passwords, set a different function in config.inc.php
 * 
 * (for example, password_insert_lancaster -- which, however, is subject to the
 * webpage at Lancaster University that it exploits being available!)
 * 
 * Whatever function you use must be in a source file included() in adminhome.inc.php
 * (such as this file is). 
 * 
 * All password-creation functions must return an array of n candidate passwords.
 * 
 * CQPweb passwords can only contain the characters defined as \w in PCRE (i.e. 
 * letters, digits, underscore).
 * 
 */
function password_insert_internal($n)
{
	$pwd = array();
	
	for ( $i = 0 ; $i < $n ; $i++ )
	{
		$pwd[$i] = sprintf("%c%c%c%c%d%d%c%c%c%c",
						rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a),
						rand(0,9), rand(0,9),
						rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a)
						); 
	}
	return $pwd;
}


function password_insert_lancaster($n)
{
	$page = file_get_contents('https://www.lancs.ac.uk/iss/security/passwords/makepw.php?num='. (int)$n);
	
	return explode("\n", str_replace("\r\n", "\n", $page));
}

function password_insert_from_atoms($n)
{
	$pwd = array();

	/* get static variables from the file of atoms (separate file so this massive array
	 * is not loaded into RAM unless we need it) */
	if (! isset($password_atoms))
		include('../lib/password-atoms.inc.php');

	for ( $i = 0 ; $i < $n ; $i++ )
	{
		$pwd[$i] = 	$password_atoms[rand(0, $password_atoms_N - 1)] 
					. rand(0,9) . rand(0,9)
					. $password_atoms[rand(0, $password_atoms_N - 1)];
	}
	// TODO
	// allow alternate pattern-schemes for password suggestions to be called on
	// e.g. to allow choice between scramble-rand (like we have in password_insert_internal)
	// and lexical-rand, like we have here.
	// and, what's mroe, options for inserting extra $%^&£ punc-marks into the password suggestion. 
	// so ultimately, we should ahve just one func for creating password-suggestions (mebbe?)

	return $pwd;
}


/**
 * Utility function for the create_text_metadata_for functions.
 * 
 * Returns nothing, but deletes the text_metadata_for table and aborts the script 
 * if there are bad text ids.
 * 
 * (NB - doesn't do any other cleanup e.g. temporary files).
 * 
 * This function should be called before any other updates are made to the database.
 */
function create_text_metadata_check_text_ids($corpus)
{
	if (false === ($bad_ids = create_text_metadata_get_bad_ids($corpus, 'text_id')))
		return;

	/* database revert to zero text metadata prior to abort */
	do_mysql_query("drop table if exists text_metadata_for_" . mysql_real_escape_string($corpus));
	do_mysql_query("delete from text_metadata_fields where corpus = '" . mysql_real_escape_string($corpus) . '\'');
	
	$msg = "The data source you specified for the text metadata contains badly-formatted text"
		. " ID codes, as follows: <strong>"
		. $bad_ids
		. "</strong> (text ids can only contain unaccented letters, numbers, and underscore).";
	
	exiterror_general($msg);
}

/**
 * Utility function for the create_text_metadata_for functions.
 * 
 * Returns nothing, but deletes the text_metadata_for table and aborts the script 
 * if there are any non-word values in the specified field.
 * 
 * Use for categorisation columns. A BIT DIFFERENT to how we do it for text ids
 * (different error message).
 * 
 * (NB - doesn't do any other cleanup e.g. temporary files).
 * 
 * This function should be called before any other updates are made to the database.
 * 
 * 
 */
function create_text_metadata_check_field_words($corpus, $field)
{
	if (false === ($bad_ids = create_text_metadata_get_bad_ids($corpus, $field)))
		return;
	
	/* database revert to zero text metadata prior to abort */
	do_mysql_query("drop table if exists text_metadata_for_" . mysql_real_escape_string($corpus));
	do_mysql_query("delete from text_metadata_fields where corpus = '" . mysql_real_escape_string($corpus) . '\'');
	
	$msg = "The data source you specified for the text metadata contains badly-formatted "
		. " category handles in field <strong>$field</strong>, as follows: <strong>"
		. $bad_ids
		. "</strong> (category handles can only contain unaccented letters, numbers, and underscore).";
	
	exiterror_general($msg);	
}

/**
 * Returns false if there are no bad ids in the field specified.
 * 
 * If there are bad ida, a string containing those ids is returned.
 */
function create_text_metadata_get_bad_ids($corpus, $field)
{
	$corpus = mysql_real_escape_string($corpus);
	$field  = mysql_real_escape_string($field);
	
	$result = do_mysql_query("select distinct $field from text_metadata_for_$corpus 
								where $field REGEXP '[^A-Za-z0-9_]'");
	if (mysql_num_rows($result) == 0)
		return false;

	$bad_ids = '';
	while (($r = mysql_fetch_row($result)) !== false)
		$bad_ids .= " '${r[0]}';";
	
	return $bad_ids;	
}

/**
 * Wrapper round create_text_metadata_for() for when we need to create the file from CQP.
 * 
 * $fields_to_show is (part of) a CQP instruction: see admin-execute.inc.php 
 */
function create_text_metadata_for_from_xml($fields_to_show)
{
	global $cqp;
	global $create_text_metadata_for_info;
	global $cqpweb_uploaddir;
	global $corpus_cqp_name;

	$full_filename = "/$cqpweb_uploaddir/{$create_text_metadata_for_info['filename']}";

	/* get the $corpus_cqp_name variable by including the corpus's settings file */
	include("../{$create_text_metadata_for_info['corpus']}/settings.inc.php");

	$cqp->set_corpus($corpus_cqp_name);
	$cqp->execute('c_M_F_xml = <text> []');
	$cqp->execute("tabulate c_M_F_xml match text_id $fields_to_show > \"$full_filename\"");

	/* the wrapping is done: pass to create_text_metadata_for() */
	create_text_metadata_for();	
}



function create_text_metadata_for()
{
	/* this is an ugly but efficient way to get the data that I need for this */
	global $create_text_metadata_for_info;
	global $cqpweb_uploaddir;
	global $cqpweb_tempdir;
	
	
	$corpus = cqpweb_handle_enforce($create_text_metadata_for_info['corpus']);
	
	if (!is_dir("../$corpus"))
		exiterror_fullpage("Corpus $corpus does not seem to be installed!", __FILE__, __LINE__);	
	
	if (empty($create_text_metadata_for_info['filename']))
		exiterror_fullpage("No input file was specified!\nMetadata setup aborts.", __FILE__, __LINE__);
				
	$file = "/$cqpweb_uploaddir/{$create_text_metadata_for_info['filename']}";
	if (!is_file($file))
		exiterror_fullpage("The file [$file] is not a file!", __FILE__, __LINE__);

	$input_file = "/$cqpweb_tempdir/___install_temp_{$create_text_metadata_for_info['filename']}";
	
	$source = fopen($file, 'r');
	$dest = fopen($input_file, 'w');
	while (false !== ($line = fgets($source)))
		fputs($dest, rtrim($line, "\r\n") . "\t0\t0\t0\n");
	fclose($source);
	fclose($dest);

	/* cleanup the original file if we were asked to */
	if ($create_text_metadata_for_info['file_should_be_deleted'])
		unlink($file);

	
// what the hell does this do?
// it doesn't seem to do anything.
// cut it out, see if it still works???
//	$sql_query = "select handle from text_metadata_fields where corpus = '$corpus'";
//	$result = do_mysql_query($sql_query);
// end of bit that doesn't seem to do anything.
	
	/* note, size of text_id is 50 to allow possibility of non-decoded UTF8 - they should be shorter */
	$create_statement = "create table `text_metadata_for_$corpus`(
		`text_id` varchar(50) NOT NULL";
	
	$scan_statements = array();
	
	for ($i = 1; $i <= $create_text_metadata_for_info['field_count']; $i++)
	{
		if ($create_text_metadata_for_info['fields'][$i]['handle'] == '')
			continue;
			
		$create_text_metadata_for_info['fields'][$i]['handle'] 
			= cqpweb_handle_enforce($create_text_metadata_for_info['fields'][$i]['handle']);
			
		if ($create_text_metadata_for_info['fields'][$i]['classification'])
		{
			$create_statement .= ",\n\t\t`" 
				. $create_text_metadata_for_info['fields'][$i]['handle'] 
				. '` varchar(20) default NULL';
			$inserts_for_metadata_fields[] = "insert into text_metadata_fields 
				(corpus, handle, description, is_classification)
				values ('$corpus', '{$create_text_metadata_for_info['fields'][$i]['handle']}',
				'{$create_text_metadata_for_info['fields'][$i]['description']}', 1)";
			$scan_statements[] = array ('field' => $create_text_metadata_for_info['fields'][$i]['handle'],
									'statement' => 
									"select distinct({$create_text_metadata_for_info['fields'][$i]['handle']}) 
									from text_metadata_for_$corpus"
									);
			/* and add to list for which indexes are needed */
			$category_index_list[] = $create_text_metadata_for_info['fields'][$i]['handle'];
		}
		else
		{
			$create_statement .= ",\n\t\t`" 
				. $create_text_metadata_for_info['fields'][$i]['handle'] 
				. '` text default NULL';
			$inserts_for_metadata_fields[] = "insert into text_metadata_fields 
				(corpus, handle, description, is_classification)
				values ('$corpus', '{$create_text_metadata_for_info['fields'][$i]['handle']}',
				'{$create_text_metadata_for_info['fields'][$i]['description']}', 0)";
		}
	}
	/* note, varchar(20) seems ungenerous - fix this? */

	/* add the standard fields; begin list of indexes. */
	$create_statement .= ",
		`words` INTEGER NOT NULL default '0',
		`cqp_begin` BIGINT UNSIGNED NOT NULL default '0',
		`cqp_end` BIGINT UNSIGNED NOT NULL default '0',
		primary key (text_id)
		";
	if (! empty($category_index_list))
		foreach ($category_index_list as &$cur)
			$create_statement .= ", index($cur) ";
	
	/* finish off the rest of the create statement */
	$create_statement .= "
		) CHARSET=utf8 ;\n\n";


	$update_statement = '';
	if (isset($create_text_metadata_for_info['primary_classification']))
	{
		$px = (int)$create_text_metadata_for_info['primary_classification'];
		$pa = $create_text_metadata_for_info['fields'][$px]['handle'];
		if ($pa !== '')
			$update_statement = "update corpus_metadata_fixed set primary_classification_field = '$pa' 
				where corpus = '$corpus'";
	}

	if (isset($inserts_for_metadata_fields))
	{
		foreach($inserts_for_metadata_fields as &$ins)
			do_mysql_query($ins);
	}
	
	do_mysql_query($create_statement);
	
	do_mysql_infile_query("text_metadata_for_$corpus", $input_file);
	
	unlink($input_file);

	/* check resulting table for invalid text ids and invalid category handles */
	create_text_metadata_check_text_ids($corpus);
	/* we can re-use $category_index_list which contains only fieldnames of categorisations */
	if (!empty($category_index_list))
		foreach ($category_index_list as &$cur)
			create_text_metadata_check_field_words($corpus, $cur);
	
	
	if ($update_statement !== '')
		do_mysql_query($update_statement);

	foreach($scan_statements as &$current)
	{
		$result = do_mysql_query($current['statement']);

		while (($r = mysql_fetch_row($result)) !== false)
		{
			$add_value_sql = "insert into text_metadata_values 
				(corpus, field_handle, handle)
				values
				('$corpus', '{$current['field']}', '{$r[0]}')";
			do_mysql_query($add_value_sql);
		}
	}

	/* and, if requested, we can do all the other setup automatically */
	if ($create_text_metadata_for_info['do_automatic_metadata_setup'])
	{
		print_debug_message('About to start running auto-pre-setup functions');
		
		/* get the right global settings for these functions */
		import_settings_as_global($corpus);

		/* do unconditionally */
		populate_corpus_cqp_positions();
		
		/* if there are any classifications... */
		if (mysql_num_rows(
				do_mysql_query("select handle from text_metadata_fields 
					where corpus = '$corpus' and is_classification = 1")
				) > 0 )
			metadata_calculate_category_sizes();
			
		/* if there is more than one text ... */
		list($n) = mysql_fetch_row(do_mysql_query("select count(text_id) from text_metadata_for_$corpus"));
		if ($n > 1)		
			make_cwb_freq_index();
		
		/* do unconditionally */
		corpus_make_freqtables();
		
		print_debug_message('Auto-pre-setup functions complete.');
	}
}

/**
 * A much, much simpler version of create_text_metadata_for()
 * which simply creates a table of text_ids with no other info.
 * 
 * Unlike create_text_metadata_for() it must run from WITHIN the
 * corpus directory, not from within ../adm .
 */
function create_text_metadata_for_minimalist()
{
	global $path_to_cwb;
	global $cqpweb_tempdir;
	global $corpus_cqp_name;
	global $corpus_sql_name;
	global $cwb_registry;
	
	
	if (!is_dir("../$corpus_sql_name"))
		exiterror_fullpage("Corpus $corpus_sql_name does not seem to be installed!", __FILE__, __LINE__);	

	$input_file = "/$cqpweb_tempdir/___install_temp_metadata_$corpus_sql_name";

	exec("/$path_to_cwb/cwb-s-decode -n -r /$cwb_registry $corpus_cqp_name -S text_id"
		. " > $input_file");

	/* note, size of text_id is 50 to allow possibility of non-decoded UTF8 - they should be shorter */
	$create_statement = "create table `text_metadata_for_$corpus_sql_name`(
		`text_id` varchar(50) NOT NULL,
		`words` INTEGER NOT NULL default '0',
		`cqp_begin` BIGINT UNSIGNED NOT NULL default '0',
		`cqp_end` BIGINT UNSIGNED NOT NULL default '0',
		primary key (text_id)
		) CHARSET=utf8 ;\n\n";

	//$load_statement = "$mysql_LOAD_DATA_INFILE_command '$input_file' INTO TABLE text_metadata_for_$corpus_sql_name";

	do_mysql_query($create_statement);

	do_mysql_infile_query("text_metadata_for_$corpus_sql_name", $input_file);

	create_text_metadata_check_text_ids($corpus_sql_name);
	
	/* since it's minimilist, there are no classifications. */

	unlink($input_file);
	
	/* finally call position and word count update. */
	populate_corpus_cqp_positions();
}



/** 
 * Deletes the metadata table plus the records that log its fields/values.
 * this is a separate function because it reverses the "create_text_metadata_for" function 
 * and it is called by the general "delete corpus" function 
 */
function delete_text_metadata_for($corpus)
{
	$corpus = mysql_real_escape_string($corpus);
	
	/* delete the table */
	do_mysql_query("drop table if exists text_metadata_for_$corpus");
	
	/* delete its explicator records */
	do_mysql_query("delete from text_metadata_fields where corpus = '$corpus'");
	do_mysql_query("delete from text_metadata_values where corpus = '$corpus'");
}



/** support function for the functions that create/read from dump files. */
function dumpable_dir_basename($dump_file_path)
{
	if (substr($dump_file_path,	-7) == '.tar.gz')
		return substr($dump_file_path, 0, -7);
	else
		return rtrim($dump_file_path, '/');
}

/** support function for the functions that create/read from dump files. 
 *  Parameter: a directory to turn into a .tar.gz (path, WITHOUT .tar.gz at end) */
function cqpweb_dump_targzip($dirpath)
{
	$dir = end(explode('/', $dirpath));
	
	$back_to = getcwd();
	
	chdir($dirpath);
	chdir('..');
	
	exec("tar -cf $dir.tar $dir");
	exec("gzip $dir.tar");
	
	recursive_delete_directory($dirpath);

	chdir($back_to);
}

/** support function for the functions that create/read from dump files. 
 *  Parameter: a .tar.gz to turn into a directory, but does not delete the archive. */
function cqpweb_dump_untargzip($path)
{
	$back_to = getcwd();
	
	chdir(dirname($path));
	
	$file = basename($path, '.tar.gz');
	
	exec("gzip -d $file.tar.gz");
	exec("tar -xf $file.tar");
	/* put the dump file back as it was */
	exec("gzip $file.tar");
	
	chdir($back_to);
}

/**
 * A variant dump function which only dumps user-saved data.
 * 
 * This currently includes: 
 * (1) cached queries which are saved; 
 * (2) categorised queries and their database.
 * 
 * (possible additions: subcorpora, user CQP macros...)
 */
function cqpweb_dump_userdata($dump_file_path)
{
	global $cqpweb_tempdir;
	
	php_execute_time_unlimit();
	
	$dir = dumpable_dir_basename($dump_file_path);
	
	if (is_dir($dir))				recursive_delete_directory($dir);
	if (is_file("$dir.tar"))		unlink("$dir.tar");
	if (is_file("$dir.tar.gz"))		unlink("$dir.tar.gz");
	
	mkdir($dir);
	
	/* note that the layout is different to a snapshot - we do not have 
	 * subdirectories or sub-contained tar.gz files */
	
	/* copy saved queries (status: saved or saved-for-cat) */
	$saved_queries_dest = fopen("$dir/__SAVED_QUERIES_LINES", 'w');
	$result = do_mysql_query("select * from saved_queries where saved > 0");
	while (false !== ($row = mysql_fetch_row($result)))
	{
		/* copy any matching files to the location */
		foreach (glob("/$cqpweb_tempdir/*:{$row[0]}") as $f)
			if (is_file($f))
				copy($f, "$dir/".basename($f));
				
		/* write this row of the saved_queries to file */
		foreach($row as &$v)
			if (is_null($v))
				$v = '\N';
				
		fwrite($saved_queries_dest, implode("\t", $row) . "\n");
	}
	fclose($saved_queries_dest);
	
	/* write the saved_catqueries table, plus each db named in it, to file */
	
	$tables_to_save = array('saved_catqueries');
	$result = do_mysql_query("select dbname from saved_catqueries");
	while (false !== ($row = mysql_fetch_row($result)))
		$tables_to_save[] = $row[0];

	$create_tables_dest = fopen("$dir/__CREATE_TABLES_STATEMENTS", "w");
	foreach ($tables_to_save as $table)
	{
		$dest = fopen("$dir/$table", "w");
		$result = do_mysql_query("select * from $table");
		while (false !== ($r = mysql_fetch_row($result)))
		{
			foreach($r as &$v)
				if (is_null($v))
					$v = '\N';
			fwrite($dest, implode("\t", $r) . "\n");
		}
		$result = do_mysql_query("show create table $table");
				list($junk, $create) = mysql_fetch_row(do_mysql_query("show create table $table"));
		fwrite($create_tables_dest, $create ."\n\n~~~###~~~\n\n");
		
		fclose($dest);
	}
	fclose($create_tables_dest);

	cqpweb_dump_targzip($dir);

	php_execute_time_relimit();
}

/**
 * Undump a userdata snapshot.
 * 
 * TODO not tested yet
 */
function cqpweb_undump_userdata($dump_file_path)
{
	global $cqpweb_tempdir;

	php_execute_time_unlimit();

	$dir = dumpable_dir_basename($dump_file_path);
	
	cqpweb_dump_untargzip("$dir.tar.gz");
	
	/* copy cache files back where they came from */
	foreach (glob("/$dir/*:*") as $f)
		if (is_file($f))
			copy($f, "$cqpweb_tempdir/" . basename($f));

	/* load back the mysql tables */
	foreach (explode('~~~###~~~', file_get_contents("$dir/__CREATE_TABLES_STATEMENTS")) as $create_statement)
	{
		if (preg_match('/CREATE TABLE `([^`]*)`/', $create_statement, $m) < 1)
			continue;
		if ($m[1] == 'saved_catqueries')
			continue;
			/* see below for what we do with saved_catqueries */

		do_mysql_query("drop table if exists {$m[1]}");
		do_mysql_query($create_statement);
		do_mysql_infile_query($m[1], $m[1]);
	}
	
	/* now, we need to load the data back into saved_queries  --
	 * but we need to check for the existence of like-named save-queries and delete them first. 
	 * Same deal for saved_catqueries. */
	foreach (file("$dir/__SAVED_QUERIES_LINES") as $line)
	{
		list($qname, $junk, $corpus) = explode("\t", $line);
		do_mysql_query("delete from saved_queries where query_name = '$qname' and corpus = '$corpus'");
	}
	//do_mysql_query("$mysql_LOAD_DATA_INFILE_command '$dir/__SAVED_QUERIES_LINES' into table saved_queries");
	do_mysql_infile_query('saved_queries', "$dir/__SAVED_QUERIES_LINES");

	foreach (file("$dir/saved_catqueries") as $line)
	{
		list($qname, $junk, $corpus) = explode("\t", $line);
		do_mysql_query("delete from saved_catqueries where catquery_name = '$qname' and corpus = '$corpus'");
	}
	//do_mysql_query("$mysql_LOAD_DATA_INFILE_command '$dir/saved_catqueries' into table saved_catqueries");
	do_mysql_infile_query('saved_catqueries', "$dir/saved_catqueries");

	recursive_delete_directory($dir);
	
	php_execute_time_relimit();

}

/**
 * Dump an entire snapshot of the CQPweb system.
 */
function cqpweb_dump_snapshot($dump_file_path)
{
	global $cqpweb_tempdir;
	
	php_execute_time_unlimit();
	
	$dir = dumpable_dir_basename($dump_file_path);
	
	if (is_dir($dir))				recursive_delete_directory($dir);
	if (is_file("$dir.tar"))		unlink("$dir.tar");
	if (is_file("$dir.tar.gz"))		unlink("$dir.tar.gz");
	
	mkdir($dir);
	
	cqpweb_mysql_dump_data("$dir/__DUMPED_DATABASE.tar.gz");
	
	mkdir("$dir/cache");
	
	/* copy the cache */
	foreach(scandir("/$cqpweb_tempdir") as $f)
		if (is_file("/$cqpweb_tempdir/$f"))
			copy("/$cqpweb_tempdir/$f", "$dir/cache/$f");
	
	/* copy corpus setting files */
	foreach(list_corpora() as $c)
		copy("../$c/settings.inc.php", "$dir/$c.settings.inc.php");
		
	/* NOTE: we do not attempt to dump out CWB registry or data files. */
			
	cqpweb_dump_targzip($dir);
	
	php_execute_time_relimit();
}

function cqpweb_undump_snapshot($dump_file_path)
{
	global $cqpweb_tempdir;

	php_execute_time_unlimit();

	$dir = dumpable_dir_basename($dump_file_path);
	
	cqpweb_dump_untargzip("$dir.tar.gz");
	
	/* copy cache files back where they came from */
	foreach(scandir("$dir/cache") as $f)
		if (is_file("$dir/cache/$f"))
			copy("$dir/cache/$f", "/$cqpweb_tempdir/$f");
	
	/* corpus settings: create the directory if necessary */
	foreach (scandir("$dir") as $sf)
	{
		if (!is_file($sf))
			continue;
		list($corpus) = explode('.', $sf);
		if (! is_dir("../$corpus"))
			mkdir("../$corpus");
		copy("$dir/$sf", "../$corpus/settings.inc.php");
		/* in case these were damaged or not yet created... */
		install_create_corpus_script_files("../$corpus");
	}
	
	/* call the MySQL undump function */
	cqpweb_mysql_undump_data("$dir/__DUMPED_DATABASE.tar.gz");

	recursive_delete_directory($dir);
	
	php_execute_time_relimit();
}


/**
 * Does a data dump of the current status of the mysql database.
 * 
 * The database is written to a collection of text files that are compressed
 * into a .tar.gz file (whose location should be specified as either
 * an absolute path or a path relative to the working directory of the script
 * that calls this function.)
 * 
 * Note that the path, minus the .tar.gz extension, will be created as an
 * intermediate directory during the dump process.
 * 
 * The form of the .tar is as follows: one text file per table in the database,
 * plus one text file containing create table statements as PHP code.
 * 
 * If the $dump_file_path argument does not end in ".tar.gz", then that 
 * extension will be added.
 * 
 * TODO not tested yet
 */
function cqpweb_mysql_dump_data($dump_file_path)
{
	$dir = dumpable_dir_basename($dump_file_path);
		
	if (is_dir($dir))				recursive_delete_directory($dir);
	if (is_file("$dir.tar"))		unlink("$dir.tar");
	if (is_file("$dir.tar.gz"))		unlink("$dir.tar.gz");
			
	mkdir($dir);
		
	$create_tables_dest = fopen("$dir/__CREATE_TABLES_STATEMENTS", "w");
	
	$list_tables_result = do_mysql_query("show tables");
	while (false !== ($r = mysql_fetch_row($list_tables_result)))
	{
		list($junk, $create) = mysql_fetch_row(do_mysql_query("show create table {$r[0]}"));
		fwrite($create_tables_dest, $create ."\n\n~~~###~~~\n\n");
		
		$dest = fopen("$dir/{$r[0]}", "w");
		$result = do_mysql_query("select * from {$r[0]}");
		while (false !== ($line_r = mysql_fetch_row($result)))
		{
			foreach($line_r as &$v)
				if (is_null($v))
					$v = '\N';
			fwrite($dest, implode("\t", $line_r) . "\n");
		}
		fclose($dest);
	}
	
	fclose($create_tables_dest);
	
	cqpweb_dump_targzip($dir);
}

/**
 * Undoes the dumping of the mysql directory.
 * 
 * Note that this overwrites any tables of the same name that are present.
 * 
 * TODO NOT TESTED YET.
 * 
 * If the $dump_file_path argument does not end in ".tar.gz", then that 
 * extension will be added.
 */
function cqpweb_mysql_undump_data($dump_file_path)
{	
	$dir = dumpable_dir_basename($dump_file_path);
	
	cqpweb_dump_untargzip("$dir.tar.gz");
	
	foreach (explode('~~~###~~~', file_get_contents("$dir/__CREATE_TABLES_STATEMENTS")) as $create_statement)
	{
		if (preg_match('/CREATE TABLE `([^`]*)`/', $create_statement, $m) < 1)
			continue;
		do_mysql_query("drop table if exists {$m[1]}");
		do_mysql_query($create_statement);
		//do_mysql_query("$mysql_LOAD_DATA_INFILE_command '{$m[1]}' into table {$m[1]}");
		do_mysql_infile_query($m[1], $m[1]);
	}
	
	recursive_delete_directory($dir);
}


/**
 * Function to re-set the mysql setup to its initialised form.
 */
function cqpweb_mysql_total_reset()
{
	foreach (array( 'db_', 
					'freq_corpus_', 
					'freq_sc_', 
					'temporary_freq_', 
					'text_metadata_for_',
					'__freqmake_temptable'
					)
			as $prefix)
	{
		$result = do_mysql_query("show tables like \"$prefix%\"");
		while ( ($r = mysql_fetch_row($result)) !== false)
			do_mysql_query("drop table if exists $r");
	}
	
	$array_of_create_statements = cqpweb_mysql_recreate_tables();

	foreach ($array_of_create_statements as $name => &$statement)
	{
		do_mysql_query("drop table if exists $name");
		do_mysql_query($statement);
	}
}

/**
 * Gives you the create table statements for setup as an array.
 */
function cqpweb_mysql_recreate_tables()
{
	$create_statements = array();
	
	$create_statements['query_history'] =
		"create table query_history (
			`instance_name` varchar(31) default NULL,
			`user` varchar(20) NOT NULL default '',
			`corpus` varchar(20) NOT NULL default '',
			`cqp_query` text NOT NULL,
			`restrictions` text character set utf8 collate utf8_bin default NULL,
			`subcorpus` varchar(200) default NULL,
			`date_of_query` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`hits` int(11) default NULL,
			`simple_query` text,
			`query_mode` varchar(12) default NULL,
			KEY `user` (`user`),
			KEY `corpus` (`corpus`),
			KEY `cqp_query` (`cqp_query`(256))
		) CHARACTER SET utf8";

	
	$create_statements['saved_queries'] =
		"CREATE TABLE `saved_queries` (
			`query_name` varchar(150),
			`user` varchar(20) default NULL,
			`corpus` varchar(20) NOT NULL,
			`query_mode` varchar(12) default NULL,
			`simple_query` text default NULL,
			`cqp_query` text NOT NULL,
			`restrictions` text default NULL,
			`subcorpus` varchar(200) default NULL,
			`postprocess` text default NULL,
			`hits_left` text default NULL,
			`time_of_query` int(11) default NULL,
			`hits` int(11) default NULL,
			`hit_texts` int(11) default NULL,
			`file_size` int(10) unsigned default NULL,
			`saved` tinyint(1) default 0,
			`save_name` varchar(50) default NULL,
			`date_of_saving` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			KEY `query_name` (`query_name`),
			KEY `user` (`user`),
			KEY `corpus` (`corpus`),
			FULLTEXT KEY `restrictions` (`restrictions`),
			KEY `subcorpus` (`subcorpus`),
			FULLTEXT KEY `postprocess` (`postprocess`(100)),
			KEY `time_of_query` (`time_of_query`),
			FULLTEXT KEY `cqp_query` (`cqp_query`)
	) CHARACTER SET utf8 COLLATE utf8_bin";

	$create_statements['saved_catqueries'] =
		"CREATE TABLE `saved_catqueries` (
			`catquery_name` varchar(150) NOT NULL,
			`user` varchar(20) default NULL,
			`corpus` varchar(20) NOT NULL,
			`dbname` varchar(150) NOT NULL,
			`category_list` TEXT,
			KEY `catquery_name` (`catquery_name`),
			KEY `user` (`user`),
			KEY `corpus` (`corpus`)
	) CHARACTER SET utf8 COLLATE utf8_bin";


	
	$create_statements['user_settings'] =
		"CREATE TABLE `user_settings` (
			`username` varchar(20) NOT NULL default '',
			`password` varchar(20) default NULL,
			`realname` varchar(50) default NULL,
			`email` varchar(50) default NULL,
			`conc_kwicview` tinyint(1),
			`conc_corpus_order` tinyint(1),
			`cqp_syntax` tinyint(1),
			`context_with_tags` tinyint(1),
			`use_tooltips` tinyint(1),
			`thin_default_reproducible` tinyint(1),
			`coll_statistic` tinyint,
			`coll_freqtogether` int,
			`coll_freqalone` int,
			`coll_from` tinyint,
			`coll_to` tinyint,
			`max_dbsize` int(10) unsigned default NULL,
			`linefeed` char(2) default NULL,
			primary key(`username`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['text_metadata_fields'] =
		"CREATE TABLE `text_metadata_fields` (
			`corpus` varchar(20) NOT NULL,
			`handle` varchar(20) NOT NULL,
			`description` varchar(255) default NULL,
			`is_classification` tinyint(1) default 0,
			primary key (`corpus`, `handle`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['text_metadata_values'] =
		"CREATE TABLE `text_metadata_values` (
			`corpus` varchar(20) NOT NULL,
			`field_handle` varchar(20) NOT NULL,
			`handle` varchar(20) NOT NULL,
			`description` varchar(255) default NULL,
			`category_num_words` int unsigned default NULL,
			`category_num_files` int unsigned default NULL,
			primary key(`corpus`, `field_handle`, `handle`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['annotation_metadata'] =
		"CREATE TABLE `annotation_metadata` (
			`corpus` varchar(20) NOT NULL,
			`handle` varchar(20) NOT NULL,
			`description` varchar(255) default NULL,
			`tagset` varchar(255) default NULL,
			`external_url` varchar(255) default NULL,
			primary key (`corpus`, `handle`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['corpus_metadata_fixed'] =
		"CREATE TABLE `corpus_metadata_fixed` (
			`corpus` varchar(20) NOT NULL,
			`visible` tinyint(1) default 1,
			`primary_classification_field` varchar(20) default NULL,
			`primary_annotation` varchar(20) default NULL,
			`secondary_annotation` varchar(20) default NULL,
			`tertiary_annotation` varchar(20) default NULL,
			`tertiary_annotation_tablehandle` varchar(40) default NULL,
			`combo_annotation` varchar(20) default NULL,
			`external_url` varchar(255) default NULL,
			`public_freqlist_desc` varchar(150) default NULL,
			`corpus_cat` int NOT NULL DEFAULT 1,
			`cwb_external` tinyint(1) NOT NULL default 0,
			PRIMARY KEY (corpus)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['corpus_metadata_variable'] =
		"CREATE TABLE `corpus_metadata_variable` (
			`corpus` varchar(20) NOT NULL,
			`attribute` text NOT NULL,
			`value` text default NULL,
			key(`corpus`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['saved_dbs'] =
		"CREATE TABLE `saved_dbs` (
			`dbname` varchar(200) NOT NULL,
			`user` varchar(30) default NULL,
			`create_time` int(11) default NULL,
			`cqp_query` text character set utf8 collate utf8_bin NOT NULL,
			`restrictions` text character set utf8 collate utf8_bin default NULL,
			`subcorpus` varchar(200) default NULL,
			`postprocess` text default NULL,
			`corpus` varchar (20) NOT NULL,
			`db_type` varchar(15) default NULL,
			`colloc_atts` varchar(200) default '',
			`colloc_range` int default '0',
			`sort_position` int default '0',
			`db_size` bigint UNSIGNED default NULL,
			`saved` tinyint(1) NOT NULL default 0,
			key (`dbname`),
			key (`user`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['saved_subcorpora'] =
		"CREATE TABLE `saved_subcorpora` (
			`subcorpus_name` varchar(200) NOT NULL,
			`corpus` varchar(20) NOT NULL,
			`user` varchar(30) default NULL,
			`restrictions` text character set utf8 collate utf8_bin,
			`text_list` text character set utf8 collate utf8_bin,
			`numfiles` mediumint(8) unsigned default NULL,
			`numwords` bigint(21) unsigned default NULL,
			key(`corpus`, `user`),
			key(`text_list`(256))
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['mysql_processes'] =
		"CREATE TABLE `mysql_processes` (
			`dbname` varchar(200) NOT NULL,
			`begin_time` int(11) default NULL,
			`process_type` varchar(15) default NULL,
			`process_id` varchar(15) default NULL
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['saved_freqtables'] =
		"CREATE TABLE `saved_freqtables` (
			`freqtable_name` varchar(150),
			`corpus` varchar(20) NOT NULL,
			`user` varchar(30) default NULL,
			`restrictions` text character set utf8 collate utf8_bin,
			`subcorpus` varchar(200) NOT NULL,
			`create_time` int(11) default NULL,
			`ft_size` bigint UNSIGNED default NULL,
			`public` tinyint(1) default 0,
			KEY `subcorpus` (`subcorpus`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['system_messages'] =
		"CREATE TABLE `system_messages` (
			`message_id` varchar(150),
			`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`header` varchar(150),
			`content` text character set utf8 collate utf8_bin,
			`fromto` varchar(150),
			key (`message_id`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	
	
	$create_statements['system_longvalues'] =
		"CREATE TABLE `system_longvalues` (
			`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`id` varchar(40),
			`value` text,
			primary key(`id`)
	) CHARACTER SET utf8 COLLATE utf8_bin";
	
	
	$create_statements['annotation_mapping_tables'] =
		"CREATE TABLE `annotation_mapping_tables` (
			`id` varchar(40),
			`name` varchar(255), 
			`mappings` text character set utf8,
			key(`id`)
	) CHARACTER SET utf8 COLLATE utf8_bin";
	
	
	$create_statements['user_macros'] =
		"CREATE TABLE `user_macros` (
			`username` varchar(20) NOT NULL default '',
			`macro_name` varchar(50) NOT NULL,
			`macro_num_args` int,
			`macro_body` text,
			key(`username`)
	) CHARACTER SET utf8 COLLATE utf8_bin";


	$create_statements['xml_visualisations'] =
		"CREATE TABLE `xml_visualisations` (
			`corpus` varchar(20) NOT NULL,
			`element` varchar(50) NOT NULL,
			`cond_attribute` varchar(50) NOT NULL default '',
			`cond_regex` varchar(100) NOT NULL default '',
			`xml_attributes` varchar(100) NOT NULL default '',
			`text_metadata` varchar(255) NOT NULL default '',
			`in_concordance` tinyint(1) NOT NULL default 1,
			`in_context` tinyint(1) NOT NULL default 1,
			`bb_code` text,
			`html_code` text,
			primary key(`corpus`, `element`, `cond_attribute`, `cond_regex`)
	) CHARACTER SET utf8 COLLATE utf8_bin";
	/* note that, becuase the attribute/regex condition must be part of the primary key, the regex is limited to
	 * 100 UTF8 characters (keys cannot exceed 1000 bytes = 333 utf8 chars) */ 
	
	
	$create_statements['corpus_categories'] =
		"CREATE TABLE `corpus_categories` (
			`idno` int NOT NULL AUTO_INCREMENT,
			`label` varchar(255) DEFAULT '',
			`sort_n` int NOT NULL DEFAULT 0,
			PRIMARY KEY (`idno`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	
	
	return $create_statements;
}














function cqpweb_import_css_file($filename)
{
	global $cqpweb_uploaddir;
	
	$orig = "/$cqpweb_uploaddir/$filename";
	$new = "../css/$filename";
	
	if (is_file($orig))
	{
		if (is_file($new))
			exiterror_general("A CSS file with that name already exists. File not copied.");
		else
			copy($orig, $new);
	}
}



/**
 * Installs the default "skins" ie CSS colour schemes.
 * 
 * Note, doesn't actually specify that one of these should be used anywhere
 * -- just makes them available.
 */
function cqpweb_regenerate_css_files()
{
	$yellow_pairs = array(
		'#ffeeaa' =>	'#ddddff',		/* error */
		'#bbbbff' =>	'#ffbb77',		/* dark */
		'#ddddff' =>	'#ffeeaa'		/* light */
		);
		
	$green_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#66cc99',		/* dark */
		'#ddddff' =>	'#ccffcc'		/* light */
		);
		
	$red_pairs = array(
		'#ffeeaa' =>	'#ddddff',		/* error */
		'#bbbbff' =>	'#ff8899',		/* dark */
		'#ddddff' =>	'#ffcfdd'		/* light */
		);
	
	$brown_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#cd663f',		/* dark */
		'#ddddff' =>	'#eeaa77'		/* light */
		);
	
	$purple_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#be71ec',		/* dark */
		'#ddddff' =>	'#dfbaf5'		/* light */
		);
	
	$darkblue_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#0066aa',		/* dark */
		'#ddddff' =>	'#33aadd'		/* light */
		);
	$lime_pairs = array(
		'#ffeeaa' =>	'#00ffff',		/* error */
		'#bbbbff' =>	'#B9FF6F',		/* dark */
		'#ddddff' =>	'#ECFF6F'		/* light */
		);
	$aqua_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#00ffff',		/* dark */
		'#ddddff' =>	'#b0ffff'		/* light */
		);
	$neon_pairs = array(
		'#ffeeaa' =>	'#00ff00',		/* error */
		'#bbbbff' =>	'#ff00ff',		/* dark */
		'#ddddff' =>	'#ffa6ff'		/* light */
		);
	$dusk_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#8000ff',		/* dark */
		'#ddddff' =>	'#d1a4ff'		/* light */
		);
	$gold_pairs = array(
		'#ffeeaa' =>	'#80ffff',		/* error */
		'#bbbbff' =>	'#808000',		/* dark */
		'#ddddff' =>	'#c1c66c'		/* light */
		);
	/* black will have to wait since inserting white text only where necessary is complex
	$black_pairs = array(
		'#ffeeaa' =>	'#ddddff',		/* error * /
		'#bbbbff' =>	'#ff8899',		/* dark * /
		'#ddddff' =>	'#ffcfdd'		/* light * /
		);
	*/
	
	
	$css_file = cqpweb_css_file();
	
	file_put_contents('../css/CQPweb.css', $css_file);
	file_put_contents('../css/CQPweb-yellow.css', 	strtr($css_file, $yellow_pairs));
	file_put_contents('../css/CQPweb-green.css', 	strtr($css_file, $green_pairs));
	file_put_contents('../css/CQPweb-red.css', 		strtr($css_file, $red_pairs));
	file_put_contents('../css/CQPweb-brown.css', 	strtr($css_file, $brown_pairs));
	file_put_contents('../css/CQPweb-purple.css', 	strtr($css_file, $purple_pairs));
	file_put_contents('../css/CQPweb-darkblue.css', strtr($css_file, $darkblue_pairs));
	file_put_contents('../css/CQPweb-lime.css', 	strtr($css_file, $lime_pairs));
	file_put_contents('../css/CQPweb-aqua.css', 	strtr($css_file, $aqua_pairs));
	file_put_contents('../css/CQPweb-neon.css', 	strtr($css_file, $neon_pairs));
	file_put_contents('../css/CQPweb-dusk.css', 	strtr($css_file, $dusk_pairs));
	file_put_contents('../css/CQPweb-gold.css', 	strtr($css_file, $gold_pairs));


}






/**
 * Returns the code of the default CSS file for built-in colour schemes.
 */
function cqpweb_css_file ()
{
	return <<<HERE


/* top page heading */

h1 {
	font-family: Verdana;
	text-align: center;
}



/* different paragraph styles */

p.errormessage {
	font-family: courier new;
	font-size: large
}

p.instruction {
	font-family: verdana;
	font-size: 10pt
}

p.helpnote {
	font-family: verdana;
	font-size: 10pt
}

p.bigbold {
	font-family: verdana;
	font-size: medium;
	font-weight: bold;
}

p.spacer {
	font-size: small;
	padding: 0pt;
	line-height: 0%;
	font-size: 10%
}

span.hit {
	color: red;
	font-weight: bold
}

span.contexthighlight {
	font-weight: bold
}

span.concord-time-report {
	color: gray;
	font-size: 10pt;
	font-weight: normal
}


/* table layout */


table.controlbox {
	border: large outset
}

td.controlbox {
	font-family: Verdana;
	padding-top: 5px;
	padding-bottom: 5px;
	padding-left: 10px;
	padding-right: 10px;
	border: medium outset
}




table.concordtable {
	border-style: solid;
	border-color: #ffffff; 
	border-width: 5px
}

th.concordtable {
	padding-left: 3px;
	padding-right: 3px;
	padding-top: 7px;
	padding-bottom: 7px;
	background-color: #bbbbff;
	font-family: verdana;
	font-weight: bold;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}

td.concordgeneral {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ddddff;	
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}	


td.concorderror {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ffeeaa;	
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}


td.concordgrey {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #d5d5d5;	
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
}	


td.before {
	padding: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 2px;
	border-right-width: 0px;
	text-align: right
}

td.after {
	padding: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 0px;
	border-right-width: 2px;
	text-align: left
}

td.node {
	padding: 3px;
	background-color: #f0f0f0;	
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 0px;
	border-right-width: 0px;
	text-align: center
}

td.lineview {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}	

td.text_id {
	padding: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
	text-align: center
}

td.end_bar {
	padding: 3px;
	background-color: #d5d5d5;	
	font-family: verdana;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
	text-align: center
}


td.basicbox {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 10px;
	padding-bottom: 10px;
	background-color: #ddddff;	
	font-family: verdana;
	font-size: 10pt;
}


td.cqpweb_copynote {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ffffff;
	font-family: verdana;
	font-size: 8pt;
	color: gray;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}


/* different types of link */
/* first, for left-navigation in the main query screen */
a.menuItem:link {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}
a.menuItem:visited {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}
a.menuItem:hover {
	white-space: nowrap;
	font-family: verdana;
	color: red;
	font-size: 10pt;
	text-decoration: underline;
}

/* next, for the currently selected menu item 
 * will not usually have an href 
 * ergo, no visited/hover */
a.menuCurrentItem {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}


/* next, for menu bar header text item 
 * will not usually have an href 
 * ergo, no visited/hover 
a.menuHeaderItem {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-weight: bold;
	font-size: 10pt;
	text-decoration: none;

}*/

/* here, for footer link to help */
a.cqpweb_copynote_link:link {
	color: gray;
	text-decoration: none;
}
a.cqpweb_copynote_link:visited {
	color: gray;
	text-decoration: none;
}
a.cqpweb_copynote_link:hover {
	color: red;
	text-decoration: underline;
}
HERE;


}



?>