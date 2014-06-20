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







/* distribution.inc.php */

/* this file contains the code for calculating and showing distribution of hits across text cats */


/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */



/* initialise variables from settings files  */
require('../lib/environment.inc.php');


/* include function library files */
require("../lib/library.inc.php");
require('../lib/html-lib.inc.php');
require("../lib/concordance-lib.inc.php");
require("../lib/concordance-post.inc.php");
require("../lib/exiterror.inc.php");
require("../lib/user-lib.inc.php");
require("../lib/metadata.inc.php");
require("../lib/subcorpus.inc.php");
require("../lib/cache.inc.php");
require("../lib/db.inc.php");
require("../lib/cwb.inc.php");
require("../lib/cqp.inc.php");


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);


/* ------------------------------- *
 * initialise variables from $_GET *
 * and perform initial fiddling    *
 * ------------------------------- */



/* this script takes all of the GET parameters from concordance.php,
 * but only qname is absolutely critical, the rest just get passed */

$qname = safe_qname_from_get();
	
/* all scripts that pass on $_GET['theData'] have to do this, to stop arg passing adding slashes */
if (isset($_GET['theData']))
	$_GET['theData'] = prepare_query_string($_GET['theData']);






/* parameters unique to this script */

if (isset($_GET['classification']))
	$class_scheme_to_show = $_GET['classification'];
else
	$class_scheme_to_show = '__all';
	
if (isset($_GET['crosstabsClass']))
	$class_scheme_for_crosstabs = $_GET['crosstabsClass'];
else
	$class_scheme_for_crosstabs = '__none';

/* crosstabs only allowed if general information not selected */

if ($class_scheme_to_show == '__all' || $class_scheme_to_show == '__filefreqs')
	$class_scheme_for_crosstabs = '__none';
/* nb crosstabs also overriden if "file frequency" is selected */

if (isset($_GET['showDistAs']) && $_GET['showDistAs'] == 'graph' && $class_scheme_to_show != '__filefreqs')
{
	$print_function = 'print_distribution_graph';
	$class_scheme_for_crosstabs = '__none';
}
else
	$print_function = 'print_distribution_table';

/* as you can see above, if graph is selected, then crosstabs is overridden */

/* do we want a nice HTML table or a downloadable table? */
if (isset($_GET['tableDownloadMode']) && $_GET['tableDownloadMode'] == 1)
	$download_mode = true;
else
	$download_mode = false;






/* does a db for the distribution exist? */

/* search the db list for a db whose parameters match those of the query named as qname */
/* if it doesn't exist, create one */

$query_record = check_cache_qname($qname);
if ($query_record === false)
	exiterror_general("The specified query $qname was not found in cache!", __FILE__, __LINE__);


$db_record = check_dblist_parameters('dist', $query_record['cqp_query'], $query_record['restrictions'], 
				$query_record['subcorpus'], $query_record['postprocess']);

if ($db_record === false)
{
	$dbname = create_db('dist', $qname, $query_record['cqp_query'], $query_record['restrictions'], 
				$query_record['subcorpus'], $query_record['postprocess']);
	$db_record = check_dblist_dbname($dbname);
}
else
{
	$dbname = $db_record['dbname'];
	touch_db($dbname);
}
/* this dbname & its db_record can be globalled by the various print-me functions */


