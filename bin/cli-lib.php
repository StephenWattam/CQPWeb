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

function ask_boolean_question($question)
{
	while (1)
	{
		echo "\n$question\n\n";
	
		echo "Enter [Y]es or [N]:";
		
		$s = strtolower(trim(fgets(STDIN), "/ \t\r\n"));
		if ($s[0] == 'y')
			return true;
		else if ($s[0] == 'n')
			return false;
	}
}

function get_enter_to_continue()
{
	echo "Press [enter] to continue.\n\n";
	fgets(STDIN);	
}

/* END FUNCTION DEFINITIONS */





?>
