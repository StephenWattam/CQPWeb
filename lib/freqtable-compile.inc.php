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









// this shouldn't be a script on its own - should be subcorpus-admin as one of the functions TODO
// call it subcorpusFunction=freqtable

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





/* connect to mySQL */
connect_global_mysql();


/* connect to CQP */
connect_global_cqp();




/* get "get" settings */

/* subcorpus for which to create frequency lists */
if (isset($_GET['compileSubcorpus']))
{
	$sc_to_compile = mysql_real_escape_string($_GET['compileSubcorpus']);
	subsection_make_freqtables($sc_to_compile);
}
else
{
	/* are we to compile all subcorpora? */
	if (isset($_GET['compileSubcorpusAll']) && $_GET['compileSubcorpusAll'] == '1')
	{
		$freqtabled_subcorpora = list_freqtabled_subcorpora();
		foreach(get_list_of_subcorpora() as $sc)
			if ( ! in_array($sc, $freqtabled_subcorpora) )
				subsection_make_freqtables($sc);
	}
	else
		exiterror_parameter('Critical parameter "compileSubcorpus" was not defined!', __FILE__, __LINE__);
}






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
// and if (headers_sent() == false)
// mebbe
?>