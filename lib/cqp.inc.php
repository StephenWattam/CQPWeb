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
 * This file contains the CQP class, which calls CQP as a child process
 * and handles all interaction with that excellent program
 */


class CQP
{
	/* MEMBERS */
	
	
	/* handle for the process */
	private $process;

	/* array for the input/output handles themselves to go in */
	private $handle;
	
	/* version numbers for the version of CQP we actually connected to */
	public $major_version;
	public $minor_version;
	public $beta_version;
	private $beta_version_flagged; /* indicates whether the beta version number was flagged by a "b" */
	public $compile_date;

	/* error handling */
	private $error_handler;	/* set to name of user-defined error handler
							   or to false if there isn't one */
	private $status;	 	/* status of last executed command ('ok' or 'error') */
	public $error_message;	/* array containing string(s) produced by last error */

	/* progress bar handling:
	 * set progress_handler to name of user-defined progressbar handler function,
	 * or to false if there isn't one */
	private $progress_handler;

	/* Boolean: used to avoid multiple "shutdown" attempts. */
	private $has_been_disconnected;


	/* debug */
	private $debug_mode;
	
	
	/* character set handling */
	private $corpus_charset;
	
	const CHARSET_UTF8 = 0;
	const CHARSET_LATIN1 = 1;
	
	
	/* the version of CWB that this class requires */
	const VERSION_MAJOR_DEFAULT = 2;
	const VERSION_MINOR_DEFAULT = 2;
	const VERSION_BETA_DEFAULT = 100;
//	const VERSION_MAJOR_DEFAULT = 3;
//	const VERSION_MINOR_DEFAULT = 2;
//	const VERSION_BETA_DEFAULT = 0;
//	temporary reversion while I work out build issues with 3.2.0
	


	/* METHODS */


	/**
	 * Create a new CQP object.
	 * 
	 * Note that both parameters MUST be absolute paths WITHOUT the initial '/'.
	 * 
	 * This function calls exit() if the backend startup is unsuccessful.
	 * 
	 * @param $path_to_cqp  String : Directory containing the cqp executable
	 * @param $cwb_registry String : Where to look for corpus registry files
	 */
	public function __construct($path_to_cqp, $cwb_registry)
	{
		/* check arguments */
		if (! is_executable("/$path_to_cqp/cqp") )
			exit("ERROR: CQP binary ``/$path_to_cqp/cqp'' is not executable! ");
		if (! is_dir("/$cwb_registry") )
			exit("ERROR: CWB registry dir ``/$cwb_registry'' seems not to exist! "); 
		
		/* create handles for CQP and leave CQP running in background */
		
		/* array of settings for the three pipe-handles */
		$io_settings = array(
			0 => array("pipe", "r"), /* pipe allocated to child's stdin  */
			1 => array("pipe", "w"), /* pipe allocated to child's stdout */
			2 => array("pipe", "w")  /* pipe allocated to child's stderr */
			);

		/* start the child process */
		/* NB: currently no allowance for extra arguments */
		$command = "/$path_to_cqp/cqp -c -r /$cwb_registry";

		$this->process = proc_open($command, $io_settings, $this->handle);

		if (! is_resource($this->process))
			exit("ERROR: CQP backend startup failed; command ==\n$command\n");

		/* $handle now looks like this:
		   0 => writeable handle connected to child stdin
		   1 => readable  handle connected to child stdout
		   2 => readable  handle connected to child stderr
	       now that this has been done, fwrite to $handle[0] passes input to  
		   the program we called; and reading from $handle[1] accesses the   
		   output from the program.
	
		   (EG) fwrite($handle[0], 'string to be sent to CQP');
		   (EG) fread($handle[1]);
			   -- latter will produce, 'whatever CQP sent back'
		*/
	
		/* process version numbers : "cqp -c" should print version on startup */
		$version_string = fgets($this->handle[1]);
		$version_string = rtrim($version_string, "\r\n");

		$version_pattern = 
			'/^CQP\s+(?:\w+\s+)*([0-9]+)\.([0-9]+)(?:\.(b?[0-9]+))?(?:\s+(.*))?$/';


		if (preg_match($version_pattern, $version_string, $matches) == 0)
			exit("ERROR: CQP backend startup failed");
		else
		{
			$this->major_version = (int)$matches[1];
			$this->minor_version = (int)$matches[2];
			$this->beta_version_flagged = false;
			$this->beta_version = 0;
			if (isset($matches[3]))
			{
				if ($matches[3][0] == 'b')
				{
					$this->beta_version_flagged = true;
					$this->beta_version = (int)substr($matches[3], 1);
				}
				else
				{
					$this->beta_version = (int)$matches[3];
				}
			}
			$this->compile_date  = (isset($matches[4]) ? $matches[4] : NULL);
			
			if ( ! $this->check_version() )
				exit("ERROR: CQP version too old ($version_string). v"
					. self::default_required_version() . 
					" or higher required.\n");			
    	}


		/* set other members */
		$this->error_handler = false;
		$this->status = 'ok';
		$this->error_message = array('');
		$this->progress_handler = false;
		$this->debug_mode = false;
		$this->has_been_disconnected = false;
		/* utf8 is the default charset, can be overridden when corpus is set. */
		$this->corpus_charset = self::CHARSET_UTF8;

		/* pretty-printing should be turned off for non-interactive use */
		$this->execute("set PrettyPrint off");
		/* so should the use of the progress bar; setting a handler reactivates it */
		$this->execute("set ProgressBar off");
	}
	/* end of constructor method */


