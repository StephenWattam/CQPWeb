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


/* ============ */
/* begin script */
/* ============ */

/* the most recent database version: ie the last version whose release involved a DB change */
$last_changed_version = '3.1.0';

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);

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
	
	$func = 'upgrade_' . str_replace('.','_',$oldv);
	
	
}

function upgrade_3_0_16()
{
	$sql = array(
		/* minor tweaks made in the code in late 3.0.16 */
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
		/* some new info fields for the corpus table... for use later. */
		'alter table corpus_info add column `is_user_corpus` tinyint(1) NOT NULL default 0',
		'alter table corpus_info add column `date_of_indexing` timestamp NOT NULL default CURRENT_TIMESTAMP'
	);
	foreach ($sql as $q)
		do_mysql_query($q);
	
	$sql = array(
		"insert into system_info (setting_name, value) VALUES ('db_version',  '3.0.16')",	# bit pointless, but establishes the last-SQL template
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
