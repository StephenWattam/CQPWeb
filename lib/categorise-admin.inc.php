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
 * This script does a number of different thihngs, depending on the value of GET->categoriseAction 
 */

/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */

/* initialise variables from settings files  */
require('../lib/environment.inc.php');


/* include function library files */
require('../lib/library.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/cache.inc.php');
require('../lib/db.inc.php');
require('../lib/user-settings.inc.php');

require("../lib/cwb.inc.php");
require("../lib/cqp.inc.php");


/* write progressively to output in case of long loading time */
ob_implicit_flush(true);

if (!url_string_is_valid())
	exiterror_bad_url();



/* connect to mySQL */
connect_global_mysql();


/* connect to CQP */
connect_global_cqp();







/* choose script action on the basis of the programmed action */
switch ($_GET['categoriseAction'])
{
	case 'enterCategories':
		categorise_enter_categories();
		break;
	
	case 'createQuery':
		categorise_create_query();
		break;
	
	case 'updateQueryAndLeave':
		categorise_update();
		header("Location: index.php?thisQ=categorisedQs&uT=y");
		break;
		
	case 'updateQueryAndNextPage':
		categorise_update();
		$inputs = url_printget(array(
			array('uT', ''),
			array('categoriseAction', ''),
			array('pageNo', (string)(isset($_GET['pageNo']) ? (int)$_GET['pageNo'] + 1 : 2)) 
			));
		header("Location: concordance.php?program=categorise&$inputs&uT=y");
		break;
		
	case 'noUpdateNewQuery':
		header("Location: index.php");
		break;	

	case 'separateQuery':
		categorise_separate();
		header("Location: index.php?thisQ=savedQs&uT=y");
		break;
		
	case 'enterNewValue':
		categorise_enter_new_value();
		break;
		
	case 'addNewValue':
		categorise_add_new_value();
		header("Location: index.php?thisQ=categorisedQs&uT=y");
		break;
	
	case 'deleteCategorisedQuery':
		categorise_delete_query();
		header("Location: index.php?thisQ=categorisedQs&uT=y");
		break;
		
	default:
		echo '<p>categorise-admin.php was passed an invalid parameter as categoriseAction! CQPweb aborts.</p>';
		break;
}


/* disconnect CQP child process using destructor function */
disconnect_global_cqp();

/* disconnect mysql */
disconnect_global_mysql();


/* ------------- */
/* END OF SCRIPT */
/* ------------- */













function categorise_create_query()
{
	global $username;
	global $corpus_sql_name;

	/* get values from $_GET */
	
	/* qname to begin with = qname */
	$qname = safe_qname_from_get();

	if (check_cache_qname($qname) === false)
		exiterror_fullpage("The specified query $qname was not found in cache!", __FILE__, __LINE__);
	

	if(isset($_GET['defaultCat']))
		$default_cat_number = (int)$_GET['defaultCat'];
	else
		$default_cat_number = 0;


	/* check there is a savename for the catquery and it contains no badchars */
	if (empty($_GET['categoriseCreateName']))
	{
		categorise_enter_categories('no_name');
		disconnect_all();
		exit();
	}
	else if ( ! cqpweb_handle_check($savename = $_GET['categoriseCreateName']) )
	{
		categorise_enter_categories('bad_names');
		disconnect_all();
		exit();
	}
	
	/* make sure no catquery of that name already exists */
	$sql_query = "select query_name from saved_queries where user = '$username' and save_name = '$savename'";
	$result = do_mysql_query($sql_query);
	if (mysql_num_rows($result) > 0)
	{
		categorise_enter_categories('name_exists');
		disconnect_all();
		exit();
	}

	
	$categories = array();
	for ($i = 1 ; $i < 7 ; $i++)
	{
		$thiscat = (isset($_GET["cat_$i"]) ? $_GET["cat_$i"] : ''); 

		/* skip any zero-length cats */
		if ($thiscat === '')
			continue;
		/* make sure there are no non-word characters in the name of each category */
		if ( ! cqpweb_handle_check($thiscat) )
		{
			categorise_enter_categories('bad_names');
			disconnect_all();
			exit();
		}
		/* make sure there are no categories that are the same */
		if (in_array($thiscat, $categories))
		{
			categorise_enter_categories('cat_repeated');
			disconnect_all();
			exit();
		}
		/* this cat is OK! */
		$categories[$i] = $thiscat;
	}
	/* make sure there actually exist some categories */
	if (count($categories) == 0)
	{
		categorise_enter_categories('no_cats');
		disconnect_all();
		exit();
	}
	$categories[] = 'other';
	$categories[] = 'unclear';
	$cat_list = implode('|', $categories);




	/* save the current query using the name that was set for categorised query */
	$newqname = qname_unique($username. '_' . $savename);
	copy_cached_query($qname, $newqname);
	
	/* get the query record for the newly-saved query */
	$query_record = check_cache_qname($newqname);
	
	/* and update it */
	$query_record['query_name'] = $newqname;
	$query_record['user'] = $username;
	$query_record['saved'] = 2;
	/* note that saved = "2" indicates a categorised query */
	$query_record['save_name'] = $savename;
	update_cached_query($query_record);
	
	/* and refresh CQP's listing  of queries in the cache directory */
	refresh_directory_global_cqp();


	
	/* create a db for the categorisation */
	$dbname = create_db('catquery', $newqname, $query_record['cqp_query'], $query_record['restrictions'], 
				$query_record['subcorpus'], $query_record['postprocess']);

	/* if there is a default category, set that default on every line */
	if ($default_cat_number != 0)
		do_mysql_query("update $dbname set category = '{$categories[$default_cat_number]}'");




	/* create a record in saved_catqueries that links the query and the db */
	$sql_query = "insert into saved_catqueries (catquery_name, user, corpus, dbname, category_list) 
					values ('$newqname', '$username', '$corpus_sql_name', '$dbname', '$cat_list')";
	do_mysql_query($sql_query);

	header("Location: concordance.php?qname=$newqname&program=categorise&uT=y");

}




