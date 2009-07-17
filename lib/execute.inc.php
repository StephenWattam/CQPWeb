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






/**
   script that allows superusers direct access to the function library via the URL / get method
   
   in the format
   
   execute.php?function=foo&args=["string"#1#2]&locationAfter=[index.php?thisQ=search]&uT=y
   
   (note that everything within [] needs to be url-encoded for non-alphanumerics)
   
      
   ANOTHER IMPORTANT NOTE:
   =======================
   
   It is quite possible to **break CQPweb** using this script.
   
   It has been written on the assumption that anyone who is a superuser is sufficiently
   non-idiotic to avoid doing so.
   
   If for any given superuser this assumption is false, then that is his/her/your problem.
   
   Not CQPweb's.
   
**/


/* include defaults and settings */
require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include all function files */
include('../lib/admin-lib.inc.php');
include('../lib/apache.inc.php');
include('../lib/cache.inc.php');
include('../lib/db.inc.php');
include('../lib/colloc-lib.inc.php');
include('../lib/concordance-lib.inc.php');
include('../lib/freqtable.inc.php');
include('../lib/freqtable-cwb.inc.php');
include('../lib/library.inc.php');
include('../lib/metadata.inc.php');
include('../lib/subcorpus.inc.php');
include('../lib/indexforms-admin.inc.php');
include('../lib/indexforms-queries.inc.php');
include('../lib/indexforms-saved.inc.php');
include('../lib/indexforms-others.inc.php');
include('../lib/indexforms-subcorpus.inc.php');
include('../lib/exiterror.inc.php');
include('../lib/user-settings.inc.php');
include('../lib/rface.inc.php');
include('../lib/corpus-settings.inc.php');
create_pipe_handle_constants();
include('../lib/cwb.inc.php');
include('../lib/cqp.inc.php');
/* more to be added */


/* only superusers get to use this script */
if (!user_is_superuser($username))
	exit ('
<html><head><title>Unauthorised access to execute.php</title></head><body><pre>
Your username does not have permission to run execute.php.

CQPweb (c) 2008
</pre></body></html>');



if (!url_string_is_valid())
	exiterror_bad_url();




/* get the name of the function to run */
if (isset($_GET['function']))
	$function = $_GET['function'];
else
	exit('
<html><head><title>No function specified for execute.php</title></head><body><pre>
You did not specify a function name for execute.php.

You should reload and specify a function.

CQPweb (c) 2008
</pre></body></html>');


/* extract the arguments */
if (isset($_GET['args']))
{
	$argv = explode('#', $_GET['args']);
	$argc = count($argv);
}
else
	$argc = 0;

if ($argc > 10)
	exit('
<html><head><title>Too many arguments for execute.php</title></head><body><pre>
You specified too many arguments for execute.php.

The script only allows up to ten arguments [which is, I rather think, quite enough -- AH].

CQPweb (c) 2008
</pre></body></html>');
	



/* connect to mySQL and cqp, in case the function call needs them as globals */
$mysql_link = mysql_connect($mysql_server, $mysql_webuser, $mysql_webpass);
if (! $mysql_link)
	exit('
<html><head><title>mySQL problem in execute.php</title></head><body><pre>
mySQL did not connect - please try again later!

CQPweb (c) 2008
</pre></body></html>');

mysql_select_db($mysql_schema, $mysql_link);
if ($utf8_set_required)
	mysql_query("SET NAMES utf8", $mysql_link);

$cqp = new CQP;
$cqp->set_error_handler("exiterror_cqp");
$cqp->execute("set DataDirectory '/$cqp_tempdir'");
$cqp->execute("$corpus_cqp_name;");


/* run the function */

switch($argc)
{	
case 0:		$function();	break;
case 1:		$function($argv[0]);	break;
case 2:		$function($argv[0], $argv[1]);	break;
case 3:		$function($argv[0], $argv[1], $argv[2]);	break;
case 4:		$function($argv[0], $argv[1], $argv[2], $argv[3]);	break;
case 5:		$function($argv[0], $argv[1], $argv[2], $argv[3], $argv[4]);	break;
case 6:		$function($argv[0], $argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);	break;
case 7:		$function($argv[0], $argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6]);	break;
case 8:		$function($argv[0], $argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7]);	break;
case 9:		$function($argv[0], $argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7], $argv[8]);	break;
case 10:	$function($argv[0], $argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7], $argv[8], $argv[9]);	break;

default:
	break;
}

disconnect_all();


/* go to the specified address, if one was specified AND if the HTTP headers have not been sent yet */
/* (if execution of the function caused anything to be written, then they WILL have been sent)      */


if ( isset($_GET['locationAfter']) && headers_sent() == false )
{
	header('Location: ' . url_absolutify($_GET['locationAfter']));
}
else if ( ! isset($_GET['locationAfter']) && headers_sent() == false )
	echo '
<html><head><title>CQPweb -- execute.php</title></head><body><pre>
Your function call has been finished executing!

Thank you for flying with execute.php.

On behalf of CQP and all the corpora, I wish you a very good day,
and I hope we\'ll see you again soon.

CQPweb (c) 2008
</pre></body></html>';

exit();
?>