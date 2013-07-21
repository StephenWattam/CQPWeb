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






function print_statistic_form_options($index_to_select)
{
	$statistic = load_statistic_info();
	$output = '';
	foreach($statistic as $index => $s)
	{
		if ($index == 0)		/* this one is saved for last */
			 continue;
		$output .= "\n\t<option value=\"$index\""
			. ($index_to_select == $index ? ' selected="selected"' : '')
			. ">{$s['desc']}</option>";
	}
	
	$output .= "<option value=\"0\" " . ($index_to_select == 0 ? ' selected="selected"' : '') 
		. ">{$statistic[0]['desc']}</option>";

	return $output;
}



function print_fromto_form_options($colloc_range, $index_to_select_from, $index_to_select_to)
{
	global $corpus_main_script_is_r2l;

	if ($corpus_main_script_is_r2l)
	{
		$rightlabel = ' after the node';
		$leftlabel = ' before the node';
	}
	else
	{
		$rightlabel = ' to the Right';
		$leftlabel = ' to the Left'; 
	}

	$output1 = $output2 = '';
	for ($i = -$colloc_range ; $i <= $colloc_range ; $i++)
	{
		if ( $i > 0 )
			$str = $i . $rightlabel;
		else if ( $i < 0 )
			$str = (-1 * $i) . $leftlabel;
		else   /* i is 0 so do nothing */
			continue;
	
		$output1 .= "\n\t<option value=\"$i\"" 
			. ($i == $index_to_select_from ? ' selected="selected"' : '')
			. ">$str</option>";
		$output2 .= "\n\t<option value=\"$i\"" 
			. ($i == $index_to_select_to   ? ' selected="selected"' : '') 
			. ">$str</option>";
	}
	return array($output1, $output2);
}


function print_freqtogether_form_options($index_to_select)
{
	$string = '';
	foreach(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 50, 100) as $n)
		$string .= '
			<option' . ($n == $index_to_select ? ' selected="selected"' : '')
			. ">$n</option>";
	return $string;
}

function print_freqalone_form_options($index_to_select)
{
	$string = '';
	foreach(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 50, 100, 500, 1000, 5000, 10000, 20000, 50000) as $n)
		$string .= '
			<option' . ($n == $index_to_select ? ' selected="selected"' : '') . ">$n</option>";
	return $string;
}









function load_statistic_info()
{
	$info = array();

	/* the labels for the different stats are as follows ... */
	/* note, the 0 is a special index - ie no statistic! (use in if statements and the like) */
	$info[0]['desc'] = 'Rank by frequency';
	$info[1]['desc'] = 'Mutual information';
	$info[2]['desc'] = 'MI3';
	$info[3]['desc'] = 'Z-score';
	$info[4]['desc'] = 'T-score';
//	$info[5]['desc'] = 'Chi-squared with Yates' correction';		// this apparently turned off in BW?
	$info[6]['desc'] = 'Log-likelihood';
	$info[7]['desc'] = 'Dice coefficient';

	return $info;

}



/**
 * Gets the SQL statement required to generate the collocation table. 
 * 
 * Field names (keys) for the table you get back when you actually
 * run the resulting query:
 *
 * $att 		 -- the collocate itself, with the name of the attribute it comes from as the field name 
 * observed 	 -- the number of times the collocate occurs in the window
 * expected 	 -- the number of times the collocate would occur in the window given smooth distribution
 * significance  -- the statistic [NOT PRESENT IF IT'S FREQ ONLY]
 * freq 		 -- the freq of that word or tag in the entire corpus (or subcorpus, etc)
 * text_id_count -- the number of texts in which the collocation occurs  
 */

