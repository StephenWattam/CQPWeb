<?php
/*
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


// TODO get rid of the CWBTempFile object.


/* CWBTempFile objects are temporary file objects 

 	examples of usage of constructor:

	$tf = new CWBTempFile();               (chooses name automatically)
	
	$tf = new CWBTempFile("NP-Chunks");    (uses name beginning with 
									 "NP-Chunks")
	$tf = new CWBTempFile("NP-Chunks.gz"); (tell module to write gzipped 
									 tempfile, using OpenFile magic)
*/


/* depends on create_pipe_handle_constants(); having previously been called */





/*

there will be a class here someday
which provides a child-process-like interface to the cwb command line utilities
encapsulating all that stuff instead of having exec() or system() calls all over the place.

(this will allow cwb::uncreate_corpus, xcwb::corpus_exists etc. to use the $this-> values for
the registry and datadir rather than calling on global variables.

but for now there are just a bunch of functions, php-extension-style
*/

/**
 * Class encapsulating the interface to the CWB command-line utilities.
 */
class CWB
{
	//paths do NOT have a / prepended in this class so should have a / in them if they are absolute
	private $path_binaries;
	private $path_datadir;
	private $path_registry;
	
	public function __construct($path_to_cwb, $cwb_datadir, $cwb_registry)
	{
		//TODO check dirs exist, store them in the appropriate object variables
		// (use realpath)
	}


	// TODO
	// public functions for setting $path_*, each of which should return the current value if
	// they are passed NULL
	
	// the only methods writen so far are to-be-used replacements for the cwb_* functions in the file
	
	
	/** Checks if a cwb-"corpus" exists for the specified lowercase name. */
	public function corpus_exists($corpus_name)
	{
		return  (	
					is_file("{$this->path_registry}/$corpus_name") 
					&& 
					is_dir("{$this->path_datadir}/$corpus_name")
				);
	}
	
	/**
	 * Removes a corpus from the CWB system. The argument must be the *lowercase*
	 * version of the CWB-corpus-name.
	 */
	public function uncreate_corpus($corpus_name)
	{
		//TODO
		//note the uncreate function below also tinkers with the MySQL. Bad modularity! Fix this.
		// it also relies on recursive_delete_directory
		// and it does a superuser check.
	}

}	/* end of class CWB */







/* check if a cwb-"corpus" exists for the specified lowercase name */
function cwb_corpus_exists($corpus_name)
{
	global $cwb_datadir;
	global $cwb_registry;
	
	// use cwb-describe-corpus instead?? naah this is quicker
	
	if  ( is_file("/$cwb_registry/$corpus_name") && is_dir("/$cwb_datadir/$corpus_name") )
		return true;
	else
		return false;	
}




/* this function only removes a corpus from the cwb system; it doesn't do anything in mysql */
/* argument must be the *lowercase* version of the name (ie *with* the __freq suffix, if nec.) */
function cwb_uncreate_corpus($corpus_name)
{
	global $cwb_datadir;
	global $cwb_registry;
	global $username;
	
	/* only superusers are allowed to do this! */
	if (! user_is_superuser($username))
		return;

	$dir_to_delete = "/$cwb_datadir/$corpus_name";
	$reg_to_delete = "/$cwb_registry/$corpus_name";
	
	/* delete all files in the directory and the directory itself */
	if (is_dir($dir_to_delete))
		recursive_delete_directory($dir_to_delete);
	
	/* delete the registry file */
	if (is_file($reg_to_delete))
		unlink($reg_to_delete);
		
	/* is there a text indextable derived from this cwb freq "corpus"? if so, delete */
	/* nb there will only be one *IF* this is a __freq corpus */
	do_mysql_query("drop table if exists freq_text_index_$corpus_name");

}

























class CWBTempFile
{
	/* MEMBERS */
	
	/* handle */
	var $file_handle;

	/* filename */
	var $name;

	/* W == writing, F == finished, R == reading, D == deleted */
	var $status;
	
