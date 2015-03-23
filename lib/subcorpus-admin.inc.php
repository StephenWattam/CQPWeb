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






/* does its action, then calls the index page with a subcorpus function */
/* for whatever is to be displayed next */


/* include defaults and settings */
require('../lib/environment.inc.php');


/* include function files */
require_once('../lib/cache.inc.php');
require_once('../lib/db.inc.php');
require_once('../lib/library.inc.php');
require_once('../lib/user-lib.inc.php');
require_once('../lib/html-lib.inc.php');
require_once('../lib/metadata.inc.php');
require_once('../lib/exiterror.inc.php');
require_once('../lib/subcorpus.inc.php');
require_once('../lib/freqtable.inc.php');
require_once("../lib/cwb.inc.php"); /* NOT TESTED YET - used by dump and undump, I think */
require_once("../lib/cqp.inc.php");

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);


if (!isset($_GET['scriptMode']))
	exiterror_general('No scriptmode specified for subcorpus-admin.inc.php!', __FILE__, __LINE__);
else
	$this_script_mode = $_GET['scriptMode'];


/* this variable is allowed to be missing */
if (isset($_GET['subcorpusNewName']))
	$subcorpus_name = mysql_real_escape_string($_GET['subcorpusNewName']);







/* if "Cancel" was pressed on a form, do nothing, and just go straight to the index */
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'Cancel')
{
	cqpweb_shutdown_environment();
	header('Location: index.php?thisQ=subcorpus&uT=y');
	exit();
}