function create_statistic_sql_query($stat, $soloform = '')
{
	global $corpus_sql_name;
	
	/* should these be parameters instead of globals? */
	// probably YES.
	global $dbname;
	global $att_for_calc;
	global $calc_range_begin;
	global $calc_range_end;
	global $calc_minfreq_collocalone;
	global $calc_minfreq_together;
	global $tag_filter;
	global $primary_annotation;
	global $freq_table_to_use;
	global $download_mode;

	global $begin_at;

	/* abbreviate the name for nice-ness in this function */
	$freq_table = $freq_table_to_use;
	
	/* table-field-cluase shorthand combos */
	
	/* the column in the db that is being collocated on */
	$item = "$dbname.$att_for_calc";
	
	$tag_clause = colloc_tagclause_from_filter($dbname, $att_for_calc, $primary_annotation, $tag_filter);

	/* number to show on one page */
	$limit_string = ($download_mode ? '' : "LIMIT $begin_at, 50");	
// shouldn't this be "per page"????


	/* the condition for including only the collocates within the window */
	if ($calc_range_begin == $calc_range_end)
		$range_condition = "dist = $calc_range_end";
	else
		$range_condition = "dist between $calc_range_begin and $calc_range_end";


	/* sql_endclause -- a block at the end which is the same regardless of the statistic */
	if ($soloform === '')
	{
		/* the normal case */
		$sql_endclause = "where $item = $freq_table.item
			and $range_condition
			$tag_clause
			and $freq_table.freq >= $calc_minfreq_collocalone
			group by $item
			having observed >= $calc_minfreq_together
			order by significance desc 
			$limit_string
			";
	}
	else
	{
		/* if we are getting the formula for a solo form */
		$sql_endclause = "where $item = $freq_table.item
			and $range_condition
			$tag_clause
			and $item = '$soloform'
			group by $item
			";		
	}



	/* shorthand variables for contingency table */
	$N   = calculate_total_basis($freq_table_to_use);
	$R1  = calculate_words_in_window();
	$R2  = $N - $R1;		
	$C1  = "($freq_table.freq)";
	$C2  = "($N - $C1)";
	$O11 = "1e0 * COUNT($item)";
	$O12 = "($R1 - $O11)";
	$O21 = "($C1 - $O11)";
	$O22 = "($R2 - $O21)";
	$E11 = "($R1 * $C1 / $N)";
	$E12 = "($R1 * $C2 / $N)";
	$E21 = "($R2 * $C1 / $N)";
	$E22 = "($R2 * $C2 / $N)";

	/*
	
	2-by-2 contingency table
	
	--------------------------------
	|        | Col 1 | Col 2 |  T  |
	--------------------------------
	| Row 1  | $O11  | $O12  | $R1 |
	|        | $E11  | $E12  |     |
	--------------------------------
	| Row 2  | $O21  | $O22  | $R2 |
	|        | $E21  | $E22  |     |
	--------------------------------
	| Totals | $C1   | $C2   | $N  |
	--------------------------------
	
	N   = total words in corpus (or subcorpus or restriction, but they are not implemented yet)
	C1  = frequency of the collocate in the whole corpus
	C2  = frequency of words that aren't the collocate in the corpus
	R1  = total words in window
	R2  = total words outside of window
	O11 = how many of collocate there are in the window 
	O12 = how many words other than the collocate there are in the window (calculated from row total)
	O21 = how many of collocate there are outside the window
	O22 = how many words other than the collocate there are outside the window
	E11 = expected values (proportion of collocate that would belong in window if collocate were spread evenly)
	E12 =     "    "      (proportion of collocate that would belong outside window if collocate were spread evenly)
	E21 =     "    "      (proportion of other words that would belong in window if collocate were spread evenly)
	E22 =     "    "      (proportion of other words that would belong outside window if collocate were spread evenly)
	
	*/

	// there are others not used for LL which will need to be added as the other stats are added in.
	// these can be changed by $thinFactor
	
	switch ($stat)
	{
	case 0:		/* Rank by frequency */
		$sql = "select $item, $O11 as observed,  $E11 as expected,
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $bwMYSQLusertable.$dbname, $freq_table 
			$sql_endclause";
		/* for rank by freq, we need to sort by something other than frequency */
		$sql = str_replace('order by significance', 'order by observed', $sql);
		break;
	
	case 1:		/* Mutual information */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			log2($O11 / $E11) as significance, $freq_table.freq,
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
		
	case 2:		/* MI3 (Cubic mutual information) */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			3 * log2($O11) - log2($E11) as significance, $freq_table.freq, 
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table 
			$sql_endclause";
		break;
		
	case 3:		/* Z-score  (with Yates' continuity correction as of v3.0.8) */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			sign($O11 - $E11) * if(abs($O11 - $E11) > 0.5, abs($O11 - $E11) - 0.5, abs($O11 - $E11) / 2) / sqrt($E11) as significance, $freq_table.freq,
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
		
	case 4:		/* T-score */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			($O11 - $E11) / sqrt($O11) as significance, $freq_table.freq,
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
/*	case 5:		/* Chi-squared with Yates' correction * /
//turned off in BNCweb
// perhaps exactly because there are two different stats here
		$sql = "select $item, count($item) as observed, $E11 as expected,
			sign($O11 - $E11) * $N * pow(abs($O11 * $O22 - $O12 * $O21) -  ($N / 2), 2) /
			(pow($R1*$R2,1) * pow($C1*$C2,1)) as significance,
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		
		$sql = "select $item, count($item) as observed, $E11 as expected,
			sign($O11 - $E11) * $N *
			((abs($O11 * $O22 - $O12 * $O21) - ($N / 2)) / ($R1 * $R2)) *
			((abs($O11 * $O22 - $O12 * $O21) - ($N / 2)) / ($C1 * $C2))
			as significance,
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
	*/
	
	case 6:		/* Log likelihood */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			sign($O11 - $E11) * 2 * (
				IF($O11 > 0, $O11 * log($O11 / $E11), 0) +
				IF($O12 > 0, $O12 * log($O12 / $E12), 0) +
				IF($O21 > 0, $O21 * log($O21 / $E21), 0) +
				IF($O22 > 0, $O22 * log($O22 / $E22), 0)
			) as significance,
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
	
	case 7:		/* Dice coefficient */
		/* this one uses extra variables, so get these first */
		$sql_query = "SELECT COUNT(DISTINCT refnumber) from $dbname WHERE $range_condition";
		$result = do_mysql_query($sql_query);
	
		list($DICE_NODE_F) = mysql_fetch_row($result);
		$P_COLL_NODE = "(COUNT(DISTINCT refnumber) / $DICE_NODE_F)";
		$P_NODE_COLL = "(COUNT($item) / ($freq_table.freq))";
		
		$sql = "select $item, count($item) as observed, $E11 as expected,
			2 / ((1 / $P_COLL_NODE) + (1 / $P_NODE_COLL)) as significance, 
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table 
			$sql_endclause";
		break;
	
	
	default:
		exiterror_arguments($stat, "Collocation script specified an unrecognised statistic", 
			__FILE__, __LINE__);
	}

	return $sql;
}


