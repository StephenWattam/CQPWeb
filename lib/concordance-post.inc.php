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

	Format of the postprocess string:
	---------------------------------
	
	Postprocesses are separated by ~~
	
	Postprocesses are named as follows:
	
	coll	... collocating with
	sort	... a sort implemented using the "sort" program (can also thin)
	thin	... thinned using the "thin" function
	dist	... a reduction from the distribution page to a class of texts
	text	... a reduction from the distribution page to a specified text
	rand	... randomise order
	unrand  ... usually (but not always) a pseudo-value, it really means take out rand
	cat		... a particular "categorisation" has been applied
	item	... a particular item was selected on the "frequency distribution" page aka "item thinning"
	custom  ... a custom postprocess was run, doing "something".
	
	
	Some of these have parameters. These are in the format:
	~~XXXX[A~B~C]~~
	
	coll[dbname~att~target~from~to~tagfilter]
		dbname=the database used for the collocation
		att=the mysql handle of the att the match is to be done on
		target=the match pattern
		from=the minimum dist the target can occur at
		to=the maximum dist the target can occur at
		tagfilter=the regex filter applied for primary-att of collocate, if any
	
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
		When the method is "n", it is always followed by an instance identifier. This ensures that
		this method can never be matched in the cache.
	
	dist[categorisation~class]
		categorisation=handle of the field that the distribution is being done over
		class=the handle that occurs in that field for the texts we want
		
	text[target_text_id]
		target_text_id -- what it says on the tin!
	
	rand
		no parameters
	
	unrand
		no parameters
		
	cat[category]
		category=the label of the category to which the solutions in this query were manually assigned
		Note: this postprocess is always done by categorise-admin.inc.php, never here (so, no functions 
		or cases for it); all this library needs ot do is render it properly
	
	item[form~tag]
		EITHER of these can be an empty string.
		
	custom[class]
		The name of the class that did the postprocessing is stored here. The class will be queried for
		a description!
	
	Postprocesses are listed in the order they were applied.
	
	When a query is "unrandomised", rand is removed from anywhere in its string,
	but "unrand" is only added if there has been a prior "sort".
	When a query is sorted using sort, rand and unrand are removed from anywhere in its string.

*/


/* class for descriptor object for a new postprocess operation */
class POSTPROCESS {
	
	/* all variables are public for backward compatability -- this was originally a PHP4 object */
	/* but many SHOULD be private */
	
	/* this variable contains one of the lowercase abbreviations of the different postprocess types */
	private $postprocess_type;
	
	/* boolean: true if the $_GET was parsed OK, false if not */
	private $i_parsed_ok;
	
	/* this stops the function "add to postprocess string" running more than once,
	 * unless its "override" is true */
	public $stored_postprocess_string;
	
	public $run_function_name;

	/* variables for collocation */
	public $colloc_db;
	public $colloc_dist_from;
	public $colloc_dist_to;
	public $colloc_att;
	public $colloc_target;
	public $colloc_tag_filter;
	
	/* variables for thin */
	public $thin_target_hit_count;
	public $thin_genuinely_random;
	
	/* variables for sort */
	public $sort_position;
	public $sort_thin_tag;
	public $sort_thin_tag_inv;
	public $sort_thin_str;
	public $sort_thin_str_inv;
	public $sort_remove_prev_sort;
	public $sort_pp_string_of_query_to_be_sorted;
	public $sort_db;
	public $sort_thinning_sql_where;
	
	/* variables for item-thinning */
	public $item_form;
	public $item_tag;
	
	/* variables for distribution-thinning */
	public $dist_db;
	public $dist_categorisation_handle;
	public $dist_class_handle;
	
	/* variables for text-distribution-thinning */
	public $text_target_id;
	
	/* variables for custom postprocesses */
	public $custom_class;
	
	/** 
	 * string - name of the function to use when the postprocess is being run.
	 * Use postprocess_type as key into the array. 
	 */
	private $function_names = array(
		'coll' => 'run_postprocess_collocation',
		'thin' => 'run_postprocess_thin',
		'rand' => 'run_postprocess_randomise',
		'unrand' => 'run_postprocess_unrandomise',
		'sort' => 'run_postprocess_sort',
		'item' => 'run_postprocess_item',
		'dist' => 'run_postprocess_dist',
		'text' => 'run_postprocess_text',
		'custom' => 'run_postprocess_custom'
		);
	
	
	/** Constructor - this reads things in from GET (and gets rid of them) */
	function __construct()
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
				$_GET['newPostP_collocTarget'],
				$_GET['newPostP_collocTagFilter']
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
			$this->colloc_tag_filter = mysql_real_escape_string($_GET['newPostP_collocTagFilter']);
			/* it should be safe to real-escape this, even though it may be a regex, because there
			 * is no metacharacter meaning for ' or ". */ 
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
			
