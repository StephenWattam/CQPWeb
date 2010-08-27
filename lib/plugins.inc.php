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

/**
 * An autoload function is defined for plugin classes.
 * 
 * All plugin classes must be files of the form ClassName.php,
 * within the lib/plugins subdirectory.
 * 
 * Note that in CQPweb, plugins are the ONLY classes that can be
 * autoloaded.
 * 
 * The autoload function therefore doesn't need to be included unless
 * either transliteration or annotation is going to be happening.
 * 
 * The $plugin parameter is, of course, the classname.
 */ 
function __autoload($plugin)
{
	// TODO create these global arrays from config strings.
	// they should be set to empty arrays in defaults.inc.php if necessary.
	// then modify Visualisations mangement to allow it to be engaged
	// then modify conocrdance and context to run it on the strings that
	// cmoe from CQP if the ncessary global variables are TRUE.
	//end TODO
	global $plugin_classes_transliterators;
	global $plugin_classes_annotators;
	
	/* check it's a valid plugin class, listed in config */
	if (in_array($plugin, $plugin_classes_transliterators))
		$type = 'translit';
	else if (array_key_exists($plugin, $plugin_classes_annotators))
		$type = 'annot';
	else
		exiterror_general("Attempting to autoload an unknown plugin! Check the configuration.");
	
	/* if the file exists, load it. f not, fall over and die. */
	$file = "../lib/plugins/$plugin.php";
	if (is_file($file))
		require_once($file);
	else
		exiterror_general('Attempting to load a plugin file that could not be found! Check the configuration.');
	
	if (!class_exists($plugin))
		exiterror_general('Plugin autoload failure, CQPweb aborts.');
	
	/* now that we've got it loaded, check it implements the right interface. */
	if ($type == 'translit')
		if (! ($plugin instanceof Transliterator) )
			exiterror_general('Bad transliteration plugin! Doesn\'t implement the Transliterator interface.'
				. ' Check the coding of your plugin ' . $plugin);
	if ($type == 'annot')
		if (! ($plugin instanceof Annotator) )
			exiterror_general('Bad annotation plugin! Doesn\'t implement the Annotator interface.'
				. ' Check the coding of your plugin ' . $plugin);
}

/**
 * Interface for Transliterator Plugins.
 * 
 * A Transliterator Plugin is an object which implements this interface.
 * 
 * It must be able to something sensible with any UTF8 text passed to it.
 * 
 * This will normally mean transliterating it to Latin or other alphabet
 * native/fmailiar to the user base.
 * 
 * A class implementing this interface can do this however it likes - 
 * internally, by calling a library, by creating a back-end process
 * and piping data back and forth - CQPweb doesn't care.
 * 
 * What you are not allowed to do in a plugin is use any of CQPweb's
 * global data. (Or rather, you are ALLOWED to - it's your computer! -
 * we just don't think it would be a good idea at all.)
 */
interface Transliterator
{
	/**
	 * This function takes a UTf8 string and returns a UTF8 string.
	 * 
	 * The returned string is the direct equivalent, but with some
	 * (or all) characters from outside the Latin range(s) converted
	 * to characters within that range.
	 * 
	 * It must be possible to pass a raw string straight from CQP,
	 * and get back a string that is still structured the same
	 * (so CQPweb functions don't need ot know about whether or not
	 * transliteration has happened).
	 */
	public function transliterate($string);
	
	/**
	 * The constructor of a transliterator plugin cannot take any
	 * arguments.
	 */
	public function __construct();
}


/**
 * Interface for Annotator Plugins.
 * 
 * An Annotator Plugin is an object that represents a program external
 * to CQPweb that can be used to manage files in some way (e.g. by 
 * tagging them.) 
 */
interface Annotator
{
	/**
	 * The constructor function may be passed a single argument.
	 * 
	 * If it is, it is the (absolute or relative) path to a configuration
	 * file, which can be loaded and used to (of course, it can be ignroed as well.)
	 * 
	 * This argument's default value is an empty string. Any "empty" value,
	 * such as '', NULL, 0 or false, should be interpreted as "no config file".
	 * 
	 * The internal format of the config file, and how it is parsed and the info
	 * stored, is a matter for the plugin to decide. Config files can be anywhere
	 * on the system that is accessible to the username that CQPweb runs under.
	 */
	public function __construct($config_file = '');
	
	/**
	 * Process a file (e.g. to tag or tokenise it).
	 * 
	 * Both arguments are relative or absolute paths. The method SHOULD NOT use
	 * CQPweb global variables.
	 * 
	 * The input file MUST NOT be modified.
	 * 
	 * This function should return false if the output file was not 
	 * successfully created.
	 * 
	 * If the output file is partially created or created with errors, it
	 * should be deleted before false is returned.
	 */
	public function process_file($path_to_input_file, $path_to_output_file);
	
	/**
	 * Should return true if either no file has yet been processed, or
	 * the last file was processed successfully.
	 * 
	 * Should return false if the last file was not processed successfully.
	 */
	public function status_ok();
	
	/**
	 * Returns a string describing the last encountered error.
	 * 
	 * If there has been no error, then it can return an empty string,
	 * or a message saying there has been no error. It doesn't matter which.
	 */
	public function error_desc();
	
	/**
	 * Returns the size of the last output file created as an integer count of bytes.
	 * 
	 * If no file has yet been processed, return 0.
	 */
	public function output_size();
	
}


?>