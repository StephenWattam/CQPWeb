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




/* help.inc.php */

/* this file is a trimmed down chunk of the idnex providing help */

/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */


/* before anything else */
header('Content-Type: text/html; charset=utf-8');


/* initialise variables from settings files  */

require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include function library files */
require ("../lib/library.inc.php");
//require ("../lib/user-settings.inc.php");
//require ("../lib/exiterror.inc.php");
//require ("../lib/cache.inc.php");
//require ("../lib/subcorpus.inc.php");
//require ("../lib/db.inc.php");
//require ("../lib/freqtable.inc.php");
//require ("../lib/metadata.inc.php");
/* just for one function */
//require ("../lib/concordance-lib.inc.php");
/* just for print functions */
//require ("../lib/colloc-lib.inc.php");


/* connect to mySQL */
connect_global_mysql();





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

<?php





/* ******************* */
/* PRINT SIDE BAR MENU */
/* ******************* */

// TTD: add tool tips using onmouseOver

?>
<table class="concordtable" width="50%">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Links to corpus and annotation help</a></th>
	</tr>

<?php


/* SHOW CORPUS METADATA */
echo "<tr><td class=\"";
echo "concordgeneral\"><a class=\"menuItem\" 
	href=\"index.php?thisQ=corpusMetadata&uT=y\" 
	onmouseover=\"return escape('View CQPweb\'s database of information about this corpus')\">
	View corpus metadata</a></td></tr>";



/* print a link to a corpus manual, if there is one */
$sql_query = "select external_url from corpus_metadata_fixed where corpus = '"
	. $corpus_sql_name . "' and external_url IS NOT NULL";
$result = mysql_query($sql_query, $mysql_link);
if ($result == false) 
	exiterror_mysqlquery(mysql_errno($mysql_link), mysql_error($mysql_link), __FILE__, __LINE__);
if (mysql_num_rows($result) < 1)
	echo '<tr><td class="concordgeneral"><a class="menuCurrentItem">No corpus documentation available</a></tr></td>';
else
{
	$row = mysql_fetch_row($result);
	echo '<tr><td class="concordgeneral"><a class="menuItem" href="'
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


<?php

print_footer();

/* ... and disconnect mysql */
mysql_close($mysql_link);

/* ------------- */
/* END OF SCRIPT */
/* ------------- */


?>