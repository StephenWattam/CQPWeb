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

//TODO usage here? generalise usage for all bin scripts?
// if --corpus=corpus is specified, change to ../corpus


echo "\n\n/***********************/\n\n\n";

echo '

This script runs all the setup for frequency lists for a corpus.

Full debug messages are printed. 

Note, if you run this script before setting up the text metdata table, things WILL go badly wrong.


';




/* include defaults and settings */
require('../lib/environment.inc.php');


/* include all function files */
include('../lib/admin-lib.inc.php');
include('../lib/cache.inc.php');
include('../lib/db.inc.php');
include('../lib/colloc-lib.inc.php');
include('../lib/concordance-lib.inc.php');
include('../lib/freqtable.inc.php');
include('../lib/freqtable-cwb.inc.php');
include('../lib/library.inc.php');
include('../lib/metadata.inc.php');
include('../lib/subcorpus.inc.php');
//include('../lib/indexforms-admin.inc.php');
//include('../lib/indexforms-queries.inc.php');
//include('../lib/indexforms-saved.inc.php');
//include('../lib/indexforms-others.inc.php');
//include('../lib/indexforms-subcorpus.inc.php');
include('../lib/exiterror.inc.php');
include('../lib/user-lib.inc.php');
include('../lib/rface.inc.php');
include('../lib/corpus-settings.inc.php');
include('../lib/xml.inc.php');
include('../lib/cwb.inc.php');
include('../lib/cqp.inc.php');


if (isset($argv[1]) && is_dir ("../$argv[1]"))
	chdir("../$argv[1]");
else if (!file_exists('settings.inc.php'))
	exit("\nNo corpus specified to run freqlist setup for?\n");


cqpweb_startup_environment();

/* keep a note of when we started */
$start_time = @date(DATE_RSS);

/* expand the PHP memory limit to the same generous limit allowed for CWB */
ini_set('memory_limit', "${cwb_max_ram_usage_cli}M");



$print_debug_messages = true;

/* set up some variables for the offline code */
$corpus = $corpus_sql_name; /* code below was copied from subroutine with argument $corpus */


echo "About to run the function populating corpus CQP positions...\n\n";
populate_corpus_cqp_positions();
echo "Done populating corpus CQP positions.\n\n";

/* if there are any classifications... */
if (mysql_num_rows(
		do_mysql_query("select handle from text_metadata_fields 
			where corpus = '$corpus' and is_classification = 1")
		) > 0 )
{
	echo "About to run the function calculating category sizes...\n\n";
	metadata_calculate_category_sizes();
	echo "Done calculating category sizes.\n\n";
}
else
	echo "Function calculating category sizes was not run because there aren't any text classifications.\n\n";

/* if there is more than one text ... */
list($n) = mysql_fetch_row(do_mysql_query("select count(text_id) from text_metadata_for_$corpus"));
if ($n > 1)
{
	echo "About to run the function making the CWB text-by-text frequency index...\n\n";	
	make_cwb_freq_index();
	echo "Done making the CWB text-by-text frequency index.\n\n";	
}
else
	echo "Function making the CWB text-by-text frequency index was not run because there is only one text.\n\n";	


/* do unconditionally */
echo "About to run the function creating frequency tables.\n\n";	
corpus_make_freqtables();
echo "Done creating frequency tables...\n\n";



cqpweb_shutdown_environment();

$end_time = @date(DATE_RSS);
echo "

Frequency-list setup for corpus $corpus_sql_name is now complete.

Began at: $start_time

Finished at: $end_time

";

?>
