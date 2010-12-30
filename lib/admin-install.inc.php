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
 * This file contains functions used in the installation of CQPweb
 * corpora (not including textmetadata installation!)
 * 
 * It should generally not be included into scripts unless the user
 * is a sysadmin.
 */




/**
 * Just a little object to hold info on the install corpus parsed from GET;
 * NOT an independent module in any way, shape or form, just a way to simplify
 * variable parsing.
 */
class corpus_install_info
{
	public $corpus_mysql_name;
	public $corpus_cwb_name;
	
	public $already_cwb_indexed;
	
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
		/* *********************** */
		
		$this->corpus_mysql_name = cqpweb_handle_enforce($_GET['corpus_mysql_name']);
		$this->corpus_cwb_name = strtolower(cqpweb_handle_enforce($_GET['corpus_cwb_name']));

		/* check for reserved words */
		global $cqpweb_reserved_subdirs;
		if (in_array($this->corpus_mysql_name, $cqpweb_reserved_subdirs))
			exiterror_fullpage("The following corpus names are not allowed: " . implode(' ', $cqpweb_reserved_subdirs),
				__FILE__, __LINE__);
		
		if (substr($this->corpus_cwb_name, -6) == '__freq')
			exiterror_fullpage('Error: Corpus CWB names cannot end in __freq!!');
		
		$this->script_is_r2l = ( $_GET['corpus_scriptIsR2L'] === '1' );
		$this->encode_charset = ( $_GET['corpus_encodeIsLatin1'] === '1' ? 'latin1' : 'utf8' );
				
		if ( $this->corpus_cwb_name === '' || $this->corpus_mysql_name === '' )
			exiterror_fullpage("You must specify a corpus name using only letter, numbers and underscore",
				__FILE__, __LINE__);
		
		$_GET['corpus_description'] = addcslashes($_GET['corpus_description'], "'");
		$this->description = $_GET['corpus_description'];
		
		
		/* ***************** */
		
