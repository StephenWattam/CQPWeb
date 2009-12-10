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


/* initialise variables from settings files  */

require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include function library files */
include ("../lib/library.inc.php");
include ("../lib/exiterror.inc.php");
include ("../lib/metadata.inc.php");




if (!url_string_is_valid())
	exiterror_bad_url();



?>
<html>
<head>
<?php
echo '<title>' . $corpus_title . ': viewing text metadata -- CQPweb </title>';
echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

</head>
<body>

<?php


/* download the information */

/* connect to mySQL */
connect_global_mysql();


	
/* initialise variables from $_GET */

if (! isset($_GET["text"]) )
{
	echo "<p class='errormessage'>View text metadata: No text specified! Please reload CQPweb.
		</p></body></html>";
	exit();
}
else 
	$text_id = mysql_real_escape_string($_GET["text"]);
	

$result = mysql_query("SELECT * from text_metadata_for_$corpus_sql_name 
	where text_id = '$text_id'", $mysql_link);

if ($result == false) 
	printerror_mysqlquery(mysql_errno($mysql_link), 
		mysql_error($mysql_link), __FILE__, __LINE__);

if (mysql_num_rows($result) < 1)
{
	?>
	<p class="errormessage">
		The database doesn't appear to contain any metadata for text <?php echo $text_id; ?>!
	</p></body></html> 
	<?php
	exit(1);
}


echo 
'<table class="concordtable" width="100%">
	<tr>
		<th colspan="2" class="concordtable">';
echo "Metadata for text <em>$text_id</em>
		</th>
	</tr>";


$metadata = mysql_fetch_row($result);
$n = count($metadata);

for ( $i = 0 ; $i < $n ; $i++ )
{
	$att = metadata_expand_attribute(mysql_field_name($result, $i), $metadata[$i]);
	
	/* this expansion is hardwired */
	if ($att['field'] == 'text_id')
		$att['field'] =  'Text identification label';
	/* this expansion is hardwired */
	if ($att['field'] == 'words')
		$att['field'] =  'No. words in text';
	/* don't show the CQP delimiters for the file */
	if ($att['field'] == 'cqp_begin' || $att['field'] == 'cqp_end')
		continue;
	/* don't allow empty cells */
	if ($att['value'] === '')
		$att['value'] = '&nbsp;';
	
	echo '<tr><td class="concordgrey">' . $att['field']
		. '</td><td class="concordgeneral">' . $att['value'] . '</td></tr>
		';
		
	unset($att);
}

/* disconnect mysql */
mysql_close($mysql_link);

echo '</table>';

print_footer();

?>
</body>
</html>