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


/*
 * TODO
 * 
 * A lot of the functions in this file could do with renaming in a way that at least AIMS
 * to be systematic.
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
	$s = str_replace("\r", ' ', $s);
	$s = str_replace("\n", ' ', $s);
	$s = str_replace("\t", ' ', $s);
	$s = str_replace('  ', ' ', $s);
	/* note we do NOT use %0D, %0A etc. because PHP htmldecodes for us. */
	
	return $s;
}


/**
 * This function gets one of the allowed query-mode strings from $_GET.
 * 
 * If no valid query-mode is specified, it (a) causes CQPweb to abort if
 * $strict is true; OR (b) returns NULL if $strict is false. 
 */
function prepare_query_mode($s, $strict = true)
{
	$strict = (bool)$strict;
	
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
	global $username;
	
	if ($subcorpus != 'no_subcorpus')
	{
		$sql_query = "select numwords, numfiles from saved_subcorpora
			WHERE subcorpus_name = '$subcorpus'
			AND corpus = '$corpus_sql_name'
			AND user = '$username'";

		$result = do_mysql_query($sql_query);
				
		return mysql_fetch_row($result);
	}
	else
	{
		/* this works for a restriction, or even if the whole corpus is being searched */
		$sql_query = "select sum(words), count(*) from text_metadata_for_$corpus_sql_name";

		$sql_query .= ($restrictions == 'no_restriction' ? '' : ' where ' . $restrictions);

		$result = do_mysql_query($sql_query);

		return mysql_fetch_row($result);
	}
}



