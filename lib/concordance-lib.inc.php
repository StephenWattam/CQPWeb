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





/* --------------------------------------------- */
/* FUNCTIONS ONLY USED IN THE CONCORDANCE SCRIPT */
/* --------------------------------------------- */





function prepare_query_string($s)
{
	if (preg_match('/\A[^\S]*\Z/', $s))
		exiterror_general('You are trying to search for nothing!', __FILE__, __LINE__);

	/* remove whitespace */
	$s = trim($s);
	/* and internal line breaks / tabs */
	$s = str_replace('%0D', ' ', $s);
	$s = str_replace('%0A', ' ', $s);
	$s = str_replace('%09', ' ', $s);
	$s = str_replace('  ', ' ', $s);
	
	/* note that, at this point, somehow, quotes will have been "magicked" */
	/* into slashed quotes; so I need to remove the slashes */
	$s = preg_replace('/\\\\"/', '"', $s);
	$s = preg_replace("/\\\\'/", "'", $s);
// chnage this - there is a built-in PHP function for this
// and, for forward-compatibility, do a check against the MAGIC-QUOTES constnt (see php manual
	// not sure if all the above are necessary or even if they will work
	// but if they don't work, then they will fail-safe instead of fail-sorry
	
	return $s;
}


/* invalid values: cause CQPweb to abort if $strict; are converted to NULL if not $strict */
function prepare_query_mode($s, $strict = true)
{
	if ( ! is_bool($strict) )
		exiterror_arguments($strict, 
			"prepare_query_mode() needs a bool (or nothing) as its 2nd argument!", __FILE__, __LINE__);
	
	$s = strtolower($s);
	
	switch($s)
	{
	case 'sq_case':
	case 'sq_nocase':
	case 'cqp':
		return $s;
	default:
		if ($strict)
			exiterror_parameter('Invalid query mode specified!', __FILE__, __LINE__);
		else
			return NULL;
	}
}




/* returns an array: [0] == number of words searched, [1] == number of files searched */
function amount_of_text_searched($subcorpus, $restrictions)
{
	global $corpus_sql_name;
	global $mysql_link;
	global $username;
	
	if ($subcorpus != 'no_subcorpus')
	{
		$sql_query = "select numwords, numfiles from saved_subcorpora
			WHERE subcorpus_name = '$subcorpus'
			AND corpus = '$corpus_sql_name'
			AND user = '$username'";

		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
				
		return mysql_fetch_row($result);
	}
	else
	{
		/* this works for a restriction, or even if the whole corpus is being searched */
		$sql_query = "select sum(words), count(*) from text_metadata_for_$corpus_sql_name";

		$sql_query .= ($restrictions == 'no_restriction' ? '' : ' where ' . $restrictions);

		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);

		return mysql_fetch_row($result);
	}
}