	public function __destruct()
	{
		$this->disconnect();
	}

	/* This was originally the "fake distructor" function when this class was written for PHP 4.x;
	 * the shutdown code has been kept here rather than in __destruct() to avoid breaking old code. */ 
	function disconnect()
	{
		if ($this->has_been_disconnected)
			return;
		
		/* the PHP manual says "It is important that you close any pipes
		 * before calling proc_close in order to avoid a deadlock" 
		 * well, OK then! */
		
		if (isset($this->handle[0]))
		{
			fwrite($this->handle[0], "exit\n");
			fclose($this->handle[0]);
		}
		if (isset($this->handle[1]))
			fclose($this->handle[1]);
		if (isset($this->handle[2]))
			fclose($this->handle[2]);
		
		/* and finally shut down the child process so script doesn't hang */
		if (isset($this->process))
			proc_close($this->process);
			
		$this->has_been_disconnected = true;
	}
	/* end of method disconnect() */


	/* ------------------------ */
	/* Version handling methods */
	/* ------------------------ */
	

	/**
	 * Is the version of CQP (and, ergo, CWB) that was connected equal to,
	 * or greater than, a specified minimum?
	 * 
	 * Parameters: a minimum major, minor & beta version number. The latter two 
	 * default to zero; if no value for major is set, the default numbers are used.
	 * These defaults are public class constants.
	 * 
	 * Returns true if the current version (loaded in __construct) is greater than the minimum.
	 */
	function check_version($major = 0, $minor = 0, $beta = 0)
	{
		if ($major == 0)
			return $this->check_version_default();
		if  (
			($this->major_version > $major)
			||
			($this->major_version == $major && $this->minor_version > $minor)
			||
			($this->major_version == $major && $this->minor_version == $minor
				&& $this->beta_version >= $beta)
			)
			return true;
		else
			return false;
	}
	
	private function check_version_default()
	{
		return $this->check_version(
			self::VERSION_MAJOR_DEFAULT,
			self::VERSION_MINOR_DEFAULT,
			self::VERSION_BETA_DEFAULT
			);
	}
	
	
	
	/* ---------------------------------------- */
	/* Methods for controlling the CQP back-end */
	/* ---------------------------------------- */

	
	/**
	 * sets the corpus.
	 * 
	 * note: this is the same as running "execute" on the corpus name
	 * except that it implements a "wrapper" around the charset
	 * if necessary, allowing utf8 input to be converted to some other
	 * character set for future calls to $this->execute() 
	 */
	function set_corpus($corpus_id)
	{
		$this->execute($corpus_id);
		$infoblock = "\n" . implode("\n", $this->execute('info')) . "\n";
		
		if (preg_match("/\nCharset:\s+(\S+)\s/", $infoblock, $m) > 0)
		{
			switch($m[1])
			{
			case 'latin1':
			case 'iso-8859-1':
				$this->corpus_charset = self::CHARSET_LATIN1;
				break;
			default:
				/* anything else gets treated as utf8: typically "ascii", "utf8" */
				$this->corpus_charset = self::CHARSET_UTF8;
				break;
			}
		}
	}
	
