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



/* ------------ *
 * BEGIN SCRIPT *
 * ------------ */

/* like similar scripts, this delays writing to stdout until the end because of dual output formats */


/* initialise variables from settings files  */
require('../lib/environment.inc.php');


/* include function library files */
require('../lib/library.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/metadata.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/cache.inc.php');
require('../lib/concordance-lib.inc.php');
require('../lib/concordance-post.inc.php');
require('../lib/subcorpus.inc.php');
require('../lib/db.inc.php');
require('../lib/user-lib.inc.php');
require("../lib/cwb.inc.php");
require("../lib/cqp.inc.php");

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP );






/* ------------------------------- *
 * initialise variables from $_GET *
 * and perform initial fiddling    *
 * ------------------------------- */



$qname = safe_qname_from_get();



/* if the "program equals sort" thing has been passed through from concordance, 
 * switch into the mode where we use the existing sort position. */
if (isset($_GET['program']) && empty($_GET['concBreakdownAt']) && $_GET['program'] == 'sort')
{
	$sortprogram = true;
	$_GET['concBreakdownAt'] = 'sort';
}
else
	$sortprogram = false;


/* the root of the SQL fieldname for the thing we are breaking down */

switch (isset ($_GET['concBreakdownAt']) ? $_GET['concBreakdownAt'] : 'node')
{
case 'node':
	/* break down the query hit itself */ 
	$sql_position = 'node';
	break;

case 'sort':
	/* use the current sort position : flag for later extraction from postprocess string */
	$sql_position = '~EXTRACT~FROM~SORT~RECORD~';
	break;

default:
	/* detect a specified position */
	if (0 < preg_match('/^(before|after)\d+$/', $_GET['concBreakdownAt']))
		$sql_position = $_GET['concBreakdownAt'];
	else
		$sql_position = 'node';
	break;
}

// TODO need also to put the breakdown position into the description string


switch (isset ($_GET['concBreakdownOf']) ? $_GET['concBreakdownOf'] : 'words')
{
case 'annot':
case 'both':
	$breakdown_of = $_GET['concBreakdownOf'];
	break;
default:
	$breakdown_of = 'words';
	break;
}


/* do we want a nice HTML table or a downloadable table? */
$download_mode = (isset($_GET['tableDownloadMode']) && $_GET['tableDownloadMode'] == 1);


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









$att_desc = get_corpus_annotations();	
$att_desc['word'] = 'Word';

$primary_annotation = get_corpus_metadata('primary_annotation');


if (empty($primary_annotation) && $breakdown_of != 'words')
{
	exiterror_general('You cannot do a frequency breakdown based on annotation, ' 
		. 'because no primary annotation is specified for this corpus.');
}




/* does a db for the sort exist? */

/* first get all the info about the query in one handy package */

$query_record = check_cache_qname($qname);
if ($query_record === false)
	exiterror_general("The specified query $qname was not found in cache!", __FILE__, __LINE__);


/* now, search the db list for a db whose parameters match those of the query
 * named as qname; if it doesn't exist, we need to create one */
$db_record = check_dblist_parameters('sort', $query_record['cqp_query'],
				$query_record['restrictions'], $query_record['subcorpus'],
				$query_record['postprocess']);