// so useful, it should prob be in library
/* takes a "query-record" style associative array */
function create_solution_heading($record, $include_corpus_size = true)
{
	global $mysql_link;
	global $corpus_sql_name;
	global $cqp;
	
	if (isset($cqp))
		$cqp_was_set = true;
	else
	{
		$cqp_was_set = false;
		connect_global_cqp();
	}

	/* check only those elements of the array that are actually getting used
	 * and put them into easier-reference variables 
	 */
	$qname				= (isset($record['query_name'])		? $record['query_name']		: exiterror_arguments('', '', __FILE__, __LINE__) );
	$simple_query		= (isset($record['simple_query'])	? $record['simple_query']	: '' );
	$cqp_query			= (isset($record['cqp_query'])		? $record['cqp_query']		: exiterror_arguments('', '', __FILE__, __LINE__) );
	$qmode				= (isset($record['query_mode'])		? $record['query_mode']		: exiterror_arguments('', '', __FILE__, __LINE__) );
	$num_of_solutions	= (isset($record['hits'])			? $record['hits']			: exiterror_arguments('', '', __FILE__, __LINE__) );
	$num_of_files		= (isset($record['hit_texts'])		? $record['hit_texts']		: exiterror_arguments('', '', __FILE__, __LINE__) );
	$subcorpus			= (isset($record['subcorpus'])		? $record['subcorpus']		: exiterror_arguments('', '', __FILE__, __LINE__) );
	$restrictions		= (isset($record['restrictions'])	? $record['restrictions']	: exiterror_arguments('', '', __FILE__, __LINE__) );

	$final_string = 'Your query &ldquo;';

	if ( $qmode == 'cqp' || $simple_query == '' )
		$final_string .= htmlspecialchars($cqp_query, ENT_QUOTES, 'UTF-8', false);
	else
		$final_string .= htmlspecialchars($simple_query, ENT_QUOTES, 'UTF-8', false);

	$final_string .= "&rdquo;";


	if ($subcorpus != 'no_subcorpus')
		$final_string .= ', in subcorpus &ldquo;<em>' . $subcorpus . '</em>&rdquo;';
	else if ($restrictions != 'no_restriction')
		$final_string .= ', restricted to ' . translate_restrictions_to_prose($restrictions) . ',';

		
	$final_string .= ' returned '. make_thousands($num_of_solutions) . ' matches';	


	if ($num_of_files > 1) 
		$final_string .= " in $num_of_files different texts";
	else 
		$final_string .= " in 1 text";



	/* default is yes, but it can be overidden and left out eg for collocation */
	if ($include_corpus_size)
	{ 
		/* find out total amount of text searched (with either a restriction or a subcorpus) */
		list($num_of_words_searched, $num_of_files_searched)
			= amount_of_text_searched($subcorpus, $restrictions);
		
		if ($num_of_words_searched == 0)
			/* this should never happen, but the following should avoid problems with div-by-zero */
			$num_of_words_searched = 0.1;
	
		$final_string .= ' (in ' . make_thousands($num_of_words_searched) . ' words [' 
			. make_thousands($num_of_files_searched) . ' texts]; frequency: ' 
			. round(($num_of_solutions / $num_of_words_searched) * 1000000, 2)
			. ' instances per million words)';
	}

	/* add postprocessing comments here */
	if ( empty($record['postprocess']) )
		;
	else
		$final_string .= postprocess_string_to_description($record['postprocess'], $record['hits_left']);

	if (!$cqp_was_set)
		disconnect_global_cqp();

	return $final_string;
}




function format_time_string($timeTaken, $not_from_cache = true)
{
	if (isset($timeTaken) )
		$str .= " <span class=\"concord-time-report\">[$timeTaken seconds"
			. ($not_from_cache ? '' : ' - retrieved from cache') . ']</span>';
	else if ( ! $not_from_cache )
		$str .= ' <span class="concord-time-report">[data retrieved from cache]</span>';

	return $str;
}





