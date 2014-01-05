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
 * This file contains the global configuration checks and default values that are imported 
 * into the $Config object.
 */



/* php stubs in each corpus directory; we can't make this constant, but it should be treated as if it was! */ 
$cqpweb_script_files = array( 'api', 'collocation', 'concordance', 'context',
						'distribution', 'execute', 'freqlist',
						'freqtable-compile', 'help', 'index',
						'keywords', 'redirect', 'subcorpus-admin',
						'textmeta', 'upload-query');

/* "reserved words" that can't be used for corpus ids;
 * note: all reserved words are 3 lowercase letters and any new ones we add will also be 3 lowercase letters */
$cqpweb_reserved_subdirs = array('adm', 'bin', 'css', 'doc', 'lib', 'rss', 'usr');







/* ------------------------ */
/* GENERAL DEFAULT SETTINGS */
/* ------------------------ */




/* Global setting: are we running on Windows? */
if (!isset($cqpweb_running_on_windows))
	$cqpweb_running_on_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

/* Is this copy of CQPweb available for access via the internet? */
if (!isset($cqpweb_no_internet))
	$cqpweb_no_internet = false;

/* supply default email address */
if (!isset($cqpweb_email_from_address))
	$cqpweb_email_from_address = '';

/* name for cookies stored in users' browsers */
if (!isset($cqpweb_cookie_name))
	$cqpweb_cookie_name = "CQPwebLogonToken";

/* how long can someone stay logged in without visiting the site? */
if (!isset($cqpweb_cookie_max_persist))
	$cqpweb_cookie_max_persist = 5184000;




/* Does mysqld have file-write/read ability? If set to true, CQPweb uses LOAD DATA
 * INFILE and SELECT INTO OUTFILE. If set to false, file write/read into/out of
 * mysql tables is done via the client-server link.
 * 
 * Giving mysqld file access, so that CQPweb can directly exchange files in 
 * the temp/cache directory with the MySQL server, may be considerably more efficient.
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

/* Has MySQL got LOAD DATA LOCAL disabled? */
if (!isset($mysql_local_infile_disabled))
	$mysql_local_infile_disabled = false;
	
/* From the previous two variables, deduce whether we have ANY infile access. */
if ($mysql_has_file_access)
	/* if the SERVER has file access, then lack of LOAD DATA LOCAL doesn't matter */
	// in THEORY. I haven't checked ths out with a server that has LOAD DATA LOCAL disabled.
	$mysql_infile_disabled = false;
else
	/* otherwise, whether we have ANY infile access is dependent on whether we have local access */
	$mysql_infile_disabled = $mysql_local_infile_disabled;




/* These are defaults for the max amount of memory allowed for CWB programs that let you set this,
 * counted in megabytes. The first is used for web-scripts, the second for CLI-scripts. */
if (!isset($cwb_max_ram_usage))
	$cwb_max_ram_usage = 50;
else
	$cwb_max_ram_usage = (int)$cwb_max_ram_usage;
if (!isset($cwb_max_ram_usage_cli))
	$cwb_max_ram_usage_cli = 1000;
else
	$cwb_max_ram_usage_cli = (int)$cwb_max_ram_usage_cli;
/* the default allows generous memory for indexing in command-line mode,
 * but is stingy in the Web interface, so admins can't bring down the server accidentally */


