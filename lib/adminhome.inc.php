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







/* adminhome.inc.php */

/* this file contains the code that renders the various admin function controls */

/* inputs for forms that access this script:

   thisF - specify the type of query you want to pop up

*/



/* before anything else */
header('Content-Type: text/html; charset=utf-8');



/* first, process the various "actions" that this script may be asked to perform */
require('../lib/admin-execute.inc.php');







/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */


/* initialise variables from settings files  */

require("../lib/defaults.inc.php");


/* include function library files */
require ("../lib/library.inc.php");
require ("../lib/apache.inc.php");
require ("../lib/admin-lib.inc.php");
require ("../lib/exiterror.inc.php");
require ("../lib/metadata.inc.php");
require ("../lib/ceql.inc.php");


if (!user_is_superuser($username))
	exiterror_fullpage("You do not have permission to use this program.", __FILE__, __LINE__);



/* initialise variables from $_GET */

/* in the case of index.php, we can allow there not to be any arguments, and set
   them manually */


if (! isset($_GET["thisF"]) )
	$thisF = "showCorpora";
else 
	$thisF = $_GET["thisF"];





/* connect to mySQL */
connect_global_mysql();




?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>CQPweb Sysadmin Control Panel</title>
<link rel="stylesheet" type="text/css" href="<?php echo $css_path_for_adminpage;?>" />
<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
<!-- nonstandard header includes javascript for corpus highlight function! -->
<script type="text/javascript">
<!--
function corpus_box_highlight_on(corpus)
{
	document.getElementById("corpusCell_"+corpus).className = "concorderror";
}
function corpus_box_highlight_off(corpus)
{
	document.getElementById("corpusCell_"+corpus).className = "concordgeneral";
}
//-->
</script>
</head>
<body>

<table class="concordtable" width="100%">
	<tr>
		<td valign="top">

<?php




/***********************/
/* PRINT SIDE BAR MENU */
/***********************/

// TTD: add tool tips using onmouseOver

?>
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Menu</a></th>
	</tr>
</table>

<table class="concordtable" width="100%">

<tr>
	<th class="concordtable"><a class="menuHeaderItem">Corpora</a></th>
</tr>
<?php


echo "<tr><td class=\"";
if ($thisF != "showCorpora")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=showCorpora&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Show corpora</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "installCorpus" && $thisF != 'installCorpusIndexed')
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=installCorpus&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Install new corpus</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "publicTables")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=publicTables&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Public frequency lists</a></td></tr>";


?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">Uploads</a></th>
</tr>
<?php

echo "<tr><td class=\"";
if ($thisF != "newUpload")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=newUpload&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Upload a file</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "uploadArea")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=uploadArea&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "View upload area</a></td></tr>";


?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">Users</a></th>
</tr>
<?php

echo "<tr><td class=\"";
if ($thisF != "userAdmin")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=userAdmin&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Manage users</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "groupAdmin")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=groupAdmin&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Manage group membership</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "groupAccess")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=groupAccess&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Manage group access</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "superuserAccess")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=superuserAccess&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Manage superuser access</a></td></tr>";


?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">Database</a></th>
</tr>

<?php

echo "<tr><td class=\"";
if ($thisF != "manageProcesses")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=manageProcesses&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Manage MySQL processes</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "tableView")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=tableView&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "View a MySQL table</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "mysqlRestore")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=mysqlRestore&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Reset MySQL database</a></td></tr>";

?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">System</a></th>
</tr>

<?php

echo "<tr><td class=\"";
if ($thisF != "systemSettings")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=systemSettings&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "System settings</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "systemMessages")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=systemMessages&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "System messages</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "systemSecurity")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=systemSecurity&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "System security</a></td></tr>";


?>
<tr>
	<th class="concordtable"><a class="menuHeaderItem">Misc</a></th>
</tr>

<tr>
	<td class="concordgeneral">
		<a class="menuItem" href="../"
			onmouseover="return escape('Go to a list of all corpora on the CQPweb system')">
			CQPweb main menu
		</a>
	</td>
</tr>
<?php

echo "<tr><td class=\"";
if ($thisF != "skins")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=skins&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Skins and colours</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "mappingTables")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=mappingTables&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Mapping tables</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "cacheControl")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=cacheControl&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Cache control</a></td></tr>";

echo "<tr><td class=\"";
if ($thisF != "phpConfig")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=phpConfig&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "PHP configuration</a></td></tr>";


?>


<!--  everythign below this poitn NEEDS INTEGRATING -->


<tr>
	<th class="concordtable"><a class="menuHeaderItem">Usage Statistics</a></th>
</tr>
<?php


echo "<tr><td class=\"";
if ($thisF != "corpusStatistics")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=corpusStatistics&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Corpus statistics</a></td></tr>";


echo "<tr><td class=\"";
if ($thisF != "userStatistics")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=userStatistics&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "User statistics</a></td></tr>";


echo "<tr><td class=\"";
if ($thisF != "queryStatistics")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=queryStatistics&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Query statistics</a></td></tr>";

/*
echo "<tr><td class=\"";
if ($thisF != "usageStatistics")
	echo "concordgeneral\"><a class=\"menuItem\" 
		href=\"index.php?thisF=usageStatistics"
		. "&orderBy=numberQueries&uT=y\">";
else 
	echo "concordgrey\"><a class=\"menuCurrentItem\">";
echo "Usage statistics</a></td></tr>";
*/

?>
</table>

		</td>
		<td valign="top">
		
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable">
			CQPweb Sysadmin Control Panel
		</th>
	</tr>
</table>



<?php




/* ********************************** */
/* PRINT MAIN SEARCH FUNCTION CONTENT */
/* ********************************** */





