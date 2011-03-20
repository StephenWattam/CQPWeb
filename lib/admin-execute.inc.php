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






/* check for an uploaded file */
if (!empty($_FILES))
{
	/* in this case, there will be no $_GET: so create what will be needed */
	$_GET['admFunction'] = 'uploadFile';
	$_GET['uT'] = 'y';
}



/* code block that diverts up the various "actions" that may enter adminhome, so that they go to execute.php */


switch($_GET['admFunction'])
{
	case 'execute':
		/* general case for when it's all already set up */
		require('../lib/execute.inc.php');
		exit();
		
	case 'resetSystemSecurity':
		$_GET['function'] = 'restore_system_security';
		require('../lib/execute.inc.php');
		exit();
		
	case 'uploadFile':
		$_GET['function'] = 'uploaded_file_to_upload_area';
		$_GET['args'] = $_FILES['uploadedFile']['name'] . '#' . $_FILES['uploadedFile']['type'] . '#' 
			. $_FILES['uploadedFile']['size'] . '#' . $_FILES['uploadedFile']['tmp_name'] . '#' 
			. $_FILES['uploadedFile']['error'];
		$_GET['locationAfter'] = 'index.php?thisF=uploadArea&uT=y';
		require('../lib/execute.inc.php');
		exit();
	
	case 'fileView':
		$_GET['function'] = 'uploaded_file_view';
		$_GET['args'] = $_GET['filename'];
		require('../lib/execute.inc.php');
		exit();

	case 'fileCompress':
		$_GET['function'] = 'uploaded_file_gzip';
		$_GET['args'] = $_GET['filename'];
		$_GET['locationAfter'] = 'index.php?thisF=uploadArea&uT=y';
		require('../lib/execute.inc.php');
		exit();

	case 'fileDecompress':
		$_GET['function'] = 'uploaded_file_gunzip';
		$_GET['args'] = $_GET['filename'];
		$_GET['locationAfter'] = 'index.php?thisF=uploadArea&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'fileFixLinebreaks':
		$_GET['function'] = 'uploaded_file_fix_linebreaks';
		$_GET['args'] = $_GET['filename'];
		$_GET['locationAfter'] = 'index.php?thisF=uploadArea&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'fileDelete':
		$_GET['function'] = 'uploaded_file_delete';
		$_GET['args'] = $_GET['filename'];
		$_GET['locationAfter'] = 'index.php?thisF=uploadArea&uT=y';
		require('../lib/execute.inc.php');
		exit();

	case 'installCorpus':
	case 'installCorpusIndexed':
		$_GET['function'] = 'install_new_corpus';
		/* in this case there is no point sending parameters;      */
		/* the function is better off just getting them from $_get */
		$_GET['locationAfter'] = 'XX'; //the function itself sets this 
		require('../lib/execute.inc.php');
		exit();


		/* as with previous, the function gets its "parameters" from _GET */

	
	case 'deleteCorpus':
		if ($_GET['sureyouwantto'] !== 'yes')
		{
			/* default back to non-function-execute-mode */
			foreach ($_GET as $k=>$v) unset($_GET[$k]);
			break;
		}
		$_GET['function'] = 'delete_corpus_from_cqpweb';
		$_GET['args'] = $_GET['corpus'];
		$_GET['locationAfter'] = 'index.php';
		require('../lib/execute.inc.php');
		exit();		
	
	case 'accessRemoveGroup':
		$_GET['function'] = 'deny_group_access_to_corpus';
		$_GET['args'] = $_GET['corpus'] . '#' . $_GET['groupToRemove'];
		$_GET['locationAfter'] 
			= '../' . preg_replace('/\W/', '', $_GET['corpus']) . '/index.php?thisQ=userAccess&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'accessAddGroup':
		$_GET['function'] = 'give_group_access_to_corpus';
		$_GET['args'] = $_GET['corpus'] . '#' . $_GET['groupToAdd'];
		$_GET['locationAfter'] 
			= '../' . preg_replace('/\W/', '', $_GET['corpus']) . '/index.php?thisQ=userAccess&uT=y';
		require('../lib/execute.inc.php');
		exit();
	
	case 'accessUpdateGroupRights':
		$_GET['function'] = 'update_group_access_rights';
		
		/* block potential hack removing suepruser access */
		if ($_GET['group'] == 'superusers')
			exit(); 
		
		$group_update_allow = array();
		foreach ($_GET as $k => $v)
		{
			if (substr($k,0,12) == 'hasAccessTo_')
			{
				if ( (bool)$v  )
					$group_update_allow[] = substr($k,12);
			}
		}
			
		$_GET['args'] = $_GET['group'];		
		$_GET['args'] .= '#' . implode('|', $group_update_allow);
		
		$_GET['locationAfter'] = 'index.php?thisF=groupAccess&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
		
	case 'accessCloneGroupRights':
		$_GET['function'] = 'clone_group_access_rights';
		$_GET['args'] = $_GET['groupCloneFrom'] . '#' . $_GET['groupCloneTo'];
		$_GET['locationAfter'] = 'index.php?thisF=groupAccess&uT=y';
		require('../lib/execute.inc.php');
		exit();
	
		
		
	case 'newUser':
		$_GET['function'] = 'add_new_user';
		$_GET['args'] = trim($_GET['newUsername']) .'#'. trim($_GET['newPassword']) .'#'. trim($_GET['newEmail']) ;
		$_GET['locationAfter'] = 'index.php?thisF=userAdmin&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'newBatchOfUsers':
		$_GET['function'] = 'add_batch_of_users';
		$_GET['args'] = trim($_GET['newUsername']) .'#'. $_GET['sizeOfBatch'] . '#' . trim($_GET['newPassword']) 
			. '#' . trim($_GET['batchAutogroup']);
		$_GET['args'] .= ($_GET['newPasswordUseRandom'] == '1' ? '#true' : '');
		$_GET['locationAfter'] = 'index.php?thisF=userAdmin&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'deleteUser':
		$_GET['function'] = 'delete_user';
		$_GET['args'] = $_GET['userToDelete'] ;
		$_GET['locationAfter'] = 'index.php?thisF=userAdmin&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'deleteUserBatch':
		$_GET['function'] = 'delete_user_batch';
		$_GET['args'] = $_GET['userBatchToDelete'] ;
		$_GET['locationAfter'] = 'index.php?thisF=userAdmin&uT=y';
		require('../lib/execute.inc.php');
		exit();

	case 'addUserToGroup':
		$_GET['function'] = 'add_user_to_group';
		$_GET['args'] = $_GET['userToAdd'] .'#' . $_GET['groupToAddTo'] ;
		$_GET['locationAfter'] = 'index.php?thisF=groupAdmin&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'removeUserFromGroup':
		$_GET['function'] = 'remove_user_from_group';
		$_GET['args'] = $_GET['userToRemove'] .'#' . $_GET['groupToRemoveFrom'] ;
		$_GET['locationAfter'] = 'index.php?thisF=groupAdmin&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'addSystemMessage':
		$_GET['function'] = 'add_system_message';
		$_GET['args'] = $_GET['systemMessageHeading']. '#' . $_GET['systemMessageContent'];
		$_GET['locationAfter'] = 'index.php?thisF=systemMessages&uT=y';
		require('../lib/execute.inc.php');
		exit();

	case 'variableMetadata':
		$_GET['function'] = 'add_variable_corpus_metadata';
		$_GET['args'] = $_GET['corpus'] . '#' . $_GET['variableMetadataAttribute']. '#' 
			. $_GET['variableMetadataValue'];
		$_GET['locationAfter'] = '../'. $_GET['corpus'] .'/index.php?thisQ=manageMetadata&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'regenerateCSS':
		$_GET['function'] = 'cqpweb_regenerate_css_files';
		$_GET['locationAfter'] = 'index.php?thisF=skins&uT=y';
		require('../lib/execute.inc.php');
		exit();
	
	case 'transferStylesheetFile':
		$_GET['function'] = 'cqpweb_import_css_file';
		if (!isset($_GET['cssFile']))
		{
			header("Location: index.php?thisF=skins&uT=y");
			exit();
		}	
		$_GET['args'] = $_GET['cssFile'];
		$_GET['locationAfter'] = 'index.php?thisF=skins&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	case 'updateCategoryDescriptions':
		$update_text_metadata_values_descriptions_info['corpus'] = $_GET['corpus'];
		$update_text_metadata_values_descriptions_info['actions'] = array();
		foreach($_GET as $key => &$val_desc)
		{
			if (substr($key, 0, 5) !== 'desc-')
				continue;
			list($junk, $field, $val_handle) = explode('-', $key);
			$update_text_metadata_values_descriptions_info['actions'][] = array (
				'field_handle' => $field,
				'value_handle' => $val_handle,
				'new_desc' => $val_desc
				);
		}
		$_GET['function'] = 'update_text_metadata_values_descriptions';
		$_GET['locationAfter'] = '../' . $_GET['corpus'] .'/index.php?thisQ=manageCategories&uT=y';
		require('../lib/execute.inc.php');
		exit();
	
	case 'updateCorpusMetadata':
		$update_corpus_metadata_info['corpus'] = $_GET['corpus'];
		$update_corpus_metadata_info['visible'] = $_GET['updateVisible'];
		// These 3 variables no longer set from this form
		//$update_corpus_metadata_info['primary_classification_field'] = $_GET['updatePrimaryClassification'];
		//$update_corpus_metadata_info['primary_annotation'] = $_GET['updatePrimaryAnnotation'];
		//$update_corpus_metadata_info['external_url'] = $_GET['updateURL'];
		$_GET['function'] = 'update_corpus_metadata_fixed';
		$_GET['locationAfter'] = 'index.php?thisF=showCorpora&uT=y';
		require('../lib/execute.inc.php');
		exit();
		
	
	case 'createMetadataFromFile':
		$create_text_metadata_for_info = array();
		$create_text_metadata_for_info['filename'] = $_GET['dataFile'];
		$create_text_metadata_for_info['file_should_be_deleted'] = false;
		$create_text_metadata_for_info['corpus'] = $_GET['corpus'];
		$create_text_metadata_for_info['primary_classification'] = $_GET['primaryClassification'];
		$create_text_metadata_for_info['fields'] = array();
		$create_text_metadata_for_info['field_count'] = (int)$_GET['fieldCount'];
		for ($i = 1; $i <= $create_text_metadata_for_info['field_count']; $i++)
		{
			if ($_GET["fieldHandle$i"] == '')
				continue;
			$create_text_metadata_for_info['fields'][$i] = array(
					'handle' => $_GET["fieldHandle$i"],
					'description' => $_GET["fieldDescription$i"],
					'classification' => (bool)$_GET["isClassificationField$i"]
				);
		}
		$create_text_metadata_for_info['do_automatic_metadata_setup'] = (bool) $_GET['createMetadataRunFullSetupAfter'];
		$_GET['function'] = 'create_text_metadata_for';
		$_GET['locationAfter'] = '../' . $_GET['corpus'] .'/index.php?thisQ=manageMetadata&uT=y';
		require('../lib/execute.inc.php');
		exit();

	case 'createMetadataFromXml':
		$create_text_metadata_for_info = array();
		$create_text_metadata_for_info['corpus'] = $_GET['corpus'];
		$create_text_metadata_for_info['filename'] = "___createMetadataFromXml_{$_GET['corpus']}";
		$create_text_metadata_for_info['file_should_be_deleted'] = true;
		$create_text_metadata_for_info['primary_classification'] = $_GET['primaryClassification'];
		$create_text_metadata_for_info['fields'] = array(false);
		/* note the dummy value for [fields][0], becaue reading from here starts at 1 */

		foreach($_GET as $k => &$v)
		{
			if ( substr($k, 0, 24) != 'createMetadataFromXmlUse')
				continue;
			if ($v !== '1')
				continue;
				
			/* OK, we know we've found a field handle that we are supposed to use. */
			list(, $handle) = explode('_', $k, 2);
			
			$field_list[] = $handle;
			
			$create_text_metadata_for_info['fields'][] = array(
					'handle' => $handle,
					'description' => $_GET["createMetadataFromXmlDescription_$handle"],
					'classification' => (bool)$_GET["isClassificationField_$handle"]
				);
		}
		$create_text_metadata_for_info['field_count'] = count($create_text_metadata_for_info['fields']);
		$create_text_metadata_for_info['do_automatic_metadata_setup'] = (bool) $_GET['createMetadataRunFullSetupAfter'];

		$fields_to_show = '';
		foreach($field_list as &$f)
			$fields_to_show .= ', match ' . $f;
		$_GET['args'] = $fields_to_show;
		$_GET['function'] = 'create_text_metadata_for_from_xml';
		$_GET['locationAfter'] = '../' . $_GET['corpus'] .'/index.php?thisQ=manageMetadata&uT=y';
		require('../lib/execute.inc.php');
		exit();

	case 'clearMetadataTable':
	 	if ($_GET['clearMetadataAreYouReallySure'] != 'yesYesYes')
	 	{
			$_GET['function'] = 'exiterror_general';
			$_GET['args'] = "CQPweb won't delete the metadata unless you confirm you're certain!" ;
	 	}
	 	else
	 	{	
			$_GET['function'] = 'delete_text_metadata_for';
			$_GET['locationAfter'] = '../' . $_GET['corpus'] .'/index.php?thisQ=manageMetadata&uT=y';
			$_GET['args'] = $_GET['corpus'] ;
	 	}
		require('../lib/execute.inc.php');
		exit();
	
	
	case 'newMappingTable':
		if(strpos($_GET['newMappingTableCode'], '#') !== false)
		{
			$_GET['args'] = "You cannot use the \"hash\" character in a mapping table.";
			// Actually this is a lie. You can, should you really want to do something that bonkers.
			// the problem is that then it can't be passed to execute.inc.php
			// because hash is an argument separator.
			// TODO: clean this up.
			$_GET['function'] = 'exiterror_fullpage';
		}
		else
		{
			$_GET['function'] = 'add_tertiary_mapping_table';
			$_GET['locationAfter'] = 'index.php?thisF=mappingTables&showExisting=1&uT=y';
			$_GET['args'] = $_GET['newMappingTableId'].'#'.$_GET['newMappingTableName'].'#'.$_GET['newMappingTableCode'] ;
		}
		require('../lib/execute.inc.php');
		exit();
		
	default:
		/* break and fall through to the rest of adminhome.inc.php */
		break;
}
?>