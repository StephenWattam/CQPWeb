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
 * Library of functions supporting multivariate analysis operations 
 * and user-created feature matrices.
 */



/*
 * 
 * FEATURE MATRIX OBJECT FUNCTIONS
 * 
 */




/**
 * Get a list of all feature matrices.
 * 
 * Returns an array consisting of stdClass objects, each of which
 * contains the MySQL fields for a single saved feature matrix.
 * 
 * The array is ordered alphabetically by savename, but the ID numbers of the matrices are
 * also available (given as array keys).
 * 
 * @param corpus  If not an empty value, only feature matrices from the given corpus will be returned.
 *                Default: empty (all corpora).
 * @param user    If not an empty value, only feature matrices belonging to the given user will be returned.
 *                Default: empty (all users).
 * @return        Array containing object list.
 */
function list_feature_matrices($corpus = NULL, $user = NULL)
{
	$list = array();
	
	if (empty($corpus))
	{
		if (!empty($user))
			$where = ' where user = \'' . mysql_real_escape_string($user) . '\' ';
	}
	else
	{
		$where = ' where corpus = \'' . mysql_real_escape_string($corpus) . '\' ';
		if (!empty($user))
			$where = ' and user = \'' . mysql_real_escape_string($user) . '\' ';	
	}
	
	$result = do_mysql_query("select * from saved_matrix_info $where order by save_name asc");	
	
	while (false !== ($o = mysql_fetch_object($result)))
		$list[$o->id] = $o;
	
	return $list;
}


/**
 * Delete a specified feature matrix - identified by unique integer ID.
 */
function delete_feature_matrix($id)
{
	$id = (int)$id;
	
	/* first, delete the actual data table. */
	$table = feature_matrix_id_to_tablename($id);
	do_mysql_query("drop table if exists $table");
	
	/* now, delete all the rows containing information about this fm's variables. */
	do_mysql_query("delete from saved_matrix_features where matrix_id = $id");
	
	/* finally, delete the database row itself. */
	do_mysql_query("delete from saved_matrix_info where id = $id");
}

/**
 * Translates a feature matrix description to an ID number.
 * Returns false if no matching entry found. 
 */
function lookup_feature_matrix_id($corpus, $user, $savename)
{
	//TODO
	
}

/**
 * Gets a DB object corresponding to the specified properties.
 * Returns false if no matching entry found.
 */
function get_feature_matrix($corpus, $user, $savename)
{
	// TODO
}


/**
 * Gets a DB object corresponding to the specified feature matrix.
 * Returns false if no matching entry found.
 */
function get_feature_matrix_by_id($id)
{
	$id = (int) $id;
	$o = mysql_fetch_object(do_mysql_query("TODO where id = $id")); 	//TODO
}


/**
 * Translates a feature matrix ID code to the mysql tablename 
 * that contains the data for that feature matrix.
 */
function feature_matrix_id_to_tablename($id)
{
	//TODO
	
}

/**
 * Creates a feature matrix object in the MySQL database.
 * 
 * Note this function *does not* populate the actual database table that contains the matrix. 
 * 
 * @return the ID number of the saved feature matrix we have just created.
 */
function build_feature_matrix()
{
	//TODO
	
	
	// straight after the bit where we create the _info table row do this:
	$id = get_mysql_insert_id();
	
	return $id;
}


/**
 * Returns a string containing an instruction to run in R
 * that will create the feature matrix desired as an R matrix.
 * 
 * @param $id  ID of the feature matrix you want to gety a string for.
 * TODO -- or, better to pass in a DB object? 
 */
function get_feature_matrix_r_import($id)
{
	//TODO	
	
}


/**
 * Lists all the data objects in a given feature matrix.
 *
 * @return  An array of strings (variable labels)
 */
function feature_matrix_list_variables($id)
{
	//TODO
	
}

/**
 * Lists all the data objects in a given feature matrix.
 * 
 * @return  An array of strings (object labels)
 */
function feature_matrix_list_objects($id)
{
	//TODO
	
}




?>