function print_control_row()
{
	global $qname;
	global $page_no;
	global $per_page;
	global $num_of_pages;
	global $reverseViewMode;
	global $reverseViewButtonText;
	global $postprocess;
	global $program;

	/* this is the variable to which everything is printed */
	$final_string = '<tr>';


	/* ----------------------------------------- */
	/* first, create backards-and-forwards-links */
	/* ----------------------------------------- */
	
	$marker = array( 'first' => '|&lt;', 'prev' => '&lt;&lt;', 'next' => "&gt;&gt;", 'last' => "&gt;|" );
	
	/* work out page numbers */
	$nav_page_no['first'] = ($page_no == 1 ? 0 : 1);
	$nav_page_no['prev']  = $page_no - 1;
	$nav_page_no['next']  = ($num_of_pages == $page_no ? 0 : $page_no + 1);
	$nav_page_no['last']  = ($num_of_pages == $page_no ? 0 : $num_of_pages);
	/* all page numbers that should be dead links are now set to zero  */
	

	foreach ($marker as $key => $m)
	{
		$final_string .= '<td align="center" class="concordgrey"><b><a class="page_nav_links" ';
		$n = $nav_page_no[$key];
		if ( $n != 0 )
			/* this should be an active link */
			$final_string .= 'href="concordance.php?'
				. url_printget(array(
					array('uT', ''), array('pageNo', "$n"), array('qname', $qname)
					) )
				. '&uT=y"';
		$final_string .= ">$m</b></a></td>";
	}

	/* ----------------------------------------- */
	/* end of create backards-and-forwards-links */
	/* ----------------------------------------- */



	/* --------------------- */
	/* create show page form */
	/* --------------------- */
	$final_string .= "<form action=\"concordance.php\" method=\"get\"><td width=\"20%\" class=\"concordgrey\" nowrap=\"nowrap\">&nbsp;";
	
	$final_string .= '<input type="submit" value="Show Page:"/> &nbsp; ';
	
	$final_string .= '<input type="text" name="pageNo" value="1" size="8" />';
		
	$final_string .= '&nbsp;</td>';

	$final_string .= url_printinputs(array(
		array('uT', ''), array('pageNo', ""), array('qname', $qname)
		));
	
	$final_string .= '<input type="hidden" name="uT" value="y"/></form>';
	
	
	
	/* ----------------------- */
	/* create change view form */
	/* ----------------------- */
	$final_string .= "<form action=\"concordance.php\" method=\"get\"><td align=\"center\" width=\"20%\" class=\"concordgrey\" nowrap=\"nowrap\">&nbsp;";
	
	$final_string .= "<input type=\"submit\" value=\"$reverseViewButtonText\"/>";
		
	$final_string .= '&nbsp;</td>';
	
	$final_string .= url_printinputs(array(
		array('uT', ''), array('viewMode', "$reverseViewMode"), array('qname', $qname)
		));
	
	$final_string .= '<input type="hidden" name="uT" value="y"/></form>';
	


	/* ----------------*/
	/* interrupt point */
	/* --------------- */
	if ($program == 'categorise')
		/* return just with two empty cells */
		return $final_string . '<td class="concordgrey" width="25%">&nbsp;</td>
				<td class="concordgrey" width="25%">&nbsp;</td></tr>';


	/* ------------------------ */
	/* create random order form */
	/* ------------------------ */
	if (substr($postprocess, -4) !== 'rand' || substr($postprocess, -6) == 'unrand')
	{
		/* current display is not randomised */
		$newPostP_value = 'rand';
		$randomButtonText = 'Show in random order';
	}
	else
	{
		/* curent display is randomised */
		$newPostP_value = 'unrand';
		$randomButtonText = 'Show in corpus order';
	}
		
	$final_string .= "<form action='concordance.php' method='get'>
		<td align=\"center\" width=\"20%\" class=\"concordgrey\" nowrap=\"nowrap\">&nbsp;";
	
	$final_string .= '<input type="submit" value="' . $randomButtonText . '"/>';
	
	$final_string .= '&nbsp;</td>';	

	$final_string .= url_printinputs(array(
		array('uT', ''), array('qname', $qname), array('newPostP', $newPostP_value)
		));

	$final_string .= '<input type="hidden" name="uT" value="y"/></form>';


	/* -------------------------- */
	/* create action control form */
	/* -------------------------- */
	$final_string .= '<form action="redirect.php" method="get"><td class="concordgrey" nowrap="nowrap">&nbsp;';
		
	$final_string .= '
		<select name="redirect">	
			<option value="newQuery" selected="selected">New query</option>
			<option value="thin">Thin...</option>
			<option value="freqList">Frequency breakdown</option>
			<option value="distribution">Distribution</option>
			<option value="sort">Sort</option>
			<option value="collocations">Collocations...</option>
			<option value="download">Download...</option>
			<option value="categorise">Categorise...</option>
			<option value="saveHits">Save current set of hits...</option>
		</select>
		&nbsp;
		<input type="submit" value="Go!"/>';
	
	$final_string .= url_printinputs(array(
		array('uT', ''), array('redirect', ''), array('qname', $qname)
		));
	
	$final_string .= '<input type="hidden" name="uT" value="y"/>&nbsp;</td></form>';
	
	
	
	
	/* finish off and return */
	$final_string .= '</tr>';

	return $final_string;
}




function print_column_headings()
{
	global $viewMode;
	global $conc_start;
	global $conc_end;
	global $page_no;
	global $num_of_pages;
	global $program;

	
	$final_string = '<tr><th class="concordtable">No</th>'
		. '<th class="concordtable">Filename</th><th class="concordtable"'
		. ( $viewMode == 'kwic' ? ' colspan="3"' : '' )
		. '>';
		
	$final_string .= "Solution $conc_start to $conc_end &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	
	$final_string .= "Page $page_no / $num_of_pages</th>";
	
	if ($program == 'categorise')
		$final_string .= '<th class="concordtable">Category</th>';
	
	$final_string .= '</tr>';

	return $final_string;
}