if ($download_mode)
{
	/* ----------------------------------------------------------------- */
	/* Here is how we do the plaintext download of all file frequencies. */
	/* ----------------------------------------------------------------- */
	
	$sql_query = "SELECT db.text_id as text, md.words as words, count(*) as hits 
		FROM $dbname as db 
		LEFT JOIN text_metadata_for_$corpus_sql_name as md ON db.text_id = md.text_id
		GROUP BY db.text_id
		ORDER BY db.text_id";
	$result = do_mysql_query($sql_query);	

	$da = get_user_linefeed($User->username);
	
	$description = distribution_header_line($query_record, $db_record);
	$description = preg_replace('/&([lr]dquo|quot);/', '"', $description);
	$description = preg_replace('/<span .*>/', '', $description);
	
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=text_frequency_data.txt");
	echo $description, "$da";
	echo "__________________$da$da";

	echo "Text\t\tNo. words in text\tNo. hits in text\tFreq. per million words$da$da";

	while (false !== ($r = mysql_fetch_object($result)))
		echo $r->text, "\t", $r->words, "\t", $r->hits, "\t", round(($r->hits / $r->words) * 1000000, 2), $da;
	
	/* end of code for plaintext download. */
}
else
{
	/* begin HTML output */
	echo print_html_header("$corpus_title -- CQPweb showing distribution of query solutions", 
	                       $Config->css_path, 
	                       array('cword', ''));
	
	/* -------------------------------- *
	 * print upper table - control form * 
	 * -------------------------------- */
	
	/* get a list of handles and descriptions for classificatory metadata fieds in this corpus */
	$class_scheme_list = metadata_list_classifications();
	
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="4" class="concordtable">
				<?php
					echo distribution_header_line($query_record, $db_record);
				?>
			</th>
		</tr>
			<form action="redirect.php" method="get">
			<tr>
				<td class="concordgrey">Categories:</td>
				<td class="concordgrey">
					<select name="classification">
						<?php
						
						$selected_done = false;
						$class_desc_to_pass = "";
						
						foreach($class_scheme_list as $c)
						{
							echo "\n\t\t\t\t\t<option value=\"" . ($c['handle']) . '"';
							if ($c['handle'] == $class_scheme_to_show)
							{
								$class_desc_to_pass = $c['description'];
								echo ' selected="selected"';
								$selected_done = true;
							}
							echo '>' . ($c['description']) . '</option>';
						}
						
						if ($selected_done == false)
						{
							$ff_str  = ($class_scheme_to_show == '__filefreqs' ? ' selected="selected"' : '');
							$all_str = ($class_scheme_to_show == '__all'       ? ' selected="selected"' : '');
						}
						echo "\n\t\t\t\t\t<option value=\"__all\"$all_str>General information</option>";
						echo "\n\t\t\t\t\t<option value=\"__filefreqs\"$ff_str>File-frequency information</option>\n";
	
						?>
					</select>
				</td>
				<td class="concordgrey">
					Show as:
				</td>
				<td class="concordgrey">
					<select name="showDistAs">
						<option value="table"<?php 
							echo ($print_function != 'print_distribution_graph' ? ' selected="selected"' : '');
							?>>Distribution table</option>
						<option value="graph"<?php
							echo ($print_function == 'print_distribution_graph' ? ' selected="selected"' : '');
							?>>Bar chart</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgrey">Category for crosstabs:</td>
				<td class="concordgrey">
					<select name="crosstabsClass">
						<?php
						
						$selected_done = false;
						$class_desc_to_pass_for_crosstabs = "";
						
						foreach($class_scheme_list as $c)
						{
							echo '
								<option value="' . ($c['handle']) . '"';
							if ($c['handle'] == $class_scheme_for_crosstabs)
							{
								$class_desc_to_pass_for_crosstabs = $c['description'];
								echo ' selected="selected"';
								$selected_done = true;
							}
							echo '>' . ($c['description']) . '</option>';
						}
						if ($selected_done)
							echo '
								<option value="__none">No crosstabs</option>';
						else
							echo '
								<option value="__none" selected="selected">No crosstabs</option>';
						?>
						
					</select>
				</td>
				<td class="concordgrey">
					<!-- This cell kept empty to add more controls later -->
					&nbsp;
				</td>
				<td class="concordgrey">
					<select name="redirect">
						<option value="refreshDistribution" selected="selected">Show distribution</option>
						<option value="distributionDownload">Download text frequencies</option>
						<option value="newQuery">New query</option>
						<option value="backFromDistribution">Back to query result</option>
					</select>
					<input type="submit" value="Go!" />
				</td>
				<input type="hidden" name="qname" value="<?php echo $qname; ?>" />
				<?php
				
				/* iff we have a per-page / page no passed in, pass it back, so we can return to
				 * the right place using the back-from-distribution option */
				
				if (isset($_GET['pageNo']))
				{
					$_GET['pageNo'] = (int)$_GET['pageNo'];
					echo "<input type=\"hidden\" name=\"pageNo\" value=\"{$_GET['pageNo']}\" />";
				}
				if (isset($_GET['pp']))
				{
					$_GET['pp'] = (int)$_GET['pp'];
					echo "<input type=\"hidden\" name=\"pp\" value=\"{$_GET['pp']}\" />";
				}
				
				?>	
				<input type="hidden" name="uT" value="y" />
			</tr>
		</form>
		<?php 
		if (count($class_scheme_list) == 0  && $class_scheme_to_show != '__filefreqs')
		{
			?>
			<tr>
				<th class="concordtable" colspan="4">
					This corpus has no text-classification metadata, so the distribution cannot be shown.
					You can still select the &ldquo;<em>File-frequency information</em>&rdquo; command 
					from the menu above.
				</th>
			</tr>
			<?php
		}
		?> 
	</table>
	
	<?php
	
	echo '<table class="concordtable" width="100%">';
	
	
	
	if ($class_scheme_for_crosstabs == '__none')
	{
		switch ($class_scheme_to_show)
		{
		case '__all':
			/* show all schemes, one after another */
			foreach ($class_scheme_list as $c)
				$print_function($c['handle'], $c['description'], $qname);
			break;
		
		case '__filefreqs':
			print_distribution_filefreqs($qname);
			break;
			
		default:
			/* print lower table - one classification has been specified */
			$print_function($class_scheme_to_show, $class_desc_to_pass, $qname);
		}
	
	}
	else
	{
		/* do crosstabs */
		print_distribution_crosstabs($class_scheme_to_show, $class_desc_to_pass, 
			$class_scheme_for_crosstabs, $class_desc_to_pass_for_crosstabs, $qname);
	}
	
	
	
	echo '</table>';
	
	echo print_html_footer();

} /* end of "else" for "if download_mode" */