	/**
	 * This is the same as "executing" the 'show corpora' command,
	 * but the function sorts through the output for you and returns 
	 * the list of corpora in a nice, whitespace-free array
	 */
	public function available_corpora()
	{
		$corpora = ' ' . implode("\t", $this->execute("show corpora"));
		$corpora = preg_replace('/\s+/', ' ', $corpora);
		$corpora = preg_replace('/ \w: /', ' ', $corpora);
		$corpora = trim($corpora);
		return explode(' ', $corpora);
	}
		
	
	/** execute a CQP command & returns an array of results */
	public function execute($command, $my_line_handler = false)
	{
		$result = array();
		if ( (!is_string($command)) || $command == "" )
		{
			$this->status = 'error';
			array_unshift($this->error_message, "ERROR: CQP->execute was called with no command");
			$this->error($this->error_message);
		}
		$command = $this->filter_input($command);
				
		/* change any newlines in command to spaces */
		preg_replace('/\n/', '/ /', $command);
		/* check for ; at end and remove if there */
		preg_replace('/\n/', '/;\s*$/', $command);
		
		if ($this->debug_mode == true)
			echo "CQP << $command;\n";

		/* send the command to CQP's stdin */			
		fwrite($this->handle[0], "$command;\n .EOL.;\n");
		/* that executes the command */

		/* then, get lines one by one from child stdout */
		while (strlen($line = fgets($this->handle[1])) > 0 )
		{
			/* delete carriage returns from the line */
			$line = trim($line, "\r\n");
			$line = preg_replace('/\r/', '', $line);

			/* special line due to ".EOL.;" marks end of output */
			/* avoids having to mess around with stream_select */
			if ($line == '-::-EOL-::-')
			{
				if ($this->debug_mode == true)
					echo "CQP --------------------------------------";
				break;
			}
			
			/* if line is a progressbar line */
			if (preg_match('/^-::-PROGRESS-::-/', $line) > 0)
			{
				$this->handle_progressbar($line);
				continue;
			}
			
			/* OK, so it's an ACTUAL RESULT LINE */
			if ($this->debug_mode == true)
				echo "CQP >> $line\n";
				
			if ($my_line_handler != false)
				/* call the specified function */
				$my_line_handler($line);
			else
				/* add the line to an array of results */
				array_push($result, $line);
		}

		/* check for error messages */
		$this->checkerr();

		/* return the array of results */
		return $this->filter_output($result);
	
	}
	/* end of method execute() */



	/**
	 * Like execute(), but only allows query commands, so is safer.
	 * Returns an array of results.
	 */
	public function query($command, $my_line_handler = false)
	{
		$result = array();
		$key = rand();
		$errmsg = array();
		$error = false;
		
		if ( (!is_string($command)) || $command == "" )
		{
			$this->status = 'error';
			$this->error_message = array_merge("ERROR: CQP->query was called with no command",
				$this->error_message);
			$this->error($this->error_message);
		}

		/* enter query lock mode */
		$this->execute("set QueryLock $key");
		if ($this->status != 'ok')
		{
			$errmsg = array_merge($errmsg, $this->error_message);
			$error = true;
		}
		
		/* RUN THE QUERY */
		$result = $this->execute($command, $my_line_handler);
		if ($this->status != 'ok')
		{
			$errmsg = array_merge($errmsg, $this->error_message);
			$error = true;
		}
	
		/* cancel query lock mode */
		$this->execute("unlock $key");
		if ($this->status != 'ok')
		{
			$errmsg = array_merge($errmsg, $this->error_message);
			$error = true;
		}
		
		if ($error)
			$this->status = 'error';
		else
			$this->status = 'ok';
		$this->error_message = $errmsg;
		
		return $result;
	}




	/**
	 * wrapper for ->execute that gets the size of the named saved query.
	 * method has no error coding - relies on the normal ->execute error checking.
	 */
	public function querysize($name)
	{
		if ((!is_string($name)) || $name == "")
		{
			$this->status = 'error';
			$this->error_message = array_merge("ERROR: CQP->querysize was passed an invalid argument",
				$this->error_message);
			$this->error($this->error_message);
		}
			
		$result = $this->execute("size $name");
		
		if (isset($result[0]))
			return (int) $result[0];
		else
			return 0;
			/* fails-safe to 0 */
	}




	
	/**
	 * dump named query result into table of corpus positions.
	 * returns an array of results 
	 */
	public function dump($subcorpus, $from = '', $to = '')
	{
		if ( !is_string($subcorpus)  || $subcorpus == ""  )
		{
			$this->status = 'error';
			$this->error_message = array_merge("ERROR: CQP->dump was passed an invalid argument",
				$this->error_message);
			$this->error($this->error_message);
		}
		
		$temp_returned = $this->execute("dump $subcorpus $from $to");

		$rows = array();

		foreach($temp_returned as $t)
			$rows[] = explode("\t", $t);
			
		return $rows;
	}



