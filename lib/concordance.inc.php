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


/* concordance.inc.php */

/* this file contains the code for (a) doing and (b) displaying a search */


/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */


/* initialise variables from settings files  */

require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include function library files */
require('../lib/library.inc.php');
require('../lib/concordance-lib.inc.php');
require('../lib/concordance-post.inc.php');
require('../lib/ceql.inc.php');
require('../lib/metadata.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/cache.inc.php');
require('../lib/subcorpus.inc.php');
require('../lib/db.inc.php');
require('../lib/user-settings.inc.php');

/* and because I'm using the next two modules I need to... */
//create_pipe_handle_constants();
require("../lib/cwb.inc.php"); /* NOT TESTED YET - used by dump and undump, I think */
require("../lib/cqp.inc.php");


/* write progressively to output in case of long loading time */
ob_implicit_flush(true);

if (!url_string_is_valid())
	exiterror_bad_url();














/* ----------------------------------------------------------------- */

/*            establish mysql and cqp data connections               */

/* ----------------------------------------------------------------- */


/* connect to mySQL and set up for UTF-8 */

$mysql_link = mysql_connect($mysql_server, $mysql_webuser, $mysql_webpass);

if (! $mysql_link)
{
	?>
	<p class="errormessage">
		mySQL did not connect - please try again later!
	</p></body></html> 
	<?php
	exit(1);
}

mysql_select_db($mysql_schema, $mysql_link);

/* utf-8 setting is dependent on a variable defined in settings.inc.php */
if ($utf8_set_required)
	mysql_query("SET NAMES utf8", $mysql_link);


/* connect to CQP */
$cqp = new CQP;

/* select an error handling function */
$cqp->set_error_handler("exiterror_cqp");

/* set CQP's temporary directory */
$cqp->execute("set DataDirectory '/$cqp_tempdir'");

/* select corpus */
//$cqp->execute("$corpus_cqp_name");
$cqp->set_corpus($corpus_cqp_name);
/* note that corpus must be (RE)SELECTED after calling "set DataDirectory" */










/* ------------------------------- */
/* initialise variables from $_GET */
/* and perform initial fiddling    */
/* ------------------------------- */



/* QUERY VARIABLES */
/* *************** */

/* qname is the overriding variable --- is it set? */
/* if it is, then we don't need $theData or anything like that */
if (isset($_GET['qname']) && $_GET['qname'] != 'INIT')
{
	$qname = $_GET['qname'];
	$incoming_qname_specified = true;
}
else
{
	/* if we're here, either $qname was INIT, or no qname was specified */
	$qname = 'INIT';
	$incoming_qname_specified = false;
}


/* handling of theData && qmode */
if (! $incoming_qname_specified )
{
	/* we have to have theData & qmode */
	if (isset($_GET['theData']))
		$_GET['theData'] = $theData = prepare_query_string($_GET['theData']);
	else
		exiterror_parameter('The content of the query was not specified!', __FILE__, __LINE__);

	if (isset($_GET['qmode']))
		$qmode = prepare_query_mode($_GET['qmode'], true);
	else
		exiterror_parameter('No query mode was specified!', __FILE__, __LINE__);
}
else
{
	/* theData & qmode are optional: set them to NULL if not present */
	/* note that they are ignored UNLESS qname turns out not to be cached after all */
	if (isset($_GET['theData']))
		$_GET['theData'] = $theData = prepare_query_string($_GET['theData']);
	else
		$theData = NULL;

	if (isset($_GET['qmode']))
		$qmode = prepare_query_mode($_GET['qmode'], false);
	else
		$qmode = NULL;
}
/* stop "theData" & "qmode" from being passed to any other script */
unset($_GET['theData']);
unset($_GET['qmode']);
/* only used if this is a new query */
$case_sensitive = ($qmode === 'sq_nocase' ? 0 : 1);



