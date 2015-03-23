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


/* BEFORE WE INCLUDE ANY FILES: if _GET[userC] is set, then we need to make this script behave like queryhome */
if (isset($_GET['userC']))
{
	require("../lib/queryhome.inc.php");
	exit;
}
/* otherwise, go on and render userhome. */


/* initialise variables from settings files  */
require('../lib/environment.inc.php');

/* include function library files */
require('../lib/library.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/colloc-lib.inc.php');
require('../lib/indexforms-user.inc.php');
require('../lib/indexforms-others.inc.php');

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP | CQPWEB_STARTUP_DONT_CHECK_URLTEST, RUN_LOCATION_USR);




/* thisQ: the query whose interface page is to be displayed on the right-hand-side. */
$thisQ = ( isset($_GET["thisQ"]) ? $_GET["thisQ"] : 'welcome' );


echo print_html_header('CQPweb User Page', $Config->css_path, array('cword'));


/* ******************* *
 * PRINT SIDE BAR MENU *
 * ******************* */
?>
<!-- main table -->
<table class="concordtable" width="100%">
	<tr>
		<td valign="top">
            <?php print_menu(); ?>
		</td>
		<td valign="top" width="100%">

<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable">
			<a class="menuHeaderItem">
				CQPweb User Page
			</a>
		</th>
	</tr>
</table>



<?php




/* ******************************* */
/* PRINT SELECTED FUNCTION CONTENT */
/* ******************************* */

/*
 * Note: we need to have two wholly disjunct sets here, one if a user is logged in, and one if they are not.
 */

if ($User->logged_in)
{
	switch($thisQ)
	{
	case 'welcome':
		printscreen_welcome();
		display_system_messages();
		break;

	case 'userSettings':
		printscreen_usersettings();
		break;

	case 'userMacros':
		printscreen_usermacros();
		break;

	case 'corpusAccess':
		printscreen_corpusaccess();
		break;

	case 'userDetails':
		printscreen_userdetails();
		break;

	case 'changePassword':
		printscreen_changepassword();
		break;

	case 'userLogout':
		printscreen_logout();
		break;

	/* common cases... */

	case 'accessDenied':
		printscreen_accessdenied();
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
			We are sorry, but that is not a valid menu option.
		</p>
		<?php
		break;
	}
} /* endif a user is logged in */

else
{
	switch($thisQ)
	{
	case 'login':
		printscreen_login();
		display_system_messages();
		break;

	case 'create':
		printscreen_create();
		break;

	case 'verify':
		printscreen_verify();
		break;

	case 'resend':
		printscreen_resend();
		break;

	case 'lostUsername':
		printscreen_lostusername();
		break;

	case 'lostPassword':
		printscreen_lostpassword();
		break;



	/* common cases... (repeated code from above) */

	case 'accessDenied':
		printscreen_accessdenied();
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
			We are sorry, but that is not a valid menu option.
		</p>
		<?php
		break;
	}

} /* endif no user is logged in */



/* finish off the page */
?>

		</td>
	</tr>
</table>
<?php

echo print_html_footer();

cqpweb_shutdown_environment();

exit();


/* ************* *
 * END OF SCRIPT *
 * ************* */



?>
