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






// IMPORTANT NOTE : much of the contents of this file are untested as of now.








/**
 * class RFace:
 * 
 * PHP interface to the R statistics program
 * 
 * Usage:
 * 
 * $r = new RFace($path_to_r);
 * 
 * where $path_to_r is a relative path to the directory containing the R executable
 * that DOES NOT end in a slash
 * 
 * $result = $r->execute("text to be fed to R here");
 * 
 * to explicitly shut down child process:
 * 
 * unset($r);
 * 
 * Other methods (TODO) wrap-around ->execute() to provide a friendlier API for various uses of R 
 * 
 * some functions:
 * 
 * load array to vector
 * load multi-d array to data table
 * get list of current objects as an array
 * get info about a named object (size, type etc)
 * save workspace as specified filename
 * check argument array 
 * 
 */
class RFace 
{
	/* class constants */
	
	const DEFAULT_CHART_FILENAME = 'R-chart';
	
	/* member variables */
	
	/* handle for the process */
	private $process;

	/* array for the input/output handles themselves to go in */
	private $handle;
	
	/* caches for oft-used R calls */
	private $object_list_cache = false;
	
	/* function for handling lines as they are passed */
	private $line_handler_callback = false;
	
	/* path directory separator character - use instgead of literal '/' */
	private $DIRSEP;
	
	
	/* variables for error handling */
	
	/* has there been an error? */
	private $ok = true;
	
	/* most recent error message */
	private $last_error_message;
	
	/* should the object exit the program on detection of an error? */
	private $exit_on_error = false;
	
	/* if true, debug info will be printed to stdout */
	private $debug_mode;
	
	
	
		
	
	
	
	
	/* constructor */
	function __construct($path_to_r = false, $debug = false)
	{
		/* misc initialisation -- get this out of the way first */
		$this->DIRSEP = (strtoupper(substr(php_uname('s'), 0, 3)) == 'WIN' ? '\\' : '/');

		/* set debug mode for this object */
		$this->debug_mode = (bool) $debug;

		/* check that we know where the R program is... */
		if ($path_to_r === false && $this->DIRSEP == '/')
		{
			/* try to deduce the path using Unix "which" if available */
			exec("which R", $exec_output);
			if (is_executable($exec_output[0]))
				$path_to_r = substr($exec_output[0], 0, -2);
		}
		if ( ! is_dir($path_to_r) )
			exit("ERROR: directory $path_to_r for R executable not found.\n");
		
		/* array of settings for the three pipe-handles */
		$io_settings = array(
			0 => array("pipe", "r"), /* pipe allocated to child's stdin  */
			1 => array("pipe", "w"), /* pipe allocated to child's stdout */
			2 => array("pipe", "w")  /* pipe allocated to child's stderr */
			);
	
		$command = "$path_to_r/R --slave";
		
		$this->process = proc_open($command, $io_settings, $this->handle);
		
		if (! is_resource($this->process))
			$this->error("ERROR: R backend startup failed; command: $command\n");
		else if ($this->debug_mode)
			echo "R backend successfully started up.\n";
	}
	
	
	
	/* destructor */
	function __destruct()
	{
		if (isset($this->handle[0]))
		{
			fwrite($this->handle[0], 'q()\n');		//TODO check whether the \n is needed; I imagine it is
			fclose($this->handle[0]);
		}
		if (isset($this->handle[1]))
			fclose($this->handle[1]);
		if (isset($this->handle[2]))
			fclose($this->handle[2]);
		
		if ($this->debug_mode)
			echo "Pipes to R backend successfully closed.\n";
		
		/* and finally shut down the child process so script doesn't hang*/
		if (isset($this->process))
			$stat = proc_close($this->process);

		if ($this->debug_mode)
			echo  "R slave process has been closed with termination status [ $stat ].\n"
				. "RFace object will now destruct.\n";
	}




