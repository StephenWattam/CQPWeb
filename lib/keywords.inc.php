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


/* note: this script emits nothing on stdout until the last minute, because it can alternatively */
/* write a plaintext file as HTTP attachment */




/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */

// TODO: potential bug -- the left join for "comp" function may be quite slow -- 
// it is worth doing a time-test on the db


/* initialise variables from settings files  */

require_once("settings.inc.php");
require_once("../lib/defaults.inc.php");


/* include function library files */
require_once("../lib/library.inc.php");
require_once("../lib/exiterror.inc.php");
require_once("../lib/metadata.inc.php");
require_once("../lib/user-settings.inc.php");
require_once("../lib/freqtable.inc.php");
require_once("../lib/cwb.inc.php");         // needed?
require_once("../lib/cqp.inc.php");


// debug
ob_implicit_flush(true);



if (!url_string_is_valid())
	exiterror_bad_url();









/* connect to mySQL */
connect_global_mysql();










/* ------------------------------- */
/* initialise variables from $_GET */
/* and perform initial fiddling    */
/* ------------------------------- */




	
/* overall mode of script */

if (isset($_GET['kwMethod']) && $_GET['kwMethod'] == 'Compare lists!' )
	$mode = 'comp';
else
	$mode = 'key';




/* do we want a nice HTML table or a downloadable table? */
if ($_GET['tableDownloadMode'] == 1)
	$download_mode = true;
else
	$download_mode = false;

	

/* do we want all keywords, only positive, or only negative? */
switch($_GET['kwWhatToShow'])
{
case 'onlyNeg':
	$show_only = 'neg';
	break;
case 'onlyPos':
	$show_only = 'pos';
	break;
default:
	$show_only = '';
	break;
}



/* attribute to compare */

if (!isset($_GET['kwCompareAtt']) )
	$att_for_comp = 'word';
else
	$att_for_comp = $_GET['kwCompareAtt'];
if (preg_match('/\W/', $_GET['kwCompareAtt']) > 0)
	exiterror_fullpage("An invalid word-annotation ($att) was specified!", __FILE__, __LINE__);

	

/* minimum fequencies */

if (!isset($_GET['kwMinFreq1']) )
	$minfreq[1] = 5;
else
	$minfreq[1] = (int)$_GET['kwMinFreq1'];	

if (!isset($_GET['kwMinFreq2']) )
	$minfreq[2] = 5;
else
	$minfreq[2] = (int)$_GET['kwMinFreq2'];	


/* statistic to use */

if (!isset($_GET['kwStatistic']) )
	$statistic = 'LL';
else 
	switch ($_GET['kwStatistic'])
	{
		case 'LL': 	$statistic = 'LL';	$statistic_display = 'LL';			break;
//TODO: add the other statistics
//		case 'X2': 	$statistic = 'X2';	$statistic_display = 'Chi-square';	break;
//		case 'MI': 	$statistic = 'MI';	$statistic_display = 'MI';			break;
		default:
			exiterror_fullpage("An invalid statistic ({$_GET['kwStatistic']}) was specified!", __FILE__, __LINE__);
	}

/* override statistic if we are not in keyword mode */
if ($mode == 'comp')
	$statistic = 'comp';


/* the significance threshold */

if ( !isset($_GET['kwThreshold']) )
	$_GET['kwThreshold'] = "5";

switch ($_GET['kwThreshold'])
{
case '5':		$threshold = '3.84';	break;
case '1':		$threshold = '6.63';	break;
case '0.1':		$threshold = '10.83';	break;
case '0.01':	$threshold = '15.13';	break;
case '0.001':	$threshold = '19.52';	break;
case '0.0001':	$threshold = '23.99';	break;
case '0.00001':	$threshold = '29.27';	break;
default:		$threshold = '0';		break;
}




/* per page and page numbers */

if (isset($_GET['pageNo']))
	$_GET['pageNo'] = $page_no = prepare_page_no($_GET['pageNo']);
else
	$page_no = 1;

if (isset($_GET['pp']))
	$per_page = prepare_per_page($_GET['pp']);   /* filters out any invalid options */
else
	$per_page = $default_per_page;
/* note use of same variables as used in a concordance */


$limit_string = ($download_mode ? '' : ("LIMIT ". ($page_no-1) * $per_page . ', ' . $per_page));



