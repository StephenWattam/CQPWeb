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





/* library of functions for dealing with metadata tables */





/** 
 * Returns a list of currently-defined corpus categories, as an array (integer keys = id numbers).
 * 
 * This list is never empty (if the database table is empty, a default entry "uncategorised" is created
 * with id number 1 (since 1 is the default category that new corpora have first off....).
 */
function list_corpus_categories()
{
	$result = do_mysql_query("select idno, label from corpus_categories order by sort_n asc");
	if (mysql_num_rows($result) < 1)
	{
		do_mysql_query("ALTER TABLE corpus_categories AUTO_INCREMENT=1");
		do_mysql_query("insert into corpus_categories (idno, label, sort_n) values (1, 'Uncategorised', 0)");
		return array(0=>'Uncategorised');
	}	
	$list_of_cats = array();
	while ( ($r=mysql_fetch_row($result)) !== false )
		$list_of_cats[$r[0]] = $r[1];
	return $list_of_cats;
}


function update_corpus_category_sort($category_idno, $new_sort_n)
{
	$category_idno = (int)$category_idno;
	$new_sort_n = (int)$new_sort_n;
	do_mysql_query("update corpus_categories set sort_n = $new_sort_n where idno = $category_idno");
}

function delete_corpus_category($category_idno)
{
	$category_idno = (int)$category_idno;
	do_mysql_query("delete from corpus_categories where idno = $category_idno");	
}

function add_corpus_category($label, $initial_sort_n = 0)
{
	$label = mysql_real_escape_string($label);
	if (empty($label))
		return;
	$initial_sort_n = (int)$initial_sort_n;
	do_mysql_query("insert into corpus_categories (label, sort_n) values ('$label', $initial_sort_n)");
}


/** returns a list of all the corpora (referred to by mysql ID codes) currently in the system, as an array */
function list_corpora()
{
	$list_of_corpora = array();
	$result = do_mysql_query("select corpus from corpus_metadata_fixed");
	while ( ($r=mysql_fetch_row($result)) !== false )
		$list_of_corpora[] = $r[0];
	return $list_of_corpora;
}




/** returns a list of all the texts in the specified corpus, as an array */
function corpus_list_texts($corpus)
{
	$list_of_texts = array();
	$result = do_mysql_query("select text_id from text_metadata_for_" . mysql_real_escape_string($corpus));
	while ( ($r=mysql_fetch_row($result)) !== false )
		$list_of_texts[] = $r[0];
	return $list_of_texts;
}




function text_metadata_table_exists()
{
	global $corpus_sql_name;

	$sql_query = "show tables";
	$result = do_mysql_query($sql_query);
	
	$tables = array();
	while ( ($r = mysql_fetch_row($result)) !== false)
		$tables[] = $r[0];
		

	if (in_array("text_metadata_for_$corpus_sql_name", $tables))
		return true;
	else
		return false;
}





function get_corpus_metadata($field)
{
	global $corpus_sql_name;
	
	/* if data is in fixed metadata table */
	switch ($field)
	{
	/* update this list as necessary if extra fields are added */
	case 'primary_classification_field':
	case 'primary_annotation':
	case 'secondary_annotation':
	case 'tertiary_annotation':
	case 'tertiary_annotation_tablehandle':
	case 'combo_annotation':
	case 'external_url':
	case 'public_freqlist_desc':
	case 'corpus_cat':
	case 'cwb_external':
		$query = "select $field from corpus_metadata_fixed where corpus = '$corpus_sql_name'";
		break;
	default:
		$query = "select value from corpus_metadata_variable where corpus = '"
			. $corpus_sql_name ."' AND attribute = '$field'";
	}

	$result = do_mysql_query($query);

	if ($result != false && mysql_num_rows($result) != 0)
	{
		/* data was found */
		$r = mysql_fetch_row($result);
		$value = $r[0];
	}
	else
	{
		/* data was not found */
		$value = "";
	}
	return $value;
}

