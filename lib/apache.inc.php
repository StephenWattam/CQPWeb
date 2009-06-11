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










/**
 *  object for dealing with simple apache .htaccess files  of the format below 
 *  
 *  AuthUserFile PATH
 *  AuthGroupFile PATH
 *  AuthName REALM
 *  AuthType Basic
 *  deny from all
 *  require group GROUP GROUP GROUP || require user USER USER USER
 *  satisfy any
 *  
 *  EXTRA_DIRECTIVES
 * 
 * 
 *  see http://httpd.apache.org/docs/1.3/howto/auth.html 
 *  
 *  don't need to put methods in a <Limit> -- if it's not there, then the restrictions 
 *  specified are applied to ALL http methods, not just those specified; see
 *  http://httpd.apache.org/docs/1.3/mod/core.html#limit
 */


class apache_htaccess {

	private $AuthName;
	
	
	/* important note: all paths should be RELATIVE */
	/* ie must begin with a slash if that is what is intended */
	
	/* directory name */
	private $path_to_apache_password_utility_directory;
	
	/* filename not a directory name */
	private $path_to_groups_file;
	
	/* filename not a directory name */
	private $path_to_password_file;
		
	/* directory name of the web directory where this htaccess is going to go */
	private $path_to_web_directory;
	
	/* note: only one of these can be set. */
	private $permitted_users;
	private $permitted_groups;
	
	private $extra_directives;

	/* override rules :
	   (1) when loading from file, users will only be loaded if groups is not there
	   (2) nothing can be added to users if groups is set
	   (3) nothing can be added to groups is users is set
	   (4) when saving to file, users will only be saved if groups is not there 
	*/
	
	
	/* constructor allows you to set the location of the web directory all in one go */	
	function __construct($path_to_web_directory = false)
	{
		if ($path_to_web_directory !== false)
			$this->set_path_to_web_directory($path_to_web_directory);
	}

	/* reset functions for the four private variables */
	function set_path_to_apache_password_utility_directory($password_program_path)
	{
		if (substr($password_program_path, -1) == '/')
			$password_program_path = substr($password_program_path, 0,
										strlen($password_program_path)-1);
		$this->path_to_apache_password_utility_directory = $password_program_path;
	}
	function set_path_to_groups_file($groupfile_path)
	{
		$this->path_to_groups_file = $groupfile_path;
	}
	function set_path_to_password_file($passwords_path)
	{
		$this->path_to_password_file = $passwords_path;
	}
	function set_path_to_web_directory($path_to_web_directory)
	{
// this deffo needs a check!
// likewise the similar one above
		if (substr($path_to_web_directory, -1) == '/')
			$path_to_web_directory = substr($path_to_web_directory, 0,
										strlen($path_to_web_directory)-1);
		$this->path_to_web_directory = $path_to_web_directory;
	}
	function set_AuthName($newAuthName)
	{
		/* if it contains a non-word and does not already have quotes start-and-end */
		if ( preg_match('/\W/', $newAuthName) > 0 && preg_match('/^".*"$/', $newAuthName) == 0)
			$this->AuthName = '"' . str_replace('"', '\"', $newAuthName) . '"';
		else
			$this->AuthName = $newAuthName;
	}

	
	
	
	/* for the next block of functions, true == all ok, false == anything else */
	function allow_user($user)
	{
		if (is_array($this->permitted_groups))
			return false;
		if (isset($this->permitted_users))
		{
			if ( ! in_array($user, $this->permitted_users) )
				$this->premitted_users[] = $user;
		}
		else
		{
			$this->permitted_users = array($user);
		}
		return true;
	}
	function disallow_user($user)
	{
		if ($this->permitted_users === NULL)
			return;	
		$key = array_search($user, $this->permitted_users);
		if ($key !== false)
			unset($this->permitted_users[$key]);
	}
	function get_allowed_users()
	{
		return $this->permitted_users;
	}
	function clear_allowed_users()
	{
		unset($this->permitted_users);
	}
	
