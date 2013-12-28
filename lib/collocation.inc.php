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





/* collocation.inc.php */

/* this file contains the code for creating colloc databases and then creating a colloc display */


/* note: this script emits nothing on stdout until the last minute, because it can alternatively */
/* write a plaintext file as HTTP attachment */



/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */




/* initialise variables from settings files  */
require('../lib/environment.inc.php');


/* include function library files */
require("../lib/library.inc.php");
require('../lib/html-lib.inc.php');
require("../lib/concordance-lib.inc.php");
require("../lib/concordance-post.inc.php");
require("../lib/colloc-lib.inc.php");
require("../lib/exiterror.inc.php");
require("../lib/user-settings.inc.php");
require("../lib/metadata.inc.php");
require("../lib/freqtable.inc.php");
require("../lib/freqtable-cwb.inc.php");
require("../lib/cache.inc.php");
require("../lib/subcorpus.inc.php");
require("../lib/db.inc.php");
require("../lib/cwb.inc.php");
require("../lib/cqp.inc.php");




// debug
ob_implicit_flush(true);



cqpweb_startup_environment();


/* download all user settings */

$user_settings = get_all_user_settings($username);





/* ------------------------------- */
/* initialise variables from $_GET */
/* and perform initial fiddling    */
/* ------------------------------- */

/* variables from collocation-options */
/* ---------------------------------- */


/* this script is passed qname from the collocation-options */
$qname = safe_qname_from_get();




/* $colloc_atts -- a list of attributes separated by ~ */
/* $colloc_atts_list -- same thing as an array */

/* note that this has been set up so that incoming is the same from colloc-options and from self */
if ( preg_match_all('/collAtt_(\w+)=1/', $_SERVER['QUERY_STRING'], $match, PREG_PATTERN_ORDER) >  0 )
{
	foreach ($match[1] as $m)
		$colloc_atts_list[] = $m;
	sort($colloc_atts_list);
	$colloc_atts = '~~';			/* nonzero string signals that a check is needed, see below */
}
else
	$colloc_atts = '';


/* colloc_range --- the max range number --- if not set / badly specified, this defaults. */
/* note that this has been set up so that incoming is the same from colloc-options and from self */

if ( isset($_GET['maxCollocSpan']) )
	$colloc_range = (int)$_GET['maxCollocSpan'];
else
	$colloc_range = $default_colloc_range;
// TODO need control on this as colloc-options could be hacked!! a default max_max, as it were* /




/* parameters unique to this script */
/* ---------------------------------- */

/* variables that come from the collocation control form and only affect "this" calculation */

/* note that "calc" in a variable name indicates it is to be used for display,
 * as opposed to variables that are to be used for the database creation & db cache-retrieval */


/* the p-attribute to be used for this script's calculation (it is validated below) */
$att_for_calc = 'word';
if (isset($_GET['collocCalcAtt']))
	$att_for_calc = $_GET['collocCalcAtt'];


/* Window span for the calculation : both must be between -colloc_range and colloc_range */

if (isset($_GET['collocCalcBegin']) && abs($_GET['collocCalcBegin']) <= $colloc_range )
	$calc_range_begin = (int)$_GET['collocCalcBegin'];
else if (isset($user_settings->coll_from))
	$calc_range_begin = (int)$user_settings->coll_from;
else
	/* defaults to 2-left of node, or 2-right of max, whichever is wider */
	$calc_range_begin = ($colloc_range > 2 ? -($colloc_range - 2) : $colloc_range);

if (isset($_GET['collocCalcEnd']) && abs($_GET['collocCalcEnd']) <= $colloc_range )
	$calc_range_end = (int)$_GET['collocCalcEnd'];
else if (isset($user_settings->coll_to))
	$calc_range_end = (int)$user_settings->coll_to;
else
	/* defaults to mirror of the begin value */
	$calc_range_end = -($calc_range_begin);

/* add a restriction on range begin and end: neither can be more than colloc_range (abs-wise) */
if ( abs($calc_range_begin) > $colloc_range )
	$calc_range_begin = $colloc_range * ($calc_range_begin / abs($calc_range_begin));