switch($thisF)
{
case 'showCorpora':
	printquery_showcorpora();
	break;
	
case 'installCorpus':
	printquery_installcorpus_unindexed();
	break;

case 'installCorpusIndexed':
	printquery_installcorpus_indexed();
	break;

case 'installCorpusDone':
	printquery_installcorpusdone();
	break;
	
case 'deleteCorpus':
	/* note - this never has a menu entry -- it must be triggered from showCorpora */
	printquery_deletecorpus();
	break;
	
case 'publicTables':
	echo '<p class="errormessage">We\'re sorry, this function has not been built yet.</p>';
	break;

case 'newUpload':
	printquery_newupload();
	break;
	
case 'uploadArea':
	printquery_uploadarea();
	break;
	
case 'userAdmin':
	printquery_useradmin();
	break;
	
case 'groupAdmin':
	printquery_groupadmin();
	break;

case 'groupAccess':
	printquery_groupaccess();
	break;

case 'superuserAccess':
	printquery_superuseraccess();
	break;
	
case 'skins':
	printquery_skins();
	break;

case 'mappingTables':
	printquery_mappingtables();
	break;

case 'cacheControl':
case 'systemSettings':
	echo '<p class="errormessage">We\'re sorry, this function has not been built yet.</p>';
	break;
	
case 'systemMessages':
	printquery_systemannouncements();
	break;

case 'systemSecurity':
	printquery_systemsecurity();
	break;

case 'mysqlRestore':
	printquery_mysqlsystemrestore();
	break;

case 'phpConfig':
	printquery_phpconfig();
	break;

case 'tableView':
	printquery_tableview();
	break;
	
case 'manageProcesses':
	printquery_mysqlprocesses();
	break;
	
case 'corpusStatistics':
	printquery_statistic('corpus');
	break;

case 'userStatistics':
	printquery_statistic('user');
	break;

case 'queryStatistics':
	printquery_statistic('query');
	break;


default:
	?>
	<p class="errormessage">&nbsp;<br/>
		&nbsp; <br/>
		We are sorry, but that is not a valid function type.
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


















/* -------------- */
/* FUNCTIONS HERE */
/* -------------- */


function printquery_showcorpora()
{
	global $corpus_sql_name;	/* this is not brought from global scope but inserted into it */
	global $mysql_link;

	$sql_query = "select * from corpus_metadata_fixed order by corpus asc";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="8">Showing corpus settings for currently installed corpora</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="8">
				&nbsp;<br/>
				<em>Visible</em> means the corpus is accessible through the main menu. Invisible
				corpora can still be accessed by direct URL entry by people who know the web address.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable">Corpus</th>
			<th class="concordtable" colspan="2">Visibility</th>
			<!--
			<th class="concordtable">Primary classification</th>
			<th class="concordtable">External URL</th>
			-->
			<th class="concordtable" colspan="4">Manage...</th>
			<th class="concordtable">Delete</th>
		</tr>
	<?php
	
	while ( ($r=mysql_fetch_assoc($result)) !== false)
	{
		if ($r['visible'])
			$visible_options = '<option value="1" selected="selected">Visible</option>
				<option value="0">Invisible</option>';
		else
			$visible_options = '<option value="1">Visible</option>
				<option value="0" selected="selected">Invisible</option>';

		// NO LONGER DONE BY THIS FORM
		/* note the use of the setting usually set by "settings" * /
		$corpus_sql_name = $r['corpus'];
		$classifications = metadata_list_classifications();
		$class_options = '';
		
		foreach ($classifications as &$class)
		{
			$class_options .= "<option value=\"{$class['handle']}\"";
			$class_options .= ($class['handle'] === $r['primary_classification_field'] ? 'selected="selected"' : '');
			$class_options .= '>' . $class['description'] . '</option>';
		}
				
		*/
		
		$javalinks = ' onmouseover="corpus_box_highlight_on(\'' . $r['corpus'] 
			. '\')" onmouseout="corpus_box_highlight_off(\'' . $r['corpus'] 
			. '\')" ';
		
		?>
		<tr>
			<form action="index.php" method="get">
				<td class="concordgeneral" <?php echo "id=\"corpusCell_{$r['corpus']}\""; ?>>
					<a class="menuItem" href="../<?php echo $r['corpus']; ?>">
						<strong><?php echo $r['corpus']; ?></strong>
					</a>
				</td>
				
				<td align="center" class="concordgeneral">
					<select name="updateVisible"><?php echo $visible_options; ?></select>
				</td>
				
				<!--
				<td align="center" class="concordgeneral">
					<select name="updatePrimaryClassification"><?php /*echo $class_options; */?></select>
				</td>
								
				-->
				
				<td align="center" class="concordgeneral"><input type="submit" value="Update!"></td>
				
				<input type="hidden" name="corpus" value="<?php echo $r['corpus']; ?>" />
				<input type="hidden" name="admFunction" value="updateCorpusMetadata" />
				<input type="hidden" name="uT" value="y" />
			</form>

			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=corpusSettings&uT=y">
					[Settings]
				</a>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=userAccess&uT=y">
					[Access]
				</a>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=manageMetadata&uT=y">
					[Metadata]
				</a>
			</td>

			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=manageAnnotation&uT=y">
					[Annotation]
				</a>
			</td>

			<td class="concordgeneral" align="center">
				<a class="menuItem"  href="index.php?thisF=deleteCorpus&corpus=<?php echo $r['corpus']?>&uT=y">
					[Delete corpus]
				</a>
			</td>		
		
		</tr>
		<?php
	}
	?></table><?php
	
	/* clean up */
	unset($corpus_sql_name);
}




function printquery_installcorpus_indexed()
{
	global $cwb_registry;
	
	
	?>
	<form action="index.php" method="GET">
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">
					Install a corpus pre-indexed in CWB
				</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br/>
					<a href="index.php?thisF=installCorpus&uT=y">
						Click here to install a completely new corpus from files in the upload area.
					</a>
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify a MySQL name for this corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_mysql_name"  onKeyUp="check_c_word(this)" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Enter the full name of the corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_description" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify the CWB name (lowercase format)</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_cwb_name" onKeyUp="check_c_word(this)" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" rowspan="2">Where is the registry file?</td>
				<td class="concordgeneral">
					<input type="radio" name="corpus_useDefaultRegistry" value="1" checked="checked" />
					In CQPweb's usual registry directory 
					<a class="menuItem" onmouseover="return escape('/<?php echo $cwb_registry; ?>/')">
						[?]
					</a>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<input type="radio" name="corpus_useDefaultRegistry" value="0" />
					In the directory specified here:
					<br/>
					<input type="text" name="corpus_cwb_registry_folder" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Tick here if the main script in the corpus is right-to-left</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_scriptIsR2L" value="1"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Tick here if the corpus is encoded in Latin1 (iso-8859-1)
					<br/>
					<em>
						(note that the character set in CQPweb is assumed to be UTF8 unless otherwise specifed)
					</em> 
				</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_encodeIsLatin1" value="1"/>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br/>
					P-attributes (annotation) are read automatically from the registry file.
					Use "Manage annotation" to add descriptions, tagset names/links, etc. 
					<br/>&nbsp;
				</td>
			</tr>
		<?php printquery_installcorpus_stylesheetrows(); ?>
		</table>
				
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Install corpus</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Install corpus with settings above" />
					<br/>&nbsp;<br/>
					<input type="reset" value="Clear this form" />
				</td>
			</tr>
		</table>
		
		<input type="hidden" name="admFunction" value="installCorpusIndexed" />
		<input type="hidden" name="uT" value="y" />
	</form>
	
	<?php
	
}


function printquery_installcorpus_unindexed()
{
	global $cqpweb_uploaddir;
	
	
	?>
	<form action="index.php" method="GET">
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">
					Install new corpus
				</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br/>
					<a href="index.php?thisF=installCorpusIndexed&uT=y">
						Click here to install a corpus you have already indexed in CWB.</a>
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify the MySQL name of the corpus you wish to create</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_mysql_name" onKeyUp="check_c_word(this)"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify the CWB name of the corpus you wish to create</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_cwb_name" onKeyUp="check_c_word(this)"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Enter the full name of the corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_description" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Tick here if the main script in the corpus is right-to-left</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_scriptIsR2L" value="1"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Tick here if the corpus is encoded in Latin1 (iso-8859-1)
					<br/>
					<em>
						(note that the character set in CQPweb is assumed to be UTF8 unless otherwise specifed)
					</em> 
				</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_encodeIsLatin1" value="1"/>
				</td>
			</tr>
		</table>
		
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="4" class="concordtable">
					Select files
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="4">
					The following files are available (uncompressed) in the upload area. Put a tick next to
					the files you want to index into CWB format.
				</td>
			</tr>
			<tr>
				<th class="concordtable">Include?</th>
				<th class="concordtable">Filename</th>
				<th class="concordtable">Size (K)</th>
				<th class="concordtable">Date modified</th>
			</tr>
			<?php
			$file_list = scandir("/$cqpweb_uploaddir/");
	
			foreach ($file_list as &$f)
			{
				$file = "/$cqpweb_uploaddir/$f";
				
				if (!is_file($file)) continue;
				
				if (substr($f,-3) === '.gz') continue;
	
				$stat = stat($file);
				?>
				
				<tr>
					<td class="concordgeneral" align="center">
						<?php 
						echo '<input type="checkbox" name="includeFile" value="' . urlencode($f) . '" />'; 
						?>
					</td>
					
					<td class="concordgeneral" align="left"><?php echo $f; ?></td>
					
					<td class="concordgeneral" align="right";>
						<?php echo make_thousands(round($stat['size']/1024, 0)); ?>
					</td>
				
					<td class="concordgeneral" align="center">
						<?php echo date('Y-M-d H:i:s', $stat['mtime']); ?>
					</td>		
				</tr>
				<?php
			}
			?>
		</table>
		<table class="concordtable" width="100%">
			<tr>
				<th  colspan="6" class="concordtable">
					Define corpus annotation
				</th>
			</tr>
			<tr>
				<td  colspan="6" class="concordgrey">
					You do not need to specify the <em>word</em> as a P-attribute or the <em>text</em> as
					an S-atribute. Both are assumed and added automatically.
				</td>
			</tr>
			<tr>
				<th colspan="6" class="concordtable">S-attributes (XML elements)</th>
			</tr>
			<tr>
				<td rowspan="6" class="concordgeneral">
					<input type="radio" name="withDefaultSs" value="1" checked="checked"/>
					Use default setup for S-attributes (only &lt;s&gt;)
					<br/>
					<input type="radio" name="withDefaultSs" value="0"/>
					Use custom setup (specify up to 6 attributes in the boxes opposite)
				</td>
				<?php 
				foreach(array(1,2,3,4,5,6) as $q)
				{
					if ($q != 1) echo '<tr>';
					echo "<td colspan=\"5\"align=\"center\" class=\"concordgeneral\">
							<input type=\"text\" name=\"customS$q\"  onKeyUp=\"check_c_word(this)\"/>
						</td>
					</tr>";
				}
				?>

			</tr>
			<tr>
				<th colspan="6" class="concordtable">P-attributes (word tags)</th>
			</tr>
			<tr>
				<td rowspan="7" class="concordgeneral">
					<input type="radio" name="withDefaultPs" value="1" checked="checked"/>
					Use default setup for P-attributes (pos, hw, semtag, class, lemma)
					<br/>
					<input type="radio" name="withDefaultPs" value="0"/>
					Use custom setup (specify up to 6 attributes in the boxes opposite)
				</td>
				<td class="concordgrey" align="center">Primary?</td>
				<td class="concordgrey" align="center">Handle</td>
				<td class="concordgrey" align="center">Description</td>
				<td class="concordgrey" align="center">Tagset</td>
				<td class="concordgrey" align="center">External URL</td>
			</tr>
			<?php 
			foreach(array(1,2,3,4,5,6) as $q)
			{
				echo "<tr>
					<td align=\"center\" class=\"concordgeneral\">
						<input type=\"radio\" name=\"customPPrimary\" value=\"$q\" />
					</td>
					<td align=\"center\" class=\"concordgeneral\">
						<input type=\"text\" maxlength=\"15\" name=\"customPHandle$q\" onKeyUp=\"check_c_word(this)\" />
					</td>
					<td align=\"center\" class=\"concordgeneral\">
						<input type=\"text\" maxlength=\"150\" name=\"customPDesc$q\" />
					</td>
					<td align=\"center\" class=\"concordgeneral\">
						<input type=\"text\" maxlength=\"150\" name=\"customPTagset$q\" />
					</td>
					<td align=\"center\" class=\"concordgeneral\">
						<input type=\"text\" maxlength=\"150\" name=\"customPurl$q\" />
					</td>
				</tr>";
			}
			?>
		</table>
		
		<table class="concordtable" width="100%">
		<?php printquery_installcorpus_stylesheetrows(); ?>
		</table>
				
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Install corpus</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Install corpus with settings above" />
					<br/>&nbsp;<br/>
					<input type="reset" value="Clear this form" />
				</td>
			</tr>
		</table>
		
		<input type="hidden" name="admFunction" value="installCorpus" />
		<input type="hidden" name="uT" value="y" />
	</form>
	
	<?php
}


function printquery_installcorpusdone()
{
	/* addslashes shouldn't be necessary here, but paranoia never hurts */
	$corpus = addslashes($_GET['newlyInstalledCorpus']);
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Your corpus has been successfully installed!
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>You can now:</p>
				<ul>
					<!-- 
					<li>
						<a href="../<?php echo $corpus; ?>/index.php?thisQ=manageMetadata&uT=y">
							Go to the corpus' metadata control page
						</a>
						(to set up frequency lists, add corpus metadata, etc.)
					</li>
					-->
					<li>
						<a href="../<?php echo $corpus; ?>/index.php?thisQ=manageMetadata&uT=y">
							Design and insert a text-metadata table for the corpus
						</a>
						(searches won't work till you do)<br/>
					</li>
					<li>
						<a href="index.php?thisF=installCorpus&uT=y">
							Install another corpus
						</a>
					</li>
				</ul>
				<p>&nbsp;</p>
			</td>
		</tr>
	</table>
	<?php
}

function printquery_installcorpus_stylesheetrows()
{
	?>
	
			<tr>
				<th colspan="2" class="concordtable">Select a stylesheet</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="left">
					<input type="radio" name="cssCustom" value="0" checked="checked"/>
					Choose a built in stylesheet:
				</td>
				<td class="concordgeneral" align="left">
					<select name="cssBuiltIn">
						<?php
							$list = scandir('../css');
							foreach($list as &$l)
							{
								if (substr($l, -4) !== '.css')
									continue;
								else
									echo "<option>$l</option>";
							}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="left">
					<input type="radio" name="cssCustom" value="1" />
					Use the stylesheet at this URL:
				</td>
				<td class="concordgeneral" align="left">
					<input type="text" maxlength="255" name="cssCustomUrl" />
				</td>
			</tr>
	<?php
}



function printquery_deletecorpus()
{
	$corpus = $_GET['corpus'];
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				You have requested deletion of the corpus "<?php echo $corpus; ?>" from the CQPweb system.
			</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center">Are you sure you want to do this?</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form action="index.php" method="get">
					<br/>
					<input type="checkbox" name="sureyouwantto" value="yes"/>
					Yes, I'm sure I want to do this.
					<br/>&nbsp;<br/>
					<input type="submit" value="I am definitely sure I want to delete this corpus." />
					<br/>
					<input type="hidden" name="admFunction" value="deleteCorpus" />
					<input type="hidden" name="corpus" value="<?php echo $corpus; ?>" />
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
	</table>					
		
	<?php
}

function printquery_newupload()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Add a file to the upload area
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Files uploaded to CQPweb can be used as the input to indexing, or as database inputs
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form enctype="multipart/form-data" action="index.php" method="POST">
					<!--  20 Mb maximum -->
					<input type="hidden" name="MAX_FILE_SIZE" value="20000000" /> 
					Choose a file to upload: <input name="uploadedFile" type="file" />
					<br />
					<input type="submit" value="Upload file" />
					<input type="reset"  value="Clear form" />
					<br/>
				</form>
			</td>
		</tr>
	</table>
	<?php
}


function printquery_uploadarea()
{
	global $cqpweb_uploaddir;
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="7" class="concordtable">
				List of files currently in upload area
			</th>
		</tr>
		<tr>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
			<th colspan="4" class="concordtable">Actions</th>
		</tr>
		<?php
		
		$file_list = scandir("/$cqpweb_uploaddir/");
		
		$total_files = 0;
		$total_bytes = 0;

		foreach ($file_list as &$f)
		{
			$file = "/$cqpweb_uploaddir/$f";
			
			if (!is_file($file)) continue;
			
			$file_is_compressed = ( (substr($f,-3) === '.gz') ? true : false);

			$stat = stat($file);
			
			$total_files++;
			$total_bytes += $stat['size'];
			
			echo '';

			?>
			<tr>
			<td class="concordgeneral" align="left"><?php echo $f; ?></td>
			
			<td class="concordgeneral" align="right";>
				<?php echo make_thousands(round($stat['size']/1024, 0)); ?>
			</td>
			
			<td class="concordgeneral" align="center"><?php echo date('Y-M-d H:i:s', $stat['mtime']); ?></td>
			
			<td class="concordgeneral" align="center">
				<?php 
					if ($file_is_compressed)
						echo '&nbsp;';
					else
						echo '<a class="menuItem" href="index.php?admFunction=fileView&filename=' 
							. urlencode($f) . '&uT=y">[View]</a>';
				?>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem" href="index.php?admFunction=<?php 
					if ($file_is_compressed)
					{
						echo 'fileDecompress&filename=' .urlencode($f);
						$compress_label = '[Decompress]';
					}
					else
					{
						echo 'fileCompress&filename=' .urlencode($f);
						$compress_label = '[Compress]';
					}
				?>&uT=y"><?php echo$compress_label; ?></a>
			</td>
			
			<td class="concordgeneral" align="center">
				<?php 
				if ($file_is_compressed)
					echo '&nbsp;';
				else
					echo '<a class="menuItem" href="index.php?admFunction=fileFixLinebreaks&filename=' 
						. urlencode($f) . '&uT=y">[Fix linebreaks]</a>'; 
				?>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem" href="index.php?admFunction=fileDelete&filename=<?php 
					echo urlencode($f);
				?>&uT=y">[Delete]</a>
			</td>
			</tr>
			<?php

		}
		
		echo '<tr><td align="left" class="concordgrey" colspan="7">'
			. $total_files . ' files (' . make_thousands(round($total_bytes/1024, 0)) . ' K)'
			. '</td></tr>';
		
		?>
		
	</table>
	<?php
}




function printquery_useradmin()
{
	global $mysql_link;
	global $cqpweb_uses_apache;
	
	$apache = get_apache_object('nopath');
	
	
	if ($cqpweb_uses_apache)
	{
		$array_of_users = $apache->list_users();
		
		$user_list_as_options = '';
		foreach ($array_of_users as $a)
			$user_list_as_options .= "<option>$a</option>\n";
		
		
		/* before we start, add the javascript function that inserts password cxandidates */
		
		echo print_javascript_for_password_insert('password_insert_lancaster');
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="3" class="concordtable">
					Create new user (or reset user password)
				</th>
			</tr>
			<form action="index.php" method="GET">
				<tr>
					<td class="concordgeneral">
						Enter the username you wish to create/reset:
					</td>
					<td class="concordgeneral">
						<input type="text" name="newUsername" tabindex="1" width="30" onKeyUp="check_c_word(this)" />
					</td>
					<td class="concordgeneral" rowspan="3">
						<input type="submit" value="Create user account" tabindex="5" />
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">
						Enter a new password for the specified user:
					</td>
					<td class="concordgeneral">
						<input type="text" id="passwordField" name="newPassword" tabindex="2" width="30" 
							onKeyUp="check_c_word(this)" />
						<a class="menuItem" tabindex="3"
							onmouseover="return escape('Suggest a password')" onclick="insertPassword()">
							[+]
						</a>
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">
						Enter the user's email address (optional):
					</td>
					<td class="concordgeneral">
						<input type="text" name="newEmail" tabindex="4" width="30" />
					</td>
				</tr>
				<input type="hidden" name="admFunction" value="newUser"/>
				<input type="hidden" name="uT" value="y" />
			</form>
			
			
			
			<tr>
				<th colspan="3" class="concordtable">
					Create a batch of user accounts
				</th>
			</tr>
			<form action="index.php" method="GET">
				<tr>
					<td class="concordgeneral">
						Enter the root for the batch of usernames:
					</td>
					<td class="concordgeneral">
						<input type="text" name="newUsername" width="30" onKeyUp="check_c_word(this)" />
					</td>
					<td class="concordgeneral" rowspan="5">
						<input type="submit" value="Create batch of users" />
						<br/>&nbsp;<br/>
						<input type="reset" value="Clear form" />
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">
						Enter the number of accounts in the batch:
						<br/>
						<em>(Usernames will have the numbers 1 to N appended to them)</em>
					</td>
					<td class="concordgeneral">
						<input type="text" name="sizeOfBatch" width="30" />
					</td>
				<tr>
					<td rowspan="2" class="concordgeneral">
						Enter a password for the users, or assign random passwords automatically:
					</td>
					<td class="concordgeneral">
						<input type="radio" checked="checked" name="newPasswordUseRandom" value="0"/>
						<input type="text" name="newPassword" width="30" onKeyUp="check_c_word(this)" />
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">
						<input type="radio" name="newPasswordUseRandom" value="1"/>
						Assign passwords randomly and report results in text file format
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">
						Enter a group for the new users to be assigned to:
					</td>
					<td class="concordgeneral">
						<input type="text" name="batchAutogroup" width="30" onKeyUp="check_c_word(this)"  />
					</td>
				</tr>
				<input type="hidden" name="admFunction" value="newBatchOfUsers"/>
				<input type="hidden" name="uT" value="y" />
			</form>
			
			
			
			<tr>
				<th colspan="3" class="concordtable">
					Delete a user account
				</th>
			</tr>
			<form action="index.php" method="GET">
				<tr>
					<td class="concordgeneral">
						Select a user to delete:
					</td>
					<td class="concordgeneral">
						<select name="userToDelete">
							<option></option>
							<?php echo $user_list_as_options; ?>
						</select>
					</td>
					<td class="concordgeneral">
						<input type="submit" value="Delete this user's account" />
					</td>
				</tr>
				<input type="hidden" name="admFunction" value="deleteUser"/>
				<input type="hidden" name="uT" value="y" />
			</form>
			<form action="index.php" method="GET">
				<tr>
					<td class="concordgeneral">
						Delete a batch of users - all usernames consisting of this string plus a number:
					</td>
					<td class="concordgeneral">
						<input type="text" name="userBatchToDelete" onKeyUp="check_c_word(this)" />
					</td>
					<td class="concordgeneral">
						<input type="submit" value="Delete all matching users' accounts" />
					</td>
				</tr>
				<input type="hidden" name="admFunction" value="deleteUserBatch"/>
				<input type="hidden" name="uT" value="y" />
			</form>
		</table>
		
		<?php
	}
	/* endif: cqpweb_uses_apache */
	
	?>
	
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="4" class="concordtable">
				Set user's maximum database size
			</th>
		</tr>
		<tr>
			<td colspan="4" class="concordgrey">
				&nbsp;<br/>
				This limit allows you to control the amount of disk space that MySQL operations - such as 
				calculating distributions or collocations - can take up at one go from each user.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable">Username</th>
			<th class="concordtable">Current limit</th>
			<th class="concordtable">New limit</th>
			<th class="concordtable">Update</th>
		</tr>
		<?php
		$sql_query = "SELECT username, max_dbsize from user_settings";
	
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false)
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
		
		while (($r = mysql_fetch_assoc($result)) !== false)
		{
			$limit_options 
				= "<option value=\"{$r['username']}#max_dbsize#100\" selected=\"selected\">100</option>\n";
			for ($n = 100, $i = 1; $i < 8; $i++)
			{
				$n *= 10;
				$w = make_thousands($n);
				$limit_options .= "<option value=\"{$r['username']}#max_dbsize#$n\">$w</option>\n";
			}
			?>
			<form action="index.php" method="get">
				<tr>
					<td class="concordgeneral"><strong><?php echo $r['username'];?></strong></td>
					<td class="concordgeneral" align="center">
						<?php echo make_thousands($r['max_dbsize']); ?>
					</td>
					<td class="concordgeneral" align="center">
						<select name="args">
							<?php echo $limit_options; ?>
						</select>
					</td>
					<td class="concordgeneral" align="center"><input type="submit" value="Go!" /></td>
				</tr>
				<input type="hidden" name="admFunction" value="execute"/>
				<input type="hidden" name="function" value="update_user_setting"/>
				<input type="hidden" name="locationAfter" value="index.php?thisF=userAdmin&uT=y"/>
				<input type="hidden" name="uT" value="y" />
			</form>
			<?php
		}
		?>
		
	</table>

	<?php
}