/* check for restrictions and subcorpus statements */
/* note that the specification of a subcorpus trumps restrictions */
/* note that these will be overwritten below if a named query is retrieved from cache */
if (isset($_GET['subcorpus']))
{
	$subcorpus = $_GET['subcorpus'];
	$restrictions = 'no_restriction';
}
else
{
	$subcorpus = 'no_subcorpus';
	$restrictions = translate_restrictions_definition_string();
	
	/* careful - if there was a subcorpus in the "&t=...", it will now be stated as a restriction */
	/* but that won't work, therefore we need to do this : */
	
	if (preg_match('/\A\(subcorpus=\'(\w+)\'\)\z/', $restrictions, $temp) > 0 ) 
	{
		$subcorpus = $temp[1];
		$restrictions = 'no_restriction';
	}
	unset($temp);
	
	/* this switches "last restrictions" from being treated as a subcorpus to being treated as restrictions */
	if ($subcorpus == '__last_restrictions')
	{
		$restrictions = reload_last_restrictions();
		$subcorpus = 'no_subcorpus';
	}
}


/* this always starts as an empty string -- it may be added to later by new-postprocess, */
/* or an existing postprocessor string may be loaded from memory. */
$postprocess = '';

/* load variables for new postprocesses */
$new_postprocess = false;
if (isset($_GET['newPostP']) && $_GET['newPostP'] !== '')
{
	$new_postprocess = new POSTPROCESS();
	if ($new_postprocess->parsed_ok() == false)
		exiterror_parameter('The parameters for query postprocessing could not be loaded!', 
			__FILE__, __LINE__);
	unset($_GET['pageNo']);
	/* so that we know it will go to page 1 of the postprocessed query */
}




/* RENDERING VARIABLES */
/* ******************* */

if (isset($_GET['pageNo']))
	$_GET['pageNo'] = $page_no = prepare_page_no($_GET['pageNo']);
else
	$page_no = 1;



if (isset($_GET['pp']))
	$per_page = prepare_per_page($_GET['pp']);   /* filters out any invalid options */
else
	$per_page = $default_per_page;
	
if ($per_page == 'count')
{
	$count_hits_then_cease = true;
	$per_page = $default_per_page;
}
else
	$count_hits_then_cease = false;
if ($per_page == 'all')
{
	$show_all_hits = true;
	$per_page = $default_per_page;
}
else
	$show_all_hits = false;


if (isset($_GET['viewMode']))
	$viewMode = $_GET['viewMode'];
else
	$viewMode = "kwic";
	// TO DO: get default view mode from user settings
	// with the user settings retrieved from mysql



/* set kwic variables */
// actually, these should be set using the default from mysql
if ($viewMode == "kwic") 
{
	$reverseViewMode = "line";
	$reverseViewButtonText = "Line View";
}
else
{
	$viewMode = "line";
	$reverseViewMode = "kwic";
	$reverseViewButtonText = "KWIC View";
}



/* the program variable */
/* note this is only used for the RENDERING of the query */
switch($_GET['program'])
{
case 'collocation':	/* does this actually do anything? */
//case 'distribution':
case 'sort':
case 'lookup':
case 'categorise':
	$program = $_GET['program'];
	break;
default:
	$program = 'search';
	break;
}












/* --------------------- */
/* set up user variables */
/* --------------------- */
// like default kwic view, possibly tooltips --- not done yet

/* determine, for this user, whether or not tooltips are to be displayed */
// not done yet



/* ----------------------- */
/* gather some corpus info */
/* ----------------------- */
$primary_tag_handle = get_corpus_metadata('primary_annotation');








/* ----------------------------------------------------------------------------- */
/* Start of section which runs two separate tracks                               */
/* a track for a query that is in cache and another track for a query that isn't */
/* ----------------------------------------------------------------------------- */

$startTime = microtime_float();
	


/* start by assuming that an old query can be dug up */
$run_new_query = false;
/* this will, or will not, be disproven later on     */

/* and set $num_of_solutions so it fails-safe to 0   */
$num_of_solutions = 0;