function update_corpus_category($newcat)
{
	global $corpus_sql_name;
	$newcat = (int)$newcat;
	do_mysql_query("update corpus_metadata_fixed set corpus_cat = $newcat where corpus = '$corpus_sql_name'");
}

function update_corpus_title($newtitle)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	$settings->set_corpus_title($newtitle);
	$settings->save();
}

function update_css_path($newpath)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	$settings->set_css_path($newpath);
	$settings->save();
}

function update_corpus_main_script_is_r2l($newval)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	$settings->set_r2l($newval);
	$settings->save();
}
function update_corpus_uses_case_sensitivity($newval)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	$settings->set_case_sens($newval);
	$settings->save();
}
function update_corpus_context_scope($newcount, $newunit)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	$settings->set_context_scope($newcount);
	$settings->set_context_s_attribute($newunit);
	$settings->save();
}
//TODO delete following function
/*
function update_corpus_directory_override($type, $newdir)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	if ($type == 'reg' || $type == 'reg_dir' || $type == 'regdir')
		$settings->set_directory_override_reg($newdir);
	if ($type == 'data' || $type == 'data_dir' || $type == 'datadir')
		$settings->set_directory_override_data($newdir);
	$settings->save();	
}
*/

function update_corpus_visualisation_gloss($in_concordance, $in_context, $annot)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	$settings->set_visualise_gloss_in_concordance($in_concordance);
	$settings->set_visualise_gloss_in_context($in_context);
	$settings->set_visualise_gloss_annotation($annot);
	$settings->save();	
}

function update_corpus_visualisation_translate($in_concordance, $in_context, $s_att)
{
	global $corpus_sql_name;
	$settings = new CQPwebSettings('..');
	$settings->load($corpus_sql_name);
	$settings->set_visualise_translate_in_concordance($in_concordance);
	$settings->set_visualise_translate_in_context($in_context);
	$settings->set_visualise_translate_s_att($s_att);
	$settings->save();	
}
function update_corpus_metadata($field, $value)
{
	global $corpus_sql_name;
	
	$field = mysql_real_escape_string($field);
	$value = mysql_real_escape_string($value);
	
	/* if data is in fixed metadata table */
	switch ($field)
	{
	/* update this list as necessary if extra fields are added */
	case 'primary_classification_field':
	case 'primary_annotation':
	case 'secondary_annotation':
	case 'tertiary_annotation':
	case 'tertiary_annotation_tablehandle':
	case 'combo_annotation':
	case 'external_url':
	case 'public_freqlist_desc':
	case 'corpus_cat':
		$query = "update corpus_metadata_fixed set $field = '$value' where corpus = '$corpus_sql_name'";
		break;
	default:
		$query = "update corpus_metadata_variable set value = '$value' "
			. " where corpus = '$corpus_sql_name' AND attribute = '$field'";
	}

	do_mysql_query($query);
}


/**
 * Returns as integer the number of words in this corpus.
 */
function get_corpus_wordcount()
{
	global $corpus_sql_name;

	// this should prob be an auto-genrerated item of fixed metadata
	// obviating the need for this function separate from the above
	$sql_query = "select sum(words) from text_metadata_for_$corpus_sql_name";
	$result = do_mysql_query($sql_query);
	$row = mysql_fetch_row($result);

	return (int)$row[0];
}

/**
 * Returns an associative array: the keys are annotation handles, 
 * the values are annotation descs.
 * 
 * If the corpus has no annotation, an empty array is returned. 
 */
function get_corpus_annotations()
{
	global $corpus_sql_name;
	
	$compiled = array();

	$sql_query = "select handle, description from annotation_metadata 
		where corpus = '$corpus_sql_name'";
	
	$result = do_mysql_query($sql_query);

	while (($r = mysql_fetch_row($result)) !== false)
		$compiled[$r[0]] = $r[1];

	return $compiled;
}

function check_is_real_corpus_annotation($handle)
{
	static $annotations;

	if ($handle == 'word')
		return true;

	if (!isset($annotations))
		$annotations = get_corpus_annotations();
	
	return array_key_exists($handle, $annotations);
}

