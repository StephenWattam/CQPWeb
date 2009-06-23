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





/* each of these functions prints a table for the right-hand side interface */

function printquery_search()
{
	global $mysql_link;
	global $corpus_sql_name;

	global $default_per_page;
	global $username;
	
	if (isset($_GET['insertString']))
		$insertString = $_GET['insertString'];
	else
		$insertString = NULL;

	if	(   isset($_GET['insertType']) 
		&&	(	   $_GET['insertType'] == 'sq_case' 
				|| $_GET['insertType'] == 'sq_nocase' 
				|| $_GET['insertType'] == 'cqp' )   
		)
		$select_qmode = $_GET['insertType'];
	else
		$select_qmode = 'sq_nocase';

?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable">Standard Query</th>
	</tr>

	<tr><td class="concordgeneral">
	
		<form action="concordance.php" accept-charset="UTF-8" method="get"> 
	
			&nbsp;<br/>
			
			<textarea name="theData" rows="5" cols="65" wrap="physical" style="font-size: 16px;"
				><?php if (isset($insertString)) echo prepare_query_string($insertString); ?></textarea>
			&nbsp;<br/>
			&nbsp;<br/>
			
			<!-- not sure what this next parameter does -->
			<!--input type="hidden" name="qType" value="cql"/-->
			
			<table>	
				<tr><td class="basicbox">Query mode:</td>
				
				<td class="basicbox">
					<select name="qmode">
						<option value="cqp"<?php if ($select_qmode == 'cqp') echo ' selected="selected"';?>>
							CQP syntax
						</option>
						<option value="sq_nocase"<?php if ($select_qmode == 'sq_nocase') echo ' selected="selected"';?>>
							Simple query (ignore case)
						</option>
						<option value="sq_case"<?php if ($select_qmode == 'sq_case') echo ' selected="selected"';?>>
							Simple query (case-sensitive)
						</option>
					</select>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<a target="_blank" href="../doc/Simple_query_language.pdf"
						onmouseover="return escape('How to compose a search using the Simple Query language')">
						Simple query language syntax
					</a>
				</td></tr>
			
				<tr><td class="basicbox">Number of hits per page:</td>
				
				<td class="basicbox">	
					<select name="pp">
						<option value="count">count hits</option>
						<option value="10"<?php if ($default_per_page == 10) echo ' selected="selected"';?>>10</option>
						<option value="50"<?php if ($default_per_page == 50) echo ' selected="selected"';?>>50</option>
						<option value="100"<?php if ($default_per_page == 100) echo ' selected="selected"';?>>100</option>
						<option value="250"<?php if ($default_per_page == 250) echo ' selected="selected"';?>>250</option>
						<option value="350"<?php if ($default_per_page == 350) echo ' selected="selected"';?>>350</option>
						<option value="500"<?php if ($default_per_page == 500) echo ' selected="selected"';?>>500</option>
						<option value="1000<?php if ($default_per_page == 1000) echo ' selected="selected"';?>">1000</option>
						<?php
						/* this option is currently restricted to superusers, but  */
						/* perhaps I should invent a category of "power users" who */
						/* can be trusted not to misuse features like this  ????   */
						if (user_is_superuser($username))
							echo '<option value="all">show all</option>';
						?>
					</select>
				</td></tr>
	
				<tr>
				<td class="basicbox">Restriction:</td>
				<input type="hidden" name="del" size="-1" value="begin" />
				<td class="basicbox">
					<select name="t">
						<option value="" selected="selected">None (search whole corpus)</option>
						<?php
						
						/* create options for the Primary Classifcation */
						
						$sql_query = "select primary_classification_field from corpus_metadata_fixed
							where corpus = '$corpus_sql_name'";
						$result = mysql_query($sql_query, $mysql_link);
						if ($result == false) 
							exiterror_mysqlquery(mysql_errno($mysql_link), 
								mysql_error($mysql_link), __FILE__, __LINE__);
						$row = mysql_fetch_row($result);
						$field = $row[0];
						
						$catlist = metadata_category_listdescs($field);
						
						foreach ($catlist as $h => $c)
							echo "<option value=\"$field~$h\">".(empty($c) ? $h : $c)."</option>\n";

						
						/* list the user's subcorpora for this corpus */
						/* including the last set of restrictions used */
						
						$sql_query = "select subcorpus_name, numwords, numfiles from saved_subcorpora
							where corpus = '$corpus_sql_name' and user = '$username' order by subcorpus_name";
						$result = mysql_query($sql_query, $mysql_link);
						if ($result == false) 
							exiterror_mysqlquery(mysql_errno($mysql_link), 
								mysql_error($mysql_link), __FILE__, __LINE__);

						while (($row = mysql_fetch_assoc($result)) != false)
						{
							if ($row['subcorpus_name'] == '__last_restrictions')
								echo '<option value="__last_restrictions">Last restrictions ('
									. make_thousands($row['numwords']) . ' words in ' 
									. make_thousands($row['numfiles']) . ' texts)</option>';
							else
								echo '<option value="subcorpus~' . $row['subcorpus_name'] . '">'
									. 'Subcorpus: ' . $row['subcorpus_name'] . ' ('
									. make_thousands($row['numwords']) . ' words in ' 
									. make_thousands($row['numfiles']) . ' texts)</option>';
						}

						?>

					</select>
				</td></tr>
				<input type="hidden" name="del" size="-1" value="end" />			
				<tr>
					<td class="basicbox">&nbsp;</td>
					<td class="basicbox">				
						<input type="submit" value="Start Query"/>
						<input type="reset" value="Reset Query"/>
					</td>
				</tr>
			</table>
					
			<!-- this input ALWAYS comes last -->
			<input type="hidden" name="uT" value="y"/>
		</form>
	</td></tr>

</table>
<?php
}