			/* empty() checks for a range of nonsensical things: '', '0', etc. */
			if ( ! isset( $_GET['newPostP_thinReallyRandom'], $_GET['newPostP_thinTo'], $_GET['newPostP_thinHitsBefore'] )
				|| empty($_GET['newPostP_thinTo'])  )
			{
				$this->i_parsed_ok = false;
				return;
			}
			
			$_GET['newPostP_thinTo'] = trim($_GET['newPostP_thinTo']);
			if (substr($_GET['newPostP_thinTo'], -1) == '%')
			{
				$thin_factor = str_replace('%', '', $_GET['newPostP_thinTo']) / 100;
				
				/* check for insane percentage values */
				if ($thin_factor >= 1)
				{
					$this->i_parsed_ok = false;
					return;
				}
				
				$this->thin_target_hit_count = round(((int)$_GET['newPostP_thinHitsBefore']) * $thin_factor, 0);
			}
			else
			{
				$this->thin_target_hit_count = (int)$_GET['newPostP_thinTo'];
				
				/* a check for thinning to "more hits than we originally had" */			
				if ($this->thin_target_hit_count >= (int)$_GET['newPostP_thinHitsBefore'])
				{
					$this->i_parsed_ok = false;
					return;
				}				
			}
			
			$this->thin_genuinely_random = ($_GET['newPostP_thinReallyRandom'] == 1) ? true : false;
			
			break;

			
		case 'item':
			$this->postprocess_type = 'item';
			/* we only need one out of form and tag */
			if ( empty($_GET['newPostP_itemForm']) && empty($_GET['newPostP_itemTag']))
			{
				$this->i_parsed_ok = false;
				return;
			}
			
			$this->item_form = (isset($_GET['newPostP_itemForm']) ? mysql_real_escape_string($_GET['newPostP_itemForm']) : '');
			$this->item_tag = (isset($_GET['newPostP_itemTag']) ? mysql_real_escape_string($_GET['newPostP_itemTag']) : '');
	
			break;

			
			
		case 'dist':
			$this->postprocess_type = 'dist';

			if ( empty($_GET['newPostP_distCateg']) || empty($_GET['newPostP_distClass']))
			{
				$this->i_parsed_ok = false;
				return;
			}
			
			$this->dist_categorisation_handle = mysql_real_escape_string($_GET['newPostP_distCateg']);
			$this->dist_class_handle = mysql_real_escape_string($_GET['newPostP_distClass']);
			
			break;
			