	/**
	 * Main execution function: returns an array of lines of output from R.
	 * 
	 * This may be an empty array if R didn't print anything.
	 */
	function execute($command, $line_handler_callback = false)
	{
		/* execute can change the number of objects, so clear obj cache */
		$$this->object_list_cache = false;
		
		if ( (!is_string($command)) || $command == "" )
		{
			$this->ok = false;
			$this->error("ERROR: RFace::execute() was called with no command");
		}
				

		if ($this->debug_mode == true)
			echo "R << $command;\n";

		/* send the command to R's stdin */			
		fwrite($this->handle[0], $command);		// TODO do we need a \n here?
		/* that executes the command */

		$result = array();
		
		
		/* then, get lines one by one from [OUT] */
		while (false !== ($line = fgets($this->handle[1])) )
		{
			/* delete whitespace from the line */
			$line = trim($line, " \t\r\n");
			if (empty($line))
				continue;
				
			/* an output line we ALWAYS ignore; an empty statement terminated by ; is not invalid! */
			if ($line == 'Error: unexpected \';\' in ";"')
				continue;

			if ($this->debug_mode)
				echo "R >> $line\n";
			
			if ($line_handler_callback !== false)
				/* call the specified function */
				$line_handler_callback($line);
			else
				/* add the line to an array of results */
				$result[] = $line;
		}

		/* return the array of results */
		return $result;

	}
	
	
	/**
	 * Specify a callback function to be used on lines as they are retrieved from ->execute()
	 */
	public function set_line_handler($func)
	{
		if (function_exists($func))
			$this->line_handler_callback = $func;
		else if ($func == false)
			$this->line_handler_callback = false;
		else
			$this->error('ERROR: Unrecognised callback function specified.');
	} 
	
	
	/**
	 * Sets debug messages on or off (parameter is a bool).
	 * 
	 * (By default, debug messages are off. They can also be turned on when
	 * the constructor is called.)
	 */
	public function set_debug($new_value)
	{
		$this->debug_mode = (bool) $new_value;	
	}
	
	
	/**
	 * error control
	 */
	
	/**
	 * Sets exit-on-error mode on/off (parameter is a bool).
	 * 
	 * (Byt default, this mode is OFF.)
	 */
	public function set_exit_on_error($new_value)
	{
		$this->exit_on_error = (bool) $new_value;	
	}
	
	
	/**
	 * Checks whether everything has gone OK.
	 * Returns false if there has been an error.
	 */
	public function ok()
	{
		return $this->ok;
	}
	
	/**
	 * Gets the most recent error message
	 */
	public function error_message()
	{
		return $this->last_error_message;	
	}
	
	/**
	 * Raise an error from within the RFace.
	 * 
	 * Method can optionally be passed a message and line number.
	 */
	private function error($msg = false, $line = false)
	{
		if ($msg == false)
			$msg = "ERROR: Non specific R interface error!";
		if ($line != false)
			$msg .= "\n\t... at line $line";
		$this->last_error_message = $msg;
		$this->ok = false;
		if ($this->exit_on_error)
			exit($this->last_error_message);
		else if ($this->debug_mode)
			echo $this->last_error_message;
	}
	
	
	/**
	 * load functions
	 */
	
	/**
	 * Loads data from a PHP array to an R vector.
	 * 
	 * The PHP array is assumed to have continuous numeric keys starting at 0,
	 * which will be shifted to continuous numeric keys starting at 1 in R.
	 * 
	 * A number-mode array can contain only numbers (ints or floats).
	 * 
	 * A string-mode array is allowed to contain ints and floats - but they will be
	 * converted to strings by PHP's normal string-embedding mechanism.
	 * 
	 * @param varname The name the new R object should have. It is also possible to
	 *                overwrite an object, as usual in R. No checks are performed on
	 *                overwriting.
	 * @param array   The array itself; passed by reference. If it's not an array but
	 *                a single variable, then it will be converted to a one-member
	 *                vector.
	 * @param type    Optionally specify the type of array: 'string', 'number', 'deduce'.
	 */
	public function load_vector($varname, &$array, $type = 'deduce')
	{
		if (! is_array($array))
		{
			$tmparray = array($array);
			return load_vector($varname, $tmparray, self::deduce_array_type($tmparray));
		}
		
		switch ($type)
		{
		case 'string':
			return load_vector_of_strings($varname, $array);
		case 'number':
			return load_vector_of_numbers($varname, $array);
		case 'deduce':
			return load_vector($varname, $array, self::deduce_array_type($array));
		default:
			$this->error('Unrecognised array type!', __LINE__);
			break;
		}
	}
	