cqpweb_shutdown_environment();


/* ------------- */
/* END OF SCRIPT */
/* ------------- */








function file_freq_comp_asc($a, $b)
{
    if ($a['per_mill'] == $b['per_mill'])
        return 0;

    return ($a['per_mill'] < $b['per_mill']) ? -1 : 1;
}
function file_freq_comp_desc($a, $b)
{
    if ($a['per_mill'] == $b['per_mill'])
        return 0;

    return ($a['per_mill'] < $b['per_mill']) ? 1 : -1;
}



function print_distribution_filefreqs($qname_for_link)
{
	global $corpus_sql_name;
	
	global $dbname;
	global $db_record;
	
	global $dist_num_files_to_list;


	
	$sql_query = "SELECT db.text_id, md.words, count(*) as hits 
		FROM $dbname as db 
		LEFT JOIN text_metadata_for_$corpus_sql_name as md ON db.text_id = md.text_id
		GROUP BY db.text_id";

	$result = do_mysql_query($sql_query);
	
	$master_array = array();
	$i = 0;
	while ( ($t = mysql_fetch_assoc($result)) != false)
	{
		$master_array[$i] = $t;
		$master_array[$i]['per_mill'] = round(($t['hits'] / $t['words']) * 1000000, 2);
		$i++;
	}
	


	?>
		<tr>
			<th colspan="4" class="concordtable">
				Your query was <i>most</i> frequently found in the following files:
			</th>
		</tr>
		<tr>
			<th class="concordtable">Text</th>
			<th class="concordtable">Number of words</th>
			<th class="concordtable">Number of hits</th>
			<th class="concordtable">Frequency<br/>per million words</th>
		</tr>
	<?php

	usort($master_array, "file_freq_comp_desc");

	
	for ( $i = 0 ; $i < $dist_num_files_to_list && isset($master_array[$i]) ; $i++ )
	{
		$textlink = "concordance.php?qname=$qname_for_link&newPostP=text&newPostP_textTargetId={$master_array[$i]['text_id']}&uT=y";
		?>
		<tr>
			<td align="center" class="concordgeneral">
				<a href="textmeta.php?text=<?php echo $master_array[$i]['text_id']; ?>&uT=y">
					<?php echo $master_array[$i]['text_id']; ?>
				</a>
			</td>
			<td align="center" class="concordgeneral">
				<?php echo number_format((float)$master_array[$i]['words']); ?>
			</td>
			<!-- note - link to restricted query (to just that text) needed here -->
			<td align="center" class="concordgeneral">
				<a href="<?php echo $textlink; ?>">
					<?php echo number_format((float)$master_array[$i]['hits']); ?>
				</a>
			</td>
			<td align="center" class="concordgeneral">
				<?php echo $master_array[$i]['per_mill']; ?>
			</td>
		</tr>
		<?php
	}	


	?>
		<tr>
			<th colspan="4" class="concordtable">
				Your query was <i>least</i> frequently found in the following files (only files with at least 1 hit are included):
			</th>
		</tr>
		<tr>
			<th class="concordtable">Text</th>
			<th class="concordtable">Number of words</th>
			<th class="concordtable">Number of hits</th>
			<th class="concordtable">Frequency<br/>per million words</th>
		</tr>
	<?php

	usort($master_array, "file_freq_comp_asc");

	
	for ( $i = 0 ; $i < $dist_num_files_to_list && isset($master_array[$i]) ; $i++ )
	{
		$textlink = "concordance.php?qname=$qname_for_link&newPostP=text&newPostP_textTargetId={$master_array[$i]['text_id']}&uT=y";
		?>
		<tr>
			<td align="center" class="concordgeneral">
				<a href="textmeta.php?text=<?php echo $master_array[$i]['text_id']; ?>&uT=y">
					<?php echo $master_array[$i]['text_id']; ?>
				</a>
			</td>
			<td align="center" class="concordgeneral">
				<?php echo number_format((float)$master_array[$i]['words']); ?>
			</td>
			<!-- note - link to restricted query (to just that text) needed here -->
			<td align="center" class="concordgeneral">
				<a href="<?php echo $textlink; ?>">
					<?php echo number_format((float)$master_array[$i]['hits']); ?>
				</a>
			</td>
			<td align="center" class="concordgeneral">
				<?php echo $master_array[$i]['per_mill']; ?>
			</td>
		</tr>
		<?php
	}
}