function printquery_groupadmin()
{
	global $cqpweb_uses_apache;

	if ($cqpweb_uses_apache)
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="7" class="concordtable">
					Manage user groups
				</th>
			</tr>
			<tr>
				<th class="concordtable">Group</th>
				<th class="concordtable">Members</th>
				<th class="concordtable" colspan="2">Add member</th>			
				<th class="concordtable" colspan="2">Remove member</th>	
				<th class="concordtable">Delete</th>
			</tr>
		<?php
		$apache = get_apache_object('nopath');	
		$list_of_groups = $apache->list_groups();
		
		foreach ($list_of_groups as $group)
		{
			echo '<tr>';
			echo '<td class="concordgeneral"><strong>' . $group . '</strong></td>';
	
			$member_list = $apache->list_users_in_group($group);
			sort($member_list);
			echo "\n<td class=\"concordgeneral\">";
			$i = 0;
			foreach ($member_list as &$member)
			{
				echo $member . ' ';
				$i++;
				if ($i == 5)
				{
					echo "<br/>\n";
					$i = 0;
				}
			}
			if (empty($member_list))echo '&nbsp;';
			echo '</td>';
			
			if ($group == 'superusers')
			{
				echo '<td class="concordgeneral" colspan="5">&nbsp;</td>';
				continue;
			}
			
			$members_not_in_group = array_diff($apache->list_users(), $member_list);
			$options = "<option></option>\n";
			foreach ($members_not_in_group as &$m)
				$options .= "<option>$m</option>\n";		
			echo "<form action=\"index.php\" method=\"GET\">
				<td class=\"concordgeneral\" align=\"center\">
				<select name=\"userToAdd\">$options</select></td>\n";
			echo "<td class=\"concordgeneral\" align=\"center\">
				<input type=\"submit\" value=\"Add user to group\" /></td>\n";
			echo "<input type=\"hidden\" name=\"admFunction\" value=\"addUserToGroup\" />
				<input type=\"hidden\" name=\"groupToAddTo\" value=\"$group\" />
				<input type=\"hidden\" name=\"uT\" value=\"y\" /></form>\n";
			
			$options = "<option></option>\n";
			foreach ($member_list as &$m)
				$options .= "<option>$m</option>\n";
			echo "<form action=\"index.php\" method=\"GET\">\n
				<td class=\"concordgeneral\" align=\"center\">
				<select name=\"userToRemove\">$options</select></td>\n";
			echo "<td class=\"concordgeneral\" align=\"center\">
				<input type=\"submit\" value=\"Remove user from group\" /></td>\n";
			echo "<input type=\"hidden\" name=\"admFunction\" value=\"removeUserFromGroup\" />
				<input type=\"hidden\" name=\"groupToRemoveFrom\" value=\"$group\" />
				<input type=\"hidden\" name=\"uT\" value=\"y\" /></form>\n";
			
			$l = '&locationAfter=' . urlencode('index.php?thisF=groupAdmin&uT=y');
			echo "<td class=\"concordgeneral\" align=\"center\">
				<a class=\"menuItem\" href=\"index.php?admFunction=execute&function=delete_group&args=$group$l&uT=y\">
				[x]</a></td>\n";
						
			echo '</tr>';
		}
		?>
		</table>
		
		<table class="concordtable" width="100%">
			<form action="index.php" method="get">
				<tr>
					<th colspan="3" class="concordtable">
						Add new group
					</th>
				</tr>
				<tr>
					<td class="concordgeneral">
						<br/>
						Enter the name for the new group:
						<br/>
						&nbsp;
					</td>
					<td class="concordgeneral" align="center">
						<br/>
						<input type="text" maxlength="20" name="args" onKeyUp="check_c_word(this)" >
						<br/>
						&nbsp;
					<td class="concordgeneral" align="center">
						<br/>
						<input type="submit" value="Add this group to the system"/>
						<br/>
						&nbsp;
					</td>
				</tr>
				<input type="hidden" name="admFunction" value="execute" />
				<input type="hidden" name="function" value="create_group" />
				<input type="hidden" name="locationAfter" value="index.php?thisF=groupAdmin&uT=y" />
				<input type="hidden" name="uT" value="y" />
			</form>
		</table>
		
		<?php
	} /* endif $cqpweb_uses_apache */
	else
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">
					Manage user groups
				</th>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					&nbsp;<br/>
					CQPweb internal group management is not available (requires Apache web server).
					<br/>&nbsp;
				</th>
			</tr>
	
		<?php	
	}
}