if ( abs($calc_range_end) > $colloc_range )
	$calc_range_end = $colloc_range * ($calc_range_end / abs($calc_range_end));



if ( ( ! ($calc_range_end >= $calc_range_begin)) || $calc_range_end == 0 || $calc_range_begin == 0 )
	exiterror_parameter("Your position range does not make sense; go 'back' and change it!", 
		__FILE__, __LINE__);
	/* !! this is a stop-gap -- there should be a properly printed report page here, like in BW.  (TODO) */
	// perhaps called as a function?? which could be hived off to a separate file colloc-lib.




/* minimum frequencies for the collocate-node combo and the collocate considered alone */
/* only positive integers allowed */
if (isset($_GET['collocMinfreqTogether']) )
	$calc_minfreq_together = abs((int) $_GET['collocMinfreqTogether']);
else if (isset($user_settings->coll_freqtogether))
	$calc_minfreq_together = (int)$user_settings->coll_freqtogether;
else
	$calc_minfreq_together = $default_colloc_minfreq;

if (isset($_GET['collocMinfreqColloc']) )
	$calc_minfreq_collocalone = abs((int) $_GET['collocMinfreqColloc']);
else if (isset($user_settings->coll_freqalone))
	$calc_minfreq_collocalone = (int)$user_settings->coll_freqalone;
else
	$calc_minfreq_collocalone = $default_colloc_minfreq;



/* are we to use the overall freq table for the corpus EVEN IF a subsection is specified ? */
if (isset($_GET['freqtableOverride']) )
	$freq_table_override = (bool)$_GET['freqtableOverride'];
else
	$freq_table_override = false;




/* is this the "collocation solo" function? */
if (!empty($_GET['collocSolo']))
{
	$soloform = $_GET['collocSolo'];
	$solomode = true;
}
else
	$solomode = false;



/* and a purely display-related variable */
if (isset($_GET['beginAt']) )
	$begin_at = abs((int) $_GET['beginAt']);
else
	$begin_at = 0;


/* do we want a nice HTML table or a downloadable table? */
if (isset($_GET['tableDownloadMode']) && $_GET['tableDownloadMode'] == 1)
	$download_mode = true;
else
	$download_mode = false;






/* this is an array full of goodies as laid out in the function that creates it */
$statistic = load_statistic_info();

/* calc_stat is the index of the statistic to be used for the collocation table below,
   to be used as an index into the array created above */
if ( isset($_GET['collocCalcStat']) )
	$calc_stat = (int) $_GET['collocCalcStat'];
else if ( isset($user_settings->coll_statistic) )
	$calc_stat = (int)$user_settings->coll_statistic;
else
	$calc_stat = $default_calc_stat;		/* see defaults.inc.php */

if (! isset($statistic[$calc_stat]) )
	/* non-existent stat, so go to default */
	$calc_stat = $default_calc_stat;



/* tag_filter - the tag that displayed collocs must have */
if ( isset($_GET['collocTagFilter'])  &&  $_GET['collocTagFilter'] != '')
	$tag_filter = $_GET['collocTagFilter'];
else
	$tag_filter = false;
/* note it comes from _GET so before use it must be escaped! various functions do this */

/* do not allow a tag filter if tha collocation attribute IS the primary annotation */
$primary_annotation = get_corpus_metadata('primary_annotation');

if ($primary_annotation == $att_for_calc)
{
	$tag_filter = false;
	$display_tag_filter_control = false;
}
else
	$display_tag_filter_control = true;



/* -------------------------- */
/* end of variable initiation */
/* -------------------------- */







$att_desc = get_corpus_annotations();
$att_desc['word'] = 'Word';


/* validate list of p-attributes to include && get their names */

if ($colloc_atts !== '')
{
	/* delete anything from the list of atts to put in the database that is not a real annotation */
	foreach ($colloc_atts_list as $k => $v)
		if( ! array_key_exists($v, $att_desc) )
			unset($colloc_atts_list[$k]);
	/* and compile the string-version of the list to what it needs to be to go in the db */
	$colloc_atts = implode('~', $colloc_atts_list);
}

