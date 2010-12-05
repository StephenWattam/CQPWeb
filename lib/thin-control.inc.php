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




/* print a form to collect the options for running collocations */

/* initialise variables from settings files  */

require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include function library files */
require("../lib/library.inc.php");
require("../lib/exiterror.inc.php");
require("../lib/cache.inc.php");
//require("../lib/freqtable.inc.php");
//require("../lib/concordance-lib.inc.php");

if (!url_string_is_valid())
	exiterror_bad_url();



/* before anything else */
header('Content-Type: text/html; charset=utf-8');
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
echo '<title>' . $corpus_title . ' -- CQPweb Thinning Options</title>';
echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
?>
<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
</head>
<body>


<?php



/* check parameters - only one we really need is qname */

if (isset($_GET['qname']))
	$qname = $_GET['qname'];
else
	exiterror_parameter('Critical parameter "qname" was not defined!', __FILE__, __LINE__);





/* connect to mySQL */
connect_global_mysql();




/* get the query record so we can find out how many hits we are thinning */
$query_record = check_cache_qname($qname);
if ($query_record === false)
	exiterror_general("The specified query $qname was not found in cache!", __FILE__, __LINE__);

$hits = ( isset($query_record['hits_left']) ? $query_record['hits_left'] : $query_record['hits'] );

$num_of_hits_text = '(current no. of instances: ' . make_thousands($hits) . ')';



/* now print the options form */
?>


<table width="100%" class="concordtable">
	<form action="concordance.php" method="get">
		<tr>
			<th colspan="4" class="concordtable">
				Choose options for thinning your query <?php echo $num_of_hits_text; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				Thinning method:
			</td>
			<td class="concordgrey">
				<select name="newPostP_thinReallyRandom">
					<option value="0" selected="selected">random (selection is reproducible)</option>
					<option value="1">random (selection is not reproducible)</option>
				</select>
			</td>
			<td class="concordgrey">
				<input type="text" name="newPostP_thinTo"/>
				(number of instances or percentage)
			</td>
			<td class="concordgrey">
				<input type="submit" value="Thin this query"/>
			</td>
		</tr>
		<input type="hidden" name="qname" value="<?php echo $qname; ?>"/>
		<input type="hidden" name="newPostP" value="thin"/>
		<input type="hidden" name="newPostP_thinHitsBefore" value="<?php echo $hits; ?>"/>
		<?php 
		if (isset($_GET['viewMode']))
			echo '<input type="hidden" name="viewMode" value="' . $_GET['viewMode'] . '"/>';
		/* does anything else from GET need passing on? */
		?>
		
		<input type="hidden" name="uT" value="y" />
	</form>
</table>



<?php


/* create page end HTML */
print_footer();


/* disconnect mysql */
disconnect_global_mysql();


/* ------------- */
/* end of script */
/* ------------- */

?>