/* next two functions support the "create statistic" function */


function colloc_tagclause_from_filter($dbname, $att_for_calc, $primary_annotation, $tag_filter)
{

	/* there may or may not be a primary_annotation filter; $tag_filter is from _GET, so check it */
	if (isset($tag_filter) && $tag_filter != false && $att_for_calc != $primary_annotation)
	{
		/* as of v2.11, tag restrictions are done with REGEXP, not = as the operator 
		 * if there are non-Word characters in the restriction; since tags usually
		 * are alphanumeric, defaulting to = may save procesing time.
		 * As with CQP, anchors are automatically added. */
		if (preg_match('/\W/', $tag_filter))
		{
			$tag_filter = regex_add_anchors($tag_filter);
			$tag_clause_operator = 'REGEXP';
		}
		else
			$tag_clause_operator = '=';
		
		/* tag filter is set and applies to a DIFFERENT attribute than the one being calculated */
		
		return "and $dbname.$primary_annotation $tag_clause_operator '"
			. mysql_real_escape_string($tag_filter)
			. "' ";
	}
	else
		return '';
}





function calculate_total_basis($basis_table)
{
	static $total_basis_cache;
	
	if ( ! isset($total_basis_cache[$basis_table]))	
	{
		$sql_query = 'select sum(freq) from ' . mysql_real_escape_string($basis_table) ;
	
		$result = do_mysql_query($sql_query);
		
		$r = mysql_fetch_row($result);
		
		$total_basis_cache[$basis_table] = $r[0];
	}
	
	return $total_basis_cache[$basis_table];
}





/**
 * Calculates the total number of word tokens in the collocation window
 * described by the global variables $calc_range_begin, $calc_range_end
 * for the globally specified $dbname.
 */
function calculate_words_in_window()
{
	global $dbname;
	global $calc_range_begin;
	global $calc_range_end;
	
	
	$sql_query = "SELECT COUNT(*) from $dbname";
	
	if ($calc_range_begin == $calc_range_end)
		$sql_query .= " where dist = $calc_range_end";
	else
		$sql_query .= " where dist between $calc_range_begin and $calc_range_end";
		
	/* note that mySQL 'BETWEEN' is inclusive of the limit-values */
	
	$r = mysql_fetch_row(do_mysql_query($sql_query));

	return $r[0];
}