		case 'text':
			$this->postprocess_type = 'text';
			if ( empty($_GET['newPostP_textTargetId']) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->text_target_id = mysql_real_escape_string($_GET['newPostP_textTargetId']);
			break;	
		
		case 'rand':
			$this->postprocess_type = 'rand';
			break;
		case 'unrand':
			$this->postprocess_type = 'unrand';
			break;
		
		default:
			/* it might be a custom postprocess */
			if (substr($_GET['newPostP'], 0, 11) == 'CustomPost:')
			{
				$this->custom_class = preg_replace('/\W/', '', substr($_GET['newPostP'], 11));
				$this->postprocess_type = 'custom';
				$record = retrieve_plugin_info($this->custom_class);
				$this->custom_obj = new $this->custom_class($record->path);
				break;
			}

			/* no, it's not a custom process - it's just a bad value. */
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
		global $instance_name;
		
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
			$string_to_work_on .= "~~coll[$this->colloc_db~$this->colloc_att~$this->colloc_target~"
				. "$this->colloc_dist_from~$this->colloc_dist_to~$this->colloc_tag_filter]";
			break;
		
		case 'thin':
			$r_or_n = ($this->thin_genuinely_random ? ('n' . $instance_name) : 'r');
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

		case 'item':
			$string_to_work_on .= "~~item[$this->item_form~$this->item_tag]";
			break;
		
		case 'dist':
			$string_to_work_on .= "~~dist[$this->dist_categorisation_handle~$this->dist_class_handle]";
			break;
		
		case 'text':
			$string_to_work_on .= "~~text[$this->text_target_id]";
			break;
		
		case 'custom':
			$string_to_work_on .= "~~custom[$this->custom_class]";
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
	
	function colloc_sql_for_queryfile()
	{
		if (!$this->colloc_sql_capable())
			return false;

		return "SELECT beginPosition, endPosition
			FROM {$this->colloc_db}
			WHERE {$this->colloc_att} = '{$this->colloc_target}'
			"  . $this->colloc_tag_filter_clause() . "
			AND dist BETWEEN {$this->colloc_dist_from} AND {$this->colloc_dist_to}";
			//TODO: does this need an order by like dist does, to make sure results are in corpus order?
	}
	
	function colloc_tag_filter_clause()
	{
		/* see also */
		if (empty($this->colloc_tag_filter))
			return '';
		
		$is_regex = (bool)preg_match('/\W/', $this->colloc_tag_filter); 
		$op = ($is_regex ? 'REGEXP' : '=');
		$filter = ($is_regex ? regex_add_anchors($this->colloc_tag_filter) : $this->colloc_tag_filter);
		$tag_filter_att = get_corpus_metadata('primary_annotation');
		
		return "AND $tag_filter_att $op '{$filter}'";
	}

/*
function colloc_tagclause_from_filter($dbname, $att_for_calc, $primary_annotation, $tag_filter)
{

	/* there may or may not be a primary_annotation filter; $tag_filter is from _GET, so check it * /
	if (isset($tag_filter) && $tag_filter != false && $att_for_calc != $primary_annotation)
	{
		/* as of v2.11, tag restrictions are done with REGEXP, not = as the operator 
		 * if there are non-Word characters in the restriction; since tags usually
		 * are alphanumeric, defaulting to = may save procesing time.
		 * As with CQP, anchors are automatically added. * /
		if (preg_match('/\W/', $tag_filter))
		{
			$tag_filter = preg_replace('/^\^/', '', $tag_filter);
			$tag_filter = preg_replace('/^\\A/', '', $tag_filter);
			$tag_filter = preg_replace('/\$$/', '', $tag_filter);
			$tag_filter = preg_replace('/\\[Zz]$/', '', $tag_filter);
			$tag_filter = '^' . $tag_filter . '$';
			$tag_clause_operator = 'REGEXP';
		}
		else
			$tag_clause_operator = '=';
		
		/* tag filter is set and applies to a DIFFERENT attribute than the one being calculated * /
		
		return "and $dbname.$primary_annotation $tag_clause_operator '"
			. mysql_real_escape_string($tag_filter)
			. "' ";
	}
	else
		return '';
}*/


	function colloc_sql_capable()
	{
		return ( isset(	$this->colloc_db, 
						$this->colloc_dist_from, 
						$this->colloc_dist_to, 
						$this->colloc_att, 
						$this->colloc_target
					) );
	}
	
	
	
	
	/* functions for sorting */
	
	/* the "orig" query record is needed because the DB is created for the orig query */
	function sort_set_dbname($orig_query_record)
	{			
		/* search the db list for a db whose parameters match those of the query we are working with  */
		/* if it doesn't exist, we need to create one */
		$db_record = check_dblist_parameters('sort', $orig_query_record['cqp_query'],
						$orig_query_record['restrictions'], $orig_query_record['subcorpus'],
						$this->sort_pp_string_of_query_to_be_sorted);
		/* note, instead of the postprocess string from the query record (which has not been edited by the */
		/* add_to_postprocess_string function) we use the postprocess string of the query we need to work */
		/* from -- which was recorded in this object when we ran add_to_postprocess_string */


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
	function sort_sql_for_queryfile($orig_query_record)
	{			
		$this->sort_set_dbname($orig_query_record);
		if (!$this->sort_sql_capable())
			return false;

		/* use the sort settings to create the where and order by clause */
		
		$extra_sort_pos_sql = '';
		
		/* the variable "sort_position_sql" is beforeX, afterX, or node */
		if ($this->sort_position < 0)
		{
			$sort_position_sql = 'before' . (-1 * $this->sort_position);
			for ($i = (-1 * $this->sort_position) ; $i < 6 ; $i++)
				$extra_sort_pos_sql .= ", before$i COLLATE utf8_general_ci ";
		}
		else if ($this->sort_position == 0)
		{
			$sort_position_sql = 'node';
			$extra_sort_pos_sql = ", after1 COLLATE utf8_general_ci"
				. ", after2 COLLATE utf8_general_ci"
				. ", after3 COLLATE utf8_general_ci"
				. ", after4 COLLATE utf8_general_ci"
				. ", after5 COLLATE utf8_general_ci";
		}
		else if ($this->sort_position > 0)
		{
			$sort_position_sql = 'after' . $this->sort_position;
			for ($i = $this->sort_position ; $i < 6 ; $i++)
				$extra_sort_pos_sql .= ", after$i COLLATE utf8_general_ci";
		}
			

		
		/* first, what are we actually sorting on? */

		$this->sort_thinning_sql_where = '';
		
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
			if (!empty($this->sort_thinning_sql_where))
				$this->sort_thinning_sql_where .= ' and ' . $where_clause_temp;
			else
				$this->sort_thinning_sql_where = 'where ' . $where_clause_temp;
		}		
			
		return "SELECT beginPosition, endPosition
			FROM {$this->sort_db} 
			{$this->sort_thinning_sql_where}
			ORDER BY $sort_position_sql COLLATE utf8_general_ci  $extra_sort_pos_sql ";
			/* note:
			 * we always use utf8_general_ci for the actual sorting,
			 * even if the collation of the sort DB is actually utf8_bin 
			 * (for purposes of frequency breakdown, restriction matching etc);
			 * see also the creation of $extra_sort_pos_sql above
			 */

	}
	


	function sort_sql_capable()
	{
		/* the debug version: 
		show_var($this->stored_postprocess_string);
		$d = array($this->sort_position, $this->sort_thin_tag, $this->sort_thin_tag_inv, 
				$this->sort_thin_str, $this->sort_thin_str_inv, $this->sort_remove_prev_sort, 
				$this->sort_db, $this->sort_pp_string_of_query_to_be_sorted);
		show_var($d);
		*/
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
	
	
	
	/* "item" functions ; note the "item" postprocess re-uses some of the sort methods too */ 
	
	function item_sql_for_queryfile($orig_query_record)
	{
		$this->sort_set_dbname($orig_query_record);
		
		$this->sort_thinning_sql_where = '';
		
		if (! empty($this->item_form))
			$this->sort_thinning_sql_where .= "where node = '$this->item_form' " ;
		
		if (! empty($this->item_tag))
		{
			if ( $this->sort_thinning_sql_where == '')
				$this->sort_thinning_sql_where .= 'where';
			else
				$this->sort_thinning_sql_where .= 'and';
			$this->sort_thinning_sql_where .= " tagnode = '$this->item_tag' ";
		}

		return "SELECT beginPosition, endPosition
			FROM {$this->sort_db}
			$this->sort_thinning_sql_where
			ORDER BY beginPosition  ";
			//TODO: would refnumber be better than beginPosition? investigate
			

	}
	
	function dist_set_dbname($orig_query_record)
	{
		/* search the db list for a db whose parameters match those of the query we are working with  */
		/* if it doesn't exist, we need to create one */
		$db_record = check_dblist_parameters('dist', $orig_query_record['cqp_query'],
						$orig_query_record['restrictions'], $orig_query_record['subcorpus'],
						$orig_query_record['postprocess']);


		if ($db_record === false)
		{
			$this->dist_db = create_db('dist', $orig_query_record['query_name'], 
						$orig_query_record['cqp_query'], $orig_query_record['restrictions'], 
						$orig_query_record['subcorpus'], $orig_query_record['postprocess']);
		}
		else
		{
			touch_db($db_record['dbname']);
			$this->dist_db = $db_record['dbname'];
		}
			
	}
	
	function dist_sql_for_queryfile($orig_query_record)
	{
		global $corpus_sql_name;
		
		$this->dist_set_dbname($orig_query_record);
		
		return "SELECT beginPosition, endPosition
			FROM {$this->dist_db} 
			INNER JOIN text_metadata_for_$corpus_sql_name 
			ON {$this->dist_db}.text_id = text_metadata_for_$corpus_sql_name.text_id 
			WHERE text_metadata_for_$corpus_sql_name.{$this->dist_categorisation_handle}
				= '{$this->dist_class_handle}' 
			ORDER BY refnumber";
	}
	
	function text_sql_for_queryfile($orig_query_record)
	{
		/* IMPORTANT NB!! uses the same dbname-finding function as "dist" */
		$this->dist_set_dbname($orig_query_record);	
		
		return "SELECT beginPosition, endPosition
			FROM {$this->dist_db}
			WHERE text_id = '{$this->text_target_id}'";
	}
	

} /* end of class POSTPROCESS */











/* run_postprocess_ functions have the following format: 
 * parameters:	a cache-record of the new query and a descriptor object for the postprocess 
 * return:   	the cache record it was passed, with a new 'query_name' and 'hits_left' 
 */


/**
 * Creates a new query from that specified in cache_record by running
 * the "collocation" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_collocation($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $cqpweb_tempdir;
	global $username;
	
	
	// TODO: could the following be part of an "unsave" function????
	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();

	/* first, write a "dumpfile" to temporary storage */
	$tempfile  = "/$cqpweb_tempdir/temp_coll_$new_qname.tbl";

	$sql_query = $descriptor->colloc_sql_for_queryfile();

	$solutions_remaining = do_mysql_outfile_query($sql_query, $tempfile);

	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $solutions_remaining;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	if ($solutions_remaining > 0)
	{
		/* load to CQP as a new query, and save */
		$cqp->execute("undump $new_qname < '$tempfile'");
		$cqp->execute("save $new_qname");
		unlink($tempfile);
		
		/* get the size of the new query */
		$cache_record['file_size'] = cqp_file_sizeof($new_qname);

		/* put this newly-created query in the cache */
		do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

		update_cached_query($cache_record);

	}
	else 
	{
		unlink($tempfile);
		say_sorry_postprocess();
		/* which exits the program */
	}

	return $cache_record;
}


/**
 * Creates a new query from that specified in cache_record by running
 * the "sort" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_sort($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $cqpweb_tempdir;
	global $username;

	$orig_cache_record = $cache_record;

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	/* first, write a "dumpfile" to temporary storage */
	$tempfile  = "/$cqpweb_tempdir/temp_sort_$new_qname.tbl";

