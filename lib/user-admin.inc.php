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





/* include defaults and settings */
require('../lib/environment.inc.php');


/* library files */
require('../lib/user-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/library.inc.php');

// TODO this should prob not be a file....

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);
update_multiple_user_settings($username, parse_get_user_settings());

cqpweb_shutdown_environment();
header('Location: ' . url_absolutify('index.php?thisQ=userSettings&uT=y'));
exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */


/** Gets all "newSetting" parameters from $_GET and sanitises for correct type of input. */
function parse_get_user_settings()
{
	$settings = array();
	foreach($_GET as $k => $v)
	{
		if (preg_match('/^newSetting_(\w+)$/', $k, $m) > 0)
		{
			switch($m[1])
			{
			/* boolean settings */
			case 'conc_kwicview':
			case 'conc_corpus_order':
			case 'cqp_syntax':
			case 'context_with_tags':
			case 'use_tooltips':
			case 'thin_default_reproducible':
				$settings[$m[1]] = (bool)$v;
				break;
			
			/* string settings */
			case 'realname':
			case 'email':
				/* This will be sanitised at the DB interface level. */
				 $settings[$m[1]] = $v;
				break;
			
			/* integer settings */
			case 'coll_statistic':
			case 'coll_freqtogether':
			case 'coll_freqalone':
			case 'coll_from':
			case 'coll_to':
			case 'max_dbsize':
				$settings[$m[1]] = (int)$v;
				break;
				
			/* patterned settings */
			case 'linefeed':
				if (preg_match('/^(da|d|a|au)$/', $v) > 0)
					$settings[$m[1]] = $v;
				break;
			case 'username':
				$settings[$m[1]] = preg_replace('/\W/', '', $m[1]);
				break;
			}
		} 
	}
	return $settings;
}



?>
