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
 * this file contains functions dealing with the creation and management of subcorpora 
 * and restrictions 

 * note that a RESTRICTION is a where-clause that can be used to select files in the text_metadata 
 * table for this corpus; a SUBCORPUS can be based on a restriction or may be a list of text names 

 * if both restrictions and text list are present for a subcorpus, restrictions overrule file list 
 */




/**
 * create a subcorpus from a space-delimited text list.
 * note - no list-format checking is performed here, only sorting.
 * 
 * Any existing subcorpus of the same name is overwritten.
 */
function create_subcorpus_list($subcorpus_name, $text_list)
{
	global $User;
	global $corpus_sql_name;

	$text_list = alphabetise_textlist($text_list);
	$whereclause = translate_textlist_to_where($text_list);
	
	$sql_query = "SELECT count(*), sum(words) FROM text_metadata_for_$corpus_sql_name 
		WHERE $whereclause";
	$result = do_mysql_query($sql_query);
	list($numfiles, $numwords) = mysql_fetch_row($result);
			
	unset($result);

	$subcorpus_name = mysql_real_escape_string($subcorpus_name);
	
	/* overwrite check must be performed before this */
	$sql_query = "DELETE FROM saved_subcorpora 
		WHERE subcorpus_name = '$subcorpus_name'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	do_mysql_query($sql_query);

	$text_list = mysql_real_escape_string($text_list);
	
	$sql_query = "INSERT INTO saved_subcorpora (subcorpus_name, corpus, user, text_list, numfiles, numwords)
		values 
		('$subcorpus_name', '$corpus_sql_name', '{$User->username}', '$text_list', '$numfiles', '$numwords')";
	do_mysql_query($sql_query);
}





/** Creates a subcorpus from restrictions formatted as SQL "where" clause */
function create_subcorpus_restrictions($subcorpus_name, $restrictions)
{
	global $User;
	global $corpus_sql_name;

    // FIXME
    header("X-restrict: " . $restrictions);

	$sql_query = "SELECT count(*), sum(words) FROM text_metadata_for_$corpus_sql_name 
		WHERE $restrictions";
	$result = do_mysql_query($sql_query);
	list($numfiles, $numwords) = mysql_fetch_row($result);
			
	unset($result);

	/* overwrite check must be performed before this */
	$sql_query = "DELETE FROM saved_subcorpora 
		WHERE subcorpus_name = '$subcorpus_name'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	do_mysql_query($sql_query);
	
	$subcorpus_name = mysql_real_escape_string($subcorpus_name);
	$restrictions = mysql_real_escape_string($restrictions);
	
	$sql_query = "INSERT INTO saved_subcorpora (subcorpus_name, corpus, user, restrictions, numfiles, numwords)
		values 
		('$subcorpus_name', '$corpus_sql_name', '{$User->username}', '$restrictions', '$numfiles', '$numwords')";
	do_mysql_query($sql_query);
}



function create_subcorpus_query($subcorpus_name, $qname)
{
	global $User;
	global $corpus_sql_name;
	global $cqp;

	/* check the connection to CQP */
	if (isset($cqp))
		$cqp_was_set = true;
	else
	{
		$cqp_was_set = false;
		connect_global_cqp();
	}

	/* get text list from query result; convert to a space-delimited list of ids */
	
	$grouplist = $cqp->execute("group $qname match text_id");
	
	$texts = array();
	foreach($grouplist as &$g)
		list($texts[]) = explode("\t", $g);
	
	$list_of_texts = implode(' ', $texts);

	/* then call the function we already have */
	create_subcorpus_list($subcorpus_name, $list_of_texts);

	if (!$cqp_was_set)
		disconnect_global_cqp();
}



function create_subcorpus_invert($subcorpus_name, $subcorpus_to_invert)
{
	global $corpus_sql_name;
	
	$texts_to_exclude = explode(' ', subcorpus_get_text_list($subcorpus_to_invert));
	
	$result = do_mysql_query("select text_id from text_metadata_for_$corpus_sql_name");
	
	$new_text_list = '';
	
	while (false !== ($r = mysql_fetch_row($result)))
		if (!in_array($r[0], $texts_to_exclude))
			$new_text_list .= $r[0] . ' ';
	
	$new_text_list = trim($new_text_list);
	
	if (empty($new_text_list))
		exiterror_general("The subcorpus you have tried to create would not contain any texts!");
	
	create_subcorpus_list($subcorpus_name, $new_text_list);
}