	$sql_query = $descriptor->sort_sql_for_queryfile($orig_cache_record);

	$solutions_remaining = do_mysql_outfile_query($sql_query, $tempfile);


	if ($descriptor->sort_remove_prev_sort)
	{
		/* we need to remove the previous "hits left" because a sort has been undone */
		$cache_record['hits_left'] = preg_replace('/~?\d+\Z/', '', $cache_record['hits_left']);
	}
	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $solutions_remaining;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	if ($solutions_remaining > 0)
	{
		
		/* load to CQP as a new query, and save */
		$cqp->execute("undump $new_qname < '$tempfile'");
		$cqp->execute("save $new_qname");
		unlink($tempfile);
		
		/* get the size of the new query */
		$cache_record['file_size'] = cqp_file_sizeof($new_qname);	

		/* put this newly-created query in the cache */
		do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");


		update_cached_query($cache_record);
	
	}
	else 
	{
		unlink($tempfile);
		say_sorry_postprocess();
		/* which exits the program */
	}

	return $cache_record;
}





/**
 * Creates a new query from that specified in cache_record by running
 * the "randomise" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_randomise($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $username;
	

	$old_qname = $cache_record['query_name'];	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	/* note for randomisation, "hits left" doesn't need changing */

	/* actually randomise */
	$cqp->execute("$new_qname = $old_qname");
	$cqp->execute("sort $new_qname randomize 42");
	$cqp->execute("save $new_qname");


	/* put this newly-created query in the cache */
	do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

	update_cached_query($cache_record);

	return $cache_record;
}


