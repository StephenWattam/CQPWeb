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
 * This script updates the structure of the database to match the version of the code.
 * 
 * It is always safe to run this function because, if the db structure is up to date, it won't do anything.
 * 
 * Note that, up to and including 3.0.16, it was assumed that DB changes would be done manually. 
 * 
 * So, all manual changes up to 3.0.16 MUST be applied before running this script.
 */


require('../lib/environment.inc.php');

/* include function library files */
require('../lib/library.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/exiterror.inc.php');

require ('../bin/cli-lib.php');




/* VARS THAT NEED UPDATING EVERY TIME A NEW VERSION IS PILED ON */

		/* the most recent database version: ie the last version whose release involved a DB change */
		$last_changed_version = '3.1.0';
		
		/* 
		 * versions where there is no change. Array of old_version => version that followed. 
		 * E.g. if there were no changes between 3.1.0 and 3.1.1, this array should contain
		 * '3.1.0' => '3.1.1', so the function can then reiterate and look for changes between
		 * 3.1.2 and whatever follows it.
		 */
		$versions_where_there_was_no_change = array();

/* END COMPULSORY UPDATE VARS */



/* ============ * 
 * begin script * 
 * ============ */


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP , RUN_LOCATION_CLI);

/* begin by checking for a really old database version ... */

$greater_than_3_0_16 = false;

$result = do_mysql_query('show tables');

while (false !== ($r = mysql_fetch_row($result)))
{
	if ($r[0] == 'system_info')
	{
		$greater_than_3_0_16 = true;
		break;
	}
}

if (!$greater_than_3_0_16)
{
	echo "Database version is now at < 3.1.0. Database will now be upgraded to 3.1.0...\n";
	upgrade_db_version_from('3.0.16');
}

while (($version = get_db_version()) != $last_changed_version)
{
	echo "Current DB version is $version; target version is $last_changed_version .  About to upgrade....\n";
	upgrade_db_version_from($version);
}

echo "CQPweb database is now at the most-recently-changed version ($last_changed_version). Upgrade complete!\n";	

cqpweb_shutdown_environment();

exit;


/* --------------------------------------------------------------------------------------------------------- */


function upgrade_db_version_from($oldv)
{	
	global $versions_where_there_was_no_change;
	
	if (array_key_exists($oldv,$versions_where_there_was_no_change))
		do_mysql_query("update system_info set value = '{$versions_where_there_was_no_change[$oldv]}' where setting_name = 'db_version'");
	else
	{
		$func = 'upgrade_' . str_replace('.','_',$oldv);
		$func();
	}
}


