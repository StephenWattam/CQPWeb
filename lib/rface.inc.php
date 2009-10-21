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






// IMPORTANT NOTE : much of the contents of this file are untested as of now.











/**
 * class RFace
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
	/* member variables */
	/* handle for the process */
	private $process;

	/* array for the input/output handles themselves to go in */
	private $handle;
	
	
	
	private $debug_mode;
	/* if true, debug info will be printed to stdout */
	
	
	
	
	
	
	//TODO: potential problems .... (?)
	
	
	
	
	
	
	
	
	/* constructor */
	function __construct($path_to_r = FALSE)
	{
		if ($path_to_r === FALSE)
		{
			/* try to deduce the path using Unix "which" */
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
			exit("ERROR: R backend startup failed; command ==\n$command\n");
		
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
		
		/* and finally shut down the child process so script doesn't hang*/
		if (isset($this->process))
			proc_close($this->process);
	}




	/* main execution function */
	function execute($command, $line_handler_callback = false)
	{
		if ( (!is_string($command)) || $command == "" )
		{
// TODO: error handling!
//			$this->status = 'error';
//			$this->error_message = array_merge("ERROR: RFace->execute was called with no command",
//				$this->error_message);
//			$this->error($this->error_message);
		}
				

		
		if ($this->debug_mode == true)
			echo "R << $command;\n";

		/* send the command to R's stdin */			
		fwrite($this->handle[0], $command);
		/* that executes the command */

		$result = array();
		
		
		/* then, get lines one by one from [OUT] */
		while (strlen($line = fgets($this->handle[1])) > 0 )
		{
			/* delete whitespace from the line */
			$line = trim($line, " \t\r\n");

			if ($this->debug_mode)
				echo "R >> $line\n";
			
			if ($line_handler_callback !== false)
				/* call the specified function */
				$line_handler_callback($line);
			else
				/* add the line to an array of results */
				array_push($result, $line);
		}

		/* check for error messages */
//TODO
//		$this->checkerr();

		/* return the array of results */
		return $result;

	}
	
	
	/**
	 * load functions
	 */
	
	public function load_vector_from_array(&$array, $type = 'deduce')
	{
		switch ($type)
		{
		case 'string':
			return load_vector_from_array_of_strings($array);
		case 'number':
			return load_vector_from_array_of_numbers($array);
		case 'deduce':
			return load_vector_from_array($array, $this->deduce_array_type($array));
			// should deduce_array_type be a static method ? probably. actually, private static.
		}	
	}
	
	public function load_vector_from_array_of_strings(&$array)
	{
		//TODO
	}
	
	public function load_vector_from_array_of_numbers(&$array)
	{
		//TODO
	}
	
	
	public function list_objects()
	{
		return $this->execute("ls()");
		//TODO: additional procesing of output required?	probably....
	}
	
	
	
	
	
	
	
	/* error management functions */


/* end of class RFace */
}
?>
