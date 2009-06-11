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









/**

	Format of the postprocess string:
	---------------------------------
	
	Postprocesses are separated by ~~
	
	Postprocesses are named as follows:
	
	coll	... collocating with
	sort	... a sort implemented using the "sort" program (can also thin)
	thin	... thinned using the "thin" function
	dist	... a reduction from the distribution page
	rand	... randomise order
	unrand  ... usually (but not always) a pseudo-value, it really means take out rand
	cat		... a particular "categorisation" has been applied
	
	Some of these have parameters. These are in the format:
	~~XXXX[A~B~C]~~
	
	coll[dbname~att~target~from~to]
		att=the mysql handle of the att the match is to be done on
		target=the match pattern
		from=the minimum dist the target can occur at
		to=the maximum dist the target can occur at
	(dbname is not saved cos not needed to retrieve a collocation-concordance from cache)
	
	sort[position~thin_tag~thin_tag_inv~thin_str~thin_str_inv]
		position=the position of the sort, in format +2, -1, +3, etc. Maximum: +/- 5.
		thin_tag=the tag that the sorted position must have (full string, not regex); or '.*' if any tag is allowed
		thin_tag_inv=1 if the specified tag is to be *excluded*, otherwise 0
		thin_str=the string that the sorted position must begin with (full string, not regex); or '.*' if any wordform is allowed
		thin_str_inv=1 if the specified starts-with-string is to be *excluded*, otherwise 0
		
		#note: the .* is NOT fed to mySQL, it is just a code that CQPweb will pick up on.
	
	thin[count~method]
		count=the number of queries REMAINING after it has been thinned
		method=r|n : r for "random reproducible", n for "random nonreproducible"
	
	dist[dbname~categorisation~class]
		categorisation=handle of the field that the distribution is being done over
		class=the handle that occurs in that field for the texts we want
	
	rand
		no parameters
	
	unrand
		no parameters
		
	cat[category]
		category=the label of the category to which the solutions in this query were manually assigned
		Note: this postprocess is always done by categorise-admin.inc.php, never here (so, no functions 
		or cases for it); all this library needs ot do is render it properly
		
	
	Postprocesses are listed in the order they were applied.
	
	When a query is "unrandomised", rand is removed from anywhere in its string,
	but "unrand" is only added if there has been a prior "sort".
	When a query is sorted using sort, rand and unrand are removed from anywhere in its string.

*/


/* class for descriptor object for a new postprocess operation */
//TODO update to PHP-5 object
class POSTPROCESS {
	/* this variable contains one of the 4-letter abbreviations of the different postprocess types */
	var $postprocess_type;
	
	/* boolean: true if the $_GET was parsed OK, false if not */
	var $i_parsed_ok;
	
	/* this stops the function "add to postprocess string" running more than once */
	/* unless its "override" is true */
	var $stored_postprocess_string;
	
	var $run_function_name;

	/* variables for collocation */
	var $colloc_db;
	var $colloc_dist_from;
	var $colloc_dist_to;
	var $colloc_att;
	var $colloc_target;
	
	/* variables for thin */
	var $thin_target_hit_count;
	var $thin_genuinely_random;
	
	/* variables for sort */
	var $sort_position;
	var $sort_thin_tag;
	var $sort_thin_tag_inv;
	var $sort_thin_str;
	var $sort_thin_str_inv;
	var $sort_remove_prev_sort;
	var $sort_pp_string_of_query_to_be_sorted;
	var $sort_db;
	var $sort_sort_thinning_sql_where;
	