/* this one is the huge one ....... */
function upgrade_3_0_16()
{
	$sql = array(
		'alter table user_settings    alter column username set default ""',
		'alter table saved_catqueries alter column corpus set default ""',
		'alter table saved_catqueries alter column dbname set default ""',
		'alter table saved_dbs drop key dbname',
		'alter table saved_dbs add primary key `dbname` (`dbname`)',
		'alter table saved_dbs alter column corpus set default ""',
		'alter table saved_subcorpora alter column subcorpus_name set default ""',
		'alter table saved_subcorpora alter column corpus set default ""',
		'alter table mysql_processes add primary key (`dbname`)',
		'alter table saved_freqtables add primary key (`freqtable_name`)',
		'alter table saved_freqtables alter column subcorpus set default ""',
		'alter table saved_freqtables alter column corpus set default ""',
		'alter table system_messages drop key `message_id`',
		'alter table system_messages add primary key (`message_id`)',
		'alter table system_messages alter column header set default ""',
		'alter table system_messages alter column fromto set default NULL',
		'alter table user_macros alter column username set default ""',
		'alter table user_macros alter column macro_name set default ""',
		'alter table user_macros add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table xml_visualisations alter column corpus set default ""',
		'alter table xml_visualisations alter column element set default ""',
		'alter table xml_visualisations drop primary key',
		'alter table xml_visualisations add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table xml_visualisations add unique key(`corpus`, `element`, `cond_attribute`, `cond_regex`)',
		/* The GREAT RENAMING  and rearrangement of main corpus/user tables */
		'rename table mysql_processes to system_processes',
		'rename table user_settings to user_info',
		'rename table corpus_metadata_fixed to corpus_info',
		'alter table user_info drop primary key',
		'alter table user_info add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table user_info add unique key (`username`)',		
		'alter table corpus_info drop primary key',
		'alter table corpus_info add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table corpus_info add unique key (`corpus`)',
		'alter table corpus_categories drop column idno',
		'alter table corpus_categories add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table annotation_mapping_tables drop key `id`',
		'update annotation_mapping_tables set id="oxford_simple_tags" where id="oxford_simplified_tags"',
		'update annotation_mapping_tables set id="rus_mystem_classes" where id="russian_mystem_wordclasses"',
		'update annotation_mapping_tables set id="nepali_simple_tags" where id="simplified_nepali_tags"',
		'update corpus_info set tertiary_annotation_tablehandle="oxford_simple_tags" where tertiary_annotation_tablehandle="oxford_simplified_tags"',
		'update corpus_info set tertiary_annotation_tablehandle="rus_mystem_classes" where tertiary_annotation_tablehandle="russian_mystem_wordclasses"',
		'update corpus_info set tertiary_annotation_tablehandle="nepali_simple_tags" where tertiary_annotation_tablehandle="simplified_nepali_tags"'
	);
	foreach ($sql as $q)
		do_mysql_query($q);
	
	$result = do_mysql_query("select id from annotation_mapping_tables where char_length(id) > 20");
	while (false !== ($r = mysql_fetch_row($result)))
	{
		list($oldhandle) = $r;
		echo "WARNING. Annotation mapping table handle '$oldhandle' is too long for the new DB version. Please enter one of 20 characters or less.\n";
		for($continue = true; $continue; )
		{
			$newhandle = get_variable_word('a new handle for this table');
			$continue = false;
			
			if (strlen($newhandle) > 20)
			{
				echo "Sorry, that name is too long. 20 characters or less please!\n";
				$continue = true;
			}
			$result = do_mysql_query("select id from annotation_mapping_tables where id = $newhandle");
			if (0 < mysql_num_rows($result))
			{
				echo "Sorry, that handle already exists. Suggest another please!\n";
				$continue = true;
			}		
		}
		echo "thank you, replacing the handle now.........\n"; 
		
		do_mysql_query("update annotation_mapping_tables set id='$newhandle' where id='$oldhandle'");
		do_mysql_query("update corpus_info set tertiary_annotation_tablehandle='$newhandle' where tertiary_annotation_tablehandle='$oldhandle'");
	}

	/* ok, with that fixed, back to just running lists of commands.... */
	
	$sql = array(	
		'alter table annotation_mapping_tables CHANGE `id` `handle` varchar(20) NOT NULL, add primary key (`handle`)',
		/* some new info fields for the corpus table... for use later. */
		'alter table corpus_info add column `is_user_corpus` tinyint(1) NOT NULL default 0',
		'alter table corpus_info add column `date_of_indexing` timestamp NOT NULL default CURRENT_TIMESTAMP',
		/* let's get the system_info table */
		'CREATE TABLE `system_info` (
		   setting_name varchar(20) NOT NULL collate utf8_bin,
		   value varchar(255),
		   primary key(`setting_name`)
		 ) CHARACTER SET utf8 COLLATE utf8_general_ci',
		"insert into system_info (setting_name, value) VALUES ('db_version',  '3.0.16')",	# bit pointless, but establishes the last-SQL template
		/* now standardise length of usernames across all tables to THIRTY. */
		'alter table user_macros drop key username, CHANGE `username`  `user` varchar(30) NOT NULL, add unique key (`user`, `macro_name`)',
		'alter table user_macros CHANGE macro_name `macro_name` varchar(20) NOT NULL default ""',
		'alter table saved_queries modify `user` varchar(30) default NULL',
		'alter table saved_catqueries modify `user` varchar(30) default NULL',
		'alter table query_history modify `user` varchar(30) default NULL',
		'alter table user_info modify `username` varchar(30) NOT NULL',
		/* new tables for the new username system */
		'CREATE TABLE `user_groups` (
		   `id` int NOT NULL AUTO_INCREMENT,
		   `group_name` varchar(20) NOT NULL UNIQUE COLLATE utf8_bin,
		   `description` varchar(255) NOT NULL default "",
		   `autojoin_regex` text,
		   primary key (`id`)
		 ) CHARACTER SET utf8 COLLATE utf8_general_ci',
		 'CREATE TABLE `user_memberships` (`user_id` int NOT NULL,`group_id` int NOT NULL,`expiry_time` int UNSIGNED NOT NULL default 0) CHARACTER SET utf8 COLLATE utf8_general_ci',
		'insert into user_groups (group_name,description)values("superusers","Users with admin power")',
		'insert into user_groups (group_name,description)values("everybody","Group to which all users automatically belong")'
	);
	foreach ($sql as $q)
		do_mysql_query($q);
	
	echo "User groups are managed in the database now, not in the Apache htgroup file.\n";
	echo "If you want to re-enable your old groups, please use load-pre-3.1-groups.php.\n";
	echo "(Please acknowledge.)\n";
	get_enter_to_continue();
	
	/* back to DB changes again */
	
	$sql = array(
		'alter table user_info add column `passhash` char(61) AFTER email',
		'alter table user_info add column `acct_status` tinyint(1) NOT NULL default 0 AFTER passhash',
		/* all existing users count as validated. */
		'update user_info set acct_status = ' . USER_STATUS_ACTIVE,
		'alter table user_info add column `expiry_time` int UNSIGNED NOT NULL default 0 AFTER acct_status',
		'alter table user_info add column `last_seen_time` timestamp NOT NULL default CURRENT_TIMESTAMP AFTER expiry_time',
		'alter table user_info add column `password_expiry_time` int UNSIGNED NOT NULL default 0 AFTER expiry_time',
	);
	foreach ($sql as $q)
		do_mysql_query($q);
	
	/* CONVERT EXISTING PASSWORDS INTO PASSHASHES */
	echo "about to shift password system over to hashed-values in the database....\n";
	echo "all users whose accounts go back to the era before CQPweb kept passwords in the database will\n";
	echo "have their password changed to the string ``change_me'' (no quotes) and a near-future expiry date set on that password;\n";
	echo "depending on your code version, password expiry may or may not be implemented. (Please acknowledge).\n";
	get_enter_to_continue();
	
	$result = do_mysql_query("select username, password from user_info");
	$t = time() + (7 * 24 * 60 * 60);
	while (false === ($o = mysql_fetch_object($result)))
	{
		if (empty($o->password))
		{
			$extra =  ", password_expiry_time = $t";
			$o->password='change_me';
		}
		else
			$extra = '';
		
		$passhash = generate_new_hash_from_password($o->password);
		do_mysql_query("update user_info set passhash = '$passhash'$extra where username = '$username'");
	}
	echo "done transferring passwords to secure encrypted form. Old passwords will now be deleted.\nPlease acknowledge.\n";
	get_enter_to_continue();
	
	/* back to DB changes again */
	
	$sql = array(
		"alter table user_info drop column password",
		"alter table user_info add column `verify_key` varchar(32) default NULL AFTER acct_status",
		"CREATE TABLE `user_cookie_tokens` (
			`token` char(33) NOT NULL default '__token' UNIQUE,
			`user_id` int NOT NULL,
			`expiry`  int UNSIGNED NOT NULL default 0
			) CHARACTER SET utf8 COLLATE utf8_bin",
		"alter table user_info modify column `email` varchar(255) default NULL",
		"alter table user_info modify column `realname` varchar(255) default NULL",
		"alter table user_info add column `affiliation` varchar(255) default NULL after `email`",
		"alter table user_info add column `country` char(2) default '00' after `affiliation`",
		"CREATE TABLE `user_privilege_info` (
			`id` int NOT NULL AUTO_INCREMENT,
			`description` varchar(255) default '',
			`type` tinyint(1) unsigned default NULL,
			`scope` text,
			primary key(`id`)
			) CHARACTER SET utf8 COLLATE utf8_general_ci",
		"CREATE TABLE `user_grants_to_groups` 
			(`group_id` int NOT NULL,`privilege_id` int NOT NULL,`expiry_time` int UNSIGNED NOT NULL default 0) 
			CHARACTER SET utf8 COLLATE utf8_general_ci",
		"CREATE TABLE `user_grants_to_users` 
			(`user_id` int NOT NULL,`privilege_id` int NOT NULL,`expiry_time` int UNSIGNED NOT NULL default 0) 
			CHARACTER SET utf8 COLLATE utf8_general_ci",




		"update system_info set value = '3.1.0' where setting_name = 'db_version'"
	);
	foreach ($sql as $q)
		do_mysql_query($q);
}

function get_db_version()
{
	list($version) = mysql_fetch_row(do_mysql_query('select value from system_info where setting_name = "db_version"'));
	return $version;
}




?>
