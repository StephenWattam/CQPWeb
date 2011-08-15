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
 * Class representing a CQP child process and handling 
 * all interaction with that excellent program.
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
	
	/* Note, unlike the CWB internals, there is no separate value for ASCII. ASCII counts as UTF8. */ 
	const CHARSET_UTF8 		= 0;
	const CHARSET_LATIN1 	= 1;
	const CHARSET_LATIN2 	= 2;
	const CHARSET_LATIN3 	= 3;
	const CHARSET_LATIN4 	= 4;
	const CHARSET_CYRILLIC 	= 5;
	const CHARSET_ARABIC 	= 6;
	const CHARSET_GREEK 	= 7;
	const CHARSET_HEBREW 	= 8;
	const CHARSET_LATIN5 	= 9;
	const CHARSET_LATIN6 	= 10;
	const CHARSET_LATIN7 	= 13;
	const CHARSET_LATIN8 	= 14;
	const CHARSET_LATIN9 	= 15;
	/* the literal values are ISO-8859 part numbers, but this is only for neatness; these numbers
	 * are not actually used for their values. */


	/* array mapping the constants above to strings for iconv() */
	private static $charset_labels = array(
		self::CHARSET_UTF8			=> 'UTF-8',
	 	self::CHARSET_LATIN1		=> 'ISO-8859-1',
	 	self::CHARSET_LATIN2		=> 'ISO-8859-2',
		self::CHARSET_LATIN3 		=> 'ISO-8859-3',
		self::CHARSET_LATIN4 		=> 'ISO-8859-4',
		self::CHARSET_CYRILLIC		=> 'ISO-8859-5',
		self::CHARSET_ARABIC 		=> 'ISO-8859-6',
		self::CHARSET_GREEK 		=> 'ISO-8859-7',
		self::CHARSET_HEBREW 		=> 'ISO-8859-8',
		self::CHARSET_LATIN5 		=> 'ISO-8859-9',
		self::CHARSET_LATIN6 		=> 'ISO-8859-10',
		self::CHARSET_LATIN7 		=> 'ISO-8859-13',
		self::CHARSET_LATIN8 		=> 'ISO-8859-14',
		self::CHARSET_LATIN9 		=> 'ISO-8859-15'
		);
	
	/* the version of CWB that this class requires */
	const VERSION_MAJOR_DEFAULT = 3;
	const VERSION_MINOR_DEFAULT = 0;
	const VERSION_BETA_DEFAULT = 0;
	


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

	/**
	 * Disconnects the child process.
	 * 
	 * This was originally the "fake distructor" function when this class was written for PHP 4.x;
	 * the shutdown code has been kept here rather than in __destruct() to avoid breaking calls to 
	 * ->disconnect() that exist in CQPweb. 
	 */ 
	public function disconnect()
	{
		if ($this->has_been_disconnected)
			return;
		
		/* the PHP manual says "It is important that you close any pipes
		 * before calling proc_close in order to avoid a deadlock" --
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
	public function check_version($major = 0, $minor = 0, $beta = 0)
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
	 * Sets the corpus.
	 * 
	 * note: this is the same as running "execute" on the corpus name
	 * except that it implements a "wrapper" around the charset
	 * if necessary, allowing Utf8 input to be converted to some other
	 * character set for future calls to $this->execute(). 
	 */
	public function set_corpus($corpus_id)
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
	 * Gets a list of available corpora as a numeric array.
	 * 
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
		
	
	/** 
	 * Executes a CQP command & returns an array of results (output lines from CQP),
	 * or false if an error is detected.
	 */
	public function execute($command, $my_line_handler = false)
	{
		$result = array();
		if ( (!is_string($command)) || $command == "" )
		{
			$this->add_error("ERROR: CQP->execute was called with no command");
			$this->error();
			return false;
		}
		$command = $this->filter_input($command);
		
		/* change any newlines in command to spaces */
		$command = preg_replace("/\n/", ' ', $command);
		/* check for ; at end and remove if there */
		$command = preg_replace('/;\s*$/', '', $command);
		
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
		if ($this->checkerr())
			return false;

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
			$this->add_error("ERROR: CQP->query was called with no command");
			$this->error();
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
		
		/* note, we will probably not get to here, since ->execute()
		 * will have clled ->error() already. But this works if no
		 * external error handler was called. */
		if ($error)
			$this->status = 'error';
		else
			$this->status = 'ok';
		$this->error_message = $errmsg;
		
		return $result;
	}




	/**
	 * A wrapper for ->execute that gets the size of the named saved query.
	 * method has no error coding - relies on the normal ->execute error checking.
	 */
	public function querysize($name)
	{
		if ((!is_string($name)) || $name == "")
		{
			$this->add_error("ERROR: CQP->querysize was passed an invalid argument");
			$this->error();
		}
			
		$result = $this->execute("size $name");
		
		if (isset($result[0]))
			return (int) $result[0];
		else
			return 0;
			/* fails-safe to 0 */
	}




	/**
	 * Dumps a named query result into table of corpus positions.
	 * Returns an array of results. 
	 */
	public function dump($subcorpus, $from = '', $to = '')
	{
		if ( !is_string($subcorpus)  || $subcorpus == ""  )
		{
			$this->add_error("ERROR: CQP->dump was passed an invalid argument");
			$this->error();
			return false;
		}
		
		$temp_returned = $this->execute("dump $subcorpus $from $to");

		$rows = array();

		foreach($temp_returned as $t)
			$rows[] = explode("\t", $t);
			
		return $rows;
	}



	/**
	 * Undumps a named query result from a table of corpus positions.
	 * 
	 * Usage:
	 * $cqp->undump($named_query, $matches); 
	 *
	 * Constructs a named query result from a table of corpus positions 
	 * (i.e. the opposite of the ->dump() method).  Each element of $matches 
	 * is an array as follows:
	 *           [match, matchend, target, keyword] 
	 * that represents the anchor points of a single match.  The target and 
	 * keyword anchors are optional, but every anonymous array in the arg 
	 * list has to have the same length.  When the matches are not sorted in 
	 * ascending order, CQP will automatically create an appropriate sort 
	 * index for the undumped query result. 
	 * 
	 * An optional extra argument specifies an absolute path to a directory 
	 * where the necessary temporary file can be stored; if none is given,
	 * the method will attempt to use the temporary directory (i.e. /tmp, which
	 * is the default location for CWB temp files).
	 */
	public function undump($subcorpus, $matches, $datadir = '')
	{
		if ( (!is_string($subcorpus)) || $subcorpus == "" || (!is_array($matches)) )
		{
			$this->add_error("ERROR: CQP->undump was passed an invalid argument");	
			$this->error();
			return false;
		}

		/* undump with target and keyword? this variable will determine it */
		$with = '';
		
		/* number of matches ( = remaining hits) */
		$n_matches = count($matches);

		/* need to read undump table from a temporary file, because entering a dumpfile
		 * from stdin requires cqp -e, which we don't have.
		 * 
		 * Allow a place on disk to be specified.
		 */
		if ($datadir != '')
			$datadir = rtrim($datadir, '/') . '/';

		$tempfile = new CQPInterchangeFile($datadir, true, 'this_undump');
		/* is this next line still necessary? Possibly not, but may be more efficient */
		$tempfile->write("$n_matches\n");
		
		/* find out whether we're doing targets, keywords etc */
		$n_anchors = count(reset($matches));
		if ($n_anchors < 2 || $n_anchors > 4)
		{
			$this->add_error("CQP: row arrays in undump table must have between "
				. "2 and 4 elements (first row has $n_anchors)");
			$this->error();
			$tempfile->close();
			return false;
		}
		if ($n_anchors >= 3)
			$with = "with target";
		if ($n_anchors == 4)
			$with .= " keyword";
		
		/* we iterate the array,  making sure it's valid, before writing to temp */
		foreach	($matches as &$row)
		{
			$row_anchors = count($row);
			/* check that row matches */
			if (! ($row_anchors == $n_anchors) )
			{
				$this->add_error("CQP: all rows in undump table must have the same "
					. "length (first row = $n_anchors, this row = $row_anchors)");
				$this->error();
				$tempfile->close();
				return false;
			}
			$tempfile->write(implode("\t", $row) . "\n");
		}

		$tempfile->finish();

		/* now send undump command with filename of temporary file */
		$tempfile_name = $tempfile->get_filename();
		$this->execute("undump $subcorpus $with < 'gzip -cd $tempfile_name |'");
		// TODO. Does this *really* need gzipping?

		/* delete temporary file */
		$tempfile->close();
		
		/* return success status of undump command */
		return $this->ok();
	}
	/* end of method undump() */








	/**
	 * Computes frequency distribution over attribute values (single values
	 * or pairs) using CQP's group command.
	 * 
	 * Note that the arguments are specified in the logical order, in contrast to "group".
	 * 
	 * USAGE:  $cqp->group($named_query, "$anchor.$att", "$anchor.$att", $cutoff]);
	 * note: in this PHP version, unlike the Perl, all args are compulsory.
	 * 
	 * NB. Not tested yet.
	 */
	public function group($subcorpus, $spec1, $spec2, $cutoff)
	{
	//TODO tests should be for "empty" really....
		if ( $subcorpus == "" || $spec1 == "")
		{
			$this->add_error("ERROR: CQP->group was passed an invalid argument");
			$this->error();
			return false;
		}

		if (0 == preg_match(
					'/^(match|matchend|target[0-9]?|keyword)\.([A-Za-z0-9_-]+)$/',
					$spec1, $matches) )
		{
			$this->add_error("CQP:  invalid key \"$spec1\" in group() method\n");
			$this->error;
			return false;
		}
			
		$spec1 = $matches[1] . " " . $matches[2];
		unset($matches);
		
		if ($spec2 != "")
		{
			if (0 == preg_match(
						'/^(match|matchend|target[0-9]?|keyword)\.([A-Za-z0-9_-]+)$/',
						$spec2, $matches) )
			{
				$this->add_error("CQP:  invalid key \"$spec2\" in group() method\n");
				$this->error;
				return false;
			}
			$spec2 = "{$matches[1]} {$matches[2]}";
 		}

		if ($spec2 != "")
			$command = "group $subcorpus $spec2 by $spec1 cut $cutoff";
		else
			$command = "group $subcorpus $spec1 cut $cutoff";
		
		$rows = array();
		
		$temp_returned = $this->execute($command);
		
		foreach($temp_returned as &$t)
			$rows[] = explode("\t", $t);
		
		return $rows;
	}








	/** Computes the frequency distribution for match strings based on a sort clause. */
	public function count($subcorpus, $sort_clause, $cutoff = 1)
	{
		if ($subcorpus == "" || $sort_clause == "")
		{
			$this->add_error('ERROR: in CQP->count. USAGE: $cqp->count($named_query, $sort_clause [, $cutoff]);',
				$this->error_message);
			$this->error();
			return false;
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
	 * Checks CQP's stderr stream for error messages.
	 * 
	 * IF THERE IS AN ERROR ON THE CHILD PROCESS'S STDERR, this function: 
	 * (1) moves the error message from stderr to $this->error_message 
	 * (2) prints an alert and the error message (up to 1024 lines)
	 * (3) returns true 
	 * 
	 * OTHERWISE, this function returns false.
	 */
	public function checkerr()
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




	/** A method to read the CQP object's status variable. */
	public function status()
	{
		return $this->status;
	}
	
	
	
	
	/** 
	 * A simplified interface for checking for CQP errors: 
	 * returns TRUE if status is ok, otherwise FALSE.
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

	/** Does same as get_error_message, but with all strings in the array rolled together. */
	public function get_error_message_as_string()
	{
		return implode("\n", $this->error_message);
	}

	/** 
	 * Does same as get_error_message, but with (X)HTML paragraph and linebreak tags;
	 * the parameter dictates what the value of the "class" attribute on the p-tag is to be.
	 * 
	 * If no argument is supplied, the paragraph is given no class.
	 */
	public function get_error_message_as_html($p_class = '')
	{
		$class = (empty($p_class) ? '' : " class=\"$p_class\"");
		return "<p$class>" . implode("\n<br/>", $this->error_message) . "\n</p>\n";
	}
	
	/** 
	 * Clears out all the error messages, returning the error message array
	 * to its original state (contains only 1 empty string).
	 * 
	 * Can only be called by user, the object itself won't call it from within
	 * another method. 
	 */
	public function clear_error_messages()
	{
		$this->error_message = array('');
	}	


	

	/**
	 * Function to call when the object encounters an error.
	 *  
	 * It takes as argument an array of strings to print; these 
	 * strings report errors in the object and CQP error messages. 
	 *
	 * If no argument is specified, the internal array of error 
	 * messages is used instead.
	 * 
	 * The strings are just printed to stdout if there is no error
	 * handler set; otherwise, the error handler callback is used.
	 */
	private function error($messages = NULL)
	{
		if (!is_array($messages))
			$messages =& $this->error_message;
		/* we need call_user_func() because trying to call $this->error_handler directly
		 * may cause PHP to think we are trying to call a method called "error_handler". */
		if ($this->error_handler != false)
			call_user_func($this->error_handler, $messages);
		else
			echo implode("\n", $messages);
	}

	/**
	 * Adds an error at the top of the stack, without triggering the error
	 * handler or printing errors.
	 * 
	 * The ->status is also set to "error".
	 *
	 * Normal internal usage: first call add_error with the new error message,
	 * then EITHER carry on, OR call ->error() and then return false.
	 */
	private function add_error($message)
	{
		$this->status = 'error';
		array_unshift($this->error_message, $message);
	}



	/** Sets a user-defined error handler function. */
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
	 * bool according to the usual PHP rules (except NULL, of course).
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
		if ($this->corpus_charset == self::CHARSET_UTF8)
			return $string;
		else
			return iconv('UTF-8', self::$charset_labels[$this->corpus_charset] . '//TRANSLIT', $string);
		/* 
		switch($this->corpus_charset)
		{
		case self::CHARSET_UTF8:
			return $string;
		case self::CHARSET_LATIN1:
			return utf8_decode($string);
		}
		*/
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
		 * this is done WITHIN the else, rather than by calling this function recursively,
		 * to save function call overhead in the (most common) case where the underlying
		 * corpus is UTF8. 
		 */
		if ($this->corpus_charset == self::CHARSET_UTF8)
			return $string;
		else
		{
			if (is_array($string))
			{
				foreach($string as $k => &$v)
//					$string[$k] = utf8_encode($v);
					$string[$k] = iconv(self::$charset_labels[$this->corpus_charset], 'UTF-8', $v);	 
				return $string;
			}
			else
//				return utf8_encode($string);
				return iconv(self::$charset_labels[$this->corpus_charset], 'UTF-8', $string);			
		}
	}
	/** 
	 * Gets a string describing the charset of the currently loaded corpus,
	 * or NULL if no corpus is loaded.
	 */
	public function get_corpus_charset()
	{
		switch ($this->corpus_charset)
		{
		case self::CHARSET_UTF8: 		return 'utf8';
		case self::CHARSET_LATIN1:		return 'latin1';
		case self::CHARSET_LATIN2:		return 'latin2';
		case self::CHARSET_LATIN3:		return 'latin3';
		case self::CHARSET_CYRILLIC:	return 'cyrillic';
		case self::CHARSET_ARABIC:		return 'arabic';
		case self::CHARSET_GREEK:		return 'greek';
		case self::CHARSET_HEBREW:		return 'hebrew';
		case self::CHARSET_LATIN5:		return 'latin5';
		case self::CHARSET_LATIN6:		return 'latin6';
		case self::CHARSET_LATIN7:		return 'latin7';
		case self::CHARSET_LATIN8:		return 'latin8';
		case self::CHARSET_LATIN9:		return 'latin9';
		default:						return NULL;
		}
	}
	
	
	
	/* -------------- */
	/* STATIC METHODS */
	/* -------------- */

	
	/** Backslash-escapes any CQP-syntax metacharacters in the argument string */
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
	
	/** Gets a string containing the class's default required version of CWB. */ 
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
		
		/* all checks are wrapped in a do ... while(false) to allow a break to go straight to shutdown;
		 * goto shutdown would be nice as well but we don't want to count on PHP 5.3+ */
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






/**
 * Interchange files are self-deleting temporary files. 
 * 
 * They are used to write some data; when you then 'finish' the file it hangs around
 * as a closed file, whose name you can send to another program (or you can read from it
 * via the object instead). 
 * 
 * The file is automatically deleted when you 'close' it, or when the object is destroyed.
 * 
 * The file will be either a plain file or a gzipped plain file.
 * 
 * Typical usage is as follows.
 * 
 * $intfile = new CQPInterchangeFile($my_temp_directory);
 * 
 * $intfile->write($my_data);
 * 
 * $intfile->finish();
 * 
 * send_to_some_other_module($intfile->get_filename());
 * 
 * // or...
 * 
 * $lines_to_do_something_with = $infile->read();
 * 
 * $intfile->close();
 * 
 * This object is based on the CWB::TempFile object from the Perl interface, but with
 * simplified internals (doesn't use pipes, only gives two file format options instead 
 * of several).
 * 
 * This class requires the Zlib extension.
 */
class CQPInterchangeFile
{
	/* Members */
	
	/** Stores a reading/writing handle */
	private $handle;
	
	/** Full filepath (absolute or relative) */
	private $name;
	
	/** Status flag: W == writing, F == finished, R == reading, D == deleted */
	private $status;
	
	/** Is the file written/read as a gz file or not?  */
	private $compression;
	
	/** The file protocol wrapper (dependent on $this->compression) */
	private $protocol;
	
	/** Callback for error handler function. */
	private $callback;


	/* METHODS */
	
		
	/**
	 * Note, the constructor interface is a bit different to the CWB::TempFile interface in the Perl
	 * module.
	 * 
	 * You MUST specify a directory for the file to be put in, the default is the working directory.
	 * 
	 * If $gzip is true, the file will be compressed.
	 * 
	 * If $nameroot is specified (letters, numbers, dash and underscore only!) it will be used as the 
	 * basis for the file's name. But it won't be precisely this name, of course. 
	 */
	public function __construct($location = '.', $gzip = false, $nameroot = 'CQPInterchangeFile')
	{
		/* process arguments */
		$this->compression = (bool)$gzip;
		
		$nameroot = preg_replace('/[^A-Za-z0-9_\-]/', '', $nameroot);
		if (empty($nameroot))
			$nameroot = 'CQPInterchangeFile';
			
		/* remove rightmost / or \ from folder, as below it assumed there will be no slash */
		$location = rtrim($location, '/\\');
		if (empty($location))
			$location = '.';
		
		$unique = base_convert(uniqid(), 16, 36);
		$suffix = ( $this->compression ? '.gz' : '' );
		
		/* deeply unlikely you'll need this bit.... */
		for ($this->name = "$location/$nameroot-$unique$suffix", $n = 1; file_exists($this->name) ; $n++ )
			$this->name = "$location/$nameroot-$unique-$n$suffix";
		
		$this->protocol = ( $this->compression ? 'compress.zlib://' : '' );
		$this->handle = fopen($this->protocol . $this->name, 'w');
		$this->status = "W";
	}
	
	/** Destructor; closes the file if not closed manually. */
	public function __destruct()
	{
		if ($this->status != "D")
			$this->close();
	}
	
	/** Writes a line to the interchange file. */
	public function write($line)
	{
		if ($this->status != "W")
			$this->error( "CQPInterchangeFile: Can't write to file {$this->name} with status {$this->status}" );
		
		if ( false === fwrite($this->handle, $line) )
 			$this->error("CQPInterchangeFile: Error writing to file {$this->name}");
	}
	
	
	/** Stops writing the file, and closes its handle */
	public function finish()
	{
		if (! ($this->status == "W") )
			$this->error("CQPInterchangeFile: Can't finish file {$this->name} with status {$this->status}");

		/* close the file */
		if ( ! fclose($this->handle))
 			$this->error("CQPInterchangeFile: Error closing file {$this->name}");
		$this->status = "F";
	}
	
	/** 
	 * Reads a line from the file (opening before doing so if necessary). 
	 * 
	 * In case of error, the return values are the same as for fgets().
	 */
	public function read()
	{
		if ($this->status == "D")
			$this->error("CQPInterchangeFile: Can't read from file {$this->name}, already deleted.");
				
		if ($this->status == "W")
			$this->finish();
			
		if ($this->status != "R")
		{
			$this->handle = fopen($this->protocol . $this->name, 'r');
			$this->status = "R";
		}
		/* read a line */
		return fgets($this->handle);
	}

	/** Restart reading of the tempfile, by closing and re-opening it. */
	public function rewind()
	{
		if ($this->status == "D" || $this->status == "W")
			$this->error("CQPInterchangeFile: Can't rewind file {$this->name} with status {$this->status}");
		
		/* if rewind is called before first read, it does nothing */
		if ($this->status != "R")
			;
		else
		{
			if (!fclose($this->handle))
	 			$this->error("CQPInterchangeFile: Error closing file " . $this->name . "\n");
			$this->handle = fopen($this->protocol . $this->name, "r");
		}
	}
	
	/**
	 * Finishes reading or writing, and closes and deletes the file.
	 * 
	 * No return value (for either success or error conditions
	 */
	public function close()
	{
		if ( ($this->status == "W" || $this->status == "R") && isset($this->handle) )
		{
			if (! fclose($this->handle))
 				$this->error( "CQPInterchangeFile: Error closing file " . $this->name);
 			unset ($this->handle);
  		}
  		
		if (is_file($this->name)) 
		{
			if (!unlink($this->name))
				$this->error( "CQPInterchangeFile: Could not unlink file " . $this->name);
		}
		$this->status = "D";
	}
	
	
	/**
	 * Get the path of the temporary file.
	 * 
	 * It may be relative or absolute.
	 */
	public function get_filename()
	{
		return $this->name;
	}



	/**
	 * Get the file's current status as an (uppercase) string.
	 * 
	 * Example usage: echo $interchange_file->status() . "\n"; 
	 */
	public function get_status()
	{
		switch($this->status)
		{
		case "W":		return "WRITING";
		case "F":		return "FINISHED";
		case "R":		return "READING";
		case "D":		return "DELETED";
		}
	}
	
	
	
	
	
	/* error handling functions */
	
	/**
	 * Allows a callback function to be specified for error messages
	 * (rather than exiting the program, which is the default error handling).
	 * 
	 * The callback can be anything that will work as the first argument of the PHP function
	 * call_user_func().
	 */
	public function set_error_callback($callback)
	{
		$this->callback = $callback;
	}
	
	/**
	 * Sends an error meessage to the user-specified callback, or aborts the program
	 * if no callback is set.
	 */ 
	private function error($message)
	{
		if ( ! empty($this->error_callback) )
			call_user_func($this->error_callback, $message);
		else
			exit($message);
	}
	
} /* end of class CQPInterchangeFile */

?>