	/* is the file opened using OpenFile compressed or not?  */
	var $compression;

	var $pipe_handle;
	
	var $process;
	

	/* METHODS */
	
	/* constructor */
	function CWBTempFile($prefix)
	{
		if (!isset($prefix))
			$prefix = "CWBTempFile";
		
		$suffix = "";
		
		if (preg_match('/\.(gz|bz2|Z)$/', $prefix, $matched) > 0 )
		{
			$prefix = preg_replace('/\.(gz|bz2|Z)$/', '', $prefix);
			$suffix = "." . $matched[1];
			// need to check the line above works
		}
		
		/* if $prefix isn't absolute or relative path */
		/* create temp file in /tmp directory */

		if (preg_match('/\//', $prefix) > 0)
			$basedir = "";
		else
			$basedir = "/tmp/";	
		
		/* orig perl uses process number */
		/* but I suppose unix epoch time is as good */
		$unique = time();
			
		$fn = $basedir . $prefix . "." . $unique . $suffix;
		
		/* deeply unlikely you'd need this */
		$num = 1;
		while(file_exists($fn))
		{
			$fn = basedir . $prefix . "." . $unique . "-" . $num . $suffix;
			$num++;
		}
		
		$this->file_handle = $this->OpenFile($fn, "w");
		$this->name = $fn;
		$this->status = "W";
	}



	/* fake destructor */
	function destroyme()
	{
		if ($this->status != "D")
			$this->close();
	}




	/* -------- */
	/* OpenFile */
	/* -------- */
	
	/* open file for reading or writing, with 'magical' compression/ 
	   decompression. 
	   
	   EITHER sets up a process in the class members, & returns pipe to 
	   stdin or stdout, depending onwhether this was a read or write open;
	   
	   OR just returns a read or write file pointer, if no pipe was involved
	   
	   Either way, you've got a file pointer to the file you've opened.
	   
	   note: this function takes its mode as a separate arg, like C and PHP 
	   not as tags all over the filename, as in Perl. So don't pass any! 
	   note also that it doesn't like + in its mode, append will only work 
	   if tis writing to file - not if it's using pipes 
	*/
	function OpenFile($name, $mode) 
	{
		if (preg_match('/^[arw]$/', $mode) == 0)
			exit("CWB::OpenFile: incorrect mode flag $mode\n");
	
		$this->compression = false;
	
		/* the following if-else ladder looks for the file type */
		/* and works out the correct program call for that */
	
		if (preg_match('/\.(tgz|tar\.(gz|Z))$/', $name) > 0 ) 
		{
			/* .tgz or .tar.gz or .tar.Z : */
			if ($mode == "r")
			{
				$this->compression = true;
				$prog = "gtar xzfO";
			}
		}
		else if (preg_match('/\.tar$/', $name) > 0 )
		{
			/*  .tar  */
			if ($mode == "r")
			{
				$this->compression = true;
				$prog = "gtar xfO";
			}
		}
		else if (preg_match('/\.gz$/', $name) > 0 )
		{
			/* .gz :  two types of program */
			$this->compression = true;
			$prog = ($mode != "r") ? "gzip" : "gzip -cd"; 
		}
		else if (preg_match('/\.bz2$/', $name) > 0 )
		{
			/* .bz2 :  two types of program */
			$this->compression = true;
			$prog = ($mode != "r") ? "bzip2" : "bzcat"; 
		}
		else if (preg_match('/\.Z$/', $name) > 0 )
		{
			/* .Z : two types of pipe */
			$this->compression = true;
			$prog = ($mode != "r") ? "compress" : "zcat";
		}
		
		if ($this->compression)
		{
			/* open with pipes */
			$io_settings = array(
				0 => array("pipe", "r"), /* pipe to child's stdin  */
				1 => array("pipe", "w"), /* pipe to child's stdout */
				2 => array("pipe", "w")  /* pipe to child's stderr */
				);		/* array of settings for the three pipe-handles */

			/* start the process */

			$this->process 
				= proc_open(  "$prog $name", $io_settings, 
							$this->pipe_handle);
			
			switch($mode)
			{
			case "r" :	$fh = $this->pipe_handle[1];	break;
			case "w" :	$fh = $this->pipe_handle[0];		break;
			case "a" :	$fh = $this->pipe_handle[0];		break;
			default  :	exit("CWB::OpenFile: incorrect mode: $mode\n");
			}	
		}
		else
			$fh = fopen($name, $mode);

		if (!isset($fh))
			exit("CWB::OpenFile: Can't open file '$name'." );
	
		return $fh;
	}
	
	
	function CloseFile($fh)
	{
		if ($this->compression)
		{
			if (isset($this->pipe_handle[0]))
				fclose($this->pipe_handle[0]);
			if (isset($this->pipe_handle[1]))
				fclose($this->pipe_handle[1]);
			if (isset($this->pipe_handle[2]))
				fclose($this->pipe_handle[2]);
			if (isset($this->process))
				proc_close($this->process);
			
			/* bit of a dirty hack this line */
			return true;
		}
		else
			return fclose($fh);
	}
	


	