/** 
 * Returns a list of tags used in the given annotation field, 
 * derived from the corpus's freqtable.
 */
function corpus_annotation_taglist($field)
{
	global $corpus_sql_name;
	
	/* shouldn't be necessary...  but hey */
	$field = mysql_real_escape_string($field);
	/* this function WILL NOT RUN on word - the results would be huge & unwieldy */
	if ($field == 'word')
		return array();
	
	$sql_query = "select distinct(item) from freq_corpus_{$corpus_sql_name}_{$field} limit 1000";
	$result = do_mysql_query($sql_query);
			
	while ( ($r = mysql_fetch_row($result)) !== false )
		$tags[] = $r[0];
	
	sort($tags);
	return $tags;
}


function metadata_expand_attribute($field, $value)
{
	global $corpus_sql_name;
	/* value may not be a handle, so it needs escaping */
	$value = mysql_real_escape_string($value);
	
	$sql_query = 'SELECT description FROM text_metadata_values WHERE corpus = '
		. "'$corpus_sql_name' AND field_handle = '$field' AND handle = '$value' LIMIT 1";


	$result = do_mysql_query($sql_query);

	if (mysql_num_rows($result) == 0)
		$exp_val = $value;
	else
	{
		$row = mysql_fetch_row($result);
		$exp_val = $row[0];
		if ($exp_val == '')
			$exp_val = $value;
	}
	
	unset($row);
	unset($result);
	

	$sql_query = 'SELECT description FROM text_metadata_fields WHERE corpus = '
		. "'$corpus_sql_name' AND handle = '$field' LIMIT 1";
		
	$result = do_mysql_query($sql_query);	

	if (mysql_num_rows($result) == 0)
		$exp_field = $field;
	else
	{
		$row = mysql_fetch_row($result);
		$exp_field = $row[0];
		if ($exp_field == '')
			$exp_field = $field;
	}
	
	return array('field' => $exp_field, 'value' => $exp_val);
}




/**
 * Expands the handle of a field to its description.
 * 
 * If there is no description, the handle is returned unaltered.
 */
function metadata_expand_field($field)
{
	global $corpus_sql_name;
	
	$field = mysql_real_escape_string($field);
	$sql_query = 'SELECT description FROM text_metadata_fields WHERE corpus = '
		. "'$corpus_sql_name' AND handle = '$field' LIMIT 1";
		
	$result = do_mysql_query($sql_query);

	if (mysql_num_rows($result) == 0)
		$exp_field = $field;
	else
	{
		list($exp_field) = mysql_fetch_row($result);
		if (empty($exp_field))
			$exp_field = $field;
	}

	return $exp_field;
}



/**
 * Returns an associative array (field=>value) for the text with the specified text id
 */
function metadata_of_text($text_id)
{
	global $corpus_sql_name;

	$text_id = mysql_real_escape_string($text_id);

	$sql_query = "select * from text_metadata_for_$corpus_sql_name 
					where text_id = '$text_id' limit 1";
	$result = do_mysql_query($sql_query);
	
	return mysql_fetch_assoc($result);
}

/**
 * Returns an onmouseover string for links to the specified text_id
 * 
 * TODO: this should probably be in concordance-lib
 */
