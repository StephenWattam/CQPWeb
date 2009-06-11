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



/* a very short script, which turns the parameters from the wordlookup form into parameters for */
/* concordance.php and then calls a "location" */


require('settings.inc.php');
require('../lib/defaults.inc.php');
//require('../lib/library.inc.php');
require('../lib/concordance-lib.inc.php');
require('../lib/exiterror.inc.php');

if (isset($_GET['lookupString']))
	$theData = prepare_query_string($_GET['lookupString']);
else
	exiterror_parameter('The content of the query was not specified!', __FILE__, __LINE__);

switch($_GET['lookupType'])
{
	case 'end':
		$theData = '*' . $theData;
		break;
		
	case 'begin':
		$theData = $theData . '*';
		break;
		
	case 'contain':
		$theData = '*' . $theData . '*';
		break;
		
	case 'exact':
	default:
		break;
}



$url = "concordance.php?program=lookup&lookupShowWithTags={$_GET['lookupShowWithTags']}";

$url .= "&theData=$theData";

$url .= "&pp=" . (string)((int)$_GET['pp']);

$url .= '&qmode=sq_nocase';

$url .= '&del=begin&t=&del=end&uT=y';

header("Location: $url");

/* end of script */





?>