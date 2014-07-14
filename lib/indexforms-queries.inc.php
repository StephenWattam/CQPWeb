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




/* each of these functions prints a table for the right-hand side interface */

function printquery_search()
{
	global $User;
	
	global $corpus_sql_name;
	global $corpus_uses_case_sensitivity;

	global $default_per_page;
	
	if (isset($_GET['insertString']))
		$insertString = $_GET['insertString'];
	else
		$insertString = NULL;

	if (isset($_GET['insertSubcorpus']))
		$insertSubcorpus = $_GET['insertSubcorpus'];
	else
		$insertSubcorpus = '**search all**';

	if	(   isset($_GET['insertType']) 
		&&	(	   $_GET['insertType'] == 'sq_case' 
				|| $_GET['insertType'] == 'sq_nocase' 
				|| $_GET['insertType'] == 'cqp' )   
		)
		$select_qmode = $_GET['insertType'];
	else
		$select_qmode = ($corpus_uses_case_sensitivity ? 'sq_case' : 'sq_nocase');



?>
<table class="concordtable" width="100%">

<tr>
	<th class="concordtable">Standard Query</th>
</tr>

<tr>
	<td class="concordgeneral">
	
		<form action="concordance.php" accept-charset="UTF-8" method="get"> 
	
			&nbsp;<br/>
			
			<textarea name="theData" rows="5" cols="65" style="font-size: 16px;"
				><?php if (isset($insertString)) echo prepare_query_string($insertString); ?></textarea>
			&nbsp;<br/>
			&nbsp;<br/>
			
			<table>	
				<tr>
					<td class="basicbox">Query mode:</td>
					
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
						<a target="_blank" href="../doc/cqpweb-simple-syntax-help.pdf"
							onmouseover="return escape('How to compose a search using the Simple Query language')">
							Simple query language syntax
						</a>
					</td>
				</tr>
			
				<tr>
					<td class="basicbox">Number of hits per page:</td>
					
					<td class="basicbox">	
						<select name="pp">
							<option value="count">count hits</option>
							<option value="10"<?php   if ($default_per_page == 10)   echo ' selected="selected"';?>>10</option>
							<option value="50"<?php   if ($default_per_page == 50)   echo ' selected="selected"';?>>50</option>
							<option value="100"<?php  if ($default_per_page == 100)  echo ' selected="selected"';?>>100</option>
							<option value="250"<?php  if ($default_per_page == 250)  echo ' selected="selected"';?>>250</option>
							<option value="350"<?php  if ($default_per_page == 350)  echo ' selected="selected"';?>>350</option>
							<option value="500"<?php  if ($default_per_page == 500)  echo ' selected="selected"';?>>500</option>
							<option value="1000"<?php if ($default_per_page == 1000) echo ' selected="selected"';?>>1000</option>
							<?php
							if ($User->is_admin())
								echo '<option value="all">show all</option>';
							?>

						</select>
					</td>
				</tr>
	
				<tr>
					<td class="basicbox">Restriction:</td>
					<input type="hidden" name="del" size="-1" value="begin" />
					<td class="basicbox">
						<select name="t">
							
							<?php
							
							/* first option is always whole corpus */
							echo '<option value="" ' 
								. ( $insertSubcorpus == '**search all**' ? 'selected="selected"' : '' )
								. '>None (search whole corpus)</option>'; 
							
							/* create options for the Primary Classification */
							$sql_query = "select primary_classification_field from corpus_info
								where corpus = '$corpus_sql_name'";
							$result = do_mysql_query($sql_query);
							$row = mysql_fetch_row($result);
							$field = $row[0];
							
							$catlist = metadata_category_listdescs($field);
							
							foreach ($catlist as $h => $c)
								echo "<option value=\"$field~$h\">".(empty($c) ? $h : $c)."</option>\n";
	
							
							/* list the user's subcorpora for this corpus */
							/* including the last set of restrictions used */
							
							$sql_query = "select subcorpus_name, numwords, numfiles from saved_subcorpora
								where corpus = '$corpus_sql_name' and user = '{$User->username}' order by subcorpus_name";
							$result = do_mysql_query($sql_query);
	
							while (($row = mysql_fetch_assoc($result)) != false)
							{
								if ($row['subcorpus_name'] == '__last_restrictions')
									echo '<option value="__last_restrictions">Last restrictions ('
										. number_format((float)$row['numwords']) . ' words in ' 
										. number_format((float)$row['numfiles']) . ' texts)</option>';
								else
									echo '<option value="subcorpus~' . $row['subcorpus_name'] . '"'
										. ($insertSubcorpus == $row['subcorpus_name'] ? ' selected="selected"' : '')
										. '>'
										. 'Subcorpus: ' . $row['subcorpus_name'] . ' ('
										. number_format((float)$row['numwords']) . ' words in ' 
										. number_format((float)$row['numfiles']) . ' texts)</option>';
							}
	
							?>
	
						</select>
					</td>
				</tr>
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
	</td>
</tr>

</table>
<?php
}