function print_sort_control($primary_annotation, $postprocess_string)
{
	global $corpus_main_script_is_r2l;
	/* get current sort settings : from the current query's postprocess string */
	/* ~~sort[position~thin_tag~thin_tag_inv~thin_str~thin_str_inv] */
	$command = array_pop(explode('~~', $postprocess_string));

	if (substr($command, 0, 4) == 'sort')
	{
		list($current_settings_position, 
			$current_settings_thin_tag, $current_settings_thin_tag_inv,
			$current_settings_thin_str, $current_settings_thin_str_inv)
			=
			explode('~', trim(substr($command, 4), '[]'));
		if ($current_settings_thin_tag == '.*')
			$current_settings_thin_tag = '';
		if ($current_settings_thin_str == '.*')
			$current_settings_thin_str = '';
	}
	else
	{
		$current_settings_position = 1;
		$current_settings_thin_tag = '';
		$current_settings_thin_tag_inv = 0;
		$current_settings_thin_str = '';
		$current_settings_thin_str_inv = 0;
	}


/*
show_var($d = array($current_settings_position, 
			$current_settings_thin_tag, $current_settings_thin_tag_inv,
			$current_settings_thin_str, $current_settings_thin_str_inv));
*/
	
	/* create a select box: the "position" dropdown */
	$position_select = '<select name="newPostP_sortPosition">';
	
	foreach(array(5,4,3,2,1) as $i)
	{
		$position_select .= "\n\t<option value=\"-$i\""
			. (-$i == $current_settings_position ? ' selected="selected"' : '')
			. ">$i Left</option>";
	}
	$position_select .= "\n\t<option value=\"0\""
		. (0 == $current_settings_position ? ' selected="selected"' : '')
		. ">Node</option>";
	foreach(array(1,2,3,4,5) as $i)
	{
		$position_select .= "\n\t<option value=\"$i\""
			. ($i == $current_settings_position ? ' selected="selected"' : '')
			. ">$i Right</option>";
	}

	$position_select .= '</select>';

	if ($corpus_main_script_is_r2l)
	{
		$position_select = str_replace('Left',  'Before', $position_select);
		$position_select = str_replace('Right', 'After',  $position_select);
	}

	/* create a select box: the "tag restriction" dropdown */
	if (!empty($primary_annotation))
		$taglist = corpus_annotation_taglist($primary_annotation);
	else
		$taglist = array();

	$tag_restriction_select = '<select name="newPostP_sortThinTag">
		<option value=""' . ('' === $current_settings_thin_tag ? ' selected="selected"' : '') 
		. '>None</option>';
	
	foreach ($taglist as &$tag)
		$tag_restriction_select .= '<option' . ($tag == $current_settings_thin_tag ? ' selected="selected"' : '')
				. ">$tag</option>\n\t";
	
	$tag_restriction_select .= '</select>';



	/* list of inputs with all the ones set by this form cleared */
	$forminputs = url_printinputs(array(
				array('pageNo', '1'),
				array('uT', ''),
				array('newPostP_sortThinString', ''),
				array('newPostP_sortThinStringInvert', ''),
				array('newPostP_sortThinTag', ''),
				array('newPostP_sortThinTagInvert', ''),
				array('newPostP_sortPosition', ''),
				) );

	/* all is now set up so we are ready to return the final string */
	return '
	<tr>
		<form action="concordance.php" method="get">
			<td colspan="4" class="concordgrey"><strong>Sort control:</td>
			<td class="concordgrey">
				Position:
				' . $position_select . '
			</td>
			<td class="concordgrey" nowrap="nowrap">
				Tag restriction:
				' . $tag_restriction_select . '
				<br/>
				<input type="checkbox" name="newPostP_sortThinTagInvert" value="1"'
				. ($current_settings_thin_tag_inv ? ' checked="checked"' : '')
				. ' /> exclude
			</td>
			<td class="concordgrey" nowrap="nowrap">
				Starting with:
				<input type="text" name="newPostP_sortThinString" value="'
				. $current_settings_thin_str 
				. '" />
				<br/>
				<input type="checkbox" name="newPostP_sortThinStringInvert" value="1"'
				. ($current_settings_thin_str_inv ? ' checked="checked"' : '')
				. ' /> exclude
			</td>
			<td class="concordgrey">
				&nbsp;
				<input type="submit" value="Update sort" />
			</td>
			' . $forminputs	. '
			<input type="hidden" name="newPostP_sortRemovePrevSort" value="1"/>
			<input type="hidden" name="newPostP" value="sort"/>
			<input type="hidden" name="uT" value="y"/>
		</form>
	</tr>';
}




/* creates the control bar at the bottom of "categorise" */

function print_categorise_control()
{
	global $viewMode;
	
	$final_string = '<tr><td class="concordgrey" align="right" colspan="'
		. ($viewMode == 'kwic' ? 6 : 4)
		.'">'
		.'
		<select name="categoriseAction">
			<option value="updateQueryAndLeave">Save values and leave categorisation mode</option>
			<option value="updateQueryAndNextPage" selected="selected">Save values for this page and go to next</option>
			<option value="noUpdateNewQuery">New Query (does not save changes to category values!)</option>
		</select>
		<input type="submit" value="Go!"/>'
		. "</td></tr>\n";
	
	return $final_string;

}












