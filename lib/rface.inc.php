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



// IMPORTANT NOTE : much of the contents of this file are untested as of now.








/**
 * class RFace:
 * 
 * PHP interface to the R statistics program.
 * 
 * Usage:
 * 
 * $r = new RFace($path_to_r);
 * 
 * where $path_to_r is a relative path to the directory containing the R executable
 * that DOES NOT end in a slash.
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
	
	/** handle for the process */
	private $process;

	/** array for the input/output handles themselves to go in */
	private $handle;
	
	/* caches for oft-used R calls */
	
	/** cache of the list of object names */
	private $object_list_cache = false;
	
	/** function for handling lines as they are passed */
	private $line_handler_callback = false;
	/** if a class name is placed here, $line_handler_callback will be treated as a static method of that class */
	private $line_handler_class = false;
	/** if an object is placed here, $line_handler_callback will be treated as a method of that object */
	private $line_handler_object = false;
	
	
	
	/* variables for error handling */
	
	/** has there been an error? */
	private $ok = true;
	
	/** most recent error message */
	private $last_error_message;
	
	/** should the object exit the program on detection of an error? */
	private $exit_on_error;
	
	/** if true, debug info will be printed to debug_dest */
	private $debug_mode;
	
	/** stream that debug messages will be sent to */
	private $debug_dest;
	
	
	
	
	
	
	
	
	/* constructor */
	function __construct($path_to_r = false, $debug = false, $debug_dest = STDOUT)
	{
		/* Constructor errors are critical, so let's turn on exit_on_error. */
		$this->exit_on_error = true;

		/* set debug mode for this object */
		$this->debug_mode = (bool) $debug;

		if ($debug)
		{
			if (($restype = get_resource_type($debug_dest)) == 'stream' || $restype == 'file')
				$this->debug_dest = $debug_dest;
			else
				$this->error("RFace: ERROR: Stream specified for printing debug messages is not valid.\n");
		}

		/* check that we know where the R program is... */
		if (empty($path_to_r))
		{
			/* detect whether or not R is on the path.... */
			$found = false;
			foreach(explode(PATH_SEPARATOR, $_ENV['PATH']) as $path)
			{
				// TODO: do I need a .exe here in Windows, or will it work regardless?
				if (is_executable(rtrim($path, DIRECTORY_SEPARATOR) . '/R'))
				{
					$found = true;
					break;
				}
			}
			if ( ! $found)
				$this->error("RFace: ERROR: no location supplied for R program, and it is not in the PATH.\n");
		}
		else if ( ! is_dir($path_to_r = rtrim($path_to_r, DIRECTORY_SEPARATOR)) )
			$this->error("RFace: ERROR: directory $path_to_r for R executable not found.\n");

		/* array of settings for the three pipe-handles */
		$io_settings = array(
			0 => array("pipe", "r"), /* pipe allocated to child's stdin  */
			1 => array("pipe", "w"), /* pipe allocated to child's stdout */
			2 => array("pipe", "w")  /* pipe allocated to child's stderr */
			);
		
		$command = "$path_to_r/R --slave";
		
		$this->process = proc_open($command, $io_settings, $this->handle);
		
		if (! is_resource($this->process))
			$this->error("RFace: ERROR: R backend startup failed; command: $command\n");
		else if ($this->debug_mode)
			$this->debug_alert("RFace: R backend successfully started up.\n");

		/* finally, turn down the severity level of errors... */
		$this->exit_on_error = false;
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
		
		$this->debug_alert( "RFace: Pipes to R backend successfully closed.\n");
		
		/* and finally shut down the child process so script doesn't hang*/
		if (isset($this->process))
			$stat = proc_close($this->process);

		if ($this->debug_mode)
		$this->debug_alert("RFace: R slave process has been closed with termination status [ $stat ].\n"
					. "\tRFace object will now destruct.\n");
	}




	/**
	 * Main execution method: returns an array of lines of output from R.
	 * 
	 * This may be an empty array if R didn't print anything.
	 * 
	 * If $line_handler_callback is specified, it will be called on each line of
	 * output. If it returns a value, that value will be added to the return-array
	 * instead of the line. If it does not return a value, nothing will be added
	 * to the return-array (and thus, the caller will ultimately get back an empty 
	 * array).
	 * 
	 * If $line_handler_callback is NOT specified, the function checks whether
	 * one has been set at the object level ($this->line_handler_callback). If it
	 * has, that is used. 
	 * 
	 */
	function execute($command, $line_handler_callback = false)
	{
		if ($line_handler_callback === false)
			if ($this->$line_handler_callback !== false)
				$line_handler_callback = $this->line_handler_callback;
		
		/* execute can change the number of objects, so clear object-list cache */
		$this->object_list_cache = false;
		
		if ( (!is_string($command)) || $command == "" )
		{
			$this->ok = false;
			$this->error("RFace: ERROR: RFace::execute() was called with no command\n");
			return;
		}


		$this->debug_alert("RFace: R << $command;\n");

		/* send the command to R's [IN] */
		// TODO do we need a \n here after the command?
		if (false === fwrite($this->handle[0], $command))
			$this->error("RFace: ERROR: problem writing to the R input stream\n");
		/* that executes the command ... */

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

			$this->debug_alert("RFace: R >> $line\n");
			
			if ($line_handler_callback !== false)
			{
				/* call the specified function */
				$callback_return = $line_handler_callback($line);
				if (isset($callback_return))
				{
					$result[] = $callback_return;
					unset($callback_return);
				}
				/* else don't add anything to $result */
			}
			else
				/* add the line to an array of results */
				$result[] = $line;
		}
		/* Note, no attempt is made to do anything with R's [ERR] stream. */

		/* return the array of results */
		return $result;

	}
	
	
	/**
	 * Specify a callback function to be used on lines as they are retrieved by ->execute().
	 *
	 * The callback can be  (a) a closure (b) a string naming a function (c) an array of an object plus a method name
	 * (d) an array of a class name plus a method name.
	 *
	 * To use no line handler, pass false.
	 */
	public function set_line_handler($callback)
	{
		if (false === $callback)
		{
			$this->line_handler_class = false;
			$this->line_handler_object = false;
			$this->line_handler_callback = false;
			$this->debug_alert("RFace: Line handler wiped, line handling disabled.\n");
		}
		else if (is_array($callback))
		{
			if ( isset($callback[0], $callback[1]) && count($callback) == 2)
			{
				/* case one:  we have been passed an object or class and its method */
				$callback_name = '[not known]';
				if ( is_object($callback[0]) && is_callable($callback, false, $callback_name) )
				{
					$this->line_handler_class = false;
					$this->line_handler_object = $callback[0];
					$this->line_handler_callback = $callback[1];
					$this->debug_alert("RFace: Line handler accepted ( $callback_name, object call ).\n";
				}
				else if (class_exists($callback[0] && method_exists($callback[0], $callback[1]))
				{
					$this->line_handler_class = $callback[0];
					$this->line_handler_object = false;
					$this->line_handler_callback = $callback[1];
					$this->debug_alert("RFace: Line handler accepted ( $callback[0]::$callback[1], static call ).\n";
			 	}
				else
					$this->error("RFace: ERROR: Uncallable object/class method passed as line handler.\n");
			}
			else
				$this->error("RFace: ERROR: Invalid array layout for line handler callback.\n");
		}
		else if  (is_callable($callback))
		{
			$this->line_handler_class = false;
			$this->line_handler_object = false;
			$this->line_handler_callback = $callback;
			$callback_name = ( is_string($callback) ? $callback : '[anonymous function]');
			$this->debug_alert("RFace: Line handler accepted ( $callback_name ).\n");
		}
		else
			$this->error("RFace: ERROR: Unrecognisable line handler function was passed ( $callback ).\n");
	}
	
	
	/**
	 * Sets debug messages on or off (parameter is a bool).
	 * 
	 * (By default, debug messages are off. They can also be turned on when
	 * the constructor is called.)
	 */
	public function set_debug($new_value)
	{
		$this->debug_mode = (bool) $new_value
	}
	
	
	/*
	 * error control
	 */
	
	/**
	 * Sets exit-on-error mode on/off (parameter is a bool).
	 * 
	 * (By default, this mode is OFF.)
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
	 * When an error is raised, it will exit PHP if the "exit_on_error" variable
	 * is set to true. Otherwise, the error is stored, and can be accessed using
	 * the error_message() and ok() methods.
	 * 
	 * In debug mode, errors will be sent to the debug output stream as well.
	 * 
	 * Method can optionally be passed a message and line number.
	 */
	private function error($msg = false, $line = false)
	{
		if ($msg == false)
			$msg = "ERROR: General R interface error!";
		if ($line != false)
			$msg .= "\n\t... at line $line";
		$this->last_error_message = $msg;
		$this->ok = false;
		if ($this->exit_on_error)
			exit($this->last_error_message);
		else
			$this->debug_alert($this->last_error_message);
	}
	
	/**
	 * Print a debug message, if debug output is enabled.
	 */
	private function debug_alert($msg)
	{
		if ($this->debug_mode)
			fputs($this->debug_dest, $msg);
	}
	
	
	/*
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
		case 'deduce':
			return $this->load_vector($varname, $array, self::deduce_array_type($array));
		case 'string':
			return $this->load_vector_of_strings($varname, $array);
		case 'number':
			return $this->load_vector_of_numbers($varname, $array);
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
		//old version that modified the argument array, a no-no
		//foreach($array as $k=>&$a)
		//	$a = self::num($a);
		//$instring = "$varname = c(" . implode(',',$array) . ')';
		
		/* build comma-delimited string of values */
		$instring = '';
		foreach($array as $k=>&$a)
			$instring .= ',' . self::num($a);
		
		/* now, add start and end of command, before sending to R */
		$instring = preg_replace('/\A,/', "$varname = c(", $instring);
		$instring .= ')';
		$this->execute($instring);
		
		//TODO: check this method works esp. with various kinds of zero
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
	 * (2) that fields are separated by \t
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
		//TODO
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
	
	
	/*
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
	 *                               not as an array, with the appropriate type. If this option is
	 *                               used for a multi-element vector, you only get the first element.
	 *                     //TODO others?
	 * 
	 * TODO Points to pointer. What if the object is a data frame, special object etc?
	 */
	public function read($varname, $mode = 'vector')
	{
		
		
		if (!$this->object_exists($varname))
		{
			$this->error("RFace: ERROR: Cannot read contents of nonexistent R object $varname!\n");
			return;
		}
		/* first, request the variable's contents */
		$data = $this->execute($varname);
		
		// TODO -- finish the function
		switch($mode)
		{
		case 'solo':
		case 'vector':
			// TODO convert text to zero-indexed array.
			
			if ($mode == 'solo')
				$output = $output[0];
				//  TODO: does type need adjusting?
			break;
		case 'verbatim':
			/* this one is easy */
			$output = $data;
			break;
		default:
			$this->error("RFace: ERROR: Unacceptable object read-mode $mode!");
			return;
		}
		
		return $output;
	}
	
	
	
	
	
	/**
	 * Gets an array of object-names in the active R workspace, as strings.
	 * 
	 * If there are no objects, returns an empty array. 
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
	 * If it doesn't exist, no error is raised.
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
		
		/* 'rfo' for "RFace Object" */
		for ($new = 'rfo'; true ; $new .= chr(rand(0x41, 0x5a)) )
		{
			for ($i = 0x41; $i <= 0x5a; $i++)
			{
				$curr = $new . chr($i);
				if (!in_array($curr, $names))
					return $curr;
			}
		}
		/* sanity check, should not be reached */
		$this->error("RFace: ERROR: New object name could not be generated.\n");
	}
	
	//TODO finish this method
	// I need to understand R charts system better to get things sorted out
	/**
	 * Saves the chart created by the given command to the specified filename.
	 */
	public function make_chart($filename, $chart_command)
	{
		if (is_dir($filename))
			$filename .= '.' . self::DEFAULT_CHART_FILENAME;
			// TODO: need to check for slash at end of varname
		
		// TODO set the graphics output to save to this file
		
		$this->execute($chart_command);
		
		// TODO reset the graphics output
		
		// TODO: hang on, aren't charts created by multiple commands
		// sometimes? If so, could this work?
		// (presumably yes, if the lines are separated
		// by \n)
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
			$this->execute("save.image(file=\"$path/.RData\")");
		else 
			$this->execute("save.image(file=\"$path\")");
	}

	/**
	 * Load an R workspace from a specified location.
	 * 
	 * If $path is a directory, this function loads the file .Rdata in that directory.
	 * 
	 * Otherwise, $path is treated as a filename (the method will add the .RData extension
	 * by default if the filename it is passed does not exist).
	 */	
	public function load_workspace($path)
	{
		if (is_dir($path))
		{
			if (is_file("$path/.RData"))
				$this->execute("load(file\"=$path/.RData\")");
			else
				$this->error("ERROR: can't load workspace, no file found at $path/.Rdata!");
		}
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
	
	
	
	
	
	/*
	 * Static methods
	 * 
	 * (mostly variable assessment functions at the moment...)
	 * 
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
