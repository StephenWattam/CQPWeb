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







/* before anything else */
header('Content-Type: text/html; charset=utf-8');

include ("lib/defaults.inc.php");
include ("lib/library.inc.php");

/* connect to mySQL */
connect_global_mysql();



if ($use_corpus_categories_on_homepage)
{
	/* get a list of categories */
	$sql_query = "select distinct (corpus_cat) from corpus_metadata_fixed where visible = 1 order by corpus_cat asc";
	$result = mysql_query($sql_query, $mysql_link);

	if ($result == false) 
	{
		?>
		<html><body>
		<p class="errormessage">
			the mySQL query could not be run - please try again later!
		</p></body></html> 
		<?php
		exit(1);
	}
	
	while ( ($r = mysql_fetch_row($result)) != false)
		$categories[] = $r[0];
	
	/* how many categories? if only one, it is either uncategorised or a single assigned cat: ergo don't use cats */
	$n = count($categories);
	if ($n < 2)
		$use_corpus_categories_on_homepage = false;
}
else
{
	$categories[0] = '%';
}


?>
<html>
<head>

<title>CQPweb Main Page</title>

<link rel="stylesheet" type="text/css" href="css/<?php echo $css_path_for_homepage;?>" />

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

</head>
<body>

<table class="concordtable" width="100%">

	<tr>
		<th colspan="3" class="concordtable">
			Welcome to CQPweb!
			<br/>
			<em>Please select a corpus from the list below to enter.</em>
		</th>
	</tr>

<?php


foreach ($categories as $cat)
{
	if ($use_corpus_categories_on_homepage)
		echo '<tr><th colspan="3" class="concordtable">' . $cat . "</th></tr>\n\n";


	/* get a list of corpora */
	
	$sql_query = "select corpus, visible from corpus_metadata_fixed
		where visible = 1 and corpus_cat like '$cat' order by corpus asc";

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
	{
		?>
		<html><body>
		<p class="errormessage">
			the mySQL query could not be run - please try again later!
		</p></body></html> 
		<?php
		exit(1);
	}
	
	$corpus_list = array();
	while ( ($x = mysql_fetch_object($result)) != false)
		$corpus_list[] = $x;
//echo '<pre>';print_r($corpus_list);echo'</pre>';
	
	
	
	
	$i = 0;
	$celltype = 'concordgeneral';
	
	foreach ($corpus_list as $c)
	{
		if ($i == 0)
			echo '<tr>';
			
		include ("{$c->corpus}/settings.inc.php");
		
		echo "
			<td class=\"$celltype\" width=\"33%\" align=\"center\">
				&nbsp;<br/>
				<a href=\"{$c->corpus}/index.php\">$corpus_title</a>
				<br/>&nbsp;
			</td>";
			$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');
		
		if ($i == 2)
		{
			echo '</tr>';
			$i = 0;
		}
		else
		{
			$i++;
		}
		
		unset($corpus_title);
	}
	
	if ($i == 1)
	{
		echo "<td class=\"$celltype\" width=\"33%\" align=\"center\">&nbsp;</td>";
		$i++;
		$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');
	}
	if ($i == 2)
		echo "<td class=\"$celltype\" width=\"33%\" align=\"center\">&nbsp;</td>";

}

?>

			
</table>

<?php


display_system_messages();

print_footer();


/* disconnect mysql */
mysql_close($mysql_link);
?>