/**
 * Creates a new query from that specified in cache_record by running
 * the "unrandomise" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_unrandomise($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $username;
	

	$old_qname = $cache_record['query_name'];	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	/* note for unrandomisation, "hits left" doesn't need changing */

	/* actually unrandomise */
	$cqp->execute("$new_qname = $old_qname");
	$cqp->execute("sort $new_qname");
	$cqp->execute("save $new_qname");


	/* put this newly-created query in the cache */
	do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

	update_cached_query($cache_record);

	return $cache_record;
}


/**
 * Creates a new query from that specified in cache_record by running
 * the "thin" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_thin($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $username;

	$old_qname = $cache_record['query_name'];	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $descriptor->thin_target_hit_count;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	/* actually thin */
	$cqp->execute("$new_qname = $old_qname");
	/* constant seed of 42 results in reproducibly-random thinning */
	if ( ! $descriptor->thin_genuinely_random)
		$cqp->execute("randomize 42");
	$cqp->execute("reduce $new_qname to $descriptor->thin_target_hit_count");
	$cqp->execute("save $new_qname");
	
	/* get the size of the new query */
	$cache_record['file_size'] = cqp_file_sizeof($new_qname);
	
	/* put this newly-created query in the cache */
	do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

	update_cached_query($cache_record);

	return $cache_record;
}




/**
 * Creates a new query from that specified in cache_record by running
 * the "item" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_item($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $cqpweb_tempdir;
	global $username;

	$old_qname = $cache_record['query_name'];	
	$orig_cache_record = $cache_record;


	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	/* actually do it ! */

	/* first, write a "dumpfile" to temporary storage */
	$tempfile  = "/$cqpweb_tempdir/temp_item_$new_qname.tbl";

	/* this method call creates the DB if it doesn't already exist */
	$sql_query = $descriptor->item_sql_for_queryfile($orig_cache_record);

	$solutions_remaining = do_mysql_outfile_query($sql_query, $tempfile);

	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $solutions_remaining;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	if ($solutions_remaining > 0)
	{
		/* load to CQP as a new query, and save */
		$cqp->execute("undump $new_qname < '$tempfile'");
		$cqp->execute("save $new_qname");
		unlink($tempfile);
		
		/* get the size of the new query */
		$cache_record['file_size'] = cqp_file_sizeof($new_qname);

		/* put this newly-created query in the cache */
		do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

		update_cached_query($cache_record);
	}
	else 
	{
		unlink($tempfile);
		say_sorry_postprocess();
		/* which exits the program */
	}

	return $cache_record;
}



// NB this was copied from "item" (and thus, from "sort") with minor changes.
// TODO optimise sort, item, dist, text, colloc etc. to re-use the code, since so much of it is the same
// rather than repeating it
/**
 * Creates a new query from that specified in cache_record by running
 * the "distribution" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_dist($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $cqpweb_tempdir;
	global $username;

	$old_qname = $cache_record['query_name'];	
	$orig_cache_record = $cache_record;



	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	/* actually do it ! */

	/* first, write a "dumpfile" to temporary storage */
	$tempfile  = "/$cqpweb_tempdir/temp_dist_$new_qname.tbl";

	/* this method call creates the DB if it doesn't already exist */
	$sql_query = $descriptor->dist_sql_for_queryfile($orig_cache_record);

	$solutions_remaining = do_mysql_outfile_query($sql_query, $tempfile);

	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $solutions_remaining;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	if ($solutions_remaining > 0)
	{
		/* load to CQP as a new query, and save */
		$cqp->execute("undump $new_qname < '$tempfile'");
		$cqp->execute("save $new_qname");
		unlink($tempfile);
		
		/* get the size of the new query */
		$cache_record['file_size'] = cqp_file_sizeof($new_qname);

		/* put this newly-created query in the cache */
		do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

		update_cached_query($cache_record);
	}
	else 
	{
		unlink($tempfile);
		say_sorry_postprocess();
		/* which exits the program */
	}

	return $cache_record;
	
}


