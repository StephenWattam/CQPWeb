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






/* index.inc.php */

/* this file contains the code that renders the various search screens */

/* inputs for forms that access this script:

   thisQ - specify the type of query you want to pop up
   
   other inputs vary depdning on function

*/


/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */


/* before anything else */
header('Content-Type: text/html; charset=utf-8');


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
/* just for one function */
require_once("../lib/concordance-lib.inc.php");
/* just for print functions */
require_once("../lib/colloc-lib.inc.php");

/* this is probably _too_ paranoid. but hey */
if (user_is_superuser($username))
{
	require_once('../lib/apache.inc.php');
	require_once('../lib/admin-lib.inc.php');
	require_once('../lib/corpus-settings.inc.php');
}


/* especially, include the functions for each type of query */
require_once("../lib/indexforms-queries.inc.php");
require_once("../lib/indexforms-saved.inc.php");
require_once("../lib/indexforms-admin.inc.php");
require_once("../lib/indexforms-subcorpus.inc.php");
require_once("../lib/indexforms-others.inc.php");






/* initialise variables from $_GET */

/* in the case of index.php, we can allow there not to be any arguments, and set
   them manually */


if (! isset($_GET["thisQ"]) )
	$thisQ = "search";
else 
	$thisQ = $_GET["thisQ"];
	
	
/* NOTE: some particular ptinquery_ functions will demand other arguments */






/* connect to mySQL and set up for UTF-8 */

$mysql_link = mysql_connect($mysql_server, $mysql_webuser, $mysql_webpass);

if (! $mysql_link)
{
	?>
	<p class="errormessage">
		mySQL did not connect - please try again later!
	</p></body></html> 
	<?php
	exit(1);
}

mysql_select_db($mysql_schema, $mysql_link);

/* utf-8 setting is dependent on a variable defined in settings.inc.php */
if ($utf8_set_required)
	mysql_query("SET NAMES utf8", $mysql_link);




?>


<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
echo '<title>' . $corpus_title . ' -- CQPweb</title>';
echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
?>
</head>
<body>

<table class="concordtable" width="100%">
	<tr>
		<td valign="top">

<?php





/***********************/
/* PRINT SIDE BAR MENU */
/***********************/

// TODO: add tool tips using onmouseOver

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


/* SIMPLE QUERY */
echo "<tr><td class=\"";
if ($thisQ != "search")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=search&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Standard query</a></td></tr>";


/* RESTRICTED QUERY */
echo "<tr><td class=\"";
if ($thisQ != "restrict")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=restrict&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Restricted query</a></td></tr>";


/* WORDLOOKUP QUERY */
echo "<tr><td class=\"";
if ($thisQ != "lookup")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=lookup&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Word lookup</a></td></tr>";

//
// NB - is distinction between Word lists and Frequncy lists BNC-specific? 
// if not, what is it?
//

/* FREQLIST QUERY */
echo "<tr><td class=\"";
if ($thisQ != "freqList")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=freqList&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Frequency lists</a></td></tr>";


/* KEYWORDS QUERY */
echo "<tr><td class=\"";
if ($thisQ != "keywords")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=keywords&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Keywords</a></td></tr>";


/* BROWSE FILE * /
/* am I even going to bother with this? Prob can't wihout fulltext * /
echo "<tr><td class=\"";
if ($thisQ != "browse")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=browse&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Browse a file</a></td></tr>";
*/

/* The next two are nto needed - they are actually subsections of "create subcorpus * /
echo "<tr><td class=\"";
if ($thisQ != "metadata")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=metadata&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Search metadata</a></td></tr>";


echo "<tr><td class=\"";
if ($thisQ != "textClasses")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=textClasses&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Explore text classes</a></td></tr>";

*/


?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">User controls</a></th>
</tr>
<?php
/* note, it is not necessary to put the username in the URL for the following 
   options cos the script derives it from the environment anyway */


/* USER SETTINGS */
echo "<tr><td class=\"";
if ($thisQ != "userSettings")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=userSettings&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "User settings</a></td></tr>";


/* QUERY HIST */
echo "<tr><td class=\"";
if ($thisQ != "history")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=history&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Query history</a></td></tr>";


/* SAVED QUERIES */
echo "<tr><td class=\"";
if ($thisQ != "savedQs")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=savedQs&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Saved queries</a></td></tr>";


/* CATEGORISED QUERIES */
echo "<tr><td class=\"";
if ($thisQ != "categorisedQs")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=categorisedQs&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Categorised queries</a></td></tr>";


