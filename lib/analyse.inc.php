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
 * page scripting the interface for corpus analysis. 
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


cqpweb_startup_environment();


//temp shortcut -- will eventually be a switch on a parameter here
output_analysis_factanal();


/* shutdown and end script */
cqpweb_shutdown_environment();

/*
 * =============
 * END OF SCRIPT
 * =============
 * 
 * (separate function for different analyses follow)
 * 
 */ 



/**
 * User interface function for factor analysis.
 * 
 * TODO an as-text version?
 * TODO tidy up all the HTML.
 */
function output_analysis_factanal()
{
	global $Corpus;
	global $Config;
	global $corpus_title;
	

	// TODO - using R's factanal() manually, work out the minimum bnumber of features needed for factor analysis.
	// Then check the matrix for this number of features and print an error message if absent.
	


	/* get matrix info object */	
	
	if ( (!isset($_GET['matrix'])) || $_GET['matrix'] === '')
		exiterror("No feature matrix was specified for the analysis.");
	
	$fm_id = (int) $_GET['matrix'];
	$matrix = get_feature_matrix($fm_id);
	
	// TODO put here the check for the minimum number fo features in the matrix.


	/* import the matrix to R in raw form */
	$r = new RFace();
	insert_feature_matrix_to_r($r, $matrix->id, 'mydata');
	
	$op = array();
	
	// TODO maybe parameterise the "max number of factors" as an "advanced" option on the query page 
	foreach (array(2,3,4,5,6,7) as $i)
	{
		// TODO make rotation type an "advanced" option on the query page 
		// (advanced options to be hidden behind a JavaScript button of course)
		$r->execute("out = factanal(mydata, $i, rotation=\"varimax\")");
		$op[$i] = implode( "\n", $r->execute("print(out, digits = 2, sort = TRUE)"));
	}
	
	
	
	
	echo print_html_header($corpus_title, $Config->css_path, array('modal'));
	
	
	?>

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Analyse Corpus: Factor Analysis of Feature Matrix
				&ldquo;<?php echo $matrix->savename; ?>&rdquo; 
			</th>
		</tr>
		<tr>
			<td class="concorderror">
				&nbsp;<br>
				<b>This function is currently under development</b>. So far, all you can do is
				view the raw output of the factor analysis from R (shown below).
				The analysis is currently performed for the range 2 to 7 factors.
				<br>&nbsp;
			</td>
		</tr>
		
	<?php
	
	foreach($op as $i => $solution)
		echo "\n\t\t<tr>"
			, "\n\t\t\t<th class=\"concordtable\">Factor Analysis Output for $i factors</th>"
			, "\n\t\t</tr>\n\t\t<tr>"
			, "\n\t\t\t<td class=\"concordgeneral\">"
			, "\n<pre>\n"
			, $solution
			, "\n</pre>\n\t\t\t</td>\n\t\t</tr>\n"
			;

	echo "\n</table>\n";
	
	echo print_html_footer();
}