function metadata_tooltip($text_id)
{
	global $corpus_sql_name;
	
	static $stored_tts = array();
	
	/* avoid re-running a double mysql query for a text whose tooltip has already been created */
	if (isset($stored_tts[$text_id]))
		return $stored_tts[$text_id]; 
	
	$sql_query = "select * from text_metadata_for_$corpus_sql_name where text_id = '$text_id'";
	$text_result = do_mysql_query($sql_query);
	if (mysql_num_rows($text_result) == 0)
		return "";
	$text_data = mysql_fetch_assoc($text_result);

	$sql_query = "select handle from text_metadata_fields where corpus = '$corpus_sql_name' and is_classification = 1";
	$field_result = do_mysql_query($sql_query);
	if (mysql_num_rows($field_result) == 0)
		return "";
	
	$tt = 'onmouseover="return escape(\'Text <b>' . $text_id . '</b><BR>'
		. '<i>(length = ' . make_thousands($text_data['words']) 
		. ' words)</i><BR>--------------------<BR>';
	
	while (($field_handle = mysql_fetch_row($field_result)) != false)
	{
		$item = metadata_expand_attribute($field_handle[0], $text_data[$field_handle[0]]);
		
		if ($item['value'] != "")
			$tt .= str_replace('\'', '\\\'', '<i>' . cqpweb_htmlspecialchars($item['field']) . ':</i> <b>' 
						. cqpweb_htmlspecialchars($item['value']) . '</b><BR>');
	}
	
	$tt .= '\')"';
	
	/* store for later use */
	$stored_tts[$text_id] = $tt;
	
	return $tt;
}

/**
 * Returns an array of field handles for the metadata table in this corpus.
 */
function metadata_list_fields()
{
	global $corpus_sql_name;

	$sql_query = "select handle from text_metadata_fields where corpus = '$corpus_sql_name'";
	$result = do_mysql_query($sql_query);
			
	$r = array();
	while (($temp = mysql_fetch_row($result)) != false)
		$r[] = $temp[0];
	
	return $r;
}


/**
 * Returns true if this field name is a classification; false if it is free text.
 * 
 * An exiterror will occur if the field does not exist!
 */
function metadata_field_is_classification($field)
{
	global $corpus_sql_name;

	$field = mysql_real_escape_string($field);

	$sql_query = "SELECT is_classification FROM text_metadata_fields WHERE 
		corpus = '$corpus_sql_name' and handle = '$field'";
		
	$result = do_mysql_query($sql_query);

	if (mysql_num_rows($result) < 1)
		exiterror_general("Unknown metadata field specified!\n\n$sql_query", __FILE__, __LINE__);

	list($return_me) = mysql_fetch_row($result);
	
	return (bool)$return_me;
}



/**
 * Returns an array of arrays listing all the classification schemes & 
 * their descs for the current corpus. 
 * 
 * Return format: array('handle'=>$the_handle,'description'=>$the_description) 
 * 
 * If the description is NULL or an empty string in the database, a copy of the handle 
 * is put in place of the description. This default functionality can be turned off 
 * by passing a FALSE argument.
 */
function metadata_list_classifications($disallow_empty_descriptions = true)
{
	global $corpus_sql_name;
	
	$disallow_empty_descriptions = (bool)$disallow_empty_descriptions;

	$sql_query = "SELECT handle, description FROM text_metadata_fields WHERE 
		corpus = '$corpus_sql_name' and is_classification = 1";
		
	$result = do_mysql_query($sql_query);

	$return_me = array();

	while (($r = mysql_fetch_assoc($result)) != false)
	{
		if ($disallow_empty_descriptions && empty($r['description']))
			$r['description'] = $r['handle'];
		$return_me[] = $r;
	}
	return $return_me;
}


/**
 *  Returns a list of category handles occuring for the given classification. 
 */
function metadata_category_listall($classification)
{
	global $corpus_sql_name;


	$sql_query = "SELECT handle FROM text_metadata_values 
		WHERE field_handle = '$classification' AND corpus = '$corpus_sql_name'";
		
	$result = do_mysql_query($sql_query);

	$return_me = array();
	
	while (($r = mysql_fetch_row($result)) != false)
		$return_me[] = $r[0];
	
	return $return_me;
}



/**
 * Returns an associative array of category descriptions,
 * where the keys are the handles, for the given classification.
 * 
 * If no description exists, the handle is set as the description.
 */
function metadata_category_listdescs($classification)
{
	global $corpus_sql_name;

	$sql_query = "SELECT handle, description FROM text_metadata_values 
		WHERE field_handle = '$classification' AND corpus = '$corpus_sql_name'";
		
	$result = do_mysql_query($sql_query);

	$return_me = array();
	
	while (($r = mysql_fetch_row($result)) != false)
		$return_me[$r[0]] = (empty($r[1]) ? $r[0] : $r[1]);
	
	return $return_me;
}




