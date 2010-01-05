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




class CQPwebSettings
{
	/* currently only seven variables are supported */
	
	
	/* these four must be strings: disallow_nonwords is used on SQL name and CQP name of corpus */
	private $corpus_title;
	private $corpus_sql_name;
	private $corpus_cqp_name;
	private $css_path;
	
	/* these ones must be boolean */
	private $corpus_main_script_is_r2l;
	private $corpus_uses_case_sensitivity;
	
	private $directory_override_reg;
	private $directory_override_data;
	
	
	/* management variables */
	private $cqpweb_root_directory_path;
	/* should be either absolute or relative to the working dir of the current script */
	

	/* getters and setters */
	
	public function get_corpus_title() { return $this->corpus_title; }
	public function set_corpus_title($new_value) { $this->corpus_title = (string)$new_value; }
	
	public function get_corpus_sql_name() { return $this->corpus_sql_name; }
	public function set_corpus_sql_name($new_value) 
	{
		$this->corpus_sql_name = $this->disallow_nonwords($new_value);
	}
	
	public function get_corpus_cqp_name() { return $this->corpus_cqp_name; }
	public function set_corpus_cqp_name($new_value)
	{
		$this->corpus_cqp_name = $this->disallow_nonwords($new_value);
	}
		
	public function get_css_path() { return $this->css_path; }
	public function set_css_path($new_value) { $this->css_path = $new_value; }

	public function get_r2l() { return $this->corpus_main_script_is_r2l; }
	public function set_r2l($new_value) { $this->corpus_main_script_is_r2l = (bool) $new_value; }

	public function get_case_sens() { return $this->corpus_uses_case_sensitivity; }
	public function set_case_sens($new_value) { $this->corpus_uses_case_sensitivity = (bool) $new_value; }


	public function get_directory_override_reg() { return $this->directory_override_reg; }
	public function set_directory_override_reg($new_value)
	{
		if (empty($new_value))
			unset($this->directory_override_reg);
		else if (is_dir($new_value))
			$this->directory_override_reg = $new_value;
	}

	public function get_directory_override_data() { return $this->directory_override_data; }
	public function set_directory_override_data($new_value)
	{
		if (empty($new_value))
			unset($this->directory_override_data);
		else if (is_dir($new_value))
			$this->directory_override_data = $new_value;
	}

	/**
	 * Constructor's sole parameter is path to the root directory of CQPweb; 
	 * this can be absolute (beginning with '/') or relative to the script's 
	 * working directory. Defaults to '.'.
	 */
	public function __construct($path = '.')
	{
		$this->cqpweb_root_directory_path = $path;
	}
	
	
	/**
	 * returns 0 for all OK otherwise a string describing the error
	 */
	public function load($sqlname = false)
	{
		if ($sqlname !== false)
			$this->corpus_sql_name = $sqlname;
		
		if (! isset($this->corpus_sql_name))
			return "CQPwebSettings Error: you haven't specified the corpus settings you want to load!";
		
		include("{$this->cqpweb_root_directory_path}/{$this->corpus_sql_name}/settings.inc.php");
		
		/* check whether each variable is set, ifso, upload it to the private variables */

		if (isset($corpus_sql_name))
		{
			if ($corpus_sql_name !== $this->corpus_sql_name)
				return "CQPwebSettings Error: the original file does not match the specified SQL-name!";
		}
		if (isset($corpus_title))
			$this->corpus_title = $corpus_title;
		if (isset($corpus_cqp_name))
			$this->corpus_cqp_name = $corpus_cqp_name;
		if (isset($css_path))
			$this->css_path = $css_path;
		if (isset($corpus_main_script_is_r2l))
			$this->corpus_main_script_is_r2l = (bool)$corpus_main_script_is_r2l;
		if (isset($corpus_uses_case_sensitivity))
			$this->corpus_uses_case_sensitivity = (bool)$corpus_uses_case_sensitivity;
//		if (isset($this_corpus_directory_override['reg_dir']))
//			$this->directory_override_reg = $this_corpus_directory_override['reg_dir'];
//		if (isset($this_corpus_directory_override['data_dir']))
//			$this->directory_override_data = $this_corpus_directory_override['data_dir'];
		
		
		return 0;
	}
	
	
	
	/**
	 * returns 0 for all OK, otherwise a string describing the error
	 */
	public function save()
	{
		if ( ! $this->ready_to_save())
			return "CQPwebSettings Error: not ready to save (necessary variables not all set)";
		
		$data = "<?php\n\n";
		
		$data .= "\$corpus_title = '{$this->corpus_title}';\n";
		$data .= "\$corpus_sql_name = '{$this->corpus_sql_name}';\n";
		$data .= "\$corpus_cqp_name = '{$this->corpus_cqp_name}';\n";
		$data .= "\$css_path = '{$this->css_path}';\n";
		
		if (isset($this->corpus_main_script_is_r2l))
			$data .= "\$corpus_main_script_is_r2l = " . ($this->corpus_main_script_is_r2l ? 'true' : 'false') . ";\n";
		if (isset($this->corpus_uses_case_sensitivity))
			$data .= "\$corpus_uses_case_sensitivity = " . ($this->corpus_uses_case_sensitivity ? 'true' : 'false') . ";\n";
		
//		if (isset($this->directory_override_reg))
//			$data .= "\$this_corpus_directory_override['reg_dir'] = '{$this->directory_override_reg}';\n";
//		if (isset($this->directory_override_data))
//			$data .= "\$this_corpus_directory_override['data_dir'] = '{$this->directory_override_data}';\n";
				
		$data .= "?>";
		
		file_put_contents("{$this->cqpweb_root_directory_path}/{$this->corpus_sql_name}/settings.inc.php", $data);
			
		return 0;
	}
	
	
	
	private function ready_to_save()
	{
		if (! isset(
			$this->corpus_title ,
			$this->corpus_sql_name ,
			$this->corpus_cqp_name ,
			$this->css_path
			) )
			return false;
		return true;
	}
	
	
	
	private function disallow_nonwords($argument)
	{
		return preg_replace('/\W/', '', (string)$argument); 	
	}
}


?>