// prob don't need this function - can use corpus_annotation_taglist()

function colloc_table_taglist($field, $dbname)
{
	
	/* shouldn't be necessary...  but hey */
	$field = mysql_real_escape_string($field);
	/* this function WILL NOT RUN on word - the results would be huge & unwieldy */
	if ($field == 'word')
		return array();
	
	$sql_query = "select distinct($field) from $dbname limit 1000";
	$result = do_mysql_query($sql_query);
			
	while ( ($r = mysql_fetch_row($result)) !== false )
		$tags[] = $r[0];
	
	sort($tags);
	return $tags;
}












function run_script_for_solo_collocation()
{
	/* note, this function is really just a moved-out-of-the-way chunk of the script */
	/* it assumes all the globals of collocation.inc.php and won't run anywhere else */
	
	global $statistic;
	global $soloform;
	global $tag_filter;
	global $calc_range_begin;
	global $calc_range_end;
	global $att_for_calc;
	global $query_record;
	global $dbname;
	global $primary_annotation;
	
	global $corpus_title;
	global $css_path;
	global $corpus_main_script_is_r2l;
	/* bdo tags ensure that l-to-r goes back to normal after an Arabic (etc) string */
	$bdo_tag1 = ($corpus_main_script_is_r2l ? '<bdo dir="ltr">' : '');
	$bdo_tag2 = ($corpus_main_script_is_r2l ? '</bdo>' : '');
	
	$soloform = mysql_real_escape_string($soloform);
	

	foreach ($statistic as $s => $info)
	{
	
		$sql_query = create_statistic_sql_query($s, $soloform);

		$result = mysql_query($sql_query);
				
		$row = mysql_fetch_assoc($result);
		
		/* adjust number formatting : expected -> 3dp, significance -> 4dp, freq-> thousands*/
		if ( empty($row['significance']) )
			$statistic[$s]['value'] = 'n/a';
		else
			$statistic[$s]['value'] = round($row['significance'], 3);
	
	}
	/* this lot don't need doing on every iteration; they pick up their values from its last loop */
	$observed_to_show = make_thousands($row['observed']);
	$observed_for_calc = $row['observed'];
	$expected_to_show = round($row['expected'], 3);
	$basis_to_show = make_thousands($row['freq']);
	$number_of_files_to_show = make_thousands($row['text_id_count']);
	if ($query_record['subcorpus'] == 'no_subcorpus' && $query_record['restrictions'] == 'no_restriction')
		$basis_point = 'the whole corpus';
	else
		$basis_point = 'the current subcorpus';
	
	header('Content-Type: text/html; charset=utf-8');
	?>
	<html>
	<head>
	<?php
	echo '<title>' . $corpus_title . ' -- CQPweb collocation results</title>';
	echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
	?>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	</head>
	<body>
	<table class="concordtable" width="100%">
		<tr>

	<?php	
	/* check that soloform actually occurs at all */
	if (empty($basis_to_show))
	{
		echo '<td class="concordgeneral"><strong>'
			. "<em>$soloform</em> does not collocate with &ldquo;{$query_record['cqp_query']}&rdquo"
			. " within a window of $calc_range_begin to $calc_range_end."
			. '</strong></td></tr></table>';
		return;
	}

	echo '<th class="concordtable" colspan="2">';
	
	$tag_description = (empty($tag_filter) ? '' : " with tag restriction <em>$tag_filter</em>");
	
	echo "Collocation information for the node &ldquo;{$query_record['cqp_query']}&rdquo; 
		collocating with &ldquo;$soloform&rdquo; $tag_description $bdo_tag1($basis_to_show occurrences in $basis_point)$bdo_tag2 ";

	echo "</th>
		</tr>
		<tr>
			<th class=\"concordtable\" width=\"50%\">Type of statistic</th>
			<th class=\"concordtable\" width=\"50%\">
				Value (for window span $calc_range_begin to $calc_range_end)
			</th>
		</tr>";
	

	
	foreach ($statistic as $s => $info)
	{
		/* skip "rank by frequency" */
		if ($s == 0)
			continue;
			
		echo "
			<tr>
				<td class=\"concordgrey\">{$info['desc']}</td>
				<td class=\"concordgeneral\" align=\"center\">{$info['value']}</td>
			</tr>";
	}
	echo '</table>
		';
	
	echo '<table class="concordtable" width="100%">
		';
	
	echo "
		<tr>
			<th class=\"concordtable\" colspan=\"4\">
				Within the window $calc_range_begin to $calc_range_end, <em>$soloform</em> occurs
				$observed_to_show times in 
				$number_of_files_to_show files 
				(expected frequency: $expected_to_show)
			</th>
		</tr>
		<tr>
			<th class=\"concordtable\" align=\"left\">Distance</th>
			<th class=\"concordtable\">No. of occurrences</th>
			<th class=\"concordtable\">In no. of texts</th>
			<th class=\"concordtable\">Percent</th>
		</tr>
		";
		
	for ($i = $calc_range_begin ; $i <= $calc_range_end ; $i++)
	{
		if ($i == 0)
		{
			?><tr><td colspan="4"></td></tr><?php
			continue;
		}

		$tag_clause = colloc_tagclause_from_filter($dbname, $att_for_calc, $primary_annotation, $tag_filter);

		$sql_query = "SELECT count($att_for_calc), count(distinct(text_id)) 
			FROM $dbname 
			WHERE $att_for_calc = '$soloform' 
			$tag_clause
			AND dist = $i
			";

		$result = do_mysql_query($sql_query);

		$row = mysql_fetch_row($result);
		
		if ($row[0] == 0)
		{
			$link = $i;
			$n_hits = 0;
			$n_texts = 0;
			$percent = 0;
		}
		else
		{
			$link = "<a href=\"concordance.php?qname={$query_record['query_name']}&newPostP=coll"
				. "&newPostP_collocDB=$dbname&newPostP_collocDistFrom=$i&newPostP_collocDistTo=$i"
				. "&newPostP_collocAtt=$att_for_calc&newPostP_collocTarget="
				. urlencode($soloform)
				. "&newPostP_collocTagFilter="
				. urlencode($tag_filter)
				. "&uT=y\" onmouseover=\"return escape('Show solutions collocating with "
				. "<B>$soloform</B> at position <B>$i</B>')\">$i</a>";
			$n_hits = make_thousands($row[0]);
			$n_texts = make_thousands($row[1]);
			$percent = round(($row[0]/$observed_for_calc)*100.0, 1);	
		}
		echo "
			<tr>
				<td class=\"concordgrey\">$link</td>
				<td class=\"concordgeneral\" align=\"center\">$n_hits</td>
				<td class=\"concordgeneral\" align=\"center\">$n_texts</td>
				<td class=\"concordgeneral\" align=\"center\">$percent%</td>
			</tr>";
	}

	echo "</table>";
}








