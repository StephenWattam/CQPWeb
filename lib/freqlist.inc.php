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



/* initialise variables from settings files  */

require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include function library files */
require_once("../lib/library.inc.php");
require_once("../lib/freqtable.inc.php");
require_once("../lib/exiterror.inc.php");
require_once("../lib/metadata.inc.php");
require_once("../lib/user-settings.inc.php");
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

/* this is a bit of a cheat to get rid of empty strings && make sure isset works properly */
foreach ($_GET as $k => $g)
	if ($g === '')
		unset($_GET[$k]);
		/* note, this could bugger up any script that needs to read a restriction string! */


/* do we want a nice HTML table or a downloadable table? */
$download_mode = (bool)$_GET['tableDownloadMode'];


/*
 * the table to use would be extracted from GET here, but it requires mysql
 */




/* flAtt: attribute to base the table on */

if (!isset($_GET['flAtt']) )
	$att = 'word';
else
	$att = $_GET['flAtt'];
if (preg_match('/\W/', $_GET['flAtt']) > 0)
	exiterror_fullpage("An invalid word-annotation ($att) was specified!", __FILE__, __LINE__);
/* validated below */

/* determine the order of the frequency list */

switch ($_GET['flOrder'])
{
case 'alph':
	$order_by_clause = 'order by item asc, freq desc';
	break;
case 'asc':
	$order_by_clause = 'order by freq asc, item';
	break;
default:
	$order_by_clause = 'order by freq desc, item';
	break;
}



/* set up the filter */
/* value checking is done by the mysql_real_escape_string and by the "switch" */

if (isset($_GET['flFilterString']) && $_GET['flFilterString'] !== '')
{
	switch($_GET['flFilterType'])
	{
	case 'begin':
		$filter_clause = "item like '" . mysql_real_escape_string($_GET['flFilterString']) . "%'";
		$filter_desc = ", starting with &ldquo;{$_GET['flFilterString']}&rdquo;";
		break;
	case 'end':
		$filter_clause = "item like '%" . mysql_real_escape_string($_GET['flFilterString']) . "'";
		$filter_desc = ", ending with &ldquo;{$_GET['flFilterString']}&rdquo;";
		 break;
	case 'contain':
		$filter_clause = "item like '%" . mysql_real_escape_string($_GET['flFilterString']) . "%'";
		$filter_desc = ", containing &ldquo;{$_GET['flFilterString']}&rdquo;";
		break;
	case 'exact':
		$filter_clause = "item = '" . mysql_real_escape_string($_GET['flFilterString']) . "'";
		$filter_desc = ", matching &ldquo;{$_GET['flFilterString']}&rdquo;";
		break;
	default:	/* inc NULL or '' if filter type not set */
		$filter_clause = "";
		$filter_desc = '';
		break;
	}
}

/* set up the frequency filter */

/* if only one is set, make sure it is flFreqLimit1 */
if (isset($_GET['flFreqLimit2']) && !isset($_GET['flFreqLimit1']))
{
	$_GET['flFreqLimit1'] = $_GET['flFreqLimit2'];
	unset($_GET['flFreqLimit2']);
}

if (isset($_GET['flFreqLimit1'], $_GET['flFreqLimit2']))
{
	/* both are set */
	$up_limit = (int)$_GET['flFreqLimit1'];
	$down_limit = (int)$_GET['flFreqLimit2'];
	if ($down_limit > $up_limit)
	{
		$temp = $down_limit;
		$down_limit = $up_limit;
		$up_limit = $temp;
	}
	$range_clause = "freq BETWEEN $down_limit AND $up_limit";
	$range_desc = ", occurring between $down_limit and $up_limit times";
}
else if (isset($_GET['flFreqLimit1']))
{
	/* only one was set: treat as a minimum if we are in desc order, a maximum otherwise */
	$limit = (int)$_GET['flFreqLimit1'];
	if ($_GET['flOrder'] === 'asc')
	{
		$range_clause = "freq >= $limit";
		$range_desc = ", occurring at least $limit times";
	}
	else
	{
		$range_clause = "freq <= $limit";
		$range_desc = ", occurring not more than $limit times";
	}
}
else
	$range_desc = $range_clause = '';



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




/* -------------------------- */
/* end of variable initiation */
/* -------------------------- */







/* now there are two more parameters to process */

/* the table to use (basename) */

if ( !isset($_GET['flTable']) || $_GET['flTable'] == __entire_corpus )
{
	$table_base = "freq_corpus_$corpus_sql_name";
	$table_desc = "entire &ldquo;$corpus_title&rdquo;";
}
else
{
	/* for security -- it should, of course, be a handle */
	$subcorpus = mysql_real_escape_string($_GET['flTable']);
	$freqtable_record = check_freqtable_subcorpus($subcorpus);
	$table_base = $freqtable_record['freqtable_name'];
	$table_desc = "subcorpus &ldquo;{$subcorpus}&rdquo;";
}