// so useful, it should prob be in library
/* takes a "query-record" style associative array */
function create_solution_heading($record, $include_corpus_size = true)
{
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

	if ($qmode != 'uploaded')
	{
		if ( $qmode == 'cqp' || $simple_query == '' )
			$final_string .= htmlspecialchars($cqp_query, ENT_QUOTES, 'UTF-8', false);
		else
			$final_string .= htmlspecialchars($simple_query, ENT_QUOTES, 'UTF-8', false);
	
		$final_string .= "&rdquo;";
	}
	else
		$final_string = 'Your uploaded query';


	if ($subcorpus != 'no_subcorpus')
		$final_string .= ', in subcorpus &ldquo;<em>' . $subcorpus . '</em>&rdquo;';
	else if ($restrictions != 'no_restriction')
		$final_string .= ', restricted to ' . translate_restrictions_to_prose($restrictions) . ',';

		
	$final_string .= ' returned ' . make_thousands($num_of_solutions) . ' matches';	


	if ($num_of_files > 1) 
		$final_string .= ' in ' . make_thousands($num_of_files) . ' different texts';
	else 
		$final_string .= ' in 1 text';



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
	$str = '';
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
	global $visualise_translate_in_concordance;

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
	if ($visualise_translate_in_concordance)
	{
		$final_string .= "<td align=\"center\" width=\"20%\" class=\"concordgrey\" nowrap=\"nowrap\">No KWIC view available</td>";
	}
	else
	{
		$final_string .= "<form action=\"concordance.php\" method=\"get\"><td align=\"center\" width=\"20%\" class=\"concordgrey\" nowrap=\"nowrap\">&nbsp;";
		
		$final_string .= "<input type=\"submit\" value=\"$reverseViewButtonText\"/>";
			
		$final_string .= '&nbsp;</td>';
		
		$final_string .= url_printinputs(array(
			array('uT', ''), array('viewMode', "$reverseViewMode"), array('qname', $qname)
			));
		
		$final_string .= '<input type="hidden" name="uT" value="y"/></form>';
	}
	


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

	$custom_options = '';
	foreach (list_plugins_of_type(PLUGIN_TYPE_POSTPROCESSOR) as $record)
	{
		$obj = new $record->class($record->path);
		$label = $obj->get_label();
		$custom_options .= "<option value=\"CustomPost:{$record->class}\">$label</option>\n\t\t\t";
		unset($obj);
	}
	
	$final_string .= '<form action="redirect.php" method="get"><td class="concordgrey" nowrap="nowrap">&nbsp;';
		
	$final_string .= '
		<select name="redirect">	
			<option value="newQuery" selected="selected">New query</option>
			<option value="thin">Thin...</option>
			<option value="breakdown">Frequency breakdown</option>
			<option value="distribution">Distribution</option>
			<option value="sort">Sort</option>
			<option value="collocations">Collocations...</option>
			<option value="download-conc">Download...</option>
			<option value="categorise">Categorise...</option>
			<option value="saveHits">Save current set of hits...</option>
			' . $custom_options . '
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












/**
 * Processes a line of CQP output for display in the CQPweb concordance table.
 * 
 * This is done with regard to certain rendering-control variables esp. related to gloss
 * visualisation.
 * 
 * Returns a line of 3 or 5 td's that can be wrapped in a pair of tr's, or have other
 * cells added (e.g. for categorisation).
 * 
 * Note no tr's are added at this point.
 * 
 * In certain display modes, these td's may have other smaller tables within them.
 * 
 * @param cqp_line				A line of output from CQP.
 * @param position_table		I have no idea what this is for.
 * @param line_number			The line number to be PRINTED (counted from 1)
 * @param highlight_position	The entry in left or right context to be highlit. 
 * 								Set to a ridiculously large number (such as 10000000)
 * 								to get no highlight.
 * @param highlight_show_pos	Boolean: show the primary annotation of the highlit
 * 								item in-line.  
 * @return                      The built-up line.
 */
function print_concordance_line($cqp_line, $position_table, $line_number, 
	$highlight_position, $highlight_show_pos = false)
{
	global $viewMode;
	global $primary_tag_handle;
	global $visualise_gloss_in_concordance;
	global $visualise_translate_in_concordance;

	/* corpus positions of query anchors (see CQP tutorial) * /
	// list () would be briefer
	$match_p = $position_table[$i][0];
	$matchend_p = $position_table[$i][1];
	$target_p = $position_table[$i][2];
	$keyword_p = $position_table[$i][3];
	/* I'm not actually using these at the moment ? */

	/* get URL of the extra-context page right at the beginning, 
	 * because we don't know when we may need it */
	$context_url = concordance_line_get_context_url($line_number);

	if ($visualise_translate_in_concordance)
	{
		global $visualise_translate_s_att;
		/* extract the translation content, which will be BEFORE the text_id */
		preg_match("/<$visualise_translate_s_att (.*?)><text_id/", $cqp_line, $m);
		$translation_content = $m[1];
		$cqp_line = preg_replace("/<$visualise_translate_s_att .*?><text_id/", '<text_id', $cqp_line);
	}

	/* extract the text_id and delete that first bit of the line */
	extract_cqp_line_position_labels($cqp_line, $text_id, $position_label);
	
	/* divide up the CQP line */
	list($kwic_lc, $kwic_match, $kwic_rc) = explode('--%%%--', $cqp_line);

	/* left context string */
	list($lc_string, $lc_tool_string) 
		= concordance_line_blobprocess($kwic_lc, 'left', $highlight_position, $highlight_show_pos);

	list($node_string, $node_tool_string) 
		= concordance_line_blobprocess($kwic_match, 'node', $highlight_position, $highlight_show_pos, $context_url);

	/* right context string */
	list($rc_string, $rc_tool_string) 
		= concordance_line_blobprocess($kwic_rc, 'right', $highlight_position, $highlight_show_pos);

	/* if the corpus is r-to-l, this function call will spot it and handle things for us */
	right_to_left_adjust($lc_string, $lc_tool_string, $node_string, $node_tool_string, $rc_string,$rc_tool_string); 



	/* create final contents for putting in the cells */
	if ($visualise_gloss_in_concordance)
	{
		$lc_final   = build_glossbox('left', $lc_string, $lc_tool_string);
		$node_final = build_glossbox('node', $node_string, $node_tool_string);
		$rc_final   = build_glossbox('right', $rc_string, $rc_tool_string);
	}
	else
	{
		$lc_final = $lc_string;
		$rc_final = $rc_string;
		
		/* the untidy HTML here is inherited from BNCweb. */
		$full_tool_tip = "onmouseover=\"return escape('"
			. str_replace('\'', '\\\'', $lc_tool_string . '<font color=&quot;#DD0000&quot;>'
				. $node_tool_string . '</font> ' . $rc_tool_string)	
			. "')\"";
		$node_final = '<b><a class="nodelink" href="' . $context_url . '" '
				. $full_tool_tip . '>' . $node_string . '</a></b>';
	}


	/* print cell with line number */
	$final_string = "<td class=\"text_id\"><b>$line_number</b></td>";
	
	$final_string .= "<td class=\"text_id\"><a href=\"textmeta.php?text=$text_id&uT=y\" "
		. metadata_tooltip($text_id) . '>' . $text_id . ($position_label === '' ? '' : " $position_label") . '</a></td>';

	
	if ($viewMode == 'kwic')
	{
		/* print three cells - kwic view */

		$final_string .= '<td class="before" nowrap="nowrap"><div class="before">' . $lc_final   . '</div></td>';

		$final_string .= '<td class="node"   nowrap="nowrap">'                     . $node_final . '</td>';
		
		$final_string .= '<td class="after"  nowrap="nowrap"><div class="after">'  . $rc_final   . '</div></td>';
	}
	else
	{
		/* print one cell - line view */
		
		/* glue it all together, then wrap the translation if need be */
		$subfinal_string =  $lc_final . ' ' . $node_final . ' ' . $rc_final;
		if ($visualise_translate_in_concordance)
			$subfinal_string = concordance_wrap_translationbox($subfinal_string, $translation_content);
		
		/* and add to the final string */
		$final_string .= '<td class="lineview">' . $subfinal_string . '</td>';
	}

	$final_string .= "\n";

	return $final_string;
}




/**
 * Converts a node-or-right-or-left context string from CQP output into
 * two strings ready for printing in CQPweb.
 * 
 * The FIRST string is the "main" string; the one that is the principle
 * readout. The SECOND string is the "other" string: either for a 
 * tag-displaying tooltip, or for the gloss-line when the gloss is visible.
 * 
 * This function gets called 3 times per hit, obviously.
 * 
 * Note: we do not apply links here in normal mode, but if we are visualising
 * a gloss, then we have to (because the node gets buried in the table
 * otherwise).
 */
function concordance_line_blobprocess($lineblob, $type, $highlight_position, $highlight_show_pos = false, $context_url = '')
{
	global $visualise_gloss_in_concordance;
	
	/* all string literals (other than empty strings or spacers) 
	 * must be here so they can be conditionally set. */
	if ($type == 'node')
	{
		$main_begin_high = '';
		$main_end_high = '';
		$other_begin_high = '';
		$other_end_high = '';
		$glossbox_nodelink_begin = '<b><a class="nodelink" href="' . $context_url . '" >';
		$glossbox_nodelink_end = '</a></b>';
	}
	else
	{
		$main_begin_high = '<span class="contexthighlight">';
		$main_end_high = '</span> ';
		$other_begin_high = '<b>';
		$other_end_high = '</b> ';
		$glossbox_nodelink_begin = '';
		$glossbox_nodelink_end = '';
	}
	/* every glossbox will contain a bolded link, if it is a node; otherwise not */
	$glossbox_line1_cell_begin = "<td class=\"glossbox-$type-line1\" nowrap=\"nowrap\">$glossbox_nodelink_begin";
	$glossbox_line2_cell_begin = "<td class=\"glossbox-$type-line2\" nowrap=\"nowrap\">$glossbox_nodelink_begin";
	$glossbox_end = $glossbox_nodelink_end . '</td>';
	/* end of string-literals-into-variables section */

	
	/* the "trim" is just in case of unwanted spaces (there will deffo be some on the left) ... *///show_var(htmlspecialchars($lineblob));
	/* this regular expression puts tokens in $m[4]; xml-tags-before in $m[1]; xml-tags-after in $m[5] . */
	preg_match_all('|((<\S+?( \S+?)?>)*)([^ <]+)((</\S+?>)*) ?|', trim($lineblob), $m, PREG_PATTERN_ORDER);
	/* note, this is p[rone to interference from literal < in the index.
	 * TODO: 
	 * Will be fixable when we have XML concordance output in CQP v 4.0 */
	$token_array = $m[4];
	$xml_before_array = $m[1];
	$xml_after_array = $m[5];

	$n = (empty($token_array[0]) ? 0 : count($token_array));
	
	/* if we are in the left string, we need to translate the highlight position from
	 * a negative number to a number relative to 0 to $n... */
	if ($type == 'left')
		$highlight_position = $n + $highlight_position + 1;
	
	/* these are the strings we will build up */
	$main_string = '';
	$other_string = '';
	
	for ($i = 0; $i < $n; $i++) 
	{
/* TODO replace with actual function to render the XML viz, rather than just HTML speciallchars */
$xml_before_string = htmlspecialchars($xml_before_array[$i]) . ' ';
$xml_after_string =  ' ' . htmlspecialchars($xml_after_array[$i]);
		list($word, $tag) = extract_cqp_word_and_tag($token_array[$i]);

		if ($type == 'left' && $i == 0 && preg_match('/\A[.,;:?\-!"]\Z/', $word))
			/* don't show the first word of left context if it's just punctuation */
			continue;
	
		if (!$visualise_gloss_in_concordance)
		{
			/* the default case: we are buiilding a concordance line and a tooltip */
			if ($highlight_position == $i+1) /* if this word is the word being sorted on / collocated etc. */
			{
				$main_string .= "$xml_before_string$main_begin_high$word"
					. ($highlight_show_pos ? $tag : '') 
					. "$main_end_high$xml_after_string" ;
				$other_string .= "$other_begin_high$word$tag$other_end_high";
			}
			else
			{
				$main_string .= "$xml_before_string$word$xml_after_string ";
				$other_string .= "$word$tag ";
			}
		}
		else
		{
			/* build a gloss-table instead;
			 * other_string will be the second line of the gloss table instead of a tooltip */
			if ($highlight_position == $i+1)
			{
				$main_string .= "$glossbox_line1_cell_begin$xml_before_string$main_begin_high$word$main_end_high$xml_after_string$glossbox_end";
				$other_string .= "$glossbox_line2_cell_begin$main_begin_high$tag$main_end_high$glossbox_end";
			}
			else
			{
				$main_string .= "$glossbox_line1_cell_begin$xml_before_string$word$xml_after_string$glossbox_end";
				$other_string .= "$glossbox_line2_cell_begin$tag$glossbox_end";
			}	
		}
	}
	if ($main_string == '' && !$visualise_gloss_in_concordance)
		$main_string = '&nbsp;';
	

	/* extra step needed because otherwise a space may get linkified */
	if ($type == 'node')
		$main_string = trim($main_string);

	return array($main_string, $other_string);
}

/** Pass in the (printed) line number, get back a relative URL to the context page. */
function concordance_line_get_context_url($line_number)
{
	global $qname;
	return 'context.php?batch=' . ($line_number-1) . '&qname=' . $qname . '&uT=y';
}

/** 
 * Switches around the contents of the left/right strings, if necessary, 
 * to support L2R scripts. 
 * 
 * All parameters are passed by reference.
 */
function right_to_left_adjust(&$lc_string,   &$lc_tool_string, 
                              &$node_string, &$node_tool_string, 
                              &$rc_string,   &$rc_tool_string)
{
	global $viewMode;
	global $corpus_main_script_is_r2l;
	global $visualise_gloss_in_concordance;

	if ($corpus_main_script_is_r2l)
	{
		/* ther are two entirely different styles of reordering.
		 * (1) if we are using glosses (strings of td's all over the shop)
		 * (2) if we have the traditional string-o'-words
		 */ 
		if ($visualise_gloss_in_concordance)
		{
			/* invert the order of table cells in each string. */
			$lc_string        = concordance_invert_tds($lc_string);
			$lc_tool_string   = concordance_invert_tds($lc_tool_string);
			$node_string      = concordance_invert_tds($node_string);
			$node_tool_string = concordance_invert_tds($node_tool_string);
			$rc_string        = concordance_invert_tds($rc_string);
			$rc_tool_string   = concordance_invert_tds($rc_tool_string);
			/* note this is done regardless of whether we are in kwic or line */	
			/* similarly, regardless of whether we are in kwic or line, we need to flip lc and rc */
			$temp_r2l_string = $lc_string;
			$lc_string = $rc_string;
			$rc_string = $temp_r2l_string;
			/* we need ot do the same with the tooltips too */
			$temp_r2l_string = $lc_tool_string;
			$lc_tool_string = $rc_tool_string;
			$rc_tool_string = $temp_r2l_string;
		}
		else
		{
			/* we only need to worry in kwic. In line mode, the flow of th
			 * text and the normal text-directionality systems in the browser
			 * will deal wit it for us.*/
			if ($viewMode == 'kwic')
			{
				$temp_r2l_string = $lc_string;
				$lc_string = $rc_string;
				$rc_string = $temp_r2l_string;
			}
		}
	}
	/* else it is an l-to-r script, so do nothing. */
}

/**
 * Build a two-line (or three line?) glossbox table from
 * two provided sequences of td's.
 * 
 * $type must be left, node, or right (as a string). Anything
 * else will be treated as if it was "node".
 */
function build_glossbox($type, $line1, $line2, $line3 = false)
{
	global $corpus_main_script_is_r2l;
	global $viewMode;
	if ($viewMode =='kwic')
	{
		switch($type)
		{
			case 'left':	$align = 'right';	break;
			case 'right':	$align = 'left';	break;
			default:		$align = 'center';	break;
		}
	}
	else
		$align = ($corpus_main_script_is_r2l ? 'right' : 'left');
	
	if (empty($line1) && empty($line2))
		return '';
		
	return 	'<table class="glossbox" align="' . $align . '"><tr>'
			. $line1
			. '</tr><tr>' 
			. $line2
			. '</tr>'
			. ($line3 ? '' : '')
			. '</table>';
}

function concordance_wrap_translationbox($concordance, $translation)
{
	return 
		'<table class="transbox"><tr><td class="transbox-top">'
		. $concordance
		. '</td></tr><tr><td class="transbox-bottom">'
		. $translation
		. "\n</td></tr></table>\n";
			
}

/**
 * Takes a string consisting of a sequence of td's.
 * 
 * Returns the same string of td's, in the opposite order.
 * Note - if there is material outside the td's, results may
 * be unexpected. Should not be used outside the concordance
 * line rendering module.
 */
function concordance_invert_tds($string)
{
	$stack = explode('</td>', $string);
	
	$newstring = '';
	
	while (! is_null($popped = array_pop($stack)))
	{
		/* there will prob be an empty string at the end,
		 * from after the last end-td. We don't want to add this.
		 * But all the other strings in $stack should be "<td>...".
		 */ 
		if (!empty($popped))
			$newstring .= $popped . '</td>';	
	}
	
	return $newstring;
}


/**
 * Function used by print_concordance_line. 
 * 
 * Also used in context.inc.php.
 * 
 * It takes a single word/tag string from the CQP concordance line, and
 * returns an array of 0 => word, 1 => tag 
 */
function extract_cqp_word_and_tag($cqp_source_string)
{
	global $visualise_gloss_in_concordance;
	
	static $word_extraction_pattern = NULL;
	if (is_null($word_extraction_pattern))
	{ 
		/* on the first call only: only deduce the pattern once per run of the script */
		global $primary_tag_handle;
		global $visualise_gloss_in_concordance;
		
		/* OK, this is how it works: if EITHER a primary tag is set, OR we are visualising 
		 * glosses, then we must split the token into word and tag using a regex.
		 * 
		 * If NEITHER of these things is the case, then we don't use a regex.
		 * 
		 * Note that we assume that forward slash can be part of a word, but not part of a 
		 * (primary) tag.
		 * 
		 * [TODO: note this in the manual] 
		 */
		$word_extraction_pattern = 
			(  (empty($primary_tag_handle)&&!$visualise_gloss_in_concordance) ? false : '/\A(.*)\/([^\/]*)\z/' );
	}
	
	if ($word_extraction_pattern)
	{
		preg_match($word_extraction_pattern, cqpweb_htmlspecialchars($cqp_source_string), $m);
		if (!isset($m[1], $m[2]))
		{
			/* a fallback case, for if the regular expression is derailed;
			 * should only happen wtih badly-encoded XML tags. */
			$word = '[UNREADABLE]';
			$tag = $visualise_gloss_in_concordance ? '[UNREADABLE]' : '_';
		}
		else
		{
			/* this will nearly always be the case. */
			$word = $m[1];
			$tag = ($visualise_gloss_in_concordance ? '' : '_') . $m[2];
		}
	}
	else
	{
		$word = cqpweb_htmlspecialchars($cqp_source_string);
		$tag = '';
	}
	return array($word, $tag);
}

/**
 * Extracts the position inidicators (text_id and, optionally, one other) and place them
 * in the given variables; scrub them from the CQP line and put the new CQP line
 * back in the variable the old one came from.
 * 
 * Returns nothing; modifies all its parameters.
 * 
 * Note that if the corpus is set up to not use a position label, that argument will be
 * set to an empty string.
 */ 
function extract_cqp_line_position_labels(&$cqp_line, &$text_id, &$position_label)
{
	global $visualise_position_labels;
	global $visualise_position_label_attribute;

	if ($visualise_position_labels)
	{
		/* if a position label is to be used, it is extracted from between <text_id ...> and the colon. */
		if (0 < preg_match("/\A\s*\d+: <text_id (\w+)><$visualise_position_label_attribute ([^>]+)>:/", $cqp_line, $m) )
		{
			$text_id = $m[1];
			$position_label = cqpweb_htmlspecialchars($m[2]);
			$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+><$visualise_position_label_attribute [^>]+>:/", '', $cqp_line);
		}
		else
		{
			/* Position label could not be extracted, sojust extract text_id */
			preg_match("/\A\s*\d+: <text_id (\w+)><$visualise_position_label_attribute>:/", $cqp_line, $m);
			$text_id = $m[1];
			$position_label = '';
			$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+><$visualise_position_label_attribute>:/", '', $cqp_line);
			/* note it IS NOT THE SAME as the "normal" case below: the s-att still prints, just wihtout a value */		
		}
	}
	else
	{
		/* If we have no position label, just extract text_id */
		preg_match("/\A\s*\d+: <text_id (\w+)>:/", $cqp_line, $m);
		$text_id = $m[1];
		$position_label = '';
		$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+>:/", '', $cqp_line);
	}
}


/* print a sorry-no-solutions page, shut down CQP, and end */
//TODO: This should actually output a full page inc. HTML header
function say_sorry($instance_name, $sorry_input = "no_solutions")
{
	history_update_hits($instance_name, 0);
	$errorType = "";

	if ($sorry_input == "no_files")
		$errorText = "There are no files that match your restrictions.";
	else /* sorry_input is "no_solutions" */
		$errorText = "There are no matches for your query.";
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