	private function load_vector_of_strings($varname, &$array)
	{
		$instring = "$varname = c(\"" . implode('","', $array) . '")';
		$this->execute($instring);
	}
	
	private function load_vector_of_numbers($varname, &$array)
	{
		// TODO -- note, this won't work because it modifies the ORIGINBAL
		// I could create a temporary array and modify that but that would be a pain and would double the mem usage needlessly
		// the loop needs ot assemble the axctual stirng.
		// can I use map() here?
		foreach($array as $k=>&$a)
			$a = self::num($a);
		$instring = "$varname = c(" . implode(',',$array) . ')';
		$this->execute($instring);
	}
	
	/**
	 * Creates a factor from a PHP array of strings.
	 * 
	 * See load_vector for how conversion works.
	 */
	public function load_factor($varname, &$array)
	{
		$temp_obj = $this->new_object_name();
		
		$this->load_vector_of_strings($temp_obj, $array);

		$this->execute("$varname = factor($temp_obj)");
		
		$this->drop_object($temp_obj);
	}
	
	/**
	 * Creates a data frame from a PHP string or two-dimensional array.
	 * 
	 * If $data is a string, then the following assumptions are made:
	 * 
	 * (1) that lines are terminated by \n or \r\n
	 * (2) that fields are terminated by \t
	 * (3) if $header_row, then the first line is treated as containing column names
	 *     that can be used as object names
	 * 
	 * If $data is an array, then the following assumptions are made:
	 * 
	 * (1) that TODO
	 */
	public function load_data_frame($varname, &$data, $header_row = true, $invert_array = true)
	{
		if (is_string($data))
			$this->load_data_frame_from_string($varname, $data, $header_row);
		else if (is_array($data))
			$this->load_data_frame_from_2darray($varname, $data, $header_row, $invert_array);
	}
	
	private function load_data_frame_from_string($varname, &$data, $header_row = true)
	{
		
	}
	
	private function load_data_frame_from_2darray($varname, &$data, $header_row = true, $invert_array = true)
	{
		if ($invert_array)
		{
			$temp_data = array();
			// TODO: load data to temp_data
			$this->load_data_frame_from_2darray($varname, $temp_data, $header_row, false);
			return;	
		}	
	}
	
	
	/**
	 * read functions
	 */
	
	/**
	 * Reads the value of an object from R.
	 * 
	 * The basic way of using the method returns an array equivalent to the R vector
	 * or other object specified.
	 * 
	 * @param varname   Name of the object to read
	 * @param mode      How to read the object.
	 *                  Options:
	 *                     'vector' -- the default, create a number or string array from an R vector.
	 *                     'verbatim' -- create a string containing the verbatim description
	 *                                   of the object from R's output.
	 *                     'solo' -- for a one-element vector: returns it as a single variable,
	 *                               not as an array, with the appropriate type.
	 *                     //TODO others? 
	 */
	public function read($varname, $mode = 'vector')
	{
		// OH no, hang on, what if this is the return value of a function
		// or the result of an operation
		// of PART of a vector???
		// so don't chekc if exists.
		
		
		if (!$this->object_exists($varname))
		{
			$this->error("Cannot fetch contents of nonexistent object $varname!");
			return;
		}
		/* first, request the variable's contents */
		$data = $this->execute($varname);
		
		// TODO
		
		return $output;
	}
	
	
	
	
	
	/**
	 * Gets an array of object-names in the active R workspace, as strings. 
	 */
	public function list_objects()
	{
		if ($this->object_list_cache !== false)
			return $this->object_list_cache;
		
		$rawtext = implode(' ', $this->execute("ls()"));
		
		if ($rawtext == 'character(0)')
			/* no objects */
			return array();
		
		if (preg_match_all('/"(\S+)"/', $rawtext, $matches, PREG_PATTERN_ORDER) < 1)
			$this->error('Error parsing output from ls() >> R!', __LINE__);
		
		$this->object_list_cache = $matches[1];
		return $matches[1]; 
	}
	
	/**
	 * Checks whether an object of the given name exists in the active R workspace.
	 */
	public function object_exists($obj)
	{
		return in_array($obj, $this->list_objects());	
	}
	