/* create a restriction string to go in any queries that are created */
$restrict_string = (isset($subcorpus) ? '&del=begin&t=subcorpus~'. $subcorpus . '&del=end' : '');


/* check the attribute setting is valid */

$att_desc = get_corpus_annotations();	
$att_desc['word'] = 'Word';

/* if the script has been fed an attribute that doesn't exist for this corpus, failsafe to 'word' */
if (! array_key_exists($att, $att_desc) )
	$att = 'word';

$freqtable = "{$table_base}_$att";





/* now we can assemble the SQL query */

if (! $range_clause && ! $filter_clause)
	$grand_where = '';
else if ($range_clause && !$filter_clause)
	$grand_where = "where $range_clause";
else if (!$range_clause && $filter_clause)
	$grand_where = "where $filter_clause";
else
	$grand_where = "where $filter_clause and $range_clause";

$sql_query = "SELECT item, freq from $freqtable 
	$grand_where
	$order_by_clause
	$limit_string";

/* and run it */
$result = do_mysql_query($sql_query);

$n = mysql_num_rows($result);

$next_page_exists = ( $n == $per_page ? true : false );

$description = "Frequency list: {$att_desc[$att]} frequencies in {$table_desc}{$filter_desc}{$range_desc}";








if ($download_mode)
{
	freqlist_write_download($att_desc[$att], $description, $result);
}
else
{
	header('Content-Type: text/html; charset=utf-8');
	/* writing HTML begins here! */
	?>
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<?php
	echo '<title>' . $corpus_title . ' -- view CQPweb frequency list</title>';
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
		<?php echo print_freqlist_control_row($page_no, $next_page_exists); ?>
	</table>

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" width="5%">No.</th>
			<th class="concordtable" width="40%"><?php echo $att_desc[$att]; ?></th>
			<th class="concordtable">Frequency</th>
		</tr>

	<?php
	/* print the results */
	
	/* this is the number SHOWN on the first line */
	/* the value of $i is (relatively speaking) 1 less than this */
	$begin_at = (($page_no - 1) * $per_page) + 1; 
	
	for ( $i = 0 ; $i < $n ; $i++ )
	{
		$r = mysql_fetch_object($result);
		$line = print_freqlist_line($r, ($begin_at + $i), $att, $restrict_string);
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



function print_freqlist_line($data, $line_number, $att, $restricts)
{
	/* 
	 * the format of "data" is as follows
	 * object(stdClass)(2) {
	 *   ["item"]=>
	 *   ["freq"]=>
	 */

	/* case-sensitivity of the corpus affects use of flags in CQP queries, and lowercasing of wordforms */
	global $corpus_cqp_query_default_flags;
	global $corpus_uses_case_sensitivity;

	if ( $att == 'word' && ! $corpus_uses_case_sensitivity && function_exists('mb_strtolower') )
		$data->item = mb_strtolower($data->item, 'UTF-8');
		/* there may be a better function to use in future versions of PHP; the mb extension is nonstandard */

	$target = CQP::escape_metacharacters($data->item);

	$link = 'href="concordance.php?theData=' 
			. urlencode("[$att=\"{$target}\"$corpus_cqp_query_default_flags]")
			. $restricts 
			. '&qmode=cqp&uT=y"'
			;
	$string  = "<td class=\"concordgeneral\" align=\"right\"><b>$line_number</b></td>";
	$string .= "<td class=\"concordgeneral\"><b><a $link>{$data->item}</a></b></td>";
	$string .= "<td class=\"concordgeneral\"  align=\"center\">" 
		. make_thousands($data->freq) . '</td>';
	
	return $string;
}





function print_freqlist_control_row($page_no, $next_page_exists)
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
			$string .= 'href="freqlist.php?'
				. url_printget(array(
					array('pageNo', "$n")
					) )
				. '"';
		$string .= ">$m</b></a></td>";
	}
// this should be generalised as is used by lots of scripts: use $this_script


	$string .= 
		'<form action="redirect.php" method="get">
			<td class="concordgrey">
				<select name="redirect">
					<option value="newFreqlist" selected="selected">New Frequency List</option>
					<option value="downloadFreqList">Download whole list</option>
					<option value="newQuery">New Query</option>
				</select>
				' .  url_printinputs() /* which includes uT */ . '
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="submit" value="Go!" />
			</td>
		</form>';

	$string .= '</tr>';

	return $string;
}


function freqlist_write_download($att_desc, $description, &$result)
{
	$da = get_user_linefeed($username);
	$description = preg_replace('/&[lr]dquo;/', '"', $description);

	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=frequency_list.txt");
	echo "$description$da";
	echo "__________________$da$da";
	echo "Number\t$att_desc\tFrequency$da$da";

	for ($i = 1, $total = 0; ($r = mysql_fetch_object($result)) !== false ; $i++ )
	{
		echo "$i\t{$r->item}\t{$r->freq}$da";
		$total += $r->freq;
	}
	echo "{$da}Total:\t\t$total$da";
}

?>