	/**
	 * undump named query result from table of corpus positions 
	 * $cqp->undump($named_query, $matches); 
	 *
	 * Construct a named query result from a table of corpus positions 
	 * (i.e. the opposite of the ->dump() method).  Each element of matches 
	 * is an array as follows:           [match, matchend, target, keyword] 
	 * that represents the anchor points of a single match.  The target and 
	 * keyword anchors are optional, but every anonymous array in the arg 
	 * list has to have the same length.  When the matches are not sorted in 
	 * ascending order, CQP will automatically create an appropriate sort 
	 * index for the undumped query result. 
	 */
	public function undump($subcorpus, $matches)
	{
		if ( (!is_string($subcorpus)) || $subcorpus == "" || (!is_array($matches)) )
		{
			$this->status = 'error';
			$this->error_message = array_merge("ERROR: CQP->undump was passed an invalid argument",
				$this->error_message);
			$this->error($this->error_message);
		}

		/* undump with target and keyword? this variable will determine it */
		$with = '';
		
		/* number of matches (= remaining arguments) */
		$n_matches = count($matches);

		/* need to read undump table from temporary file */
		$tempfile = new TempFile("this_undump.gz");
		
		$tempfile->write("$n_matches\n");
		foreach ($matches as $row)
		{
			$row_anchors = count($row);
			if (! isset($n_anchors))
			{
				$n_anchors = $row_anchors;
				/* find out whether we're doing targets, keywords etc */
				if ($n_anchors < 2 || $n_anchors > 4)
					exit("CQP: row arrays in undump table must have between "
						. "2 and 4 elements (first row has $n_anchors)");
				if ($n_anchors >= 3)
					$with = "with target";
				if ($n_anchors == 4)
					$with .= " keyword";
			}
			else
			{
				/* check that row matches */
				if (! ($row_anchors == $n_anchors) )
					exit("CQP: all rows in undump table must have the same "
						. "length (first row = $n_anchors, " 
						. "this row = $row_anchors)");
			}						
			$row_string = implode("\t", $row);
			$row_string .= "\n";
			$tempfile->write($row_string);
		}

		$tempfile->finish();

		/* now send undump command with filename of temporary file */
		$tempfile_name = $tempfile->get_filename();

		$this->execute("undump $subcorpus $with < 'gzip -cd $tempfile_name |'");

		/* delete temporary file */
		$tempfile->close();
		
		/* return success status of undump command */
		return $this->ok;
	}
	/* end of method undumnp() */







	/**
	 * compute frequency distribution over attribute values (single values
	 * or pairs) using group command; note that the arguments are specified
	 * in the logical order, in contrast to "group".
	 * 
	 * USAGE:  $cqp->group($named_query, "$anchor.$att", "$anchor.$att", $cutoff]);
	 * note: in this PHP version, unlike the Perl, all args are compulsory 
	 * 
	 * NB. Not tested yet.
	 */
	public function group($subcorpus, $spec1, $spec2, $cutoff)
	{
		if ( $subcorpus == "" || $spec1 == "")
		{
			$this->status = 'error';
			$this->error_message = array_merge("ERROR: CQP->group was passed an invalid argument",
				$this->error_message);
			$this->error($this->error_message);
		}

		if (preg_match(
			'/^(match|matchend|target[0-9]?|keyword)\.([A-Za-z0-9_-]+)$/',
			$spec1, $matches) == 0)
			exit("CQP:  invalid key \"$spec1\" in group() method\n");
			
		$spec1 = $matches[1] . " " . $matches[2];
		unset($matches);
		
		if ($spec2 != "")
		{
			if (preg_match(
				'/^(match|matchend|target[0-9]?|keyword)\.([A-Za-z0-9_-]+)$/',
				$spec2, $matches) == 0)
				exit("CQP:  invalid key \"$spec2\" in group() method\n");
			$spec2 = $matches[1] . " " . $matches[2];
 		}

		if ($spec2 != "")
			$command = "group $subcorpus $spec2 by $spec1 cut $cutoff";
		else
			$command = "group $subcorpus $spec1 cut $cutoff";
		
		$rows = array();
		
		$temp_returned = $this->execute($command);
		
		foreach($temp_returned as $t)
			/* erm, I think this will work! */
			$rows = array_merge($rows, split("\t", $t));
		
		return $rows;
	}








