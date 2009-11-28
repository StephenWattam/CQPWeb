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









// this shouldn't be a script on its own - should be subcorpus-admin as one of the functions 

/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */

/* initialise variables from settings files  */

require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include function library files */
require ("../lib/library.inc.php");
require ("../lib/freqtable.inc.php");
require ("../lib/freqtable-cwb.inc.php");
require ("../lib/exiterror.inc.php");
require ("../lib/metadata.inc.php");
require ("../lib/subcorpus.inc.php");
require ("../lib/db.inc.php");

/* and because I'm using the next two modules I need to... */
//create_pipe_handle_constants();
require ("../lib/cwb.inc.php");
require ("../lib/cqp.inc.php");



if (!url_string_is_valid())
	exiterror_bad_url();





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

/* connect to CQP */
$cqp = new CQP;

/* select an error handling function */
$cqp->set_error_handler("exiterror_cqp");

/* set CQP's temporary directory */
$cqp->execute("set DataDirectory '/$cqp_tempdir'");

/* select corpus */
//$cqp->execute("$corpus_cqp_name;");
$cqp->set_corpus($corpus_cqp_name);
/* note that corpus must be (RE)SELECTED after calling "set DataDirectory" */




/* get "get" settings */

/* subcorpus for which to create frequency lists */

if (isset($_GET['compileSubcorpus']))
	$sc_to_compile = mysql_real_escape_string($_GET['compileSubcorpus']);
else
	exiterror_parameter('Critical parameter "compileSubcorpus" was not defined!', __FILE__, __LINE__);









/* do it */
subsection_make_freqtables($sc_to_compile);







/* disconnect CQP child process using destructor function */
$cqp->disconnect();

/* disconnect mysql */
mysql_close($mysql_link);









/* redirect to the right page */
if (!isset($_GET['compileAfter']))
	$_GET['compileAfter'] = 'index_sc';


if (headers_sent() == false)
{
	switch($_GET['compileAfter'])
	{
	/* other cases here, if seen as necessary */
	case 'index_sc':
		/* just the default */
	default:
		header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
		break;
	}
}
/* END OF SCRIPT */





// hey, make absolute_location() a library function
// that does url_absolutify for you
/// and if (headers_sent() == false)
// mebbe
?>