function print_distribution_graph($classification_handle, $classification_desc, $qname_for_link)
{
	global $corpus_sql_name;
	
	global $dbname;
	global $db_record;
	
	global $graph_img_path;

	/* a list of category descriptions, for later accessing */
	$desclist = metadata_category_listdescs($classification_handle);

	/* the main query that gets table data */
	$sql_query = "SELECT md.$classification_handle as handle,
		count(db.text_id) as hits
		FROM text_metadata_for_$corpus_sql_name	as md 
		LEFT JOIN $dbname as db on md.text_id = db.text_id
		GROUP BY md.$classification_handle";
	
	$result = do_mysql_query($sql_query);


	/* compile the info */
	
	$max_per_mill = 0;
	$master_array = array();
	
	/* for each category: */
	for ($i = 0 ; ($c = mysql_fetch_assoc($result)) != false ; $i++)
	{
		/* skip the category of "null" ie no category in this classification */
		if ($c['handle'] == '')
		{
			$i--;
			continue;
		}
		$master_array[$i] = $c;
		
		list ($words_in_cat, $files_in_cat) = metadata_size_of_cat($classification_handle, $c['handle']);
		
		$master_array[$i]['words_in_cat'] = $words_in_cat;
		$master_array[$i]['per_mill'] = round(($master_array[$i]['hits'] / $master_array[$i]['words_in_cat']) * 1000000, 2);
		
		if ($master_array[$i]['per_mill'] > $max_per_mill)
			$max_per_mill = $master_array[$i]['per_mill'];
	}
	
	if ($max_per_mill == 0)
	{
		/* no category in this classification has any hits */
		echo "<tr><th class=\"concordtable\">No category within the classification scheme 
			\"$classification_desc\" has any hits in it.</th></tr></table>
			<table class=\"concordtable\" width=\"100%\">";
		return;
	}
	
	$n = count($master_array);
	$num_columns = $n + 1;

	/* header row */
	
	?>
		<tr>
			<th colspan="<?php echo $num_columns; ?>" class="concordtable">
				<?php echo "Based on classification: </i>$classification_desc</i>"; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey"><b>Category</b></td>
	<?php
	
	/* line of category labels */

	for($i = 0; $i < $n; $i++)
	{
		echo '<td class="concordgrey"><center><b>' . $master_array[$i]['handle'] . '</b></center></td>';
	}
	
	?>
		</tr>
		<tr>
			<td class="concordgeneral">&nbsp;</td>
	<?php
	
	/* line of bars */

	for($i = 0; $i < $n; $i++)
	{
		if ($desclist[$master_array[$i]['handle']] == "")
			$this_label = $master_array[$i]['handle'];
		else
			$this_label = $desclist[$master_array[$i]['handle']];

		$html_for_hover = "Category: <b>$this_label</b></br><hr color=&quot;#000099&quot;>" 
			. '<font color=&quot;#DD0000&quot;>' . $master_array[$i]['hits'] . '</font> hits in '
			. '<font color=&quot;#DD0000&quot;>' . number_format((float)$master_array[$i]['words_in_cat']) 
			. '</font> words.';
			

		$this_bar_height = round( ($master_array[$i]['per_mill'] / $max_per_mill) * 100, 0);

		/* make this a link to the limited query when I do likewise in the distribution table */
		echo '<td  align="center" valign="bottom" class="concordgeneral">' 
			. '<a onmouseover="return escape(\'' . $html_for_hover . '\')">'
			. '<img border="1" src="' . $graph_img_path 
			. "\" width=\"70\" height=\"$this_bar_height\" align=\"absbottom\"/></a></td>";
	}
	
	?>
		</tr>
		<tr>
			<td class="concordgrey"><b>Hits</b></td>
	<?php
	
	/* line of hit counts */
	
	for ($i = 0; $i < $n; $i++)
	{
		echo '<td class="concordgrey"><center>' . $master_array[$i]['hits'] . '</center></td>';
	}
	
	?>
		</tr>
		<tr>
			<td class="concordgrey"><b>Cat size (MW)</b></td>
	<?php

	/* line of cat sizes */

	for ($i = 0; $i < $n; $i++)
	{
		echo '<td class="concordgrey"><center>' 
			. round(($master_array[$i]['words_in_cat'] / 1000000), 2)
			. '</center></td>';
	}
	
	?>
		</tr>
		<tr>
			<td class="concordgrey"><b>Freq per M</b></td>
	<?php
	
	/* line of per-million-words */

	for ($i = 0; $i < $n; $i++)
	{
		echo '<td class="concordgrey"><center>' 
			. $master_array[$i]['per_mill']
			. '</center></td>';
	}
	
	/* end the table and re-start for the next graph, so it can have its own number of columns */
	?>
		</tr>
	</table>
	<table class="concordtable" width="100%">
	<?php
}









