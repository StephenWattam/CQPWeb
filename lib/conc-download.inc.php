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



/* initialise variables from settings files  */

require_once ('settings.inc.php');
require_once ('../lib/defaults.inc.php');


/* include function library files */
require_once ('../lib/library.inc.php');
require_once ('../lib/concordance-lib.inc.php');
require_once ('../lib/concordance-post.inc.php');
require_once ('../lib/cache.inc.php');
require_once ('../lib/subcorpus.inc.php');
require_once ('../lib/exiterror.inc.php');
require_once ('../lib/metadata.inc.php');
require_once ('../lib/user-settings.inc.php');
require_once ('../lib/cwb.inc.php'); /* NOT TESTED YET - used by dump and undump, I think */
require_once ('../lib/cqp.inc.php');



/* connect to mySQL */
connect_global_mysql();


/* connect to CQP */
connect_global_cqp();





/* variables from GET needed by both versions of this script */


if (isset($_GET['qname']))
	$qname = $_GET['qname'];
else
	exiterror_parameter('Critical parameter "qname" was not defined!', __FILE__, __LINE__);








if ( isset($_GET['downloadGo']) && $_GET['downloadGo'] === 'yes')
{
	/* ----------------------------- */
	/* create and send the text file */
	/* ----------------------------- */
	
	/* first, gather format settings from $_GET */
	/* the folllowing switch deals wth the ones that have "typical settings" */
	switch ($_GET['downloadTypical'])
	{
	case 'threeline':
		/* The threeline format falls through to copypaste. 
		 * A correction function is applied to output lines. */

	case 'copypaste':

		/* linebreak */
		$da = get_user_linefeed($username);
		
		/* handles or values? */
		$category_handles_only = true;
		
		/* use <<<>>>? -- NO */
		$hit_delimiter_before = '';
		$hit_delimiter_after  = '';
		
		/* context size */
		$words_in_context = $default_words_in_download_context;
		
		/* tagged and untagged? */
		$tagged_as_well = false;
		
		/* file-start info format */
		$header_format = 'tabs';
		
		/* kwic or line? */
		$download_view_mode = 'kwic';
		
		/* include corpus positions? */
		$include_positions = false;
		
		/* include url as column? */
		$context_url = false;
		
		/* the filename for the output */
		$filename = 'concordance_download.txt';
		
		/* NO classifications */
		$classifications_to_include = array();
		
		break;
		
		
	case 'filemaker':
	
		/* linebreak */
		$da = get_user_linefeed($username);
		
		/* handles or values? */
		$category_handles_only = true;
		
		/* use <<<>>>? -- YES */
		$hit_delimiter_before = '<<< ';
		$hit_delimiter_after  = ' >>>';
		
		/* context size */
		$words_in_context = $default_words_in_download_context;
		
		/* tagged and untagged? */
		$tagged_as_well = true;
		
		/* file-start info format */
		$header_format = NULL;
		
		/* kwic or line? */
		$download_view_mode = 'line';
		
		/* include corpus positions? */
		$include_positions = true;
		
		/* include url as column? */
		$context_url = true;
		
		
		/* the filename for the output */
		$filename = "concordance_filemaker_import.txt";
		
		/* in this case, ALL categories are downloaded */
		$classifications_metadata = metadata_list_classifications();
		$classifications_to_include = array();
		foreach ( $classifications_metadata as &$s)
			$classifications_to_include[] = $s['handle'];


		break;
	
	default:
		/* IE, no special set of pre-sets given */

		/* linebreak */
		if (isset($_GET['downloadLinebreak']))
		{
			$da = preg_replace('/[^da]/', '', $_GET['downloadLinebreak']);
			$da = strtr($da, "da", "\r\n");
		}
		else
			$da = get_user_linefeed($username);

		
		/* handles or values? */
		
		if (isset($_GET['downloadFullMeta']) && $_GET['downloadFullMeta'] == 'handles')
			$category_handles_only = true;
		else
			$category_handles_only = false;
				
		/* use <<<>>>? */
		
		$hit_delimiter_before = '';
		$hit_delimiter_after  = '';
		if (isset($_GET['downloadResultAnglebrackets']) && $_GET['downloadResultAnglebrackets'])
		{
			$hit_delimiter_before = '<<< ';
			$hit_delimiter_after  = ' >>>';
		}

		
		/* context size */
		
		if (isset($_GET['downloadContext']))
			$words_in_context = (int) $_GET['downloadContext'];
		else
			$words_in_context = $default_words_in_download_context;
		
		/* tagged and untagged? */

		if (isset($_GET['downloadTaggedAndUntagged']) && $_GET['downloadTaggedAndUntagged'] == 1)
			$tagged_as_well = true;
		else
			$tagged_as_well = false;
		
		/* file-start info format */
		
		$header_format = NULL;
		switch (isset($_GET['downloadHeadType']))
		{
		case 'list':	$header_format = 'list';	break;
		case 'tabs':	$header_format = 'tabs';	break;
		default: 		/* leave as NULL */ ;		break;
		}
		
		/* kwic or line? */
		
		if (isset($_GET['downloadViewMode']) && $_GET['downloadViewMode'] == 'line')
			$download_view_mode = 'line';
		else
			$download_view_mode = 'kwic';
					
		/* include corpus positions? */
		
		if (isset($_GET['downloadPositions']) && $_GET['downloadPositions'] == 1)
			$include_positions = true;
		else
			$include_positions = false;
		
		/* include url as column? */
		
		if (isset($_GET['downloadURL']) && $_GET['downloadURL'] == 1)
			$context_url = true;
		else
			$context_url = false;
		
		/* the filename for the output */

		$filename = (isset($_GET['downloadFilename']) ? preg_replace('/\W/', '', $_GET['downloadFilename']) : '' );
		if ($filename == '')
			$filename = 'concordance_download';
		$filename .= '.txt';

		/* the categories to include */
		
		$classifications_metadata = metadata_list_classifications();
		foreach ( $classifications_metadata as &$s)
			$list_of_classifications[] = $s['handle'];

		$classifications_to_include = array();
		
		switch ($_GET['downloadMetaMethod'])
		{
		case 'all':
			$classifications_to_include = $list_of_classifications;
			break;
		
		case 'ticked':
			foreach($_GET as $key => &$val)
			{
				if (substr($key, 0, 13) != 'downloadMeta_')
					continue;
				$c = substr($key, 13);
				if ($val && in_array($c, $list_of_classifications))
					$classifications_to_include[] = $c;
			}
			break;
		
		default:
			/* shouldn't ever get here */
			/* add no classifications to the array to include */
			break;
		}
		
		break;
	} /* end of switch */
	
	/* end of variable setup */

	
	/* send the HTTP header */
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=$filename");

	/* write the file header if specified */
	
	if ($header_format == 'list')
	{
		/* print the header line from the query */
		
		echo str_replace('&rdquo;', '"', 
			str_replace('&ldquo;', '"', 
				preg_replace('/<[^>]+>/', '', 
					create_solution_heading(check_cache_qname($qname)))))
						. $da . $da ;
		
		/* print the rest of the printer-friendly header */
		
		echo "Processed for $username at " . url_absolutify('') . "$da$da";
		echo "Order of tab-delimited text:$da";
		echo "1. Number of hit$da";
		echo "2. Text ID$crlf";
		if ($download_view_mode == 'kwic')
		{
			echo "3. Context before{$da}4. Query item{$da}5. Context after$da";
			$j = 6;
			if ($tagged_as_well)
			{
				echo "6. Tagged context before{$da}7. Tagged query item{$da}8. Tagged context after$da";
				$j = 9;
			}
		}
		else
		{
			echo "3. Concordance line$da";
			$j = 4;
			if ($tagged_as_well)
			{
				echo "4. Tagged concordance line$da";
				$j = 5;
			}
		}
		foreach($classifications_to_include as $c)
		{
			echo "$j. " . metadata_expand_field($c) . $da;
			$j++;
		}
		if ($context_url)
		{
			echo "$j. URL$da";
			$j++;
		}
		if ($include_positions)
		{
			echo "$j. Matchbegin corpus position$da";
			$j++;
			echo "$j. Matchend corpus position$da";
			$j++;
		}
		echo $da;
	}
	else if ($header_format == 'tabs')
	{
		echo "Number of hit\tText ID";
		if ($download_view_mode == 'kwic')
		{
			echo "\tContext before\tQuery item\tContext after";
			if ($tagged_as_well)
				echo "\tTagged context before\tTagged query item\tTagged context after";
		}
		else
		{
			echo "\tConcordance line";
			if ($tagged_as_well)
				echo "\tTagged concordance line";
		}
		foreach($classifications_to_include as &$c)
			echo "\t" . metadata_expand_field($c);
		if ($context_url)
			echo "\tURL";
		if ($include_positions)
		{
			echo "\tMatchbegin corpus position";
			echo "\tMatchend corpus position";
		}
		echo $da;
	}
	
	/* end of file heading */


	/* CQP commands to make ready for concordance line download */

	$cqp->execute("set LD '--<<>>--'");
	$cqp->execute("set RD '--<<>>--'");
	$cqp->execute("set Context $words_in_context words");
	$primary_tag_handle = get_corpus_metadata('primary_annotation');
	$cqp->execute('show +word' . (empty($primary_tag_handle) ? '' : "+$primary_tag_handle "));
	$cqp->execute("set PrintStructures \"text_id\""); 

	list($num_of_solutions) = $cqp->execute("size $qname");


	/* set up the category string for SQL queries */
	$sql_classifications = '';
	if (!empty($classifications_to_include)) 
		$sql_classifications = implode(',', $classifications_to_include);

	/* and get category descriptions for if they need expanding */
	foreach ($classifications_to_include as &$c)
		$category_descriptions[$c] = metadata_category_listdescs($c);



	/* loop for concordance line download, 100 lines at a time */
	
	/* before running the loop, unlimit in case of big query */
	if ($num_of_solutions > 100)
		php_execute_time_unlimit();
	
	for ($batch_start = 0; $batch_start < $num_of_solutions; $batch_start += 100) 
	{
		$batch_end = $batch_start + 99;
		if ($batch_end >= $num_of_solutions)
			$batch_end = $num_of_solutions - 1; 
			
		$kwic = $cqp->execute("cat $qname $batch_start $batch_end");
		$table = $cqp->dump($qname, $batch_start, $batch_end); 
		$n = count($kwic);

		/* loop for each line */
		for ($i = 0 ; $i < $n ; $i++)
		{
			$line_indicator = $batch_start + $i + 1;

			preg_match("/\A\s*\d+: <text_id (\w+)>:/", $kwic[$i], $m);
			$text_id = $m[1];
			$kwic[$i] = preg_replace("/\A\s*\d+: <text_id \w+>:\s+/", '', $kwic[$i]);

			list($kwic_lc, $kwic_match, $kwic_rc) = explode('--<<>>--', $kwic[$i]);
			list($match, $matchend, $target, $keyword) = $table[$i];

			/* get tagged and untagged lines for print */
			
			$untagged = $kwic_lc . ' ~~~***###' 
				. $hit_delimiter_before . $kwic_match . $hit_delimiter_after 
				. ' ~~~***###' . $kwic_rc;
			if ($tagged_as_well) 
				$tagged = "\t" . preg_replace('/([^\s\/]+)\/(\S+)/', '$1_$2', $untagged);
			else
				$tagged = '';
			$untagged = preg_replace('/([^\s\/]+)\/(\S+)/', '$1', $untagged);
			
			$kwiclimiter = ($download_view_mode == 'kwic' ? "\t" : ' ');
			$tagged = preg_replace('/\s*~~~\*\*\*###\s*/', $kwiclimiter, $tagged);
			$untagged = preg_replace('/\s*~~~\*\*\*###\s*/', $kwiclimiter, $untagged);


			if (!empty($sql_classifications)) 
			{
//TODO this is the sql query where S got a problem
				$sql_query = "SELECT $sql_classifications FROM text_metadata_for_$corpus_sql_name where text_id='$text_id'";
				$result = do_mysql_query($sql_query);

				$category_output = mysql_fetch_assoc($result);

				$categorisation_string  = "\t";
				foreach($category_output as $class => &$cat)
				{
					if (! $category_handles_only) 
						$categorisation_string .= $category_descriptions[$class][$cat] . "\t" ;
					else
						$categorisation_string .= $cat . "\t";
				}
				if (substr($categorisation_string, -1) == "\t")
					$categorisation_string = substr($categorisation_string, 0, -1);
			}
			

			$link = ($context_url ? "\t". url_absolutify("context.php?qname=$qname&batch=" . ($batch_start + $i) . "&uT=y") : '');
			
			$out = "$line_indicator\t$text_id\t$untagged$tagged$categorisation_string$link";
			
			if ($include_positions)
				$out .= "\t$match\t$matchend";
			
			echo $out . $da;

			
		} /* end loop for each line */

	} /* end loop for concordance line batch download */

	/* just in case ... */
	if ($num_of_solutions > 100)
		php_execute_time_relimit();

} /* end of if ($_GET['downloadGo'] === 'yes') */