function printquery_groupaccess()
{
	global $cqpweb_uses_apache;

	if ($cqpweb_uses_apache)
	{
		/* we're gonna need apache */

		$apache = get_apache_object('nopath');	


		?>
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="7" class="concordtable">
					Manage user groups
				</th>
			</tr>
			<tr>
				<th class="concordtable">Group</th>
				<th class="concordtable">Corpus access rights</th>
				<th class="concordtable">Actions</th>
			</tr>
		<?php
		
		
		/* create a template for a table of tickboxes for each corpus */
		
		$list_of_corpora = list_corpora();
		
		$tableform_of_corpora = '<table width="100%"><tr>';

		$i = 1;
		foreach ($list_of_corpora as $c)
		{
			/* setup the template */
			if ($i == 1)
				$tableform_of_corpora .= '<tr>';	
			
			$tableform_of_corpora .= '<td class="basicbox" width="25%" style="padding:0px">'
				. '<input type="checkbox" name="hasAccessTo_'.$c.'" value="1" __CHECKVALUE__FOR__'.$c.' />&nbsp;'.$c 
				. '</td>';
			if ($i == 4)
			{
				$tableform_of_corpora .= "</tr>\n\n\n";	
				$i = 1;
			}
			else
				$i++;
			
			/* and get the list of groups that has access to that corpus */
			$apache->set_path_to_web_directory("../$c");
			$apache->load();
			$corpus_access_rights[$c] = $apache->get_allowed_groups();
		}
		if ($i > 1)
		{
			/* ie, if we are mid-tr */
			while ($i <= 4)
			{
				$tableform_of_corpora .= '<td class="basicbox" width="25%" style="padding:0px">&nbsp;</td>';
				$i++;
			}
		}
		
		$tableform_of_corpora .= '</tr></table>'; 
		
		/* OK, now render a form for each group showing the current access 
		 * rights and allowing changes to be made */
		
		$list_of_groups = $apache->list_groups();

		foreach ($list_of_groups as $group)
		{
			?>
			
			<form action="index.php" method="get">
				<tr>
					<td class="concordgeneral"><strong><?php echo $group; ?></strong></td>
					
					<td class="concordgeneral">
						
						<?php 
						
						if ($group == "superusers")
							echo "<center>Superusers always have access to everything.";
						else
						{
							foreach($list_of_corpora as $c)
								$translations["__CHECKVALUE__FOR__$c"] 
									= (in_array($group, $corpus_access_rights[$c]) ? 'checked="checked"' : '');
							
							echo strtr($tableform_of_corpora, $translations); 
						}
						?>
						
					</td>
					
					<td class="concordgeneral" align="center">
						<input type="submit" value="Update" />
					</td>
				</tr>
				<input type="hidden" name="admFunction" value="accessUpdateGroupRights" />
				<input type="hidden" name="group" value="<?php echo $group; ?>" />
				<input type="hidden" name="uT" value="y" />
			</form>
						
			<?php
		}
		?>
		</table>
		
		
		
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="3" class="concordtable">
					Access right cloning
				</th>
			</tr>
			
			<tr>
				<td colspan="3" class="concordgrey">
					&nbsp;<br/>
					IF you "clone" access rights from Group A to Group B, you overwrite all the current access
					rights of Group B; it will have exactly the same privilenges as Group A.
					<br/>&nbsp;
					
					<br/><b>DOES NOT WORK YET!!!!!!</b>
				</td>
			</tr>
			
			<?php
			
			$clone_group_options = '';
			foreach ($list_of_groups as $group)
			{
				if ($group == 'superusers')
					continue;
				$clone_group_options .= "<option>$group</option>\n";
			}
			
			?>
			
			<form action="" method="get">
			
				<tr>
					<td class="concordgeneral">
						&nbsp;<br/>
						Clone from:
						<select name="groupCloneFrom">
							<?php echo $clone_group_options; ?>
						</select>
						<br/>&nbsp;
					</td>
					<td class="concordgeneral">
						&nbsp;<br/>
						Clone to:
						<select name="groupCloneTo">
							<?php echo $clone_group_options; ?>
						</select>
						<br/>&nbsp;
					</td>
					<td class="concordgeneral" align="center">
						&nbsp;<br/>
						<input type="submit" value="Clone access rights!" />
						<br/>&nbsp;
					</td>
				</tr>
			
			</form>
					 
		</table>		
			
	<?php
		
		
		
	} /* endif $cqpweb_uses_apache */
	else
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">
					User groups: access rights
				</th>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					&nbsp;<br/>
					CQPweb internal group management is not available (requires Apache web server).
					<br/>&nbsp;
				</th>
			</tr>
	
		<?php	
	}
}


