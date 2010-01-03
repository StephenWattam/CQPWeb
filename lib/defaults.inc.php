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



/* because this might be called from the root directory rather than a corpus */
if (file_exists('lib/config.inc.php'))
	require_once('lib/config.inc.php');
else
	require_once('../lib/config.inc.php');


/* ------------------------ */
/* GENERAL DEFAULT SETTINGS */
/* ------------------------ */

/* can be overridden by setting these variables in config.inc.php */

/* Does mysqld have file-write/read ability? If set to true, CQPweb uses LOAD DATA
 * INFILE and SELECT INTO OUTFILE. If set to false, file write/read into/out of
 * mysql tables is done via the client-server link.
 * 
 * Giving mysqld file access, so that CQPweb can directly exchange files in 
 * $cqpweb_tempdir with the MySQL server, may be considerably more efficient.
 * 
 * (BUT -- we've not tested this yet)
 * 
 * The default is false. 
 */
if (!isset($mysql_has_file_access))
	$mysql_has_file_access = false;

/*
 * -- If mysqld has file access,  it is mysqld (not the php-mysql-client) which
 * will do the opening of the file.
 * -- But if mysqld does not have file access, then we should load all infiles locally.
 */
if ($mysql_has_file_access)
	$mysql_LOAD_DATA_INFILE_command = 'LOAD DATA INFILE';
else
	$mysql_LOAD_DATA_INFILE_command = 'LOAD DATA LOCAL INFILE';


/* TEMPORARY DIRECTORIES
 * 
 * We previously had two different temporary directories
 * 
 * Now we have just one; but the code has not all been revised yet.
 */
if (!isset($cqpweb_tempdir))
{
	echo('CRITICAL ERROR: $cqpweb_tempdir has not been set');
	exit();
}
/* This temporary code preserves the old directory names as references to the new directory name */
$mysql_tempdir =& $cqpweb_tempdir;
$cqp_tempdir =& $cqpweb_tempdir;


/* the following stops calls to CQP::set_corpus causing an error in the "adm" scripts */
if (!isset($corpus_cqp_name))
	$corpus_cqp_name = ';';

if (!isset($utf8_set_required))
	$utf8_set_required = true;
	
/* the next defaults are for tweaks to the system -- not so much critical! */

if (!isset($use_corpus_categories_on_homepage))
	$use_corpus_categories_on_homepage = false;

if (!isset($css_path_for_homepage))
	$css_path_for_homepage = "../css/CQPweb.css";

if (!isset($css_path_for_adminpage))
	$css_path_for_adminpage = "../css/CQPweb-red.css";

if (!isset($create_password_function))
	$create_password_function = "password_insert_internal";

if (!isset($cqpweb_uses_apache))
	$cqpweb_uses_apache = true;

if (!isset($print_debug_messages))
	$print_debug_messages = false;


/* PER-CORPUS DEFAULT SETTINGS */
/* for if settings.inc.php don't specify them */

if (!isset($corpus_main_script_is_r2l))
	$corpus_main_script_is_r2l = false;

if (!isset($utf8_set_required))
	$utf8_set_required = false;

if (!isset($css_path))
	$css_path = "../css/CQPweb.css";

if (!isset($graph_img_path))
	$graph_img_path = "../css/img/blue.bmp";

if (!isset($dist_num_files_to_list))
	$dist_num_files_to_list = 100;

if (!isset($context_scope))
	$context_scope = 12;

if (!isset($default_per_page))
	$default_per_page = 50;

if (!isset($default_history_per_page))
	$default_history_per_page = 100;

if (!isset($default_extended_context))
	$default_extended_context = 100;

if (!isset($default_max_context))
	$default_max_context = 1100;


/* this allows settings.inc.php to override config.inc.php * /
if (isset($this_corpus_directory_override['reg_dir']))
	$cwb_registry = $this_corpus_directory_override['reg_dir'];
if (isset($this_corpus_directory_override['data_dir']))
	$cwb_datadir = $this_corpus_directory_override['data_dir'];
	
	1/12/09 : as of now we are no longer allowing directory overrides.
	*/

	
/* collocation defaults */
if (!isset($default_colloc_range))
	$default_colloc_range = 5;
	
