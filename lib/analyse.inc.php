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
 * page scripting the interface for cotrpus analysis. 
 * 
 * Currently only allows multivariate analysis, but hopefully will allow
 * others later, including custom analysis.
 */

/* include defaults and settings */
require('../lib/environment.inc.php');


/* library files */
require('../lib/library.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/cache.inc.php');
require('../lib/subcorpus.inc.php');
//require('../lib/db.inc.php');
//require('../lib/freqtable.inc.php');
//require('../lib/metadata.inc.php');
require('../lib/xml.inc.php');
require('../lib/multivariate.inc.php');
require('../lib/rface.inc.php');
require('../lib/cwb.inc.php');
require('../lib/cqp.inc.php');


// startup
cqpweb_startup_environment();

// print page head




/// call body function





// end page and shutdown


// note needs 10 features.

/*
 * SCRATCH CODE -- TRYING IT OUT
 * 
 * proof of concept code runnign factor analysis in R.
 */
$r = new RFace();
$r->set_debug(true);

//import data
$fm_id = (int) $_GET['matrix'];
get_feature_matrix_r_import($r, $fm_id, 'mydata');

$op ='';

foreach (array(2,3,4,5) as $i)
{
	$r->execute("out = factanal(mydata, $i, rotation=\"varimax\")");
	$op1 .= implode( '\n', $r->execute("print(out, digits = 2, sort = TRUE)"));
	$op2 .= implode( '\n', $r->read("out", "verbatim"));
	
}


?>
<html>
<head>

</head>
<body>
<pre>

Factor Analysis Output for 2 to 5 factors
=========================================


<?php echo $op1 ?>


~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
################################################################################################



<?php echo $op2 ?>







</pre>

</body>

</html>


<?php

cqpweb_shutdown_environment();

//TODO many, mnay things.

