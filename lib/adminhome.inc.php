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
 *
 * @file
 *
 * adminhome.inc.php: this file contains the code that structures the HTML of the admin control panel.
 *
 *
 */

/* ------------ *
 * BEGIN SCRIPT *
 * ------------ */


/* first, process the various "actions" that the admin interface may be asked to perform */
require('../lib/admin-execute.inc.php');
/*
 * note that the execute actions are zero-environment: they call execute.inc.php
 * which builds an environment, then calls a function, then exits.
 */

require('../lib/environment.inc.php');


/* include function library files */
require('../lib/library.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/admin-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/metadata.inc.php');
require('../lib/ceql.inc.php');
require('../lib/cqp.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/templates.inc.php');

/* and include, especially, the interface forms for this screen */
require('../lib/indexforms-adminhome.inc.php');


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP | CQPWEB_STARTUP_DONT_CHECK_URLTEST | CQPWEB_STARTUP_CHECK_ADMIN_USER, RUN_LOCATION_ADM);





/* thisF: the function whose interface page is to be displayed on the right-hand-side. */
$thisF = ( isset($_GET["thisF"]) ? $_GET["thisF"] : 'showCorpora' );



echo print_html_header('CQPweb Admin Control Panel', $Config->css_path, array('cword', 'corpus-name-highlight', 'attribute-embiggen'));

?>

<body>

<table class="concordtable" width="100%">
	<tr>
		<td valign="top">
            <?php print_admin_menu(); ?>
        </td>
		<td width="100%" valign="top">

            <h1 class="page-title">CQPweb Admin Control Panel</h1>

<?php




/* ****************** */
/* PRINT MAIN CONTENT */
/* ****************** */





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

case 'annotationTemplates':
	printquery_annotationtemplates();
	break;

case 'metadataTemplates':
	printquery_metadatatemplates();
	break;

case 'xmlTemplates':
	printquery_xmltemplates();
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

case 'systemMessages':
	printquery_systemannouncements();
	break;

case 'mappingTables':
	printquery_mappingtables();
	break;

case 'cacheControl':
	printquery_cachecontrol();
	break;

case 'manageProcesses':
	printquery_systemprocesses();
	break;

case 'tableView':
	printquery_tableview();
	break;

case 'phpConfig':
	printquery_phpconfig();
	break;

case 'opcodeCache':
	printquery_opcodecache();
	break;

case 'systemSnapshots':
	printquery_systemsnapshots();
	break;

case 'systemDiagnostics':
	printquery_systemdiagnostics();
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

/* ------------- *
 * END OF SCRIPT *
 * ------------- */




?>
