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


/** "element" must be specified with either the '~start' or '~end' suffixes. */
function xml_visualisation_delete($corpus, $element, $cond_attribute, $cond_regex)
{
	do_mysql_query("delete from xml_visualisations "
		. xml_visualisation_primary_key_whereclause($corpus, $element, $cond_attribute, $cond_regex));
}

/**
 * Turn on/off the use of an XML visualisation in context display.
 */
function xml_visualisation_use_in_context($corpus, $element, $cond_attribute, $cond_regex, $new)
{
	$newval = ($new ? 1 : 0);
	do_mysql_query("update xml_visualisations set in_context = $newval "
		. xml_visualisation_primary_key_whereclause($corpus, $element, $cond_attribute, $cond_regex));	
}

/**
 * Turn on/off the use of an XML visualisation in concordance display.
 */
function xml_visualisation_use_in_concordance($corpus, $element, $cond_attribute, $cond_regex, $new)
{
	$newval = ($new ? 1 : 0);
	do_mysql_query("update xml_visualisations set in_concordance = $newval "
		. xml_visualisation_primary_key_whereclause($corpus, $element, $cond_attribute, $cond_regex));	
}

/** 
 * Generate a where clause for db changes that must affect just one visualisation;
 * does all the string-checking and returns a full whereclause.
 */ 
function xml_visualisation_primary_key_whereclause($corpus, $element, $cond_attribute, $cond_regex)
{
	$corpus = cqpweb_handle_enforce($corpus);
	$element = mysql_real_escape_string($element);
	list($cond_attribute, $cond_regex) = xml_visualisation_condition_enforce($cond_attribute, $cond_regex);
	
	return " where corpus='$corpus' 
			and element = '$element' 
			and cond_attribute = '$cond_attribute' 
			and cond_regex = '$cond_regex'";
}

/**
 * Creates an entry in the visualisation list.
 * 
 * A previously-existing visualisation for that same tag is deleted.
 * 
 * The "code" supplied should be the input BB-code format.
 * 
 * IMPORTANT NOTE: here, $element does NOT include the "~(start|end)", whereas the other xml_vs functions
 * assume that it DOES.
 */