	/** compute freq distribution for match strings based on sort clause */
	public function count($subcorpus, $sort_clause, $cutoff = 1)
	{
		if ($subcorpus == "" || $sort_clause == "")
		{
			$this->status = 'error';
			$this->error_message = array_merge('ERROR: in CQP->count. ' 
				. 'USAGE: $cqp->count($named_query, $sort_clause [, $cutoff]);',
				$this->error_message);
			$this->error($this->error_message);
		}
		
		$rows = array();
		
		$temp_returned = $this->execute("count $subcorpus $sort_clause cut $cutoff");
	
		foreach($temp_returned as $t)
		{
			// erm, I think this will work!
			list ($size, $first, $string) = split("\t", $t, 3);
			$rows[] = array($size, $string, $first, $first+$size-1);
		}
		return $rows;
	}



	/* ----------------------- */
	/* Error-handling methods. */
	/* ----------------------- */

	
	
	/**
	 * Checks CQP's stderr stream for error messages .
	 * 
	 * IF THERE IS AN ERROR ON THE CHILD PROCESS'S STDERR, this function: 
	 * (1) moves the error message from stderr to $this->error_message 
	 * (2) prints an alert and the error message (up to 1024 lines)
	 * (3) returns true 
	 * 
	 * OTHERWISE, this function returns false.
	 */
	function checkerr()
	{
		$w = NULL;
		$e = NULL;
		$error_strings = array();

		/* is there anything on the child STDERR? */
		$ready = stream_select($r=array($this->handle[2]), $w, $e, 0);

		/* read all available lines from CQP's stderr stream */
		while ($ready > 0 && count($error_strings < 1024))
		{	
			$error_strings[] = trim(fgets($this->handle[2]), "\r\n");
			
			/* check stream again before reiterating */
			$ready = stream_select($r=array($this->handle[2]), $w, $e, 0);
		}

		if (count($error_strings) > 0)
		{
			/* there has been an error */
			$this->status = 'error';
			$this->error_message = $error_strings;
			array_unshift($error_strings, "**** CQP ERROR ****");
			$this->error($error_strings);

			return true;
		}
		else
			return false;
	}




	/** method to read the CQP object's status variable */
	public function status()
	{
		return $this->status;
	}
	
	
	
	
	/** 
	 * simplified interface for checking for CQP errors: 
	 * returns TRUE if status is ok, otherwise FALSE 
	 */
	public function ok()
	{
		return ($this->status == 'ok');
	}



	
	/**
	 * Returns the last error reported by CQP. 
	 * 
	 * This is not reset automatically, so you need to check $cqp->status 
	 * in order to find out whether the error message was actually produced 
	 * by the last command.
	 *  
	 * The return value is an array of error message lines sans newlines.
	 */
	public function get_error_message()
	{
		return $this->error_message;
	}

	/** does same as get_error_message, but with all strings in the array rolled together */
	public function get_error_message_as_string()
	{
		//TODO delete commented-out old version once it's established nothing broke.
		//$output = '';
		//foreach($this->error_message as $msg)
			//$output .= "$msg\n";
		//return $output;
		return implode("\n", $this->error_message);
	}

	/** does same as get_error_message, but with (X)HTML paragraph and linebreak tags */
	public function get_error_message_as_html()
	{
		$output = '<p class="errormessage">';
		foreach ($this->error_message as $msg)
			$output .= "<br/>$msg\n";
		$output .= "</p>\n";
		return $output;
	}
	
	/** 
	 * Clears out all the error messages, returning the error message array
	 * to its original state (contains only 1 empty string.
	 * 
	 * Can only be called by user, the object itself won't call it from within
	 * another method. 
	 */
	public function clear_error_messages()
	{
		$this->error_message = array('');
	}	


	