function subcorpus_change_restrictions_to_list($subcorpus_name)
{
	global $User;
	global $corpus_sql_name;

	$sql_query = "select * from saved_subcorpora
		WHERE subcorpus_name = '$subcorpus_name'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) < 1)
		exiterror_arguments($subcorpus_name, 'This subcorpus does not seem to exist!', 
			__FILE__, __LINE__);
	
	$record = mysql_fetch_assoc($result);
	
	if ($record['restrictions'] == '')
		/* nothing to change */
		return;
			
	unset($result);
	
	$list = translate_restrictions_to_text_list($record['restrictions']);
	
	$sql_query = "update saved_subcorpora set restrictions = '', text_list = '$list'
		WHERE subcorpus_name = '$subcorpus_name'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	do_mysql_query($sql_query);
}







/**
 * Removes the texts listed in the array from the specified subcorpus.
 */
function subcorpus_remove_texts($subcorpus, $text_array)
{

	/* get the list of texts */
	$new_list = subcorpus_get_text_list($subcorpus);

	/* remove the texts from the list */
	foreach($text_array as &$t)
		$new_list = preg_replace('/ {0,1}' . $t . '/', '', $new_list); 
	
	/* because there might be a space at the beginning */
	$new_list = trim($new_list);

	if (empty($new_list))
		exiterror_general('There would not be any texts left if you deleted all these!');

	subcorpus_alter_text_list($subcorpus, $new_list);
}




/** 
 * Adds the texts listed in the array to the specified subcorpus 
 * (if they are not already there).
 */
function subcorpus_add_texts($subcorpus, $text_array)
{	
	/* get the list of texts */
	$new_list = subcorpus_get_text_list($subcorpus);
	
	/* add new texts to list if not there already */
	foreach($text_array as &$t)
		if (preg_match("/\b$t\b/", $new_list) < 1)
			$new_list .= ' ' . $t;
	$new_list = alphabetise_textlist($new_list);
	
	subcorpus_alter_text_list($subcorpus, $new_list);
}



/**
 * Changes the subcorpus to have the new text list (and no restrictions)
 * and updates its size.
 * 
 * Note: new_list should be a space-delimited string as per usual.
 */
function subcorpus_alter_text_list($subcorpus, $new_list)
{
	global $User;
	global $corpus_sql_name;

	$subcorpus = mysql_real_escape_string($subcorpus);
	
	/* if this subcorpus has a compiled freq table, delete it */
	if ( ! (false === ($ft = check_freqtable_subcorpus($subcorpus))))
		delete_freqtable($ft['freqtable_name']);
	
	/* find out the new size of the subcorpus and update */
	$sql_query = "SELECT count(*), sum(words) FROM text_metadata_for_$corpus_sql_name 
		WHERE " . translate_textlist_to_where($new_list);
	list($numfiles, $numwords) = mysql_fetch_row(do_mysql_query($sql_query));

	$sql_query = "update saved_subcorpora 
		SET restrictions = NULL, text_list = '$new_list', numfiles = $numfiles, numwords = $numwords 
		WHERE subcorpus_name = '$subcorpus'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	do_mysql_query($sql_query);
}


/**
 * Returns a string containing that subcorpus' list of text_ids, separated by spaces
 */
function subcorpus_get_text_list($subcorpus)
{
	global $User;
	global $corpus_sql_name;
	
	$subcorpus = mysql_real_escape_string($subcorpus);
			
	$sql_query = "select restrictions, text_list from saved_subcorpora
		WHERE subcorpus_name = '$subcorpus'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) < 1)
		exiterror_arguments($subcorpus, 'This subcorpus does not seem to exist!', 
			__FILE__, __LINE__);

	$sc_record = mysql_fetch_assoc($result);
	unset($result);
	
	if ($sc_record['text_list'] != '')
	{
		/* use text list - don't even look at restrictions */
		$final_string = $sc_record['text_list'];
	}
	else
	{
		/* no text list, so resort to restrictions */
		if ($sc_record['restrictions'] == '')
			exiterror_arguments($subcorpus, 'This subcorpus\'s database record is incomplete!', 
				__FILE__, __LINE__);

		$sql_query = "select text_id from text_metadata_for_$corpus_sql_name
			WHERE {$sc_record['restrictions']}";
		$result = do_mysql_query($sql_query);
				
		while ( ($r = mysql_fetch_row($result)) !== false)
		{
			$text_ids[] = $r[0];	
		}
		$final_string = implode(' ', $text_ids);
	}
	
	return $final_string;
}