if ( $incoming_qname_specified )
{
	/* TRACK FOR CACHED QUERY */
	
	/* check the cache */
	if ( ! (($cache_record = check_cache_qname($qname)) === false) )
		$num_of_solutions = $cqp->querysize($qname);

	if  ( $cache_record === false || $num_of_solutions == 0 )
	{
		/* if query not found in cache, JUMP TRACKS */
		$_GET['qname'] = $qname = 'INIT';
		$incoming_qname_specified = false;	

		/* check the now-compulsory variables */
		if ($theData == NULL)
			exiterror_parameter('The content of the query was not specified!', __FILE__, __LINE__);	

		if ($qmode == NULL)
			exiterror_parameter('No query mode was specified!', __FILE__, __LINE__);
	}
	else
	{
		/* the cached file has been found and it DOESN'T contain 0 solutions */

		/* touch the query, updating its "time" to now */
		if ($cache_record['saved'] == 0)
			touch_cached_query($qname);
		
		/* take info from the cache record, and copy it to script variables & _GET */
		$qmode = $cache_record['query_mode'];
		// better just to unset, surely?
//		if (isset($_GET['qmode']))
//			$_GET['qmode'] = $qmode;
		unset($_GET['qmode']);
			
		$cqp_query = $cache_record['cqp_query'];

		$simple_query = $cache_record['simple_query'];
		
		$subcorpus = ($cache_record['subcorpus'] == 'NULL' ? 'no_subcorpus' : $cache_record['subcorpus']);
		// better just to unset, surely?
//		if (isset($_GET['subcorpus']))
//			$_GET['subcorpus'] = $subcorpus;
		unset($_GET['subcorpus']);
			
		$restrictions = ($cache_record['restrictions'] == 'NULL' ? 'no_restriction' : $cache_record['restrictions']);
		
		$postprocess = $cache_record['postprocess'];
//show_var($cache_record);
		unset($theData);
		
		/* next stop on this track is POSTPROCESS then DISPLAYING THE QUERY */
	}
}


/* this can't be an ELSE, because of the possibility of a track switch in preceding IF */
if ( ! $incoming_qname_specified )
{
	/* derive the $cqp_query and $simple_query variables and put the query into history */
	if ($qmode == 'cqp')
	{
		$simple_query = '';
		$cqp_query = $theData;
	}
	else /* if this is a simple query */
	{
		/* keep a record of the simple query */
		$simple_query = $theData;
		/* convert the simple query to a CQP query */
		//$theData = $cqp_query = process_simple_query($theData, $case_sensitive);	
		$cqp_query = process_simple_query($theData, $case_sensitive);
		
		if ($simple_query == $cqp_query)
			exiterror_general('The Simple Query Parser failed!', __FILE__, __LINE__);
	}
	/* either way, $theData is no longer needed */
	unset($theData);
	
	history_insert($instance_name, $cqp_query, $restrictions, $subcorpus, $simple_query, $qmode);
	$history_inserted = true; 
	
	
	/* look in the cache for a query that matches this one on crucial parameters */

	if ( ! (($cache_record = check_cache_parameters($cqp_query, $restrictions, $subcorpus)) === false) )
		$num_of_solutions = $cqp->querysize($cache_record['query_name']);
		
	if  ( $cache_record === false || $num_of_solutions == 0 )
	{
		/* query is not found in cache at all - therefore, it needs to be run anew */
		/* and said new query inserted into cache with a brand-new qname           */
		/* queries with no solutions are also re-run                               */
		$run_new_query = true;
	}
	else
	{
		/* we have a query in the cache with the same cqp_query, subc., restr., & postp.! */

		/* take info from the cache record, and copy it to script variables */
		/* note: cqp_query (and other parameters) were what we matched on, so no need to copy */
		$qname = $cache_record['query_name'];

		/* the other two are slightly complicated */
		/* cache record: if it already contains a simple_query, then it will be identical to */
		/* simple_query, so no need to update that way. Rather, update the other way.        */
		if ($simple_query != '' && $cache_record['simple_query'] == '')
		{
			$cache_record['simple_query'] = $simple_query;
			update_cached_query($cache_record);
		}
				
		/* qmode shouldn't be updated, because this was, after all, a "new" query */
		/* so regardless of the qmode of the cached query, this instance has its own qmode */	
		
		/* touch the query, updating its "time" to now */
		if ($cache_record['saved'] == 0)
			touch_cached_query($qname);
		/* next stop on this track is POSTPROCESS then DISPLAYING THE QUERY */
	}
}