function xml_visualisation_create($corpus, $element, $code, $cond_attribute = '', $cond_regex = '', 
	$is_start_tag = true, $in_concordance = true, $in_context = true)
{
	/* disallow conditions in end tags (because they have no attributes) */
	if (! $is_start_tag)
		$cond_attribute = $cond_regex = '';
	
	/* make safe all db inputs: use handle enforce, where possible */
	$corpus = cqpweb_handle_enforce($corpus);
	$element = cqpweb_handle_enforce($element);
	list($cond_attribute, $cond_regex) = xml_visualisation_condition_enforce($cond_attribute, $cond_regex);
	
	$element_db = $element . ($is_start_tag ? '~start' : '~end');
	
	xml_visualisation_delete($corpus, $element_db, $cond_attribute, $cond_regex);
	
	$html = xml_visualisation_bb2html($code, !$is_start_tag);
	
	$in_concordance = ($in_concordance ? 1 : 0);
	$in_context     = ($in_context     ? 1 : 0);
	
	/* what fields are used? check the html not the bbcode, so $$$*$$$ is already removed from end tags */
	$xml_attributes = implode('~', $fields = xml_visualisation_extract_fields($html, 'xml'));
	if ($cond_attribute != '' && !in_array($cond_attribute, $fields))
		$xml_attributes .= "~$cond_attribute";
	$text_metadata  = implode('~', xml_visualisation_extract_fields($html, 'text'));


	do_mysql_query("insert into xml_visualisations
		(corpus, element, cond_attribute, cond_regex,
			xml_attributes, text_metadata, 
			in_context, in_concordance, bb_code, html_code)
		values
		('$corpus', '$element_db', '$cond_attribute', '$cond_regex', 
			'$xml_attributes', '$text_metadata',
			$in_context,$in_concordance, '$code', '$html')");
}

/** 
 * Returns an arrya contianing its two arguments, adjusted (empty strings
 * idf there is no condition, a handle and a mysql-escaped regex otherwise) 
 */
function xml_visualisation_condition_enforce($cond_attribute, $cond_regex)
{
	$cond_attribute = trim ($cond_attribute);
	
	if (! empty($cond_attribute))
	{
		$cond_attribute = cqpweb_handle_enforce($cond_attribute);
		$cond_regex = mysql_real_escape_string($cond_regex);
	}
	else
	{
		$cond_attribute = '';
		$cond_regex = '';
	}

	return array($cond_attribute, $cond_regex);
}

/** 
 * Returns an array of all fields used in the argument string.
 * 
 * XML-attributes are specified in the form $$$NAME$$$ .
 * 
 * Text metadata attributes are specified in the form ~~~NAME~~~ . 
 * 
 * Specify mode by having second argument be "text" or "xml".
 * 
 * If anything other than those two is specified, "xml" is assumed.
 * 
 * The special markers $$$$$$ and ~~~~~~ are not extracted.
 */
function xml_visualisation_extract_fields($code, $type='xml')
{
	/* set delimiter */
	if ($type == 'text' || $type == 'TEXT')
		$del = '~~~';
	else
		$del = '\$\$\$';
	
	$fields = array();
	
	$n = preg_match_all("/$del(\w*)$del/", $code, $m, PREG_SET_ORDER);
	
	foreach($m as $match)
	{
		/* note that $$$$$$ means value of this s-attribute, whereas ~~~~~~ means the ID of the current text;
		 * in both cases, we want to ignore it from this array, as it is not stored in the DB. */
		if (empty($match[1]))
			continue;
		if ( ! in_array($match[1], $fields))
			$fields[] = $match[1];
	}
	
	return $fields;
}


function xml_visualisation_bb2html($bb_code, $is_for_end_tag = false)
{
	$html = cqpweb_htmlspecialchars($bb_code);
	
	/* 
	 * OK, we have made the string safe. 
	 * 
	 * Now let's un-safe each of the BBcode sequences that we allow.
	 */ 
	
	/* begin with tags that are straight replacements and do not require PCRE. */
	static $from = NULL;
	static $to = NULL;
	if (is_null($from))
		initialise_visualisation_simple_bbcodes($from, $to);
	$html = str_ireplace($from, $to, $html);
	
	/* get rid of empty <li>s */
	$html = preg_replace('|<li>\s*</li>|', '', $html);
	
	/* if there are newlines, convert to just normal spaces */
	$html = strtr($html, "\r\n", "  ");
	
	/* table cells - in normal BBcode these are invariant, however, we allow
	 * [td c=num] for colspan and [td r=num] for rowspan */
	function for_table_cell_callback($m)
	{
		$span = '';
		if (!empty($m[2]))
		{
			if (0 < preg_match('/r=\d+/', $m[2], $n))
				$span .= " rowspan={$n[1]}";
			if (0 < preg_match('/c=\d+/', $m[2], $n))
				$span .= " colspan={$n[1]}";
		}
		return "<t{$m[1]}$span>";
	}
	//TODO replace the above with a closure at some point
	$html = preg_replace_callback('|\[t([hd])\s+([^\]]*)\]|i',  'for_table_cell_callback', $html );
	
	/* color opening tags: allow the "colour" alleged-misspelling (curse these US-centric HTML standards! */
	$html = preg_replace('|\[colou?r=(#?\w+)\]|i', '<span style="color:$1">', $html);
	
	/* size opening tags: always in px rather than pt */
	$html = preg_replace('|\[size=(\d+)\]|i', '<span style="font-size:$1px">', $html);
	
	/* an extension for CQPweb: create popup boxes! */
	$html = preg_replace('|\[popup=([^\]]*])\]|', '<span onmouseover="return escape(\'$1\')">', $html);
	
	/* This is another CQPweb extension to BBCode, allow block and nonblock style appliers */
	$html = preg_replace('~\[(div|span)=(\w+)\]~i', '<$1 class="XmlViz__$2">', $html);
	
	/* img is an odd case, in theory we could do it with simple replaces, but since it collapses down to
	 * just one tag, let's be safe and only allow it in cases where the tags match. */
	$html = preg_replace('|\[img\]([^"]+?)\[/img\]|i', '<img src="$1" />', $html);
	/* we also have a variant form with height and width */	
	$html = preg_replace('|\[img=(\d+)x(\d+)\]([^"]+?)\[/img\]|i', '<img width="$1" height="$2" src="$3" />', $html);
	$html = preg_replace('|\[img\s+width=(\d+)\s+height=(\d+)\s*\]([^"]+?)\[/img\]|i', 
							'<img width="$1" height="$2" src="$3" />', $html);
	$html = preg_replace('|\[img\s+height=(\d+)\s+width=(\d+)\s*\]([^"]+?)\[/img\]|i', 
							'<img width="$2" height="$1" src="$3" />', $html);
	
	/* now links - two sorts of these */
	$html = preg_replace('|\[url\]([^"]+?)\[/url\]|i', '<a target="_blank" href="$1">$1</a>', $html);
	$html = preg_replace('|\[url=([^"]+?)\](.+?)\[/url\]|i', '<a target="_blank" href="$1">$2</a>', $html);
	
	
	if ($is_for_end_tag)
	{
		/* remove all attribute values: end-tags don't have them in CQP concordances. */
		$html = preg_replace('/$$$\w*$$$/', '', $html);
	}
	
	return $html;
}


/** Initialise arrays of simple bbcode translations */
function initialise_visualisation_simple_bbcodes(&$from, &$to)
{
	/* emboldened text: we use <strong>;  not <b> */
	$from[0] = '[b]';			$to[0] =  '<strong>'; 
	$from[1] = '[/b]';			$to[1] =  '</strong>'; 
	
	/* italic text: we use <em>;  not <i> */
	$from[2] = '[i]';			$to[2] =  '<em>'; 
	$from[3] = '[/i]';			$to[3] =  '</em>'; 
	
	/* underlined text: we use <u>;  not <ins> or anything silly. */
	$from[4] = '[u]';			$to[4] =  '<u>'; 	
	$from[5] = '[/u]';			$to[5] =  '</u>'; 
	
	/* struckthrough text: just use <s> */
	$from[6] = '[s]';			$to[6] =  '<s>'; 			
	$from[7] = '[/s]';			$to[7] =  '</s>'; 
	
	/* unnumbered list is easy enough. BUT the [*] that creates <li> makes life trickier. */
	$from[8] =  '[list]';		$to[8] =   '<ul><li>';
	$from[9] =  '[/list]';		$to[9] =   '</li></ul>';
	$from[10] = '[*]';			$to[10] =  '</li><li>';
	/* note we will need a regex to get rid of empty <li>s.  See main processing function. */

 	/* quote is how we get at HTML blockquote. No other styling specified. */
	$from[11] = '[quote]';		$to[11] =  '<blockquote>';
	$from[12] = '[/quote]';		$to[12] =  '</blockquote>'; 
	
	/* code gives us <pre>. */
	$from[13] = '[code]';		$to[13] =  '<pre>'; 
	$from[14] = '[/code]';		$to[14] =  '</pre>';
	
	/* table main holder; td and tr are more complex */
	$from[15] = '[table]';		$to[15] =  '<table>'; 
	$from[16] = '[/table]';		$to[16] =  '</table>';
	$from[17] = '[tr]';			$to[17] =  '<tr>'; 
	$from[18] = '[/tr]';		$to[18] =  '</tr>';

	/* close tags for elements with complicated opening tags */
	$from[19] = '[/td]';		$to[19] =  '</td>';
	$from[20] = '[/th]';		$to[20] =  '</th>';
	$from[21] = '[/size]';		$to[21] =  '</span>';
	$from[22] = '[/color]';		$to[22] =  '</span>';
	$from[23] = '[/colour]';	$to[23] =  '</span>';
	$from[24] = '[/div]';		$to[24] =  '</div>';
	$from[25] = '[/span]';		$to[25] =  '</span>';
	$from[34] = '[/popup]';		$to[34] =  '</span>';  // NOTE out of order number cos this was added later
	
	/* alternative bbcode list styles - let's support as many as possible */
	$from[26] = '[ul]';			$to[26] =  '<ul><li>';
	$from[27] = '[/ul]';		$to[27] =  '</li></ul>';
	$from[28] = '[ol]';			$to[28] =  '<ol><li>';
	$from[29] = '[/ol]';		$to[29] =  '</li></ol>';
	
	/* something not needed in most cases, but throw it in anyway.... */
	$from[30] = '[centre]';		$to[30] =  '<center>';
	$from[31] = '[center]';		$to[31] =  '<center>';
	$from[32] = '[/centre]';	$to[32] =  '</center>';
	$from[33] = '[/center]';	$to[33] =  '</center>';

/* next number: 35 */
}


/** 
 * Gets an array of s-attributes that need to be shown in the CQP concordance line
 * in order for visualisation to work. 
 */
function xml_visualisation_s_atts_to_show()
{
	global $corpus_sql_name;

	$atts = array();

	$result = do_mysql_query("select element, xml_attributes from xml_visualisations where corpus='$corpus_sql_name'");

	while (false !== ($r = mysql_fetch_object($result)))
	{
		list($r->element) = explode('~', $r->element); 
		if ( ! in_array($r->element, $atts) )
			$atts[] = $r->element;
		if ($r->xml_attributes == '')
			continue;
		foreach (explode('~', $r->xml_attributes) as $a)
		{
			$s_a = "{$r->element}_$a";
			if ( ! in_array($s_a, $atts) )
				$atts[] = $s_a;
		}
	}
	
	return $atts;
}

?>
