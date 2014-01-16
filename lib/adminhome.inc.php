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

/* adminhome.inc.php: this file contains the code that renders the various admin function controls */

/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */


/* first, process the various "actions" that this script may be asked to perform */
require('../lib/admin-execute.inc.php');
/* note that the execute actions are zero-environment: they call either execute.inc.php 
 * or admin-do.inc.php, both of which build an environment, then redirect somewhere */

require('../lib/environment.inc.php');


/* include function library files */
require("../lib/library.inc.php");
require('../lib/html-lib.inc.php');
require("../lib/admin-lib.inc.php");
require("../lib/exiterror.inc.php");
require("../lib/metadata.inc.php");
require("../lib/ceql.inc.php");
require("../lib/cqp.inc.php");
require("../lib/user-lib.inc.php");

/* and include, especially, the interface forms for this screen */
require("../lib/indexforms-adminhome.inc.php");


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP | CQPWEB_STARTUP_DONT_CHECK_URLTEST | CQPWEB_STARTUP_CHECK_ADMIN_USER, RUN_LOCATION_ADM);





/* thisF: the function whose interface page is to be displayed on the right-hand-side. */
$thisF = ( isset($_GET["thisF"]) ? $_GET["thisF"] : 'showCorpora' );





/* before anything else... */
header('Content-Type: text/html; charset=utf-8');


// TODO move the raw .js out of here into a file. And use the html function.
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>CQPweb Admin Control Panel</title>
<link rel="stylesheet" type="text/css" href="<?php echo $Config->css_path ;?>" />
<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script>

<!-- nonstandard header includes javascript for doodads specific to the admin-interface -->
<script type="text/javascript">
<!--
// functions for corpus highlighting in main table
function corpus_box_highlight_on(corpus)
{
	document.getElementById("corpusCell_"+corpus).className = "concorderror";
}
function corpus_box_highlight_off(corpus)
{
	document.getElementById("corpusCell_"+corpus).className = "concordgeneral";
}
// functions for adding extra lines to the "install corpus" forms
function add_s_attribute_row()
{
	var number = document.getElementById('s_instruction_cell').rowSpan + 1;
	document.getElementById('s_instruction_cell').rowSpan = number.toString();

	var theTr = document.createElement('tr');
	var theTd = document.createElement('td');
	var theIn = document.createElement('input');
	
	theTd.setAttribute('colspan','5');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('name','customS'+number);
	theIn.setAttribute('onKeyUp','check_c_word(this)');
	
	theTr.appendChild(theTd);
	theTd.appendChild(theIn);
	
	document.getElementById('s_att_row_1').parentNode.insertBefore(theTr,
		document.getElementById('p_att_header_row'));
}
function add_p_attribute_row()
{
	var number = document.getElementById('p_instruction_cell').rowSpan;
	var newRowSpan = number + 1;
	document.getElementById('p_instruction_cell').rowSpan = newRowSpan.toString();

	var theTr = document.createElement('tr');

	var theTd = document.createElement('td');
	var theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','radio');
	theIn.setAttribute('name','customPPrimary');
	theIn.value = number;
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','15');
	theIn.setAttribute('name','customPHandle'+number);
	theIn.setAttribute('onKeyUp','check_c_word(this)');
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name','customPDesc'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name','customPTagset'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name','customPurl'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	document.getElementById('p_att_row_1').parentNode.appendChild(theTr);
}
//-->
</script>
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

<?php
echo print_menurow_heading('Corpora');
echo print_menurow_admin('showCorpora', 'Show corpora');
echo print_menurow_admin('installCorpus', 'Install new corpus');
echo print_menurow_admin('manageCorpusCategories', 'Manage corpus categories');
echo print_menurow_admin('publicTables', 'Public frequency lists');

echo print_menurow_heading('Uploads');
echo print_menurow_admin('newUpload', 'Upload a file');
echo print_menurow_admin('uploadArea', 'View upload area');

echo print_menurow_heading('Users and privileges');
echo print_menurow_admin('userAdmin', 'Manage users');
echo print_menurow_admin('groupAdmin', 'Manage groups');
echo print_menurow_admin('groupMembership', 'Manage group membership');
echo print_menurow_admin('privilegeAdmin', 'Manage privileges');
echo print_menurow_admin('userGrants', 'Manage user grants');
echo print_menurow_admin('groupGrants', 'Manage group grants');

echo print_menurow_heading('Database');
echo print_menurow_admin('manageProcesses', 'Manage MySQL processes');
echo print_menurow_admin('tableView', 'View a MySQL table');
echo print_menurow_admin('mysqlRestore', 'Reset MySQL database');

echo print_menurow_heading('System');
echo print_menurow_admin('systemSettings', 'System settings');
echo print_menurow_admin('systemMessages', 'System messages');
echo print_menurow_admin('systemSecurity', 'System security');
echo print_menurow_admin('systemSnapshots', 'System snapshots');
echo print_menurow_admin('systemDiagnostics', 'System diagnostics');

echo print_menurow_heading('Misc');
?>

<tr>
	<td class="concordgeneral">
		<a class="menuItem" href="../"
			onmouseover="return escape('Go to a list of all corpora on the CQPweb system')">
			CQPweb main menu
		</a>
	</td>
</tr>
<?php
echo print_menurow_admin('skins', 'Skins and colours');
echo print_menurow_admin('mappingTables', 'Mapping tables');
echo print_menurow_admin('cacheControl', 'Cache control');
echo print_menurow_admin('phpConfig', 'PHP configuration');

echo print_menurow_heading('Usage Statistics');
echo print_menurow_admin('corpusStatistics', 'Corpus statistics');
echo print_menurow_admin('userStatistics', 'User statistics');
echo print_menurow_admin('queryStatistics', 'Query statistics');
echo print_menurow_admin('advancedStatistics', 'Advanced statistics');

?>
</table>

		</td>
		<td valign="top">
		
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable">
			CQPweb Admin Control Panel
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

case 'manageCorpusCategories':
	printquery_corpuscategories();
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

case 'groupMembership':
	printquery_groupmembership();
	break;

case 'privilegeAdmin':
	printquery_privilegeadmin();
	break;

case 'userGrants':
	printquery_usergrants();
	break;

case 'groupGrants':
	printquery_groupgrants();
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

case 'systemSnapshots':
	printquery_systemsnapshots();
	break;

case 'systemDiagnostics':
	printquery_systemdiagnostics();
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
	printquery_systemprocesses();
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
	
case 'advancedStatistics':
	printquery_advancedstats();
	break;

/* special option for printing a message shown via GET */
case 'showMessage':
	printquery_message();
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

echo print_html_footer();

cqpweb_shutdown_environment();

/* ------------- */
/* END OF SCRIPT */
/* ------------- */




?>