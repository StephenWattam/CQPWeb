<?php
/**
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-10 Andrew Hardie
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


/*
 * This file contains the main script for the CQPweb API-via-HTTP.
 * 
 * It processes incoming requests and calls other bits of CQPweb in
 * such a way as to send back the results of each function in 
 * accordance with the API documentation.
 * 
 * This is generally as plain text (easily explode()-able or otherwise
 * manipulable within Perl or PHP).
 */

require_once("settings.inc.php");
require_once("../lib/defaults.inc.php");


/* include function library files */
require_once('../lib/library.inc.php');
require_once('../lib/concordance-lib.inc.php');
require_once('../lib/concordance-post.inc.php');
require_once('../lib/ceql.inc.php');
require_once('../lib/metadata.inc.php');
require_once('../lib/exiterror.inc.php');
require_once('../lib/cache.inc.php');
require_once('../lib/subcorpus.inc.php');
require_once('../lib/db.inc.php');
require_once('../lib/user-settings.inc.php');
require_once("../lib/cwb.inc.php");
require_once("../lib/cqp.inc.php");


if (!url_string_is_valid())
	exiterror_bad_url();


/*
 * This switch runs across functions available within the web-api.
 * 
 * Each option assembles the _GET as appropiate, then include()s the right other file.
 * 
 * If the included file produces output that we don't want, then we need to turn on
 * output buffering, capture the HTML, extract what we need from it, then throw it away.
 * e.g. the qname form the concordance output in the query function.
 * 
 * In some cases, just writing a simpler version fo the script may be justifiable.
 */
switch($_GET['function'])
{
case 'query':
	/* run a query */
	//return value: the query name. (allow this to be specified, as with a saved query?
	// yeah actually, that would be better.
	break;
	/* endcase query */
	
	
case 'concordance':
	/* print out a concordance for an existing query */
	// interface to concordance download.
	break;
	/* endcase concordance */
	
/* insert other cases HERE */

default:
	// send error code	
	break;
}

?>