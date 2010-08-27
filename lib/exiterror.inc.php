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




//////////// TODO reformat these functions and associated CSS to produce a nice page like BNCweb's




function exiterror_bad_url()
{
	?>
	<html>
		<head>
		</head>
		<body>
			<h3>We're sorry, but CQPweb could not read your full URL.</h3>
			
			<h3>Proxy servers sometimes truncate URLs, try again
			without a proxy server!</h3>
			
			<hr/>
			<p><a href="index.php">Back to corpus home page.</p>
		</body>
	</html>
	<!-- needs a css link etc && proper layout -->
	<?php
	exit();
}

function exiterror_fullpage($errormessage, $script=NULL, $line=NULL)
{
	global $css_path;
	
	?>
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>CQPweb has encountered an error!</title>
	<link rel="stylesheet" type="text/css" href="<?php echo $css_path; ?>" />

	</head>
	<body>
	<?php
	echo '<p class="errormessage">CQPweb encountered an error and could not continue.</p>';
	echo "<p class=\"errormessage\">$errormessage</p>";
	if (isset($script, $line))
		echo "<p class=\"errormessage\">... in file <b>$script</b> line <b>$line</b>.</p>";
	print_footer();
	disconnect_all();
	exit();
}

function exiterror_general($errormessage, $script=NULL, $line=NULL)
{
	echo '<p class="errormessage">CQPweb encountered an error and could not continue.</p>';
	echo "<p class=\"errormessage\">$errormessage</p>";
	if (isset($script, $line))
		echo "<p class=\"errormessage\">... in file <b>$script</b> line <b>$line</b>.</p>";
	print_footer();
	disconnect_all();
	exit();
}



function exiterror_cacheoverload()
{
	?>
	<p class="errormessage">CRITICAL ERROR - CACHE OVERLOAD!</p>
	<p class="errormessage">CQPweb tried to clear cache space but failed!</p>
	<p class="errormessage">Please report this error to the system administrator.</p>
	<?php
	print_footer();
	disconnect_all();
	exit();
}


/* used for freqtable overloads too */
function exiterror_dboverload()
{
	?>
	<p class="errormessage">CRITICAL ERROR - DATABASE CACHE OVERLOAD!</p>
	<p class="errormessage">CQPweb tried to clear database cache space but failed!</p>
	<p class="errormessage">Please report this error to the system administrator.</p>
	<?php
	print_footer();
	disconnect_all();
	exit();
}


function exiterror_toomanydbprocesses($process_type)
{
	global $mysql_process_limit;
	global $mysql_process_name;

	// does this need to be a full page? prob not as will be acalling from a script 
	?>
	<p class="errormessage">Too many database processes!</p>
	<p class="errormessage">
		There are already 
			<?php 
			echo "{$mysql_process_limit[$process_type]} {$mysql_process_name[$process_type]}";
			?>
		databases being compiled. Please use the "back"-button of your browser and try again 
		in a few moments.
	</p>
	<?php
	print_footer();
	disconnect_all();
	exit();
}



function exiterror_mysqlquery($errornumber, $errormessage, $script=NULL, $line=NULL)
{
	?>
	<p class="errormessage">A mySQL query did not run successfully!</p>
	<?php
	echo "<p class=\"errormessage\">Error # $errornumber: <br/>";
	echo "$errormessage </p>";
	if (isset($script, $line))
		echo "<p class=\"errormessage\">... in file <b>$script</b> line <b>$line</b>.</p>";
	print_footer();
	disconnect_all();
	exit();
}

function exiterror_mysqlquery_show($errornumber, $errormessage, $origquery, $script=NULL, $line=NULL)
{
	?>
	<p class="errormessage">A mySQL query did not run successfully!</p>
	<?php
	echo "<p class=\"errormessage\">Error # $errornumber: <br/>";
	echo "$errormessage </p>";
	echo "<p class=\"errormessage\">Original query: <br/>";
	echo "$origquery</p>";
	if (isset($script, $line))
		echo "<p class=\"errormessage\">... in file <b>$script</b> line <b>$line</b>.</p>";
	print_footer();
	disconnect_all();
	exit();
}

function exiterror_parameter($errormessage, $script=NULL, $line=NULL)
{
	?>
	<p class="errormessage">A PHP script was passed a badly-formed parameter set!</p>
	<?php
	echo "<p class=\"errormessage\">$errormessage </p>";
	if (isset($script, $line))
		echo "<p class=\"errormessage\">... in file <b>$script</b> line <b>$line</b>.</p>";
	print_footer();
	disconnect_all();
	exit();
}



function exiterror_arguments($argument, $errormessage, $script=NULL, $line=NULL)
{
	?>
	<p class="errormessage">A PHP function was passed an invalid argument type!</p>
	<?php
	echo "<p class=\"errormessage\">Argument value was $argument. Problem: <br/>";
	echo "$errormessage </p>";
	if (isset($script, $line))
		echo "<p class=\"errormessage\">... in file <b>$script</b> line <b>$line</b>.</p>";
	print_footer();
	disconnect_all();
	exit();
}


/* CQP error message as a table */
function exiterror_cqp($error_array)
{
	?>
	<table border="0" width="100%" cellpadding="3" cellspacing="3">
	<tr>
		<td><p class="errormessage"><b>Error message</b></p></td>
	</tr>
	<tr>
		<td><p class="errormessage">
			<?php
			foreach ($error_array as $e)
				echo "\t$e<br/>";
			?>
		</p></td>
	</tr>
	</table>
	<?php
	print_footer();
	disconnect_all();
	exit();
}



/* prints a header to go on top of exiterror_cqp, and then calls it */
function exiterror_cqp_full($error_array)
{
	global $css_path;
	
	?>
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>CQPweb -- CQP reports errors!</title>
	<link rel="stylesheet" type="text/css" href="<?php echo $css_path; ?>" />
	
	</head>
	<body>
	<?php
	exiterror_cqp($error_array);
}

?>