/* validate p-attribute to be used as basis of collocation */

if ( ( ! isset($colloc_atts_list) ) || ( ! in_array($att_for_calc, $colloc_atts_list) ) )
	$att_for_calc = 'word';









$startTime = microtime(true);


/* does a db for the collocation exist? */

/* first get all the info about the query in one handy package */

$query_record = check_cache_qname($qname);
if ($query_record === false)
	exiterror_general("The specified query $qname was not found in cache!", __FILE__, __LINE__);


/* now, search the db list for a db whose parameters match those of the query  */
/* named as qname; if it doesn't exist, we need to create one */
$db_record = check_dblist_parameters('colloc', $query_record['cqp_query'],
				$query_record['restrictions'], $query_record['subcorpus'],
				$query_record['postprocess'],
				$colloc_atts, $colloc_range);



if ($db_record === false)
{
	$is_new_db = true;

	$dbname = create_db('colloc', $qname, $query_record['cqp_query'], $query_record['restrictions'],
				$query_record['subcorpus'], $query_record['postprocess']);
	$db_record = check_dblist_dbname($dbname);
}
else
{
	$dbname = $db_record['dbname'];
	touch_db($dbname);
	$is_new_db = false;
}
/* this dbname & its db_record can be globalled by print functions in the script */

/* for now just find out how many distinct items it has in it */
$sql_query = "select distinct($att_for_calc) from $dbname";
$result = do_mysql_query($sql_query);
$db_types_total = mysql_num_rows($result);
unset($result);




/* OK we have the db for calculating collocations, now we need the basis of comparison */
/* if there is a subcorpus or restriction, then a table for that needs to be found or created */
if ($query_record['subcorpus'] != 'no_subcorpus')
{
	/* has this subcorpus had its freqtables created? */
	if ( ($freqtable_record = check_freqtable_subcorpus($query_record['subcorpus'])) != false )
		;
	else
	{
		/* if not, and if the subseciton is not too big, create it */
		list($words_in_subsection, $junk) = amount_of_text_searched($query_record['subcorpus'], $query_record['restrictions']);
		$freq_table_override = ( $_________words_in_subsection < $collocation_disallow_cutoff ? $freq_table_override : false );
		if ( ! $freq_table_override )
			$freqtable_record = subsection_make_freqtables($query_record['subcorpus']);
	}
}
else if ($query_record['restrictions'] != 'no_restriction')
{
	/* search for a freqtable matching that restriction */
	if ( ($freqtable_record = check_freqtable_restriction($query_record['restrictions'])) != false )
		;
	else
	{
		/* if there isn't one, and if the subsection is not too big, create it */
		list($words_in_subsection, $junk) = amount_of_text_searched($query_record['subcorpus'], $query_record['restrictions']);
		$freq_table_override = ( $words_in_subsection < $collocation_disallow_cutoff ? $freq_table_override : false );
		if ( ! $freq_table_override )
			$freqtable_record = subsection_make_freqtables('no_subcorpus', $query_record['restrictions']);
	}
}
/* nb: freq_table_override is tested in the ELSE condition above. This means that if the override is
 * set to TRUE, but by some chance the freqtable necessary does exist, the override does not kick in.
 * 
 * Note also, if the override is activated, $freqtable_record WON'T be set.
 */


if ( isset($freqtable_record) && $freqtable_record !== false)  /* ie (a) IF the if above was true.... */
{
 	/* we are using a subsection (sc or retriction): touch it and assign the freqtable name */
	$freq_table_to_use = "{$freqtable_record['freqtable_name']}_{$att_for_calc}";
	$desc_of_basis = 'this subcorpus';
	touch_freqtable($freq_table_to_use);
}
else
{
	/* we are not using a subsection, so default to the table for this corpus */
	/* OR the override for a too-much-time-to-calculate subcorpus was engaged */
	$freq_table_to_use = "freq_corpus_{$corpus_sql_name}_{$att_for_calc}";
	/* this variable is not used here, but IS used in create_statistic_sql_query() */
	$desc_of_basis = 'whole corpus';
}