		if ($this->already_cwb_indexed)
		{
			global $cwb_registry;
			/* check that the corpus registry file exists, that the corpus datadir exists */
			/* in the process, getting the "override" directories, if they exist */
			
			$use_normal_regdir = (bool)$_GET['corpus_useDefaultRegistry'];
			$registry_file = "/$cwb_registry/{$this->corpus_cwb_name}";
			
			if ( ! $use_normal_regdir)
			{
				$orig_registry_file = 
					'/' 
					. trim(trim($_GET['corpus_cwb_registry_folder']), '/')
					. '/' 
					. $this->corpus_cwb_name;
				if (is_file($registry_file))
					exiterror_fullpage("A corpus by that name already exists in the CQPweb registry!",
						__FILE__, __LINE__);					
				if (!is_file($orig_registry_file))
					exiterror_fullpage("The specified CWB registry file does not seem to exist in that location.",
						__FILE__, __LINE__);
				
				/* we have established that the registry file does not exist and the original does
				 * so we can now import the registry file into CQPweb's registry 
				 */	
				copy($orig_registry_file, $registry_file);
			}
			else
			{
				if (!is_file($registry_file))
					exiterror_fullpage("The specified CWB corpus does not seem to exist in CQPweb's registry.",
						__FILE__, __LINE__);
			}
			
			$regdata = file_get_contents($registry_file);
			
			if (preg_match("/HOME\s+\/([^\n]+)\s/", $regdata, $m) < 1)
			{
				unlink($registry_file);
				exiterror_fullpage("A data-directory path could not be found in the registry file for "
					. "the CWB corpus you specified.\n\nEither the data-directory is unspecified, or it is "
					. "specified with a relative path (an absolute path is needed).",
						__FILE__, __LINE__);
			}
			$test_datadir = '/' .  $m[1];
			
			if (!is_dir($test_datadir))
				exiterror_fullpage("The data directory specified in the registry file could not be found.",
					__FILE__, __LINE__);
			
			/* check that <text> and <text_id> are s-attributes */
			if (preg_match('/\bSTRUCTURE\s+text\b/', $regdata) < 1 
				|| preg_match('/\bSTRUCTURE\s+text_id\b/', $regdata) < 1)
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

		

		/* ******************* */
		
		/* p-attributes */
		if ($this->already_cwb_indexed)
		{
			preg_match_all("/ATTRIBUTE\s+(\w+)\s*[#\n]/", $regdata, $m, PREG_PATTERN_ORDER);
			foreach($m[1] as $p)
			{
				if ($p == 'word')
					continue;
				$this->p_attributes[] = $p;
				$this->p_attributes_mysql_insert[] = $this->get_p_att_mysql_insert($p, '', '', '');
				
				/* note that no "primary" annotation is created if we are loading in an existing corpus */
				/* instead, the primary annotation can be set later */
				/* note also that cwb_external applies EVEN IF the indexed corpus was already in this directory
				 * (its sole use is to prevent deletion of data that CQPweb did not create)
				 */
				$this->corpus_metadata_fixed_mysql_insert =
					"insert into corpus_metadata_fixed (corpus, primary_annotation, cwb_external) 
					values ('{$this->corpus_mysql_name}', NULL, 1)";
			}
		}
		else
			$this->load_p_atts_based_on_get();


		/* ******************* */

		
		/* s-attributes */
		/* have to have this one! */
		$this->s_attributes[] = 'text:0+id';
		
		if ($_GET['withDefaultSs'] === '1')
		{
			$this->s_attributes[] = 's';
		}
		else
		{
			for ( $q = 1 ; isset($_GET["customS$q"]) ; $q++ )
			{
				if (preg_match('/^\w+(:0)?(\+\w+)+$/', $_GET["customS$q"]) > 0)
					$cand = $_GET["customS$q"];
				else
					$cand = cqpweb_handle_enforce($_GET["customS$q"]);
				if ($cand === '')
					continue;
				else
					$this->s_attributes[] = $cand;
			}
		}

		
		/* ******************* */

		if ($_GET['cssCustom'] == 1)
		{
			/* escape single quotes in the address because it will be embedded in a single-quoted string */ 
			$this->css_url = addcslashes($_GET['cssCustomUrl'], "'");
			/* only a silly URL would have ' in it anyway, so this is for safety */
		}
		else
		{
			/* we assume no single quotes in builtin CSS files because we can make sure no
			 * silly filenames get added in the future.... */ 
			$this->css_url = "../css/{$_GET['cssBuiltIn']}";
			if (! is_file($this->css_url))
				$this->css_url = '';
		}
		
		/* ******************* */
		
		
		
		
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
			for ( $q = 1 ; isset($_GET["customPHandle$q"]) ; $q++ )
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
	global $cqpweb_tempdir;
	global $cwb_datadir;
	global $cwb_registry;
	
	$info = new corpus_install_info;


	/* we need both case versions here */
	$corpus = $info->corpus_cwb_name;
	$CORPUS = strtoupper($corpus);


	/* mysql table inserts */	
	/* these come first because if corpus already exists, they will cause an abort */
	if (! empty($info->p_attributes_mysql_insert))
	{
		foreach ($info->p_attributes_mysql_insert as &$s)
			do_mysql_query($s);
	}
	do_mysql_query($info->corpus_metadata_fixed_mysql_insert);


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
	
		$encode_output_file = "/$cqpweb_tempdir/{$corpus}__php-cwb-encode.txt";
	
		$encode_command =  "/$path_to_cwb/cwb-encode -xsB -c {$info->encode_charset} -d $datadir -f " 
			. implode(' -f ', $info->file_list)
			. " -R /$cwb_registry/$corpus "
			. ( empty($info->p_attributes) ? '' : (' -P ' . implode(' -P ', $info->p_attributes)) )
			. ' -S ' . implode(' -S ', $info->s_attributes)
					/* NB don't need possibility of no S-atts because there is always text:0+id */
			. " > $encode_output_file";
	
		exec($encode_command);
		
		chmod("/$cwb_registry/$corpus", 0664);
	
		$make_output_file = "/$cqpweb_tempdir/{$corpus}__php-cwb-make.txt";
		
		exec("/$path_to_cwb/cwb-makeall -r /$cwb_registry -V $CORPUS > $make_output_file");
	
		$huffcode_output_file = "/$cqpweb_tempdir/{$corpus}__php-cwb-huffcode.txt";
		
		exec("/$path_to_cwb/cwb-huffcode     -A $CORPUS >  $huffcode_output_file");
		exec("/$path_to_cwb/cwb-compress-rdx -A $CORPUS >> $huffcode_output_file");
	
	
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
	install_create_corpus_script_files($newdir);


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
		. '?>';
	file_put_contents($filepath, $data);
	chmod($filepath, 0775);
}


function install_create_corpus_script_files($in_dir)
{
	foreach (array( 'api', 'collocation', 'concordance', 'context',
					'distribution', 'execute', 'freqlist',
					'freqtable-compile', 'help', 'index',
					'keywords', 'redirect', 'subcorpus-admin',
					'textmeta') as $c)
	{
		file_put_contents("$in_dir/$c.php", "<?php require('../lib/$c.inc.php'); ?>");
		chmod("$in_dir/$c.php", 0775);
	}		
}



?>