function printquery_superuseraccess()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Manage superuser access
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>This setting cannot be changed over the web.</p>
				<p>You must change it manually, by editing the file <em>defaults.inc.php</em>.</p>
				<p>&nbsp;</p>
				<p>This is for security reasons - superusers can potentially break the system.</p>
				<p>&nbsp;</p>
			</td>
		</tr>
	</table>
	<?php
}


function printquery_skins()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">
				Skins and colour schemes
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="4">
				&nbsp;<br/>
				Use the button below to re-generate built-in colour schemes:
				<br/>
				<form action="index.php" method="GET">
					<center>
						<input type="submit" value="Regenerate colour schemes!" />
					</center>
					<input type="hidden" name="admFunction" value="regenerateCSS"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="4">
				&nbsp;<br/>
				Listed below are the CSS files currently present in the upload area which do <em>not</em> already appear in
				the main <em>css</em> directory. Select a file and click &ldquo;Import!&rdquo; 
				to create a copy of the file in the <em>css</em> directory.  
				<br/>&nbsp;
			</td>
		<tr>
			<th class="concordtable">Transfer?</th>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>
		<form action="index.php" method="GET">
			<?php
			global $cqpweb_uploaddir;
			$file_list = scandir("/$cqpweb_uploaddir/");
			
			foreach ($file_list as &$f)
			{
				$file = "/$cqpweb_uploaddir/$f";
				$target = "../css/$f";
				
				if (!is_file($file)) continue;	
				if (substr($f,-4) !== '.css') continue;
				if (is_file($target)) continue;
	
				$stat = stat($file);
				?>
				
				<tr>
					<td class="concordgeneral" align="center">
						<?php 
						echo '<input type="radio" name="cssFile" value="' . urlencode($f) . '" />'; 
						?>
					</td>
					
					<td class="concordgeneral" align="left"><?php echo $f; ?></td>
					
					<td class="concordgeneral" align="right";>
						<?php echo make_thousands(round($stat['size']/1024, 0)); ?>
					</td>
				
					<td class="concordgeneral" align="center">
						<?php echo date('Y-M-d H:i:s', $stat['mtime']); ?>
					</td>		
				</tr>
				<?php
			}
			?>
			<tr>
				<td class="concordgrey" align="center" colspan="4">
					&nbsp;<br/>
					<input type="submit" value="Transfer" />
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="transferStylesheetFile" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	<?php
}