switch ($this_script_mode)
{

case 'create_from_manual':

	if (!isset($_GET['subcorpusListOfFiles']))
	{
		cqpweb_shutdown_environment();
		/* effectively do not allow a submission (but sans error message) if the field is empty */
		header('Location: '
			. url_absolutify('index.php?subcorpusCreateMethod=manual&subcorpusFunction=define_subcorpus'
			. "&subcorpusNewName=$subcorpus_name&thisQ=subcorpus&uT=y"));
	}
	else
	{
		$list_of_texts = mysql_real_escape_string(trim(preg_replace('/[\s,]+/', ' ',
			$_GET['subcorpusListOfFiles'])));

		subcorpus_admin_check_name($subcorpus_name,
			url_absolutify('index.php?subcorpusBadName=y&' . url_printget()));

		/* get a list of text names that are not real text ids */
		$errors = check_textlist_valid($list_of_texts);

		if ($errors != '__no__errors__')
		{
			cqpweb_shutdown_environment();
			header('Location: '
				. url_absolutify('index.php?subcorpusCreateMethod=manual&subcorpusListOfFiles='
				. "$list_of_texts&subcorpusFunction=define_subcorpus&subcorpusNewName="
				. "$subcorpus_name&subcorpusBadTexts=$errors&thisQ=subcorpus&uT=y"));
		}

		create_subcorpus_list($subcorpus_name, $list_of_texts);

		cqpweb_shutdown_environment();
		header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
	}
	exit();



case 'create_from_metadata':

	$restrictions = translate_restrictions_definition_string();
	if ($restrictions == 'no_restriction' )
	{
		cqpweb_shutdown_environment();
		/* effectively do not allow a submission (but sans error message) if no cats selected */
		header('Location: '
			. url_absolutify('index.php?subcorpusCreateMethod=metadata&subcorpusFunction=define_subcorpus'
			. "&subcorpusNewName=$subcorpus_name&thisQ=subcorpus&uT=y"));
	}

	if ($_GET['action'] == 'Get list of texts')
	{
		/* then we don't want to actually store it, just display a new form */

		$list_of_texts_to_show_in_form = translate_restrictions_to_text_list($restrictions);
		$header_cell_text = 'Viewing texts that match the following metadata restrictions: <br/>'
			. translate_restrictions_to_prose($restrictions);
		$field_to_show = get_corpus_metadata('primary_classification_field');

		cqpweb_shutdown_environment();
		$_GET['subcorpusFunction'] = 'list_of_files';
		require("../lib/queryhome.inc.php");
	}
	else
	{
		subcorpus_admin_check_name($subcorpus_name,
			url_absolutify('index.php?subcorpusBadName=y&subcorpusCreateMethod='
				. 'metadata&subcorpusFunction=define_subcorpus&'
				. untranslate_restrictions_definition_string($restrictions) . '&'
				. url_printget()));

		create_subcorpus_restrictions($subcorpus_name, $restrictions);

		cqpweb_shutdown_environment();
		header('X-debug: test');
		header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
	}
	exit();






case 'create_from_metadata_scan':

	/* set up variables in memory, manipulate GET, and then include index to get the list */

	if (!isset($_GET['metadataFieldToScan']))
		exiterror_general('No search field specified!', __FILE__, __LINE__);
	else
		$field_to_show = $field = mysql_real_escape_string($_GET['metadataFieldToScan']);

	if (!isset($_GET['metadataScanString']))
		exiterror_general('No search target specified!', __FILE__, __LINE__);
	else
		$orig_value = $value = mysql_real_escape_string($_GET['metadataScanString']);

	$header_cell_text = 'Viewing texts where <em>' . metadata_expand_field($field);

	switch($_GET['metadataScanType'])
	{
	case 'begin':
		$value .= '%';
		$header_cell_text .= '</em> begins with';
		break;

	case 'end':
		$value = '%' . $value;
		$header_cell_text .= '</em> ends with';
		break;

	case 'contain':
		$value = '%' . $value . '%';
		$header_cell_text .= '</em> contains';
		break;

	case 'exact':
		/* note - if nothing is specified, assume exact match required */
	default:
		$header_cell_text .= '</em> matches exactly';
		break;
	}

	$header_cell_text .= ' &ldquo;' . $orig_value . '&rdquo;';


	$sql_query = "select text_id from text_metadata_for_$corpus_sql_name where $field like '$value'";
	$result = do_mysql_query($sql_query);


	$list_of_texts_to_show_in_form = '';

	while ( ($r = mysql_fetch_row($result)) != false)
		$list_of_texts_to_show_in_form .= ' ' . $r[0];

	$list_of_texts_to_show_in_form = trim($list_of_texts_to_show_in_form);


	$_GET = array();
	$_GET['thisQ'] = 'subcorpus';
	$_GET['subcorpusFunction'] = 'list_of_files';

	cqpweb_shutdown_environment();
// TODO. This may be better with Location.
	require('../lib/queryhome.inc.php');
	exit();




case 'create_from_query':

	if (!isset($_GET['savedQueryToScan']))
	{
		cqpweb_shutdown_environment();
		/* effectively do not allow a submission (but sans error message) if no query specified */
		header('Location: '
			. url_absolutify('index.php?subcorpusCreateMethod=query&subcorpusFunction=define_subcorpus'
			. "&subcorpusNewName=$subcorpus_name&thisQ=subcorpus&uT=y"));
		exit();
	}


	$create = ($_GET['action'] != 'Get list of texts');
	$qname = mysql_real_escape_string($_GET['savedQueryToScan']);

	if ($create)
	{
		subcorpus_admin_check_name($subcorpus_name,
			url_absolutify('index.php?subcorpusBadName=y&subcorpusCreateMethod='
				. 'query&subcorpusFunction=define_subcorpus&'
				. url_printget()));

		create_subcorpus_query($subcorpus_name, $qname);

		cqpweb_shutdown_environment();
		header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
	}
	else
	{
		$header_cell_text = "Viewing texts in saved query &ldquo;$qname&rdquo;";
		$field_to_show = get_corpus_metadata('primary_classification_field');

		connect_global_cqp();

		$grouplist = $cqp->execute("group $qname match text_id");

		$texts = array();
		foreach($grouplist as &$g)
			list($texts[]) = explode("\t", $g);

		$list_of_texts_to_show_in_form = implode(' ', $texts);

		cqpweb_shutdown_environment();
		$_GET['subcorpusFunction'] = 'list_of_files';
		// TODO better with Location?
		require('../lib/queryhome.inc.php');
	}
	exit();


case 'create_inverted':

	$subcorpus_to_invert = mysql_real_escape_string($_GET['subcorpusToInvert']);
	if (empty($subcorpus_to_invert))
		exiterror_general("You must specify a subcorpus to invert!");

	subcorpus_admin_check_name($subcorpus_name,
		url_absolutify('index.php?subcorpusBadName=y&subcorpusCreateMethod='
			. 'invert&subcorpusFunction=define_subcorpus&'
			. url_printget()));

	create_subcorpus_invert($subcorpus_name, $subcorpus_to_invert);

	cqpweb_shutdown_environment();
	header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
	exit();


case 'create_text_id':
	$text_list = corpus_list_texts($corpus_sql_name);

	if (count($text_list) > 100)
		exiterror_general('This corpus contains more than 100 texts, so you cannot use the one-subcorpus-per-text function!');

	foreach($text_list as $id)
		create_subcorpus_list($id, $id);

	cqpweb_shutdown_environment();
	header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
	exit();


case 'copy':


	if (! (isset($_GET['subcorpusToCopy']) && isset($subcorpus_name) ) )
		exiterror_general('No subcorpus name specified for copying in subcorpus-admin.inc.php!',
			__FILE__, __LINE__);

	if (preg_match('/\W/', $subcorpus_name) > 0)
	{
		cqpweb_shutdown_environment();
		/* call the index script with a rejected name */
		header('Location: ' . url_absolutify('index.php?subcorpusBadName=y&' . url_printget()));
		exit();
	}


	$old_subcorpus_name = mysql_real_escape_string($_GET['subcorpusToCopy']);

	/* queries below need username and corpus because only with all three is the record unique */

	$sql_query = "insert into saved_subcorpora select * from saved_subcorpora
		where subcorpus_name = '$old_subcorpus_name'
		and corpus = '$corpus_sql_name'
		and user = '{$User->username}'";
	do_mysql_query($sql_query);

	$sql_query = "update saved_subcorpora set subcorpus_name = '$subcorpus_name'
		where subcorpus_name = '$old_subcorpus_name'
		and corpus = '$corpus_sql_name'
		and user = '{$User->username}'
		LIMIT 1";
	do_mysql_query($sql_query);

	cqpweb_shutdown_environment();
	header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));

	exit();