function printquery_restricted()
{
	global $User;
	
	global $default_per_page;
	global $corpus_uses_case_sensitivity;
	
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
		$select_qmode = ($corpus_uses_case_sensitivity ? 'sq_case' : 'sq_nocase');
	
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
	<tr>
		<td class="concordgeneral" colspan="3">
	
			&nbsp;<br/>
			
			<textarea name="theData" rows="5" cols="65" style="font-size: 16px;"
				><?php if (isset($insertString)) echo prepare_query_string($insertString); ?></textarea>
			&nbsp;<br/>
			&nbsp;<br/>
			
			<table>	
				<tr>
					<td class="basicbox">Query mode:</td>
				
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
						<a target="_blank" href="../doc/cqpweb-simple-syntax-help.pdf"
							onmouseover="return escape('How to compose a search using the Simple Query language')">
							Simple query language syntax
						</a>
					</td>
				</tr>
			
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
						/* this option is currently restricted to superusers, but  *
						 * perhaps I should invent a category of "power users" who *
						 * can be trusted not to misuse features like this  ????   */
						if ($User->is_admin())
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

	echo printquery_build_restriction_block((isset($checkarray) ? $checkarray : NULL), 'query');
	echo '</table>';
}





/**
 * This provides the metadata restrictions block that is used for queries and for subcorpora.
 * checkarray is an array of categories / classes that are to be checked;
 * if it is NULL, the http query string will be searched for pairs of inherited values.
 */
