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
 * Functions for management / invocation of corpus-indexing templates.
 */

/**
 * Returns an array of objects representing annotation templates.
 * 
 * Each object contains: (a) the fields from the database; (b) an array "attributes" of database objects for the template's p-attributes.
 * 
 * The array keys are the ID numbers.
 */
function list_annotation_templates()
{
	$list = array();
	
	$result = do_mysql_query("select * from annotation_template_info order by id");
	
	while (false !== ($o = mysql_fetch_object($result)))
	{
		$o->attributes = array();
		$list[$o->id] = $o;
	}
		
	$result = do_mysql_query("select * from annotation_template_content order by template_id, order_in_template");
	
	while (false !== ($o = mysql_fetch_object($result)))
	{
		/* skip any attributes whose linked template does not exist (sanity check) */
		if (!isset($list[$o->template_id]))
			continue;
		$list[$o->template_id]->attributes[$o->order_in_template] = $o;
	}
	
	return $list;
}

/**
 * Add a new annotation template.
 * 
 * @param description  A string to label it with. Does not have to be unique.
 * @param primary      String containing handle of the primary annotation (one of the attributes listed in the next argument).
 * @param attributes   An array of p-attribute descriptions. This must contain either inner-arrays or objects, each of which is a
 *                     description of a p-attribute, as created by the various forms and stored in the database. Necessary fields are
 *                     as follows:
 *                      * handle => C-word-only short handle for the attribute.
 *                      * description => Long description for the attribute.
 *                      * is_feature_set => Boolean for whether this is a feature set.
 *                      * tagset => name of tagset (empty string if none).
 *                      * external_url => URL to tagset manual (empty string if none). 
 *                     The keys of the entire array should be numeric and should represent the column-numbers to which each attribute
 *                     applies - that is, start at 1 (because "word" is 0) and go up from there, with no gaps. 
 */
function add_annotation_template($description, $primary, $attributes)
{
	$description  = mysql_real_escape_string($description);
	do_mysql_query("insert into annotation_template_info (description) values ('$description')");
	$id = get_mysql_insert_id(); 
	
	if (!empty($primary))
	{
		$primary = cqpweb_handle_enforce($primary);
		do_mysql_query("update annotation_template_info set primary_annotation = '$primary' where id = $id");
	} 
	
	for ($i = 1 ; isset($attributes[$i]) ; $i++)
	{
		$a = is_object($attributes[$i]) ? $attributes[$i] : (object)$attributes[$i];
		
		$sql = "insert into annotation_template_content (template_id, order_in_template, handle, description, is_feature_set, tagset, external_url) values (";
		
		$sql .= "$id, $i,";
		
		$sql .= ' \'' . cqpweb_handle_enforce($a->handle) . '\', ' ;
		
		$sql .= ' \'' . mysql_real_escape_string($a->description) . '\', ';
		
		$sql .= ($a->is_feature_set ? '1,' : '0,');

		$sql .= ' \'' . mysql_real_escape_string($a->tagset) . '\', ';
		
		$sql .= ' \'' . mysql_real_escape_string($a->external_url) . '\'';

		$sql .= ')';
		
		do_mysql_query($sql);
	}
}


/**
 * Deletes template with the specified ID. 
 */
function delete_annotation_template($id)
{
	$id = (int) $id;
	do_mysql_query("delete from annotation_template_content where template_id = $id");
	do_mysql_query("delete from annotation_template_info where id = $id");
}


function load_default_annotation_templates()
{
	add_annotation_template('Word tokens only', NULL, NULL);
	
	$pos_plus_lemma = array (
			1 => array('handle'=>'pos', 'description'=>'Part-of-speech tag', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>''),
			2 => array('handle'=>'lemma', 'description'=>'Lemma', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>'')
			);
			
	add_annotation_template('POS plus lemma (TreeTagger format)', 'pos', $pos_plus_lemma);

	$pos_plus_lemma[3] = 
			array('handle'=>'semtag', 'description'=>'Semantic tag', 'is_feature_set'=>false, 
					'tagset'=>'USAS tagset', 'external_url'=>'http://ucrel.lancs.ac.uk/usas');


	add_annotation_template('POS plus lemma plus semtag', 'pos',  $pos_plus_lemma);

	$lancaster_annotations = array ( 
			1 => array('handle'=>'pos', 'description'=>'Part-of-speech tag', 'is_feature_set'=>false, 
						'tagset'=>'C6 tagset', 'external_url'=>'http://ucrel.lancs.ac.uk/claws6tags.html'),
			2 => array('handle'=>'lemma', 'description'=>'Lemma', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>''),
			3 => array('handle'=>'semtag', 'description'=>'Semantic tag', 'is_feature_set'=>false, 
						'tagset'=>'USAS tagset', 'external_url'=>'http://ucrel.lancs.ac.uk/usas'),
			4 => array('handle'=>'class', 'description'=>'Simple POS', 'is_feature_set'=>false, 
						'tagset'=>'Oxford Simplified Tagset', 'external_url'=>'http://www.natcorp.ox.ac.uk/docs/URG/codes.html#klettpos'),
			5 => array('handle'=>'taglemma', 'description'=>'Tagged lemma', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>''),
			6 => array('handle'=>'fullsemtag', 'description'=>'Full USAS analysis', 'is_feature_set'=>false, 
						'tagset'=>'USAS tagset', 'external_url'=>'')
			);

	add_annotation_template('Lancaster toolchain annotations', 'pos', $lancaster_annotations);
	
	$lancaster_annotations[7] = 
					array('handle'=>'orig', 'description'=>'Unregularised spelling', 'is_feature_set'=>false, 
							'tagset'=>'', 'external_url'=>'');

	add_annotation_template('Lancaster toolchain annotations ( + VARD orig.)', 'pos',  $lancaster_annotations);

}

/**
 * This is a bit of a cheat function as it breaks the usual separation of levels.
 * 
 * But it beats creating a separate script for just this!
 */
function interactive_load_annotation_template()
{
	if (empty($_GET['newTemplateDescription']))
		exiterror_general("No description given for new template.");
	
	$description = $_GET['newTemplateDescription'];
	
	$atts = array();

	for ( $i = 1; !empty($_GET["templatePHandle$i"]) ; $i++ )
	{
		$atts[$i] = new stdClass();
		
		$atts[$i]->handle = cqpweb_handle_enforce($_GET["templatePHandle$i"]);
		
		if ($atts[$i]->handle == '__HANDLE')
		{
			unset($atts[$i]);
			break;
		}
		
		$atts[$i]->description = $_GET["templatePDesc$i"];
		
		$atts[$i]->tagset = $_GET["templatePTagset$i"];
		
		$atts[$i]->external_url = $_GET["templatePurl$i"];
		
		$atts[$i]->is_feature_set = (isset($_GET["templatePfs$i"]) && 1 == $_GET["templatePfs$i"]);
		
		if (isset($_GET['templatePPrimary']) && $i == $_GET['templatePPrimary'])
			$primary = $atts[$i]->handle;
	}
	
	if (!isset($primary))
		$primary = NULL;
	
	add_annotation_template($description, $primary, $atts);

	
}

?>