/**
 * returns true if the sc has a restrictions field but no subcorpus field;
 * otherwise false
 */
function subcorpus_based_on_restrictions($subcorpus)
{
	global $User;
	global $corpus_sql_name;
	
	$subcorpus = mysql_real_escape_string($subcorpus);

	$sql_query = "select restrictions, text_list from saved_subcorpora
		WHERE subcorpus_name = '$subcorpus'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) < 1)
		exiterror_arguments($subcorpus, 'This subcorpus does not seem to exist!', 
			__FILE__, __LINE__);

	$sc_record = mysql_fetch_assoc($result);
	unset($result);
	
	if ($sc_record['text_list'] != '')
	{
		/* it has a text list */
		return false;
	}
	else
	{
		/* no text list, so check if there are restrictions */
		if ($sc_record['restrictions'] == '')
			exiterror_arguments($subcorpus, 'This subcorpus\'s database record is incomplete!', 
				__FILE__, __LINE__);

		return true;
	}

}


/**
 * get the size of the subcorpus in words and files
 * returned as  array: [0]=> $words, 'words'=> $words, [1]=>files, ['files']=>$files 
 */
function subcorpus_sizeof($subcorpus)
{
	global $User;
	global $corpus_sql_name;
	
	$subcorpus = mysql_real_escape_string($subcorpus);
	
	$sql_query = "select numwords, numfiles from saved_subcorpora
		WHERE subcorpus_name = '$subcorpus'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) < 1)
		exiterror_arguments($subcorpus, 'This subcorpus does not seem to exist!', 
			__FILE__, __LINE__);
	
	$r = mysql_fetch_row($result);
	
	$r['words'] = $r[0];
	$r['files'] = $r[1];
	
	return $r;				
}

/**
 * Amends the numwords and numfiles fields in the subcorpus table to match the 
 * (presumably new) text list
 */
function subcorpus_sizeof_update($subcorpus)
{
	//TODO
	// is this even needed?
}


/**
 * Gets an array containing the names of all subcorpora belonging to the current user.
 */ 