else

{
	/* --------------------------------------- */
	/* write an HTML page with all the options */
	/* --------------------------------------- */
	
	
	$user_settings = get_all_user_settings($username);

	/* enable the user setting to be auto-selected for linebreak type */
	$da_selected = array('d' => '', 'a' => '', 'da' => '');
	if ($user_settings->linefeed == 'au')
		$user_settings->linefeed = guess_user_linefeed($username);
	$da_selected[$user_settings->linefeed] = ' selected="selected" ';
	
	/* before anything else */
	header('Content-Type: text/html; charset=utf-8');
	?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
echo '<title>' . $corpus_title . ' -- CQPweb Concordance Download</title>';
echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
?>
<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
</head>
<body>
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable" colspan="2">Download concordance</th>
	</tr>
	<tr>
		<td class="concordgeneral" colspan="2" align="center">
			&nbsp;<br/>
			<form action="redirect.php" method="get">
				<input type="submit" 
					value="Download with typical settings for copy-paste into Word, Excel etc." />
				<br/>
				<input type="hidden" name="redirect" value="download" />
				<input type="hidden" name="qname" value="<?php echo $qname; ?>" />
				<input type="hidden" name="downloadGo" value="yes" />
				<input type="hidden" name="downloadTypical" value="copypaste" />
				<input type="hidden" name="uT" value="y" />
			</form>
			<form action="redirect.php" method="get">
				&nbsp;<br/>
				<input type="submit" 
					value="Download with typical settings for FileMaker Pro" />
				<br/>&nbsp;
				<input type="hidden" name="redirect" value="download" />
				<input type="hidden" name="qname" value="<?php echo $qname; ?>" />
				<input type="hidden" name="downloadGo" value="yes" />
				<input type="hidden" name="downloadTypical" value="filemaker" />
				<input type="hidden" name="uT" value="y" />
			</form>
		</td>
	</tr>
	<form action="redirect.php" method="get">
		<tr>
			<th class="concordtable" colspan="2">Detailed output options</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2" align="center">
				&nbsp;<br/>
				Formatting options
				<br/>&nbsp;
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral" width="50%">
				Choose operating system on which you will be working with the file:
			</td>
			<td class="concordgeneral">
				<select name="downloadLinebreak">
					<option value="d"  <?php echo $da_selected['d']; ?>>Macintosh (OS 9 and below)</option>
					<option value="da" <?php echo $da_selected['da'];?>>Windows</option>
					<option value="a"  <?php echo $da_selected['a']; ?>>UNIX (incl. OS X)</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Print short handles or full values for textual categories:</td>
			<td class="concordgeneral">
				<select name="downloadFullMeta">
					<option selected="selected" value="full">full values</option>
					<option value="handles">short handles</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Mark query result in sentence (format: &lt;&lt;&lt; result &gt;&gt;&gt;): </td>
			<td class="concordgeneral">
				<select name="downloadResultAnglebrackets">
					<option value="1">Yes</option>
					<option value="0" selected="selected">No</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Size of context: </td>
			<td class="concordgeneral">
				<select name="downloadContext">
					<option value="1">1 words each way</option>
					<option value="2">2 words each way</option>
					<option value="3">3 words each way</option>
					<option value="4">4 words each way</option>
					<option value="5">5 words each way</option>
					<option value="6">6 words each way</option>
					<option value="7">7 words each way</option>
					<option value="8">8 words each way</option>
					<option value="9">9 words each way</option>
					<option value="10" selected="selected">10 words each way</option>
					<option value="50">50 words each way</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Download both tagged and untagged version of your results: </td>
			<td class="concordgeneral">
				<select name="downloadTaggedAndUntagged">
					<option value="1" selected="selected">Yes</option>
					<option value="0">No</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Write information about table columns at the beginning of file:</td>
			<td class="concordgeneral">
				<select name="downloadHeadType">
					<option value="NULL">No</option>
					<option value="tabs" selected="selected">Yes - column headings</option>
					<option value="list">Yes - printer-friendly list</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Format of output - KWIC or line:</td>
			<td class="concordgeneral">
				<select name="downloadViewMode">
					<option value="kwic" selected="selected">KWIC</option>
					<option value="line">Line</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Include corpus positions (required for re-import)</td>
			<td class="concordgeneral">
				<select name="downloadPositions">
					<option value="1" selected="selected">Yes</option>
					<option value="0">No</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Include URL to context display</td>
			<td class="concordgeneral">
				<select name="downloadURL">
					<option value="1" selected="selected">Yes</option>
					<option value="0">No</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">Enter name for the downloaded file:</td>
			<td class="concordgeneral">
				<input type="text" name="downloadFilename" value="concordance" />
			</td>
		</tr>
		
		<tr>
			<td class="concordgrey" colspan="2" align="center">
				&nbsp;<br/>
				Please tick the text metadata categories that you want to include in your download:
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Method:</td>
			<td class="concordgeneral">
				<select name="downloadMetaMethod">
					<option value="all">Download all text metadata</option>
					<option value="ticked" selected="selected">Download text metadata ticked below</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Select from available text categorisation schemes:
			<td class="concordgeneral">
				<?php	
				foreach ( metadata_list_classifications() as $scheme )
					echo "\n\t\t\t\t<input type=\"checkbox\" name=\"downloadMeta_"
						. "{$scheme['handle']}\" value=\"1\">{$scheme['description']}<br/>";
				?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				&nbsp;<br/>
				<input type="submit" value="Download with settings above" />
				<!-- &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="reset" value="Clear form" />-->
				<br/>&nbsp;
			</td>
		</tr>
		<input type="hidden" name="redirect" value="download" />
		<input type="hidden" name="qname" value="<?php echo $qname; ?>" />
		<input type="hidden" name="downloadGo" value="yes" />
		<input type="hidden" name="downloadTypical" value="NULL" />
		<input type="hidden" name="uT" value="y" />
	</form>
	<?php
	
	// TODO
	/*
	 * should we have the functionality to allow an annotation OTHER THAN the primary attribute
	 * to be selected for a concordance download?
	 */
	
	?>
	<tr>
		<th class="concordtable" colspan="2">Switch download format</th>
	</tr>
	<form action="redirect.php" method="get">
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				&nbsp;<br/>
				<input type="submit" value="Download query as plain-text table" />
				<br/>&nbsp;
			</td>
		</tr>
		<input type="hidden" name="redirect" value="download-tab" />
		<input type="hidden" name="qname" value="<?php echo $qname; ?>" />
		<input type="hidden" name="uT" value="y" />
	</form>
</table>
<?php print_footer(); ?>
</body>
</html>
	<?php


} /* end of the huge determining if-else */


/* disconnect CQP child process and mysql */
disconnect_all();

/* end of script */


?>
