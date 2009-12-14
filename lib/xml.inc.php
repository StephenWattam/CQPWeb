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

/*
 * IMPORTANT NOTE
 * 
 * CQPweb does not have XML support yet, but a couple of functions need ot deal with s-attributes in various ways.
 * 
 * When XML support is added, most of these functions should be rewritten to query a mysql table instead of crudely yanking the
 * registry file into memory.
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
	
	$data = file_get_contents("/$cwb_registry/" . strtolower($corpus_cqp_name));
	
	preg_match_all("/STRUCTURE\s+(\w+)\s*[#\n]/", $data, $m, PREG_PATTERN_ORDER);

	return $m[1];
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


?>
