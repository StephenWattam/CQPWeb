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






/* index.inc.php --- this file contains the code that renders 
 * various search screens and other front-page stuff (basically
 * everything you access from the mainpage side-menu).
 */

/* The main paramater for forms that access this script:
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

require_once("settings.inc.php");
require_once("../lib/defaults.inc.php");


/* include function library files */
require_once("../lib/library.inc.php");
require_once("../lib/user-settings.inc.php");
require_once("../lib/exiterror.inc.php");
require_once("../lib/cache.inc.php");
require_once("../lib/subcorpus.inc.php");
require_once("../lib/db.inc.php");
require_once("../lib/ceql.inc.php");
require_once("../lib/freqtable.inc.php");
require_once("../lib/metadata.inc.php");
require_once("../lib/concordance-lib.inc.php");
require_once("../lib/colloc-lib.inc.php");

/* this is probably _too_ paranoid. but hey */
if (user_is_superuser($username))
{
	require_once('../lib/apache.inc.php');
	require_once('../lib/admin-lib.inc.php');
	require_once('../lib/corpus-settings.inc.php');
	require_once('../lib/xml.inc.php');		// move to main section if users need XML functions
}


/* especially, include the functions for each type of query */
require_once("../lib/indexforms-queries.inc.php");
require_once("../lib/indexforms-saved.inc.php");
require_once("../lib/indexforms-admin.inc.php");
require_once("../lib/indexforms-subcorpus.inc.php");
require_once("../lib/indexforms-others.inc.php");






/* initialise variables from $_GET */

/* in the case of index.php, we can allow there not to be any arguments, and supply a default;
 * so don't check for presence of uT=y */

/* thisQ: the query whose interface page is to be displayed on the right-hand-side. */
$thisQ = ( isset($_GET["thisQ"]) ? $_GET["thisQ"] : 'search' );
	
/* NOTE: some particular printquery_.* functions will demand other $_GET variables */





/* connect to mySQL */
connect_global_mysql();





/* before anything else */
header('Content-Type: text/html; charset=utf-8');
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
echo '<title>' . $corpus_title . ' -- CQPweb</title>';
echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
?>
<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
</head>
<body>

<table class="concordtable" width="100%">
	<tr>
		<td valign="top">

<?php





/* ******************* */
/* PRINT SIDE BAR MENU */
/* ******************* */

?>
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Menu</a></th>
	</tr>
</table>

<table class="concordtable" width="100%">

<tr>
	<th class="concordtable"><a class="menuHeaderItem">Corpus queries</a></th>
</tr>

<?php
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
/* At least for the moment, CQPweb is not bothering with this function from BNCweb
echo print_menurow_index('browse', 'Browse a file');
*/
?>

<tr>
	<th class="concordtable"><a class="menuHeaderItem">User controls</a></th>
</tr>

<?php
echo print_menurow_index('userSettings', 'User settings');
echo print_menurow_index('history', 'Query history');
echo print_menurow_index('savedQs', 'Saved queries');
echo print_menurow_index('categorisedQs', 'Categorised queries');
echo print_menurow_index('uploadQ', 'Upload a query');
echo print_menurow_index('subcorpus', 'Create/edit subcorpora');
?>

<tr>
	<th class="concordtable"><a class="menuHeaderItem">Corpus info</a></th>
</tr>

<?php
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
$sql_query = "select external_url from corpus_metadata_fixed "
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
	?>
	
	<tr>
		<th class="concordtable">
			<a class="menuHeaderItem">Admin tools</a>
		</th>
	</tr>
	<tr>
		<td class="concordgeneral">
			<a class="menuItem" href="../adm">Admin control panel</a>
		</td>
	</tr>
	<?php
	
	echo print_menurow_index('corpusSettings', 'Corpus settings');
	if ($cqpweb_uses_apache)
		echo print_menurow_index('userAccess', 'Manage access');
	echo print_menurow_index('manageMetadata', 'Manage metadata');
	echo print_menurow_index('manageCategories', 'Manage text categories');
	echo print_menurow_index('manageAnnotation', 'Manage annotation');
	echo print_menurow_index('manageVisualisation', 'Manage visualisations');
	echo print_menurow_index('cachedQueries', 'Cached queries');
	echo print_menurow_index('cachedDatabases', 'Cached databases');
	echo print_menurow_index('cachedFrequencyLists', 'Cached frequency lists');
	
} /* end of "if user is a superuser" */

?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">About CQPweb</a></th>
</tr>

<tr>
	<td class="concordgeneral">
		<a class="menuItem" href="../"
			onmouseover="return escape('Go to a list of all corpora on the CQPweb system')">
			CQPweb main menu
		</a>
	</td>
</tr>
<tr>
	<td class="concordgeneral">
		<a class="menuItem" target="_blank" href="../doc/CQPweb-man.pdf"
			onmouseover="return escape('CQPweb manual')">
			CQPweb manual
		</a>
	</td>
</tr>
<?php
echo print_menurow_index('who_the_hell', 'Who did it?');
echo print_menurow_index('latest', 'Latest news');
echo print_menurow_index('bugs', 'Report bugs');


?>
</table>

		</td>
		<td valign="top">
		
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">
		<?php echo $corpus_title . $searchpage_corpus_name_suffix; ?>
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

case 'userSettings':
	printquery_usersettings();
	printquery_usermacros();
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
		We are sorry, but that is not a valid query type.
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

print_footer();

disconnect_global_mysql();

/* ------------- */
/* END OF SCRIPT */
/* ------------- */


?>