function collocation_write_download(
	$att_for_calc, 
	$calc_stat, 
	$att_desc, 
	$basis_desc, 
	$stat_desc, 
	$description, 
	&$result
	)
{
	global $username;
	$da = get_user_linefeed($username);
	
	$description = preg_replace('/&([lr]dquo|quot);/', '"', $description);
	$description = preg_replace('/<span .*>/', '', $description);

	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=collocation_list.txt");
	echo "$description$da";
	echo "__________________$da$da";
	$sighead = ($calc_stat == 0 ? '' : "\t$stat_desc value");
	echo "No.\t$att_desc\tTotal no. in $basis_desc\tExpected collocate frequency\t"
		. "Observed collocate frequency\tIn no. of texts$sighead";
	echo "$da$da";


	for ($i = 1; ($row = mysql_fetch_assoc($result)) !== false ; $i++ )
	{
		/* adjust number formatting : expected -> 3dp, significance -> 4dp */
		if ( empty($row['significance']) )
			$row['significance'] = 'n/a';
		else
			$row['significance'] = round($row['significance'], 3);
		$row['expected'] = round($row['expected'], 3);
		
		$sig = ($calc_stat == 0 ? '' : "\t{$row['significance']}");
		
		echo "$i\t{$row[$att_for_calc]}\t{$row['freq']}\t{$row['expected']}\t{$row['observed']}";
		echo "\t{$row['text_id_count']}$sig$da";
	}
}



?>