	function allow_group($group)
	{
		if (is_array($this->permitted_users))
			return false;
		
		if (isset($this->permitted_groups))
		{
			if ( ! in_array($group, $this->permitted_groups) )
				$this->permitted_groups[] = $group;
		}
		else
		{
			$this->permitted_groups = array($group);
		}
		return true;
	}
	function disallow_group($group)
	{
		if ($this->permitted_groups === NULL)
			return;	
		$key = array_search($group, $this->permitted_groups);
		if ($key !== false)
			unset($this->permitted_groups[$key]);
	}
	function get_allowed_groups()
	{
		return $this->permitted_groups;
	}
	function clear_allowed_groups()
	{
		unset($this->permitted_groups);
	}
	
	
	
	function add_extra_directive($directive_text)
	{
		if (! in_array($directive_text, $this->extra_directives) )
			$this->extra_directives[] = $directive_text;
	}
	function remove_extra_directive($directive_text)
	{
		$directive_text = trim($directive_text);
		if ($this->extra_directives === NULL)
			return;	
		$key = array_search($directive_text, $this->extra_directives);
		if ($key !== false)
			unset($this->extra_directives[$key]);
	}
	function clear_extra_directives()
	{
		unset($this->extra_directives);
	}


	/* returns true for all OK, false for something went wrong */
	function load()
	{
		/* load an Apache htaccess file of the sort this class handles */
		
		if (!$this->check_ok_for_htaccess_load())
			return false;
		
		$data = file_get_contents("{$this->path_to_web_directory}/.htaccess");
		
		/* load things */
		
		if (preg_match('/AuthName (.*)/', $data, $m) > 0)
			$this->AuthName = $m[1];
		if (preg_match('/AuthUserFile (.*)/', $data, $m) > 0)
			$this->path_to_password_file = $m[1];
		if (preg_match('/AuthGroupFile (.*)/', $data, $m) > 0)
			$this->path_to_groups_file = $m[1];

		if (preg_match('/group (.*)/', $data, $m) > 0)
			$this->permitted_groups = explode(' ', $m[1]);
		if (preg_match('/user (.*)/', $data, $m) > 0)
			$this->permitted_users = explode(' ', $m[1]);
		
		if (preg_match('/satisfy any(.*)$/s', $data, $m) > 0)
		{
			$this->extra_directives = explode("\n", $m[1]);
			foreach($this->extra_directives as $key => &$val)
			{
				if ($val === '')
					unset($this->extra_directives[$key]);
			}
		}
	}
	
	/* returns true for all OK, false for something went wrong */
	function save()
	{
		/* write the Apache htaccess file */
		
		if (!$this->check_ok_for_htaccess_save())
			return false;
			
		if (file_put_contents("{$this->path_to_web_directory}/.htaccess", $this->make_htaccess_contents()) === false)
			return false;
		
		return true;
	}
	
	
	
	function make_htaccess_contents()
	{
		$string = "AuthUserFile {$this->path_to_password_file}\n";	
		$string .= "__XX__XX__\n";
		$string .= "AuthName {$this->AuthName}\n";
		$string .= "AuthType Basic\n";
		
		$string .= "deny from all\n";
		if (isset($this->permitted_groups))
		{
			$string .= 'require group ' . implode(' ', $this->permitted_groups) . "\n";
			$string = str_replace('__XX__XX__', "AuthGroupFile {$this->path_to_groups_file}", $string);
		}
		else
		{
			if (isset($this->permitted_users))
				$string .= 'require user ' . implode(' ', $this->permitted_users) . "\n";
			$string = str_replace('__XX__XX__', '', $string);
		}
		$string .= "satisfy any\n\n";
		
		if (isset($this->extra_directives))
			$string .= implode("\n", $this->extra_directives);
		
		return $string;
	}
	
	
	/* returns true for success, false for failure */
	function new_group($groupname)
	{
		/* add a new group to the groups file */
		if (!$this->check_ok_for_group_op())
			return false;
		
		$groupname = preg_replace('/\W/', '', $groupname);
		
		/* don't create the group if one by that name already exists */
		if ( in_array($groupname, $this->list_groups()) )
			return false;
		
		$data = file_get_contents($this->path_to_groups_file);
		
		$data .= "$groupname: \n";

		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
		
	}

	/* false for problem, otherwise array containing the groups */
	function list_groups()
	{
		/* get a list of all groups from the htgroup file, and return as an array */
		if (!$this->check_ok_for_group_op())
			return false;
	
		$data = file_get_contents($this->path_to_groups_file);
		
		if (preg_match_all("/(\w+): .*\n/", $data, $m) > 0)
			return $m[1];
		else
			return array();
	}