function printquery_build_restriction_block($checkarray, $thing_to_produce)
{
	if ($checkarray === NULL)
	{
		/* build checkarray from the http query string */
		preg_match_all('/&t=([^~]+)~([^&]+)/', $_SERVER['QUERY_STRING'], $pairs, PREG_SET_ORDER );
		
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
				. $c['handle'] . "~$handle\" " . (isset($checkarray[$c['handle']][$handle]) ? $checkarray[$c['handle']][$handle] : '') 
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




/**
 * Returns a string containing a blob of HTML that contains info about the CWB/CQP attributes
 * available to those using CQP syntax.  
 */
function printquery_build_attribute_block()
{
	$html = "<p>The following p-attributes are available in this corpus:</p>\n\n";
	
	$html .= "<center>\n<table class=\"basicbox\" width=\"80%\">\n";
	
	$html .= "<tr>\t<td width=\"18%\"><code>word</code></td>\t<td>Main word-token attribute</td>\t</tr>\n";
	
	foreach(get_corpus_annotation_info() as $p)
	{
		$tagset = (empty($p->tagset) ? '' : "(using {$p->tagset})");
		$html .= "<tr>\t<td><code>{$p->handle}</code></td>\t<td>{$p->description}$tagset</td>\t</tr>\n";
	}
	
	$html .= "</table>\n\n\n";
	
	$html .= "<p>The following s-attributes are available in this corpus:</p>\n\n<ul>\n";
	
	foreach(get_xml_all() as $s)
		$html .= "\t<li><code>$s</code></li>\n";
	
	$html .= "</ul>\n\n";
	
	return $html;
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
	global $Config;
	global $corpus_title;
	global $corpus_sql_name;
	
	/* create the options for frequency lists to compare */
	
	/* subcorpora belonging to this user that have freqlists compiled (list of names returned) */
	$subcorpora = list_freqtabled_subcorpora();
	sort($subcorpora);

	/* public freqlists - corpora */
	$public_corpora = list_public_whole_corpus_freqtables();

	/* public freqlists - subcorpora	(function returns associative array) */
	$public_subcorpora = list_public_freqtables();

	
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
				&nbsp;<br/>
				Keyword lists are compiled by comparing frequency lists you have created for different subcorpora. 
				<a href="index.php?thisQ=subcorpus&uT=y">Click here to create/view frequency lists</a>.
				<br/>&nbsp;
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
			<td class="concordgeneral" rowspan="2">Show:</td>
			<td class="concordgeneral" rowspan="2">
				<select name="kwWhatToShow">
					<option value="allKey" >All keywords</option>
					<option value="onlyPos">Positive keywords</option>
					<option value="onlyNeg">Negative keywords</option>
					<?php if ($Config->hide_experimental_features) ; else { ?>
					<option value="lock"   >Lockwords</option>
					<?php  } ?>
				</select>
			</td>
			<td class="concordgeneral">Comparison statistic:</td>
			<td class="concordgeneral">
				<select name="kwStatistic">
					<option value="LL"   selected="selected">Log-likelihood</option>
					<?php if ($Config->hide_experimental_features) ; else { ?>
					<option value="LR_LL">Log Ratio with Log-likelihood filter</option>
					<option value="LR_CI">Log Ratio with Confidence Interval filter</option>
					<option value="LR_UN">Log Ratio (unfiltered)</option>
					<?php } ?>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">
				Significance cut-off point:
				<?php if (! $Config->hide_experimental_features) echo '<br>(or confidence interval width)'; ?>
				</td>
			<td class="concordgeneral">
				<select name="kwAlpha">
					<option value="0.05"      >5%</option>
					<option value="0.01"      >1%</option>
					<option value="0.001"     >0.1%</option>
					<option value="0.0001" selected="selected">0.01%</option>
					<option value="0.00001"   >0.001%</option>
					<option value="0.000001"  >0.0001%</option>
					<option value="0.0000001" >0.00001%</option>
					<option value="1.0"       >No cut-off</option>
				</select>
				<?php if (! $Config->hide_experimental_features) {  ?><br>
				<input name="kwFamilywiseCorrect" value="Y" type="checkbox" checked="checked" />
				Use Šidák correction?<?php } ?>
			</td>
		</tr>
		
		
		<tr>
			<td class="concordgeneral">Min. frequency (list 1):</td>
			<td class="concordgeneral">
				<select name="kwMinFreq1">
					<option>1</option>
					<option>2</option>
					<option selected="selected">3</option>
					<option>4</option>
					<option>5</option>
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
			<td class="concordgeneral">Min. frequency (list 2):</td>
			<td class="concordgeneral">
				<select name="kwMinFreq2">
					<option>0</option>
					<option>1</option>
					<option>2</option>
					<option selected="selected">3</option>
					<option>4</option>
					<option>5</option>
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
			<td class="concordgeneral" colspan="4">
				<center>
					&nbsp;<br>
					<input type="submit" name="kwMethod" value="Calculate keywords" />
					<br>&nbsp;
				</center>
			</td>
		</tr>
		
		<tr>
			<th class="concordtable" colspan="4">
				View unique words or tags on one frequency list:
			</th>
		</tr>
		
		<tr>
			<td class="concordgeneral" colspan="2">Display items that occur in:</td>
			<td class="concordgeneral" colspan="2">
				<select name="kwEmpty">
					<option value="f1">Frequency list 1 but NOT frequency list 2</option>
					<option value="f2">Frequency list 2 but NOT frequency list 1</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral" colspan="4">
				<center>
					&nbsp;<br>
					<input type="submit" name="kwMethod" value="Show unique items on list" />
					<br>&nbsp;
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

/* not really a "query", but closer to belonging in this file than any other. */
function printquery_corpusmetadata()
{
	global $corpus_title;
	global $corpus_sql_name;
	global $corpus_cqp_name;

	?>
	<table class="concordtable" width="100%">
	
		<tr>
			<th colspan="2" class="concordtable">Metadata for <?php echo $corpus_title; ?> 
			</th>
		</tr>

	<?php
	
	/* set up the data we need */
	
	/* load metadata into two result arrays */

	$sql_query = "select * from corpus_info where corpus = '$corpus_sql_name'";
	$result_fixed = do_mysql_query($sql_query);
	/* this will only contain a single row */
	$metadata_fixed = mysql_fetch_assoc($result_fixed);
	
	$sql_query = "select * from corpus_metadata_variable where corpus = '$corpus_sql_name'";
	$result_variable = do_mysql_query($sql_query);	
	
	/* number of files in corpus */
	$result_textlist = do_mysql_query("select count(text_id) from text_metadata_for_$corpus_sql_name");
	list($num_texts) = mysql_fetch_row($result_textlist);
	$num_texts = number_format((float)$num_texts);
	
	/* now get total word length of all files */
	$words_in_all_texts = number_format((float)$tokens = get_corpus_wordcount());
	
	/* work out number of types and type token ratio */
	$result_temp = do_mysql_query("show tables like 'freq_corpus_{$corpus_sql_name}_word'");
	if (mysql_num_rows($result_temp) > 0)
	{
		$result_types = do_mysql_query("select count(distinct(item)) from freq_corpus_{$corpus_sql_name}_word");
		list($types) = mysql_fetch_row($result_types);
		$types_in_corpus = number_format((float)$types);
		$type_token_ratio = round( ($types / $tokens) , 2) . ' types per token';
	}
	else
	{
		$types_in_corpus = 'Cannot be calculated (frequency tables not set up)';
		$type_token_ratio = 'Cannot be calculated (frequency tables not set up)';	
	}
	
	/* get a list of metadata_fields */
	$sql_query = "select handle from text_metadata_fields where corpus = '$corpus_sql_name'";
	$result_textfields = do_mysql_query($sql_query);

	/* get a list of annotations */
	$sql_query = "select * from annotation_metadata where corpus = '$corpus_sql_name'";
	$result_annotations = do_mysql_query($sql_query);
	
	/* create a placeholder for the primary annotation's description */
	$primary_annotation_string = $metadata_fixed['primary_annotation'];
	/* the description itself will be grabbed when we scroll through the full list of annotations */
		
	
	
	?>
		<tr>
			<td width="50%" class="concordgrey">Corpus name</td>
			<td width="50%" class="concordgeneral"><?php echo $corpus_title; ?></td>
		</tr>
		<tr>
			<td class="concordgrey">CQPweb's short handles for this corpus</td>
			<td class="concordgeneral"><?php echo "$corpus_sql_name / $corpus_cqp_name"; ?></td>
		</tr>
		<tr>
			<td class="concordgrey">Total number of corpus texts</td>
			<td class="concordgeneral"><?php echo $num_texts; ?></td>
		</tr>
		<tr>
			<td class="concordgrey">Total words in all corpus texts</td>
			<td class="concordgeneral"><?php echo $words_in_all_texts; ?></td>
		</tr>
		<tr>
			<td class="concordgrey">Word types in the corpus</td>
			<td class="concordgeneral"><?php echo $types_in_corpus; ?></td>
		</tr>
		<tr>
			<td class="concordgrey">Type:token ratio</td>
			<td class="concordgeneral"><?php echo $type_token_ratio; ?></td>
		</tr>

	<?php
	
	
	/* VARIABLE METADATA */
	while (($metadata = mysql_fetch_assoc($result_variable)) != false)
	{
		/* if it looks like a URL, linkify it */
		if (0 < preg_match('|^https?://\S+$|', $metadata['value']))
			$metadata['value'] = "<a href=\"{$metadata['value']}\" target=\"_blank\">{$metadata['value']}</a>";
		/* TODO: make the above work the same as in the text metadata display? */
		?>
		<tr>
			<td class="concordgrey"><?php echo htmlspecialchars($metadata['attribute']); ?></td>
			<td class="concordgeneral"><?php echo htmlspecialchars($metadata['value']); ?></td>
		</tr>
		<?php
	}
	
	?>
		<tr>
			<th class="concordtable" colspan="2">Text metadata and word-level annotation</td>
		</tr>
	<?php
	
	
	/* TEXT CLASSIFICATIONS */
	$num_rows = mysql_num_rows($result_textfields);
	?>
		<tr>
			<td rowspan="<?php echo $num_rows; ?>" class="concordgrey">
				The database stores the following information for each text in the corpus:
			</td>
	<?php
	$i = 1;
	while (($metadata = mysql_fetch_row($result_textfields)) != false)
	{
		echo '<td class="concordgeneral">';
		echo metadata_expand_field($metadata[0]);
		echo '</td></tr>';
		if (($i) < $num_rows)
			echo '<tr>';
		$i++;
	}
	if ($i == 1)
		echo '<td class="concordgeneral">There is no text-level metadata for this corpus.</td></tr>';
	?>
		<tr>
			<td class="concordgrey">The <b>primary</b> classification of texts is based on:</td>
			<td class="concordgeneral">
				<?php 
				echo (empty($metadata_fixed['primary_classification_field'])
					? 'A primary classification scheme for texts has not been set.'
					: metadata_expand_field($metadata_fixed['primary_classification_field'])); 
				?>
			</td>
		</tr>
	<?php	
	
	
	/* ANNOTATIONS */
	$num_rows = mysql_num_rows($result_annotations);
	?>
		<tr>
			<td rowspan="<?php echo $num_rows; ?>" class="concordgrey">
				Words in this corpus are annotated with:
			</td>
	<?php
	$i = 1;
	while (($annotation = mysql_fetch_assoc($result_annotations)) != false)
	{
		echo '<td class="concordgeneral">';
		if ($annotation['description'] != "")
		{
			echo $annotation['description'];
			
			/* while we're looking at the description, save it for later if this
			 * is the primary annotation */
			if ($primary_annotation_string == $annotation['handle'])
				$primary_annotation_string  = $annotation['description'];
		}
		else
			echo $annotation['handle'];
		if ($annotation['tagset'] != "")
		{
			echo ' (';
			if ($annotation['external_url'] != "")
				echo '<a target="_blank" href="' . $annotation['external_url'] 
					. '">' . $annotation['tagset'] . '</a>';
			else
				echo $annotation['tagset'];
			echo ')';
		}	
			
		echo '</td></tr>';
		if (($i) < $num_rows)
			echo '<tr>';
		$i++;
	}
	/* if there were no annotations.... */
	if ($i == 1)
		echo '<td class="concordgeneral">There is no word-level annotation in this corpus.</td></tr>';
	?>
		<tr>
			<td class="concordgrey">The <b>primary</b> tagging scheme is:</td>
			<td class="concordgeneral">
				<?php 
				echo empty($primary_annotation_string) 
					? 'A primary tagging scheme has not been set' 
					: $primary_annotation_string; 
				?>
			</td>
		</tr>
	<?php		
	
	
	/* EXTERNAL URL */
	if ( $metadata_fixed['external_url'] != "" )
	{
		?>
		<tr>
			<td class="concordgrey">
				Further information about this corpus is available on the web at:
			</td>
			<td class="concordgeneral">
				<a target="_blank" href="<?php echo $metadata_fixed['external_url']; ?>">
					<?php echo $metadata_fixed['external_url']; ?>
				</a>
			</td>
		</tr>
		<?php
	}
		
	?>	
	</table>
	<?php
}



?>