// another copy from the same family as preceding two functions
/**
 * Creates a new query from that specified in cache_record by running
 * the "text" postprocess, caches the new query, and returns its 
 * cache record.
 * 
 * $descriptor needs to be a POSTPROCESS object that contains the various
 * parameters controlling this postprocess.
 */
function run_postprocess_text($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $cqpweb_tempdir;
	global $username;

	$old_qname = $cache_record['query_name'];	
	$orig_cache_record = $cache_record;



	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	/* actually do it ! */

	/* first, write a "dumpfile" to temporary storage */
	$tempfile  = "/$cqpweb_tempdir/temp_text_$new_qname.tbl";

	/* this method call creates the DB if it doesn't already exist */
	$sql_query = $descriptor->text_sql_for_queryfile($orig_cache_record);

	$solutions_remaining = do_mysql_outfile_query($sql_query, $tempfile);

	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . $solutions_remaining;
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	if ($solutions_remaining > 0)
	{
		/* load to CQP as a new query, and save */
		$cqp->execute("undump $new_qname < '$tempfile'");
		$cqp->execute("save $new_qname");
		unlink($tempfile);
		
		/* get the size of the new query */
		$cache_record['file_size'] = cqp_file_sizeof($new_qname);

		/* put this newly-created query in the cache */
		do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

		update_cached_query($cache_record);
	}
	else 
	{
		unlink($tempfile);
		say_sorry_postprocess();
		/* which exits the program */
	}

	return $cache_record;
}





function run_postprocess_custom($cache_record, &$descriptor)
{
	global $instance_name;
	global $cqp;
	global $username;
	global $cqpweb_tempdir;

	$old_qname = $cache_record['query_name'];	

	$cache_record['query_name'] = $new_qname = qname_unique($instance_name);
	$cache_record['user'] = $username;
	$cache_record['saved'] = 0;
	$cache_record['save_name'] = NULL;
	$cache_record['time_of_query'] = time();
	
	$cache_record['postprocess'] = $descriptor->get_stored_postprocess_string();

	/* actually run it */

	/* the heart of it: dump, process, undump */	
	$matches = $cqp->dump($old_qname);
	$matches = $descriptor->custom_obj->postprocess_query($matches);
	$cqp->undump($new_qname, $matches, "/$cqpweb_tempdir");
	$cqp->execute("save $new_qname");

	/* get the size of the new query */
	$cache_record['hits_left'] .= (empty($cache_record['hits_left']) ? '' : '~') . count($matches) ;
	$cache_record['file_size'] = cqp_file_sizeof($new_qname);

	// TODO note, the two calls below are ACHING to be made into a function -- cache_query_from_record, maybe
	// TODO -- check what the diff is from the existing "cache a query" .... 
	
	/* put this newly-created query in the cache */
	do_mysql_query("insert into saved_queries (query_name) values ('$new_qname')");

	update_cached_query($cache_record);

	return $cache_record;
}


/*
////////////////////////////////////!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!1
/// very important note -- this won't work as well as bncWeb in the (sole) case where randomisation is applied *after* collocation thinning
/// bncWeb manages to keep the highlight, but here rand will get in the way of seeing coll
///////////// unless ... the coll output could be randomised using the same algorithm so it will be replicable????
// worry about this later
 * TODO
*/

/**
 * Returns an array of highlight positions matching the postprocess string specified.
 * 
 * This will include a dbname, which is how the function knows which data to work on.
 * 
 * NB. "Highlight positions" = which words in concordance lines should be emphasised
 * (usually in bold), e.g. collocating words in a collocation-thinned query, or sort
 * key word in a sorted query. 
 * 
 * This function sets its third output to true if tags are to be shown in highlight;
 * otherwise it is set to false.
 */
