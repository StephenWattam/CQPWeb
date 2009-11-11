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




/* just a little object to hold info on the install corpus parsed from GET */
class corpus_install_info
{
	public $corpus_mysql_name;
	public $corpus_cwb_name;
	
	public $already_cwb_indexed;
	
	public $directory_override;
	
	public $script_is_r2l;

	public $corpus_metadata_fixed_mysql_insert;
	
	public $css_url;
	public $description;
	
	public $p_attributes;
	public $p_attributes_mysql_insert;
	public $primary_p_attribute;
	public $s_attributes;
	public $file_list;
	
	
	/* constructor is sole public function */
	function __construct()
	{
		/* first thing: establish which mode we are dealing with */
		$this->already_cwb_indexed = ($_GET['admFunction'] === 'installCorpusIndexed'); 
		
		
		/* get each thing from GET */
		/***************************/
		
		$this->corpus_mysql_name = cqpweb_handle_enforce($_GET['corpus_mysql_name']);
		$this->corpus_cwb_name = strtolower(cqpweb_handle_enforce($_GET['corpus_cwb_name']));
		$this->script_is_r2l = ( $_GET['corpus_scriptIsR2L'] === '1' );
				
		if ( $this->corpus_cwb_name === '' || $this->corpus_mysql_name === '' )
			exiterror_fullpage("You must specify a corpus name using only letter, numbers and underscore",
				__FILE__, __LINE__);
		
		if (get_magic_quotes_gpc() == 0)
			$_GET['corpus_description'] = addcslashes($_GET['corpus_description'], "'");
		$this->description = $_GET['corpus_description'];
		
		/*********************/
		
		if ($this->already_cwb_indexed)
		{
			/* check that the corpus registry file exists, that the corpus datadir exists */
			/* in the process, getting the "override" directories, if they exist */
			
			$use_normal_regdir = (bool)$_GET['corpus_useDefaultRegistry'];
			
			if ($use_normal_regdir)
			{
				global $cwb_registry;
				$registry_file = "/$cwb_registry/{$this->corpus_cwb_name}";
			}
			else
			{
				$this->directory_override['reg_dir'] = trim(trim($_GET['corpus_cwb_registry_folder']), '/');
				$registry_file = '/' . $this->directory_override . '/' . $this->corpus_cwb_name;
			}
			if (!is_file($registry_file))
				exiterror_fullpage("The specified CWB corpus does not seem to exist in that location.",
					__FILE__, __LINE__);		
			
			$regdata = file_get_contents($registry_file);
			
			preg_match("/HOME\s+\/([^\n]+)\n/", $regdata, $m);
			global $cwb_datadir;
			if ($m[1] == $cwb_datadir)
				$test_datadir = '/' . $cwb_datadir;
			else
			{
				$this->directory_override['data_dir'] = $m[1];
				$test_datadir = '/' . $this->directory_override['data_dir'];
			}
			
			if (!is_dir($test_datadir))
				exiterror_fullpage("The specified data directory could not be found.",
					__FILE__, __LINE__);
			
			/* check that <text> and <text_id> are s-attributes */
			if (preg_match('/\bSTRUCTURE text\b/', $regdata) < 1 
				|| preg_match('/\bSTRUCTURE text_id\b/', $regdata) < 1)
				exiterror_fullpage("Pre-indexed corpora require s-attributes text and text_id!!",
					__FILE__, __LINE__);
		}
		else /* ie if this is NOT an already indexed corpus */
		{
			global $cqpweb_uploaddir;
			
			preg_match_all('/includeFile=([^&]*)&/', $_SERVER['QUERY_STRING'], $m, PREG_PATTERN_ORDER);
			
			$this->file_list = array();
			
			foreach($m[1] as $file)
			{
				$path = "/$cqpweb_uploaddir/$file";
				if (is_file($path))
					$this->file_list[] = $path;
				else
					exiterror_fullpage("One of the files you selected seems to have been deleted.",
						__FILE__, __LINE__);
			}
			
			if (empty($this->file_list))
				exiterror_fullpage("You must specify at least one file to include in the corpus!",
					__FILE__, __LINE__);		
		}

		

		/*********************/
		
		/* p-attributes */
		if ($this->already_cwb_indexed)
		{
			preg_match_all("/ATTRIBUTE\s+(\w+)\n/", $regdata, $m, PREG_PATTERN_ORDER);
			foreach($m[1] as $p)
			{
				if ($p == 'word')
					continue;
				$this->p_attributes[] = $p;
				$this->p_attributes_mysql_insert[] = $this->get_p_att_mysql_insert($p, '', '', '');
				
				/* note that no "primary" annotation is created if we are loading in an existing corpus */
				/* instead, the primary annotation can be set later */
				$this->corpus_metadata_fixed_mysql_insert =
					"insert into corpus_metadata_fixed (corpus, primary_annotation) 
					values ('{$this->corpus_mysql_name}', NULL)";
			}
		}
		else
			$this->load_p_atts_based_on_get();

		/*********************/

		
		/* s-attributes */
		/* have to have this one! */
		$this->s_attributes[] = 'text:0+id';
		
		if ($_GET['withDefaultSs'] === '1')
		{
			$this->s_attributes[] = 's';
		}
		else
		{
			foreach(array(1,2,3,4,5,6) as $q)
			{
				if (preg_match('/^\w+:0\+[^+]+(\+[^+]+)*$/', $_GET["customS$q"]) > 0)
					$cand = $_GET["customS$q"];
				else
					$cand = cqpweb_handle_enforce($_GET["customS$q"]);
				if ($cand === '')
					continue;
				else
					$this->s_attributes[] = $cand;
			}
		}

		
		/*********************/

		if ($_GET['cssCustom'] == 1)
		{
			// I am no longer certain if this will work properly??? haven't tried yet, should do so
			if (get_magic_quotes_gpc() == 0)
				$_GET['cssCustomUrl'] = addcslashes($_GET['cssCustomUrl'], "'");
			$this->css_url = addcslashes($_GET['cssCustomUrl'], "'");
		}
		else
		{
			$this->css_url = "../css/{$_GET['cssBuiltIn']}";
			if (! is_file($this->css_url))
				$this->css_url = '';
		}
		
		/*********************/
		
		
		
		
	} /* end constructor */
	
	
	private function load_p_atts_based_on_get()
	{
		if ($_GET['withDefaultPs'] === '1')
		{
			$this->p_attributes[] = 'pos';
			$this->p_attributes_mysql_insert[] 
				= $this->get_p_att_mysql_insert('pos', 'Part-of-speech tag', 'CLAWS7 Tagset', 
				'http://ucrel.lancs.ac.uk/claws7tags.html');
			$this->p_attributes[] = 'hw';
			$this->p_attributes_mysql_insert[] 
				= $this->get_p_att_mysql_insert('hw', 'Lemma', 'Lemma', '');
			$this->p_attributes[] = 'semtag';
			$this->p_attributes_mysql_insert[] 
				= $this->get_p_att_mysql_insert('semtag', 'Semantic tag', 'USAS Tagset', 
				'http://ucrel.lancs.ac.uk/usas/');
			$this->p_attributes[] = 'class';
			$this->p_attributes_mysql_insert[] 
				= $this->get_p_att_mysql_insert('class', 'Simple tag', 'Oxford Simplified Tags', 
				'http://www.natcorp.ox.ac.uk/XMLedition/URG/codes.html#klettpos');
			$this->p_attributes[] = 'lemma';
			$this->p_attributes_mysql_insert[] 
				= $this->get_p_att_mysql_insert('lemma', 'Tagged lemma', 'Lemma/OST', 
				'http://www.natcorp.ox.ac.uk/XMLedition/URG/codes.html#klettpos');
			
			$this->primary_p_attribute = 'pos';
			
			$this->corpus_metadata_fixed_mysql_insert =
				"insert into corpus_metadata_fixed 
				(corpus, primary_annotation, secondary_annotation, tertiary_annotation, 
				tertiary_annotation_tablehandle, combo_annotation) 
				values 
				('{$this->corpus_mysql_name}', 'pos', 'hw', 'class', 'oxford_simplified_tags', 'lemma')";			
		}
		else
		{
			foreach(array(1,2,3,4,5,6) as $q)
			{
				$cand = preg_replace('/\W/', '', $_GET["customPHandle$q"]);
				if ($cand === '')
					continue;
				else
				{
					$this->p_attributes[] = $cand;
	
					$this->p_attributes_mysql_insert[] = $this->get_p_att_mysql_insert(
						$cand, 
						mysql_real_escape_string($_GET["customPDesc$q"]), 
						mysql_real_escape_string($_GET["customPTagset$q"]), 
						mysql_real_escape_string($_GET["customPurl$q"]));
				}
			}
			
			if (isset($_GET['customPPrimary']))
				$this->primary_p_attribute = 
					preg_replace('/\W/', '', $_GET["customPHandle{$_GET['customPPrimary']}"]);
			else
				$this->primary_p_attribute = NULL;
			
			
			if (isset ($this->primary_p_attribute))
				$this->corpus_metadata_fixed_mysql_insert =
					"insert into corpus_metadata_fixed (corpus, primary_annotation) 
					values ('{$this->corpus_mysql_name}', '{$this->primary_p_attribute}')";
			else
				$this->corpus_metadata_fixed_mysql_insert =
					"insert into corpus_metadata_fixed (corpus, primary_annotation) 
					values ('{$this->corpus_mysql_name}', NULL)";
		}
	
	} /* end of function */


	
	private function get_p_att_mysql_insert($tag_handle, $description, $tagset, $url)
	{
		/* assumes everything alreadey made safe with mysql_real_escape_string or equiv */
		return
			"insert into annotation_metadata (corpus, handle, description, tagset, external_url) values 
			('{$this->corpus_mysql_name}', '$tag_handle', '$description', '$tagset', '$url')";
	}

}/* end class (corpus_install_info) */





