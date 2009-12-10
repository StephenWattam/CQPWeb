<?php

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




/* BEGIN HERE */

/* refuse to run unless we are in CLI mode */

if (php_sapi_name() != 'cli')
	exit("config script must be run in CLI mode!");

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


/* adminstrators' usernames, separated by | (as in BNCweb) */
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

/* if mySQL returns ???? instead of proper UTF-8 symbols, change this setting true -> false or false -> true */
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
			echo "OK! won't overwrite. Program aborts.\n\n";
			exit();
		}
		else if ($i[0] == 'Y')
		{
			break;
		}		
	} 
}

echo "Saving config file ...\n\n";

file_put_contents('lib/config.inc.php', $config_file);







/* create a password file for the superusers and a group file */

echo "Creating admin username(s)... (NB: admin passwords will be the same as the username";
echo "\nyou should reset them as soon as possible\n\n";

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



echo "\n\nDone! note that the source file for this program will be gzipped,\n\nto avoid unwanted re-running.";
echo "\nYou should delete this program (or move it out of the web directory) for security.";
/* zip the source file of this script so it can't be executed again by mistake */
exec("gzip -q {$argv[0]}");

exit();
?>
