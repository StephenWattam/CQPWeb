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

 
 
/* initialise variables from settings files  */

require_once ('settings.inc.php');
require_once ('../lib/defaults.inc.php');

/* include function library files */
require_once ('../lib/library.inc.php');
//require_once ('../lib/concordance-lib.inc.php');
//require_once ('../lib/concordance-post.inc.php');
//require_once ('../lib/cache.inc.php');
//require_once ('../lib/subcorpus.inc.php');
//require_once ('../lib/exiterror.inc.php');
//require_once ('../lib/metadata.inc.php');
require_once ('../lib/user-settings.inc.php');
//require_once ('../lib/cwb.inc.php'); /* NOT TESTED YET - used by dump and undump, I think */
require_once ('../lib/cqp.inc.php');




/* connect to mySQL */
connect_global_mysql();


/* connect to CQP */
connect_global_cqp();





/* variables from GET needed by both versions of this script */


if (isset($_GET['qname']))
	$qname = $_GET['qname'];
else
	exiterror_parameter('Critical parameter "qname" was not defined!', __FILE__, __LINE__);








if ( isset($_GET['downloadGo']) && $_GET['downloadGo'] === 'yes')
{
	/* ----------------------------- */
	/* create and send the text file */
	/* ----------------------------- */
	
	
	
	
	
	
	
	
	
	
	
	
	
} /* end of if ($_GET['downloadGo'] === 'yes') */

else

{
	/* --------------------------------------- */
	/* write an HTML page with all the options */
	/* --------------------------------------- */
	
	
	$user_settings = get_all_user_settings($username);

	/* enable the user setting to be auto-selected for linebreak type */
	$da_selected = array('d' => '', 'a' => '', 'da' => '');
	if ($user_settings->linefeed == 'au')
		$user_settings->linefeed = guess_user_linefeed($username);
	$da_selected[$user_settings->linefeed] = ' selected="selected" ';
	
	/* before anything else */
	header('Content-Type: text/html; charset=utf-8');
	?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
echo '<title>' . $corpus_title . ' -- CQPweb Tabulate Query</title>';
echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
?>
<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
</head>
<body>
<table class="concordtable" width="100%">
	
	
	<!-- $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$ -->
	
	<tr>
		<th class="concordtable" colspan="2">Other download formats</th>
	</tr>
	<form action="redirect.php" method="get">
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				&nbsp;<br/>
				<input type="submit" value="Download query as plain-text concordance" />
				<br/>&nbsp;
			</td>
		</tr>
		<input type="hidden" name="redirect" value="download" />
		<input type="hidden" name="qname" value="<?php echo $qname; ?>" />
		<input type="hidden" name="uT" value="y" />
	</form>
</table>
</body>
</html>
	<?php


} /* end of the huge determining if-else */


/* disconnect CQP child process and mysql */
disconnect_all();

/* end of script */


?>
	