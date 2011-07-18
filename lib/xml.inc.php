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
 * @file
 * 
 * IMPORTANT NOTE
 * 
 * CQPweb does not have full XML support yet, but rather a couple of functions need to deal with 
 * s-attributes in various ways.
 * 
 * When XML support is added, most of these functions should be rewritten to query a mysql table 
 * instead of crudely yanking the registry file into memory.
 * 
 * Other functions will appear in this file eventually!
 */


/**
 * Gets an array of all s-attributes in this corpus.
 */
function get_xml_all()
{
	global $cwb_registry;
	global $corpus_cqp_name;
	
	// TODO make this function cache its return value in a static var, 
	// so that we can reduce the number of file accesses.
	
	// TODO note use of strtolower is almost certainly THE WRONG THING
	$data = file_get_contents("/$cwb_registry/" . strtolower($corpus_cqp_name));
	
	preg_match_all("/STRUCTURE\s+(\w+)\s*[#\n]/", $data, $m, PREG_PATTERN_ORDER);

	return $m[1];
}

/**
 * Checks whether or not the specified s-attribute exists in this corpus.
 * (A convenience wrapper around get_xml_all().)
 */
function xml_exists($element)
{
	return in_array($element, get_xml_all());
}

/**
 * Gets an array of all s-attributes that are annotations.
 * (That is, they have a value that can be printed...)
 */
function get_xml_annotations()
{
	/* there has GOT to be an easier way than this to work out whether an s-att has values... 
	 * but I can't work out what that might be just now!!
	 * 
	 * Even this method might not actually work in some conceivable situations.
	 */
	
	$full_list = get_xml_all();
	
	/* for each s-attribute, extract all its annotations */
	foreach ($full_list as $tester)
		foreach($full_list as $k=>$found)
			if (substr($found, 0, strlen($tester)+1) == $tester.'_')
			{
				/* embedded string creates new var, not reference */
				$return_list[] = "$found";
				/* so that we don't look for annotations of annotations */
				unset($full_list[$k]);
			}

	return $return_list;
}


function xml_visualisation_delete($corpus, $element)
{
	$corpus = mysql_real_escape_string($corpus);
	$element = mysql_real_escape_string($element);
	
	do_mysql_query("delete from xml_visualisations 
		where corpus='$corpus' and element = '$element'");
}

function xml_visualisation_create($corpus, $element, $code, $in_concordance = true, $in_context = true)
{
	$corpus = mysql_real_escape_string($corpus);
	$element = mysql_real_escape_string($element);
	$code = xml_visualisation_code_make_safe($code);
	// TODO other filtering required? separate filter function?
	
	$in_concordance = ($in_concordance ? 1 : 0);
	$in_context     = ($in_context     ? 1 : 0);
	
	do_mysql_query("insert into xml_visualisations
		(corpus, element, in_context, in_concordance, code)
		values
		('$corpus', '$element', $in_context,$in_concordance, '$code')");
}

/**
 * Turn on/off the use of an XML visualisation in context display.
 */
function xml_visualisation_use_in_context($corpus, $element, $new)
{
	$newval = ($new ? 1 : 0);
	do_mysql_query("update xml_visualisations set in_context = $newval 
		where corpus='$corpus' and element = '$element'");	
}

/**
 * Turn on/off the use of an XML visualisation in concordance display.
 */
function xml_visualisation_use_in_concordance($corpus, $element, $new)
{
	$newval = ($new ? 1 : 0);
	do_mysql_query("update xml_visualisations set in_concordance = $newval 
		where corpus='$corpus' and element = '$element'");	
}

/**
 * Unfinished HTML-sanitiser for XML visualisations.
 * 
 * TODO.
 */
function xml_visualisation_code_make_safe($code)
{
	/* delete possible malignant HTML code */
	
	/* dangerous elements */
	$code = preg_replace('/<script\s.*?>/', '', $code);
	$code = preg_replace('/</script>/', '', $code);
	$code = preg_replace('/<applet\s.*?>/', '', $code);
	$code = preg_replace('/</applet>/', '', $code);
	$code = preg_replace('/<embed\s.*?>/', '', $code);
	$code = preg_replace('/</embed>/', '', $code);
	$code = preg_replace('/<object\s.*?>/', '', $code);
	$code = preg_replace('/</object>/', '', $code);
	
	/* dangerous attributes: on.* */
	$code = preg_replace('/\bon\w+?=/', '', $code);
	// but note that this denies functionality... but reallowing allows arbitrary javascript to be executed.

	// TODO !!!!
	/*
	 * problem: the list of potentially dangerous tags includes one or two that I would really 
	 * rather like to allow here, namely img (And presumably <a> though the list below, which
	 * I got off a microsoft help page, doesn't include it, it takes href which could
	 * contain malicious javascript code).
	 * 
	 * This looks as if it might be rather more complex than I had hoped.
	 * 
	 * Issue: given CQPweb doesn't use cookies, exactly what could an exploiter get access to 
	 * with malicious javascript that could cause a problem??
	 * 
	 * Assume we allow on.*= , img, a, etc.
	 * 
	 * And that someone got bad javascript into the src or the href or the on.*
	 * 
	 * Would they have access to anything dangerous?
	 * 
	 * They could redirect to a bad site I suppose. That would be the worst.
	 * 
	 * We DON'T PUT anything into cookies, so they couldn't be accessed by a bad site.
	 * 
	 * The only alternative approach I can think of is to create a subset metalanguage of HTML
	 * for the definition of visualisations. Bu that would be complex and would not rule out
	 * bad urls in src= or href=.
	 */ 
/*
<applet>
<body>
<embed>
<frame>
<script>
<frameset>
<html>
<iframe>
<img>
<style>
<layer>
<link>
<ilayer>
<meta>
<object>
plus attributes
 src, lowsrc, style, and href
*/
	return mysql_real_escape_string($code);		
}

?>