	function list_users_in_group($group)
	{
		/* get a list of all the users in the specified group from the htgroup file, */
		/* and return as an array */
		/* empty array if none, false if operation impossible */
		if (!$this->check_ok_for_group_op())
			return false;
	
		$data = file_get_contents($this->path_to_groups_file);
		
		if (preg_match("/$group: (.*)\n/", $data, $m) > 0)
		{
			$returnme = explode(' ', $m[1]);
			if ( ($k = array_search('', $returnme, true)) !== false)
				unset($returnme[$k]);
		}
		else
			$returnme = array();
		return $returnme;
	}

	/* true for all OK, otherwise false */
	function add_user_to_group($user, $group)
	{
		/* add the specified user to the specified group */
		if (!$this->check_ok_for_group_op())
			return false;
		$data = file_get_contents($this->path_to_groups_file);
		
		/* check whether the group contains the user already */
		preg_match("/$group:([^\n]*)/", $data, $m);
		if (preg_match("/\b$user\b/",$m[1]) > 0)
			return true;
			
		$data = preg_replace("/$group: /", "$group: $user ", $data);
		
		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}

	/* true for all OK, otherwise false */
	function delete_user_from_group($user, $group)
	{
		if (!$this->check_ok_for_group_op())
			return false;

		$data = file_get_contents($this->path_to_groups_file);
	
		if (preg_match("/$group: .*/", $data, $m) == 0 )
			return false;
		$oldline = $m[0];
		$newline = preg_replace("/ $user\b/", '', $oldline);

		$data = str_replace($oldline, $newline, $data);

		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}
	
	function delete_user_from_all_groups($user)
	{
		if (!$this->check_ok_for_group_op())
			return false;

		$data = file_get_contents($this->path_to_groups_file);

		$data = preg_replace("/ $user\b/", '', $data);

		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}

	/* true for all OK, otherwise false */
	function delete_group($group)
	{
		if (!$this->check_ok_for_group_op())
			return false;

		$data = file_get_contents($this->path_to_groups_file);
		
		$data = preg_replace("/$group: .*\n/", '', $data);

		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}

	/* returns the return val from htpasswd */
	function new_user($username, $password)
	{
		/* create the user, adding them & their password to the password file */
		/* no need to check for and delete the name -- htpasswd does this*/
		if (!$this->check_ok_for_password_op())
			return false;
		
		$c = (file_exists($this->path_to_password_file) ? '' : 'c');
		exec("{$this->path_to_apache_password_utility_directory}/htpasswd -b$c {$this->path_to_password_file} $username $password", 
			$junk, $val);

		return $val;
	}
	function list_users()
	{
		if (!$this->check_ok_for_password_op())
			return false;

		$data = file_get_contents($this->path_to_password_file);

		preg_match_all("/(\w+):.+\n/", $data, $m);
		
		return $m[1];
	}
	function delete_user($username)
	{
		if (!$this->check_ok_for_password_op())
			return false;

		$data = file_get_contents($this->path_to_password_file);

		$data = preg_replace("/$username:.+\n/", '', $data);

		if (file_put_contents($this->path_to_password_file, $data) === false)
			return false;
		
		$this->delete_user_from_all_groups($username);
		
		return true;
	}
	
	
	
	
	/* returns true if this object has all the settings it needs to work with the password file */
	/* otherwise false */
	function check_ok_for_password_op()
	{
		return (
			$this->path_to_password_file !== NULL
			&&
			$this->path_to_apache_password_utility_directory !== NULL
		? true : false );
	}
	/* returns true if this object has all the settings it needs to work with the group file */
	/* otherwise false */
	function check_ok_for_group_op()
	{
		return (
			$this->path_to_groups_file !== NULL
		? true : false );
	}
	/* returns true if this object has all the settings it needs to work with a particular htaccess file */
	/* otherwise false */
	function check_ok_for_htaccess_save()
	{
		return (
			$this->AuthName !== NULL
			&&
			$this->path_to_password_file !== NULL
			&&
			$this->path_to_web_directory !== NULL
			&&
			( $this->permitted_users !== NULL || 
				($this->permitted_groups !== NULL && $this->path_to_groups_file !== NULL)
			)
		? true : false );
	}
	function check_ok_for_htaccess_load()
	{
		return (
			$this->path_to_web_directory !== NULL
		? true : false );
	}
}






?>