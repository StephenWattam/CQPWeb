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









// this shouldn't be a script on its own - should be subcorpus-admin as one of the functions TODO
// call it subcorpusFunction=freqtable
// that would save a bundle of overhead.

/* ------------ *
 * BEGIN SCRIPT *
 * ------------ */

/* initialise variables from settings files  */
require('../lib/environment.inc.php');


/* include function library files */
require ("../lib/library.inc.php");
require ("../lib/user-lib.inc.php");
require ("../lib/freqtable.inc.php");
require ("../lib/freqtable-cwb.inc.php");
require ("../lib/exiterror.inc.php");
require ("../lib/metadata.inc.php");
require ("../lib/subcorpus.inc.php");
require ("../lib/db.inc.php");
require ("../lib/cwb.inc.php");
require ("../lib/cqp.inc.php");



cqpweb_startup_environment();

if (isset($_GET['compileSubcorpus']))
	subsection_make_freqtables($_GET['compileSubcorpus']);
else
	exiterror_general('No subcorpus was specified - frequency tables cannot be compiled!!');


cqpweb_shutdown_environment();

set_next_absolute_location('index.php?thisQ=subcorpus&uT=y');
/* redirect to the right page */



?>