	/* finish reading and delete */
	/* but note, it will cope if you pass it a writeable file as well */
	function close()
	{
		if ( ($this->status == "W" || $this->status == "R") 
			&& isset($this->file_handle) )
		{
			if (! $this->CloseFile($this->file_handle))
 				echo "CWBTempFile: Error closing tempfile " . $this->name;
  		}
  		
		/* in perl it was -f name, dunno what this is */
		if (is_file($this->name)) 
		{
			if (!unlink($this->name))
				echo "CWBTempFile: Could not unlink tempfile " . $this->name;
		}
		$this->status = "D";
	}



	function get_filename()
	{
		return $this->name;
	}



	/* example usasge: echo $tf->status() . "\n"; */
	function get_status()
	{
		switch($this->status)
		{
		case "W":		return "WRITING";
		case "F":		return "FINISHED";
		case "R":		return "READING";
		case "D":		return "DELETED";
		}
	}

	// careful - does this get passed arrays of chars or arrays of lines? 
	// am assuming lines for the nonce
	function write($line)
	{
		if ( $this->status != "W")
			exit( "CWB TempFile: Can't write to tempfile " . $this->name
				. " with status " . $this->get_status() );
				
		if (fwrite($this->file_handle, $line) == false)
		{
 			exit("CWB TempFile: Error writing to tempfile " . $this->name . "\n");
		}
	}
	
	
	
	/* stop writing file and close filehandle */
	function finish()
	{
		if (! ($this->status == "W") )
			exit("CWB TempFile: Can't finish tempfile " . $this->name 
				. " with status " . $this->status);

		/* close the file */
		if (!$this->CloseFile($this->file_handle))
 			exit("CWB TempFile: Error closing tempfile " . $this->name . "\n");
		$this->status = "F";
	}
	
	
	
	function read()
	{
		if ($this->status == "D")
			exit("CWB TempFile: Can't read from tempfile " . $this->name
				. ", already " . $this->get_status);
				
		if ($this->status == "W")
			$this->finish();
			
		if ($this->status != "R")
		{
			$this->file_handle = OpenFile($this->name);
			$this->status = "R";
		}
		/* read a line */
		$line = fgets($this->file_handle);
		return $line;
	}



	/* restart reading tempfile, by closing and re-opening it */
	function rewind()
	{
		if ($this->status == "D" || $this->status == "W")
			exit("CWB TempFile: Can't rewind tempfile " . $this->name 
				. " with status " . $this->status);
		
		/* if rewind is called before first read, it does nothing */
		if ($this->status != "R")
			;
		else
		{
			if (!fclose($this->file_handle))
	 			exit("CWB TempFile: Error writing tempfile " . $this->name . "\n");

			$this->file_handle = $this->OpenFile($this->name, "r");
		}
	}

}	/* end of class CWBTempFile */








?>