/* -------------------------------------------------------------------------- */
/* end of variable initiation (except for the two tables - part of next block */
/* -------------------------------------------------------------------------- */


/* the two tables to compare */
/* see note above in variable extraction */

if (isset($_GET['kwTable1']) )
	list($table_base[1], $table_desc[1], $table_foreign[1]) = parse_keyword_table_parameter($_GET['kwTable1']);
else
	exiterror_fullpage("No frequency table was specified (table 1)!", __FILE__, __LINE__);
	
if (isset($_GET['kwTable2']) )
	list($table_base[2], $table_desc[2], $table_foreign[2]) = parse_keyword_table_parameter($_GET['kwTable2']);
else
	exiterror_fullpage("No frequency table was specified (table 2)!", __FILE__, __LINE__);








if ($table_base[1] === false || $table_base[2] === false)
	exiterror_fullpage("CQPweb could not interpret the tables you specified!". __FILE__, __LINE__);


/* check we've got two DIFFERENT tables */
if ($table_base[1] == $table_base[2])
	exiterror_fullpage("The two frequency lists you have chosen are identical!", __FILE__, __LINE__);

/* check that the first table isn't foreign */
if ($table_foreign[1] === true)
	exiterror_fullpage("A foreign frequency list was specified for frequency list (1)!", __FILE__, __LINE__);

/* get a string to put into queries with the subcorpus */
foreach(array(1,2) as $i)
{
	$restrict_string[$i] = '';
	$cmp = 'freq_corpus_';
	if (substr($table_base[$i], 9, strlen($cmp)) == $cmp)
		/* this is the home corpus (or a foreign corpus), so no restrict needed */
		/* if foreign, will be set to false later */
		;
	else
	{
		/* this is a sub corpus -- home or foreign, so no restrict needed */
		/* if foreign, will be set to false later */
		if (preg_match("/freq_sc_{$corpus_sql_name}_(\w+)/", $table_base[$i], $m) > 0)
			$restrict_string[$i] = '&del=begin&t=subcorpus~'. $m[1] . '&del=end';
	}
}
if ($table_foreign[2] === true)
	$restrict_string[2] = false;













/* check the attribute setting is valid */

$att_desc = get_corpus_annotations();	
$att_desc['word'] = 'Word';
// TTD: add tool tips using onmouseOver
/* if the script has been fed an attribute that doesn't exist for this corpus, failsafe to 'word' */
if (! array_key_exists($att_for_comp, $att_desc) )
	$att_for_comp = 'word';



/* create the full table names */

$table_name[1] = "{$table_base[1]}_$att_for_comp";
$table_name[2] = "{$table_base[2]}_$att_for_comp";


/* get the totals for each of the 2 tables */