function get_highlight_position_table($qname, $postprocess_string, &$show_tags_in_highlight)
{
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
//TODO
// take the rand off
// is what's left a coll?
// if not, return false
// if it is, put its args into args; set a flag to say that a sort using the cqp algorithm must be applied to the array that we get back
// $sql_query_from_coll = true;
		break;

	case 'unrand':
//TODO
// take the unrand off
// is what's left a coll?
// if not, return false
// if it is, put its args into args; $coll_order_by = 'order by beginPosition'
// $sql_query_from_coll = true;
		break;

// TODO 
// actually, for both rand and unrand, it would be better to remove the rand or unrand, then return the results
// of simply calling this function again.

	case 'coll':
		$sql_query_from_coll = true;
		break;
	
	/* case dist, case thin, case custom, (or...) an additional rand, a syntax error, or anything else */	
	default:
		return false;
	/* note that arguably, "custom" should be possible to do... e.g. by using the "keyword" field as an indication of what
	 * should be highlit.
	 *  
	 * But this can be added later, if necessary. */
	}

	/* this is out here in an IF so that three different cases can access it */
	if ($sql_query_from_coll)
	{
		if (count($args) != 6)
			return false;
				
		if (empty($args[5]))
			$tag_filter_clause = '';
		else
		{
			$is_regex = (bool)preg_match('/\W/', $args[5]); 
			$op = ($is_regex ? 'REGEXP' : '=');
			$filter = ($is_regex ? regex_add_anchors($args[5]) : $args[5]);
			$tag_filter_att = get_corpus_metadata('primary_annotation');	
			$tag_filter_clause = "AND $tag_filter_att $op '{$args[5]}'";
		}

		$sql_query = "select dist from {$args[0]}
			where {$args[1]} = '{$args[2]}'
			$tag_filter_clause
			and dist between {$args[3]} and {$args[4]}
			$coll_order_by";
	}
	
	if (isset($sql_query))
	{
		$result = do_mysql_query($sql_query);

		$highlight_positions = array();
		while ( ($r = mysql_fetch_row($result)) !== false )
			$highlight_positions[] = $r[0];
		
		return $highlight_positions;
	}
	else
		return false;
}




// so useful, it should prob be in library
/**
 * Returns a printable (HTML) description of all the things that have been done
 * to a query in postprocessing, using a standard-format postprocess_string,
 * and a hits_string listing any decreases to the number of hits caused by
 * the postprocessing.
 */
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
			
			$description .= $bdo_tag1 . '(' . number_format((float)$hit_array[$i]) . ' hits)' . $bdo_tag2;
			$i++;
			
			break;
			
		case 'coll':
			$att_id = ($args[1] == 'word' ? '' : $annotations[$args[1]]);
			$description .= "collocating with $att_id <em>{$args[2]}</em>"
				. ( empty($args[5]) ? '' : " with tag restriction <em>{$args[5]}</em>" )
				. " $bdo_tag1("
				. number_format((float)$hit_array[$i]) . ' hits)'. $bdo_tag2;
			$i++;
			break;
			
		case 'rand':
			$description .= 'ordered randomly';
			break;
			
		case 'unrand':
			$description .= 'sorted into corpus order';
			break;
			
		case 'thin':
			$method = ($args[1] == 'r' ? 'random selection' : 'random selection (non-reproducible)');
			$count = number_format((float)$hit_array[$i]);
			$description .= "thinned with method <em>$method</em> to $count hits";
			$i++;
			break;
		
		case 'cat':
			$description.= "manually categorised as &ldquo;{$args[0]}&rdquo; ("
				. number_format((float)$hit_array[$i]) . " hits)";
			$i++;
			break;
		
		case 'item':
			$description .= "reduced with frequency list to  ";
			if (empty ($args[0]))
				$description .= 'tag: <em>' . $args[1] . '</em> ';
			else if (empty($args[1]))
				$description .= 'word: <em>' . $args[0] . '</em> ';
			else
				$description .= 'word-tag combination: <em>' . $args[0] . '_' . $args[1] . '</em> ';
			$description .= '(' . number_format((float)$hit_array[$i]) . ' hits)';
			$i++;
			break;
		
		case 'dist':
			$labels = metadata_expand_attribute($args[0], $args[1]);
			$description .= "distribution over <em>{$labels['field']} : {$labels['value']}</em> ";
			$description .= '(' . number_format((float)$hit_array[$i]) . ' hits)';
			$i++;
			break;
		
		case 'text':
			$description .= "occurrences in text <em>{$args[0]}</em> ";
			$description .= '(' . number_format((float)$hit_array[$i]) . ' hits)';
			$i++;
			break;
		
		case 'custom':
			$record = retrieve_plugin_info($args[0]);
			$obj = new $record->class($record->path);
			/* custom PP descs are allowd to be empty, in which case, we just add the
			 * new number of hits. */
			$description .= $obj->get_postprocess_description()
				. " $bdo_tag1("	. number_format((float)$hit_array[$i]) . ' hits)'. $bdo_tag2;
			$i++;
			unset($obj);
			break;
			
		default:
			/* malformed postprocess string; so add an error to the return */
			$description .= '????';
			break;
		}
	}

	return $description;
}



// TODO this function not tested yet.
/*
 * 
 * Postprocess helper functions.
 * 
 * For use by custom postprocessors, to allow them to find out something 
 * about the data they are acting on.
 * 
 * 
 */ 
/**
 * Gets the value of a given positional-attribute (word annotation)
 * at a given token position in the active corpus.
 * 
 * Returns a single string, or false in case of error.
 */