function categorise_update()
{
	$qname = safe_qname_from_get();
	
	$dbname = catquery_find_dbname($qname);
	
	foreach ($_GET as $key => &$val)
	{
		if ( preg_match('/^cat_(\d+)$/', $key, $m) < 1)
			continue;
		$refnumber = $m[1];
		unset($_GET[$key]);
		$selected_cat = mysql_real_escape_string($val);
		
		/* don't update if all we've been passed for this concrdance line is an empty string */
		if (empty($selected_cat))
			continue;
		
		$sql_query = "update $dbname set category = '$selected_cat' where refnumber = $refnumber";
		do_mysql_query($sql_query);
	}
	
	/* and finish by ... */
	touch_db($dbname);
	touch_cached_query($qname);

}





function categorise_separate()
{
	global $username;
	global $cqp;
	global $cqpweb_tempdir;
	global $corpus_sql_name;
	
	$qname = safe_qname_from_get();

	/* check that the query in question exists and is a catquery */
	$query_record = check_cache_qname($qname);
	if ($query_record === false || $query_record['saved'] != 2)
		exiterror_fullpage("The specified categorised query \"$qname\" was not found!", __FILE__, __LINE__);
	
	
	$dbname = catquery_find_dbname($qname);
	
	/* we DO NOT use a unique ID from instance_name, because we want to be able to 
	 * delete this query later if the mother-query is re-separated. See below. */
	$newqname_root = $qname . '_';
	$newsavename_root = $query_record['save_name'] . '_';

	$outfile_path = "/$cqpweb_tempdir/temp_cat_$newqname_root.tbl";
	if (is_file($outfile_path))
		unlink($outfile_path);

	$category_list = catquery_list_categories($qname);
	
	
	/* MAIN LOOP for this function */
	/* applies to every category in the catquery we are dealing with */
	
	foreach($category_list as &$category)
	{
		$newqname = $newqname_root . $category;
		/* if the query exists... (note, we wouldn't normally overwrite, but for separation we do */
		delete_cached_query($newqname);
		/* we also want to eliminate any existing DBs based on this  query, so any data
		 * based on a previous separation is removed */
		delete_dbs_of_query($newqname);
		
		refresh_directory_global_cqp();
		
		$newsavename = $newsavename_root . $category;
		
		/* create the dumpfile & obtain solution count */
		$sql_query = "SELECT beginPosition, endPosition FROM $dbname 
			WHERE category = '$category'";
		$solution_count = do_mysql_outfile_query($sql_query, $outfile_path);
		
		if ($solution_count < 1)
		{
			unlink($outfile_path);
			continue;
		}	
		
		$cqp->execute("undump $newqname < '$outfile_path'");
		$cqp->execute("save $newqname");

		unlink($outfile_path);
		
		/* longer postprocessor string ... */
		$new_pp_string = $query_record['postprocess']
			. (empty($query_record['postprocess']) ? '' : '~~')
			. "cat[$category]";
		
		/* note we need to re-escape fields from the $query_record that may contain ' 
		 * that could bring the strings here to a premature end! */ 
		$sql_query = "insert into saved_queries (
			query_name, 
			user, 
			corpus, 
			query_mode, 
			simple_query, 
			cqp_query,
			restrictions,
			subcorpus,
			postprocess,
			hits_left,
			time_of_query,
			hits,
			hit_texts,
			file_size,
			saved,
			save_name
			) values (
			'$newqname',
			'$username',
			'$corpus_sql_name',
			'{$query_record['query_mode']}',
			'".mysql_real_escape_string($query_record['simple_query'])."',
			'".mysql_real_escape_string($query_record['cqp_query'])."',
			'".mysql_real_escape_string($query_record['restrictions'])."',
			'".mysql_real_escape_string($query_record['subcorpus'])."',
			'".mysql_real_escape_string($new_pp_string)."',
			'". $query_record['hits_left'] . (empty($query_record['hits_left']) ? '' : '~') . $solution_count ."',
			" . time() . ",
			{$query_record['hits']},
			{$query_record['hit_texts']},
			" . cqp_file_sizeof($newqname) . ",
			1,
			'$newsavename'		
			)";
		do_mysql_query($sql_query);
	}
}