/* ---------------------------------------------------------- */
/* START OF MAIN CHUNK THAT RUNS THE QUERY AND GETS SOLUTIONS */
/* ---------------------------------------------------------- */
if ($run_new_query)
{
	/* if we are here, it is a brand new query -- not saved or owt like that. Ergo: */
	$qname = qname_unique($instance_name);

	/* delete a cache file with this name if it exists */
	cqp_file_unlink($qname);
	
	/* set restrictions / activate subcorpus */

	if ($subcorpus != 'no_subcorpus')
		load_subcorpus_to_cqp($subcorpus);
	else if ($restrictions != 'no_restriction')
		load_restrictions_to_cqp($restrictions);
	
	/* this is the business end */
	$cqp->execute("$qname = $cqp_query");
	$cqp->execute("save $qname");

	if (($num_of_solutions = $cqp->querysize($qname)) == 0)
		/* no solutions */
		say_sorry($instance_name); /* note that this exits() the script! */

	/* put the query in the cache */
	cache_query($qname, $cqp_query, $restrictions, $subcorpus, '',
		$num_of_solutions, $simple_query, $qmode);
	/* no need to check this call - if there is some kind of cockup,     */
	/* it will be caught when the query is revisited by this very script */
	
	/* finally, create a query cache record. This array can be passed to functions that require */
	/* a big bag o' info about the query (e.g. postprocess functions, the heading creator */
	$cache_record = array(	'query_name' => $qname,
							'simple_query' => $simple_query,
							'cqp_query' => $cqp_query,
							'query_mode' => $qmode,
							'hits' => $num_of_solutions,
							'subcorpus' => $subcorpus,
							'restrictions' => $restrictions,
							'postprocess' => '',
							'hits_left' => '');
}
else
{
	/* nothing. The query has been retrieved from cache. */
}

/* set flag in history for query completed */
if ($history_inserted === true)
	history_update_hits($instance_name, $num_of_solutions);
	/* IF this query created a record, update it so it's not -3 */

/* -------------------------------------------------------- */
/* END OF MAIN CHUNK THAT RUNS THE QUERY AND GETS SOLUTIONS */
/* -------------------------------------------------------- */

/* --------------------------------------------- */
/* End of section which runs two separate tracks */
/* --------------------------------------------- */



/* ----------------------- */
/* START OF POSTPROCESSING */
/* ----------------------- */

if ($new_postprocess)
{
	/* Add the new postprocess to the existing  postprocessor string, and look it up  */
	/* by parameter (using cqp_query, restrictions, subcorpus, postprocessor string)  */
	
	$old_postprocess_string = $postprocess;
	$postprocess = $new_postprocess->add_to_postprocess_string($postprocess);



	/*	If it exists, the orig qname is replaced by this one */
	if ( ! (($check_cache_record = check_cache_parameters($cqp_query, $restrictions, $subcorpus, $postprocess)) === false) )
	{
		/* dump the cache record retrieved or created above and use this one */
		$cache_record = $check_cache_record;
		$qname = $cache_record['query_name'];
		
		/* PLUS change variable settings, as we did before (see above) for original-query-matched */

		if ($simple_query != '' && $cache_record['simple_query'] == '')
		{
			$cache_record['simple_query'] = $simple_query;
			update_cached_query($cache_record);
		}

		if ($cache_record['saved'] == 0)
			touch_cached_query($qname);
	}
	/* If it doesn't exist, the postprocess is applied to the qname (ergo the qname is replaced) */
	else
	{
		$post_function = $new_postprocess->get_run_function_name();
		
		
		$cache_record = $post_function($cache_record, $new_postprocess);
		/* the postprocess functions all re-set cr['postprocess'] and cr['hits_left'] etc. */
		/* in the new query that is created */
		$qname = $cache_record['query_name'];
		if ($cache_record['saved'] == 0)
			touch_cached_query($qname);
				
		/* and, because this means we are dealing with a query new-created in cache... */
		$run_new_query = true;
		/* so that it won't say the answer was retrieved from cache in the heading */
	}
}

