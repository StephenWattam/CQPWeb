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
 *  Object for dealing with simple apache htaccess files  of the format below.
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
 *  We don't need to put methods in a Limit -- if it's not there, then the restrictions 
 *  specified are applied to ALL http methods, not just those specified; see
 *  http://httpd.apache.org/docs/1.3/mod/core.html#limit
 * 
 */
class apache_htaccess 
{


	/* begin member declaration */
	

	/** The AuthName of this htaccess file */
	private $AuthName;
	
	
	/* important note: all paths are treated as-is;
	 * ie they must begin with a slash when passed in, 
	 * if that is what is intended */
	
	/** directory name: location of binaries */
	private $path_to_apache_password_utility_directory;
	
	/** filename not a directory name: location of htgroup file (AuthGroupFile) */
	private $path_to_groups_file;
	
	/** filename not a directory name: location of htpasswd file (AuthUserFile) */
	private $path_to_password_file;
		
	/** directory name of the web directory where this htaccess is going to go */
	private $path_to_web_directory;
	
	
	/* note: only one of the following two members should be set at any one time. */
	
	/** Array of users who are permitted access (or NULL if there aren't any). */
	private $permitted_users;
	/** Array of groups who are permitted access (or NULL if there aren't any). */
	private $permitted_groups;
	
	
	/** 
	 * Extra Apache directives that this object does nothing special with, and
	 * just adds verbatim to the htaccess file. Typically this variable will be 
	 * undefined, or an empty string.
	 */
	private $extra_directives;


	/* override rules for setting member variables:
	   (1) when loading from file, users will only be loaded if groups is not there
	   (2) nothing can be added to users if groups is set
	   (3) nothing can be added to groups is users is set
	   (4) when saving to file, users will only be saved if groups is not there 
	*/
	
	
	/* end of member declaration */
	
	
	/** Constructor: allows you to set the location of the web directory all in one go. */	
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


	/** 
	 * Loads an Apache htaccess file of the sort this class handles.
	 * 
	 * Returns true for all OK, false for something went wrong 
	 */
	function load()
	{
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
	
	/**
	 * Writes the Apache htaccess file.
	 * 
	 * returns true for all OK, false for something went wrong 
	 */
	function save()
	{
		if (!$this->check_ok_for_htaccess_save())
			return false;
			
		if (file_put_contents("{$this->path_to_web_directory}/.htaccess", $this->make_htaccess_contents()) === false)
			return false;
		
		return true;
	}
	
	
	/**
	 * Get the contents of the Apache htaccess file embodying
	 * the settings within this object.
	 */
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
	
	
	/** Creates a new group; returns true for success, false for failure */
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

		if (copy($this->path_to_groups_file, $this->path_to_groups_file.'_backup_'.time()) === false)
			return false;
		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
		
	}

	/** Lists current groups; returns false for problem, otherwise an array containing the groups */
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

	/**
	 * Gets a list of all the users in the specified group from the htgroup file, 
	 * and returns it as an array; the array is empty if the group was not found,
	 * the return value is false if the operation was impossible
	 */
	function list_users_in_group($group)
	{
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

	/** true for all OK, otherwise false */
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

		if (copy($this->path_to_groups_file, $this->path_to_groups_file.'_backup_'.time()) === false)
			return false;
		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}

	/** true for all OK, otherwise false */
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

		if (copy($this->path_to_groups_file, $this->path_to_groups_file.'_backup_'.time()) === false)
			return false;
		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}
	
	/** true for all OK, otherwise false */
	function delete_user_from_all_groups($user)
	{
		if (!$this->check_ok_for_group_op())
			return false;

		$data = file_get_contents($this->path_to_groups_file);

		$data = preg_replace("/ $user\b/", '', $data);

		if (copy($this->path_to_groups_file, $this->path_to_groups_file.'_backup_'.time()) === false)
			return false;
		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}

	/** true for all OK, otherwise false */
	function delete_group($group)
	{
		if (!$this->check_ok_for_group_op())
			return false;

		$data = file_get_contents($this->path_to_groups_file);
		
		$data = preg_replace("/$group: .*\n/", '', $data);

		if (copy($this->path_to_groups_file, $this->path_to_groups_file.'_backup_'.time()) === false)
			return false;
		if (file_put_contents($this->path_to_groups_file, $data) === false)
			return false;
		
		return true;
	}

	/** 
	 * Creates a new user; returns the return value from htpasswd, or 1 in case of unix copy fail.
	 * 
	 * Note this is the opposite to most methods in this class, which return true for all-OK;
	 * it is because here we are using the return value system of htpasswd.
	 */
	function new_user($username, $password)
	{
		/* create the user, adding them & their password to the password file
		 * no need to check for and delete the name -- htpasswd does this */
		
		if (!$this->check_ok_for_password_op())
			return false;
		
		/* backup the previous file */
		if (file_exists($this->path_to_password_file))
		{
			$c = '';
			if (copy($this->path_to_password_file, $this->path_to_password_file . '_backup_' . time()) == false)
				return 1;
		}
		else
			$c = 'c';

		exec("{$this->path_to_apache_password_utility_directory}/htpasswd"
				. " -b$c {$this->path_to_password_file} $username $password"
				, $junk, $val);

		return $val;
	}
	
	/** 
	 * Gets the hashed password of the specified username from the htpasswd file,
	 * returning false if the username cannot be found or if there is some other problem.
	 */
	function get_user_hashword($username)
	{
		if (!$this->check_ok_for_password_op())
			return false;
		$data = file_get_contents($this->path_to_password_file);
		if (preg_match("/\b$username:([^\n]+)/", $data, $m) < 1)
			return false;
		else
			return $m[1];
	}
	
	/**
	 * Returns an array of usernames, or false if there is a problem.
	 */
	function list_users()
	{
		if (!$this->check_ok_for_password_op())
			return false;

		$data = file_get_contents($this->path_to_password_file);

		preg_match_all("/(\w+):.+\n/", $data, $m);
		
		return $m[1];
	}
	
	/** true for all OK, otherwise false */
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
	
	
	
	
	/**
	 * Returns true if this object has all the settings it needs to work with the password file;
	 * otherwise false.
	 */
	function check_ok_for_password_op()
	{
		return (
			$this->path_to_password_file !== NULL
			&&
			$this->path_to_apache_password_utility_directory !== NULL
		? true : false );
	}
	/**
	 * Returns true if this object has all the settings it needs to work with the group file;
	 * otherwise false
	 */
	function check_ok_for_group_op()
	{
		return (
			$this->path_to_groups_file !== NULL
		? true : false );
	}
	/**
	 * Returns true if this object has all the settings it needs to work with a particular htaccess file;
	 * otherwise false; the check is for *writing*.
	 */
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
	/**
	 * Returns true if this object has all the settings it needs to work with a particular htaccess file;
	 * otherwise false; the check is for *reading*.
	 */
	function check_ok_for_htaccess_load()
	{
		return (
			$this->path_to_web_directory !== NULL
		? true : false );
	}


} /* end of class apache_htaccess */




?>