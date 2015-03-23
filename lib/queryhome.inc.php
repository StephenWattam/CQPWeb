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
 * This file contains the code that renders 
 * various search screens and other front-page stuff 
 * (basically everything you access from the mainpage side-menu).
 *
 *
 * The main paramater for forms that access this script:
 *
 * thisQ - specify the type of query you want to pop up
 * 
 * Each different thisQ effectively runs a separate interface.
 * Some of the forms etc. that are created lead to other parts of 
 * CQPweb; some, if they're easy to process, are dealt with here.
 */


/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */



require_once('../lib/environment.inc.php');


/* include function library files */
require_once('../lib/library.inc.php');
require_once('../lib/html-lib.inc.php');
require_once('../lib/user-lib.inc.php');
require_once('../lib/exiterror.inc.php');
require_once('../lib/cache.inc.php');
require_once('../lib/subcorpus.inc.php');
require_once('../lib/db.inc.php');
require_once('../lib/ceql.inc.php');
require_once('../lib/freqtable.inc.php');
require_once('../lib/metadata.inc.php');
require_once('../lib/concordance-lib.inc.php');
require_once('../lib/colloc-lib.inc.php');
require_once('../lib/xml.inc.php');
require_once('../lib/multivariate.inc.php');

/* especially, include the functions for each type of query */
require('../lib/indexforms-queries.inc.php');
require('../lib/indexforms-saved.inc.php');
require('../lib/indexforms-subcorpus.inc.php');
require('../lib/indexforms-others.inc.php');


/* in the case of the index page, we can allow there not to be any arguments, and supply a default;
 * so don't check for presence of uT=y */
cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP | CQPWEB_STARTUP_DONT_CHECK_URLTEST);


/* this is probably _too_ paranoid. but hey */
if ($User->is_admin())
{
	require('../lib/admin-lib.inc.php');
	require('../lib/corpus-settings.inc.php');
	require('../lib/indexforms-admin.inc.php');
}





/* initialise variables from $_GET */



/* thisQ: the query whose interface page is to be displayed on the right-hand-side. */
$thisQ = ( isset($_GET["thisQ"]) ? $_GET["thisQ"] : 'search' );
	
/* NOTE: some particular printquery_.* functions will demand other $_GET variables */





/* strip tags of the header, cos HTML is allowed here... */
echo print_html_header(strip_tags($Corpus->corpus_title . $Config->searchpage_corpus_name_suffix), 
                       $Config->css_path, 
                       array('modal', 'cword', 'queryhome'));


?>
<table class="concordtable" width="100%">
	<tr>
		<td valign="top">

        <?php print_menu() ?>


		</td>
		<td width="100%" valign="top">
		
        <h1 class="page-title"><?php echo $Corpus->corpus_title?></h1>



<?php




/* ********************************** */
/* PRINT MAIN SEARCH FUNCTION CONTENT */
/* ********************************** */



switch($thisQ)
{
case 'search':
	printquery_search();
	display_system_messages();
	break;

case 'restrict':
	printquery_restricted();
	break;

case 'lookup':
	printquery_lookup();
	break;

case 'freqList':
	printquery_freqlist();
	break;

case 'keywords':
	printquery_keywords();
	break;

case 'history':
	printquery_history();
	break;

case 'savedQs':
	printquery_savedqueries();
	break;
	
case 'categorisedQs':
	printquery_catqueries();
	break;

case 'uploadQ':
	printquery_uploadquery();
	break;
	
case 'subcorpus':
	printquery_subcorpus();
	break;

case 'analyseCorpus':
	printquery_analysecorpus();
	break;

case 'corpusMetadata':
	printquery_corpusmetadata();
	break;

case 'corpusSettings':
	printquery_corpusoptions();
	break;

case 'corpusDocs':
    printquery_corpusdocs($corpus_sql_name);
    break;

case 'userAccess':
	printquery_manageaccess();
	break;

case 'manageMetadata':
	printquery_managemeta();
	break;

case 'manageCategories':
	printquery_managecategories();
	break;

case 'manageAnnotation':
	printquery_manageannotation();
	break;

case 'manageVisualisation':
	printquery_visualisation();
	printquery_xmlvisualisation();
	break;

case 'cachedQueries':
	printquery_showcache();
	break;

case 'cachedDatabases':
	printquery_showdbs();
	break;

case 'cachedFrequencyLists':
	printquery_showfreqtables();
	break;

case 'who_the_hell':
	printquery_who();
	break;
	
case 'latest':
	printquery_latest();
	break;

case 'bugs':
	printquery_bugs();
	break;



default:
	?>
	<p class="errormessage">&nbsp;<br/>
		&nbsp; <br/>
		We are sorry, but that is not a valid menu option.
	</p>
	<?php
	break;
}



/* finish off the page */
?>

		</td>
	</tr>
</table>
<?php

echo print_html_footer();

cqpweb_shutdown_environment();


?>