if ($db_record === false)
{
	$is_new_db = true;
	
	$dbname = create_db('sort', $qname, $query_record['cqp_query'], $query_record['restrictions'], 
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



/* now that we have the query record, we can finalise the SQL position, 
 * and then set up the info array that we will use later in the script... */

if ($sql_position == '~EXTRACT~FROM~SORT~RECORD~')
{
	// TODO stick here some kind of detection of the comand to breakdown contents of sort position.
}

$breakdown_of_info = array(
	'words' => array('desc'=>'words only',               
					'sql_label'=> "$sql_position",
					'sql_groupby'=> "$sql_position"
					),
	'annot' => array('desc'=>'annotation only',
					'sql_label'=> "tag$sql_position",
					'sql_groupby'=> "tag$sql_position"
				   ),
	'both'  => array('desc'=>'both words and annotation', 
					'sql_label'=> "concat($sql_position,'_',tag$sql_position)",
					'sql_groupby'=> "$sql_position, tag$sql_position"
					)
	);



/* find out how big the db is: types and tokens */
$sql_query = "select count({$breakdown_of_info[$breakdown_of]['sql_label']}) as tokens, 
	count(distinct({$breakdown_of_info[$breakdown_of]['sql_label']})) as types 
	from $dbname";
list($db_tokens_total, $db_types_total) = mysql_fetch_row(do_mysql_query($sql_query));





// TODO no this bit won't work because it DOES NOT incorporate the thinning criteria of the sort.
// it really needs to key off +++ALL++ the sort parameters, if they're present.


/* create the description */

$description = (  ? 'Solutions include ' :  );

if ('node' == $sql_position)
	$description = "At this position (, there are " 
		. number_format((float)$db_types_total) . " different types and " 
		. number_format((float)$db_tokens_total) . " tokens." 
		. " for &ldquo;{$query_record['cqp_query']}&rdquo;. ";
else
	$description = "'At position ' . stringise_sql_position($sql_position) . ', there are ' " 
		. number_format((float)$db_types_total) . " different types and " 
		. number_format((float)$db_tokens_total) . " tokens." 
		. " for &ldquo;{$query_record['cqp_query']}&rdquo;. ";

/* Add the description of the query ... */
$description .= '(' . create_solution_heading($query_record, false) . '.)';

/* finally add the description of what we're displaying. */
$description .= "<br/>Showing breakdown of {$breakdown_of_info[$breakdown_of]['desc']}.";




$sql_query = "select {$breakdown_of_info[$breakdown_of]['sql_label']} as n, 
	count({$breakdown_of_info[$breakdown_of]['sql_label']}) as sum 
	from $dbname group by {$breakdown_of_info[$breakdown_of]['sql_groupby']} 
	order by sum desc
	$limit_string";

$result = do_mysql_query($sql_query);

if (mysql_num_rows($result) < 1)
{
	/* normal cause of this: we have overflowed the page number. */
	if ($page_no > 1)
		exiterror_general("You requested a page of the frequency breakdown that appears to be empty!"); 
	else
		exiterror_general("Your frequency breakdown request produced no results. This may indicate a database error. \n"
			. "You should contact the system administrator.\n");
	/* if the query is empty, it suggests the database was not created properly.... */
}
else if ($download_mode)
{
	freqbreakdown_write_download($result, $description, $db_tokens_total);
}
else
{
	/* ----------------------------------------------------- *
	 * create the control row for concordance freq breakdown *
	 * ----------------------------------------------------- */
	$num_of_pages = (int)($db_types_total / $per_page) + (($db_types_total % $per_page) > 0 ? 1 : 0 ); 

	/* now, create backards-and-forwards-links */
	$marker = array( 'first' => '|&lt;', 'prev' => '&lt;&lt;', 'next' => "&gt;&gt;", 'last' => "&gt;|" );
	
	/* work out page numbers */
	$nav_page_no['first'] = ($page_no == 1 ? 0 : 1);
	$nav_page_no['prev']  = $page_no - 1;
	$nav_page_no['next']  = ($num_of_pages == $page_no ? 0 : $page_no + 1);
	$nav_page_no['last']  = ($num_of_pages == $page_no ? 0 : $num_of_pages);
	/* all page numbers that should be dead links are now set to zero  */


	$navlinks = '';
	foreach ($marker as $key => $m)
	{
		$navlinks .= '<td align="center" class="concordgrey"><b><a class="page_nav_links" ';
		if ( $nav_page_no[$key] != 0 )
			/* this should be an active link */
			$navlinks .= 'href="redirect.php?redirect=breakdown&'
				. url_printget(array(
					array('uT', ''), array('pageNo', $nav_page_no[$key]), array('qname', $qname)
					) )
				. '&uT=y"';
		$navlinks .= ">$m</b></a></td>";
	}
	

	$return_option = ($sql_position=='node' 
					  ? '<option value="concBreakdownNodeSort">Show hits sorted by node</option>'
					  : '<option value="concBreakdownPositionSort">Show hits sorted on position XXXXX</option>' /// TODO
					 );
	
	$freq_breakdown_controls = '
		<form action="redirect.php" method="get">
			<td class="concordgrey" align="center">
				<select name="redirect">
					<option value="concBreakdownWords">Frequency breakdown of words only</option>
					<option value="concBreakdownAnnot">Frequency breakdown of annotation only</option>
					<option value="concBreakdownBoth">Frequency breakdown of words and annotation</option>
					<option value="concBreakdownDownload">Download whole frequency breakdown table</option>
					' . $return_option . '
					<option value="newQuery" selected="selected">New query</option>
				</select>
				<input type="submit" value="Go!"/>

			</td>
			<td class="concordgrey" align="center">
				<select name="concBreakdownAt">
					' . print_sort_position_options(integerise_sql_position($sql_position)) . '
				</select>

				' . url_printinputs(array(
					array('tableDownloadMode', ''), array('redirect', ''), array('uT', ''), array('qname', $qname)
					) ) 
				. '

			</td>
			<input type="hidden" name="redirect" value="breakdown" />
			<input type="hidden" name="uT" value="y"/>
		</form>
		';


	/* ------------------------------------------------------------ *
	 * end of create the control row for concordance freq breakdown *
	 * ------------------------------------------------------------ */
	
	
	/* now, put it all together into a pretty HTML page! */
	
	echo print_html_header($corpus_title . ' -- CQPweb Query Frequency Breakdown', $css_path, array('cqpweb=clientside'));
	
	?>
	
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="6" class="concordtable"><?php echo $description ?></th>
			</tr>
			<tr>

				<?php echo $navlinks, $freq_breakdown_controls; ?>

			</tr>
		</table>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable" align="left">No.</th>
				<th class="concordtable" align="left">Search result</th>
				<th class="concordtable">No. of occurrences</th>
				<th class="concordtable">Percent</th>
			</tr>
			
			<?php
			
			for ( $i = (($page_no-1)*$per_page)+1 ; ($r=mysql_fetch_object($result)) !== false; $i++)
			{
				$percent = round(($r->sum / $db_tokens_total)*100, 2);
				
				switch($breakdown_of)
				{
				case 'words':
					$iF = urlencode($r->n);
					$iT = '';
					break;
				case 'annot':
					$iF = '';
					$iT = urlencode($r->n);
					break;
				case 'both':
					preg_match('/\A(.*)_([^_]+)\z/', $r->n, $m);
					$iF = urlencode($m[1]);
					$iT = urlencode($m[2]);
					break;
				}
				$link = "concordance.php?qname=$qname&newPostP=item&newPostP_itemForm=$iF&newPostP_itemTag=$iT&uT=y";
				
				echo "\n<tr>\n"
					, "\t<td class=\"concordgrey\">$i</td>\n"
					, "\t<td class=\"concordgeneral\"><a href=\"$link\">", cqpweb_htmlspecialchars($r->n), "</a></td>\n"
					, "\t<td class=\"concordgeneral\" align=\"center\">{$r->sum}</td>\n"
					, "\t<td class=\"concordgeneral\" align=\"center\">$percent%</td>\n"
					, "</tr>\n\n"
					;
			
			}
			?>
			
		</table>
		
	<?php
	
	
	/* create page end HTML */
	echo print_html_footer();
	
} /* end of if / else tree for doing something with the result of the main SQL query" */