function install_new_corpus()
{
	global $path_to_cwb;
	global $cqpweb_accessdir;
	global $cwb_datadir;
	global $cwb_registry;
	global $cqp_tempdir;
	
	global $mysql_link;
	
	$info = new corpus_install_info;

	$at_least_one_P = ( empty($info->p_attributes) ? '' : ' -P ');
	/* don't need the equiv for S because there always is one: text:0+id */

	
	$corpus = $info->corpus_cwb_name;
	$CORPUS = strtoupper($corpus);


	/* mysql table inserts */	
	/* these come first because if corpus already exists, they will cause an abort */
	if (! empty($info->p_attributes_mysql_insert))
	{
		foreach ($info->p_attributes_mysql_insert as &$s)
		{
			$result = mysql_query($s, $mysql_link);
			if ($result == false) 
				exiterror_mysqlquery(mysql_errno($mysql_link), 
					mysql_error($mysql_link), __FILE__, __LINE__);
		}
	}
	$result = mysql_query($info->corpus_metadata_fixed_mysql_insert, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);


	if ($info->already_cwb_indexed)
		;
	else
	{
		/* cwb-create the file */
		$datadir = "/$cwb_datadir/$corpus";
	
		if (is_dir($datadir))
			recursive_delete_directory($datadir);
		mkdir($datadir, 0775);
	
	
		/* run the commands one by one */
	
		$encode_output_file = "/$cqp_tempdir/{$corpus}__php-cwb-encode.txt";
	
		$encode_command =  "/$path_to_cwb/cwb-encode -xsB -d $datadir -f " 
			. implode(' -f ', $info->file_list)
			. " -R /$cwb_registry/$corpus "
			. $at_least_one_P
			. (empty($info->p_attributes) ? '' : implode(' -P ', $info->p_attributes))
			. ' -S ' . implode(' -S ', $info->s_attributes)
			. " > $encode_output_file";
	
		exec($encode_command);
		
		chmod("/$cwb_registry/$corpus", 0664);
	
		$make_output_file = "/$cqp_tempdir/{$corpus}__php-cwb-make.txt";
		
		exec("/$path_to_cwb/cwb-makeall -r /$cwb_registry -V $CORPUS > $make_output_file");
	
	
		$huffcode_output_file = "/$cqp_tempdir/{$corpus}__php-cwb-huffcode.txt";
		
		exec("/$path_to_cwb/cwb-huffcode -A $CORPUS > $huffcode_output_file");
		exec("/$path_to_cwb/cwb-compress-rdx -A $CORPUS >> $huffcode_output_file" );
	
	
		$huffblob = file_get_contents($huffcode_output_file);
		
		preg_match_all('/!! You can delete the file <(.*)> now/', $huffblob, $matches, PREG_PATTERN_ORDER );
	
		foreach($matches[1] as $del)
			unlink($del);
	
		/* clear the trash */
	
		unlink($encode_output_file);
		unlink($make_output_file);
		unlink($huffcode_output_file);
	}

		
	
		
	/* make a web dir and set up with superuser access */
	$newdir = realpath("..") . '/' . $info->corpus_mysql_name;
	
	if (file_exists($newdir))
	{
		if (is_dir($newdir))
			recursive_delete_directory($newdir);
		else
			unlink($newdir);
	}

	mkdir($newdir, 0775);
	
	$settings = new apache_htaccess($newdir);
	$settings->set_AuthName('CQPweb');
	$settings->set_path_to_password_file("/$cqpweb_accessdir/.htpasswd");
	$settings->set_path_to_groups_file("/$cqpweb_accessdir/.htgroup");
	$settings->allow_group('superusers');
	$settings->save();
	chmod("$newdir/.htaccess", 0664);
	
	
	 
	/* create the script files in that folder */
	foreach (array( 'collocation', 'concordance', 'context',
					'distribution', 'execute', 'freqlist',
					'freqtable-compile', 'help', 'index',
					'keywords', 'redirect', 'subcorpus-admin',
					'textmeta') as $c)
	{
		file_put_contents("$newdir/$c.php", "<?php require('../lib/$c.inc.php'); ?>");
		chmod("$newdir/$c.php", 0775);
	}
	
	/* write a settings.inc.php file */
	install_create_settings_file("$newdir/settings.inc.php", $info);

	
	/* make sure execute.php takes us to a nice results screen */
	$_GET['locationAfter'] 
		= "index.php?thisF=installCorpusDone&newlyInstalledCorpus={$info->corpus_mysql_name}&uT=y";

}


