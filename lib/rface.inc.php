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
 * class RFace:
 * 
 * PHP interface to the R statistical environment.
 * 
 * Usage:
 * 
 *     $r = new RFace($path_to_r);
 * 
 * ... where $path_to_r is an absolute or relative path to the directory containing 
 * the R executable (if you leave it unspecified, the environment path will be checked) ...
 * 
 *     $result = $r->execute("command(s) to be fed to R here");
 * 
 * To explicitly shut down child process:
 * 
 *     unset($r);
 * 
 * Other methods (many of which are to-do!) wrap-around execute() 
 * to provide a friendlier API for various uses of R. 
 * 
 * IMPORTANT NOTE : much of the code in this class is still untested.
 * 
 */
class RFace 
{
	/* class constants */
	
	const DEFAULT_CHART_FILENAME = 'R-chart';
	
	const DEFAULT_CHART_FILETYPE = 'png';
	
	const DEFAULT_WORKSPACE_FILENAME = '.RData';
	
	/* member variables : general */
	
	/** handle for the process */
	private $process;

	/** array for the input/output stream handles themselves to go in */
	private $handle;
	
	/** variable that remembers path on system of the exectuable that was started up */
	private $which;
	
	/** function (or array of object/classname + method at [0] and [1]) for handling textual output from R */
	private $output_handler_callback = false;
	
	
	/* caches for oft-used R calls */
	
	/** cache of the list of object names; begins as false, set it back to false to clear it! */
	private $object_list_cache = false;
	
	
	/* variables for error handling and debug logging */
	
	/** has there been an error? If there has, this variable will not be true. */
	private $ok = true;
	
	/** most recent error message */
	private $last_error_message;
	
	/** setting: should the object exit the program on detection of an error? */
	private $exit_on_error;
	
	/** setting: if true, debug info will be printed to debug_dest */
	private $debug_mode;
	
	/** setting: flag determining whether or not the stream in $debug_dest will be closed on destruct() */
	private $debug_dest_autoclose = false;
	