	/**
	 * Deletes an object, checking first if it exists.
	 * 
	 * If it doens't exist, no error is raised.
	 */
	public function drop_object($obj)
	{
		if ($this->object_exists($obj))
			$this->execute("rm($obj)");
	}
	
	/**
	 * Returns a string, guaranteed to be a valid R object name which does not 
	 * currently exist in the workspace being used.
	 */
	public function new_object_name()
	{
		$names = $this->list_objects();
		
		/* RFO for "RFace Object" */
		for ($new = 'rfo'; true ; $new .= chr(rand(0x41, 0x5a)) )
		{
			for ($i = 0x41; $i <= 0x5a; $i++)
			{
				$curr = $new . chr($i);
				if (!in_array($curr, $names))
					return $curr;
			}
		}
		/* should not be reached */
		$this->error('New object name could not be generated', __LINE__);
	}
	
	//TODO finish this method
	/**
	 * Saves the chart creatred by the given command to the specified filename
	 */
	public function make_chart($filename, $chart_command)
	{
		if (is_dir($filename))
			$filename .= '.' . self::DEFAULT_CHART_FILENAME;
			
		// set the gfraphics output to save to this file
		
		$this->execute($chart_command);
		
		// reset the graphics output
	}
	
	/**
	 * Save the R workspace to a specified location.
	 * 
	 * If $path is a directory, the workspace is saved as the file .Rdata in that directory.
	 * 
	 * Otherwise, $path is assumed to be a target filename and the workspace saved in
	 * that location.
	 */
	public function save_workspace($path)
	{
		if (is_dir($path))
			$this->execute("save.image(file=\"$path{$this->DIRESP}.RData\")");
		else 
			$this->execute("save.image(file=\"$path\")");
	}

	/**
	 * Load an R workspace from a specified location.
	 * 
	 * If $path is a directory, this function loads the file .Rdata in that directory.
	 * 
	 * Otherwise, $path is treated as a filename (the method will add the .RData extension
	 * by default if necessary).
	 */	
	public function load_workspace($path)
	{
		if (is_dir($path))
			$this->execute("load(file\"=$path{$this->DIRESP}.RData\")");
		else if (is_file($path))
			$this->execute("load(file\"=$path\")");
		else if (is_file($path.'.RData'))
			$this->execute("load(file\"=$path.RData\")");
		else
			$this->error("ERROR: can't load workspace, no file found at $path!");
	}
	
	
	/** 
	 * Checks whether the requested R library package is available, and
	 * loads it if it is.
	 */ 
	public function load_package()
	{
		//TODO	
	}
	
	
	
	
	
	/**
	 * Static methods
	 */
	
	
	
	
	/**
	 * Deduces whether an array has a single "type" or not.
	 * 
	 * Returns a string describing the type: this is "string" if
	 * every value in the array is a string, "number" if every value
	 * is either an int or a float, "mixed" if a value of the type
	 * contrary to the established type was detected, "undefined" if 
	 * a value that is neither string nor number was detected.
	 * 
	 * The string "array" can also be returned, if every component of
	 * the array is itself an array. NB: currently no recursive checking, TODO ?
	 * 
	 * Note: "mixed" and "undefined" are error values, and if they are
	 * returned, it says nothing about the presence of errors of the 
	 * *other* type further down the array.
	 */
	public static function deduce_array_type(&$array)
	{
		$type = 'UNKNOWN';
		
		foreach ($array as &$a)
		{
			/* get the type */
			if ( is_int($a) || is_float($a) )
				$currtype = 'number';
			else if ( is_string($a) )
				$currtype = 'string';
			else if (is_array($a))
				$currtype = 'array';
			else
				return 'undefined';	
			
			/* check the type against the type established so far */
			if ($type == 'UNKNOWN')
				$type = $currtype;
			else
				if ($type != $currtype)
					return 'mixed';
		}	
		return $type;
	}
	
	/**
	 * Gets the numeric value of a string, regardless of whether it
	 * represents a float or an int.
	 * 
	 * More reliable when building arrays of numbers than a typecast!
	 */
	public static function num($string)
	{
		return 1 * $string;
	}
		





	/* end of class RFace */
}
?>