function print_distribution_table($classification_handle, $classification_desc, $qname_for_link)
{
	global $corpus_sql_name;
	
	global $dbname;
	global $db_record;


	/* print header row for this table */
	?>
		<tr>
			<th colspan="5" class="concordtable">
				<?php echo "Based on classification: </i>$classification_desc</i>"; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				Category 
				<a class="menuItem" onClick="distTableSort(this, 'cat')" 
					onMouseOver="return escape('Sort by category')">[&darr;]</a>
			</td>
			<td class="concordgrey"><center>Words in category</center></td>
			<td class="concordgrey"><center>Hits in category</center></td>
			<td class="concordgrey"><center>Dispersion<br/>(no. files with 1+ hits)</center></td>
			<td class="concordgrey"><center>
				Frequency 
				<a class="menuItem" onClick="distTableSort(this, 'freq')" 
					onMouseOver="return escape('Sort by frequency per million')">[&darr;]</a>
				<br/>per million words in category
			</td>
		</tr>
	<?php



	/* variables for keeping track of totals */
	$total_words_in_all_cats = 0;
	$total_hits_in_all_cats = 0;
	$total_hit_files_in_all_cats = 0;
	$total_files_in_all_cats = 0;

	/* a list of category descriptions, for later accessing */
	$desclist = metadata_category_listdescs($classification_handle);

	/* the main query that gets table data */
	$sql_query = "SELECT md.$classification_handle as handle,
		count(db.text_id) as hits,
		count(distinct db.text_id) as files
		FROM text_metadata_for_$corpus_sql_name	as md 
		LEFT JOIN $dbname as db on md.text_id = db.text_id
		GROUP BY md.$classification_handle";

	$result = do_mysql_query($sql_query);

	/* for each category: */
	while (($c = mysql_fetch_assoc($result)) != false)
	{
		/* skip the category of "null" ie no category in this classification */
		if ($c['handle'] == '')
			continue;
			
		$hits_in_cat = $c['hits'];
		$hit_files_in_cat = $c['files'];
		list ($words_in_cat, $files_in_cat) = metadata_size_of_cat($classification_handle, $c['handle']);
		
		$link = "concordance.php?qname=$qname_for_link&newPostP=dist&newPostP_distCateg=$classification_handle&newPostP_distClass={$c['handle']}&uT=y";

		/* print a data row */
		?>
		<tr>
			<td class="concordgeneral" id="<?php echo $c['handle'];?>">
				<?php 
					if ($desclist[$c['handle']] == '')
						echo $c['handle'];
					else
						echo $desclist[$c['handle']];
				?>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo $words_in_cat;?>
			</td>
			<td class="concordgeneral" align="center">
				<a href="<?php echo $link; ?>">
					<?php echo $hits_in_cat; ?>
				</a>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo "$hit_files_in_cat out of $files_in_cat"; ?>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo round(($hits_in_cat / $words_in_cat) * 1000000, 2); ?>
			</td>
		</tr>
		<?php
		
		/* add to running totals */
		$total_words_in_all_cats += $words_in_cat;
		$total_hits_in_all_cats += $hits_in_cat;
		$total_hit_files_in_all_cats += $hit_files_in_cat;
		$total_files_in_all_cats += $files_in_cat;
	}


	/* print total row of table */
	?>
		<tr>

			<td class="concordgrey">
				Total:
			</td>
			<td class="concordgrey" align="center">
				<?php echo $total_words_in_all_cats; ?>
			</td>
			<td class="concordgrey" align="center">
				<?php echo $total_hits_in_all_cats; ?>
			</td>
			<td class="concordgrey" align="center">
				<?php echo $total_hit_files_in_all_cats; ?> out of <?php echo $total_files_in_all_cats; ?>
			</td>
			<td class="concordgrey" align="center">
				<?php echo round(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000, 2); ?>
			</td>
		</tr>
	<?php
}