/* defaults for paths: we add on / unless it is empty, in which case, a zero-string gets embedded before program names. */
$path_to_cwb  = (empty($path_to_cwb)  ? '' : rtrim($path_to_cwb,  DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR );
$path_to_gnu  = (empty($path_to_gnu)  ? '' : rtrim($path_to_gnu,  DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR );
$path_to_perl = (empty($path_to_perl) ? '' : rtrim($path_to_perl, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR );

/* Canonical form for $cwb_extra_perl_directories is an array of  directories; but the input format is a string of pipe-
 * delimited directories. This bit of code converts. An empty array is used if the config string vairable is not set.    */ 
if (isset($perl_extra_directories))
{
	$perl_extra_directories = explode('|',$perl_extra_directories);
	foreach($perl_extra_directories as &$perldir)
		$perldir = rtrim($perldir, "/ \t\r\n");
	unset($perldir);
}
else
	$perl_extra_directories = array();
	

/* the following stops calls to CQP::set_corpus causing an error in the "adm" scripts */
if (!isset($corpus_cqp_name))
	$corpus_cqp_name = ';';

if (!isset($mysql_utf8_set_required))
	$mysql_utf8_set_required = false;
	
/* the next defaults are for tweaks to the system -- not so much critical! */

if (!isset($use_corpus_categories_on_homepage))
	$use_corpus_categories_on_homepage = false;

if (!isset($css_path_for_homepage))
	$css_path_for_homepage = "css/CQPweb.css";

if (!isset($css_path_for_adminpage))
	$css_path_for_adminpage = "../css/CQPweb-red.css";

if (!isset($css_path_for_userpage))
	$css_path_for_userpage = "../css/CQPweb-green.css";

if (!isset($homepage_welcome_message))
	$homepage_welcome_message = "Welcome to CQPweb!";

if (!isset($searchpage_corpus_name_suffix))
	$searchpage_corpus_name_suffix = ': <em>powered by CQPweb</em>';

if (!isset($create_password_function))
	$create_password_function = "password_insert_internal";

if (!isset($print_debug_messages))
	$print_debug_messages = false;

if (!isset($debug_messages_textonly))
	$debug_messages_textonly = false;
/* but whether it was set or not we override it on the command-line */
if (php_sapi_name() == 'cli')
	$debug_messages_textonly = true;






/* This is not a default - it cleans up the input, so we can be sure the root
 * URL ends in a slash. */
if (isset($cqpweb_root_url))
	$cqpweb_root_url = rtrim($cqpweb_root_url, '/') . '/';


/* --------------------------- */
/* PER-CORPUS DEFAULT SETTINGS */
/* --------------------------- */
/* for if settings.inc.php don't specify them */

if (!isset($corpus_main_script_is_r2l))
	$corpus_main_script_is_r2l = false;

if (!isset($corpus_uses_case_sensitivity))
	$corpus_uses_case_sensitivity = false;

$corpus_sql_collation = $corpus_uses_case_sensitivity ? 'utf8_bin' : 'utf8_general_ci' ;
$corpus_cqp_query_default_flags = $corpus_uses_case_sensitivity ? '' : '%c' ; 


if (!isset($css_path))
	$css_path = "../css/CQPweb.css";

if (!isset($graph_img_path))
	$graph_img_path = "../css/img/blue.bmp";

if (!isset($dist_num_files_to_list))
	$dist_num_files_to_list = 100;

// TODO. these variables talk about "context", but actually refer to the concordance. Might be useful to clarify this.

if (isset($context_s_attribute))
	$context_scope_is_based_on_s = true;
else
	$context_scope_is_based_on_s = false;

if (!isset($context_scope))
	$context_scope = ( $context_scope_is_based_on_s ? 1 : 12 );

//TODO. next 2 variable names are confusing, cos they are not defaults: they can be set per-corpus.
// why are these even per-page things?

if (!isset($default_per_page))
	$default_per_page = 50;

if (!isset($default_history_per_page))
	$default_history_per_page = 100;

if (!isset($initial_extended_context))
	$initial_extended_context = 100;

if (!isset($max_extended_context))
	$max_extended_context = 1100;

/* and sanity check the above two... */
if ($initial_extended_context > $max_extended_context)
	$initial_extended_context = $max_extended_context;

/* position labels default off */
if (!isset($visualise_position_labels))
	$visualise_position_labels = false;
else
	if (!isset($visualise_position_label_attribute))
		$visualise_position_labels = false;

/* interlinear glossing default off */
if (!isset($visualise_gloss_in_concordance))
	$visualise_gloss_in_concordance = false;
if (!isset($visualise_gloss_in_context))
	$visualise_gloss_in_context = false;
if ($visualise_gloss_in_concordance || $visualise_gloss_in_context)
	if (!isset($visualise_gloss_annotation))
		$visualise_gloss_annotation = 'word'; 

/* supply translations default off */
if (!isset($visualise_translate_in_concordance))
	$visualise_translate_in_concordance = false;
if (!isset($visualise_translate_in_context))
	$visualise_translate_in_context = false;
if (!isset($visualise_translate_s_att))
{
	/* we can't default this one: we'll have to switch off these variables */
	$visualise_translate_in_context = false;
	$visualise_translate_in_concordance = false;
}
else
{
	/* we override $context_scope etc... if this is to be used in concordance */
	if ($visualise_translate_in_concordance)
	{
		$context_s_attribute = $visualise_translate_s_att;
		$context_scope_is_based_on_s = true;
		$context_scope = 1;
	}
}
 

	
/* collocation defaults */
if (!isset($default_colloc_range))
	$default_colloc_range = 5;
	
if (!isset($default_calc_stat))
	$default_calc_stat = 6; 	/* {6 == log-likelihood} is default collocation statistic */

if (!isset($default_colloc_minfreq))
	$default_colloc_minfreq = 5;

if (!isset($default_collocations_per_page))
	$default_collocations_per_page = 50;
	
if (!isset($collocation_disallow_cutoff))
	$collocation_disallow_cutoff = 100000000; /* cutoff for disallowing on-the-fly freqtables altogether: 100 million */
	
if (!isset($collocation_warning_cutoff))
	$collocation_warning_cutoff = 5000000; /* cutoff for going from a minor warning to a major warning: 5 million */

/* NB, warning cutoff must always be lower than disallow cutoff: so let's sanity check */
if ($collocation_warning_cutoff >= $collocation_disallow_cutoff)
	$collocation_warning_cutoff = $collocation_disallow_cutoff - 1;

/* TODO ultimately, the "disallow" cutoff should be a user privilege. */

/* collocation download default */
if (!isset($default_words_in_download_context))
	$default_words_in_download_context = 10;





/* ----------------------- */
/* SYSTEM DEFAULT SETTINGS */
/* ----------------------- */

/* some can be overrridden in the config file -- some can't! */



/* other maximums for mysql, NOT settable in config.inc.php */
$max_textid_length = 40; // TODO should this not be used in the creation of the MySQL table?????

/* Size limit of cahce directory (based on CQP save-files only!) */
if (!isset($cache_size_limit))
	$cache_size_limit = 6442450944;


//TODO the way DB maxima are calculated is dodgy, to say the least.
// PROBLEMS: (1) names beginning $default that aren;t defaults is confusing, as above
// (2) are the limits working as they should?

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
	$mysql_freqtables_size_limit = 6442450944;

/* max number of concurrent mysql processes of any one kind (big processes ie collocation, sort) */
if (!isset($mysql_big_process_limit))
	$mysql_big_process_limit = 5;

$mysql_process_limit = array(
	'colloc' => $mysql_big_process_limit,
	'freqtable' => $mysql_big_process_limit,
	'sort' => $mysql_big_process_limit,
	'dist' => 100,
	'catquery' => $mysql_big_process_limit
	);
/* plus names for if they need to be printed */
$mysql_process_name = array(
	'colloc'=> 'collocation',
	'dist' => 'distribution',
	'sort' => 'query sort',
	'freq_table' => 'frequency table'
	// TODO do we need catquery here? see where this is used.
	);




/* --------------------------------------------- */
/* VARIABLES SPECIFIC TO THIS INSTANCE OF CQPWEB */
/* --------------------------------------------- */




/**
 * $instance_name is the unique identifier of the present run of a given script 
 * which will be used as the name of any queries/records saved by the present script.
 * 
 * It was formerly the username plus the unix time, but this raised the possibility of
 * one user seeing another's username linked to a cached query. So now it's the PHP uniqid(),
 * which is a hexadecimal version of the Unix time in microseconds. This shouldn't be 
 * possible to duplicate unless (a) we're on a computer fast enough to call uniqid() twice
 * in two different processes in the same microsecond AND (b) two users do happen to hit 
 * the server in the same microsecond. Unlikely, but id codes based on the $instance_name
 * should still be checked for uniqueness before being used in any situation where the 
 * uniqueness matters (e.g. as a database primary key).
 * 
 * For compactness, we convert to base-36.  Total length = 10 chars (for the foreseeable future!).
 */ 
$instance_name = base_convert(uniqid(), 16, 36);

if (! isset($this_script))
{
	preg_match('/\/([^\/]+)$/', $_SERVER['SCRIPT_FILENAME'], $m);
	$this_script = $m[1];
}



?>