	/** 
	 * takes as argument: 1 argument -- an array of strings to return to caller 
	 * reports errors in the object and CQP error messages 
	 */
	private function error($message)
	{
		if ($this->error_handler != false)
		{
			$local_handler = $this->error_handler;
			$local_handler($message);
		}
		else
		{
			foreach($message as $current)
				echo "$current\n";		
		}
	}





	/** set user-defined error handler */
	public function set_error_handler($handler)
	{
		$this->error_handler = $handler;
	}
	



	
	/** set on/off progress bar display and specify function to deal with it */
	public function set_progress_handler($handler = false)
	{
		if ($handler != false)
		{
			$this->execute("set ProgressBar on");
			$this->progress_handler = $handler;
		}
		else
		{
			$this->execute("set ProgressBar off");
			$this->progress_handler = false;
		}
	}




	
	/**
	 * execution-pause handler to process information from the progressbar. 
	 * Note: makes calls to $this->progress_handler, with arguments 
	 * ($pass, $total, $progress [0 .. 100], $message) 
	 */
	function handle_progressbar($line = "")
	{
		if ($this->debug_mode)
			echo "CQP $line\n";
		if ($this->progress_handler == false)
			return;
			
		list($junk, $pass, $total, $message) = split("\t", $line);
		
		/* extract progress percentage, if present */
		if (preg_match('/([0-9]+)\%\s*complete/', $message, $match) > 0)
			$progress = $match[1];
		else 
			$progress = "??";
		
		$this->progress_handler($pass, $total, $progress, $message);
	}
	





	/** 
	 * Switch debug mode on or off, and return the FORMER state (whatever it was).
	 * 
	 * Note that if a NULL argument or no argument is passed in, then debug_mode
	 * will not be changed -- only its value will be returned. 
	 * 
	 * The argument should be a Boolean. If it isn't, it will be typecast to
	 * bool according to the usual PHP rules.
	 */
	public function set_debug_mode($newstate = NULL)
	{
		$oldstate = $this->debug_mode;
		
		if (!is_null($newstate))
			$this->debug_mode = (bool)$newstate;

		return $oldstate;
	}


	/* --------------- */
	/* Charset methods */
	/* --------------- */


	/** 
	 * Switch the character set encoding of an input string from the caller.
	 * 
	 * Input strings from caller are always utf8. 
	 * 
	 * This method filters them to another encoding, if necessary.
	 */
	private function filter_input($string)
	{
		switch($this->corpus_charset)
		{
		case self::CHARSET_UTF8:
			return $string;
		case self::CHARSET_LATIN1:
			return utf8_decode($string);
		}
	}
	/** 
	 * Switch the character set encoding of an output string to be sent to the caller.
	 * 
	 * Output strings from the CQP object are always utf8.
	 * 
	 * This method filters output in other encodings to utf8, if necessary.
	 * 
	 * The function can also cope with an array of strings. 
	 */
	private function filter_output($string)
	{
		/* output may be an array of strings: in which case map across all strings within it
		 * this is done WITHIN the switch, rather than by calling this function recursively,
		 * to save function call overhead in the (most common) case where the underlying
		 * corpus is UTF8. 
		 */
		switch($this->corpus_charset)
		{
		case self::CHARSET_UTF8:
			return $string;
		case self::CHARSET_LATIN1:
			if (is_array($string))
			{
				foreach($string as $k => &$v)
					$string[$k] = utf8_encode($string);
				return $string;
			}
			else
				return utf8_encode($string);
		}
		// TODO. Use the iconv function to add other Latin-X charsets, once CWB itself supports them.
	}
	/** 
	 * Gets a string describing the charset of the currently loaded corpus,
	 * or NULL if no corpus is loaded.
	 */
	public function get_corpus_charset()
	{
		switch ($this->corpus_charset)
		{
		case self::CHARSET_UTF8: 	return 'utf8';
		case self::CHARSET_LATIN1:	return 'latin1';
		default:					return NULL;
		}
	}
	
	
	
	/* -------------- */
	/* STATIC METHODS */
	/* -------------- */

	
	/** backslash-escapes any CQP-syntax metacharacters in the argument string */
	public static function escape_metacharacters($s)
	{
		$replacements = array(
			'"' => '\"',
			'(' => '\(',
			')' => '\)',
			'|' => '\|',
			'[' => '\[',
			']' => '\]',
			'.' => '\.',
			'?' => '\?',
			'+' => '\+',
			'*' => '\*'		
			);
		/* call str_replace for backslash to make sure this happens first */
		return strtr(str_replace('\\', '\\\\', $s), $replacements);
	}
	