function pphelper_cpos_get_attribute($cpos, $attribute) 
{
	/* typecast in case anyone is foolish enough to pass a float... */
	$num_of_token = (int)$cpos;
	if ($num_of_token < 0)
		exiterror_general("pphelper_cpos_within_structure: invalid corpus index [$cpos]");

	/* work out whether cpos is within an instance of the structure */
	$cmd = "/$path_to_cwb/cwb-decode -C -s $num_of_token -e $num_of_token -r /$cwb_registry  $corpus_cqp_name -P $attribute";
	$proc = popen($cmd, 'r');
	$value = fgets($proc);
	pclose($proc);
	
	if (empty($value))
		return false;
	else
		return trim($value);
}


//TODO this function not tested yet.
/**
 * Gets a full concordance from a set of matches.
 * 
 * The concordance is returned as an array of arrays. The outer array contains
 * as many members as the $matches argument, in corresponding order. Each inner array 
 * represents one hit, and corresponds to a single group of two-to-four integers.
 * Moreover, each inner array contains three members (all strings): the context
 * before, the context after, and the hit itself. 
 *
 * The $matches array is an array of arrays of integers or integers as strings, 
 * in the same format used to convey a query to a custom postprocess.
 *
 * You can specify what p-attributes and s-attributes you wish to be displayed in the
 * concordance. The default is to show words only, and no XML. Use an array of strings
 * to specify the attributes you want shown in each case.
 * 
 * You can also specify how much context is to be shown, and the unit it should be 
 * measured in. The default is ten words.
 * 
 * Individual tokens in the concordance are rendered using slashes to delimit the
 * different annotations.
 */
function pphelper_get_concordance($matches,
                                  $p_atts_to_show = 'word',
                                  $s_atts_to_show = '',
                                  $context_n = 10,
                                  $context_units = 'words')
{
	global $cqp;
	global $cqpweb_tempdir;
	global $instance_name;
	
	/* don't allow an empty array */
	if (empty($p_atts_to_show))
	
	/* for the default, but also in case someone passes a single argument. */
	if (!is_array($p_atts_to_show))
		$p_atts_to_show = array ($p_atts_to_show);

	if ( $context_units != 'words' && !xml_exists($context_units) )
		$context_units = 'words';

	/* get a new identifier by suffixing the instance name */
	$temp_qname = $instance_name . 'pph';

	/* undump matches to that uniqid */
	$cqp->undump($temp_qname, $matches, "/$cqpweb_tempdir/");
	unset($matches);

	/* Set up CQP cponcordance output stuff:
	 * The main script will not have set up its options at this point!
	 * so we can do what we like, and it will be re-done
	 */
	$cqp->execute("set Context $context_n $context_units");
	$cqp->execute("show +" . implode (' +', $p_atts_to_show));
	if (!empty($s_atts_to_show))
		$cqp->execute("set PrintStructures \"" . implode(' ', $s_atts_to_show) . "\""); 
	$cqp->execute("set LeftKWICDelim '--%%%--'");
	$cqp->execute("set RightKWICDelim '--%%%--'");

	/* cat concordance */
	$kwic = $cqp->execute("cat $qname");

	/* extract lines to arrays. */
	$result = array();
	foreach ($kwic as &$line)
	{
		$result[] = explode ('--%%%--', $line);
		unset($line);
		/* so that as one array grows, the other shrinks. */
	}
	
	/* delete the query in CQP 
	 * (it shouldn't have been saved to file, so just get it out of memory....) */
	$cqp->execute("discard $temp_qname");

	return $result;
}


// TODO this function not tested yet.
/**
 * Determines whether or not the specified corpus position (integer index) occurs
 * within an instance of the specified structural attribute (XML element).
 * 
 * Returns a boolean (true or false, or NULL in case of error).
 */
function pphelper_cpos_within_structure($cpos, $struc_attribute)
{
	global $corpus_cqp_name;
	
	/* typecast in case anyone is foolish enough to pass a float... */
	$num_of_token = (int)$cpos;
	if ($num_of_token < 0)
		exiterror_general("pphelper_cpos_within_structure: invalid corpus index [$cpos]");
	
	/* Is $struc_attribute a valid s-att for this corpus? */
	if (! in_array($struc_attribute, get_xml_all()) )
		exiterror_general("pphelper_cpos_within_structure: invalid s-attribute index [$struc_attribute]");
	
	/* work out whether cpos is within an instance of the structure */
	$cmd = "/$path_to_cwb/cwb-s-decode -r /$cwb_registry $corpus_cqp_name -S $struc_attribute";
	$proc = popen($cmd, 'r');
	$within = false;
	while ( false !== ($line = fgets($proc)) )
	{
		list($begin, $end) = explode ("\t", trim($line));
		if ($begin <= $cpos && $cpos <= $end)
		{
			$within = true;
			break;
		}
	}
	pclose($proc);

	return $within;
}



?>