/**
 * Returns a list of text IDs, plus their category for the given classification. 
 */
function metadata_category_textlist($classification)
{
	global $corpus_sql_name;
	
	$sql_query = "SELECT text_id, $classification FROM text_metadata_for_$corpus_sql_name";
		
	$result = do_mysql_query($sql_query);

	$return_me = array();
	
	while (($r = mysql_fetch_assoc($result)) != false)
		$return_me[] = $r;
	
	return $return_me;
}


/**
 * returns the size of a category within a given classification 
 * as an array with [0]=> size in words, [1]=> size in files
 */ 
function metadata_size_of_cat($classification, $category)
{
	global $corpus_sql_name;

	$sql_query = "SELECT sum(words) FROM text_metadata_for_$corpus_sql_name 
		where $classification = '$category'";

	$result = do_mysql_query($sql_query);

	$size_in_words = mysql_fetch_row($result);
	unset($result);


	$sql_query = "SELECT count(*) FROM text_metadata_for_$corpus_sql_name
		where $classification = '$category'";

	$result = do_mysql_query($sql_query);
	
	$size_in_files = mysql_fetch_row($result);
	unset($result);

	return array($size_in_words[0], $size_in_files[0]);
}


/* as above, but thins by an additional classification-catgory pair (for crosstabs) */
function metadata_size_of_cat_thinned($classification, $category, $class2, $cat2)
{
	global $corpus_sql_name;

	$sql_query = "SELECT sum(words) FROM text_metadata_for_$corpus_sql_name 
		where $classification = '$category' and $class2 = '$cat2'";

	$result = do_mysql_query($sql_query);

	$size_in_words = mysql_fetch_row($result);
	unset($result);


	$sql_query = "SELECT count(*) FROM text_metadata_for_$corpus_sql_name
		where $classification = '$category' and $class2 = '$cat2'";

	$result = do_mysql_query($sql_query);
	
	$size_in_files = mysql_fetch_row($result);
	unset($result);

	return array($size_in_words[0], $size_in_files[0]);
}




/** 
 * counts the number of words in each text class for this corpus,
 * and updates the table contianing that info 
 */
function metadata_calculate_category_sizes()
{
	global $corpus_sql_name;

	/* get a list of classification schemes */
	$sql_query = "select handle from text_metadata_fields where corpus = '$corpus_sql_name' and is_classification = 1";
	$result_list_of_classifications = do_mysql_query($sql_query);
	
	/* for each classification scheme ... */
	while( ($c = mysql_fetch_row($result_list_of_classifications) ) != false)
	{
		$classification_handle = $c[0];
		
		/* get a list of categories */
		$sql_query = "select handle from text_metadata_values 
						where corpus = '$corpus_sql_name' and field_handle = '$classification_handle'";

		$result_list_of_categories = do_mysql_query($sql_query);

	
		/* for each category handle found... */
		while ( ($d = mysql_fetch_row($result_list_of_categories)) != false)
		{
			$category_handle = $d[0];
			
			/* how many files / words fall into that category? */
			$sql_query = "select count(*), sum(words) from text_metadata_for_$corpus_sql_name 
							where $classification_handle = '$category_handle'";
			
			$result_counts = do_mysql_query($sql_query);

			if (mysql_num_rows($result_counts) > 0)
			{
				list($file_count, $word_count) = mysql_fetch_row($result_counts);

				$sql_query = "update text_metadata_values set category_num_files = '$file_count',
					category_num_words = '$word_count'
					where corpus = '$corpus_sql_name' 
					and field_handle = '$classification_handle' 
					and handle = '$category_handle'";
				do_mysql_query($sql_query);
			}
			unset($result_counts);
		} /* loop for each category */
		
		unset($result_list_of_categories);
	} /* loop for each classification scheme */
}

?>