case 'delete':

	if (isset($_GET['subcorpusToDelete']))
	{
		$deleteme = mysql_real_escape_string($_GET['subcorpusToDelete']);

		delete_subcorpus($deleteme);

		cqpweb_shutdown_environment();

		header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));

		exit();
	}
	else
		exiterror_parameter('No subcorpus specified to delete!', __FILE__, __LINE__);
	break;





case 'delete_texts':

	if (! (isset($_GET['subcorpusToDeleteFrom']) ) )
		exiterror_general('No subcorpus name specified for text deletion in subcorpus-admin.inc.php!');
	else
		$subcorpus_from = mysql_real_escape_string($_GET['subcorpusToDeleteFrom']);

	preg_match_all('/dT_([^&]*)=1/', $_SERVER['QUERY_STRING'], $m, PREG_PATTERN_ORDER);

	if (!empty($m[1]))
	{
		$m[1] = array_map('mysql_real_escape_string', $m[1]);
		subcorpus_remove_texts($subcorpus_from, $m[1]);
	}
	else
		exiterror_general("You didn't specify any files to remove from this subcorpus! Go back and try again.");

	cqpweb_shutdown_environment();
	header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));

	exit();


case 'add_texts':

	if (! (isset($_GET['subcorpusToAddTo']) ) )
		exiterror_general('No subcorpus name specified for adding texts to in subcorpus-admin.inc.php!');
	else
		$subcorpus_to = mysql_real_escape_string($_GET['subcorpusToAddTo']);

	if (!isset($_GET['subcorpusListOfFiles']))
	{
		/* no texts specified, go back to menu */
		;
	}
	else
	{
		$list_of_texts = mysql_real_escape_string(trim(preg_replace('/[\s,]+/', ' ',
			$_GET['subcorpusListOfFiles'])));

		/* get a list of text names that are not real text ids */
		$errors = check_textlist_valid($list_of_texts);

		if ($errors != '__no__errors__')
		{
			header('Location: '
				. url_absolutify('index.php?thisQ=subcorpus&subcorpusListOfFiles='
				. "$list_of_texts&subcorpusFunction=add_texts_to_subcorpus&subcorpusToAddTo="
				. "$subcorpus_to&subcorpusBadTexts=$errors&uT=y"));
			cqpweb_shutdown_environment();
			exit();
		}

		/* OK, we now know the list of names is OK */
		subcorpus_add_texts($subcorpus_to, explode(' ', $list_of_texts));
	}
	header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
	cqpweb_shutdown_environment();
	exit();