	/** stream that debug messages will be sent to; if none specified, constructor sets it to php://output */
	private $debug_dest;
	
	
	
	
	
	
	
	
	/**
	 * Constructor function for RFace class.
	 * 
	 * There are no compulsory arguments. The path to R should be a relative or absolute
	 * path of the directory where the R executable lives (i.e. DON'T put "/R" or "/R.exe"
	 * or whatever at the end of this string). If it is false, however, the normal PATH 
	 * variable from PHP's $_ENV superglobal array is used.
	 *  
	 * Note that the debug destination stream can be set up at construct-time, or later, using
	 * the dedicated functions.
	 */
	function __construct($path_to_r = false, $debug = false, $debug_dest = false, $debug_dest_autoclose = false)
	{
		/* Constructor errors are critical, so let's turn on exit_on_error. */
		$this->exit_on_error = true;

		/* passthru settings for debug mode for this object */
		$this->set_debug($debug);
		if (false === $debug_dest)
		{
			$debug_dest = fopen("php://output", "w");
			$debug_dest_autoclose = true;
		}
		$this->set_debug_destination($debug_dest, $debug_dest_autoclose);

		/* work out whether the call to R will need a '.exe' */
		$ext = (strtoupper(substr(php_uname('s'), 0, 3)) == 'WIN' ? '.exe' : '' );

		/* check that we know where the R program is... */
		if (empty($path_to_r))
		{
			/* detect whether or not R is on the path.... */
			$found = false;
			foreach(explode(PATH_SEPARATOR, $_ENV['PATH']) as $path)
				if (is_executable(rtrim($path, DIRECTORY_SEPARATOR) . '/R' . $ext))
				{
					$found = true;
					break;
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
		
		$command = "$path_to_r/R$ext --slave --no-readline";
		
		$this->process = proc_open($command, $io_settings, $this->handle);
		
		if (! is_resource($this->process))
			$this->error("RFace: ERROR: R backend startup failed; command: $command\n");
		else
			$this->debug_alert("RFace: R backend successfully started up.\n");
			
		/* remember the executable */
		$this->which = "$path_to_r/R$ext";
		
		/* finally, turn down the severity level of errors... user can always turn it up again. */
		$this->exit_on_error = false;
	}
	
	
	
	/** 
	 * Destructor function for the RFace class.
	 */
	function __destruct()
	{
		if (isset($this->handle[0]))
		{
			fwrite($this->handle[0], 'q()\n');
			fclose($this->handle[0]);
		}
		if (isset($this->handle[1]))
			fclose($this->handle[1]);
		if (isset($this->handle[2]))
			fclose($this->handle[2]);
		
		$this->debug_alert("RFace: Pipes to R backend successfully closed.\n");
		
		/* and finally shut down the child process so script doesn't hang*/
		if (isset($this->process))
			$stat = proc_close($this->process);

		$this->debug_alert("RFace: R slave process has been closed with termination status [ $stat ].\n"
						   . "\tRFace object will now destruct.\n");
		
		if ($this->debug_dest_autoclose)
		{
			$this->debug_alert("RFace: Closing debug alert stream after this message.\n");
			if ( ! fclose($this->debug_dest) )
				$this->debug_alert("RFace: ERROR: Failed to close debug alert stream.\n");
		}	
	}




	/**
	 * Main execution method: returns an array of lines of output from R.
	 * 
	 * This may be an empty array if R didn't print anything.
	 * 
	 * False is returned in case of error.
	 * 
	 * If $output_handler_callback is specified, it will be called on the
	 * output (single string with lines separated by \n but other
	 * whitespace trimmed out), and execute() will pass back the return value of 
	 * the callback function. 
	 * 
	 * If $line_handler_callback is NOT specified, the function checks whether
	 * one has been set at the object level ($this->output_handler_callback). If it
	 * has, that is used, and its return value is sent back. 
	 * 
	 * Note that empty lines are ALWAYS skipped (never collected for the output handler
	 * OR never added to the return array).
	 * 
	 */
	function execute($command, $output_handler_callback = false)
	{
		if ($output_handler_callback === false)
			if ($this->output_handler_callback !== false)
				$output_handler_callback = $this->output_handler_callback;
		
		/* execute can change the number of objects, so clear object-list cache */
		$this->object_list_cache = false;
		
		$command = trim($command);
		
		if ( empty($command) )
		{
			$this->error("RFace: ERROR: RFace::execute() was called with no command\n");
			return false;
		}
		
		$command .= PHP_EOL;

		$this->debug_alert("RFace: R << $command");

		/* send the command to R's [IN] */
		if (false === fwrite($this->handle[0], $command))
		{
			$this->error("RFace: ERROR: problem writing to the R input stream\n");
			return false;
		}
		/* that executes the command ... */
		if (false === fwrite($this->handle[0], "cat(\"-::-EOL-::-\\n\")" . PHP_EOL))
		{
			$this->error("RFace: ERROR: problem writing to the R input stream\n");
			return false;
		}

		$result = array();
		
		/* then, get lines one by one from [OUT] */
		while (1)
		{
			$line = fgets($this->handle[1]);

			/* delete whitespace from the line; */
			$line = trim($line, " \t\r\n");
			
			/* check for delimiter being printed for end-of-output */
			if ($line == '-::-EOL-::-')
				break;
				
			/* blank lines NEVER added to the array */
			if (empty($line))
				continue;

			/* an output line we ALWAYS ignore; an empty statement terminated by ; is not invalid! */
			if ($line == 'Error: unexpected \';\' in ";"')
				continue;

			/* add the line to an array of results */
			$result[] = $line;

			$this->debug_alert("RFace: R >> $line\n");
		}
		/* Note, no attempt is made to do anything with R's [ERR] stream, at least for now! */

		if (!empty($output_handler_callback))
		{
			/* call the specified function or class/object method */
			$callback_return = call_user_func($output_handler_callback, implode(PHP_EOL, $result));
			$return_print = (string) $callback_return;
			if (strlen($return_print) > 16)
				$return_print = '[extra-long string]';
			$this->debug_alert("RFace: output-collector >> output-handler-callback >> $return_print\n");
			return $callback_return;
		}
		else
			/* return the array of result lines */
			return $result;
	}


	/**
	 * Specify a callback function to be used on output from execute().
	 *
	 * The callback can be (a) a closure (b) a string naming a function (c) an array of an object plus a method name
	 * (d) an array of a class name plus a method name (that is, any of the usual options for callbacks in PHP).
	 *
	 * To use no output handler, pass false.
	 */
	public function set_output_handler($callback)
	{
		if (false === $callback)
		{
			$this->output_handler_callback = false;
			$this->debug_alert("RFace: Output handler wiped, output handling disabled.\n");
		}
		else if (is_array($callback))
		{
			if ( isset($callback[0], $callback[1]) && count($callback) == 2)
			{
				/* case one:  we have been passed an object or class and its method */
				$callback_name = '[not known]';
				if ( is_object($callback[0]) && is_callable($callback, false, $callback_name) )
				{
					$this->output_handler_callback = $callback;
					$this->debug_alert("RFace: Output handler accepted ( $callback_name, object call ).\n");
				}
				else if (class_exists($callback[0] && method_exists($callback[0], $callback[1])))
				{
					$this->output_handler_callback = $callback;
					$this->debug_alert("RFace: Output handler accepted ( $callback[0]::$callback[1], static call ).\n");
			 	}
				else
					$this->error("RFace: ERROR: Uncallable object/class method passed as output handler.\n");
			}
			else
				$this->error("RFace: ERROR: Invalid array layout for output handler callback.\n");
		}
		else if  (is_callable($callback))
		{
			$this->output_handler_callback = $callback;
			$callback_name = ( is_string($callback) ? $callback : '[anonymous function]');
			$this->debug_alert("RFace: Output handler accepted ( $callback_name ).\n");
		}
		else
			$this->error("RFace: ERROR: Unrecognisable output handler function was passed ( $callback ).\n");
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
	 * Sets the destination stream for debug messages.
	 * 
	 * Typically, you'd pass in an open file handle.
	 * 
	 * The second parameter determines whether the stream is self-closing; by
	 * default it isn't, but if it is, then the object destructor will attempt to
	 * close the stream with fclose().
	 */
	public function set_debug_destination($new_stream, $autoclose = false)
	{
		$x = @get_resource_type($new_stream);
		if ($x != 'file' && $x != 'stream')
			$this->error("RFace: ERROR: Stream specified for printing debug messages is not valid.\n");
		$this->debug_dest = $new_stream;
		$this->debug_dest_autoclose = (bool) $autoclose;	
	}
	
	
	/*
	 * error control & debug messaging
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
	 *
	 * Returns false if there has been an error.
	 */
	public function ok()
	{
		return $this->ok;
	}
	
	/**
	 * Gets the most recent error message.
	 */
	public function error_message()
	{
		return $this->last_error_message;
	}
	
	/**
	 * Raises an error from within the RFace.
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
		$this->ok = false;
		
		if ($msg == false)
			$msg = "RFace: ERROR: General R interface error!\n";
		if ($line != false)
			$msg .= "\t... at line $line\n";
		
		$this->last_error_message = $msg;
		$this->debug_alert($msg);
		if ($this->exit_on_error)
			exit($msg);
	}
	
	/**
	 * Print a message to the debug stream, if debug output is enabled.
	 */
	private function debug_alert($msg)
	{
		if ($this->debug_mode)
			fputs($this->debug_dest, $msg);
	}
	
	/**
	 * Tells you what R executable is being used.
	 * 
	 * This variable is filled in by the constructor. It can't be set, as it is 
	 * permanent: you can only read it.
	 * 
	 * If you passed in a path, then obviously, this will be the R in the location
	 * you specified. If you didn't pass in a path, this should tell you which
	 * R executable was found.
	 * 
	 * Mostly for debugging purposes.
	 */
	public function get_which_R() { return $this->which; }
	
	
	/**
	 * Gets the version of the current R executable.
	 * 
	 * @param $what  Can have one of the following values:
	 *               "all" -- return the R version string in full
	 *               "version" -- return just the version number xx.yy.zz
	 *               "date" -- return just the build date yyyy-mm-dd 
	 */
	public function get_R_version($what = "all")
	{
		$v = $this->execute("R.Version.string");	
		switch($what)
		{
		case "version":
			if ( 0 < preg_match('/R version (\d+\.\d+\.\d+)/', $v, $m))
				return $m[1];
			else
				$this->error("RFace: ERROR: Could not parse version string for version number.\n");
			break;
		case "date":
			if ( 0 < preg_match('/R version \d+\.\d+\.\d+/ \((\d+-\d+-\d+)\)', $v, $m))
				return $m[1];
			else
				$this->error("RFace: ERROR: Could not parse version string for build date.\n");
			break;
		default: 
		case "all":
			return $v;
		}
	}
	
	
	/*
	 * load methods (move data object from PHP to R)
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
		/* build comma-delimited string of values */
		$instring = '';
		foreach($array as &$a)
			$instring .= ',' . self::num($a);
		
		/* now, add start and end of command, before sending to R */
		$instring = "$varname = c(" . ltrim($instring, ",") . ')';		
		$r = $this->execute($instring);
		
		/* successful load will have returned empty array */
		return (empty($r) && is_array($r));
			
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
	 * (3) that objects = rows and fields = columns
	 * (4) if $header_row, then the first line is treated as containing column names
	 *     that can be used as variable labels
	 * (5) if $header_col, then the first column is treated as containing row labels
	 *     that can be used as object names
	 * 
	 * If $data is an array, then the following assumptions are made:
	 * 
	 * (1) that each member of the array represents a row of the table (data object)
	 * (2) that each member is a one-dimensional array representing properties of objects
	 * (3) that all inner arrys are of the same length and same type (that is, they
	 *     would work as R vectors)
	 * (4) if $header_row, then the first inner array is treated as containing column names
	 * 
	 * 
	 * @param $varname       The name the resulting variable is to have in R. If an object of
	 *                       that name already exists, it will be overwritten.
	 * @param $data          The data frame to be loaded (string representation of table, 
	 *                       or 2d array). Passed by reference, but not modified.
	 * @param $header_row    Boolean: does the data contain a header row? (Header row = 
	 *                       everything up to or including the first \n in a string; or,
	 *                       the first member of the array contains a header element.)
	 * @param $invert_array  Boolean: if true, the two dimensions of an array are swopped
	 * 
	 * @return               Boolean: true = success, 
	 */
	public function load_data_frame($varname, &$data, $header_row = true, $header_col = true, $invert_array = false)
	{
		if (is_string($data))
			return $this->load_data_frame_from_string($varname, $data, $header_row, $header_col);
		else if (is_array($data))
			return $this->load_data_frame_from_2darray($varname, $data, $header_row, $header_col, $invert_array);
	}
	
	/** helper function called only by @see load_data_frame */
	private function load_data_frame_from_string($varname, &$data, $header_row, $header_col)
	{
		$tmpnam = $this->new_object_name();
		$this->execute("$tmpnam = \"$data\"");
		$this->execute("$varname = read.table(text=$tmpnam, header=" . ($header_row ? 'TRUE' : 'FALSE') . ($header_col ? ", row.names=1)" : ")"));
		$this->drop_object($tmpnam);
		return true;
	}
	
	/** helper function called only by @see load_data_frame */
	private function load_data_frame_from_2darray($varname, &$data, $header_row, $header_col, $invert_array)
	{
		if ($invert_array)
		{
			$temp_data = array();
			// TODO: move data (inverted)to $temp_data
			return $this->load_data_frame_from_2darray($varname, $temp_data, $header_row, $header_col, false);
		}
		
		// TODO
	}
	
	
	/*
	 * read methods (move data object from R to PHP)
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
	 *                     'object' -- the default, create a PHP value matching the R object as closely 
	 *                                 as possible. This works for the different types of vector. It may 
	 *                                 not work for other object types; any object type not covered
	 *                                 will fallback to 'verbatim' mode.
	 *                     'verbatim' -- create a string containing the verbatim description
	 *                                   of the object from R's output (including whitespace/linebreaks).
	 *                     'solo' -- for use with one-element vectors: returns it as a single variable,
	 *                               not as an array, with the appropriate type. If this option is
	 *                               used for a multi-element vector, you only get the first element.
	 *                               If it is used for something that doesn't come out as a vector,
	 *                               then you'll get an error.
	 * 
	 * General TODO :
	 * 
	 * This function covers all the obvious object types (vectors of basic types - bool, int, double, string.
	 * But it needs to have more "special" object types added.
	 * For example:
	 *  * Data frame to 2D array
	 *  * 
	 * TODO Points to ponder. What if the object is a data frame, special object etc?
	 * Data frame should be converted into 2D array. Special object should probably be
	 * returned as string (only user knows exactly what to do with it) that just contains
	 * whatever R printed.
	 * 
	 */
	public function read($varname, $mode = 'object')
	{
		if (!$this->object_exists($varname))
		{
			$this->error("RFace: ERROR: Cannot read contents of nonexistent R object $varname!\n");
			return;
		}
		/* first, request the variable's contents as an array */
		$data = $this->execute($varname);
		
		$verbatim_fallthrough = false;
		
		/* generate object, or solo value, or verbatim text output. */
		switch($mode)
		{
		case 'solo':
		case 'object':
			/* 
			 * This switch converts various types of R object to PHP values.
			 * The output is always stored in $output.
			 * If no conversion routine exists, verbatim is used as the fallback.
			 */
			switch ($this->typeof($varname))
			{
			case 'NULL':
				/* PHP's NULL and R's NULL correspond closely. */
				$output = NULL;
				break;
				
			case 'logical':
				/* vector -> zero-indexed array of booleans. */
				$output = $this->read_vector_output_to_string($data);
				foreach ($output as &$o)
					$o = ($o === 'TRUE');
				break;
				
			case 'integer':
				/* vector -> zero-indexed array of ints. */
				// TODO. Factors also have typeof = integer. (But class() = factor).
				$output = $this->read_vector_output_to_string($data);
				foreach ($output as &$o)
					$o = (int) $o;
				break;
				
			case 'double':
				/* vector -> zero-indexed array of floats. */
				$output = $this->read_vector_output_to_string($data);
				foreach ($output as &$o)
					list($o) = sscanf(strtolower($o), '%e');
				break;
				
			case 'character':
				/* vector -> zero-indexed array of strings. */
				$data = $this->read_vector_output_to_string($data);
				$output = array();
				for ($i = 0, $n = strlen($data) ; $i < $n ; $i++)
				{
					/* we are outside a value : test for start of value */
					if ($data[$i] == '"')
					{
						/* we are inside a value : scroll to end of value */
						for ($j = $i+1 ; 1 ; $j++)
						{
							if ($data[$j] == '\\')
							{
								/* neither this byte nor the next one is the closing delimiter */
								$j++;
								continue;
							}
							if ($data[$j] == '"')
								break;
						}
						
						/* i = index of opening quote; j = index of closing quote. */
						$output[] = stripcslashes(substr($data, $i+1, $j-($i+1)));
						/* we need stripcslashes() because R char values are printed out with \n, \t etc. */ 
						
						/* set $i to $j, it will then increment and the next loop of the for
						 * will start at the first character after the closing " */
						$i = $j;
					}	
				} 
				break;
			
			/* TODO : More data types here? */
			
			// case 'list':
				// NB. data frames are a type of list.
			// case 'special':
			// case 'builtin':
			// case 'complex':
			// case 'raw':
			// case 'environment':
			// case 'S4':
			
			/* data types covered by default:
			 *     -- closure (we want a verbatim print of the function's code)
			 */
			default:
				$verbatim_fallthrough = true;
				break;
				
			}	/* end of switch typeof(varname) */
			
			/* final operations in case object / solo:
			 * (1) check for solo mode, and de-array if found.
			 * (2) fallthrough to verbatim if we didn't have an algorithm!
			 */
			if ($mode == 'solo' && is_array($output))
				$output = $output[0];
			if (! $verbatim_fallthrough)
				break;
			/* end of case "object" */
				
		case 'verbatim':
			/* this one is easy */
			$output = implode(PHP_EOL, $data);
			break;
			
		default:
			$this->error("RFace: ERROR: Unacceptable object read-mode $mode!\n");
			return;
		}
		
		return $output;
	}
	
	/**
	 * Support function for ->read().
	 * 
	 * Converts an output array from ->execute() into a single string,
	 * with the line-start index numbers removed.
	 */
	private function read_vector_output_to_string(&$array)
	{
		$r = '';
		/* note that if we have something like "character(0)" it will be returned as 
         * the single member of the array. */
		foreach ($array as &$a)
			$r .= ' ' . array_pop(explode(']',$a, 2));
		return trim($r, " \t\r\n");
	}
	
	/**
	 * Support function for ->read().
	 * 
	 * Converts an output array from ->execute() (array of lines)
	 * into a PHP array of strings split on whitespace.
	 * 
	 * Important note: will only work for vecytors of booleans or numbers -
	 * not for vectors of strings, which need a different approach.
	 */
	private function read_vector_output_to_array(&$array)
	{
		$s = $this->read_vector_output_to_string($array);
		/* this bit deals with empty vectors: character(0), numeric(0) etc. */
		if ( 0 < preg_match('/^\w+\(0\)$/', $s) )
			return array();
		return preg_split('/\s+/', $s, NULL, PREG_SPLIT_NO_EMPTY);
	}
	
	
	/*
	 * Object manipulation methods 
	 */
	
	
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
	 * Gets the type of an object in the active R workspace.
	 * 
	 * Returns a string indicating the type (same strings as in R)
	 * or false if there was an error. 
	 */
	public function typeof($obj)
	{
		if ( ! $this->object_exists($obj))
		{
			/* we'll get an error message from R if we send this in.
			 * Ergo, we should pre-empt, and send an error message of our own. */
			$this->error("RFace: ERROR: Cannot call typeof() on nonexistent R object $obj!\n");
			return false;
		}
		else
		{
			/* we know it will just be a "[1] ..." printout, so we can shortcut */
			list($rawtext) = $this->execute("typeof($obj)");
			return trim(substr($rawtext, 4),'"');
		}
	}
	
	/**
	 * Gets the classname of an object in the active R workspace.
	 * 
	 * Returns a string with the classname, or false if there was an error.
	 */
	public function classof($obj)
	{
		/* see "typeof" for comments on the checks performed */
		if ( ! $this->object_exists($obj))
		{
			$this->error("RFace: ERROR: Cannot call classof() on nonexistent R object $obj!\n");
			return;
		}
		else
		{
			list($rawtext) = $this->execute("class($obj)");
			return trim(substr($rawtext, 4),'"');
		}
	}
	
	/** Convenience alias for "classof" for those who prefer the PHP function name */
	public function get_class($obj) { return $this->classof($obj); }

	/**
	 * Gets the size of an object (number of objects it contains)
	 * in the active R workspace; equivalent to the R function length().
	 * 
	 * Returns an integer indicating the count of objects
	 * or false if there was an error.
	 */
	public function sizeof($obj)
	{
		/* see "typeof" for comments on the checks performed */
		if ( ! $this->object_exists($obj))
		{
			$this->error("RFace: ERROR: Cannot call sizeof() on nonexistent R object $obj!\n");
			return;
		}
		else
		{
			list($rawtext) = $this->execute("length($obj)");
			return (int) trim(substr($rawtext, 4));
		}
	}
	
	/** Convenience alias for "sizeof" for those who prefer PHP's alternative terminology! */
	public function count($obj) { return $this->sizeof($obj); }
	
	/** Convenience alias for "sizeof" for those who prefer R to PHP terminology! */
	public function length($obj) { return $this->sizeof($obj); }
	

	
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
	 * currently exist in the active R workspace.
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
	
	
	/*
	 * Graph / chart creation methods
	 */
	
	
	/**
	 * Saves the chart created by the given command to the specified filename.
	 * 
	 * To specify a chart that needs multiple commands to create, either (a)
	 * use execute() for all but the final command; or, specify multiple commands
	 * within this method's second argument by separating them using semi-colons.  
	 * 
	 * The type of the file is determined automagically by the file extension
	 * of the file path provided. 
	 */
	public function make_chart($filename, $chart_command)
	{
		if (is_dir($filename))
			$filename = rtrim($filename, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DEFAULT_CHART_FILENAME.'.'.self::DEFAULT_CHART_FILETYPE;
		
		/* auto-guess the function to use, based on the filename extension. */
		switch (substr($filename, -4))
		{
		case '.jpg':
			$func = 'jpeg';
			break;
		case '.bmp':
		case '.pdf':
		case '.png':
			$func = substr($filename, -3);
			break;
		default:
			switch (substr($filename, -5))
			{
			case '.tiff':
			case '.jpeg':
				$func = substr($filename, -4);
				break;
			default:
				$func = self::DEFAULT_CHART_FILETYPE;
				return;
			}
			break;
		}
		
		/* set the graphics output to save to this file */
		$this->execute("$func(file = \"$filename\")");
		
		/* create our chart... */
		$this->execute($chart_command);
		
		/* reset the graphics output */
		$this->execute("dev.off()");
	}
	
	
	/*
	 * Workspace control methods 
	 */
	
	
	/**
	 * Save the R workspace to a specified location.
	 * 
	 * If $path is a directory, the workspace is saved as the file .Rdata in that directory.
	 * 
	 * Otherwise, $path is assumed to be a target filename and the workspace saved in
	 * that location.
	 * 
	 * The default value for the path is PHP's current working directory.
	 * 
	 * Returns true for success, false for failure.
	 */
	public function save_workspace($path = false)
	{
		if ($path == false)
			$path = getcwd();
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if (is_dir($path))
			$r = $this->execute('save.image(file="' . $path . DIRECTORY_SEPARATOR . self::DEFAULT_WORKSPACE_FILENAME . '")');
		else if (is_writable(dirname($path)))
			$r = $this->execute("save.image(file=\"$path\")");
		else
			return false;

		/* successful save will have returned empty array. */
		return (empty($r) && is_array($r));
	}

	/**
	 * Load an R workspace from a specified location.
	 * 
	 * If $path is a directory, this function loads the file .Rdata in that directory.
	 * 
	 * Otherwise, $path is treated as a filename (the method will add the .RData extension
	 * by default if the filename it is passed does not exist).
	 * 
	 * The default value for the path is the current working directory.
	 * 
	 * Returns true for success, false for failure.
	 */
	public function load_workspace($path = '.')
	{		
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if (is_dir($path))
		{
			if (is_file("$path/.RData"))
				$r = $this->execute("load(file\"=$path/.RData\")");
			else
				$this->error("ERROR: can't load workspace, no file found at $path/.Rdata!");
		}
		else if (is_file($path))
			$r = $this->execute("load(file\"=$path\")");
		else if (is_file($path.'.RData'))
			$r = $this->execute("load(file\"=$path.RData\")");
		else
			$this->error("ERROR: can't load workspace, no file found at $path!");
			
		/* successful load will have returned empty array. */
		return (empty($r) && is_array($r));
	}
	
	
	/** 
	 * Loads the requested R library package (if it is available).
	 */
	public function load_library($lib)
	{
		if ($this->library_is_available($lib))
			$r->execute("library($lib)");
	}
	
	/** 
	 * Checks whether the specified R library package is available.
	 */
	public function library_is_available($lib)
	{
		return in_array($lib, $this->list_libraries());
	}
	
	/**
	 * Returns an array of available R library packages.
	 */
	public function list_libraries()
	{
		$obj = $this->new_object_name();
		
		$this->execute("$obj = .packages(all.available = TRUE)");
		
		$arr = $this->read($obj);
		
		$this->drop_object($obj);
		
		return $arr;
	}
	
	
	
	
	/*
	 * Static methods
	 * 
	 * (mostly variable manipulation functions at the moment...)
	 * 
	 */
	
	
	
	/**
	 * Deduces whether an (incoming PHP) array has a single "type" or not.
	 * 
	 * Returns a string describing the type: this is "string" if
	 * every value in the array is a string, "number" if every value
	 * is either an int or a float, "mixed" if a value of the type
	 * contrary to the initially established type was detected, "undefined"
	 * if a value that is neither string nor number was detected.
	 * 
	 * The string "array" can also be returned, if every component of
	 * the array is itself an array. NB: in this case there is no recursive checking.
	 * 
	 * Note: "mixed" and "undefined" are error values, and if they are
	 * returned, it says nothing about the presence of errors of the
	 * *other* type further down the array.
	 */
	private static function deduce_array_type(&$array)
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
	 * (Because a typecast would have to be to either int or float, but
	 * using PHP's context-based type juggling covers either.)
	 */
	public static function num($string)
	{
		return 1 * $string;
	}



	/* end of class RFace */
}
?>