	/** Get a string containing the class's default required version of CWB. */ 
	public static function default_required_version()
	{
		return self::VERSION_MAJOR_DEFAULT
			. '.' . self::VERSION_MINOR_DEFAULT
			. '.' . self::VERSION_BETA_DEFAULT;
	}

	/**
	 * Runs the connection procedure as in __construct(), but does it 
	 * with positively paranoid safety checks at every stage.
	 * 
	 * The results of every safety check are collected together in a
	 * string which is the return value.
	 * 
	 * No class variables are set (obviously since this is a static 
	 * function) and the process is shut down at the end, regardless
	 * of success or failure.
	 */
	public static function diagnose_connection($path_to_cqp, $cwb_registry)
	{
		$infoblob = '';
		
		$infoblob .= "Beginning diagnostics on CQP child process connection.\n\n";
		$infoblob .= "Using following configuration variables:\n";
		$infoblob .= "    \$path_to_cqp = ``$path_to_cqp''\n";
		$infoblob .= "    \$cwb_registry = ``$cwb_registry''\n";
		$infoblob .= "\n";
		
		/* all checks are wrapped in a do ... while(false) to allow a break to go straight to shutdown */
		/* goto shutdown would be nice as well but we don't want to count on PHP 5.3+ */
		do {
			/* check path to cqp is a real directory */
			$infoblob .= "Checking that /$path_to_cqp exists... ";
			if (!is_dir("/$path_to_cqp"))
			{
				$infoblob .= "\n    CHECK FAILED. Check that /$path_to_cqp exists"
					. " and contains the CQP executable.\n";
				break;
			}
			else
				$infoblob .= " yes it does!\n\n";
			
			// check that this user has read/execute permissions to it TODO
			
			/* check that cqp exists within it */
			$infoblob .= "Checking that CQP program exists... ";
			if (!is_file("/$path_to_cqp/cqp"))
			{
				$infoblob .= "\n    CHECK FAILED. Check that /$path_to_cqp"
					. " contains the CQP executable.\n";
				break;
			}
			else
				$infoblob .= " yes it does!\n\n";
			
			/* check that cqp is executable TODO */
			$infoblob .= "Checking that CQP program is executable by this user... ";
			if (!is_executable("/$path_to_cqp/cqp"))
			{
				$infoblob .= "\n    CHECK FAILED. Check that /$path_to_cqp/cqp"
					. " is executable by the username this script is running under.\n";
				break;
			}
			else
				$infoblob .= " yes it is!\n\n";

			/* check that cwb_registry is a real directory */
			$infoblob .= "Checking that /$cwb_registry exists... ";
			if (!is_dir("/$cwb_registry"))
			{
				$infoblob .= "\n    CHECK FAILED. Check that /$cwb_registry exists"
					. " and contains the CQP executable.\n";
				break;
			}
			else
				$infoblob .= " yes it does!\n\n";
			
			// check that this user has read/execute permissions to it TODO
			$infoblob .= "Checking that CWB registry is readable by this user... ";
			if (!is_readable("/$cwb_registry"))
			{
				$infoblob .= "\n    CHECK FAILED. Check that /$cwb_registry"
					. " is readable by the username this script is running under.\n";
				break;
			}
			else
				$infoblob .= " yes it is!\n\n";
			
			// do an experimental startup TODO
			
			// call proc_ get_ status() and check each field is as it should be TODO
			
			// get the version info TODO
			
			// check the version info TODO
			
			// write an experimental line to the process in
			// and check the process out (use EOL command) TODO
			
			/* if all that is working then the diagnostic is complete */ 
			$infoblob .= "The connection to the CQP child process was successful.\n";

		} while (false);
		
		/* if the process was not created successfully, we do not need ot close it */
		if (is_resource($process))
		{
			$infoblob .= "\nAttempting to shut down test process...\n";
		
			// shutdown procedure here.
			// TODO
			if (false)
				;
			else
				$infoblob .= "Process shutdown was successful.\n";
		}
		
		return $infoblob;
	}

} /* end of class CQP */

?>