foreach (array(1, 2) as $i)
{
	$sql_query = "select sum(freq) from {$table_name[$i]}";
	$result = do_mysql_query($sql_query);
	if (mysql_num_rows($result) < 1)
		exiterror_fullpage("sum(freq) not found in from {$table_name[$i]}, 
			0 rows returned from mySQL.", __FILE__, __LINE__);		
	list($table_total[$i]) = mysql_fetch_row($result);
	unset ($result);
}


/* in compare  mode, we also need ... */
$empty = 'f2';
if ($_GET['kwEmpty'] === 'f1')
	$empty = 'f1';
$title_bar_index = (int)substr($empty, 1, 1);
$title_bar_index_other = ($title_bar_index == 1 ? 2 : 1);


/* create the clause for if we want only positive or only negative keywords */
switch($show_only)
{
case 'pos':
	//$show_only_clause = "and ({$table_name[1]}.freq / {$table_total[1]}) > ({$table_name[2]}.freq / {$table_total[2]})";
	$show_only_clause = "and freq1 > E1";
	break;
case 'neg':
	//$show_only_clause = "and ({$table_name[2]}.freq / {$table_total[2]}) > ({$table_name[1]}.freq / {$table_total[1]})";
	$show_only_clause = "and freq1 < E1";
	break;
default:
	$show_only_clause = '';
	break;
}



/* assemble the main SQL query */

switch ($statistic)
{
	case 'LL':
		$sql_query = "select 
			{$table_name[1]}.item,
			{$table_name[1]}.freq as freq1, 
			{$table_name[2]}.freq as freq2, 
			({$table_total[1]} * ({$table_name[1]}.freq + {$table_name[2]}.freq) / ({$table_total[1]} + {$table_total[2]})) as E1, 
			({$table_total[2]} * ({$table_name[1]}.freq + {$table_name[2]}.freq) / ({$table_total[1]} + {$table_total[2]})) as E2, 
			2 * (({$table_name[1]}.freq * log({$table_name[1]}.freq / ({$table_total[1]} * ({$table_name[1]}.freq + {$table_name[2]}.freq) 
						/ ({$table_total[1]} + {$table_total[2]})))) 
					+ 
				({$table_name[2]}.freq * log({$table_name[2]}.freq / ({$table_total[2]} * ({$table_name[1]}.freq + {$table_name[2]}.freq) 
						/ ({$table_total[1]} + {$table_total[2]})))))
				as theValue 
			from {$table_name[1]}, {$table_name[2]}
			
			where {$table_name[1]}.item = {$table_name[2]}.item 
			and {$table_name[1]}.freq >= {$minfreq[1]}
			and {$table_name[2]}.freq >= {$minfreq[2]}
			
			having theValue >= $threshold
			$show_only_clause
			order by theValue desc 
			$limit_string
			";
		break;
		
	case 'comp':
		/* we are in compare mode, not keyword mode */
		if ($empty == "f2")
		{
			$a = 2;		$b = 1;
		}
		else
		{
			$b = 2;		$a = 1;
		}

		$sql_query = "SELECT {$table_name[$a]}.item, {$table_name[$a]}.freq as freq$a, 0 as freq$b 
			FROM  {$table_name[$a]} left join {$table_name[$b]} on {$table_name[$a]}.item = {$table_name[$b]}.item 
			where {$table_name[$b]}.freq is NULL 
			order by {$table_name[$a]}.freq desc 
			$limit_string";
		break;
	
	default:
		exiterror_fullpage("Undefined statistic!", __FILE__, __LINE__);
}


$result = do_mysql_query($sql_query);

$n = mysql_num_rows($result);


$next_page_exists = ( $n == $per_page ? true : false );


/* calculate the description line */
switch ($mode)
{
case 'key':
	$description = 'Key' . ($att_for_comp == 'word' ? '' : ' ') 
		. strtolower($att_desc[$att_for_comp]) . ' list for '
		. $table_desc[1] . ' compared to '
		. $table_desc[2];
	break;
case 'comp':
	$description = 'Items which occur in  ' . $table_desc[$title_bar_index]
						. ' but not in ' . $table_desc[$title_bar_index_other];
	break;
default:
	/* it shouldn't be able to get to here, but if it does, */
	exiterror_general('', __FILE__, __LINE__);
	break;
}
switch ($show_only)
{
case 'pos':
	$description .= ': showing positively key items only';
	break;
case 'neg':
	$description .= ': showing negatively key items only';
	break;
}

/* print the result */

if ($download_mode)
{
	keywords_write_download($att_desc[$att_for_comp], $description, $result);
}
else
{
	/* before anything else */
	header('Content-Type: text/html; charset=utf-8');
	?>
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<?php
	echo '<title>' . $corpus_title . ' -- CQPweb keywords analysis</title>';
	echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
	?>
	<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
	
	</head>
	<body>
	
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">
				<?php echo $description; ?>
			</th>
		</tr>
		<?php echo print_keywords_control_row($page_no, $next_page_exists, $show_only); ?>
	</table>
	
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">No.</th>
			<th class="concordtable" width="40%"><?php echo $att_desc[$att_for_comp]; ?></th>
			<th class="concordtable">Freq in <?php echo $table_desc[1]; ?></th>
			<th class="concordtable">Freq in <?php echo $table_desc[2]; ?></th>
			<?php 
			if ($mode == 'key') 
			{
				echo '<th class="concordtable">+/-</th>';
				echo "<th class=\"concordtable\">$statistic_display value</th>";
			}
			?>
		</tr>
	
	<?php
	
	/* this is the number SHOWN on the first line */
	/* the value of $i is (relatively speaking) 1 less than this */
	$begin_at = (($page_no - 1) * $per_page) + 1; 

	for ( $i = 0 ; $i < $n ; $i++ )
	{
		$r = mysql_fetch_object($result);
		$line = print_keyword_line($r, ($begin_at + $i), $att_for_comp, $restrict_string);
		echo "<tr>$line</tr>";
	}

	echo '</table>';
	
	/* create page end HTML */
	print_footer();
}

/* disconnect mysql */
disconnect_global_mysql();



/* ------------- */
/* end of script */
/* ------------- */








function print_keyword_line($data, $line_number, $att_for_comp, $restricts)
{
	/* the format of "data" is as follows
	object(stdClass)(6) {
	  ["item"]=>
	  ["freq1"]=>
	  ["freq2"]=>
	  ["E1"]=>
	  ["E2"]=>
	  ["theValue"]=>
	unless mode is comparison in which case we only have item and freq1, freq2.
	*/

	if (isset($data->theValue)) /* whihc is to say, if mode is keyword rather than comparison */
	{
		/* td classes for this line */
		if ($data->freq1 > $data->E1)
		{
			/* positively key */
			$plusminus = '+';
			$leftstyle = 'concordgeneral';
			$rightstyle = 'concordgrey';
		}
		else
		{
			/* negatively key */
			$plusminus = '-';
			$leftstyle = 'concordgrey';
			$rightstyle = 'concordgeneral';
		}
// need better styles than this! concordgeneral and concordmpheasis??
	}
	else
		$leftstyle = $rightstyle = 'concordgrey';
	
	/* links do not appear if restricts[1|2] == false */
	$target = CQP::escape_metacharacters($data->item);
	
	/* we need to either use or not use %c depending on corpus-level settings */
	global $corpus_cqp_query_default_flags;
	
	$link[1] = ( $restricts[1] !== false && $data->freq1 > 0
		? 'href="concordance.php?theData=' 
			. urlencode("[$att_for_comp=\"{$target}\"$corpus_cqp_query_default_flags]")
			. $restricts[1] 
			. '&qmode=cqp&uT=y"'
		: '' ) ;
	$link[2] = ( $restricts[2] !== false && $data->freq2 > 0
		? 'href="concordance.php?theData=' 
			. urlencode("[$att_for_comp=\"{$target}\"$corpus_cqp_query_default_flags]")
			. $restricts[2] 
			. '&qmode=cqp&uT=y"'
		: '' ) ;
	
	
	$string = '';	
	
	$string .= "<td class=\"concordgrey\" align=\"right\"><b>$line_number</b></td>";
	$string .= "<td class=\"$leftstyle\"><b>{$data->item}</b></td>";
	$string .= "<td class=\"$leftstyle\"  align=\"center\"><a $link[1]>"
		. make_thousands($data->freq1) . '</a></td>';
	$string .= "<td class=\"$rightstyle\" align=\"center\"><a $link[2]>" 
		. make_thousands($data->freq2) . '</a></td>';
	if (isset($plusminus))
	{
		$string .= "<td class=\"concordgrey\" align=\"center\">$plusminus</td>";
		$string .= '<td class="concordgrey" align="center">' . round($data->theValue, 2) . '</td>';
	}
	
	return $string;
}



function print_keyword_line_plaintext($data, $line_number, $da)
{
	/* simpler version of above for plaintext mode	*/

	if (isset($data->theValue)) /* whihc is to say, if mode is keyword rather than comparison */
		$plusminus = ($data->freq1 > $data->E1 ? '+' : '-');
	
	$string = "$line_number\t{$data->item}\t{$data->freq1}";
	if (isset($plusminus))
		$string .= "\t{$data->freq2}\t$plusminus\t" . round($data->theValue, 2);
	$string .= $da;
	
	return $string;
}


function keywords_write_download($att_desc, $description, &$result)
{
	global $username;
	$da = get_user_linefeed($username);
	$description = preg_replace('/&[lr]dquo;/', '"', $description);

	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=key_item_list.txt");
	echo "$description$da";
	echo "__________________$da$da";
	echo "Number\t$att_desc\tFreq";
	if (substr($description, 0, 3) == 'Key')
		echo " 1\tFreq 2\t+/-\tStat.";
	echo "$da$da";


	for ($i = 1; ($r = mysql_fetch_object($result)) !== false ; $i++ )
		echo print_keyword_line_plaintext($r, $i, $da);
}







function parse_keyword_table_parameter($par)
{
	global $corpus_sql_name;
	global $corpus_title;	
	
	// necessary?
	// no - regexes preclude the passing of bad labels
	// $par = mysql_real_escape_string($par)

	/* set the values that kick in if nothing else is found */
	$base = false;
	$desc = '';
	$foreign = false;


	/* --whole of rest of function is one big if-else ladder-- */
	
	
	if ($par == '__entire_corpus')
	{
		$base = "freq_corpus_$corpus_sql_name";
		$desc = "whole &ldquo;$corpus_title&rdquo;";
	}
	
	/* it's a subcorpus in this corpus */
	else if (substr($par, 0, 3) == 'sc~')
	{
		if (preg_match('/sc~(\w+)/', $par, $m) > 0)
		{
			if (($base = get_subcorpus_freqtable($m[1])) == false)
				exiterror_general("The subcorpus you selected has no frequency list! "
					. "Please compile the frequency list and try again.\n");
			$desc = "subcorpus &ldquo;{$m[1]}&rdquo;";
		}
	}
	
	/* public corpus freqlist */
	else if (substr($par, 0, 3) == 'pc~')
	{
		$foreign = true;
		if (preg_match('/pc~(\w+)/', $par, $m) > 0)
		{
			$base = "freq_corpus_{$m[1]}";
			
			$sql_query = "select public_freqlist_desc from corpus_metadata_fixed
				where corpus = '{$m[1]}'";
			$result = do_mysql_query($sql_query);

			$r = mysql_fetch_row($result);
			$desc = "corpus &ldquo;$r[0]&ldquo;";
		}
	}
	
	/* public subcorpus freqlist : whole thing after ~ is the return val */
	else if (substr($par, 0, 3) == 'ps~')
	{
		$foreign = true;
		if (preg_match('/ps~(\w+)/', $par, $m) > 0)
		{
			$base = $m[1];
			
			$sql_query = "select corpus, subcorpus from saved_freqtables 
				where freqtable_name = '{$m[1]}'";
			$result = do_mysql_query($sql_query);

			$r = mysql_fetch_assoc($result);
			$desc = "subcorpus &ldquo;{$r['subcorpus']}&rdquo; from corpus &ldquo;{$r['corpus']}&rdquo;";
		}	
	}
	
	/* implied "else": nothing has matched  -- default values at top of function get returned. */


	return array($base, $desc, $foreign);	
}




function print_keywords_control_row($page_no, $next_page_exists, $show_only)
{
	$marker = array( 'first' => '|&lt;', 'prev' => '&lt;&lt;', 'next' => "&gt;&gt;" );
	
	/* work out page numbers */
	$nav_page_no['first'] = ($page_no == 1 ? 0 : 1);
	$nav_page_no['prev']  = $page_no - 1;
	$nav_page_no['next']  = ( (! $next_page_exists) ? 0 : $page_no + 1);
	/* all page numbers that should be dead links are now set to zero  */
	
	$string .= '<tr>';


	foreach ($marker as $key => $m)
	{
		$string .= '<td align="center" class="concordgrey"><b><a class="page_nav_links" ';
		$n = $nav_page_no[$key];
		if ( $n != 0 )
			/* this should be an active link */
			$string .= 'href="keywords.php?'
				. url_printget(array(
					array('pageNo', "$n")
					) )
				. '"';
		$string .= ">$m</b></a></td>";
	}
	
		

	$string .= 
		'<form action="redirect.php" method="get">
			<td class="concordgrey">
				<select name="redirect">
					<option value="newKeywords" selected="selected">New Keyword calculation</option>
					<option value="downloadKeywords">Download whole list</option>
					<option value="newQuery">New Query</option>
					'
		. (($show_only == 'pos' || $show_only == 'neg') ? '<option value="showAll">Show all keywords' : '')
		. ($show_only != 'pos' ? '<option value="showPos">Show only positive keywords</option>' : '' )
		. ($show_only != 'neg' ? '<option value="showNeg">Show only negative keywords</option>' : '' ) . '
				</select>
				' .  url_printinputs() /* which includes uT */ . '
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="submit" value="Go!" />
			</td>
		</form>';

	$string .= '</tr>';

	return $string;
}





?>