function get_list_of_subcorpora()
{
	global $User;
	global $corpus_sql_name;

	$result = do_mysql_query("select subcorpus_name from saved_subcorpora 
								where user='{$User->username}' and corpus='$corpus_sql_name'");
	for ($list = array() ; false !== ($r = mysql_fetch_row($result)) ; )
		$list[] = $r[0];
	
	return $list;
}


/* this would be a nice easy mySQL query, BUT it is also necessary to delete associated queries && */
/* freq tables as well */
function delete_subcorpus($subcorpus_name)
{
	global $User;
	global $corpus_sql_name;

	/* delete any queries that use this subcorpus */
	$sql_query = "select query_name from saved_queries  
		where subcorpus = '$subcorpus_name'
		and corpus = '$corpus_sql_name' 
		and user = '{$User->username}'
		";
	$result = do_mysql_query($sql_query);
	
	while ( ($r = mysql_fetch_row($result)) !== false)
		delete_cached_query($r[0]);
		

	/* delete any DBs based on this subcorpus */
	$sql_query = "select dbname from saved_dbs  
		where subcorpus = '$subcorpus_name'
		and corpus = '$corpus_sql_name' 
		and user = '{$User->username}'
		";
	$result = do_mysql_query($sql_query);
	
	while ( ($r = mysql_fetch_row($result)) !== false)
		delete_db($r[0]);
		

	/* delete the freqtables for this subcorpus, if it has them */
	if ( ($freqtable_record = check_freqtable_subcorpus($subcorpus_name)) == false )
		/* it has no freq tables */
		;
	else
		delete_freqtable($freqtable_record['freqtable_name']);


	/* finally, delete the subcorpus record itself */
	$sql_query = "delete from saved_subcorpora  
		where subcorpus_name = '$subcorpus_name'
		and corpus = '$corpus_sql_name' 
		and user = '{$User->username}'
		LIMIT 1";
	do_mysql_query($sql_query);
}




function translate_restrictions_to_text_list($restrictions)
{
	global $corpus_sql_name;

	$sql_query = "select text_id from text_metadata_for_$corpus_sql_name where $restrictions";
	/* note - it isn't real-escaped, so it must be escaped before this if necessary */
	$result = do_mysql_query($sql_query);

	$list = '';
	while ($r = mysql_fetch_row($result))
		$list .= $r[0] . ' ';
		
	return rtrim($list);
}



/**
 * Loads a subcorpus into CQP for use in a subsequent query.
 */
function load_subcorpus_to_cqp($subcorpus)
{
	global $Config;
	global $User;
	global $corpus_sql_name;
	global $instance_name;

	if (! is_string($subcorpus))
		exiterror_arguments($subcorpus, "A string is needed for load_subcorpus_to_cqp!", 
			__FILE__, __LINE__);
			
	$sql_query = "select restrictions, text_list from saved_subcorpora
		WHERE subcorpus_name = '$subcorpus'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) < 1)
		exiterror_arguments($subcorpus, 'This subcorpus does not seem to exist!', 
			__FILE__, __LINE__);

	$sc_record = mysql_fetch_assoc($result);
	unset($result);
	
	if ($sc_record['text_list'] != '')
	{
		/* use text list - don't even look at restrictions */
		$wherelist = translate_textlist_to_where($sc_record['text_list']);

		$sqlfile = "{$Config->dir->cache}/sc_temp_$instance_name"; 
	
		$sql_query = "SELECT cqp_begin, cqp_end 
			FROM text_metadata_for_$corpus_sql_name 
			WHERE $wherelist ORDER BY cqp_begin ASC";
		do_mysql_outfile_query($sql_query, $sqlfile);

		/* we load the mysql outfile to CQP*/
		load_limits_to_cqp($sqlfile);
		unlink($sqlfile);
	}
	else
	{
		/* no text list, so resort to restrictions */
		if ($sc_record['restrictions'] == '')
			exiterror_arguments($subcorpus, 'This subcorpus\'s database record is incomplete!', 
				__FILE__, __LINE__);
		load_restrictions_to_cqp($sc_record['restrictions']);
	}
}



/**
 * Loads a set of textual metadata restrictions into CQP for use in a subsequent query.
 */
function load_restrictions_to_cqp($restrictions)
{
	global $Config;
	global $corpus_sql_name;
	global $instance_name;

	if (! is_string($restrictions))
		exiterror_arguments($restrictions, "A string is needed for load_restrictions_to_cqp!", 
			__FILE__, __LINE__);
			
	$sqlfile = "{$Config->dir->cache}/sc_temp_$instance_name"; 
	
	$sql_query = "SELECT cqp_begin, cqp_end 
		FROM text_metadata_for_$corpus_sql_name 
		WHERE $restrictions ORDER BY cqp_begin ASC";
	$n = do_mysql_outfile_query($sql_query, $sqlfile);
	
	/* check for restrictions that match nothing */
	if ($n < 1)
	{
		unlink($sqlfile);
		exiterror_general('There are no texts in the corpus that match those restrictions!');
	}

	/* we load the mysql outfile to CQP */
	load_limits_to_cqp($sqlfile);
	unlink($sqlfile);
}


/**
 * Do not call this function: only to be used
 * by load_restrictions_to_cqp and load_subcorpus_to_cqp 
 */
function load_limits_to_cqp($limits_file)
{
	global $cqp;

	$cqp->execute("undump Limits < '$limits_file'");
	$cqp->execute("Limits");
}