/* postprocesses a line from CQP's output_for display */
/* returns: a string containing the 3 or 5 cells */
/* WITHOUT <tr> or </tr> - so other things can be added */
/* for no highlight, set $highlight_position to a ridiculously large number (1000000 etc.) */
function print_concordance_line($cqp_line, $position_table, $line_number, 
	$highlight_position, $highlight_show_pos = false)
{
	global $qname;
	global $viewMode;
	global $primary_tag_handle;

	/* corpus positions of query anchors (see CQP tutorial) */
//	$match_p = $position_table[$i][0];
//	$matchend_p = $position_table[$i][1];
//	$target_p = $position_table[$i][2];
//	$keyword_p = $position_table[$i][3];
//// /* I'm not actually using these at the moment ? */
// list () would be briefer

	/* extract the text_id and delete that first bit of the line */
	preg_match("/\A\s*\d+: <text_id (\w+)>:/", $cqp_line, $m);
	$text_id = $m[1];
	$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+>:/", '', $cqp_line);

	/* divide up the CQP line */
	list($kwic_lc, $kwic_match, $kwic_rc) = explode('--%%%--', $cqp_line);	

	/* just in case of unwanted spaces (there will deffo be some on the left) ... */
	$kwic_rc = trim($kwic_rc);
	$kwic_lc = trim($kwic_lc);
	$kwic_match = trim($kwic_match);
	
	/* create arrays of words from the incoming variables: split at space */	
	$lc = explode(' ', $kwic_lc);
	$rc = explode(' ', $kwic_rc);
	$node = explode(' ', $kwic_match);
	
	/* how many words in each array? */
	$lcCount = count($lc);
	$rcCount = count($rc);
	$nodeCount = count($node);


	/* forward slash can be part of a word, but not part of a tag */
	$word_extraction_pattern = (empty($primary_tag_handle) ? false : '/\A(.*)\/(.*?)\z/');

	/* left context string */
	$lc_string = '';
	$lc_tool_string = '';
	for ($i = 0; $i < $lcCount; $i++) 
	{
		list($word, $tag) = extract_cqp_word_and_tag($word_extraction_pattern, $lc[$i]);
		
		if ($i == 0 && preg_match('/\A[.,;:?\-!"]\Z/', $word))
			/* don't show the first word of left context if it's just punctuation */
			continue;

		if ($highlight_position == ($i - $lcCount)) /* if this word is the word being sorted on / collocated etc. */
		{
			$lc_string .= '<span class="contexthighlight">' . $word . ($highlight_show_pos ? $tag : '') . '</span> ';
			$lc_tool_string .= '<B>' . $word . $tag . '</B> ';
		}
		else
		{
			$lc_string .= $word . ' ';
			$lc_tool_string .= $word . $tag . ' ';
		}
	}

	/* node string */
	$node_string = '';
	$node_tool_string = '';
	for ($i = 0; $i < $nodeCount; $i++) 
	{
		list($word, $tag) = extract_cqp_word_and_tag($word_extraction_pattern, $node[$i]);


		/* if this word is the word being sorted on / collocated etc. */
		/* the only thing that is different is the possibility of the tag being shown */
		/* there is no extra highlighting beyong what the node has already */
		$node_string .= $word . (($highlight_position == 0 && $highlight_show_pos) ? $tag : '') . ' ';
		
		$node_tool_string .= $word . $tag . ' ';
	}
	/* extra step needed because otherwise a space may get linkified */
	$node_string = trim($node_string);
	
	/* right context string */
	$rc_string = '';
	$rc_tool_string = '';
	for ($i = 0; $i < $rcCount; $i++) 
	{
		list($word, $tag) = extract_cqp_word_and_tag($word_extraction_pattern, $rc[$i]);
		
		if ($highlight_position == $i+1) /* if this word is the word being sorted on / collocated etc. */
		{
			$rc_string .= '<span class="contexthighlight">' . $word . ($highlight_show_pos ? $tag : '') . '</span> ';
			$rc_tool_string .= '<B>' . $word . $tag . '</B> ';
		}
		else
		{
			$rc_string .= $word . ' ';
			$rc_tool_string .= $word . $tag . ' ';
		}
	}

	$full_tool_tip = "onmouseover=\"return escape('"
		. str_replace('\'', '\\\'', $lc_tool_string . '<FONT COLOR=&quot;#DD0000&quot;>'
			. $node_tool_string . '</FONT> ' . $rc_tool_string)	
		. "')\"";
		// BNC web has a font reset instead of </FONT>, namely "<FONT COLOR=&quot;#000066&quot;>"
		
	
	/* last pre-built component is the URL of the extra-context page */
	$context_url = 'context.php?batch=' . ($line_number-1) . '&qname=' . $qname . '&uT=y';


	/* print cell with line number */
	$final_string = "<td class=\"text_id\"><b>$line_number</b></td>";
	
	$final_string .= "<td class=\"text_id\"><a href=\"textmeta.php?text=$text_id&uT=y\" "
		. metadata_tooltip($text_id) . '>' . $text_id . '</a></td>';
	
	if ($viewMode == 'kwic') 
	{
		/* we need to check for left-to-right vs. right-to-left */
		global $corpus_main_script_is_r2l;
		if ($corpus_main_script_is_r2l)
		{
			$temp_r2l_string = $lc_string;
			$lc_string = $rc_string;
			$rc_string = $temp_r2l_string;
		}
	
		/* print three cells - kwic view */
		$final_string .= '<td class="before" nowrap="nowrap">' . $lc_string . '</td>';
		
		$final_string .= '<td class="node" nowrap="nowrap"><b>'
			. '<a class="nodelink" href="' . $context_url . '" '
			. $full_tool_tip . '>' . $node_string . '</a></b></td>';
		
		$final_string .= '<td class="after" nowrap="nowrap">' . $rc_string . '</td>';
	}
	else
	{
		/* print one cell - line view */
		$final_string .= '<td class="lineview">' . $lc_string . ' ';
		$final_string .= '<b><a class="nodelink" href="' . $context_url . '" '
			. $full_tool_tip . '>' . $node_string . '</a></b>';
		$final_string .= ' ' . $rc_string . '</td>';
	}
	
	$final_string .= "\n";

	return $final_string;
}



