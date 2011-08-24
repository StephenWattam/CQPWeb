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
	
	/* we stick the result in a static cache var to reduce the number of file accesses */
	static $cache = NULL;
	
	if (is_null($cache))
	{
		/* use of strtolower() is OK because CWB identifiers *MUST ALWAYS* be ASCII */ 
		$data = file_get_contents("/$cwb_registry/" . strtolower($corpus_cqp_name));
		// but long-term consider caching the lowercase CWB name somewhere....
		
		preg_match_all("/STRUCTURE\s+(\w+)\s*[#\n]/", $data, $m, PREG_PATTERN_ORDER);
	
		$cache = $m[1];
	}
	
	return $cache;
}

/**
 * Checks whether or not the specified s-attribute exists in this corpus.
 * 
 * (A convenience wrapper around get_xml_all().)
 */
function xml_exists($element)
{
	return in_array($element, get_xml_all());
}

/**
 * Gets an array of all s-attributes that have annotration valueas 
 * (includes all those derived from attribute-value annotations
 * of another s-attribute that was specifieds xml-style).
 * 
 * That is, the listed attributes definitely have a value that can be printed.
 */
function get_xml_annotations()
{
	/* TODO - eventually this info should prob be in the DB rather than using cwb-d-c every time*/
	
/* --old version of code......
	$full_list = get_xml_all();
	
	/* for each s-attribute, extract all its annotations * /
	foreach ($full_list as $tester)
		foreach($full_list as $k=>$found)
			if (substr($found, 0, strlen($tester)+1) == $tester.'_')
			{
				/* embedded string creates new var, not reference * /
				$return_list[] = "$found";
				/* so that we don't look for annotations of annotations * /
				unset($full_list[$k]);
			}

	return $return_list;
*/

	global $path_to_cwb;
	global $cwb_registry;
	global $corpus_cqp_name;
	
	/* we stick the result in a static cache var to reduce the number of slave processes
	 * - there is no point xcalling cwb-describe-corpus more than once per  */
	static $return_list = NULL;
	
	if (is_null($return_list))
	{
		$cmd = "/$path_to_cwb/cwb-describe-corpus -r /$cwb_registry -s $corpus_cqp_name";
		
		exec($cmd, $results);
		
		$return_list = array();
		
		foreach($results as $r)
			if (0 < preg_match('/s-ATT\s+(\w+)\s+\d+\s+regions?\s+\(with\s+annotations\)/', $r, $m))
				$return_list[] = $m[1];
	}

	return $return_list;
}


function xml_visualisation_delete($corpus, $element)
{
	$corpus = mysql_real_escape_string($corpus);
	$element = mysql_real_escape_string($element);
	
	do_mysql_query("delete from xml_visualisations where corpus='$corpus' and element = '$element'");
}

function xml_visualisation_create($corpus, $element, $code, $in_concordance = true, $in_context = true)
{
	$corpus = mysql_real_escape_string($corpus);
	$element = mysql_real_escape_string($element);
	
	$html = xml_visualisation_code_make_safe($code);
	// TODO other filtering required? separate filter function?
	
	$in_concordance = ($in_concordance ? 1 : 0);
	$in_context     = ($in_context     ? 1 : 0);
	
	
	// TODO rewrite insert
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
	$corpus = mysql_real_escape_string($corpus);
	$element = mysql_real_escape_string($element);
	$newval = ($new ? 1 : 0);
	do_mysql_query("update xml_visualisations set in_context = $newval 
		where corpus='$corpus' and element = '$element'");	
}

/**
 * Turn on/off the use of an XML visualisation in concordance display.
 */
function xml_visualisation_use_in_concordance($corpus, $element, $new)
{
	$corpus = mysql_real_escape_string($corpus);
	$element = mysql_real_escape_string($element);
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

function xml_visualisation_bb_to_html($bb_code, $is_for_end_tag = false)
{
	$html = cqpweb_htmlspecialchars($bb_code);
	
	/* OK, we have made the string safe. 
	 * 
	 * Now let's un-safe each of the BBcode sequences that we allow.
	 */ 
	
	/* begin with tags that are straight replacements and do not require PCRE. */
	
	static $direct_replacements = array(
		'[b]' => '<strong>',		/* emboldeneed text: we use <strong>, not <b> */
		'[B]' => '<strong>',
		'[/b]' => '</strong>',
		'[/B]' => '</strong>',
		'[i]' => '<em>',			/* italic text: we use <em>, not <i> */
		'[I]' => '<em>',
		'[/i]' => '</em>',
		'[/I]' => '</em>',
		'[u]' => '<u>',				/* underlined text: we use <u>, not <ins> or anything silly. */
		'[U]' => '<u>',
		'[/u]' => '</u>',
		'[/U]' => '</u>',
		'[s]' => '<s>',				/* struckthrough text: just use <s> */
		'[S]' => '<s>',
		'[/s]' => '</s>',
		'[/S]' => '</s>',
		'[list]' => '<ul>',			/* unmnumered lsit is easy enough. BUT SEE BELOW for the [*] that creates <li>. */
		'[/list]' => '</ul>',
		'[List]' => '<ul>',
		'[/List]' => '</ul>',
		'[LIST]' => '<ul>',
		'[/LIST]' => '</ul>',
		'[quote]' => '<blockquote>',	/* quote is how we get at HTML blockquote. No other styling specified. */
		'[/quote]' => '</blockquote>',
		'[QUOTE]' => '<blockquote>',
		'[/QUOTE]' => '</blockquote>',
		'[Quote]' => '<blockquote>',
		'[/Quote]' => '</blockquote>',
//TODO		'[quote]' => '<blockquote>',	/* code gives us <pre>. */
		'[/quote]' => '</blockquote>',
		'[QUOTE]' => '<blockquote>',
		'[/QUOTE]' => '</blockquote>',
		'[Quote]' => '<blockquote>',
		'[/Quote]' => '</blockquote>',
		);
	
	
	$html = strtr($html, $direct_replacements);
	
	
	if ($is_for_end_tag)
	{
		/* remove all attribute values: end-tags don't have them */
		$html = preg_replace('/$$$\w*$$$/', '', $html);
	}
	
	
	return $html;
}

// important TODO note. 
// There is nothing to stop the BBCODES creating unbalanced HTML which could spill 
// across the end of the concordance line.
// Ideally, we dopn't want the XML visualisation to affect anything outside itslf.
// So, if we can wangle it, ideally when the HTML is initially generated from BB code,
// any dangling open [brackets] should be closed automatically. 

?>