function print_distribution_crosstabs($class_scheme_to_show, $class_desc_to_pass, 
	$class_scheme_for_crosstabs, $class_desc_to_pass_for_crosstabs, $qname_for_link)
{

	/* get a list of categories for the category specified in $class_scheme_to_show */
	$desclist = metadata_category_listdescs($class_scheme_to_show);
	
	
	/* for each category */
	foreach ($desclist as $h => $d)
	{
		if (empty($d))
			$d = $h;
		$table_heading = "$class_desc_to_pass_for_crosstabs / 
			where <i>$class_desc_to_pass</i> is <i>$d</i>";
	
		print_distribution_crosstabs_once($class_scheme_for_crosstabs, $table_heading,
			$class_scheme_to_show, $h, $qname_for_link);
	}
}




/* big waste of code having all this twice - but it does no harm, and there ARE small changes */
function print_distribution_crosstabs_once($classification_handle, $table_heading,
	$condition_classification, $condition_category, $qname_for_link)
{
	global $corpus_sql_name;
	
	global $dbname;
	global $db_record;


	/* print header row for this table */
	?>
		<tr>
			<th colspan="5" class="concordtable">
				<?php echo $table_heading; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey">Category</td>
			<td class="concordgrey"><center>Words in category</center></td>
			<td class="concordgrey"><center>Hits in category</center></td>
			<td class="concordgrey"><center>Dispersion<br/>(no. files with 1+ hits)</center></td>
			<td class="concordgrey"><center>Frequency<br/>per million words in category</td>
		</tr>
	<?php



	/* variables for keeping track of totals */
	$total_words_in_all_cats = 0;
	$total_hits_in_all_cats = 0;
	$total_hit_files_in_all_cats = 0;
	$total_files_in_all_cats = 0;

	/* a list of category descriptions, for later accessing */
	$desclist = metadata_category_listdescs($classification_handle);

	/* the main query that gets table data */
	$sql_query = "SELECT md.$classification_handle as handle,
		count(db.text_id) as hits,
		count(distinct db.text_id) as files
		FROM text_metadata_for_$corpus_sql_name	as md 
		LEFT JOIN $dbname as db on md.text_id = db.text_id
		WHERE $condition_classification = '$condition_category'
		GROUP BY md.$classification_handle";

	$result = do_mysql_query($sql_query);

	/* for each category: */
	while (($c = mysql_fetch_assoc($result)) != false)
	{
		/* skip the category of "null" ie no category in this classification */
		if ($c['handle'] == '')
			continue;
			
		$hits_in_cat = $c['hits'];
		$hit_files_in_cat = $c['files'];
		list ($words_in_cat, $files_in_cat) 
			= metadata_size_of_cat_thinned($classification_handle, $c['handle'], 
				$condition_classification, $condition_category);


		/* print a data row */
		?>
		<tr>
			<td class="concordgeneral">
				<?php 
					if ($desclist[$c['handle']] == '')
						echo $c['handle'];
					else
						echo $desclist[$c['handle']];
				?>
			</td>
			<td class="concordgeneral"><center>
				<?php echo $words_in_cat;?>
			</center></td>
			<td class="concordgeneral"><center>
				<?php /* TODO copy link code form other function */ echo $hits_in_cat; ?>
			</center></td>
			<td class="concordgeneral"><center>
				<?php echo "$hit_files_in_cat out of $files_in_cat"; ?>
			</center></td>
			<td class="concordgeneral"><center>
				<?php echo ($words_in_cat > 0 ? round(($hits_in_cat / $words_in_cat) * 1000000, 2) : 0) ;?>
			</center></td>
		</tr>
		<?php
		
		/* add to running totals */
		$total_words_in_all_cats += $words_in_cat;
		$total_hits_in_all_cats += $hits_in_cat;
		$total_hit_files_in_all_cats += $hit_files_in_cat;
		$total_files_in_all_cats += $files_in_cat;
	}


	/* print total row of table */
	?>
		<tr>

			<td class="concordgrey">
				Total:
			</td>
			<td class="concordgrey"><center>
				<?php echo $total_words_in_all_cats; ?>
			</center></td>
			<td class="concordgrey"><center>
				<?php echo $total_hits_in_all_cats; ?>
			</center></td>
			<td class="concordgrey"><center>
				<?php echo $total_hit_files_in_all_cats; ?> out of <?php echo $total_files_in_all_cats; ?>
			</center></td>
			<td class="concordgrey"><center>
				<?php echo ($words_in_cat > 0 ? round(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000, 2) : 0);?>
			</td>
		</tr>
	<?php
}







function distribution_header_line($query_record, $db_record)
{
	$solution_header = create_solution_heading($query_record, false);
	
	/* split and reunite */
	list($temp1, $temp2) = explode(' returned', $solution_header, 2);
	$final_string = str_replace('Your', 'Distribution breakdown for', $temp1)
		. ': this query returned'
		. $temp2;

	return $final_string;
}









?>
