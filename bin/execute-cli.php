<?php

/*
 * Improtant note: this is non-secure, so is blocked from being run from anywhere but the command line
 * only use this script if you know what you are doing!
 * it was originally invented to allow the setup process access to library functions.
 */

// TODO: get the first superuser username and set it as username by popping it
// into the right place for defaults.inc.php to find it (as if it had come via the web)

// TODO: run only from CLI

if (!isset($argv[1]))
{
	echo "Usage: cd path/to/a/corpus/directory && php ../lib/execute-cli.php function arg1 arg2 ...\n\n";
	exit(1);
}

if (isset($_GET))
	unset($_GET);

$_GET['function'] = $argv[1];
unset($argv[0],$argv[1]);
if (!empty($argv))
	$_GET['args'] = implode('#', $argv);
unset($argc, $argv);

require('../lib/execute.inc.php');

//TODO use output buffering to capture the results, strip html and print?


?>