function printquery_restricted()
{
	global $default_per_page;
	global $username;
	
	if (isset($_GET['insertString']))
		$insertString = $_GET['insertString'];
	else
		$insertString = NULL;

	if	(   isset($_GET['insertType']) 
		&&	(	   $_GET['insertType'] == 'sq_case' 
				|| $_GET['insertType'] == 'sq_nocase' 
				|| $_GET['insertType'] == 'cqp' )   
		)
		$select_qmode = $_GET['insertType'];
	else
		$select_qmode = 'sq_nocase';
	
	/* insert restrictions as checked tickboxes lower down */
	if (isset($_GET['insertRestrictions']))
	{
		preg_match_all('/\W+(\w+)=\W+(\w+)\W/', $_GET['insertRestrictions'], $matches, PREG_SET_ORDER);
		
		foreach($matches as $m)
			$checkarray[$m[1]][$m[2]] = 'checked="checked" ';
	
		unset($matches);
	}

?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable" colspan="3">Restricted Query</th>
	</tr>

	<form action="concordance.php" accept-charset="UTF-8" method="get"> 
	<tr><td class="concordgeneral" colspan="3">
	
	
			&nbsp;<br/>
			
			<textarea name="theData" rows="5" cols="65" wrap="physical" style="font-size: 16px;"
				><?php if (isset($insertString)) echo prepare_query_string($insertString); ?></textarea>
			&nbsp;<br/>
			&nbsp;<br/>
			
			<!-- not sure what this next parameter does -->
			<!--input type="hidden" name="qType" value="cql"/-->
			
			<table>	
				<tr><td class="basicbox">Query mode:</td>
				
				<td class="basicbox">
					<select name="qmode">
						<option value="cqp"<?php if ($select_qmode == 'cqp') echo ' selected="selected"';?>>
							CQP syntax
						</option>
						<option value="sq_nocase"<?php if ($select_qmode == 'sq_nocase') echo ' selected="selected"';?>>
							Simple query (ignore case)
						</option>
						<option value="sq_case"<?php if ($select_qmode == 'sq_case') echo ' selected="selected"';?>>
							Simple query (case-sensitive)
						</option>
					</select>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<a href="../doc/Simple_query_language.pdf"
						onmouseover="return escape('How to compose a search using the Simple Query language')">
						Simple query language syntax
					</a>
				</td></tr>
			
				<tr><td class="basicbox">Number of hits per page:</td>
				
				<td class="basicbox">	
					<select name="pp">
						<option value="count">count hits</option>
						<option value="10"<?php if ($default_per_page == 10) echo ' selected="selected"';?>>10</option>
						<option value="50"<?php if ($default_per_page == 50) echo ' selected="selected"';?>>50</option>
						<option value="100"<?php if ($default_per_page == 100) echo ' selected="selected"';?>>100</option>
						<option value="250"<?php if ($default_per_page == 250) echo ' selected="selected"';?>>250</option>
						<option value="350"<?php if ($default_per_page == 350) echo ' selected="selected"';?>>350</option>
						<option value="500"<?php if ($default_per_page == 500) echo ' selected="selected"';?>>500</option>
						<option value="1000<?php if ($default_per_page == 1000) echo ' selected="selected"';?>">1000</option>
						<?php
						/* this option is currently restricted to superusers, but  */
						/* perhaps I should invent a category of "power users" who */
						/* can be trusted not to misuse features like this  ????   */
						if (user_is_superuser($username))
							echo '<option value="all">show all</option>';
						?>
					</select>
				</td></tr>

				<tr>
					<td class="basicbox">&nbsp;</td>
					<td class="basicbox">				
						<input type="submit" value="Start Query"/>
						<input type="reset" value="Reset Query"/>
					</td>
				</tr>
			</table>
		</td></tr>
				
	<?php

	echo printquery_build_restriction_block($checkarray, 'query');
	echo '</table>';
}





