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

/**
 * @file
 * 
 * This file contains two things:
 * 
 * (1) The environment startup and shutdown functions that need to be called to get things moving.
 * 
 * (2) The three global objects ($Config, $User, $Corpus) into which everything is stuffed.
 * 
 * 
 */


/* include defaults and settings */
if (file_exists("settings.inc.php"))
	require("settings.inc.php");
require("../lib/defaults.inc.php");


/**
 * Startup the CQPweb environment. 
 */
function startup_cqpweb_environment()
{
	/** Global object containing information on system configuration. */
	global $Config;
	/** Global object containing information on the current user account. */
	global $User;
	/** Global object containing information on the current corpus. */
	global $Corpus;
	
	$Config = new stdClass();
	$User   = new stdClass();
	$Corpus = new stdClass();
}


?>