if (!isset($default_calc_stat))
	$default_calc_stat = 6; 	/* {6 == log-likelihood} is default collocation statistic */

if (!isset($default_colloc_minfreq))
	$default_colloc_minfreq = 5;

if (!isset($default_collocations_per_page))
	$default_collocations_per_page = 50;
	
if (!isset($collocation_warning_cutoff))
	$collocation_warning_cutoff = 5000000; /* cutoff for going from a minor warning to a major warning */


/* collocation download default */
if (!isset($default_words_in_download_context))
	$default_words_in_download_context = 10;


/* version number of CQPweb */
define('CQPWEB_VERSION', '2.11');
	




/* SYSTEM DEFAULT SETTINGS */
/* for if config.inc.php don't specify them */

/* control the size of the history table */
if (!isset($history_maxentries))
	$history_maxentries = 5000;
if (!isset($history_weekstokeep))
	$history_weekstokeep = 12;
// note: this doesn't seem to be working ... dunno why...
// but the latest version of BNCweb doesn't delete from query_history anyway


/* other maximums for mysql, NOT settable in config.inc.php */
$max_textid_length = 40;

/* Total size (in bytes) of temp files (for CQP only!) */
/* before cached queries are deleted: default is 3 GB  */
if (!isset($cache_size_limit))
	$cache_size_limit = 3221225472;

/* Default maximum size for DBs -- can be changed on a per-user basis */
if (!isset($default_max_dbsize))
	$default_max_dbsize = 1000000;
/* important note about default_max_dbsize: it refers to the ** query ** on which 
   the create_db action is being run. A distribution database will have as many rows as there
   are solutions, but a collocation database will have the num solutions x window x 2.
   
   For this reason we need the next variable as well, to control the relationship
   between the max dbsize as taken from the user record, and the effective max dbsize
   employed when we are creating a collocation database (rather than any other type of DB)
   */
if (!isset($colloc_db_premium))
	$colloc_db_premium = 4;

/* Total size (in rows) of database (distribution, colloc, etc) tables */
/* before cached dbs are deleted: default is 100 of the biggest db possible  */
if (!isset($default_max_fullsize_dbs_in_cache))
	$default_max_fullsize_dbs_in_cache = 100;

$mysql_db_size_limit = $default_max_fullsize_dbs_in_cache * $colloc_db_premium * $default_max_dbsize;

/* same for frequency tables: defaulting to 3 gig */
if (!isset($mysql_freqtables_size_limit))
	$mysql_freqtables_size_limit = 3221225472;

/* max number of concurrent mysql processes of any one kind (big processes ie collocation, sort) */
if (!isset($default_mysql_process_limit))
	$default_mysql_process_limit = 5;

$mysql_process_limit = array(
	'colloc' => $default_mysql_process_limit,
	'freqtable' => $default_mysql_process_limit,
	'sort' => $default_mysql_process_limit,
	'dist' => 100,
	'catquery' => $default_mysql_process_limit
	);
/* plus names for if they need to be printed */
$mysql_process_name = array(
	'colloc'=> 'collocation',
	'dist' => 'distribution',
	'sort' => 'query sort',
	'freq_table' => 'frequency list'
	);


/* if apache (or the like) is not being used, then $username should be set by code in config.inc.php */
if (!isset($username))
	$username = $_SERVER['REMOTE_USER'];
if (!isset($username))
	$username = '__unknown_user';



/* instance_name is the unique identifier of the present run of a given script 
 * which will be used as the name of any queries/records saved by the present script */
$instance_name = $username . '_' . time();

if (! isset($this_script))
{
	preg_match('/\/([^\/]+)$/', $_SERVER['SCRIPT_FILENAME'], $m);
	$this_script = $m[1];
}



/* ------------ */
/* MAGIC QUOTES */
/* ------------ */

/* a simplified version of the code here: http://php.net/manual/en/security.magicquotes.disabling.php */

if (get_magic_quotes_gpc()) 
{
	foreach ($_POST as $k => $v) 
	{
		unset($_POST[$k]);
		$_POST[stripslashes($k)] = stripslashes($v);
	}
	unset($k, $v);
	foreach ($_GET as $k => $v) 
	{
		unset($_GET[$k]);
		$_GET[stripslashes($k)] = stripslashes($v);
	}
	unset($k, $v);
}










?>