case 'process_from_file_list':

	/* work out if we're adding or creating */

	if (  (! isset($_REQUEST['subcorpusToAddTo']) ) && (! isset($subcorpus_name) )  )
		exiterror_general('No subcorpus name specified for adding texts to in subcorpus-admin.inc.php!');
	else
	{
		if ($_REQUEST['subcorpusToAddTo'] !== '!__NEW')
		{
			$subcorpus_to = mysql_real_escape_string($_REQUEST['subcorpusToAddTo']);
			$create = false;
			$function = 'subcorpus_add_texts';
		}
		else
		{
			$subcorpus_to = $subcorpus_name;
			$create = true;
			if ($subcorpus_to == '' || preg_match('/\W/', $subcorpus_to) > 0)
				exiterror_general('The subcorpus name you specified is invalid. Please go back and revise!');
			$function = 'create_subcorpus_list';
		}
	}

	if (isset($_REQUEST['processFileListAddAll']))
	{
		/* "include all texts" was ticked */
		/* the actual list of texts may be too long for HTTP GET, so is stored in the longvalues table */
        $text_list_to_add = preg_replace('/\W/', ' ', longvalue_retrieve($_REQUEST['processFileListAddAll']) );
        header('X-debug: ' + strlen($text_list_to_add));
		if (! $create)
			$text_list_to_add = explode(' ', $text_list_to_add);
	}
	else
	{
		preg_match_all('/aT_([^&]*)=1/', $_SERVER['QUERY_STRING'], $m, PREG_PATTERN_ORDER);

		if (!empty($m[1]))
		{
			foreach($m[1] as &$current)
				$current = mysql_real_escape_string($current);
			if ($create)
				$text_list_to_add = implode(' ', $m[1]);
			else
				$text_list_to_add = $m[1];
		}
		else
			exiterror_general("You didn't specify any files to add to this subcorpus! Go back and try again.");
	}

	/* so, by this point, if crete is true, $text_list_to_add is a string; if false, an array */
	/* and either way we have the function pointer in the "$function" variable */

	$function($subcorpus_to, $text_list_to_add);

	header('Location: ' . url_absolutify('index.php?thisQ=subcorpus&uT=y'));
	cqpweb_shutdown_environment();
	exit();





default:
	exiterror_general('Unrecognised scriptmode for savequery.inc.php!');


} /* end of big switch. Note that ALL case statements exit. */



/* ---------- */
/* END SCRIPT */
/* ---------- */





function subcorpus_admin_check_name($subcorpus_name, $location_url)
{
	if ($subcorpus_name == '' || preg_match('/\W/', $subcorpus_name) > 0)
	{
		cqpweb_shutdown_environment();
		/* call the index script with a pooh-poohed name */
		/* untranslate the restirctions so the boxes will still be filled in */
		header('Location: '. $location_url );
		exit();
	}
}


?>