function printquery_mappingtables()
{
	$show_existing = (bool)$_GET['showExisting']
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="3">
				Mapping tables
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br/>
				
				&ldquo;Mapping tables&rdquo; are used in the Common Elementary Query Language (CEQL)
				system (aka &ldquo;Simple query&rdquo;).
				
				<br/>&nbsp;<br/>
				
				They transform <em>the tag the user searches for</em> (referred to as an 
				<strong>alias</strong>) into <em>the tag that actually occurs in the corpus</em>, or 
				alternatively into <em>a regular expression covering a group of tags</em> (referred to
				as the <strong>search term</strong>.
				
				<br/>&nbsp;<br/>
				
				Each alias-to-search-term mapping has the form "ALIAS" => "SEARCH TERM".  
					
				<br/>&nbsp;<br/>
				
				<?php
				
				echo '<a href="index.php?thisF=mappingTables&showExisting='
					. ($show_existing ? '0' : '1')
					. '&uT=y">Click here '
					. ($show_existing ? 'to add a new mapping table' : 'to view all stored mapping tables')
					. "</a>.\n\n";
				?>
				<br/>&nbsp;
			</td>
		</tr>
		<?php
		if ($show_existing)
		{
			/* show existing mapping tables */
			?>
			<tr>
				<th class="concordtable" colspan="3">
					Currently stored mapping tables
				</th>
			</tr>
			<tr>
				<th class="concordtable">Name</th>
				<th class="concordtable">Mapping table</th>
				<th class="concordtable">Actions</th>
			</tr>
			
			<?php
			foreach(get_all_tertiary_mapping_tables() as $table)
			{
				echo '<tr>'
					. '<td class="concordgeneral">' . $table->name . ' <br/>&nbsp;<br/>(<em>' . $table->id . '</em>)</td>'
					. '<td class="concordgeneral"><font size="-2" face="courier new, monospace">' 
					. strtr($table->mappings, array("\n"=>'<br/>', "\t"=>'&nbsp;&nbsp;&nbsp;') )
					. '</font></td>'
					. '<td class="concordgeneral" align="center">'
					. '<a class="menuItem" href="index.php?admFunction=execute&function=drop_tertiary_mapping_table&args=' 
					. $table->id . '&locationAfter=' . urlencode('index.php?thisF=mappingTables&showExisting=1&uT=y') 
					. '&uT=y">[Delete]</a></td>'
					. "</tr>\n\n";	
			}

		}
		else
		{
			/* main page for adding new mapping tables */
			?>
			<tr>
				<th class="concordtable" colspan="3">
					Create a new mapping table
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="3">
					Your mapping table must start and end in a brace <strong>{ }</strong> ; each 
					alias-to-search-term mapping but the last must be followed by a comma. 
					Use perl-style escapes for quotation marks where necessary.
					
					<br/>&nbsp;<br/>
					
					You are strongly advised to save an offline copy of your mapping table,
					as it is a lot of work to recreate if it accidentally gets deleted from
					the database.
				</td>
			</tr>
			<form action="index.php" method="get">
				<tr>
					<td class="concordgeneral" align="center" valign="top">
						Enter an ID code
						<br/> 
						(letters, numbers, and _ only)
						<br/>&nbsp;<br/>
						<input type="text" size="30" name="newMappingTableId" onKeyUp="check_c_word(this)" />
					</td>
					<td class="concordgeneral" align="center" valign="top">
						Enter the name of the mapping table:
						<br/>&nbsp;<br/>&nbsp;<br/>
						<input type="text" size="30" name="newMappingTableName"/>
					</td>
					<td class="concordgeneral" align="center" valign="top">
						Enter the mapping table code here:
						<br/>&nbsp;<br/>&nbsp;<br/>
						<textarea name="newMappingTableCode" cols="60" rows="25"></textarea>					
					</td>				
				</tr>
				<tr>
					<td class="concordgeneral" colspan="3" align="center">
						<input type="submit" value="Create mapping table!"/>
					</td>				
				</tr>
				<input type="hidden" name="admFunction" value="newMappingTable" />
				<input type="hidden" name="uT" value="y" />
			</form>
			
			
			
			<?php
		}
		?>
		<tr>
			<th class="concordtable" colspan="3">
				Built-in mapping tables
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="3" align="center">
				CQPweb contains a number of built-in mapping tables, including the Oxford Simplified Tagset 
				devised for the BNC (highly recommended).
				<br/>&nbsp;<br/>
				Use the button below to insert them into the database.
				<br/>&nbsp;<br/>

				<form action="index.php" method="get">
					<input type="submit" value="Click here to regenerate built-in mapping tables."/>
					<br/>
					<input type="hidden" name="admFunction" value="execute" />
					<input type="hidden" name="function" value="regenerate_builtin_mapping_tables" />
					<input type="hidden" name="locationAfter" 
						value="index.php?thisF=mappingTables&showExisting=1&uT=y" />
					<input type="hidden" name="uT" value="y" />
				</form>					
			</td>
		</tr>
	</table>
	<?	
}




function printquery_systemsettings()
{
	echo "lah lah!";
}



function printquery_systemsecurity()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				CQPweb system security
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				Use the button below to restore default security to the built-in system subdirectories of
				the CQPweb base directory.
				<br/>&nbsp;<br/>
				These directories are: <strong>adm</strong> (only superusers can access), <strong>lib</strong>
				(no one can access), and <strong>css</strong> and <strong>doc</strong> (totally open: don't 
				require login).
				<br/>&nbsp;<br/>
				.htaccess files in these directories will be replaced / deleted.
				<br/>&nbsp;<br/>
				Use the <em>manage access</em> links under <em>Show corpora</em> to manage security for 
				individual corpus directories.
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form action="index.php" method="get">
					<br/>
					<input type="submit" value="Restore default security!" />
					<br/>
					<input type="hidden" name="admFunction" value="resetSystemSecurity"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
	</table>
	<?
}