function install_create_settings_file($filepath, $info)
{
	$data = "<?php\n\n"
		. "\$corpus_title = '{$info->description}';\n"
		. "\$corpus_sql_name = '{$info->corpus_mysql_name}';\n"
		. "\$corpus_cqp_name = '" . strtoupper($info->corpus_cwb_name) . "';\n"
		. "\$css_path = '{$info->css_url}';\n"
		. ($info->script_is_r2l ? "\$corpus_main_script_is_r2l = true;\n" : '')
		. (empty($info->directory_override['reg_dir']) ? '' : 
			"\$this_corpus_directory_override['reg_dir'] = '{$info->directory_override['reg_dir']}';\n")
		. (empty($info->directory_override['data_dir']) ? '' : 
			"\$this_corpus_directory_override['data_dir'] = '{$info->directory_override['data_dir']}';\n")
		. '?>';
	file_put_contents($filepath, $data);
	chmod($filepath, 0775);
}



// TODO -- check this against cwb_uncreate_corpus and prevent duplication of functionality
function delete_corpus_from_cqpweb($corpus)
{
	global $mysql_link;
	global $cwb_registry;
	global $cwb_datadir;
	global $corpus_cqp_name;
	$corpus = mysql_real_escape_string($corpus);

	/* get the cwb name of the corpus, etc. */
	include("../$corpus/settings.inc.php");
	if (isset($this_corpus_directory_override['reg_dir']))
		$cwb_registry = $this_corpus_directory_override['reg_dir'];
	if (isset($this_corpus_directory_override['data_dir']))
		$cwb_datadir = $this_corpus_directory_override['data_dir'];	
	
	$corpus_cwb_lower = strtolower($corpus_cqp_name);
	
	/* check the corpus is actually there to delete */
	$sql_query = "select corpus, cwb_external from corpus_metadata_fixed where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	if (mysql_num_rows($result) < 1)
		return;
	
	/* do we also want to delete the CWB data? */
	list($junk, $cwb_external) = mysql_fetch_row($result);
	$also_delete_cwb = !( (bool)$cwb_external);
	

	/* delete all saved queries, subcorpus frequency tables, and dbs associated with this corpus */
	$sql_query = "select query_name from saved_queries where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	while (($r = mysql_fetch_row($result)) !== false)
		delete_cached_query($r[0]);
	$sql_query = "select dbname from saved_dbs where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	while (($r = mysql_fetch_row($result)) !== false)
		delete_db($r[0]);
	$sql_query = "select freqtable_name from saved_freqtables where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	while (($r = mysql_fetch_row($result)) !== false)
		delete_freqtable($r[0]);
	
	/* delete the actual subcorpora */
	$sql_query = "delete from saved_subcorpora where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);	


	/* delete the entries from corpus_metadata_fixed / variable / annotation */
	$sql_query = "delete from corpus_metadata_fixed where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	$sql_query = "delete from corpus_metadata_variable where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	$sql_query = "delete from annotation_metadata where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	/* clear the text metadata (see below) */
	delete_text_metadata_for($corpus);

	/* delete the web directory */
	recursive_delete_directory("../$corpus");
	
	if ($also_delete_cwb)
	{
		/* delete the CWB registry and data */
		unlink("/$cwb_registry/$corpus_cwb_lower");
		recursive_delete_directory("/$cwb_datadir/$corpus_cwb_lower");
		
		/* if they exist, delete the CWB registry and data for its __freq */
		if (file_exists("/$cwb_registry/{$corpus_cwb_lower}__freq"))
		{
			unlink("/$cwb_registry/{$corpus_cwb_lower}__freq");
			recursive_delete_directory("/$cwb_datadir/{$corpus_cwb_lower}__freq");
		}
	}
}





// TODO: move this to a different file, so I can put it in user scripts too (e.g. for uploading an annotated query
function upload_file_to_upload_area($original_name, $file_type, $file_size, $temp_path, $error_code)
{
	/**
	 * CQPweb assumes the following settings in php.ini
	 * 
	 * upload_max_file_size = 20M
	 * memory_limit = 25M
	 * post_max_size = 22M
	 * max_execution_time = 60
	 * 
	 */

	global $cqpweb_uploaddir;
	global $username;

	/* convert back to int: execute.inc.php may have turned it to a string */
	$error_code = (int)$error_code;
	if ($error_code !== UPLOAD_ERR_OK)
		exiterror_fullpage('The file did not upload correctly! Please try again.', __FILE__, __LINE__);
	
	/* only superusers can upload REALLY BIG files */
	/* maybe make this variable - a user setting? */
	if (!user_is_superuser($username))
	{
		/* normal user limit is 2MB */
		if ($file_size > 2097152)
			exiterror_fullpage('The file did not upload correctly! Please try again.', __FILE__, __LINE__);
	}
	
	/* find a new name - a file that does not exist */
	for ($filename = basename($original_name); 1 ; $filename = '_' . $filename)
	{
		$new_path = "/$cqpweb_uploaddir/$filename";
		if (file_exists($new_path) === false)
			break;
	}
	
	if (move_uploaded_file($temp_path, $new_path)) 
		chmod($new_path, 0664);
	else
		exiterror_fullpage("The file could not be processed! Possible file upload attack", __FILE__, __LINE__);
}


function uploaded_file_fix_linebreaks($filename)
{
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);
	
	$data = file_get_contents($path);

	$data = str_replace("\xd\xa", "\xa", $data);
	
	$intermed_path = "/$cqpweb_uploaddir/__________uploaded_file_fix_linebreaks________temp_________datoa__________.___";
	
	file_put_contents($intermed_path, $data);

	unlink($path);

	rename($intermed_path, $path);
	chmod($path, 0777);
}


function uploaded_file_delete($filename)
{	
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);
	
	unlink($path);
}

function uploaded_file_gzip($filename)
{	
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);

	$zip_path = $path . '.gz';
	
	$in_file = fopen($path, "rb");
	if (!$out_file = gzopen ($zip_path, "wb"))
	{
		exiterror_fullpage('Your request could not be completed - compressed file could not be opened.', 
			__FILE__, __LINE__);
	}

	php_execute_time_unlimit();
	while (!feof ($in_file)) 
	{
		$buffer = fgets($in_file, 4096);
		gzwrite($out_file, $buffer, 4096);
	}
	php_execute_time_relimit();

	fclose ($in_file);
	gzclose ($out_file);
	
	unlink($path);
	chmod($zip_path, 0777);
}


