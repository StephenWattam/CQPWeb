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
 * @file
 * 
 * Receiver script for actions relating to multivariate analysis - 
 * mostly the management of feature matrices.
 * 
 * The actions are triggered through redirect. The script has no stub of its own.
 * 
 * The actions are controlled via switcyh and mostly work by sorting through
 * the "_GET" parameters, and then calling the underlying functions
 * (mostly in multivariate.inc.php).
 * 
 * When a case is complex, it has been hived off into a function within this file.
 */


/* include defaults and settings */
require('../lib/environment.inc.php');


/* library files */

/* include function library files */
require('../lib/library.inc.php');
//require('../lib/html-lib.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/cache.inc.php');
require('../lib/subcorpus.inc.php');
//require('../lib/db.inc.php');
//require('../lib/freqtable.inc.php');
//require('../lib/metadata.inc.php');
require('../lib/xml.inc.php');
require('../lib/multivariate.inc.php');


$script_mode = isset($_GET['multivariateAction']) ? $_GET['multivariateAction'] : false; 

switch($script_mode)
{
case 'deleteFM':
	if (!isset($_GET['matrix])

default:
	/* dodgy parameter: ERROR out. */
	exiterror_general("A badly-formed multivariate-analysis operation was requested!"); 
	break;
}


if (isset($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */





/*
 * =======================================
 * FUNCTIONS for running bits of the above
 * =======================================
 */
 