function printquery_mysqlsystemrestore()
{
	if ($_GET['mysql_restore_areyousure'] == 'yesimsure'
		&& $_GET['mysql_restore_reallyreallysure'] == 'yesimcertain')
	{
		cqpweb_mysql_total_reset();
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">
					Done!
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					MySQL system restore complete.
				</td>
			</tr>
		</table>
		<?
	}
	else
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">
					MySQL system restore
				</th>
			</tr>
			<form action="index.php" method="get">
				<tr>
					<td class="concordgeneral">
						<center>
							<strong>
								Running this function will delete the CQPweb database and reinstall it.
							</strong>
						</center>
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">
						<center>
							Are you sure you want to do this?
							<br/>&nbsp;<br/>
							<em>Yes I'm sure!</em>
							<input type="checkbox" name="mysql_restore_areyousure" value="yesimsure" />
							<br/>&nbsp;<br/>							
							Are you really really sure?
							<input type="radio" name="mysql_restore_reallyreallysure" value="yesimcertain" /> 
							<em>Yes</em>
							<input type="radio" name="mysql_restore_reallyreallysure" value="no" selected="selected" /> 
							<em>No</em>
							<br/>&nbsp;<br/>
							<input type="submit" value="Delete and reinstall the CQPweb MySQL database" />
							&nbsp;&nbsp;
							<input type="reset" value="Clear form" />
							<br/>&nbsp;
						</center>
					</td>
				</tr>
				<input type="hidden" name="thisF" value="mysqlRestore"/>
				<input type="hidden" name="uT" value="y" />
			</form>
		</table>
		<?
	}
}


function printquery_systemannouncements()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Add a system message
			</th>
		</tr>
		<form action="index.php" method="get">
			<tr>
				<td class="concordgeneral">
					<center>
						<strong>Heading:</strong>
						<input type="text" name="systemMessageHeading" size="90" maxlength="100"/>
					</center>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<center>
						<textarea name="systemMessageContent" rows="5" cols="65" 
							wrap="physical" style="font-size: 16px;"></textarea>
						<br/>&nbsp;<br/>
						<input type="submit" value="Add system message" />
						&nbsp;&nbsp;
						<input type="reset" value="Clear form" />
						<br/>&nbsp;
					</center>
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="addSystemMessage"/>
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	
	<?php
	display_system_messages();
}