/* ------------------------------------------------------------------------------------- */
/* send the script off to the separate function if it is the collocation-solo capability */
/* ------------------------------------------------------------------------------------- */
if ($solomode === true)
{
	run_script_for_solo_collocation();
	echo print_html_footer();
	cqpweb_shutdown_environment();
	exit(0);
}






/* run the BIIIIIIIIIIIIG mysql query */

$sql_query = create_statistic_sql_query($calc_stat);

$result = do_mysql_query($sql_query);




/* "time" == time to create the db (if nec), create the freqtable (if nec), + run the BIIIG query */
$timeTaken = round(microtime(true) - $startTime, 3);

$description = "There are " . number_format((float)$db_types_total) . " different " 
	. strtolower($att_desc[$att_for_calc]) 
	. "s in your collocation database for &ldquo;{$query_record['cqp_query']}&rdquo;. (" 
	. create_solution_heading($query_record, false) . ') ' 
	. format_time_string($timeTaken, $is_new_db);




if ($download_mode)
{
	collocation_write_download($att_for_calc, $calc_stat, $att_desc[$att_for_calc], $desc_of_basis, 
		$statistic[$calc_stat]['desc'], $description, $result);
}
else
{

	/* ------------------------------------------------ */
	/* create the HTML for the control panel at the top */
	/* ------------------------------------------------ */
	
	/* first step: generate the SELECT dropdowns for each collocation calculation option */
	
	/* create the P-ATTRIBUTE TO CALCULATE SELECTION BOX */	
	$select_for_colloc = '<select name="collocCalcAtt">
		<option value="word" ' . ('word' == $att_for_calc ? 'selected="selected"' : '') 
		. '>Word form</option>';
	
	if (! empty($colloc_atts_list))
	{
		foreach($colloc_atts_list as $a)
			$select_for_colloc .= "\n\t<option value=\"$a\"" 
				. ($a == $att_for_calc ? ' selected="selected"' : '') 
				. ">{$att_desc[$a]}</option>";
	}
	
	$select_for_colloc .= '</select>';
	
	/* create the CALCULATION STATISTIC SELECTION BOX */
	$select_for_stats = '<select name="collocCalcStat">';
	$select_for_stats .= print_statistic_form_options($calc_stat);
	$select_for_stats .= '</select>';
	
	/* create the RANGE TO CALCULATE SELECTION BOXES */
	list ($tempfrom, $tempto) = print_fromto_form_options($colloc_range, $calc_range_begin, $calc_range_end);
	$select_for_windowfrom = '<select name="collocCalcBegin">' . $tempfrom . '</select>';
	$select_for_windowto   = '<select name="collocCalcEnd">'   . $tempto   . '</select>';
	
	/* create the MINIMUM FREQUENCY OF COLLOCATES SELECTION BOX */
	$select_for_freqtogether = 
		'<select name="collocMinfreqTogether">'
		. print_freqtogether_form_options($calc_minfreq_together) 
		.'</select>';
	$select_for_freqalone = 
		'<select name="collocMinfreqColloc">' 
		. print_freqalone_form_options($calc_minfreq_collocalone)
		. '</select>';
	
	/* create the TAG FILTER SELECTION BOX */
	if (isset($colloc_atts_list) && in_array($primary_annotation, $colloc_atts_list))
	{
		/* was formerly name="collocTagFilterSelect" */
		$select_for_tag = '<select onChange="setCollocTagFilter(this);">
				<option value="-??..__any__..??-"' . ($tag_filter === false ? ' selected="selected"' : '')
				. '>(none)</option>';

		foreach(colloc_table_taglist($primary_annotation, $dbname) as $tag)
			$select_for_tag .= '
				<option' . ($tag == $tag_filter ? ' selected="selected"' : '')
				. ">$tag</option>";
		$select_for_tag .= '</select>';
	}
	else
	{
		$select_for_tag = '<select><option selected="selected">no restriction</option></select>';
	}



	/* ok, all select-option dropdowns have been dynamically generated : now, write it! */
	
	/* before anything else */
	header('Content-Type: text/html; charset=utf-8');
	?>
	<html>
	<head>
	<?php
	echo '<title>' . $corpus_title . ' -- CQPweb collocation results</title>';
	echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
	?>
	<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	</head>
	<body>
	
	<table class="concordtable" width="100%">
		<form action="redirect.php" method="get">
			<tr>
				<th class="concordtable" colspan="4">Collocation controls</th>
			</tr>
	
			<tr>
				<td class="concordgeneral">Collocation based on:</td>
				<td class="concordgeneral"><?php echo $select_for_colloc; ?></td>
				<td class="concordgeneral">Statistic:</td>
				<td class="concordgeneral"><?php echo $select_for_stats; ?></td>
			</tr>
	
			<tr>
				<td class="concordgeneral">Collocation window <em>from</em>:</td>
				<td class="concordgeneral"><?php echo $select_for_windowfrom; ?></td>
				<td class="concordgeneral">Collocation window <em>to</em>:</td>
				<td class="concordgeneral"><?php echo $select_for_windowto; ?></td>
			</tr>
	
			<tr>
				<td class="concordgeneral">Freq(node, collocate) at least: </td>
				<td class="concordgeneral"><?php echo $select_for_freqtogether; ?></td>
				<td class="concordgeneral">Freq(collocate) at least: </td>
				<td class="concordgeneral"><?php echo $select_for_freqalone; ?></td>
			</tr>
		
			<tr>
				<td class="concordgrey">Filter results by:</td>
				<td class="concordgrey">
					specific collocate: 
					<input type="text" name="collocSolo" size="15" maxlength="40"/>
				</td>
				<td class="concordgrey">
					<?php
					if ($display_tag_filter_control)
					{
						?>
						<script type="text/javascript">
						<!--
						// this function only works within this <td>
						function setCollocTagFilter(fromThisSelect)
						{
							var newValue = fromThisSelect.options[fromThisSelect.selectedIndex].value;
							// work around stupid, stupuid Internet Explorer bug
							if (newValue == "")
								newValue = fromThisSelect.options[fromThisSelect.selectedIndex].innerHTML;
							
							if (newValue == "-??..__any__..??-")
								newValue = "";
							var target = document.getElementById('collocTagFilter');
							target.value = newValue;
						}
						//-->
						</script>
						and/or tag: 
						<input name="collocTagFilter" id="collocTagFilter" 
							value="<?php echo $tag_filter;?>"
							type="text" size="5"
						/>
						<?php 
						echo $select_for_tag;
					}
					else
						echo 'tag restriction: n/a';
					?>
				</td>
				<td class="concordgrey">
					<select name="redirect">
						<option value="rerunCollocation">Submit changed parameters</option>
						<option value="collocationDownload">Download collocation results</option>
						<option value="newQuery">New query</option>
						<option value="backFromCollocation">Back to query result</option
						<!--
							important note: because of intervening "create database" screen,
							the return-target is always the first page of the query -
							unlike the Distribution program, which remembers where we were.
						-->
					</select>
					<input type="submit" value=" Go! " />
				</td>
				<!-- hidden inputs here -->
				<input type="hidden" name="maxCollocSpan" value="<?php echo $colloc_range; ?>"/>
				<input type="hidden" name="qname" value="<?php echo $qname; ?>"/>
				<?php
				if (! empty($colloc_atts_list))
				{
					foreach ($colloc_atts_list as $a)
						echo "\t\t<input type=\"hidden\" name=\"collAtt_$a\" value=\"1\"/>\n";
				}
				?>
				<input type="hidden" name="uT" value="y"/>
			</tr>
		</form>
	</table>

	<!-- 
		end of collocation control display, start of collocation results display 
	-->

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="<?php echo ($calc_stat != 0 ? 7 : 6); ?>">
				<?php echo $description; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey"><center>No.</center></td>
			<td class="concordgrey"><center><?php echo $att_desc[$att_for_calc];?></center></td>
			<td class="concordgrey"><center>Total no. in <?php echo $desc_of_basis; ?></center></td>
			<td class="concordgrey"><center>Expected collocate frequency</center></td>
			<td class="concordgrey"><center>Observed collocate frequency</center></td>
			<td class="concordgrey"><center>In no. of texts</center></td>
		
			<?php
			if ($calc_stat != 0)
				echo "<td class=\"concordgrey\"><center>{$statistic[$calc_stat]['desc']} value</center></td>\n";
			?>
		</tr>
		
	<?php
	
	$i = $begin_at;
	while (( $row = mysql_fetch_assoc($result)) !== false)
	{
		$i++;
	
		/* adjust number formatting : expected -> 3dp, significance -> 4dp, freq-> thousands*/
		if ( empty($row['significance']) )
			$row['significance'] = 'n/a';
		else
			$row['significance'] = round($row['significance'], 3);
		$row['observed'] = number_format((float)$row['observed']);
		$row['expected'] = round($row['expected'], 3);
		$row['freq'] = number_format((float)$row['freq']);
		
		$att_for_calc_tt_show = strtr($row[$att_for_calc], array("'"=>"\'", '"'=>'&quot;'));
		
		$solo = "<a href=\"collocation.php?collocSolo=" . $row[$att_for_calc] . '&'
			. url_printget(array(array('collocSolo', '')))
			. "\" onmouseover=\"return escape('Show detailed info on <B>"
			. $att_for_calc_tt_show . "</B>')\">{$row[$att_for_calc]}</a>";
		
		$link = "<a href=\"concordance.php?qname=$qname&newPostP=coll&newPostP_collocDB=$dbname"
			. "&newPostP_collocDistFrom=$calc_range_begin&newPostP_collocDistTo=$calc_range_end"
			. "&newPostP_collocAtt=$att_for_calc&newPostP_collocTarget="
			. urlencode($row[$att_for_calc])
			. "&newPostP_collocTagFilter=" . urlencode($tag_filter)
			. "&uT=y\" onmouseover=\"return escape('Show solutions collocating with <B>"
			. $att_for_calc_tt_show . "</B>')\">{$row['observed']}</a>";
		
		$sig = ($calc_stat == 0 ? '' : "<td class=\"concordgeneral\"><center>{$row['significance']}</center></td>");
		$str = "
			<tr>
				<td class=\"concordgeneral\"><center>$i</center></td>
				<td class=\"concordgeneral\"><center>$solo</center></td>
				<td class=\"concordgeneral\"><center>{$row['freq']}</center></td>
				<td class=\"concordgeneral\"><center>{$row['expected']}</center></td>
				<td class=\"concordgeneral\"><center>$link</center></td>
				<td class=\"concordgeneral\"><center>{$row['text_id_count']}</center></td>
				$sig
			</tr>
			";
		echo $str;
	}
	
	echo '</table>';
	
	
	
	
	
	/* create navlinks here */
	$navlinks = '<table class="concordtable" width="100%"><tr><td class="concordgrey" align="left';
	
	if ($begin_at > 0)
	{
		$new_begin_at = $begin_at - $default_collocations_per_page;
		if ($new_begin_at < 0)
			$new_begin_at = 0;
		$navlinks .=  '"><a href="collocation.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= "\">&lt;&lt; [Previous $default_collocations_per_page collocates]";
	if ($begin_at > 0)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="concordgrey" align="right';
	
	if ($i == ($begin_at + $default_collocations_per_page) )
		$navlinks .=  '"><a href="collocation.php?' . url_printget(array(array('beginAt', "$i")));
	$navlinks .= "\">[Next $default_collocations_per_page collocates] &gt;&gt;";
	if ($i == ($begin_at + $default_collocations_per_page) )
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;
	
	echo print_html_footer();


} /* endof "else" for "if $download_mode" */



cqpweb_shutdown_environment();


/* ------------- */
/* end of script */
/* ------------- */
?>