/* get the highlight-positions table */
$highlight_positions_array = get_highlight_position_table($qname, $postprocess, $highlight_show_tag);

/* even if tags are to be shown, don't do so if no primary annotation is specified */
$highlight_show_tag = ( (! empty($primary_tag_handle) ) && $highlight_show_tag);


/* --------------------- */
/* END OF POSTPROCESSING */
/* --------------------- */


$endTime = microtime_float();
$timeTaken = round($endTime - $startTime, 3);




/* for safety, put the new qname into _GET; if a function looks there, it'll find the right qname */
$_GET['qname'] = $qname;
/* this is the qname of the cached query which the rest of the script will render */






/* whatever happened above, $num_of_solutions contains the number of solutions in the original query */
/* BUT a postprocess the num of solutions that get rendered and thus the number of pages */
/* num_of_solutions_final == the number of solutions all AFTER postprocessing */

$num_of_solutions_final = (
		($cache_record['hits_left'] == '' || $cache_record['hits_left'] === NULL) 
		?  $num_of_solutions 
		:  end(explode('~', $cache_record['hits_left'])) 
		);

/* so we can work out how many pages there are (also == the # of the last page) */
if ($show_all_hits)
	$per_page = $num_of_solutions_final;
	/* which will make the next statement set $num_of_pages to 1 */

$num_of_pages = (int)($num_of_solutions_final / $per_page) 
					+ (($num_of_solutions_final % $per_page) > 0 ? 1 : 0 );

/* make sure current page number is within feasible scope */
if ($page_no > $num_of_pages)
	$_GET['pageNo'] = $page_no = $num_of_pages;







/* ----------------------- */
/* DISPLAY THE CONCORDANCE */
/* ----------------------- */


/* if program is word-lookup,. we don't display here - we go straight to freqlist. */
if ($program == 'lookup')
{
	$showtype = ($_GET['lookupShowWithTags'] == 0 ? 'concBreakdownWords' : 'concBreakdownBoth');
	header("Location: redirect.php?redirect=$showtype&qname=$qname&pp=$per_page&uT=y");
	disconnect_all();
	exit();
}


/* before anything else */
header('Content-Type: text/html; charset=utf-8');


?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<?php
		echo '<title>' . $corpus_title . ' -- CQPweb Concordance</title>';
		echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
		?>

	</head>
	<body>

<?php



/* print table headings && control lines */

echo "\n<table class=\"concordtable\" width=\"100%\">\n";

echo '<tr><th colspan="8" class="concordtable">' 
	. create_solution_heading($cache_record)
	. format_time_string($timeTaken, $run_new_query)
	. '</th></tr>';

if ($count_hits_then_cease)
{
	echo '</table>';
	print_footer();
	disconnect_all();
	exit();
}

$control_row = print_control_row();
echo $control_row;



if ($program == "sort")
	echo print_sort_control($primary_tag_handle, $cache_record['postprocess']);




/* set up CQP options for the concordance display */