/* uses the "get" string to get a string of conditions in sql that can be applied to the */
/* text metadata table for this corpus to extract a list of files or corpus positions */
/* if there are no such conditions, it just returns 'no_restriction', whihc can be used as a flag */
function translate_restrictions_definition_string()
{


    $query_string = $_SERVER['QUERY_STRING'] . file_get_contents('php://input');
    $query_string = urldecode($query_string);
    /* $query_string = $_SERVER['QUERY_STRING']; */

    /* header("X-qs: $query_string"); */

	/* check for a named subcorpus OR a __last_restriction */
	global $subcorpus;
	if ( strpos($query_string, '&t=__last_restrictions') !== false) 
	{
		$subcorpus = '__last_restrictions';
        header("X-qs1: $query_string");
		return 'no_restriction';
	}
	if (preg_match('/&t=subcorpus~(\w*?)&/', $query_string, $m2))
	{
		$subcorpus = $m2[1];
        header("X-qs2: $query_string");
		return 'no_restriction';
	}



	/* format of the string = &del=begin&t=[class~cat]&t=&del=end */
	$m = array();
	if ( ! preg_match('/&del=begin(.*?)&del=end/', $query_string, $m)){
        return 'no_restriction';
    /* }elseif ( ! preg_match('/&del=begin(.*?)(&del=end)?/', $query_string, $m)){     // FIXME: REALLY ugly hack. */
    /*     return 'no_restriction'; */
    }


    /* header("X-q31: " . $m[1]); */

	/* must be at least one restriction */
	if ($m[1] === '&t=' || $m[1] === '')
	{
		unset($_GET['del']);
		unset($_GET['t']);
		$query_string = preg_replace('/&del=begin.*?&del=end/', '', $query_string);
        /* header("X-qs3: $query_string"); */
		return 'no_restriction';
	}
	
	/* this is for data security  to failsafe - everything in the string *should* be a handle */
	$m[1] = mysql_real_escape_string($m[1]);




	$restriction = preg_split('/&t=/', $m[1], -1, PREG_SPLIT_NO_EMPTY );
	unset($m);

	$sql_restrictions = array();
	
	/* extract the classificationscheme-category (attribute-value) pairs */
	foreach ($restriction as $r)
	{
		preg_match('/\A([^~]*)~(.*?)\Z/', $r, $m);
		$class = $m[1];
		$cat = $m[2];
		
		$sql_restrictions[$class][$cat] = "$class='$cat'";
	}

	/* sort the array - means that identical "where" strings really will be identical */
	foreach ($sql_restrictions as $k => $s)
		ksort($sql_restrictions[$k]);
	ksort($sql_restrictions);

	/* collapse the arrays */
	$temp_array = array();
	foreach ($sql_restrictions as $s)
		$temp_array[] = '(' . implode(' || ', $s) . ')';

	$final_sql_string = implode(' && ', $temp_array);


	/* finally, remove the "del" and "t" elements from get, so that they can't be passed on */
	unset($_GET['t']);
	unset($_GET['del']);
	
	/* and delete it from the server array */
	$query_string = preg_replace('/&del=begin.*?&del=end/', '', $query_string);
	
	return $final_sql_string;
}

/**
 * turns a where-clause-format set of descriptions into a URL string,
 * with "del=begin" at the start and "&del=end" at the end. Note the lack
 * of an ampersand at the start of the string.
 */
function untranslate_restrictions_definition_string($restrictions)
{
	if ($restrictions == 'no_restriction')
		return '';

	/* delete brackets */
	$restrictions = str_replace('(', '', $restrictions);
	$restrictions = str_replace(')', '', $restrictions);
	/* delete single quotes */
	$restrictions = str_replace('\'', '', $restrictions);

	/* convert = back to squiggle */
	$restrictions = str_replace('=', '~', $restrictions);
	
	/* merge the two types of condition */
	$restrictions = str_replace(' || ', '&t=', $restrictions);
	$restrictions = str_replace(' && ', '&t=', $restrictions);

	return 'del=begin&t=' . $restrictions . '&del=end' ;
}



/* this function shouldn't be needed any more 
function save_last_restrictions_as_subcorpus($restrictions)
{
	global $User;
	global $corpus_sql_name;

	$sql_query = "SELECT count(*), sum(words) FROM text_metadata_for_$corpus_sql_name 
		WHERE $restrictions";
	do_mysql_query($sql_query);

	list($numfiles, $numwords) = mysql_fetch_row($result);
			
	unset($result);

	$sql_query = "DELETE FROM saved_subcorpora 
		WHERE subcorpus_name = '__last_restrictions'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	do_mysql_query($sql_query);
	
	$restrictions = mysql_real_escape_string($restrictions);
	
	$sql_query = "INSERT INTO saved_subcorpora (subcorpus_name, corpus, user, restrictions, numfiles, numwords)
		values 
		('__last_restrictions', '$corpus_sql_name', '{$User->username}', '$restrictions', '$numfiles', '$numwords')";
	do_mysql_query($sql_query);
}
*/