function printquery_tableview()
{
	global $mysql_link;
	
	if (isset($_GET['limit']) && strlen($_GET['limit']) > 0 )
		$limit = mysql_real_escape_string($_GET['limit']);
	else
		$limit = "NO_LIMIT";

	
	if(isset($_GET['table']))
	{
		/* a table has already been chosen */
		
		?>
		<table class="concordtable" width="100%">
		
			<tr>
				<th class="concordtable">Viewing mySQL table
				<?php echo $_GET['table']; ?>
				</th>
			</tr>
			
		<tr><td class="concordgeneral">
		
		<?php
		
		$table = mysql_real_escape_string($_GET['table']);
		$sql_string = "SELECT * FROM $table";		
		if ($limit != "NO_LIMIT")
			$sql_string .= " LIMIT $limit";
		
		$result = mysql_query($sql_string, $mysql_link);
		if ($result == false) 
		{
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
			exit(1);
		}
		
		/* print column headers */
		echo '<table class="concordtable"><tr>';
		for ( $i = 0 ; $i < mysql_num_fields($result) ; $i++ )
			echo "<th class='concordtable'>" . mysql_field_name($result, $i)
				. "</th>";
		echo '</tr>';
		
		/* print rows */
		while ( ($row = mysql_fetch_row($result)) != FALSE )
		{
			echo "<tr>";
			foreach ($row as $r)
				echo "<td class='concordgeneral'>$r</td>\n";
			echo "</tr>\n";	
		}
		
		echo '</table>';
	}
	else
	{
		/* no table has been chosen */
		
		$sql_string = "SHOW TABLES";
		
		$result = mysql_query($sql_string, $mysql_link);
		if ($result == false) 
		{
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
			exit(1);
		}

		?>
		<table class="concordtable" width="100%">
		
			<tr>
				<th class="concordtable">View a mySQL table</th>
			</tr>

		<tr><td class="concordgeneral">
		
			<form action="index.php" method="get"> 
				<input type="hidden" name="thisF" value="tableView"/>

				<table><tr>
				<td class="basicbox">Select table to show:</td>
				
				<td class="basicbox">
					<select name="table">

		<?php
			while ( ($row = mysql_fetch_row($result)) != FALSE )
				echo "<option value='$row[0]'>$row[0]</option>\n";
		?>
					</select>
				</td></tr>
				
				<tr><td class="basicbox">Optionally, enter a LIMIT:</td>
				
				<td class="basicbox">
					<input type="text" name="limit" />
				</td></tr>
				<tr><td class="basicbox">&nbsp;</td>

				<td class="basicbox">
					<!-- this input ALWAYS comes last -->
					<input type="hidden" name="uT" value="y"/>
					<input type="submit" value="Show table"/>
				</td></tr>
				</table>

			</form>

		<?php
	}

	?></td></tr></table><?php
}

// currently just dumps the table to the page.
// we also want options to kill, etc.
// and ideally delete any associated temp files if their names can be worked out.
// TODO : this, properly!
function printquery_mysqlprocesses()
{
	$result= do_mysql_query('SELECT * from mysql_processes');
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">Viewing running mySQL processes</th>
		</tr>
		<tr>
			<th class="concordtable" >Database being created</th>
			<th class="concordtable" >Time process began</th>
			<th class="concordtable" >Process type</th>
			<th class="concordtable" >Process ID</th>
		</tr>
		<?php
		while (($process = mysql_fetch_object($result)) !== false)
		{
			echo '<tr>'
				. '<td class="concordgeneral">' . $process->dbname . '</td>'
				. '<td class="concordgeneral">' . date(DATE_RSS, $process->begin_time) . '</td>'
				. '<td class="concordgeneral">' . $process->process_type . '</td>'
				. '<td class="concordgeneral">' . $process->process_id . '</td>'
				. '</tr>';	
		}
		?>
	</table>
	<?php
}



function printquery_statistic($type = 'user')
{
	global $mysql_link;
	global $default_history_per_page;

	/* note usage of the same system of "perpaging" as the "Query History" function */
	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	switch($type)
	{
	case 'user':
		$bigquery = 'select user, count(*) as c from query_history 
			group by user order by c desc';
		$colhead = 'Username';
		$pagehead = 'for user accounts';
		break;
	case 'corpus':
		$bigquery = 'select corpus, count(*) as c from query_history 
			group by corpus order by c desc';
		$colhead = 'Corpus';
		$pagehead = 'for corpora';
		break;
	case 'query':
		$bigquery = 'select cqp_query, count(*) as c from query_history 
			group by cqp_query order by c desc';
		$colhead = 'Query';
		$pagehead = 'for particular query strings';
		break;
	}
	
	$result = mysql_query($bigquery);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	?>
	<table width="100%" class="concordtable">
		<tr>
			<th colspan="3" class="concordtable">Usage statistics <?php echo $pagehead;?></th>
		</tr>
		<tr>
			<th class="concordtable" width="10%">No.</th>
			<th class="concordtable" width="60%"><?php echo $colhead; ?></th>
			<th class="concordtable" width="30%">No. of queries</th>
		</tr>
		<?php
		
		$toplimit = $begin_at + $per_page;
		$alt_toplimit = mysql_num_rows($result);

		if (($alt_toplimit + 1) < $toplimit)
			$toplimit = $alt_toplimit + 1;
			
		for ( $i = 1 ; $i < $toplimit ; $i++ )
		{
			$row = mysql_fetch_row($result);
			if (!$row)
				break;
			if ($i < $begin_at)
				continue;

			echo "<tr>\n";
			echo '<td class="concordgeneral" align="center">' . "$i</td>\n";
			echo '<td class="concordgeneral" align="left">' . "{$row[0]}</td>\n";
			echo '<td class="concordgeneral" align="center">' . make_thousands($row[1]) . "</td>\n";
			echo "</tr>\n";
		}
		
		?>
	</table>
	<?php

	$navlinks = '<table class="concordtable" width="100%"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{	
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Move up the list]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysql_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Move down the list] &gt;&gt;';
	if (mysql_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;
}


function printquery_phpconfig()
{
	if ($_GET['showPhpInfo'])
	{
		phpinfo();
		return;
	}
	?>

	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				Internal PHP settings relevant to CQPweb
			</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgrey" align="center">
				&nbsp;<br/>
				To see the full phpinfo() dump, 
					<a href="index.php?thisF=phpConfig&showPhpInfo=1&uT=y">click here</a>.
				<br/>&nbsp;	
			</td>
		</tr>
		
		<tr>
			<th colspan="2" class="concordtable">
				General
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				PHP version
			</td>
			<td class="concordgeneral">
				<?php echo phpversion(); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Location of INI file
			</td>
			<td class="concordgeneral">
				<?php echo php_ini_loaded_file(); ?>
			</td>
		</tr>
		
		<tr>
			<th colspan="2" class="concordtable">
				Magic quotes
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Magic quotes for GET, POST, COOKIE
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('magic_quotes_gpc') ? 'On' : 'Off'; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Magic quotes at runtime
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('magic_quotes_runtime')? 'On' : 'Off'; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Magic quotes sybase mode
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('magic_quotes_sybase')? 'On' : 'Off'; ?>
			</td>
		</tr>




		<tr>
			<th colspan="2" class="concordtable">
				Memory and runtime
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				PHP's memory limit
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('memory_limit'); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum script running time 
				<br/>
				<em>(turned off by some scripts)</em>
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('max_execution_time'); ?> seconds
			</td>
		</tr>




		<tr>
			<th colspan="2" class="concordtable">
				File uploads
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				File uploads enabled
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('file_uploads')? 'On' : 'Off'; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Temporary upload directory
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('upload_tmp_dir') ; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum upload size
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('upload_max_filesize'); ?>
			</td>
		</tr>



		<tr>
			<th colspan="2" class="concordtable">
				MySQL
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Client API version
			</td>
			<td class="concordgeneral">
				<?php echo mysql_get_client_info(); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Socket on localhost
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('mysql.default_socket'); ?>
			</td>
		</tr>

	</table>
	
	<?php
}


?>