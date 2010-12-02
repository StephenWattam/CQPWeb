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


/* BEGIN FUNCTION DEFINITIONS */

function get_variable_path($desc)
{
	echo "Please enter\n$desc\nas an absolute directory path:\n\n";
	
	while (1)
	{
		$s = trim(fgets(STDIN), "/ \t\r\n");
		echo "\n\n";
	
		if (!is_dir("/$s") || $s === '')
			echo "\n\n/$s does not appear to be a valid directory path, please try again:\n\n\n";
		else
			return $s;
	}	
}

function get_variable_word($desc)
{
	echo "Please enter\n$desc.\nNote this can only contain ASCII letters, numbers and underscore.\n\n";
	
	while (1)
	{
		$s = trim(fgets(STDIN));
		echo "\n\n";
	
		if (preg_match('/\W/', $s) > 0 || $s === '')
			echo "\n\n$s contains invalid characters, please try again:\n\n\n";
		else
			return $s;
	}	
}

/* END FUNCTION DEFINITIONS */




/* BEGIN HERE */

/* refuse to run unless we are in CLI mode */
if (php_sapi_name() != 'cli')
	exit("Critical error: CQPweb's auto-config script must be run in CLI mode!\n");

echo "\n\n/***********************/\n\n\n";

echo "This is the configuration set up program for CQPweb.\n\n\n";






/* get the options, one by one */


$superuser_username = '';
while (1)
{
	$superuser_username .=  '|' . get_variable_word('the username you want to use for the sysadmin account');
	
	while (1)
	{
		echo "Add another admin username?) [y/n]\n\n";
		$i = strtoupper(fgets(STDIN));
		if ($i[0] =='N')
			break 2;
		else if ($i[0] == 'Y')
			break 1;
	}	
}
$superuser_username = trim($superuser_username, '|');





//TODO use a call to "which" to pre-guess these, but still ask for manual confirmation.
$path_to_cwb = get_variable_path("the path to the directory containing the cwb executables");
$path_to_apache_utils = get_variable_path("the path to the directory containing the apache passwd utilities");
$path_to_perl = get_variable_path("the path to the directory containing the perl executable");

$cwb_datadir = get_variable_path("the path to the directory you wish to use for the CWB datafiles");
$cwb_registry = get_variable_path("the path to the directory you wish to use for CWB registry files");

$cqpweb_tempdir = get_variable_path("the path to the directory you wish to use for the CQPweb cache and other temp files");
$cqpweb_accessdir = get_variable_path("the path to the directory you wish to store passwords / group files in");
$cqpweb_uploaddir = get_variable_path("the path to the directory you wish to store uploaded files in");


$mysql_webuser = get_variable_word("the MySQL username that you want CQPweb to use (do NOT use root)");
$mysql_webpass = get_variable_word("the password for this MySQL user");
$mysql_schema = get_variable_word("the name of the MySQL database to use for CQPweb tables");

echo "\n\nNote: the system will be set to use 'localhost' as the MySQL server.";
echo "\nIf you want to use a different MySQL server, please edit config.inc.php manually.\n\n";

  
$mysql_server = 'localhost';






$config_file = 
"<?php

/* SYSTEM CONFIG SETTINGS : the same for every corpus, and CANNOT be overridden */

/* these settings should never be alterable from within CQPweb (would risk transmitting them as plaintext) */


/* adminstrators' usernames, separated by | */
\$superuser_username = '$superuser_username';


/* mySQL username and password */
\$mysql_webuser = '$mysql_webuser';
\$mysql_webpass = '$mysql_webpass';
\$mysql_schema  = '$mysql_schema';
\$mysql_server  = '$mysql_server';



/* ---------------------- */
/* server directory paths */
/* ---------------------- */

/* variables do require a '/' before and after */
\$path_to_cwb = '$path_to_cwb';
\$path_to_apache_utils = '$path_to_apache_utils';
\$path_to_perl = '$path_to_perl';

\$cqpweb_tempdir = '$cqpweb_tempdir';
\$cqpweb_accessdir = '$cqpweb_accessdir';
\$cqpweb_uploaddir = '$cqpweb_uploaddir';


\$cwb_datadir = '$cwb_datadir';
\$cwb_registry = '$cwb_registry';

/* if queries to mySQL returns ???? instead of proper UTF-8 symbols, */ 
/* it can often be fixed instantly by changing this setting, either  */
/* true -> false or false -> true.                                   */
/* The initial value is \"true\", but which is needed depends on     */
/* several different aspects of your system setup.                   */
\$utf8_set_required = true;

?>";







if (!is_dir('lib'))
	mkdir('lib');
if (is_file('lib/config.inc.php'))
{
	while (1)
	{
		echo "'lib/config.inc.php' already exists, overwrite? (if not, program will abort) [y/n]\n\n";
		$i = strtoupper(fgets(STDIN));
		if ($i[0] =='N')
		{
			echo "\nOK! won't overwrite. Program aborts.\n\n";
			exit();
		}
		else if ($i[0] == 'Y')
			break;
	} 
}

echo "Saving config file ...\n\n";

file_put_contents('lib/config.inc.php', $config_file);






// TODO: check whether we are using Apache, and make the Apache bits conditional on that.
// If Apache is not in use, spell out what the superuser needs to do with their webserver.


/* create a password file for the superusers and a group file */

echo "Creating admin username(s)... (NB: admin passwords will be the same as the username\n";
echo "you should reset them as soon as possible\n\n";

$x = str_replace('|', ' ', $superuser_username);
file_put_contents("/$cqpweb_accessdir/.htgroup", "superusers: $x\n");

$c = 'c';
foreach (explode(' ', $x) as $username)
{
	exec("/$path_to_apache_utils/htpasswd -b$c /$cqpweb_accessdir/.htpasswd $username $username");
	$c = '';
}




/* create a .htaccess file for ./adm */

$adm_htaccess = str_replace("\r\n", "\n", 
"AuthUserFile /$cqpweb_accessdir/.htpasswd
AuthGroupFile /$cqpweb_accessdir/.htgroup
AuthName CQPweb
AuthType Basic
deny from all
require group superusers
satisfy any
");

file_put_contents("adm/.htaccess", $adm_htaccess);
chmod("adm/.htaccess", 0664);



echo "\n\nDone! note that the source file for this script will be gzipped,\nto avoid unwanted re-running.";
echo "\n\nYou should delete this program (or move it out of the web directory) for security.";
/* zip the source file of this script so it can't be executed again by mistake */
exec("gzip -q {$argv[0]}");

?>