/* this provides the metadata restrictions block that is used for queries and for subcorpora */
/* checkarray is an array of categories / classes that are to be checked */
/* if it is NULL, the query string will be searched for pairs of inherited values */
function printquery_build_restriction_block($checkarray, $thing_to_produce)
{
	global $qString;
	if ($checkarray === NULL)
	{
		/* build checkarray from the $qstring */
		preg_match_all('/&t=([^~]+)~([^&]+)/', $qString, $pairs, PREG_SET_ORDER );
		
		foreach($pairs as $p)
			$checkarray[$p[1]][$p[2]] = 'checked="checked" ';
	
		unset($pairs);
	}
	
	$block = '
		<tr>
			<th colspan="3" class="concordtable">
				Select the text-type restrictions for your '. $thing_to_produce . ':
			</th>
		</tr>
		';
		
		
	/* get a list of classifications and categories from mysql; print them here as tickboxes */
	
	$block .= '<tr><input type="hidden" name="del" size="-1" value="begin" />';

	$classifications = metadata_list_classifications();
	
	$i = 0 ;
	
	foreach ($classifications as $c)
	{
		$header_row[$i] = '<td width="33%" class="concordgrey" align="center">' 
			. $c['description'] . '</td>';
		$body_row[$i] = '<td class="concordgeneral" valign="top" nowrap="nowrap">';
		
		$catlist = metadata_category_listdescs($c['handle']);
		
		foreach ($catlist as $handle => $desc)
			$body_row[$i] .= '<input type="checkbox" name="t" value="'
				. $c['handle'] . "~$handle\" " . $checkarray[$c['handle']][$handle]
				. '/> ' . ($desc == '' ? $handle : $desc) . '<br/>';
		

		/* whitespace is gratuitous for readability */
		$body_row[$i] .= '
			&nbsp;
			</td>';
		
		$i++;
		/* print three columns at a time */
		if ( $i == 3 )
		{
			$block .= $header_row[0] . $header_row[1] . $header_row[2] . '</tr>
				<tr>
				' . $body_row[0] . $body_row[1] . $body_row[2] . '</tr>
				<tr>
				';
			$i = 0;
		}
	}
	
	if ($i > 0) /* not all cells printed */
	{
		while ($i < 3)
		{
			$header_row[$i] = '<td class="concordgrey" align="center">&nbsp;</td>';
			$body_row[$i] = '<td class="concordgeneral">&nbsp;</td>';
			$i++;
		}
		$block .= $header_row[0] . $header_row[1] . $header_row[2] . '</tr>
			<tr>
			' . $body_row[0] . $body_row[1] . $body_row[2] . '</tr>
			<tr>
			';
	}
	
	$block .= '<input type="hidden" name="del" size="-1" value="end" />
	<input type="hidden" name="uT" value="y"/></form></tr>
	';
	
	if (empty($classifications))
		$block .= '<tr><td colspan="3" class="concordgrey" align="center">
			&nbsp;<br/>
			There are no text classification schemes set up for this corpus.
			<br/>&nbsp;
			</td></tr>';

	return $block;
}