cqpweb_shutdown_environment();


/* ------------- *
 * END OF SCRIPT *
 * ------------- */



function freqbreakdown_write_download(&$result, $description, $total_for_percent)
{
	global $username;
	$da = get_user_linefeed($username);
	$description = preg_replace('/&[lr]dquo;/', '"', $description);
	$description = preg_replace('/<\/?em>/', '', $description);
	$description = str_replace('<br/>', $da, $description);
	
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=concordance_frequency_breakdown.txt");

	echo "$description$da";
	echo "__________________$da$da";
	echo "No.\tSearch result\tNo. of occurrences\tPercent";
	echo "$da$da";

	
	for ( $i = 1 ; ($r = mysql_fetch_row($result)) !== false; $i++)
	{
		$percent = round(($r[1] / $total_for_percent)*100, 2);
		echo "$i\t{$r[0]}\t{$r[1]}\t$percent$da";
	}
}


function integerise_sql_position($sql_position)
{
	if ( $sql_position == 'node' )
		return 0;
	
	$sign = (false !== strpos($sql_position, 'before') ? 1 : -1);
	
	/* failsafe to node ==> 0 */
	if (1 > preg_match('/^(before|after)(\d+)/', $sql_position, $m)) 
		return 0;
	
	return ($m[1]==='before' ? -1 : 1) * (int)$m[2] ;
}



?>
