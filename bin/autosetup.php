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
 * This script finalises CQPweb setup once the config file has been created.
 */



require('../lib/environment.inc.php');

/* include function library files */
require('../lib/library.inc.php');
require('../lib/admin-lib.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/ceql.inc.php');

require ('../bin/cli-lib.php');




/* BEGIN HERE */


/* refuse to run unless we are in CLI mode */
if (php_sapi_name() != 'cli')
	exit("Critical error: Cannot run CLI scripts over the web!\n");

echo "\nNow finalising setup for this installation of CQPweb....\n";

echo "\nInstalling database structure; please wait.\n";

connect_global_mysql();

cqpweb_mysql_total_reset();

disconnect_global_mysql();

/* 
 * NB the above depends on the MySQL settings being available otherwise than via $Config;
 * once config.inc.php is no longer globally included in environment.nc.php 
 * it will be necessary to directly include it here.
 */

echo "\nDatabase setup complete.\n";

/* with DB installed, we can now startup the environment.... */

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP , RUN_LOCATION_CLI);

echo "\nNow, we must set passwords for each user account specified as a superuser.\n";

include ('../lib/config.inc.php');

foreach(explode('|', $superuser_username) as $super)
{
	$pw = get_variable_string("a password for user ``$super''");
	add_new_user($super, $pw, 'not-specified@nowhere.net', USER_STATUS_ACTIVE);
	echo "\nAccount setup complete for ``$super''\n";
}

echo "\n--- done.\n";

echo "\nCreating CSS files....\n";

cqpweb_regenerate_css_files();

echo "\n--- done.\n";

echo "\nCreating built-in mapping tables....\n";

regenerate_builtin_mapping_tables();

echo "\n--- done.\n";

/*
 * If more setup actions come along, add them here
 * (e.g. annotation templates, xml templates...
 */

echo "\nAutosetup complete; you can now start using CQPweb.\n";

cqpweb_shutdown_environment();

exit;


