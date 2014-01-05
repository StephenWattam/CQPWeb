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



/* initialise variables from settings files  */
require('../lib/environment.inc.php');


/* include function library files */
require('../lib/library.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/cache.inc.php');
require('../lib/subcorpus.inc.php');
require('../lib/db.inc.php');
require('../lib/ceql.inc.php');
require('../lib/freqtable.inc.php');
require('../lib/metadata.inc.php');
require('../lib/concordance-lib.inc.php');
require('../lib/colloc-lib.inc.php');
require('../lib/xml.inc.php');

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







/* before anything else */
header('Content-Type: text/html; charset=utf-8');

/* strip tags of the header, cos HTML is allowed here... */
echo print_html_header(strip_tags($Corpus->corpus_title . $Config->searchpage_corpus_name_suffix), $Config->css_path, array('cqpweb-clientside'));



?>
<table class="concordtable" width="100%">
	<tr>
		<td valign="top">

<?php





/* ******************* *
 * PRINT SIDE BAR MENU *
 * ******************* */

?>
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Menu</a></th>
	</tr>
</table>

<table class="concordtable" width="100%">

<?php
echo print_menurow_heading('Corpus queries');
echo print_menurow_index('search', 'Standard query');
echo print_menurow_index('restrict', 'Restricted query');
/* TODO
   note for future: "Restrict query by text" vs "Restrict quey by XML"
   OR: Restrict query (by XXXX) to be part of the configuration in the DB?
   with a row for every XXXX that is an XML in the db that has been set up
   for restricting-via? 
   and the normal "Restricted query" is jut a special case for text / text_id
   
   OR: just have "Restricted query" and open up sub-options when that is clicked on?
   */
echo print_menurow_index('lookup', 'Word lookup');
echo print_menurow_index('freqList', 'Frequency lists');
echo print_menurow_index('keywords', 'Keywords');

echo print_menurow_heading('User controls');
//echo print_menurow_index('userSettings', 'User settings');
echo print_menurow_index('history', 'Query history');
echo print_menurow_index('savedQs', 'Saved queries');
echo print_menurow_index('categorisedQs', 'Categorised queries');
echo print_menurow_index('uploadQ', 'Upload a query');
echo print_menurow_index('subcorpus', 'Create/edit subcorpora');

echo print_menurow_heading('Corpus info');

/* note that most of this section is links-out, so we can't use the print-row function */

/* SHOW CORPUS METADATA */
echo "<tr>\n\t<td class=\"";
if ($thisQ != "corpusMetadata")
	echo "concordgeneral\">\n\t\t<a class=\"menuItem\" " 
		. "href=\"index.php?thisQ=corpusMetadata&uT=y\" "
		. "onmouseover=\"return escape('View CQPweb\'s database of information about this corpus')\">";
else 
	echo "concordgrey\">\n\t\t<a class=\"menuCurrentItem\">";
echo "View corpus metadata</a>\n\t</td>\n</tr>";


/* print a link to a corpus manual, if there is one */
$sql_query = "select external_url from corpus_info "
	. "where corpus = '$corpus_sql_name' and external_url IS NOT NULL";
$result = do_mysql_query($sql_query);
if (mysql_num_rows($result) < 1)
	echo '<tr><td class="concordgeneral"><a class="menuCurrentItem">Corpus documentation</a></tr></td>';
else
{
	$row = mysql_fetch_row($result);
	echo '<tr><td class="concordgeneral"><a target="_blank" class="menuItem" href="'
		. $row[0] . '" onmouseover="return escape(\'Info on ' . addcslashes($corpus_title, '\'')
		. ' on the web\')">' . 'Corpus documentation</a></td></tr>';
}
unset($result);
unset($row);


/* print a link to each tagset for which an external_url is declared in metadata */
// todo: change this to use get_corpus_annotation_info()
$sql_query = "select description, tagset, external_url from annotation_metadata "
	. "where corpus = '$corpus_sql_name' and external_url IS NOT NULL";
$result = do_mysql_query($sql_query);

while (($row = mysql_fetch_assoc($result)) != false)
{
	if ($row['external_url'] != '')
		echo '<tr><td class="concordgeneral"><a target="_blank" class="menuItem" href="'
			. $row['external_url'] . '" onmouseover="return escape(\'' . $row['description']
			. ': view documentation\')">' . $row['tagset'] . '</a></td></tr>';
}
unset($result);
unset($row);



/* these are the super-user options */
if (user_is_superuser($username))
{
	echo print_menurow_heading('Admin tools');
	?>

	<tr>
		<td class="concordgeneral">
			<a class="menuItem" href="../adm">Admin control panel</a>
		</td>
	</tr>
	<?php
	
	echo print_menurow_index('corpusSettings', 'Corpus settings');
	echo print_menurow_index('userAccess', 'Manage access');
	echo print_menurow_index('manageMetadata', 'Manage metadata');
	echo print_menurow_index('manageCategories', 'Manage text categories');
	echo print_menurow_index('manageAnnotation', 'Manage annotation');
	echo print_menurow_index('manageVisualisation', 'Manage visualisations');
	echo print_menurow_index('cachedQueries', 'Cached queries');
	echo print_menurow_index('cachedDatabases', 'Cached databases');
	echo print_menurow_index('cachedFrequencyLists', 'Cached frequency lists');
	
} /* end of "if user is a superuser" */

/* all the rest is encapsulated */
echo print_menu_aboutblock();


?>
</table>

		</td>
		<td valign="top">
		
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">
		<?php echo $Corpus->corpus_title . $Config->searchpage_corpus_name_suffix; ?>
		</a></th>
	</tr>
</table>



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

case 'corpusMetadata':
	printquery_corpusmetadata();
	break;

case 'corpusSettings':
	printquery_corpusoptions();
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