$cqp->execute("set Context $context_scope words");
// unannotated corpora - this is a quick fix not done in any kind of principled way
$cqp->execute('show +word ' . (empty($primary_tag_handle) ? '' : "+$primary_tag_handle "));
$cqp->execute("set PrintStructures \"text_id\""); 
$cqp->execute("set LeftKWICDelim '--%%%--'");
$cqp->execute("set RightKWICDelim '--%%%--'");




/* what number does the concordance start and end at? */
/* conc_ = numbers that are shown */
/* batch_ = numbers for CQP, which are one less */
$conc_start = (($page_no - 1) * $per_page) + 1; 
$conc_end = $conc_start + $per_page - 1;
if ($conc_end > $num_of_solutions_final)
	$conc_end = $num_of_solutions_final;

$batch_start = $conc_start - 1;
$batch_end = $conc_end - 1;

/* get an array containing the lines of the query to show this time */
$kwic = $cqp->execute("cat $qname $batch_start $batch_end");

/* get a table of corpus positions */
$table = $cqp->dump($qname, $batch_start, $batch_end);
// !!!!!!!!!!!!!!!!!! is this used? it is passed to print_concordance_line, but does that use it?

/* n = number of concordances we have to display in this run of the script */
$n = count($kwic);




?>
</table>
<table class="concordtable" width="100%">
<?php

if ($program == 'categorise')
{
	echo '<form action="redirect.php" method="get">';
	
	/* and note, in this case we will need info on categories for the drop-down controls */ 
	$list_of_categories = catquery_list_categories($qname);
	$category_table = catquery_get_categorisation_table($qname, $conc_start, $conc_start+$n-1);
}

echo print_column_headings();


/* --------------------------- */
/* concordance line print loop */
/* --------------------------- */


for ( $i = 0, $b = ($highlight_positions_array !== false) ; $i < $n ; $i++ )
{
	$highlight_position = ($b ? (int)$highlight_positions_array[$i] : 1000000);
	
	$line = print_concordance_line($kwic[$i], $table, ($conc_start + $i), 
				$highlight_position, $highlight_show_tag);


	$categorise_column = '';
	if ($program == 'categorise') 
	{
		/* lookup what category this line has, and then build a box for it */
		$categorise_column = '<td align="center" class="concordgeneral">';
		$categorise_column .= '<select name="cat_' . ($conc_start + $i) . '">';
		
		if ($category_table[$conc_start + $i] === NULL)
			$categorise_column .= '<option select="selected"> </option>';

		foreach($list_of_categories as &$thiscat)
		{
			$select =  ($category_table[$conc_start + $i] == $thiscat) ? ' selected="selected"' : '' ; 
			$categorise_column .= "<option$select>$thiscat</option>";
		}
		
		$categorise_column .= '</select></td>';
	}
	
	echo "\n<tr>{$line}{$categorise_column}</tr>\n";
}
/* end of concordance line print loop */


/* the categorise control row */
if ($program == 'categorise')
{
	echo print_categorise_control()
		. '<input type="hidden" name="redirect" value="categorise"/>'
		. '<input type="hidden" name="pageNo" value="' . $page_no . '"/>'
		. '<input type="hidden" name="qname" value="'. $qname . '"/>'
		. '<input type="hidden" name="uT" value="y"/>'
		. '</form>'
		;
}

/* finish off table */
echo '</table>';

// more listfiles/categorise


/* show the control row again at the bottom if there are more than 15 lines on screen */
if ($num_of_solutions_final > 15 && $per_page > 15)
	echo "\n<table class=\"concordtable\" width=\"100%\">\n" . $control_row . '</table>';




/* create page end HTML */
print_footer();

/* clear out old stuff from the query cache (left till here to increase speed for user) */
delete_cached_queries();

/* and update the last restrictions (ditto) */
if ($restrictions != 'no_restriction')
	create_subcorpus_restrictions('__last_restrictions', $restrictions);

/* disconnect CQP child process using destructor function */
$cqp->disconnect();

/* disconnect mysql */
mysql_close($mysql_link);


/* ------------- */
/* END OF SCRIPT */
/* ------------- */
?>