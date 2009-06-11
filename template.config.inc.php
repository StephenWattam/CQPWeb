<?php

/* SYSTEM CONFIG SETTINGS : the same for every corpus, and CANNOT be overridden */

/* these settings should never be alterable from within CQPweb (would risk transmitting them as plaintext) */


/* adminstrators' usernames, separated by | (as in BNCweb) */
$superuser_username = 'bob|jeff';

/* mySQL username and password */
$mysql_webuser = '';
$mysql_webpass = '';
$mysql_schema  = '';
$mysql_server  = '';

/* ---------------------- */
/* server directory paths */
/* ---------------------- */

$path_to_cwb = '';
$path_to_apache_utils = '';
$path_to_perl = '';

$cqp_tempdir = '';
$cqpweb_accessdir = '';
$cqpweb_uploaddir = '';
$mysql_tempdir = '';
$cwb_datadir = '';
$cwb_registry = '';

/* if mySQL returns ???? instead of proper UTF-8 symbols, change this setting true -> false or false -> true */
$utf8_set_required = true;



?>