/** categorise-admin: delete the database, the cached query, and the record in saved_catqueries */
function categorise_delete_query()
{
	$qname = safe_qname_from_get();

	$result = do_mysql_query("select dbname from saved_catqueries where catquery_name='$qname'");
	list($dbname) = mysql_fetch_row($result);

	do_mysql_query("drop table if exists $dbname");
			
	delete_cached_query($qname);
	
	do_mysql_query("delete from saved_catqueries where catquery_name='$qname'");
}







function categorise_add_new_value()
{
	$qname = safe_qname_from_get();

	if (isset($_GET['newCategory']))
		$new_cat = mysql_real_escape_string($_GET['newCategory']);
	else
		exiterror_fullpage('Critical parameter "newCategory" was not defined!', __FILE__, __LINE__);
	
	if (! cqpweb_handle_check($new_cat))
		exiterror_fullpage('The category name you tried to add contains spaces or punctuation. '
							. 'Category labels can only contain unaccented letters, digits, and the underscore.');

	/* get the current list of categories */
	$category_list = catquery_list_categories($qname);
	
	/* adjust the category list */
	if (in_array($new_cat, $category_list))
		return;
	foreach($category_list as $i => $c)
		if ($c == 'other' || $c == 'unclear')
			unset($category_list[$i]);
	$category_list[] = $new_cat;
	$category_list[] = 'other';
	$category_list[] = 'unclear';
	
	$cat_list_string = implode('|', $category_list);
	
	$sql_query = "update saved_catqueries set category_list = '$cat_list_string' where catquery_name='$qname'";
	do_mysql_query($sql_query);
	
	/* and finish by ... */
	$dbname = catquery_find_dbname($qname);
	touch_db($dbname);
	touch_cached_query($qname);
}





/** categorise-admin: this function prints a page with a simple form for a new categorisation value to be entered */
function categorise_enter_new_value()
{
	global $css_path;

	$qname = safe_qname_from_get();


	/* before anything else */
	header('Content-Type: text/html; charset=utf-8');


	?>
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Categorise Query -- CQPweb</title>
	<?php
	echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
	?>
	<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
	
	</head>
	<body>
	<form action="redirect.php" method="get">
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">
					Add a category to the existing set of categorisation values for this query
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					&nbsp;<br/>
					Current categories:
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" >
					<em>
						<?php echo implode(', ', catquery_list_categories($qname)); ?>
					</em>
				</td>
			</tr>

			</tr>
			<tr>
				<td class="concordgrey">
					&nbsp;<br/>
					New category:
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" >
					&nbsp;<br/>
					<input type="text" name="newCategory" />
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center" colspan="2">
					<input type="submit" value="Submit" />
				</td>
			</tr>
		</table>
		<input type="hidden" name="qname" value="<?php echo $qname; ?>"/>
		<input type="hidden" name="redirect" value="categorise"/>
		<input type="hidden" name="categoriseAction" value="addNewValue"/>
		<input type="hidden" name="uT" value="y" />
	</form>
	
	<?php
	
	/* create page end HTML */
	print_footer();	
		
}