function uploaded_file_gunzip($filename)
{
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);
	
	if (preg_match('/(.*)\.gz$/', $filename, $m) > 1)
		exiterror_fullpage('Your request could not be completed - that file does not appear to be compressed.', 
			__FILE__, __LINE__);

	$unzip_path = "/$cqpweb_uploaddir/{$m[1]}";
	
	$in_file = gzopen($path, "rb");
	$out_file = fopen($unzip_path, "wb");

	php_execute_time_unlimit();
	while (!gzeof($in_file)) 
	{
		$buffer = gzread($in_file, 4096);
		fwrite($out_file, $buffer, 4096);
	}
	php_execute_time_relimit();

	gzclose($in_file);
	fclose ($out_file);
			
	unlink($path);
	chmod($unzip_path, 0777);
}

function uploaded_file_view($filename)
{
	global $cqpweb_uploaddir;
	
	$path = "/$cqpweb_uploaddir/$filename";

	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);

	$fh = fopen($path, 'r');
	
	$bytes_counted = 0;
	$data = '';
	
	while ((!feof($fh)) && $bytes_counted <= 102400)
	{
		$line = fgets($fh, 4096);
		$data .= $line;
		$bytes_counted += strlen($line);
	}

	fclose($fh);
	
	$data = htmlspecialchars($data);
	
	?>
	<html>
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>CQPweb: viewing uploaded file</title>
		</head>
		<body>
			<h1>Viewing uploaded file <i><?php echo $filename;?></i></h1>
			<p>NB: for very long files only the first 100K is shown
			<hr/>
			<pre>
			<?php echo "\n" . $data; ?>
			</pre>
		</body>
	</html>
	<?php
	exit();
}

function restore_system_security()
{
	/* four folders: adm, css, doc, lib; plus root folder */
	
	/* three of those are easy */
	if (file_exists('../.htaccess'))
		unlink('../.htaccess');
	if (file_exists('../doc/.htaccess'))
		unlink('../doc/.htaccess');
	if (file_exists('../css/.htaccess'))
		unlink('../css/.htaccess');
	
	/* adm needs a standard .htaccess which just allows superusers */
	if (file_exists('../adm/.htaccess'))
		unlink('../adm/.htaccess');
	$adm = get_apache_object(realpath('../adm'));
	$adm->allow_group('superusers');
	$adm->save();
	chmod("../adm/.htaccess", 0664);
	unset($adm);
	
	/* lib needs the same, but easier cos NO ONE allowed */
	if (file_exists('../lib/.htaccess'))
		unlink('../lib/.htaccess');
	file_put_contents('../lib/.htaccess', "deny from all");
	chmod("../lib/.htaccess", 0664);
	/* but javascript within lib needs to be allowed */
	if (file_exists('../lib/javascript/.htaccess'))
		unlink('../lib/javascript/.htaccess');
	file_put_contents('../lib/javascript/.htaccess', "allow from all");
	chmod("../lib/javascript/.htaccess", 0664);
	
	/* check that the autoconfig script in the root folder is GONE */
	if (file_exists('../cqpweb-autoconfig.php'))
		unlink('../cqpweb-autoconfig.php');
	if (file_exists('../cqpweb-autoconfig.php.gz'))
		unlink('../cqpweb-autoconfig.php.gz');
}


function add_new_user($username, $password, $email = NULL)
{
	$apache = get_apache_object('nopath');
	
	$username = preg_replace('/\W/', '', $username);
	$password = preg_replace('/\W/', '', $password);
	
	if ($username === '' || $password === '')
		exiterror_fullpage("Usernames and passwords can only contain letters, numbers and underscores",
			__FILE__, __LINE__);
	
	$apache->new_user($username, $password);
	
	if (isset($email))
	{
		create_user_record($username);
		update_user_setting($username, 'email', mysql_real_escape_string($email));
	}
}

function add_batch_of_users($username_root, $number_in_batch, $password, $autogroup, $different_passwords = false)
{
	global $create_password_function;
	
	$apache = get_apache_object('nopath');
	
	$autogroup = preg_replace('/\W/', '', $autogroup);
	$password = preg_replace('/\W/', '', $password);
	if (autogroup !== '')
	{
		$group_list = $apache->list_groups();
		if (!in_array($autogroup, $group_list))
			$apache->new_group($autogroup);
	}
	
	$number_in_batch = (int)$number_in_batch;
	
	/* get passwords and begin the text-file write */
	if ($different_passwords)
	{
		$password_list = $create_password_function($number_in_batch+2);
		header("Content-Type: text/plain; charset=utf-8");
		header("Content-disposition: attachment; filename=passwords_for_{$username_root}_batch.txt");
		echo "The following user accounts have been created.\n\nUsername\tPassword\n\n";
	}
	
	for ($i = 1 ; $i <= $number_in_batch; $i++)
	{
		$this_password = ($different_passwords ? $password_list[$i] : $password);
		$apache->new_user("$username_root$i", $this_password);
		if (autogroup !== '')
			$apache->add_user_to_group("$username_root$i", $autogroup);
		if ($different_passwords)
			echo "$username_root$i\t$this_password\n";
	}
}

function delete_user($user)
{
	$apache = get_apache_object('nopath');
	$user = preg_replace('/\W/', '', $user);
	if ($user === '')
		return;
	$apache->delete_user($user);
}


/**
 * delete all usernames consisting of $prefix plus a string of one or more digits 
 */
function delete_user_batch($prefix)
{
	$apache = get_apache_object('nopath');
	$prefix = preg_replace('/\W/', '', $prefix);
	if ($prefix === '')
		return;
	foreach ($apache->list_users() as $user)
	{
		if (preg_match("/^$prefix\d+$/", $user) > 0)
			$apache->delete_user($user);
	}	
}


function add_user_to_group($user, $group)
{
	$apache = get_apache_object('nopath');

	
	$user = preg_replace('/\W/', '', $user);
	$group = preg_replace('/\W/', '', $group);
	
	if ($user === '' || $group === '')
		exiterror_fullpage("Invalid username or group name!",
			__FILE__, __LINE__);

	$apache->add_user_to_group($user, $group);
}

function remove_user_from_group($user, $group, $superuseroverride = false)
{
	/* block the removal of users from "superusers" unless specific override given */
	if ($group == 'superusers' && $superuseroverride !== true)
		return;
	
	$apache = get_apache_object('nopath');
	$apache->delete_user_from_group($user, $group);
}

function delete_group($group)
{
	/* block the removal of "superusers" */
	if ($group == 'superusers')
		return;
	$apache = get_apache_object('nopath');
	$apache->delete_group($group);
}

function create_group($group)
{
	$apache = get_apache_object('nopath');
	$apache->new_group($group);
	/* note the apache object does input sanitation so no need to bother here */
}


function deny_group_access_to_corpus($corpus, $group)
{
	$group = preg_replace('/\W/', '', $group);
	
	if ($corpus == '' || $group == '')
		return;
	if (! file_exists("../$corpus/.htaccess"))
		return;
	/* having got here, we know the $corpus variable is OK */
	
	/* don't check group in the same way -- we want to be  */
	/* able to disallow access to nonexistent groups       */

	$apache = get_apache_object(realpath("../$corpus"));
	$apache->load();
	$apache->disallow_group($group);
	$apache->save();
}