	/* string - name of the function to use when the postprocess is being run */
	/* use postprocess_type as key */
	var $function_names = array(
		'coll' => 'run_postprocess_collocation',
		'thin' => 'run_postprocess_thin',
		'rand' => 'run_postprocess_randomise',
		'unrand' => 'run_postprocess_unrandomise',
		'sort' => 'run_postprocess_sort'
		);
	
	
	/* constructor - this reads things in from get (and gets rid of them) */
	function POSTPROCESS()
	{
		/* unless disproven below */
		$this->i_parsed_ok = true;
		
		/* all input sanitising is done HERE */
		switch($_GET['newPostP'])
		{
		case 'coll':
			$this->postprocess_type = 'coll';

			if ( ! isset(
				$_GET['newPostP_collocDB'],
				$_GET['newPostP_collocDistFrom'],
				$_GET['newPostP_collocDistTo'],
				$_GET['newPostP_collocAtt'],
				$_GET['newPostP_collocTarget']
				) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->colloc_db = preg_replace('/\W/', '', $_GET['newPostP_collocDB']);
			$this->colloc_dist_from = (int)$_GET['newPostP_collocDistFrom'];
			$this->colloc_dist_to = (int)$_GET['newPostP_collocDistTo'];
			$this->colloc_att = preg_replace('/\W/', '', $_GET['newPostP_collocAtt']);
			if (check_is_real_corpus_annotation($this->colloc_att) === false)
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->colloc_target = mysql_real_escape_string($_GET['newPostP_collocTarget']);
			break;
		
		
		case 'sort':
			$this->postprocess_type = 'sort';
			
			if ( ! isset( $_GET['newPostP_sortPosition']	) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->sort_position = (int)$_GET['newPostP_sortPosition'];

			$this->sort_thin_tag = mysql_real_escape_string($_GET['newPostP_sortThinTag']);
			if (empty($this->sort_thin_tag))
				$this->sort_thin_tag = '.*';
			$this->sort_thin_tag_inv = (bool)$_GET['newPostP_sortThinTagInvert'];
				

			$this->sort_thin_str = mysql_real_escape_string($_GET['newPostP_sortThinString']);
			if (empty($this->sort_thin_str))
				$this->sort_thin_str = '.*';
			$this->sort_thin_str_inv = (bool)$_GET['newPostP_sortThinStringInvert'];
			
			/* note that either the tag or the stirng used to restrict could include punctuation */
			/* or UTF8 characters: so only mysql sanitation is done for these two things. */
			$this->sort_remove_prev_sort = (empty($_GET['newPostP_sortRemovePrevSort']) ? false : true);		
			
			break;


		case 'thin':
			$this->postprocess_type = 'thin';
			
			if ( ! isset( $_GET['newPostP_thinReallyRandom'], $_GET['newPostP_thinTo'], $_GET['newPostP_thinHitsBefore'] ) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			
			$_GET['newPostP_thinTo'] = trim($_GET['newPostP_thinTo']);
			if (substr($_GET['newPostP_thinTo'], -1) == '%')
			{
				$thin_factor = str_replace('%', '', $_GET['newPostP_thinTo']) / 100;
				$this->thin_target_hit_count = round(((int)$_GET['newPostP_thinHitsBefore']) * $thin_factor, 0);
			}
			else
			{
				$this->thin_target_hit_count = (int)$_GET['newPostP_thinTo'];
			}
// add a check here for thinning to "more hits than we originally had"			
			$this->thin_genuinely_random = ($_GET['newPostP_thinReallyRandom'] == 1) ? true : false;
			
			break;
			
			
			
			
		case 'dist':
			$this->postprocess_type = 'dist';
			break;
		case 'rand':
			$this->postprocess_type = 'rand';
			break;
		case 'unrand':
			$this->postprocess_type = 'unrand';
			break;
		
	

		default:
			$this->i_parsed_ok = false;
			break;
		}
		/* clear these things out of GET so they are not passed on */
		foreach ($_GET as $key => &$val)
			if (substr($key, 0, 8) == 'newPostP')
				unset($_GET[$key]);
	}
	
	
	/* general functions */
	function add_to_postprocess_string($string_to_work_on, $override=false)
	{
		if (isset ($this->stored_postprocess_string) && $override == false)
			return $this->stored_postprocess_string;
	
//		$rand_taken_off = false;
//		if (substr($string_to_work_on, -6) == '~~rand')
//		{
//			$rand_taken_off = true;
//			$string_to_work_on = substr($string_to_work_on, 0, strlen($string_to_work_on)-6);
//		}
		// is this needed? there certainly doens't seem to be a "put back on" in the code
	
		switch($this->postprocess_type)
		{
		case 'coll':
			$string_to_work_on .= "~~coll[$this->colloc_db~$this->colloc_att~$this->colloc_target~$this->colloc_dist_from~$this->colloc_dist_to]";
			break;
		
		case 'thin':
			$r_or_n = ($this->thin_genuinely_random ? 'n' : 'r');
			$string_to_work_on .= "~~thin[$this->thin_target_hit_count~$r_or_n]";
			break;
			
		case 'rand':
			$string_to_work_on = preg_replace('/(~~)?unrand\z/', '', $string_to_work_on);
			$string_to_work_on .= '~~rand';
			break;
			
		case 'unrand':
			/* remove "rand" */
			if ($string_to_work_on == 'rand' || $string_to_work_on == 'unrand')
				$string_to_work_on = '';
			else
			{
				$string_to_work_on = str_replace('~~unrand', '', $string_to_work_on);
				$string_to_work_on = str_replace('unrand~~', '', $string_to_work_on);
				$string_to_work_on = str_replace('~~rand', '', $string_to_work_on);
				$string_to_work_on = str_replace('rand~~', '', $string_to_work_on);
			}
			/* but --DON'T-- add ~~unrand unless there is a "sort" somewhere in the string */
			if (strpos($string_to_work_on, 'sort[') !== false)
				$string_to_work_on .= '~~unrand';
			break;
		
		/* case sort : remove the immediately previous postprocess if it is '(un)rand' or a sort */
		case 'sort':

			if ($string_to_work_on == 'rand' || substr($string_to_work_on, -6) == '~~rand')
			{
				$string_to_work_on = substr($string_to_work_on, 0, -6);
			}
			else if ($string_to_work_on == 'unrand' ||substr($string_to_work_on, -8) == '~~unrand')
			{
				$string_to_work_on = substr($string_to_work_on, 0, -8);
			}
			else if ($this->sort_remove_prev_sort)
			{
				$string_to_work_on = preg_replace('/(~~)?sort\[[^\]]+\]\Z/', '', $string_to_work_on);
			}
			
			/* at this point, "string to work on" is the pp string of query that needs to be sorted */
			$this->sort_pp_string_of_query_to_be_sorted = ($string_to_work_on === NULL ? '' : $string_to_work_on);

				
			$mybool1 = (int)$this->sort_thin_tag_inv;
			$mybool2 = (int)$this->sort_thin_str_inv;
			$string_to_work_on .= "~~sort[$this->sort_position~$this->sort_thin_tag~$mybool1~$this->sort_thin_str~$mybool2]";
			break;

		
		default:
			return false;
		}
				
		if (substr($string_to_work_on, 0, 2) == '~~')
			$string_to_work_on = substr($string_to_work_on, 2);

		$this->stored_postprocess_string = $string_to_work_on;
		return $string_to_work_on;
	}
	
	
	function get_stored_postprocess_string()
	{
		return $this->stored_postprocess_string;
	}
	
	
	function parsed_ok()
	{
		return $this->i_parsed_ok;
	}
	
	function get_run_function_name()
	{
		return $this->function_names[$this->postprocess_type];
	}
	
	
	
	/* functions for collocations */
	
	function colloc_sql_for_queryfile($file)
	{
		if (!$this->colloc_sql_capable())
			return false;
	
		return "select beginPosition, endPosition
			into outfile '$file'
			from {$this->colloc_db}
			where {$this->colloc_att} = '{$this->colloc_target}'
			and dist between {$this->colloc_dist_from} and {$this->colloc_dist_to}";
	}
	
	function colloc_sql_count_remaining_hits()
	{
		if (!$this->colloc_sql_capable())
			return false;
	
		return "select count(*)
			from {$this->colloc_db}
			where {$this->colloc_att} = '{$this->colloc_target}'
			and dist between {$this->colloc_dist_from} and {$this->colloc_dist_to}";
	}

	function colloc_sql_capable()
	{
		return ( isset($this->colloc_db, $this->colloc_dist_from, 
			$this->colloc_dist_to, $this->colloc_att, $this->colloc_target) );
	}
	
	
	
	
	/* functions for sorting */
	
	/* the "orig" query record is needed because the DB is creaed for the orig query */
	function sort_set_dbname($orig_query_record)
	{			
		/* search the db list for a db whose parameters match those of the query we are working with  */
		/* if it doesn't exist, we need to create one */
		$db_record = check_dblist_parameters('sort', $orig_query_record['cqp_query'],
						$orig_query_record['restrictions'], $orig_query_record['subcorpus'],
						$this->sort_pp_string_of_query_to_be_sorted);
		/* note, instead of the postprocess string from the query record (which is the postprocesss  */
		/* string we are WORKING TOWARDS) we use the postprocess string of the query we need to work */
		/* from which was recorded in this object when we ran add_to_postprocess_string */


		if ($db_record === false)
		{
			$this->sort_db = create_db('sort', $orig_query_record['query_name'], 
						$orig_query_record['cqp_query'], $orig_query_record['restrictions'], 
						$orig_query_record['subcorpus'], $this->sort_pp_string_of_query_to_be_sorted);
		}
		else
		{
			touch_db($db_record['dbname']);
			$this->sort_db = $db_record['dbname'];
		}
			
	}

	/* the "orig" query record is needed to be passed to sort_set_dbname */
	function sort_sql_for_queryfile($file, $orig_query_record)
	{			
		$this->sort_set_dbname($orig_query_record);
		if (!$this->sort_sql_capable())
			return false;
		
		/* use the sort settings to create the where and order by clause */
		
		/* the variable "sort_position_sql" is beforeX, afterX, or node */
		if ($this->sort_position < 0)
		{
			$sort_position_sql = 'before' . (-1 * $this->sort_position);
			for ($i = (-1 * $this->sort_position) ; $i < 6 ; $i++)
				$extra_sort_pos_sql .= ", before$i";
//if ($sortpos == 5)$reverseOrderType = "after1" ;
		}
		else if ($this->sort_position == 0)
		{
			$sort_position_sql = 'node';
			$extra_sort_pos_sql = ", after1, after2, after3, after4, after5";
		}
		else if ($this->sort_position > 0)
		{
			$sort_position_sql .= 'after' . $this->sort_position;
			for ($i = $this->sort_position ; $i < 6 ; $i++)
				$extra_sort_pos_sql .= ", after$i";
//$reverseOrderType = "before1" if ($sortpos == 5);
		}
			

		
		
		/* first, what are we actually sorting on? */

		
		/* do we have a tag restriction? */
		if ($this->sort_thin_tag != '.*')
		{
			$this->sort_thinning_sql_where = "where tag$sort_position_sql " 
												. ($this->sort_thin_tag_inv ? '!' : '')
												. "= '$this->sort_thin_tag'";
		}
		
		/* do we have a string restriction? */
		if ($this->sort_thin_str != '.*')
		{
			$where_clause_temp = "$sort_position_sql " 
								. ($this->sort_thin_str_inv ? 'NOT ' : '')
								. "LIKE '$this->sort_thin_str%'";
			if (isset($this->sort_thinning_sql_where))
				$this->sort_thinning_sql_where .= ' and ' . $where_clause_temp;
			else
				$this->sort_thinning_sql_where = 'where ' . $where_clause_temp;
		}		
			
		return "select beginPosition, endPosition
			into outfile '$file' 
			from {$this->sort_db} 
			$this->sort_thinning_sql_where
			order by $sort_position_sql  $extra_sort_pos_sql  ";

	}
	
	function sort_sql_count_remaining_hits()
	{
		if (!$this->sort_sql_capable())
			return false;
		if (! isset($this->sort_thinning_sql_where))
			$this->sort_sql_for_queryfile;

		return "select count(*)
			from {$this->sort_db}
			$this->sort_thinning_sql_where";
	}


	function sort_sql_capable()
	{
//	show_var($this->stored_postprocess_string);
		$d = array($this->sort_position, $this->sort_thin_tag, $this->sort_thin_tag_inv, 
				$this->sort_thin_str, $this->sort_thin_str_inv, $this->sort_remove_prev_sort, 
				$this->sort_db, $this->sort_pp_string_of_query_to_be_sorted);
//		show_var($d);		
		return ( isset($this->sort_position, $this->sort_thin_tag, $this->sort_thin_tag_inv, 
			$this->sort_thin_str, $this->sort_thin_str_inv, $this->sort_remove_prev_sort, 
			$this->sort_db, $this->sort_pp_string_of_query_to_be_sorted) );

	}
	
	
	function sort_get_active_settings()
	{
		/* don't know if this is actually needed at all, but does not hurt to have */
		return array(
				'sort_position' 		=> $this->sort_position,
				'sort_thin_tag' 		=> $this->sort_thin_tag,
				'sort_thin_tag_inv' 	=> $this->sort_thin_tag_inv,
				'sort_thin_str' 		=> $this->sort_thin_str,
				'sort_thin_str_inv' 	=> $this->sort_thin_str_inv
				);
	}



} /* end of class POSTPROCESS */











/* run_postprocess_ functions have the following format: */
/* parameters:	a cache-record of the new query and a descriptor object for the postprocess */
/* return:   	the cache record it was passed, with a new 'query_name' and 'hits_left' */
function run_postprocess_collocation($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $mysql_link;
	global $cqp_tempdir;
	global $username;
	
	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;

	/* first, write a "dumpfile" to temporary storage */
	$tempfile  = "/$cqp_tempdir/temp_coll_$new_qname.tbl";
	$tempfile2 = "/$cqp_tempdir/temp_coll_$new_qname.undump";

	$sql_query = $descriptor->colloc_sql_for_queryfile($tempfile);

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);

	/* now, work out how many hits were in it */
	$sql_query = $descriptor->colloc_sql_count_remaining_hits($tempfile);

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

	list($solutions_remaining) = mysql_fetch_row($result);
	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $solutions_remaining;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	unset($result);

	if ($solutions_remaining > 0)
	{
		/* prepend number of hits to output file */
		exec("echo $solutions_remaining | cat - '$tempfile' > '$tempfile2'");
		unlink($tempfile);

		/* load to CQP as a new query, and save */
		$cqp->execute("undump $new_qname < '$tempfile2'");
		$cqp->execute("save $new_qname");
		
		/* get the size of the new query */
		$cache_record['file_size'] = cqp_file_sizeof($new_qname);

		unlink($tempfile2);

		/* put this newly-created query in the cache */

		$insert = "insert into saved_queries (query_name) values ('$new_qname')";

		$insert_result = mysql_query($insert, $mysql_link);
		if ($insert_result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		unset($insert_result);

		update_cached_query($cache_record);

	}
	else
		say_sorry_postprocess();
		/* which exits the program */

	return $cache_record;
}


function run_postprocess_sort($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $mysql_link;
	global $cqp_tempdir;
	global $username;

	$orig_cache_record = $cache_record;

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;

	/* first, write a "dumpfile" to temporary storage */
	$tempfile  = "/$cqp_tempdir/temp_sort_$new_qname.tbl";
	$tempfile2 = "/$cqp_tempdir/temp_sort_$new_qname.undump";

	$sql_query = $descriptor->sort_sql_for_queryfile($tempfile, $orig_cache_record);

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($result);

	/* now, work out how many hits were in it */
	$sql_query = $descriptor->sort_sql_count_remaining_hits($tempfile);

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

	list($solutions_remaining) = mysql_fetch_row($result);
	if ($descriptor->sort_remove_prev_sort)
	{
		/* we need to remove the previous "hits left" because a sort has been undone */
		$cache_record['hits_left'] = preg_replace('/~?\d+\Z/', '', $cache_record['hits_left']);
	}
	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $solutions_remaining;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	unset($result);

	if ($solutions_remaining > 0)
	{
		/* prepend number of hits to output file */
		exec("echo $solutions_remaining | cat - '$tempfile' > '$tempfile2'");
		unlink($tempfile);

		/* load to CQP as a new query, and save */
		$cqp->execute("undump $new_qname < '$tempfile2'");
		$cqp->execute("save $new_qname");
		
		/* get the size of the new query */
		$cache_record['file_size'] = cqp_file_sizeof($new_qname);

		unlink($tempfile2);

		/* put this newly-created query in the cache */

		$insert = "insert into saved_queries (query_name) values ('$new_qname')";

		$insert_result = mysql_query($insert, $mysql_link);
		if ($insert_result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		unset($insert_result);

		update_cached_query($cache_record);
	
	}
	else
		say_sorry_postprocess();
		/* which exits the program */

	return $cache_record;
}





function run_postprocess_randomise($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $mysql_link;
	global $username;
	

	$old_qname = $cache_record['query_name'];	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	/* note for randomisation, "hits left" doesn't need changing */

	/* actually randomise */
	$cqp->execute("$new_qname = $old_qname");
	$cqp->execute("sort $new_qname randomize 42");
	$cqp->execute("save $new_qname");


	/* put this newly-created query in the cache */
	$insert = "insert into saved_queries (query_name) values ('$new_qname')";

	$insert_result = mysql_query($insert, $mysql_link);
	if ($insert_result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($insert_result);

	update_cached_query($cache_record);

	return $cache_record;
}

function run_postprocess_unrandomise($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $mysql_link;
	global $username;
	

	$old_qname = $cache_record['query_name'];	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	/* note for unrandomisation, "hits left" doesn't need changing */

	/* actually unrandomise */
	$cqp->execute("$new_qname = $old_qname");
	$cqp->execute("sort $new_qname");
	$cqp->execute("save $new_qname");


	/* put this newly-created query in the cache */
	$insert = "insert into saved_queries (query_name) values ('$new_qname')";

	$insert_result = mysql_query($insert, $mysql_link);
	if ($insert_result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($insert_result);

	update_cached_query($cache_record);

	return $cache_record;
}


function run_postprocess_thin($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $mysql_link;
	global $username;

	$old_qname = $cache_record['query_name'];	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $descriptor->thin_target_hit_count;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	/* actually thin */
	$cqp->execute("$new_qname = $old_qname");
	if ($descriptor->thin_genuinely_random)
		$cqp->execute("randomize 42");
	$cqp->execute("reduce $new_qname to $descriptor->thin_target_hit_count");
	$cqp->execute("save $new_qname");
	
	/* get the size of the new query */
	$cache_record['file_size'] = cqp_file_sizeof($new_qname);
	
	/* put this newly-created query in the cache */
	$insert = "insert into saved_queries (query_name) values ('$new_qname')";

	$insert_result = mysql_query($insert, $mysql_link);
	if ($insert_result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	unset($insert_result);

	update_cached_query($cache_record);

	return $cache_record;
}




////////////////////////////////////!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!1
/// very important note -- this won't work as well as bncWeb in the (sole) case where randomisation is applied *after* collocation thinning
/// bncWeb manages to keep the highlight, but here rand will get in the way of seeing coll
///////////// unless ... the coll output could be randomised using the same algorithm so it will be replicable????
// worry about this later
/* returns an array of highlight positions matching the postprocess string specified */
/* this will include a dbname, which is how the function knows which data to work on */
/* this function sets its third output to true if tags are to be shown in highlight */
/* otherwise it is set to false */
function get_highlight_position_table($qname, $postprocess_string, &$show_tags_in_highlight)
{
	global $mysql_link;
	
	if ($postprocess_string == '')
		return false;

	$highlight_process = end(explode('~~', $postprocess_string));

	if (strpos($highlight_process, '[') !== false)
	{
		list($process_name, $argstring) = explode('[', str_replace(']', '', $highlight_process));
		$args = explode('~', $argstring);
	}
	else
		$process_name = $highlight_process;

	/* variables which may be changed */		
	$coll_order_by = '';
	$sql_query_from_coll = false;

	switch($process_name)
	{
	/* cases where there is a highlight */
	case 'sort':
		/* find out the size of the query: create an array with the sort position that many times */
		if (($local_cache_record = check_cache_qname($qname)) === false)
			return false;
			
		for ($i = 0  ; $i < $local_cache_record['hits_left'] ; $i++)
			$highlight_positions[$i] = $args[0];
		
		/* if a tag-restriction is set, show tags for the sort-word */
		$show_tags_in_highlight = ($args[1] != '.*');
		
		/* return it straight away without going through SQL stuff at end of this function */
		return $highlight_positions;
		
	case 'rand':
// take the rand off
// is what's left a coll?
// if not, return false
// if it is, put its args into args; set a flag to say that a sort using the cqp algorithm must be applied to the array that we get back
// $sql_query_from_coll = true;
		break;

	case 'unrand':
// take the unrand off
// is what's left a coll?
// if not, return false
// if it is, put its args into args; $coll_order_by = 'order by beginPosition'
// $sql_query_from_coll = true;
		break;


	case 'coll':
		$sql_query_from_coll = true;
		break;
	
	/* case dist, case thin, (or...) an additional rand, a syntax error, or anything else */	
	default:
		return false;
	}

	/* this is out here in an IF so that three different cases can access it */
	if ($sql_query_from_coll)
	{
		if (count($args) != 5)
			return false;
		$sql_query = "select dist from {$args[0]}
			where {$args[1]} = '{$args[2]}'     and        dist between {$args[3]} and {$args[4]}
			$coll_order_by";
	}
	
	if (isset($sql_query))
	{
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);

		$highlight_positions = array();
		while ( ($r = mysql_fetch_row($result)) !== false )
			$highlight_positions[] = $r[0];
		
		return $highlight_positions;
	}
	else
		return false;
}




// so useful, it should prob be in library
function postprocess_string_to_description($postprocess_string, $hits_string)
{
	global $corpus_main_script_is_r2l;
	/* bdo tags ensure that l-to-r goes back to normal after an Arabic (etc) string */
	$bdo_tag1 = ($corpus_main_script_is_r2l ? '<bdo dir="ltr">' : '');
	$bdo_tag2 = ($corpus_main_script_is_r2l ? '</bdo>' : '');

	$description = '';
	
	$process = explode('~~', $postprocess_string);
	
	$hit_array = explode('~', $hits_string);
	$i = 0;
	
	$annotations = get_corpus_annotations();
	
	foreach($process as $p)
	{
		$description .= ', ';
		
		if (strpos($p, '[') !== false)
		{
			list($this_process, $argstring) = explode('[', str_replace(']', '', $p));
			$args = explode('~', $argstring);
		}
		else
			$this_process = $p;

		
		switch ($this_process)
		{
			case 'sort':
			
				$description .= 'sorted on <em>'
					. ($args[0] == 0 ?  "node word"
						: ($args[0] > 0 ? "position +{$args[0]}" 
							: "position {$args[0]}") )
					.  '</em> ';	
				
				if ($args[3] != '.*')
				{
					$description .= ($args[4] == 1 ? 'not ' : '')
						. 'starting with <em>'
						. $args[3]
						. '-</em> ';
				}

				if ($args[1] != '.*')
				{
					$description .= 'with tag-restriction <em>'
						. ($args[2] == 1 ? 'exclude ' : '')
						. $args[1]
						. '</em> ';
				}
				
				$description .= $bdo_tag1 . '(' . make_thousands($hit_array[$i]) . ' hits)' . $bdo_tag2;
				$i++;
				
				break;
				
			case 'coll':
				$att_id = ($args[1] == 'word' ? '' : $annotations[$args[1]]);
				$description .= "collocating with $att_id <em>{$args[2]}</em> $bdo_tag1("
					. make_thousands($hit_array[$i]) . ' hits)'. $bdo_tag2;
				$i++;
				break;
				
			case 'rand':
				$description .= 'ordered randomly';
				break;
				
			case 'unrand':
				$description .= 'sorted into corpus order';
				break;
				
			case 'thin':
				$method = ($args[1] == 'n' ? 'random selection (non-reproducible)' : 'random selection');
				$count = make_thousands($hit_array[$i]);
				$description .= "thinned with method <em>$method</em> to $count hits";
				$i++;
				break;
			
			case 'cat':
				$description.= "manually categorised as &ldquo;{$args[0]}&rdquo; ("
					. make_thousands($hit_array[$i]) . " hits)";
				$i++;
				break;
				
			default:
				/* malformed postprocess string; so add an error to the return */
				$description .= '????';
				break;
		}
	}

	return $description;
}





?>