function printquery_lookup()
{
	/* much of this is the same as the form for freq list, but simpler */
	
	/* do we want to allow an option for "showing both words and tags"? */
	$primary_annotation = get_corpus_metadata('primary_annotation');
	
	$annotation_available = ( empty($primary_annotation) ? false : true );

?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable" colspan="2">Word lookup</th>
	</tr>
	
	<tr>
		<td class="concordgrey" colspan="2">
			&nbsp;<br/>
			You can use this search to find out how many words matching the form you look up
			occur in the corpus, and the different tags that they have.
			<br/>&nbsp;
		</td>
	</tr>
	
	<form action="redirect.php" method="get">
		<tr>
			<td class="concordgeneral">Enter the word-form you want to look up</td>
			<td class="concordgeneral">
				<input type="text" name="lookupString" size="32" />
				<br/>
				<em>(NB. you can use the normal wild-cards of Simple Query language)</em>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Show only words ...</td>
			<td class="concordgeneral">
				<table>
					<tr>
						<td class="basicbox">
							<p>
								<input type="radio" name="lookupType" value="begin" checked="checked" />
								starting with
							</p>
							<p>
								<input type="radio" name="lookupType" value="end" />
								ending with
							</p>
							<p>
								<input type="radio" name="lookupType" value="contain"/>
								containing
							</p>
							<p>
								<input type="radio" name="lookupType" value="exact"  />
								matching exactly
							</p>
						</td>
						<td class="basicbox" valign="center">
							... the pattern you specified
						</td>							
					</tr>
				</table>
				<!--
				<select name="lookupType">
					<option value="begin" selected="selected">starting with</option>
					<option value="end">ending with</option>
					<option value="contain">containing</option>
					<option value="exact">matching exactly</option>
				</select>
				the pattern you specified
				-->
			</td>
		</tr>
		
		<?php		
		if ($annotation_available)
		{
			echo '
			<tr>
				<td class="concordgeneral">List results by word-form, or by word-form AND tag?</td>
				<td class="concordgeneral">
					<select name="lookupShowWithTags">
						<option value="1" selected="selected">List by word-form and tag</option>
						<option value="0">Just list by word-form</option>
					</select>
				</td>
			</tr>';
		}
		?>

		<tr>
			<td class="concordgeneral">Number of items shown per page:</td>
			<td class="concordgeneral">
				<select name="pp">
					<option>10</option>
					<option selected="selected">50</option>
					<option>100</option>
					<option>250</option>
					<option>350</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral" colspan="2">
				<center>
					&nbsp;<br/>
					<input type="submit" value="Lookup " />
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="reset" value="Clear the form" />
					<br/>&nbsp;
				</center>
			</td>
		</tr>
		<input type="hidden" name="redirect" value="lookup" />
		<input type="hidden" name="uT" value="y" />
	</form>

</table>
<?php


}