/* CREATE/EDIT SUBCORPORA */
echo "<tr><td class=\"";
if ($thisQ != "subcorpus")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=subcorpus&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Create/edit subcorpora</a></td></tr>";


?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">Corpus info</a></th>
</tr>
<?php


/* SHOW CORPUS METADATA */
echo "<tr><td class=\"";
if ($thisQ != "corpusMetadata")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=corpusMetadata&uT=y\" 
		onmouseover=\"return escape('View CQPweb\'s database of information about this corpus')\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "View corpus metadata</a></td></tr>";



/* print a link to a corpus manual, if there is one */
$sql_query = "select external_url from corpus_metadata_fixed where corpus = '"
	. $corpus_sql_name . "' and external_url IS NOT NULL";
$result = mysql_query($sql_query, $mysql_link);
if ($result == false) 
	exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), __FILE__, __LINE__);
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
$sql_query = "select description, tagset, external_url from annotation_metadata where corpus = '"
	. $corpus_sql_name . "' and external_url IS NOT NULL";
$result = mysql_query($sql_query, $mysql_link);
if ($result == false) 
	exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), __FILE__, __LINE__);


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

	echo "<tr><td class=\"";
	if ($thisQ != "corpusSettings")
		echo "concordgeneral\"><a class=\"menuItem\" 
			href=\"index.php?thisQ=corpusSettings&uT=y\">";
	else 
		echo "concordgrey\"><a class=\"menuCurrentItem\">";
	echo "Corpus settings</a></td></tr>";

	if ($cqpweb_uses_apache)
	{
		echo "<tr><td class=\"";
		if ($thisQ != "userAccess")
			echo "concordgeneral\"><a class=\"menuItem\" 
				href=\"index.php?thisQ=userAccess&uT=y\">";
		else 
			echo "concordgrey\"><a class=\"menuCurrentItem\">";
		echo "Manage access</a></td></tr>";
	}
	

	echo "<tr><td class=\"";
	if ($thisQ != "manageMetadata")
		echo "concordgeneral\"><a class=\"menuItem\" 
			href=\"index.php?thisQ=manageMetadata&uT=y\">";
	else 
		echo "concordgrey\"><a class=\"menuCurrentItem\">";
	echo "Manage metadata</a></td></tr>";



	echo "<tr><td class=\"";
	if ($thisQ != "manageAnnotation")
		echo "concordgeneral\"><a class=\"menuItem\" 
			href=\"index.php?thisQ=manageAnnotation&uT=y\">";
	else 
		echo "concordgrey\"><a class=\"menuCurrentItem\">";
	echo "Manage annotation</a></td></tr>";



	echo "<tr><td class=\"";
	if ($thisQ != "cachedQueries")
		echo "concordgeneral\"><a class=\"menuItem\" 
			href=\"index.php?thisQ=cachedQueries&uT=y\">";
	else 
		echo "concordgrey\"><a class=\"menuCurrentItem\">";
	echo "Cached queries</a></td></tr>";



	echo "<tr><td class=\"";
	if ($thisQ != "cachedDatabases")
		echo "concordgeneral\"><a class=\"menuItem\" 
			href=\"index.php?thisQ=cachedDatabases&uT=y\">";
	else 
		echo "concordgrey\"><a class=\"menuCurrentItem\">";
	echo "Cached databases</a></td></tr>";


	echo "<tr><td class=\"";
	if ($thisQ != "cachedFrequencyLists")
		echo "concordgeneral\"><a class=\"menuItem\" 
			href=\"index.php?thisQ=cachedFrequencyLists&uT=y\">";
	else 
		echo "concordgrey\"><a class=\"menuCurrentItem\">";
	echo "Cached frequency lists</a></td></tr>";


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

/* WHO */
echo "<tr><td class=\"";
if ($thisQ != "who_the_hell")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=who_the_hell&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Who did it?</a></td></tr>";


/* LATEST NEWS */
echo "<tr><td class=\"";
if ($thisQ != "latest")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=latest&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Latest news</a></td></tr>";


/* Bugs */
echo "<tr><td class=\"";
if ($thisQ != "bugs")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisQ=bugs&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Report bugs</a></td></tr>";



?>
</table>

		</td>
		<td valign="top">
		
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">
		<?php echo "$corpus_title: <em>powered by CQPweb</em>"; ?>
		</a></th>
	</tr>
</table>



<?php




/**************************************/
/* PRINT MAIN SEARCH FUNCTION CONTENT */
/**************************************/



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

case 'manageAnnotation':
	printquery_manageannotation();
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

/* ... and disconnect mysql */
mysql_close($mysql_link);

/* ------------- */
/* END OF SCRIPT */
/* ------------- */



?>