function give_group_access_to_corpus($corpus, $group)
{
	if ($corpus == '' || $group == '')
		return;
	if (! file_exists("../$corpus/.htaccess"))
		return;
	/* having got here, we know the $corpus variable is OK */

	$apache = get_apache_object(realpath("../$corpus"));
	$group_list = $apache->list_groups();
	if (!in_array($group, $group_list))
		return;
	/* and having survived that, we know group is OK too */
	
	$apache->load();
	$apache->allow_group($group);
	$apache->save();
}



function get_apache_object($path_to_web_directory)
{
	global $path_to_apache_utils;
	global $cqpweb_accessdir;
	
	$obj = new apache_htaccess();
		
	$obj->set_AuthName('CQPweb');
	
	$obj->set_path_to_apache_password_utility_directory("/$path_to_apache_utils");
	$obj->set_path_to_groups_file("/$cqpweb_accessdir/.htgroup");
	$obj->set_path_to_password_file("/$cqpweb_accessdir/.htpasswd");
	
	$obj->set_path_to_web_directory($path_to_web_directory);

	return $obj;
}


function update_text_metadata_values_descriptions()
{
	global $update_text_metadata_values_descriptions_info;
	global $mysql_link;

	foreach($update_text_metadata_values_descriptions_info['actions'] as &$current_action)
	{
		$sql_query = "update text_metadata_values set description='{$current_action['new_desc']}' 
			where corpus = '{$update_text_metadata_values_descriptions_info['corpus']}' 
			and field_handle = '{$current_action['field_handle']}'
			and handle = '{$current_action['value_handle']}'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
	}
}

/* NB there's a function in metadata.inc.php that does something very similar to this */
/* but this one takes its input from a global variable so that it can be called by admin-execute */
function update_corpus_metadata_fixed()
{
	global $update_corpus_metadata_info;
	global $mysql_link;
	
	$sql_query = "update corpus_metadata_fixed set ";
	$first = true;
	
	foreach ($update_corpus_metadata_info as $key => &$val)
	{
		$update_corpus_metadata_info[$key] = mysql_real_escape_string($val);
		if ($key == 'corpus')
			continue;
		$sql_query .= ($first ? '' : ', ');
		$sql_query .= "$key = '{$update_corpus_metadata_info[$key]}'";
		$first = false;
	}
	
	$sql_query .= " where corpus = '{$update_corpus_metadata_info['corpus']}'";

	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}


function add_variable_corpus_metadata($corpus, $attribute, $value)
{
	global $mysql_link;
	
	$corpus = mysql_real_escape_string($corpus);
	$attribute = mysql_real_escape_string($attribute);
	$value = mysql_real_escape_string($value);
	
	$sql_query = "insert into corpus_metadata_variable (corpus, attribute, value) values
		('$corpus', '$attribute', '$value')";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
}




/**
 * creates a javascript function with $n password candidates that will write
 * one of its candidates to id=passwordField on each call
 */
function print_javascript_for_password_insert($password_function = NULL, $n = 49)
{
	/* JavaScript function to insert a new string from the initialisation array */
	global $create_password_function;
	
	if (empty($password_function))
		$password_function = $create_password_function;

	foreach ($password_function($n) as $pwd)
		$raw_array[] = "'$pwd'";
	$array_initialisers = implode(',', $raw_array);
	
	return "

	<script type=\"text/javascript\">
	<!--
	function insertPassword()
	{
		if ( typeof insertPassword.index == 'undefined' ) 
		{
			/* Not here before ... perform the initilization */
			insertPassword.index = 0;
		}
		else
			insertPassword.index++;
	
		if ( typeof insertPassword.passwords == 'undefined' ) 
		{
			insertPassword.passwords = new Array( $array_initialisers);
		}
	
		document.getElementById('passwordField').value = insertPassword.passwords[insertPassword.index];
	}
	//-->
	</script>
	
	";
}


/**
 * password_insert_internal is the default function for CQPweb candidate passwords
 * 
 * To get nicer candidate passwords, set a different function in config.inc.php
 * 
 * (for example, password_insert_lancaster -- which, however, is subject to the
 * webpage at Lancaster that it exploits remaining as it is!)
 * 
 */
function password_insert_internal($n)
{
	for ( $i = 0 ; $i < $n ; $i++ )
	{
		$pwd[$i] = sprintf("%c%c%c%c%d%d%c%c%c%c",
						rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a),
						rand(0,9), rand(0,9),
						rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a), rand(0x61, 0x7a)
						); 
	}
	return $pwd;
}


function password_insert_lancaster($n)
{
	$n = (int)$n;
	
	/* the lancs security webpage has 49 passwords per page */
	for ($i = 0 ; $i*49 < $n ; $i++)
		$page .= file_get_contents('http://www.lancs.ac.uk/iss/security/passwords/');

	preg_match_all('/<TD><kbd>\s*(\w+)\s*<\/kbd><\/TD>/', $page, $m, PREG_PATTERN_ORDER);
	
	for ($i = 0 ; $i < $n; $i++)
		$results[] = $m[1][$i];
	
	return $results;
}