/* used by print_concordance_line above and also by context.inc.php */
/* returns an array of word, tag */
function extract_cqp_word_and_tag(&$word_extraction_pattern, &$cqp_source_string)
{
	if ($word_extraction_pattern)
	{
		preg_match($word_extraction_pattern, cqpweb_htmlspecialchars($cqp_source_string), $m);
		$word = $m[1];
		$tag = '_' . $m[2];
	}
	else
	{
		$word = cqpweb_htmlspecialchars($cqp_source_string);
		$tag = '';
	}
	return array($word, $tag);
}



/* print a sorry-no-solutions page, shut down CQP, and end */
//TODO: check whether or not this should have the HTML header here
function say_sorry($instance_name, $sorry_input = "no_solutions")
{
	history_update_hits($instance_name, 0);
	$errorType = "";

	/* this references a global variable - it's meant to do so */
//	if ( preg_match("/\'/", $theData) > 0 && preg_match('/\"/', $theData == 0))
//		$errorType = "<i>Possible reason:</i> you are using an apostrophe 
//			without quotation marks.<br/>Please consult the manual 
//			to find out how to search for contracted forms.";
	
	if ($sorry_input == "no_files")
		$errorText = "There are no files that match your restrictions.";
	else /* sorry_input is "no_solutions" */
		$errorText = "<br/><b>There are no matches for your query.";
	?>
		<table width="100%">
			<tr>
				<td>
					<!-- To do: proper structural formatting here -->
					<p class="errormessage"><b>Your query had no results.</b></p>
				</td>
			</tr>
			<tr>
				<td>
					<p class="errormessage">
						<?php echo $errorText . "<br/>\n" . $errorType . "\n"; ?>
					</p>
				</td>
			</tr>
		</table>
	<?php

	print_footer();
	disconnect_all();
	exit(0);
}

/* print a sorry-no-solutions page, shut down CQP, and end */
//TODO: same as previous function
function say_sorry_postprocess()
{
	$errorText = "<br/><b>There are no matches left in your query.";
	?>
		<table width="100%">
			<tr>
				<td>
					<!-- To do: proper structural formatting here -->
					<p class="errormessage"><b>No results were left after performing that operation!.</b></p>
					<p class="errormessage"><b>Press [Back] and try again.</b></p>
				</td>
			</tr>
			<tr>
				<td>
					<p class="errormessage">
						<?php echo $errorText . "<br/>\n" . "\n"; ?>
					</p>
				</td>
			</tr>
		</table>
	<?php

	print_footer();
	disconnect_all();
	exit(0);
}





?>