function printquery_keywords()
{
	global $corpus_title;
//	global $mysql_link;
	global $corpus_sql_name;
	
	/* create the options for frequency lists to compare */
	
	/* subcorpora belonging to this user that have freqlists compiled (list of names returned) */
	$subcorpora = list_freqtabled_subcorpora();
//show_var($subcorpora);
	/* public freqlists - corpora */
	$public_corpora = list_public_whole_corpus_freqtables();
//show_var($public_corpora);
	/* public freqlists - subcorpora	(function returns associative array) */
	$public_subcorpora = list_public_freqtables();
//show_var($public_subcorpora);
	
	$list_options = "<option value=\"__entire_corpus\">Whole of $corpus_title</option>\n";
	
	foreach ($subcorpora as $s)
		$list_options .= "<option value=\"sc~$s\">Subcorpus: $s</option>\n";
	
	$list_options_list2 = $list_options;
	/* only list 2 has the "public" options */
	
	foreach ($public_corpora as $pc)
		$list_options_list2 .= ( $pc['corpus'] == $corpus_sql_name ? '' : 
			"<option value=\"pc~{$pc['corpus']}\">
			Public frequency list: {$pc['public_freqlist_desc']}</option>\n" );
		
	foreach ($public_subcorpora as $ps)
		$list_options_list2 .= "<option value=\"ps~{$ps['freqtable_name']}\">
			Public frequency list: subcorpus {$ps['subcorpus']} from corpus {$ps['corpus']}
			</option>";
	
	/* and the options for selecting an attribute */
	
	$attribute = get_corpus_annotations();
	
	$att_options = '<option value="word">Word forms</option>
		';
	
	foreach ($attribute as $k => $a)
		$att_options .= "<option value=\"$k\">$a</option>\n";
	
		

?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable" colspan="4">Keywords and key tags</th>
	</tr>
	
	<tr>
		<td class="concordgrey" colspan="4">
			<center>
				Keyword lists are compiled by comparing frequency lists you have 
				created for different subcorpora. <a href="index.php?thisQ=subcorpus&uT=y">Click 
				here to create/view frequency lists</a>.
			</center>
		</td>
	</tr>
	
	<form action="keywords.php" method="get">
		<tr>
			<td class="concordgeneral">Select frequency list 1:</td>
			<td class="concordgeneral">
				<select name="kwTable1">
					
					<?php echo $list_options; ?>
				</select>
			</td>
			<td class="concordgeneral">Select frequency list 2:</td>
			<td class="concordgeneral">
				<select name="kwTable2">
					<?php echo $list_options_list2; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Compare:</td>
			<td class="concordgeneral" colspan="3">
				<select name="kwCompareAtt">
					<?php echo $att_options; ?>
				</select>
			</td>
		</tr>
		
		<tr>
			<th class="concordtable" colspan="4">Options for keyword analysis:</th>
		</tr>
		

		<tr>
			<td class="concordgeneral">Min freq (list 1):</td>
			<td class="concordgeneral">
				<select name="kwMinFreq1">
					<option>1</option>
					<option>2</option>
					<option>3</option>
					<option>4</option>
					<option selected="selected">5</option>
					<option>6</option>
					<option>7</option>
					<option>8</option>
					<option>9</option>
					<option>10</option>
					<option>15</option>
					<option>20</option>
					<option>50</option>
					<option>100</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
			<td class="concordgeneral">Min freq (list 2):</td>
			<td class="concordgeneral">
				<select name="kwMinFreq2">
					<option>1</option>
					<option>2</option>
					<option>3</option>
					<option>4</option>
					<option selected="selected">5</option>
					<option>6</option>
					<option>7</option>
					<option>8</option>
					<option>9</option>
					<option>10</option>
					<option>15</option>
					<option>20</option>
					<option>50</option>
					<option>100</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Comparison statistic:</td>
			<td class="concordgeneral">
				<select name="kwStatistic">
					<option value="LL" selected="selected">Log-likelihood</option>
					<option value="X2">Chi-square</option>
				</select>
			</td>
			<td class="concordgeneral">Significance threshold:</td>
			<td class="concordgeneral">
				<select name="kwThreshold">
					<option value="5">5%</option>
					<option value="1">1%</option>
					<option value="0.1">0.1%</option>
					<option value="0.01" selected="selected">0.01%</option>
					<option value="0">show all</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral" colspan="4">
				<center>
					<input type="submit" name="kwMethod" value="Calculate keywords!" />
				</center>
			</td>
		</tr>
		
		<tr>
			<th class="concordtable" colspan="4">
				Options for comparing frequency lists (by filtering):
			</th>
		</tr>
		
		<tr>
			<td class="concordgeneral" colspan="2">Display words that only occur in:</td>
			<td class="concordgeneral" colspan="2">
				<!-- may need renaming! -->
				<select name="kwEmpty">
					<option value="f1">Frequency list 1</option>
					<option value="f2">Frequency list 2</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral" colspan="4">
				<center>
					<input type="submit" name="kwMethod" value="Compare lists!" />
				</center>
			</td>
		</tr>

		<input type="hidden" name="uT" value="y" />
	</form>

</table>
<?php

}





function printquery_freqlist()
{
	/* much of this is the same as the form for keywords, but simpler */
	
	global $corpus_title;
	global $corpus_sql_name;
	
	/* create the options for frequency lists to compare */
	
	/* subcorpora belonging to this user that have freqlists compiled (list of names returned) */
	$subcorpora = list_freqtabled_subcorpora();
	/* public freqlists - corpora */
	
	$list_options = "<option value=\"__entire_corpus\">Whole of $corpus_title</option>\n";
	
	foreach ($subcorpora as $s)
		$list_options .= "<option value=\"$s\">Subcorpus: $s</option>\n";
	
	/* and the options for selecting an attribute */
	
	$attribute = get_corpus_annotations();
	
	$att_options = '<option value="word">Word forms</option>
		';
	
	foreach ($attribute as $k => $a)
		$att_options .= "<option value=\"$k\">$a</option>\n";
	
		

?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable" colspan="2">Frequency lists</th>
	</tr>
	
	<tr>
		<td class="concordgrey" colspan="2">
			<center>
				You can view the frequency lists of the whole corpus and frequency lists for 
				subcorpora you have created. <a href="index.php?thisQ=subcorpus&uT=y">Click 
				here to create/view subcorpus frequency lists</a>.
			</center>
		</td>
	</tr>
	
	<form action="freqlist.php" method="get">
		<tr>
			<td class="concordgeneral">View frequency list for ...</td>
			<td class="concordgeneral">
				<select name="flTable">
					<?php echo $list_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">View a list based on ...</td>
			<td class="concordgeneral">
				<select name="flAtt">
					<?php echo $att_options; ?>
				</select>
			</td>
		</tr>
		
		<tr>
			<th class="concordtable" colspan="2">Frequency list option settings</th>
		</tr>

		<tr>
			<td class="concordgeneral">Filter the list by <em>pattern</em> - show only words/tags ...</td>
			<td class="concordgeneral">
				<select name="flFilterType">
					<option value="begin" selected="selected">starting with</option>
					<option value="end">ending with</option>
					<option value="contain">containing</option>
					<option value="exact">matching exactly</option>
				</select>
				&nbsp;&nbsp;
				<input type="text" name="flFilterString" size="32" />
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Filter the list by <em>frequency</em> - show only words/tags ...</td>
			<td class="concordgeneral">
				with frequency between 
				<input type="text" name="flFreqLimit1" size="8" />
				and
				<input type="text" name="flFreqLimit2" size="8" />
			</td>
		</tr>


		<tr>
			<td class="concordgeneral">Number of items shown per page:</td>
			<td class="concordgeneral">
				<select name="pp">
					<option>10</option>
					<option selected="selected">50</option>
					<option>100</option>
					<option>250</option>
					<option>350</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">List order:</td>
			<td class="concordgeneral">
				<select name="flOrder">
					<option value="desc" selected="selected">most frequent at top</option>
					<option value="asc">least frequent at top</option>
					<option value="alph">alphabetical order</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral" colspan="2">
				<center>
					&nbsp;<br/>
					<input type="submit" value="Show frequency list" />
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="reset" value="Clear the form" />
					<br/>&nbsp;
				</center>
			</td>
		</tr>
		<input type="hidden" name="uT" value="y" />
	</form>

</table>
<?php


}



?>