/**
 * This function prints a webpage enabling the user to enter their category names;
 * passing it an error argument affects the display in various ways,
 * but it will always produce a full webpage.
 */
function categorise_enter_categories($error = NULL)
{
	global $css_path;

	$qname = safe_qname_from_get();


	/* before anything else */
	header('Content-Type: text/html; charset=utf-8');

	/* if an error is specified, an error message is printed at the top, and the values from GET are re-printed */
	switch($error)
	{

		case 'no_name':
			$error_message = 'You have not entered a name for your query result! Please amend the settings below.';
			break;
		case 'bad_names':
			$error_message = 'Query names and categories can only contain letters, numbers and the underscore character' .
				' (&quot;_&quot;)! Please amend the settings below (an alternative has been suggested).';
			break;
		case 'no_cats':
			$error_message = 'You have not entered any categories! Please add some category names below.';
			break;
		case 'name_exists':
			$error_message = 'A categorised query with the name you specified already exists! Please choose a different name.';
			break;
		case 'cat_repeated':
			$error_message = 'You have entered the same category more than once! Please double-check your category names.';
			break;
	
		/* note that default includes "NULL", which is the norm */
		default:
			break;	
	}

	?>
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Categorise Query -- CQPweb</title>
	<?php
	echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
	?>
	
	</head>
	<body>
	<form action="redirect.php" method="get">
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">Categorise query results</th>
			</tr>
			<?php
			if (!empty($error_message))
				echo '<tr><td class="concorderror" colspan="2"><strong>Error!</strong><br/>' . $error_message . '</td></tr>';
			?>
			<tr>
				<td class="concordgrey">
					&nbsp;<br/>
					Please enter a name for this set of categories:
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					<input type="text" name="categoriseCreateName" size="34"
					<?php 
					if ($error !== NULL && isset($_GET['categoriseCreateName']))
						echo 'value="' . preg_replace('/\W/', '', $_GET['categoriseCreateName']) . '"';
					?>
					/>
				</td>
			</tr>
			<tr>
				<th class="concordtable">List category labels:</th>
				<th class="concordtable">Default category?</th>
			</tr>
		<?php
		for($i = 1 ; $i < 7 ; $i++)
		{
			$val = '';
			$selected = '';
			
			if ($error !== NULL && isset($_GET["cat_$i"]))
				$val = 'value="' . preg_replace('/\W/', '', $_GET["cat_$i"]) . '"';
			if ($error !== NULL && $_GET["defaultCat"] == $i)
				$selected = 'checked="checked"';
				
			echo "
			<tr>
				<td class=\"concordgeneral\" align=\"center\">
					<input type=\"text\" name=\"cat_$i\" size=\"34\" $val>
				</td>
				<td class=\"concordgeneral\" align=\"center\">
					<input type=\"radio\" name=\"defaultCat\" value=\"$i\" $selected?>
				</td>
			</tr>";
		}
		?>
			<tr>
				<td class="concordgeneral" align="center" colspan="2">
					<input type="submit" value="Submit" />
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					<strong>Instructions</strong>
					<br/>&nbsp;<br/>
					<ul>
						<li>
							Names can only contain letters, numbers and the underscore character (
							<strong>_</strong> )
						</li>
						<li>
							The categories <strong>Unclear</strong> and <strong>Other</strong>
							will be automatically added to the list
						</li>
						<li>
							Selecting a default category will mean that all hits will be automatically 
							set to this value. This can be useful if you expect most of the hits
							to belong to one particular category. However, it will mean that you 
							have to go through the <em>complete</em> set of concordances (and not only 
							the first x number of hits of a randomly ordered query result).
						</li>
						<li>
							You can add additional categories at any time.
						</li>
					</ul>
				</td>
			</tr>
		</table>
		<input type="hidden" name="qname" value="<?php echo $qname; ?>"/>
		<input type="hidden" name="redirect" value="categorise"/>
		<input type="hidden" name="categoriseAction" value="createQuery"/>
		<input type="hidden" name="uT" value="y" />
	</form>
	
	<?php
	
	/* create page end HTML */
	print_footer();

}
?>