function reload_last_restrictions()
{
	global $User;
	global $corpus_sql_name;

	$sql_query = "SELECT restrictions from saved_subcorpora
		WHERE subcorpus_name = '__last_restrictions'
		AND corpus = '$corpus_sql_name'
		AND user = '{$User->username}'";
	$result = do_mysql_query($sql_query);
	$row = mysql_fetch_row($result);

	return $row[0];
}


/**
 * Sorts an array of arrays representing a sequence of CWB corpus positions.
 * 
 * The array is sorted by ascending value of the [0] element of each inner array.
 * 
 * Note, unlike the normal PHP array sort functions, this function uses pass-by-value
 * and return. It does not operate on a variable passed by reference.
 * 
 * @return  The sorted array.
 */
function sort_positionlist($list)
{
	static $callback = NULL;
	if (empty($callback))
		$callback = create_function('$a, $b', 
		                            'if ($a[0] == $b[0]) return 0; 
		                             return ($a[0] < $b[0]) ? -1 : 1;');
	usort($list, $callback);
	return $list;
}





function translate_restrictions_to_prose($restrictions)
{
	if ($restrictions == 'no_restriction')
		return '';

	/* delete brackets */
	$restrictions = str_replace('(', '', $restrictions);
	$restrictions = str_replace(')', '', $restrictions);
	/* delete single quotes */
	$restrictions = str_replace('\'', '', $restrictions);

	$r_array = explode(' && ', $restrictions );

	$prose_array = array();

	for ( $i = 0, $n = count($r_array) ; $i < $n ; $i++ )
	{
		preg_match('/\A([^=]*)=/', $r_array[$i], $m);
		$field = $m[1];
		$prose_array[$i] = '<em>' . metadata_expand_field($field) . '</em>';
		$prose_array[$i] .= ': ';
		$temp_array = explode(' || ', $r_array[$i] );
		for ( $t = 0, $tn = count($temp_array); $t < $tn ; $t++)
		{
			if ($t > 0)
				$prose_array[$i] .= ($t == $tn-1 ? ' or ' : ', ');

			preg_match('/\A[^=]*=(\w*)/', $temp_array[$t], $m);
			$exp = metadata_expand_attribute($field, $m[1]);
			$prose_array[$i] .= '<em>' . $exp['value'] . '</em>';
		}
		if ($i != $n-1)
		$prose_array[$i] .= '; ';
	}

	return '&ldquo;' . implode('', $prose_array) . '&rdquo;';
}


function alphabetise_textlist($text_list)
{
	$list = explode(' ', $text_list);
	
	sort($list, SORT_STRING);
	
	return implode(' ', $list);
}


/**
 * Translates a list of texts to a where clause listing each text as
 * an or-linked condition on the text_id field.
 * 
 * Note the actual "WHERE" keyword is not included in the return value.
 * 
 * The argument (textlist) is normally expected to be a string of
 * space-delimited text ids. But, if the (optional) second parameter
 * is set to true, the first argument will instead be expected to be
 * an array of strings where each one is a single text id.
 */
function translate_textlist_to_where($text_list, $as_array = false)
{
	if ($as_array)
		$list =& $text_list;
	else
		$list = explode(' ', $text_list);
	
	$string = '(';
	
	foreach ($list as $l)
		$string .= "text_id = '$l' OR ";
	
	return (substr($string, 0, -4) . ')');
}





function check_textlist_valid($text_list)
{
	$names = explode(' ', $text_list);
	$badnames = array();
	
	foreach($names as $n)
		if (!check_real_text_name($n))
			$badnames[] = $n;
	
	if (count($badnames) > 0)
		return implode(' ', $badnames);
	else
		return '__no__errors__';
}






function check_real_text_name($text)
{
	global $corpus_sql_name;

	$text = mysql_real_escape_string($text);
	
	$sql_query = "select text_id from text_metadata_for_$corpus_sql_name where text_id = '$text'";
	$result = do_mysql_query($sql_query);
	
	if (mysql_num_rows($result) > 0)
		return true;
	else
		return false;
}






?>