function create_text_metadata_for()
{
	/* this is an ugly but efficient way to get the data that I need for this */
	global $create_text_metadata_for_info;
	global $cqpweb_uploaddir;
	global $mysql_tempdir;
	global $mysql_link;
	global $mysql_LOAD_DATA_INFILE_command;
	
	
	$corpus = preg_replace('/\W/', '', $create_text_metadata_for_info['corpus']);
	
	if (!is_dir("../$corpus"))
		exiterror_fullpage("Corpus $corpus does not seem to be installed!", __FILE__, __LINE__);	
	
	$file = "/$cqpweb_uploaddir/{$create_text_metadata_for_info['filename']}";
	if (!is_file($file))
		exiterror_fullpage("The file [$file] is not a file!", __FILE__, __LINE__);

	$input_file = "/$mysql_tempdir/___install_temp_{$create_text_metadata_for_info['filename']}";
	
	$data = file_get_contents($file);
	$data = str_replace("\n", "\t0\t0\t0\n", $data);
	file_put_contents($input_file, $data);
	
// what the hell does this do?
// it doesn't seem to do anything.
// cut it out, see if it still works???
	$sql_query = "select handle from text_metadata_fields where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false)
		exiterror_fullpage("MySQL failure!", __FILE__, __LINE__);
// end of bit that doesn't seem to do anything.
	
	/* note, size of text_id is 50 to allow possibility of non-decoded UTF8 - they should be shorter */
	$create_statement = "create table `text_metadata_for_$corpus`(
		`text_id` varchar(50) NOT NULL";
	
	$scan_statements = array();
	
	for ($i = 1; $i <= $create_text_metadata_for_info['field_count']; $i++)
	{
		$create_text_metadata_for_info['fields'][$i]['handle'] 
			= cqpweb_handle_enforce($create_text_metadata_for_info['fields'][$i]['handle']);
			
		if ($create_text_metadata_for_info['fields'][$i]['handle'] == '')
			continue;
			
		if ($create_text_metadata_for_info['fields'][$i]['classification'])
		{
			$create_statement .= ",\n\t\t`" 
				. $create_text_metadata_for_info['fields'][$i]['handle'] 
				. '` varchar(20) default NULL';
			$inserts_for_metadata_fields[] = "insert into text_metadata_fields 
				(corpus, handle, description, is_classification)
				values ('$corpus', '{$create_text_metadata_for_info['fields'][$i]['handle']}',
				'{$create_text_metadata_for_info['fields'][$i]['description']}', 1)";
			$scan_statements[] = array ('field' => $create_text_metadata_for_info['fields'][$i]['handle'],
									'statement' => 
									"select distinct({$create_text_metadata_for_info['fields'][$i]['handle']}) 
									from text_metadata_for_$corpus"
									);
			/* and add to list for which indexes are needed */
			$category_index_list[] = $create_text_metadata_for_info['fields'][$i]['handle'];
		}
		else
		{
			$create_statement .= ",\n\t\t`" 
				. $create_text_metadata_for_info['fields'][$i]['handle'] 
				. '` text default NULL';
			$inserts_for_metadata_fields[] = "insert into text_metadata_fields 
				(corpus, handle, description, is_classification)
				values ('$corpus', '{$create_text_metadata_for_info['fields'][$i]['handle']}',
				'{$create_text_metadata_for_info['fields'][$i]['description']}', 0)";
		}
	}
	/* note, varchar(20) seems ungenerous - fix this? */

	/* add the standard fields; begin list of indexes. */
	$create_statement .= ",
		`words` INTEGER NOT NULL default '0',
		`cqp_begin` BIGINT UNSIGNED NOT NULL default '0',
		`cqp_end` BIGINT UNSIGNED NOT NULL default '0',
		primary key (text_id),
		";
	if (!empty($category_index_list))
		$create_statement .= 'index(' . implode(',',$category_index_list) . ') ';

	/* finish off the rest of the create statement */
	$create_statement .= "
		) CHARSET=utf8 ;\n\n";


	$update_statement = '';
	if (isset($create_text_metadata_for_info['primary_classification']))
	{
		$px = (int)$create_text_metadata_for_info['primary_classification'];
		$pa = $create_text_metadata_for_info['fields'][$px]['handle'];
		if ($pa !== '')
			$update_statement = "update corpus_metadata_fixed set primary_classification_field = '$pa' 
				where corpus = '$corpus'";
	}

	$load_statement = "$mysql_LOAD_DATA_INFILE_command '$input_file' INTO TABLE text_metadata_for_$corpus";


	if (isset($inserts_for_metadata_fields))
	{
		foreach($inserts_for_metadata_fields as &$ins)
		{
			$result = mysql_query($ins, $mysql_link);
			if ($result == false) 
				exiterror_mysqlquery(mysql_errno($mysql_link), 
					mysql_error($mysql_link), __FILE__, __LINE__);
		}
	}
	$result = mysql_query($create_statement, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	$result = mysql_query($load_statement, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	if ($update_statement !== '')
	{
		$result = mysql_query($update_statement, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
	}
	foreach($scan_statements as &$current)
	{
		$result = mysql_query($current['statement'], $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		while (($r = mysql_fetch_row($result)) !== false)
		{
			$add_value_sql = "insert into text_metadata_values (corpus, field_handle, handle)
				values
				('$corpus', '{$current['field']}', '{$r[0]}')";
			$inner_result = mysql_query($add_value_sql, $mysql_link);
			if ($inner_result == false) 
				exiterror_mysqlquery(mysql_errno($mysql_link), 
					mysql_error($mysql_link), __FILE__, __LINE__);
		}
	}

	unlink($input_file);
}




/** deletes the metadata table plus the records that log its fields/values
  * this is a separate function because it reverses the "create_text_metadata_for" function 
  * and it is called by the general "delete corpus" function 
  */
function delete_text_metadata_for($corpus)
{
	global $mysql_link;
	$corpus = mysql_real_escape_string($corpus);
	
	/* delete the table */
	$sql_query = "drop table if exists text_metadata_for_$corpus";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	/* delete its explicator records */
	$sql_query = "delete from text_metadata_fields where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	$sql_query = "delete from text_metadata_values where corpus = '$corpus'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);

}



/**
 * function to re-set the mysql setup to basic form
 */
function cqpweb_mysql_total_reset()
{
	global $mysql_link;

	foreach (array('db_', 'freq_corpus_', 'freq_sc_', 'temporary_freq_', 'text_metadata_for_') as $prefix)
	{
		$sql_query = "show tables like \"$prefix%\"";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		while ( ($r = mysql_fetch_row($result)) !== false)
		{
			$sql_query = "drop table if exists $r";
			if ($result == false) 
				exiterror_mysqlquery(mysql_errno($mysql_link), 
					mysql_error($mysql_link), __FILE__, __LINE__);			
		}
	}
	
	$array_of_create_statements = cqpweb_mysql_recreate_tables();

	foreach ($array_of_create_statements as $name => &$statement)
	{
		$sql_query = "drop table if exists $name";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		$result = mysql_query($statement, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
	}
}

/**
 * Gives you the create table statements for setup as an array
 */
function cqpweb_mysql_recreate_tables()
{
	$create_statements = array();
	
	$create_statements['query_history'] =
		"create table query_history (
			`instance_name` varchar(31) default NULL,
			`user` varchar(20) NOT NULL default '',
			`corpus` varchar(20) NOT NULL default '',
			`cqp_query` text NOT NULL,
			`restrictions` text character set utf8 collate utf8_bin default NULL,
			`subcorpus` varchar(200) default NULL,
			`date_of_query` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`hits` int(11) default NULL,
			`simple_query` text,
			`query_mode` varchar(12) default NULL,
			KEY `user` (`user`),
			KEY `corpus` (`corpus`),
			KEY `cqp_query` (`cqp_query`(256))
		) CHARACTER SET utf8";

	
	$create_statements['saved_queries'] =
		"CREATE TABLE `saved_queries` (
			`query_name` varchar(150),
			`user` varchar(20) default NULL,
			`corpus` varchar(20) NOT NULL,
			`query_mode` varchar(12) default NULL,
			`simple_query` text default NULL,
			`cqp_query` text NOT NULL,
			`restrictions` text default NULL,
			`subcorpus` varchar(200) default NULL,
			`postprocess` text default NULL,
			`hits_left` text default NULL,
			`time_of_query` int(11) default NULL,
			`hits` int(11) default NULL,
			`file_size` int(10) unsigned default NULL,
			`saved` tinyint(1) default 0,
			`save_name` varchar(50) default NULL,
			`date_of_saving` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			KEY `query_name` (`query_name`),
			KEY `user` (`user`),
			KEY `corpus` (`corpus`),
			FULLTEXT KEY `restrictions` (`restrictions`),
			KEY `subcorpus` (`subcorpus`),
			FULLTEXT KEY `postprocess` (`postprocess`(100)),
			KEY `time_of_query` (`time_of_query`),
			FULLTEXT KEY `cqp_query` (`cqp_query`)
	) CHARACTER SET utf8 COLLATE utf8_bin";

	$create_statements['saved_catqueries'] =
		"CREATE TABLE `saved_catqueries` (
			`catquery_name` varchar(150) NOT NULL,
			`user` varchar(20) default NULL,
			`corpus` varchar(20) NOT NULL,
			`dbname` varchar(150) NOT NULL,
			`category_list` TEXT,
			KEY `catquery_name` (`catquery_name`),
			KEY `user` (`user`),
			KEY `corpus` (`corpus`)
	) CHARACTER SET utf8 COLLATE utf8_bin";


	
	$create_statements['user_settings'] =
		"CREATE TABLE `user_settings` (
			`username` varchar(20) NOT NULL default '',
			`realname` varchar(50) default NULL,
			`email` varchar(50) default NULL,
			`conc_kwicview` tinyint(1),
			`conc_corpus_order` tinyint(1),
			`cqp_syntax` tinyint(1),
			`context_with_tags` tinyint(1),
			`use_tooltips` tinyint(1),
			`coll_statistic` tinyint,
			`coll_freqtogether` int,
			`coll_freqalone` int,
			`coll_from` tinyint,
			`coll_to` tinyint,
			`max_dbsize` int(10) unsigned default NULL,
			`linefeed` char(2) default NULL,
			key(`username`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['text_metadata_fields'] =
		"CREATE TABLE `text_metadata_fields` (
			`corpus` varchar(20) NOT NULL,
			`handle` varchar(20) NOT NULL,
			`description` varchar(255) default NULL,
			`is_classification` tinyint(1) default 0,
			primary key (`corpus`, `handle`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['text_metadata_values'] =
		"CREATE TABLE `text_metadata_values` (
			`corpus` varchar(20) NOT NULL,
			`field_handle` varchar(20) NOT NULL,
			`handle` varchar(20) NOT NULL,
			`description` varchar(255) default NULL,
			`category_num_words` int unsigned default NULL,
			`category_num_files` int unsigned default NULL,
			primary key(`corpus`, `field_handle`, `handle`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['annotation_metadata'] =
		"CREATE TABLE `annotation_metadata` (
			`corpus` varchar(20) NOT NULL,
			`handle` varchar(20) NOT NULL,
			`description` varchar(255) default NULL,
			`tagset` varchar(255) default NULL,
			`external_url` varchar(255) default NULL,
			primary key (`corpus`, `handle`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['corpus_metadata_fixed'] =
		"CREATE TABLE `corpus_metadata_fixed` (
			`corpus` varchar(20) NOT NULL,
			`visible` tinyint(1) default 1,
			`primary_classification_field` varchar(20) default NULL,
			`primary_annotation` varchar(20) default NULL,
			`secondary_annotation` varchar(20) default NULL,
			`tertiary_annotation` varchar(20) default NULL,
			`tertiary_annotation_tablehandle` varchar(40) default NULL,
			`combo_annotation` varchar(20) default NULL,
			`external_url` varchar(255) default NULL,
			`public_freqlist_desc` varchar(150) default NULL,
			`corpus_cat` varchar(256) default 'Uncategorised',
			`cwb_external` tinyint(1) NOT NULL default 0,
			PRIMARY KEY (corpus)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";

	
	$create_statements['corpus_metadata_variable'] =
		"CREATE TABLE `corpus_metadata_variable` (
			`corpus` varchar(20) NOT NULL,
			`attribute` text NOT NULL,
			`value` text default NULL,
			key(`corpus`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['saved_dbs'] =
		"CREATE TABLE `saved_dbs` (
			`dbname` varchar(200) NOT NULL,
			`user` varchar(30) default NULL,
			`create_time` int(11) default NULL,
			`cqp_query` text character set utf8 collate utf8_bin NOT NULL,
			`restrictions` text character set utf8 collate utf8_bin default NULL,
			`subcorpus` varchar(200) default NULL,
			`postprocess` text default NULL,
			`corpus` varchar (20) NOT NULL,
			`db_type` varchar(15) default NULL,
			`colloc_atts` varchar(200) default '',
			`colloc_range` int default '0',
			`sort_position` int default '0',
			`db_size` bigint UNSIGNED default NULL,
			`saved` tinyint(1) NOT NULL default 0,
			key (`dbname`),
			key (`user`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['saved_subcorpora'] =
		"CREATE TABLE `saved_subcorpora` (
			`subcorpus_name` varchar(200) NOT NULL,
			`corpus` varchar(20) NOT NULL,
			`user` varchar(30) default NULL,
			`restrictions` text character set utf8 collate utf8_bin,
			`text_list` text character set utf8 collate utf8_bin,
			`numfiles` mediumint(8) unsigned default NULL,
			`numwords` bigint(21) unsigned default NULL,
			key(`corpus`, `user`),
			key(`text_list`(256))
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['mysql_processes'] =
		"CREATE TABLE `mysql_processes` (
			`dbname` varchar(200) NOT NULL,
			`begin_time` int(11) default NULL,
			`process_type` varchar(15) default NULL,
			`process_id` varchar(15) default NULL
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['saved_freqtables'] =
		"CREATE TABLE `saved_freqtables` (
			`freqtable_name` varchar(150),
			`corpus` varchar(20) NOT NULL,
			`user` varchar(30) default NULL,
			`restrictions` text character set utf8 collate utf8_bin,
			`subcorpus` varchar(200) NOT NULL,
			`create_time` int(11) default NULL,
			`ft_size` bigint UNSIGNED default NULL,
			`public` tinyint(1) default 0,
			KEY `subcorpus` (`subcorpus`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	

	$create_statements['system_messages'] =
		"CREATE TABLE `system_messages` (
			`message_id` varchar(150),
			`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`header` varchar(150),
			`content` text character set utf8 collate utf8_bin,
			`fromto` varchar(150),
			key (`message_id`)
	) CHARACTER SET utf8 COLLATE utf8_general_ci";
	
	
	$create_statements['system_longvalues'] =
		"CREATE TABLE `system_longvalues` (
			`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`id` varchar(40),
			`value` text,
			primary key(`id`)
	) CHARACTER SET utf8 COLLATE utf8_bin";
	
	
	return $create_statements;
}














function cqpweb_import_css_file($filename)
{
	global $cqpweb_uploaddir;
	
	$orig = "/$cqpweb_uploaddir/$filename";
	$new = "../css/$filename";
	
	if (is_file($orig))
	{
		if (is_file($new))
		{
			exiterror_general("A CSS file of that name already exists ($new). File not copied.");
		}
		else
			copy($orig, $new);
	}
}




function cqpweb_regenerate_css_files()
{
$yellow_pairs = array(
	'#ffeeaa' =>	'#ddddff',		/* error */
	'#bbbbff' =>	'#ffbb77',		/* dark */
	'#ddddff' =>	'#ffeeaa'		/* light */
	);
	
$green_pairs = array(
	'#ffeeaa' =>	'#ffeeaa',		/* error */
	'#bbbbff' =>	'#66cc99',		/* dark */
	'#ddddff' =>	'#ccffcc'		/* light */
	);
	
$red_pairs = array(
	'#ffeeaa' =>	'#ddddff',		/* error */
	'#bbbbff' =>	'#ff8899',		/* dark */
	'#ddddff' =>	'#ffcfdd'		/* light */
	);

$brown_pairs = array(
	'#ffeeaa' =>	'#ffeeaa',		/* error */
	'#bbbbff' =>	'#cd663f',		/* dark */
	'#ddddff' =>	'#eeaa77'		/* light */
	);

$purple_pairs = array(
	'#ffeeaa' =>	'#ffeeaa',		/* error */
	'#bbbbff' =>	'#be71ec',		/* dark */
	'#ddddff' =>	'#dfbaf5'		/* light */
	);

$darkblue_pairs = array(
	'#ffeeaa' =>	'#ffeeaa',		/* error */
	'#bbbbff' =>	'#0066aa',		/* dark */
	'#ddddff' =>	'#33aadd'		/* light */
	);
$lime_pairs = array(
	'#ffeeaa' =>	'#00ffff',		/* error */
	'#bbbbff' =>	'#B9FF6F',		/* dark */
	'#ddddff' =>	'#ECFF6F'		/* light */
	);
$aqua_pairs = array(
	'#ffeeaa' =>	'#ffeeaa',		/* error */
	'#bbbbff' =>	'#00ffff',		/* dark */
	'#ddddff' =>	'#b0ffff'		/* light */
	);
$neon_pairs = array(
	'#ffeeaa' =>	'#00ff00',		/* error */
	'#bbbbff' =>	'#ff00ff',		/* dark */
	'#ddddff' =>	'#ffa6ff'		/* light */
	);
$dusk_pairs = array(
	'#ffeeaa' =>	'#ffeeaa',		/* error */
	'#bbbbff' =>	'#8000ff',		/* dark */
	'#ddddff' =>	'#d1a4ff'		/* light */
	);
$gold_pairs = array(
	'#ffeeaa' =>	'#80ffff',		/* error */
	'#bbbbff' =>	'#808000',		/* dark */
	'#ddddff' =>	'#c1c66c'		/* light */
	);
/* black will have to wait since inserting white text only where necessary is complex
$black_pairs = array(
	'#ffeeaa' =>	'#ddddff',		/* error * /
	'#bbbbff' =>	'#ff8899',		/* dark * /
	'#ddddff' =>	'#ffcfdd'		/* light * /
	);
*/


$css_file = cqpweb_css_file();

file_put_contents('../css/CQPweb.css', $css_file);
file_put_contents('../css/CQPweb-yellow.css', 	strtr($css_file, $yellow_pairs));
file_put_contents('../css/CQPweb-green.css', 	strtr($css_file, $green_pairs));
file_put_contents('../css/CQPweb-red.css', 		strtr($css_file, $red_pairs));
file_put_contents('../css/CQPweb-brown.css', 	strtr($css_file, $brown_pairs));
file_put_contents('../css/CQPweb-purple.css', 	strtr($css_file, $purple_pairs));
file_put_contents('../css/CQPweb-darkblue.css', strtr($css_file, $darkblue_pairs));
file_put_contents('../css/CQPweb-lime.css', 	strtr($css_file, $lime_pairs));
file_put_contents('../css/CQPweb-aqua.css', 	strtr($css_file, $aqua_pairs));
file_put_contents('../css/CQPweb-neon.css', 	strtr($css_file, $neon_pairs));
file_put_contents('../css/CQPweb-dusk.css', 	strtr($css_file, $dusk_pairs));
file_put_contents('../css/CQPweb-gold.css', 	strtr($css_file, $gold_pairs));


}







function cqpweb_css_file ()
{
	return <<<HERE


/* top page heading */

h1 {
	font-family: Verdana;
	text-align: center;
}



/* different paragraph styles */

p.errormessage {
	font-family: courier new;
	font-size: large
}

p.instruction {
	font-family: verdana;
	font-size: 10pt
}

p.helpnote {
	font-family: verdana;
	font-size: 10pt
}

p.bigbold {
	font-family: verdana;
	font-size: medium;
	font-weight: bold;
}

p.spacer {
	font-size: small;
	padding: 0pt;
	line-height: 0%;
	font-size: 10%
}

span.hit {
	color: red;
	font-weight: bold
}

span.contexthighlight {
	font-weight: bold
}

span.concord-time-report {
	color: gray;
	font-size: 10pt;
	font-weight: normal
}


/* table layout */


table.controlbox {
	border: large outset
}

td.controlbox {
	font-family: Verdana;
	padding-top: 5px;
	padding-bottom: 5px;
	padding-left: 10px;
	padding-right: 10px;
	border: medium outset
}




table.concordtable {
	border-style: solid;
	border-color: #ffffff; 
	border-width: 5px
}

th.concordtable {
	padding-left: 3px;
	padding-right: 3px;
	padding-top: 7px;
	padding-bottom: 7px;
	background-color: #bbbbff;
	font-family: verdana;
	font-weight: bold;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}

td.concordgeneral {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ddddff;	
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}	


td.concorderror {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ffeeaa;	
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}


td.concordgrey {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #d5d5d5;	
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
}	


td.before {
	padding: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 2px;
	border-right-width: 0px;
	text-align: right
}

td.after {
	padding: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 0px;
	border-right-width: 2px;
	text-align: left
}

td.node {
	padding: 3px;
	background-color: #f0f0f0;	
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 0px;
	border-right-width: 0px;
	text-align: center
}

td.lineview {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}	

td.text_id {
	padding: 3px;
	background-color: #ddddff;	
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
	text-align: center
}

td.end_bar {
	padding: 3px;
	background-color: #d5d5d5;	
	font-family: verdana;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
	text-align: center
}


td.basicbox {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 10px;
	padding-bottom: 10px;
	background-color: #ddddff;	
	font-family: verdana;
	font-size: 10pt;
}


td.cqpweb_copynote {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ffffff;
	font-family: verdana;
	font-size: 8pt;
	color: gray;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px
}


/* different types of link */
/* first, for left-navigation in the main query screen */
a.menuItem:link {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}
a.menuItem:visited {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}
a.menuItem:hover {
	white-space: nowrap;
	font-family: verdana;
	color: red;
	font-size: 10pt;
	text-decoration: underline;
}

/* next, for the currently selected menu item */
/* will not usually have an href */
/* ergo, no visited/hover */
a.menuCurrentItem {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}


/* next, for menu bar header text item */
/* will not usually have an href */
/* ergo, no visited/hover 
a.menuHeaderItem {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-weight: bold;
	font-size: 10pt;
	text-decoration: none;

}*/

/* here, for footer link to help */
a.cqpweb_copynote_link:link {
	color: gray;
	text-decoration: none;
}
a.cqpweb_copynote_link:visited {
	color: gray;
	text-decoration: none;
}
a.cqpweb_copynote_link:hover {
	color: red;
	text-decoration: underline;
}
HERE;


}



?>