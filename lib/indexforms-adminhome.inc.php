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
 * 
 * @file
 * 
 * 
 * Function library for adminhome interface screen.
 * 
 */


function printquery_showcorpora()
{
	global $corpus_sql_name;	/* this is not brought from global scope but inserted into it */

	$result = do_mysql_query("select * from corpus_info order by corpus asc");
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="8">Showing list of currently installed corpora</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="8">
				&nbsp;<br/>
				<em>Visible</em> means the corpus is accessible through the main menu. Invisible
				corpora can still be accessed by direct URL entry by people who know the web address.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable">Corpus</th>
			<th class="concordtable">Indexing date</th>
			<th class="concordtable" colspan="2">Visibility</th>
			<!--
			<th class="concordtable" colspan="3">Manage...</th>
			-->
			<th class="concordtable" colspan="2">Actions</th>
		</tr>
	<?php
	
	while ( ($r=mysql_fetch_assoc($result)) !== false)
	{
		if ($r['visible'])
			$visible_options = '<option value="1" selected="selected">Visible</option>
				<option value="0">Invisible</option>';
		else
			$visible_options = '<option value="1">Visible</option>
				<option value="0" selected="selected">Invisible</option>';

		
		$javalinks = ' onmouseover="corpus_box_highlight_on(\'' . $r['corpus'] 
			. '\')" onmouseout="corpus_box_highlight_off(\'' . $r['corpus'] 
			. '\')" ';

//TODO: change tooltip below to the Title of the corpus, once that is in the database (or have as column?)

		?>
		<tr>
			<td class="concordgeneral" <?php echo "id=\"corpusCell_{$r['corpus']}\""; ?>>
				<a class="menuItem" onmouseover="return escape('<?php echo $r['corpus']; ?>')" href="../<?php echo $r['corpus']; ?>">
					<strong><?php echo $r['corpus']; ?></strong>
				</a>
			</td>

			<td class="concordgeneral" align="center">
				<?php echo $r['date_of_indexing'], "\n"; ?>
			</td>			

			<form action="index.php" method="get">
			
				<td align="center" class="concordgeneral">
					<select name="updateVisible"><?php echo $visible_options; ?></select>
				</td>
				
				<td align="center" class="concordgeneral">
					<input <?php echo $javalinks; ?> type="submit" value="Update!">
				</td>
				
				<input type="hidden" name="corpus" value="<?php echo $r['corpus']; ?>" />
				<input type="hidden" name="admFunction" value="updateCorpusMetadata" />
				<input type="hidden" name="uT" value="y" />
			
			</form>
			
			<!--
			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=userAccess&uT=y">
					[Access]
				</a>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=manageMetadata&uT=y">
					[Metadata]
				</a>
			</td>

			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=manageAnnotation&uT=y">
					[Annotation]
				</a>
			</td>
			
			-->

			<td class="concordgeneral" align="center">
				<a class="menuItem" 
				<?php echo $javalinks . ' href="../' . $r['corpus']?>/index.php?thisQ=corpusSettings&uT=y">
					[Goto corpus settings]
				</a>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem"
				<?php echo $javalinks . ' href="index.php?thisF=deleteCorpus&corpus=' . $r['corpus']?>&uT=y">
					[Delete corpus]
				</a>
			</td>		
		
		</tr>
		<?php
	}
	?></table><?php
	
	/* clean up */
	unset($corpus_sql_name);
}




function printquery_installcorpus_indexed()
{
	global $Config;
	
	
	?>
	<form action="index.php" method="GET">
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">
					Install a corpus pre-indexed in CWB
				</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br/>
					<a href="index.php?thisF=installCorpus&uT=y">
						Click here to install a completely new corpus from files in the upload area.
					</a>
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify a MySQL name for this corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_mysql_name"  onKeyUp="check_c_word(this)" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Enter the full name of the corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_description" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify the CWB name (lowercase format)</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_cwb_name" onKeyUp="check_c_word(this)" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" rowspan="2">Where is the registry file?</td>
				<td class="concordgeneral">
					<input type="radio" name="corpus_useDefaultRegistry" value="1" checked="checked" />
					In CQPweb's usual registry directory 
					<a class="menuItem" onmouseover="return escape('/<?php echo $Config->dir->registry; ?>/')">
						[?]
					</a>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<input type="radio" name="corpus_useDefaultRegistry" value="0" />
					In the directory specified here:
					<br/>
					<input type="text" name="corpus_cwb_registry_folder" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Tick here if the main script in the corpus is right-to-left</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_scriptIsR2L" value="1"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Tick here if the corpus is encoded in Latin1 (iso-8859-1)
					<br/>
					<em>
						(note that the character set in CQPweb is assumed to be UTF8 unless otherwise specifed)
					</em> 
				</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_encodeIsLatin1" value="1"/>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br/>
					P-attributes (annotation) are read automatically from the registry file.
					Use "Manage annotation" to add descriptions, tagset names/links, etc. 
					<br/>&nbsp;
				</td>
			</tr>
		<?php printquery_installcorpus_stylesheetrows(); ?>
		</table>
				
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Install corpus</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Install corpus with settings above" />
					<br/>&nbsp;<br/>
					<input type="reset" value="Clear this form" />
				</td>
			</tr>
		</table>
		
		<input type="hidden" name="admFunction" value="installCorpusIndexed" />
		<input type="hidden" name="uT" value="y" />
	</form>
	
	<?php
	
}


/**
 * Returns string containing a form chunk that has in it the P-attribute definition form.
 */
function print_embiggenable_p_attribute_form($input_name_base)
{
	$html = <<<END

			<tr id="p_att_row_1">
				<td class="concordgrey" align="center">Primary?</td>
				<td class="concordgrey" align="center">Handle</td>
				<td class="concordgrey" align="center">Description</td>
				<td class="concordgrey" align="center">Tagset</td>
				<td class="concordgrey" align="center">External URL</td>
				<td class="concordgrey" align="center">Feature set?</td>
			</tr>
END;
	foreach(array(1,2,3,4,5,6) as $q)
	{
		$html .= "
			<tr>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"radio\" name=\"{$input_name_base}PPrimary\" value=\"$q\" />
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"15\" name=\"{$input_name_base}PHandle$q\" onKeyUp=\"check_c_word(this)\" />
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"150\" name=\"{$input_name_base}PDesc$q\" />
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"150\" name=\"{$input_name_base}PTagset$q\" />
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"150\" name=\"{$input_name_base}Purl$q\" />
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"checkbox\" name=\"{$input_name_base}Pfs$q\"  value=\"1\"/>
				</td>
			</tr>\n";
	}
	$html .= <<<END
			<tr id="p_embiggen_button_row">
				<td colspan="6" class="concordgrey" align="center">
					&nbsp;<br/>
					<a onClick="add_p_attribute_row()" class="menuItem">[Embiggen form]</a>
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="pNumRows" id="pNumRows" value="6"/>
			<input type="hidden" name="inputNameBase" id="inputNameBase" value="$input_name_base"/>
END;

	return $html;
}


function printquery_installcorpus_unindexed()
{
	global $Config;
	
	// TODO: add other 8-bit encodings.
	
	?>
	<form action="index.php" method="GET">
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">
					Install new corpus
				</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br/>
					<a href="index.php?thisF=installCorpusIndexed&uT=y">
						Click here to install a corpus you have already indexed in CWB.</a>
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify the MySQL name of the corpus you wish to create</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_mysql_name" onKeyUp="check_c_word(this)"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Specify the CWB name of the corpus you wish to create</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_cwb_name" onKeyUp="check_c_word(this)"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Enter the full name of the corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_description" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Tick here if the main script in the corpus is right-to-left</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_scriptIsR2L" value="1"/>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Tick here if the corpus is encoded in Latin1 (iso-8859-1)
					<br/>
					<em>
						(note that the character set in CQPweb is assumed to be UTF8 unless otherwise specifed)
					</em>
				</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_encodeIsLatin1" value="1"/>
				</td>
			</tr>
		</table>
		
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="4" class="concordtable">
					Select files
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="4">
					The following files are available (uncompressed) in the upload area. Put a tick next to
					the files you want to index into CWB format.
				</td>
			</tr>
			<tr>
				<th class="concordtable">Include?</th>
				<th class="concordtable">Filename</th>
				<th class="concordtable">Size (K)</th>
				<th class="concordtable">Date modified</th>
			</tr>
			<?php
			$file_list = scandir($Config->dir->upload);
	
			foreach ($file_list as &$f)
			{
				$file = "{$Config->dir->upload}/$f";
				
				if (!is_file($file)) continue;
				
				if (substr($f,-3) === '.gz') continue;
	
				$stat = stat($file);
				?>
				
				<tr>
					<td class="concordgeneral" align="center">
						<?php 
						echo '<input type="checkbox" name="includeFile" value="' . urlencode($f) . '" />'; 
						?>
					</td>
					
					<td class="concordgeneral" align="left"><?php echo $f; ?></td>
					
					<td class="concordgeneral" align="right";>
						<?php echo number_format(round($stat['size']/1024, 0)); ?>
					</td>
				
					<td class="concordgeneral" align="center">
						<?php echo date('Y-M-d H:i:s', $stat['mtime']); ?>
					</td>		
				</tr>
				<?php
			}
			?>
		</table>
		<table class="concordtable" width="100%" id="annotation_table_second">
			<tr>
				<th  colspan="7" class="concordtable">
					Define corpus annotation
				</th>
			</tr>
			<tr>
				<td  colspan="7" class="concordgrey">
					You do not need to specify the <em>word</em> as a P-attribute or the <em>text</em> as
					an S-attribute. Both are assumed and added automatically.
				</td>
			</tr>
		</table>
		<table class="concordtable" width="100%" id="annotation_table">
			<tr>
				<th colspan="2" class="concordtable">S-attributes (XML elements)</th>
			</tr>
			<tr id="s_att_row_1">
				<td rowspan="6" class="concordgeneral" id="s_instruction_cell">
					<input type="radio" name="withDefaultSs" value="1" checked="checked"/>
					Use default setup for S-attributes (only &lt;s&gt;)
					<br/>
					<input type="radio" name="withDefaultSs" value="0"/>
					Use custom setup (specify attributes in the boxes opposite)
					
					<br/>&nbsp<br/>
					<a onClick="add_s_attribute_row()" class="menuItem">
						[Embiggen form]
					</a>
				</td>
				<?php 
				foreach(array(1,2,3,4,5,6) as $q)
				{
					if ($q != 1) echo '<tr>';
					echo "<td align=\"center\" class=\"concordgeneral\">
							<input type=\"text\" name=\"customS$q\"  onKeyUp=\"check_c_word(this)\"/>
						</td>
					</tr>
					";
				}
				?>

		</table>
		<table class="concordtable" width="100%" id="annotation_table_third">
			<tr id="p_att_header_row">
				<th colspan="6" class="concordtable">P-attributes (word annotation)</th>
			</tr>
			
			<tr>
				<td colspan="6" class="concordgeneral" align="center">
					<table width="100%">
						<tr>
							<td class="basicbox" width="50%">
								&nbsp;<br/>
								Choose annotation template
								<br/>
								<i>(or select "Custom annotation" and specify attributes in the boxes below)</i>
								<br/>&nbsp;
							</td>
							<td class="basicbox" width="50%" align="center">
							
								<select name="useAnnotationTemplate">
									<option value='~~customPs' selected="selected">Custom annotation</option>
									
									<?php
									foreach (list_annotation_templates() as $t)
										echo "\t\t\t\t\t\t<option value=\"{$t->id}\">{$t->description}</option>\n";
									?>
									
								</select>
							
							</td>
						</tr>
					</table>
				</td>
			</tr>
			
<?php


		echo print_embiggenable_p_attribute_form('custom');
		
		?>

		</table>
		
		<table class="concordtable" width="100%">
		<?php printquery_installcorpus_stylesheetrows(); ?>
		</table>
				
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Install corpus</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Install corpus with settings above" />
					<br/>&nbsp;<br/>
					<input type="reset" value="Clear this form" />
				</td>
			</tr>
		</table>
		
		<input type="hidden" name="admFunction" value="installCorpus" />
		<input type="hidden" name="uT" value="y" />
	</form>
	
	<?php
}


function printquery_installcorpusdone()
{
	/* addslashes shouldn't be necessary here, but paranoia never hurts */
	$corpus = addslashes($_GET['newlyInstalledCorpus']);
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Your corpus has been successfully installed!
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>You can now:</p>
				<ul>
					<li>
						<a href="../<?php echo $corpus; ?>/index.php?thisQ=manageMetadata&uT=y">
							Design and insert a text-metadata table for the corpus
						</a>
						(searches won't work till you do)<br/>
					</li>
					<li>
						<a href="index.php?thisF=installCorpus&uT=y">
							Install another corpus
						</a>
					</li>
				</ul>
				<p>&nbsp;</p>
			</td>
		</tr>
	</table>
	<?php
}

function printquery_installcorpus_stylesheetrows()
{
	?>
	
			<tr>
				<th colspan="2" class="concordtable">Select a stylesheet</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="left">
					<input type="radio" name="cssCustom" value="0" checked="checked"/>
					Choose a built in stylesheet:
				</td>
				<td class="concordgeneral" align="left">
					<select name="cssBuiltIn">
						<?php
							$list = scandir('../css');
							foreach($list as &$l)
							{
								if (substr($l, -4) !== '.css')
									continue;
								else
									echo "<option>$l</option>";
							}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="left">
					<input type="radio" name="cssCustom" value="1" />
					Use the stylesheet at this URL:
				</td>
				<td class="concordgeneral" align="left">
					<input type="text" maxlength="255" name="cssCustomUrl" />
				</td>
			</tr>
	<?php
}



function printquery_deletecorpus()
{
	$corpus = $_GET['corpus'];
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				You have requested deletion of the corpus "<?php echo $corpus; ?>" from the CQPweb system.
			</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center">Are you sure you want to do this?</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form action="index.php" method="get">
					<br/>
					<input type="checkbox" name="sureyouwantto" value="yes"/>
					Yes, I'm sure I want to do this.
					<br/>&nbsp;<br/>
					<input type="submit" value="I am definitely sure I want to delete this corpus." />
					<br/>
					<input type="hidden" name="admFunction" value="deleteCorpus" />
					<input type="hidden" name="corpus" value="<?php echo $corpus; ?>" />
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
	</table>					
		
	<?php
}


function printquery_corpuscategories()
{
	global $Config;
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="6">
				Manage corpus categories
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="6">
				Corpus categories are used to organise links to corpora on CQPweb's home page.
				<br/>&nbsp;<br/>
				This behaviour can be turned on or off using the setting 
					<code>$homepage_use_corpus_categories</code>
				in your configuration file.
				<br/>&nbsp;<br/>
				Currently, it is turned <strong><?php echo ($Config->homepage_use_corpus_categories?'on':'off'); ?></strong>.
				<br/>&nbsp;<br/>
				Categories are displayed on the home page in the defined <em>sort order</em>, with low numbers shown first
				(in the case of a numerical tie, categories are sorted alphabetically).
				<br/>&nbsp;<br/>
				The available categories are listed below. Use the form at the bottom to ad a new category.
				<br/>&nbsp;<br/>
				Important note: you cannot have two categories with the same name, and you cannot delete 
				<em>&ldquo;Uncategorised&rdquo;</em>, which is the default category of a new corpus.
			</td>
		</tr>
		<tr>
			<th class="concordtable">
				Category label
			</th>
			<th class="concordtable">
				No. corpora
			</th>
			<th class="concordtable">
				Sort order
			</th>
			<th class="concordtable" colspan="3">
				Actions
			</th>
		</tr>
		
		<?php
		/* this function call is a bit wasteful, but it makes sure "Uncategorised" exists... */
		list_corpus_categories();
		
		$result = do_mysql_query("select id, label, sort_n from corpus_categories order by sort_n asc, label asc");
		$sort_key_max = 0;
		$sort_key_min = 0; 
		while (false !== ($r = mysql_fetch_object($result)))
		{
			list($n) = mysql_fetch_row(do_mysql_query("select count(*) from corpus_info where corpus_cat={$r->id}"));
			echo '<tr><td class="concordgeneral">', $r->label, '</td>',
				'<td class="concordgeneral" align="center">', $n, '</td>',
				'<td class="concordgeneral" align="center">', $r->sort_n, '</td>',
				'<td class="concordgeneral" align="center">',
					'<a class="menuItem" href="index.php?admFunction=execute&function=update_corpus_category_sort&args=',
					$r->id, urlencode('#'), $r->sort_n - 1, 
					'&locationAfter=', urlencode('index.php?thisF=manageCorpusCategories&uT=y'), '&uT=y">',
					'[Move up]</a></td>',
				'<td class="concordgeneral" align="center">',
					'<a class="menuItem" href="index.php?admFunction=execute&function=update_corpus_category_sort&args=',
					$r->id, urlencode('#'), $r->sort_n + 1, 
					'&locationAfter=', urlencode('index.php?thisF=manageCorpusCategories&uT=y'), '&uT=y">',
					'[Move down]</a></td>',
				'<td class="concordgeneral" align="center">',
					'<a class="menuItem" href="index.php?admFunction=execute&function=delete_corpus_category&args=',
					$r->id, '&locationAfter=', urlencode('index.php?thisF=manageCorpusCategories&uT=y'), '&uT=y">',
					'[Delete]</a></td>',
				"</tr>\n";
			if ($sort_key_max < $r->sort_n)
				$sort_key_max = $r->sort_n;
			if ($sort_key_min > $r->sort_n)
				$sort_key_min = $r->sort_n;
		}
		?>
		
	</table>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="5">
				Create a new category
			</th>
		</tr>
		<form action="index.php" method="GET">
			<tr>
				<td class="concordgrey" align="center">
					&nbsp;<br/>
					Specify a category label
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					<input name="newCategoryLabel" size="50" type="text" maxlength="255"/>
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					&nbsp;<br/>
					Initial sort key for this category
					<br/>
					<em>(lower numbers appear higher up)</em>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					<select name="newCategoryInitialSortKey">
					
						<?php
						/* give options for intial sort key of zero to existing range, plus one */
						for ($sort_key_min--; $sort_key_min < 0; $sort_key_min++)
							echo "\t\t<option>$sort_key_min</option>\n";
						echo "\t\t<option selected=\"selected\">0</option>\n";
						for ($sort_key_max++, $i = 1; $i <= $sort_key_max; $i++)
							echo "\t\t<option>$i</option>\n";
						?>
						 
					</select>
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="3" align="center">
					&nbsp;<br/>
					<input type="submit" value="Click here to create the new category" />
					<br/>&nbsp;
				</td>
				<input type="hidden" name="admFunction" value="newCorpusCategory" />
				<input type="hidden" name="uT" value="y" />
			</tr>
		</form>
	</table>
	
	<?php
}


function printquery_annotationtemplates()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="8">
				Manage annotation templates
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="8">
				&nbsp;<br/>
				An annotation template is a description of a predefined set of word-level annotations (p-attributes).
				<br/>&nbsp;<br/>
				You can use templates when indexing corpora instead of specifying the p-attribute information every time.
				<br/>&nbsp;<br/>
				Use the controls below to create and manage annotation templates.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="8">
				Currently-defined annotation templates
			</th>
		</tr>		
		<tr>
			<th class="concordtable">
				ID
			</th>
			<th class="concordtable">
				Description
			</th>
			<th class="concordtable" colspan="5">
				Attributes (in order of columns left-to-right; [*] = primary)
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
			
		foreach(list_annotation_templates() as $template)
		{
			$rowspan = 1 + count($template->attributes);
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">{$template->id}</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" rowspan=\"$rowspan\">{$template->description}</td>\n"
				, "\n\t\t\t", '<td class="concordgrey" align="center">N</td>'
				, '<td class="concordgrey" align="center">Handle</td><td class="concordgrey" align="center">Description</td>'
				, '<td class="concordgrey" align="center">Feature set?</td><td class="concordgrey" align="center">Tagset</td>'
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">"
				, "<a class=\"menuItem\" href=\"index.php?admFunction=deleteAnnotationTemplate&toDelete={$template->id}&uT=y\">[x]</a></td>"
				, "\n\t\t</tr>"
				;
			
			foreach($template->attributes as $k=>$att)
			{
				$star = ($att->handle == $template->primary_annotation ? ' [*] ' : '');
				
				$link = (empty($att->external_url) ? "{$att->tagset}" :"<a href=\"{$att->external_url}\" target=\"_blank\">{$att->tagset}</a>");
					
				echo "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">{$att->order_in_template}</td>"
					, "\n\t\t\t<td class=\"concordgeneral\">{$att->handle}$star</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">{$att->description}</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">", ($att->is_feature_set ? 'Y' : 'N'), "</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">$link</td>\n"
					, "\n\t\t</tr>"
					;	
			}
		}
			
		?>
		
	</table>

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="6">
				Add new annotation template
			</th>
		</tr>		
		
		<form action="index.php" method="get">

			<tr>
				<td colspan="6" class="concordgeneral" align="center">
					<table width="100%">
						<tr>
							<td class="basicbox" width="50%" align="center">
								&nbsp;<br/>
								Enter a description for your new template:
								<br/>&nbsp;
							</td>
							<td class="basicbox" width="50%" align="center">
							
								<input type="text" name="newTemplateDescription" size="60" maxlength="255">
							
							</td>
						</tr>
					</table>
				</td>
			</tr>

			<?php echo print_embiggenable_p_attribute_form('template'); ?>

			<tr>
				<td class="concordgeneral" colspan="6" align="center">
					&nbsp;<br/>
					<input type="submit" value="Click here to create annotation template"/>
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="newAnnotationTemplate" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="6">
				Install default templates
			</th>
		</tr>		
		<tr>
			<td class="concordgrey">
				&nbsp;<br/>
				The default annotation templates describe commonly-used corpus annotation patterns 
				(especially those generated by annotation tools created or used by the CWB/CQPweb developers).
					<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<form action="index.php" method="get">
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					<input type="submit" value="Load built-in annotation templates" />
					<br/>&nbsp;
				</td>
				<input type="hidden" name="admFunction" value="loadDefaultAnnotationTemplates" />
				<input type="hidden" name="uT" value="y" />
			</form>
		</tr>
	</table>
	
	<?php
}


function printquery_metadatatemplates()
{
	echo '<p class="errormessage">printquery_metadatatemplates: TODO</p>';
}


function printquery_xmltemplates()
{
	echo '<p class="errormessage">printquery_xmltemplates: TODO</p>';
}


function printquery_newupload()
{
	// TODO this form could be aesthetically much nicer. I improved it a bit in v3.1.5, but a better layout could be achieved.
	// re-use the upload interface that users have? (once they have it)
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="2">
				Add a file to the upload area
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				&nbsp;<br/>
				Files uploaded to CQPweb can be used as the input to indexing, or as database inputs.
				<br/>&nbsp;
			</td>
		</tr>
		<form enctype="multipart/form-data" action="index.php" method="POST">
			<tr>
				<td class="concordgeneral" align="center">
					Choose a file to upload: 
				</td>
				<td class="concordgeneral" align="center">
					<input type="file" name="uploadedFile" />
				</td>
			</tr>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Upload file" />
				</td>
				<td class="concordgeneral" align="center">
					<input type="reset"  value="Clear form" />
				</td>
			</tr>
		</form>
	</table>
	<?php
}


function printquery_uploadarea()
{
	global $Config;
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="7" class="concordtable">
				List of files currently in upload area
			</th>
		</tr>
		<tr>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
			<th colspan="4" class="concordtable">Actions</th>
		</tr>
		<?php
		
		$file_list = scandir($Config->dir->upload);
		
		$total_files = 0;
		$total_bytes = 0;

		foreach ($file_list as &$f)
		{
			$file = "{$Config->dir->upload}/$f";
			
			if (!is_file($file)) continue;
			
			$file_is_compressed = ( (substr($f,-3) === '.gz') ? true : false);

			$stat = stat($file);
			
			$total_files++;
			$total_bytes += $stat['size'];
			
			echo '';

			?>
			<tr>
			<td class="concordgeneral" align="left"><?php echo $f; ?></td>
			
			<td class="concordgeneral" align="right";>
				<?php echo number_format(round($stat['size']/1024, 0)); ?>
			</td>
			
			<td class="concordgeneral" align="center"><?php echo date('Y-M-d H:i:s', $stat['mtime']); ?></td>
			
			<td class="concordgeneral" align="center">
				<?php 
					if ($file_is_compressed)
						echo '&nbsp;';
					else
						echo '<a class="menuItem" href="index.php?admFunction=fileView&filename=' 
							. urlencode($f) . '&uT=y">[View]</a>';
				?>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem" href="index.php?admFunction=<?php 
					if ($file_is_compressed)
					{
						echo 'fileDecompress&filename=' .urlencode($f);
						$compress_label = '[Decompress]';
					}
					else
					{
						echo 'fileCompress&filename=' .urlencode($f);
						$compress_label = '[Compress]';
					}
				?>&uT=y"><?php echo$compress_label; ?></a>
			</td>
			
			<td class="concordgeneral" align="center">
				<?php 
				if ($file_is_compressed)
					echo '&nbsp;';
				else
					echo '<a class="menuItem" href="index.php?admFunction=fileFixLinebreaks&filename=' 
						. urlencode($f) . '&uT=y">[Fix linebreaks]</a>'; 
				?>
			</td>
			
			<td class="concordgeneral" align="center">
				<a class="menuItem" href="index.php?admFunction=fileDelete&filename=<?php 
					echo urlencode($f);
				?>&uT=y">[Delete]</a>
			</td>
			</tr>
			<?php

		}
		
		echo '<tr><td align="left" class="concordgrey" colspan="7">'
			. $total_files . ' files (' . number_format(round($total_bytes/1024, 0)) . ' K)'
			. '</td></tr>';
		
		?>
		
	</table>
	<?php
}




function printquery_useradmin()
{
	global $Config;
	
	$array_of_users = get_list_of_users();
	
	$user_list_as_options = '';
	foreach ($array_of_users as $a)
		$user_list_as_options .= "<option>$a</option>\n";
	
	
	/* before we start, add the javascript function that inserts password candidates */
	
	echo print_javascript_for_password_insert();
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="3" class="concordtable">
				Create new user
			</th>
		</tr>
		<form action="index.php" method="POST">
			<tr>
				<td class="concordgeneral">
					Enter the username you wish to create:
				</td>
				<td class="concordgeneral">
					<input type="text" name="newUsername" tabindex="1" width="30" onKeyUp="check_c_word(this)" />
				</td>
				<td class="concordgeneral" rowspan="4">
					<input type="submit" value="Create user account" tabindex="5" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter a new password for the specified user:
				</td>
				<td class="concordgeneral">
					<input type="text" id="passwordField" name="newPassword" tabindex="2" width="50" />
					<a class="menuItem" tabindex="3"
						onmouseover="return escape('Suggest a password')" onclick="insertPassword()">
						[+]
					</a>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter the user's email address:
				</td>
				<td class="concordgeneral">
					<input type="text" name="newEmail" tabindex="4" width="30" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Send verification email?
				</td>
				<td class="concordgeneral">
					<select name="verifyType">
						<?php echo ($Config->cqpweb_no_internet ? '' : '<option value="yes">Yes, send a verification email</option>'); ?>
						 
						<option value="no:Verify" selected="selected">No, auto-verify the account</option>
						<option value="no:DontVerify">No, and leave the account unverified</option>
					</select>
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="newUser"/>
			<!--<input type="hidden" name="newUserType" value="byAdmin"/>-->
			<input type="hidden" name="uT" value="y" />
		</form>
		
		
		<tr>
			<th colspan="3" class="concordtable">
				Reset a user's password
			</th>
		</tr>

		<form action="index.php" method="POST">
			<tr>
				<td class="concordgeneral">
					Select the user for password reset:
				</td>
				<td class="concordgeneral">
					<select name="userForPasswordReset">
						<option>Select user ....</option>
						<?php echo $user_list_as_options; ?>
					</select>
				</td>
				<td class="concordgeneral" rowspan="2">
					<input type="submit" value="Reset this user's password" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter new password:
				</td>
				<td class="concordgeneral">
					<input type="text" name="newPassword" width="50" />
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="resetUserPassword"/>
			<input type="hidden" name="uT" value="y" />
			<?php
			// TODO add JavaScript Are You Sure? Pop up to the submission button of this form 
			?>
		</form>

		<!--
		<tr>
			<th colspan="3" class="concordtable">
				Create a batch of user accounts
			</th>
		</tr>
		<form action="index.php" method="GET">
			<tr>
				<td class="concordgeneral">
					Enter the root for the batch of usernames:
				</td>
				<td class="concordgeneral">
					<input type="text" name="newUsername" width="30" onKeyUp="check_c_word(this)" />
				</td>
				<td class="concordgeneral" rowspan="5">
					<input type="submit" value="Create batch of users" />
					<br/>&nbsp;<br/>
					<input type="reset" value="Clear form" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter the number of accounts in the batch:
					<br/>
					<em>(Usernames will have the numbers 1 to N appended to them)</em>
				</td>
				<td class="concordgeneral">
					<input type="text" name="sizeOfBatch" width="30" />
				</td>
			<tr>
				<td rowspan="2" class="concordgeneral">
					Enter a password for the users, or assign random passwords automatically:
				</td>
				<td class="concordgeneral">
					<input type="radio" checked="checked" name="newPasswordUseRandom" value="0"/>
					<input type="text" name="newPassword" width="30" onKeyUp="check_c_word(this)" />
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<input type="radio" name="newPasswordUseRandom" value="1"/>
					Assign passwords randomly and report results in text file format
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter a group for the new users to be assigned to:
				</td>
				<td class="concordgeneral">
					<input type="text" name="batchAutogroup" width="30" onKeyUp="check_c_word(this)"  />
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="newBatchOfUsers"/>
			<input type="hidden" name="uT" value="y" />
		</form>
		-->
		
		
		<tr>
			<th colspan="3" class="concordtable">
				Delete a user account
			</th>
		</tr>
		<form action="index.php" method="GET">
			<tr>
				<td class="concordgeneral">
					Select a user to delete:
				</td>
				<td class="concordgeneral">
					<select name="userToDelete">
						<option>Select user ....</option>
						<?php echo $user_list_as_options; ?>
					</select>
				</td>
				<td class="concordgeneral">
					<input type="submit" value="Delete this user's account" />
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="deleteUser"/>
			<input type="hidden" name="uT" value="y" />
			<?php
			// TODO add JavaScript Are You Sure? Pop up to the submission button of this form 
			?>
		</form>
	</table>
	
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="4" class="concordtable">
				Set user's maximum database size
			</th>
		</tr>
		<tr>
			<td colspan="4" class="concordgrey">
				&nbsp;<br/>
				This limit allows you to control the amount of disk space that MySQL operations - such as 
				calculating distributions or collocations - can take up at one go from each user.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable">Username</th>
			<th class="concordtable">Current limit</th>
			<th class="concordtable">New limit</th>
			<th class="concordtable">Update</th>
		</tr>
		
		<?php
		$result = do_mysql_query("SELECT username, max_dbsize from user_info");
		
		while (($r = mysql_fetch_assoc($result)) !== false)
		{
			$limit_options 
				= "<option value=\"{$r['username']}#max_dbsize#100\" selected=\"selected\">100</option>\n";
			for ($n = 100, $i = 1; $i < 8; $i++)
			{
				$n *= 10;
				$w = number_format((float)$n);
				$limit_options .= "<option value=\"{$r['username']}#max_dbsize#$n\">$w</option>\n";
			}
			?>
			<form action="index.php" method="get">
				<tr>
					<td class="concordgeneral"><strong><?php echo $r['username'];?></strong></td>
					<td class="concordgeneral" align="center">
						<?php echo number_format((float)$r['max_dbsize']); ?>
   					</td>
					<td class="concordgeneral" align="center">
						<select name="args">
							<?php echo $limit_options; ?>
						</select>
					</td>
					<td class="concordgeneral" align="center"><input type="submit" value="Go!" /></td>
				</tr>
				<input type="hidden" name="admFunction" value="execute"/>
				<input type="hidden" name="function" value="update_user_setting"/>
				<input type="hidden" name="locationAfter" value="index.php?thisF=userAdmin&uT=y"/>
				<input type="hidden" name="uT" value="y" />
			</form>
			<?php
		}
		?>
		
	</table>

	<?php
}


function printquery_groupadmin()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="7" class="concordtable">
				Manage user groups
			</th>
		</tr>
		<tr>
			<th class="concordtable">ID</th>
			<th class="concordtable">Group</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Auto-add regex</th>
			<th class="concordtable">Update</th>
			<th class="concordtable">Delete</th>
		</tr>
	<?php

	foreach (get_all_groups_info() as $group)
	{
		echo "\n\t\t\t<tr>\n";
		?>
		<form action="index.php" method="GET">
			<td class="concordgeneral" align="center">
				<strong><?php echo $group->id; ?></strong>
			</td>
			<td class="concordgeneral" align="center">
				<strong><?php echo $group->group_name; ?></strong>
			</td>
			<td class="concordgeneral"  align="center">
				<?php
				if ($group->group_name == 'everybody')
					echo '<em>Group to which all users automatically belong.</em>';
				else if ($group->group_name == 'superusers')
					echo '<em>Only admin accounts belong to this group.</em>';
				else
					echo '<input type="text" maxlength="255" size="50" name="newGroupDesc" value="'
						, cqpweb_htmlspecialchars($group->description)
						, '" />';
				?>
			</td>
			
			<?php
			if ($group->group_name == 'superusers' || $group->group_name == 'everybody')
				echo '<td class="concordgeneral" colspan="3">&nbsp;</td>', "\n";				
			else
			{

				?>
				<td class="concordgeneral"  align="center">
					<input type="text" maxlength="255" size="50" name="newGroupAutojoinRegex" value="<?php
						echo cqpweb_htmlspecialchars($group->autojoin_regex);
					?>" />
				</td>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Click to update" />
				</td>
				<?php
			}
			?>
			
			<input type="hidden" name="admFunction" value="updateGroupInfo" />
			<input type="hidden" name="groupToUpdate" value="<?php echo $group->group_name; ?>" />
			<input type="hidden" name="uT" value="y" />
		</form>
	
		<?php 
		if ( ! ($group->group_name == 'superusers' || $group->group_name == 'everybody') )
		{
			?>
			<td class="concordgeneral" align="center">
				<a class="menuItem" href="index.php?admFunction=execute&function=delete_group&args=<?php
				echo $group->group_name, '&locationAfter=', urlencode('index.php?thisF=groupAdmin&uT=y');
				?>&uT=y">
					[x]
				</a>
			</td>
			<?php
		}
		echo "\n\t\t\t</tr>\n";
	}
	?>
	<tr>
		<td class="concordgrey" colspan="6">
			&nbsp;<br/>
			The &ldquo;description&rdquo; will be visible in various places in the user interface (to users as well
			as to system administrators).
			<br/>&nbsp;<br/>
			The &ldquo;auto-add regex&rdquo; determines which users will be added automatically to this group at time of
			account creation.
			<br/>&nbsp;<br/>
			Any new user whose email address matches the regular expression given here will automatically be added to
			the group in question. For example, if you set the regex to <b>(\.edu|\.ac\.uk)$</b> then all users with
			email addresses that end in .edu or .ac.uk (i.e. US and UK academic addresses) will be added to the group
			automatically. Regexes use <a href="" target="_blank">PCRE syntax</a>.
			<br/>&nbsp;<br/>
			(Note this only affects <em>new</em> user accounts, i.e. if you add or change a regex, existing accounts
			will <em>not</em> be added to the group. You can perform a 
			<em><a href="index.php?thisF=groupMembership&uT=y">bulk add</a></em> 
			to accomplish that.)
			<br/>&nbsp;
		</td>
	</tr>
	</table>
	
	<table class="concordtable" width="100%">
		<form action="index.php" method="get">
			<tr>
				<th colspan="3" class="concordtable">
					Add new group
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<br/>
					Enter the name for the new group:
					<br/>
					&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					<br/>
					<input type="text" maxlength="20" name="args" onKeyUp="check_c_word(this)" >
					<br/>
					&nbsp;
				<td class="concordgeneral" align="center">
					<br/>
					<input type="submit" value="Add this group to the system"/>
					<br/>
					&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="execute" />
			<input type="hidden" name="function" value="add_new_group" />
			<input type="hidden" name="locationAfter" value="index.php?thisF=groupAdmin&uT=y" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	
	<?php
}


function printquery_groupmembership()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="7" class="concordtable">
				Manage user groups
			</th>
		</tr>
		<tr>
			<th class="concordtable">Group</th>
			<th class="concordtable">Members</th>
			<th class="concordtable" colspan="2">Add member</th>			
			<th class="concordtable" colspan="2">Remove member</th>	
		</tr>
	<?php
	
	$group_list = get_list_of_groups();
	
	foreach ($group_list as $group)
	{
		echo '<tr>';
		echo '<td class="concordgeneral"><strong>' . $group . '</strong></td>';

		$member_list = list_users_in_group($group);
		sort($member_list);
		echo "\n<td class=\"concordgeneral\">";
		$i = 0;
		if ($group == 'everybody')
			echo '<em>All users are members of this group.</em>';
		else
		{
			foreach ($member_list as &$member)
			{
				echo $member . ' ';
				$i++;
				if ($i == 5)
				{
					echo "<br/>\n";
					$i = 0;
				}
			}
		}
		if (empty($member_list))echo '&nbsp;';
		echo '</td>';
		
		if ($group == 'superusers' || $group == 'everybody')
		{
			echo '<td class="concordgeneral" colspan="4">&nbsp;</td>';
			continue;
		}
		
		$members_not_in_group = array_diff(get_list_of_users(), $member_list);
		$options = "<option>[Select user from list]</option>\n";
		foreach ($members_not_in_group as &$m)
			$options .= "<option>$m</option>\n";		
		echo "<form action=\"index.php\" method=\"GET\">
			<td class=\"concordgeneral\" align=\"center\">
			<select name=\"userToAdd\">$options</select></td>\n";
		echo "<td class=\"concordgeneral\" align=\"center\">
			<input type=\"submit\" value=\"Add user to group\" /></td>\n";
		echo "<input type=\"hidden\" name=\"admFunction\" value=\"addUserToGroup\" />
			<input type=\"hidden\" name=\"groupToAddTo\" value=\"$group\" />
			<input type=\"hidden\" name=\"uT\" value=\"y\" /></form>\n";
		
		$options = "<option>[Select user from list]</option>\n";
		foreach ($member_list as &$m)
			$options .= "<option>$m</option>\n";
		echo "<form action=\"index.php\" method=\"GET\">\n
			<td class=\"concordgeneral\" align=\"center\">
			<select name=\"userToRemove\">$options</select></td>\n";
		echo "<td class=\"concordgeneral\" align=\"center\">
			<input type=\"submit\" value=\"Remove user from group\" /></td>\n";
		echo "<input type=\"hidden\" name=\"admFunction\" value=\"removeUserFromGroup\" />
			<input type=\"hidden\" name=\"groupToRemoveFrom\" value=\"$group\" />
			<input type=\"hidden\" name=\"uT\" value=\"y\" /></form>\n";
				
		echo '</tr>';
	}
	?>
	</table>

	<?php
	
	$g_opts = '';
	
	foreach ($group_list as $g)
		if ($g != 'superusers' && $g != 'everybody')
			$g_opts .= "\n\t\t\t\t\t\t<option value=\"$g\">$g</option>\n";
	
	?>

	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				Bulk Add:
				<br/>
				<em>Add users to group by email address pattern-match</em>
			</th>
		<tr>
			<form action="index.php" method="get">
				<td class="concordgrey" width="50%">
					<p>&nbsp;</p>
					
					<p>
						Apply group's stored pattern-match to existing users
						<br/>&nbsp;<br/>
						<i>by default, the group auto-add regex only applies to <u>new</u>
						accounts; this function adds any existing users whose emails match
						that regex to the group in question.</i>
					</p>
					
					<p>&nbsp;</p>
				</td>
				<td class="concordgeneral">
					<p>&nbsp;</p>
					
					<p>Select group:</p>
					
					<select name="group">
						<option value="">[Select a group...]</option>
						<?php echo $g_opts; ?>

					</select>
					
					<br/>&nbsp;<br/>
					
					<input type="submit" value="Click here to run group regex against existing users" />
					
					<p>&nbsp;</p>
				</td>
				<input type="hidden" name="admFunction" value="groupRegexRerun" />
				<input type="hidden" name="uT" value="y" />
			</form>
		</tr>
		<tr>
			<form action="index.php" method="get">
				<td class="concordgrey">
					<p>&nbsp;</p>
					
					<p>Apply one-off custom regex to all existing users:</p>
					
					<p>&nbsp;</p>
				</td>
				<td class="concordgeneral">
					<p>&nbsp;</p>
					
					<p>Select group:</p>
					
					<select name="group">
						<option value="">[Select a group...]</option>
						<?php echo $g_opts; ?>
											
					</select>
					
					<p>Enter the regex to apply:</p>
					
					<input type="text" maxlength="255" size="50" name="regex" />
					
					<br/>&nbsp;<br/>
					
					<input type="submit" value="Click here to add all users matching this regex to the group specified" />
					
					<p>&nbsp;</p>
				</td>
				<input type="hidden" name="admFunction" value="groupRegexApplyCustom" />
				<input type="hidden" name="uT" value="y" />
			</form>
		</tr>
		<!--
		<tr>
			<td class="concordgrey">
				<p>&nbsp;</p>
				
				<p>This functionality is coming soon.</p>
				
				<p>&nbsp;</p>
			</td>
		</tr>
		-->
	</table>
	<?php
	
	//TODO : bulk add users
}


//function printquery_groupaccess()
//{
//
//	$apache = get_apache_object('nopath');	
//
//	? >
//	<table class="concordtable" width="100%">
//		<tr>
//			<th colspan="7" class="concordtable">
//				Manage user groups
//			</th>
//		</tr>
//		<tr>
//			<th class="concordtable">Group</th>
//			<th class="concordtable">Corpus access rights</th>
//			<th class="concordtable">Actions</th>
//		</tr>
//	<?php
//	
//	
//	/* create a template for a table of tickboxes for each corpus */
//	
//	$list_of_corpora = list_corpora();
//	
//	$tableform_of_corpora = '<table width="100%"><tr>';
//
//	$i = 1;
//	foreach ($list_of_corpora as $c)
//	{
//		/* setup the template */
//		if ($i == 1)
//			$tableform_of_corpora .= '<tr>';	
//		
//		$tableform_of_corpora .= '<td class="basicbox" width="25%" style="padding:0px">'
//			. '<input type="checkbox" name="hasAccessTo_'.$c.'" value="1" __CHECKVALUE__FOR__'.$c.' />&nbsp;'.$c 
//			. '</td>';
//		if ($i == 4)
//		{
//			$tableform_of_corpora .= "</tr>\n\n\n";	
//			$i = 1;
//		}
//		else
//			$i++;
//		
//		/* and get the list of groups that has access to that corpus */
//		$apache->set_path_to_web_directory("../$c");
//		$apache->load();
//		$corpus_access_rights[$c] = $apache->get_allowed_groups();
//	}
//	if ($i > 1)
//	{
//		/* ie, if we are mid-tr */
//		while ($i <= 4)
//		{
//			$tableform_of_corpora .= '<td class="basicbox" width="25%" style="padding:0px">&nbsp;</td>';
//			$i++;
//		}
//	}
//	
//	$tableform_of_corpora .= '</tr></table>'; 
//	
//	/* OK, now render a form for each group showing the current access 
//	 * rights and allowing changes to be made */
//	
//	$list_of_groups = $apache->list_groups();
//
//	foreach ($list_of_groups as $group)
//	{
//		? >
//		
//		<form action="index.php" method="get">
//			<tr>
//				<td class="concordgeneral"><strong><?php echo $group; ? ></strong></td>
//				
//				<td class="concordgeneral">
//					
//					<?php 
//					
//					if ($group == "superusers")
//						echo "<center><br/>Superusers always have access to everything.<br/>&nbsp;";
//					else
//					{
//						foreach($list_of_corpora as $c)
//							$translations["__CHECKVALUE__FOR__$c"] 
//								= (in_array($group, $corpus_access_rights[$c]) ? 'checked="checked"' : '');
//						
//						echo strtr($tableform_of_corpora, $translations); 
//					}
//					? >
//					
//				</td>
//				
//				<td class="concordgeneral" align="center">
//					<?php
//					echo ($group == 'superusers'
//						? '&nbsp;'
//						: '<input type="submit" value="Update" />'); ? >	
//				</td>
//			</tr>
//			<input type="hidden" name="admFunction" value="accessUpdateGroupRights" />
//			<input type="hidden" name="group" value="<?php echo $group; ? >" />
//			<input type="hidden" name="uT" value="y" />
//		</form>
//		
//		<?php
//	}
//	? >
//	</table>
//	
//	
//	
//
//	<?php
//
//}


function printquery_privilegeadmin()
{
	global $Config;
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="5">
				Manage privileges
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="5">
				&nbsp;<br/>
				&ldquo;Privileges&rdquo; are rights to use different aspects of the CQPweb system: corpora,
				plugins, and so on. Once defined, privileges can be assigned (&ldquo;granted&rdquo;)
				individually to users and/or collectively to groups of users.
				<br/>&nbsp;<br/>
				What users are able to do when logged on to CQPweb is defined by the privileges that have
				been granted to them.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="5">
				Existing privileges
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				ID
			</th>
			<th class="concordtable">
				Description
			</th>
			<th class="concordtable">
				Type
			</th>
			<th class="concordtable">
				Scope
			</th>
			<th class="concordtable">
				Actions
			</th>
		</tr>
		
		<?php
		foreach (get_all_privileges_info() as $p)
		{
			$scope_cell_string = print_privilege_scope_as_html($p->type, $p->scope_object);
			
			echo "<tr>"
				, "<td class=\"concordgeneral\" align=\"center\">{$p->id}</td>"
				, "<td class=\"concordgeneral\"><em>{$p->description}</em></td>"
				, "<td class=\"concordgeneral\">{$Config->privilege_type_descriptions[$p->type]}</td>"
				, "<td class=\"concordgeneral\">$scope_cell_string</td>"
				, "<td class=\"concordgeneral\" align=\"center\">"
					, "<a class=\"menuItem\" href=\"index.php?admFunction=deletePrivilege&privilege={$p->id}&uT=y\">[Delete]</a>"
				, "</td>"
				, "</tr>\n";
		}
		?>
		
	</table>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="2">
				Create a new privilege
			</th>
		</tr>
			<td class="concordgrey" colspan="2">
				&nbsp;<br/>
				Adding a customised privilege will be added soon, for now use "generate default privileges" below.
				<br/>&nbsp;
			</td>
		<tr>
			<th class="concordtable" colspan="2">
				Generate default privileges
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				&nbsp;<br/>
				The &ldquo;default&rdquo; privileges are:
				<ul>
					<li>A <em>full access</em> privilege for each corpus;</li>
					<li>A <em>normal access</em> privilege for each corpus;</li>
					<li>A <em>restricted access</em> privilege for each corpus.</li>
				</ul>
				Generating default privileges creates these three privileges for each corpus on the system,
				if those privileges do not exist already. Existing privileges are not affected.
				<br/>&nbsp;
			</td>
		</tr>
		<form action="index.php" method="GET">
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					<b>Generate default privileges for corpus...</b>
					<select name="corpus">
					
						<option selected="selected">[Select a corpus...]</option>
						<?php
						foreach(list_corpora() as $c)
							echo "\t\t\t\t\t\t<option value=\"$c\">$c</option>\n";
						?>
						
					</select>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					<input type="submit" value="Generate default privileges for this corpus" />
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="generateDefaultPrivileges" />
			<input type="hidden" name="uT" value="y" />
		</form>
		<form action="index.php" method="GET">
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					&nbsp;<br/>
					<input type="submit" value="Generate default privileges for all corpora" />
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="generateDefaultPrivileges" />
			<input type="hidden" name="corpus" value="~~all~~" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	<?php
}


function printquery_usergrants()
{
	$priv_desc = get_all_privilege_descriptions();
	$user_list = get_list_of_users();
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">
				Manage grants of privileges to users
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				Username
			</th>
			<th class="concordtable">
				Privilege
			</th>
			<th class="concordtable">
				Expiry time
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
		
		$at_least_one_row_written = false;
				
		foreach($user_list as $user)
		{
			$grants = list_user_grants($user);
			
			$nrows = count($grants);
			
			$firstgrant = true;
			
			foreach($grants as $g)
			{
				$at_least_one_row_written = true;
				echo "<tr>"
					, ($firstgrant ? "<td class=\"concordgeneral\" align=\"center\" rowspan=\"$nrows\">$user</td>" : '')
					, "<td class=\"concordgeneral\" align=\"center\"><b>{$g->privilege_id}</b>: {$priv_desc[$g->privilege_id]}</td>"
					, "<td class=\"concordgeneral\" align=\"center\">", ($g->expiry_time < 1 ? 'Never' : date($g->expiry_time)), "</td>"
					, "<td class=\"concordgeneral\" align=\"center\">"
					, "<a class=\"menuItem\" href=\"index.php?admFunction=removeUserGrant&user=$user&privilege={$g->privilege_id}&uT=y\">[x]</a>"
					, "</td>"
					, "</tr>";
				$firstgrant = false;
			}
		}
		
		if ( ! $at_least_one_row_written)
			echo "<tr><td class=\"concordgrey\" colspan=\"4\" align=\"center\">"
				, "&nbsp;<br/>There are currently no individual-user grants.<br/>&nbsp;</td></tr>";
		
		?>
		
	</table>

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="3">
				Grant new privilege to user
			</th>
		</tr>
		<form action="index.php" method="GET">
			<tr>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					Select user:
					<select name="user">
						<option value="">[Select a user...]</option>
						<?php
						foreach ($user_list as $u)
							echo "\n\t\t\t\t\t\t<option value=\"$u\">$u</option>\n";
						?> 
					</select>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					Select privilege:
					<select name="privilege">
						<option value="">[Select a privilege...]</option>
						<?php
						foreach ($priv_desc as $id => $desc)
							echo "\n\t\t\t\t\t\t<option value=\"$id\">$id: $desc</option>\n";
						?> 
					</select>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					<input type="submit" value="Grant privilege to user!" />
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="newGrantToUser" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	<?php
}


function printquery_groupgrants()
{
	$priv_desc = get_all_privilege_descriptions();
	$group_list = get_list_of_groups();
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">
				Manage grants of privileges to groups
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				Group
			</th>
			<th class="concordtable">
				Privilege
			</th>
			<th class="concordtable">
				Expiry time
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
		
		foreach($group_list as $group)
		{
			$grants = list_group_grants($group);
			
			if ($group == 'superusers')
				echo "\t\t<tr>\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"1\"><b>superusers</b></td>\n"
					, "\t\t\t<td class=\"concordgrey\" align=\"center\" colspan=\"3\"><em>This group always has all privileges.</em></td>\n"
					, "\t\t</tr>";
			else
			{
				if (empty($grants))
				echo "\t\t<tr>\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"1\"><b>$group</b></td>\n"
					, "\t\t\t<td class=\"concordgrey\" align=\"center\" colspan=\"3\"><em>This group currently has no granted privileges.</em></td>\n"
					, "\t\t</tr>";
				else
				{
					if (0 == ($nrows = count($grants)))
						++$nrows;
					$firstgrant = true;	

					foreach($grants as $g)
					{
						echo "<tr>"
							, ($firstgrant ? "<td class=\"concordgeneral\" align=\"center\" rowspan=\"$nrows\"><b>$group</b></td>" : '')
							, "<td class=\"concordgeneral\" align=\"center\"><b>{$g->privilege_id}</b>: {$priv_desc[$g->privilege_id]}</td>"
							, "<td class=\"concordgeneral\" align=\"center\">", ($g->expiry_time < 1 ? 'Never' : date($g->expiry_time)), "</td>"
							, "<td class=\"concordgeneral\" align=\"center\">"
							, "<a class=\"menuItem\" href=\"index.php?admFunction=removeGroupGrant&group=$group&privilege={$g->privilege_id}&uT=y\">[x]</a>"
							, "</td>"
							, "</tr>";
						$firstgrant = false;
					}
				}
			}
		}
	
		?>
	</table>	
	

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="3">
				Grant new privilege to group
			</th>
		</tr>
		<form action="index.php" method="GET">
			<tr>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					Select group:
					<select name="group">
						<option value="">[Select a group...]</option>
						<?php
						foreach ($group_list as $g)
							if ($g != 'superusers')
								echo "\n\t\t\t\t\t\t<option value=\"$g\">$g</option>\n";
						?> 
					</select>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					Select privilege:
					<select name="privilege">
						<option value="">[Select a privilege...]</option>
						<?php
						foreach ($priv_desc as $id => $desc)
							echo "\n\t\t\t\t\t\t<option value=\"$id\">$id: $desc</option>\n";
						?> 
					</select>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					<input type="submit" value="Grant privilege to group!" />
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="newGrantToGroup" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>


	<table class="concordtable" width="100%">
		<tr>
			<th colspan="3" class="concordtable">
				Clone a group&rsquo;s granted privileges
			</th>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				&nbsp;<br/>
				If you "clone" privilege grants from Group A to Group B, you overwrite all the current privileges
				of Group B; it will have exactly the same set of privileges as Group A.
				<br/>&nbsp;
			</td>
		</tr>
		
		<?php
		
		$clone_group_options = '<option value="">[Select a group...]</option>';
		foreach ($group_list as $group)
		{
			if ($group == 'superusers')
				continue;
			$clone_group_options .= "<option>$group</option>\n";
		}
		
		?>
		
		<form action="index.php" method="get">
		
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					Clone from:
					<select name="groupCloneFrom">
						<?php echo $clone_group_options; ?>
					</select>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br/>
					Clone to:
					<select name="groupCloneTo">
						<?php echo $clone_group_options; ?>
					</select>
					<br/>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br/>
					<input type="submit" value="Clone access rights!" />
					<br/>&nbsp;
				</td>
			</tr>
			
			<input type="hidden" name="admFunction" value="cloneGroupGrants"/>
			<input type="hidden" name="uT" value ="y" />
			
		</form>

	</table>		

	<?php
}


function printquery_skins()
{
	global $Config;
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">
				Skins and colour schemes
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="4">
				&nbsp;<br/>
				Use the button below to re-generate built-in colour schemes:
				<br/>
				<form action="index.php" method="GET">
					<center>
						<input type="submit" value="Regenerate colour schemes!" />
					</center>
					<input type="hidden" name="admFunction" value="regenerateCSS"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="4">
				&nbsp;<br/>
				Listed below are the CSS files currently present in the upload area which do 
				<em>not</em> already appear in the main <em>css</em> directory.
				Select a file and click &ldquo;Import!&rdquo; 
				to create a copy of the file in the <em>css</em> directory.  
				<br/>&nbsp;
			</td>
		<tr>
			<th class="concordtable">Transfer?</th>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>
		<form action="index.php" method="GET">
			<?php
			$file_list = scandir($Config->dir->upload);
			
			foreach ($file_list as &$f)
			{
				$file = "{$Config->dir->upload}/$f";
				$target = "../css/$f";
				
				if (!is_file($file)) continue;	
				if (substr($f,-4) !== '.css') continue;
				if (is_file($target)) continue;
	
				$stat = stat($file);
				?>
				
				<tr>
					<td class="concordgeneral" align="center">
						<?php 
						echo '<input type="radio" name="cssFile" value="' . urlencode($f) . '" />'; 
						?>
					</td>
					
					<td class="concordgeneral" align="left"><?php echo $f; ?></td>
					
					<td class="concordgeneral" align="right";>
						<?php echo number_format(round($stat['size']/1024, 0)); ?>
					</td>
				
					<td class="concordgeneral" align="center">
						<?php echo date('Y-M-d H:i:s', $stat['mtime']); ?>
					</td>		
				</tr>
				<?php
			}
			?>
			<tr>
				<td class="concordgrey" align="center" colspan="4">
					&nbsp;<br/>
					<input type="submit" value="Transfer" />
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="transferStylesheetFile" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	<?php
}



function printquery_mappingtables()
{
	$show_existing = ( isset($_GET['showExisting']) ? (bool)$_GET['showExisting'] : false );
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="3">
				Mapping tables
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br/>
				
				&ldquo;Mapping tables&rdquo; are used in the Common Elementary Query Language (CEQL)
				system (aka &ldquo;Simple query&rdquo;).
				
				<br/>&nbsp;<br/>
				
				They transform <em>the tag the user searches for</em> (referred to as an 
				<strong>alias</strong>) into <em>the tag that actually occurs in the corpus</em>, or 
				alternatively into <em>a regular expression covering a group of tags</em> (referred to
				as the <strong>search term</strong>.
				
				<br/>&nbsp;<br/>
				
				Each alias-to-search-term mapping has the form "ALIAS" => "SEARCH TERM".  
					
				<br/>&nbsp;<br/>
				
				<?php
				
				echo '<a href="index.php?thisF=mappingTables&showExisting='
					. ($show_existing ? '0' : '1')
					. '&uT=y">Click here '
					. ($show_existing ? 'to add a new mapping table' : 'to view all stored mapping tables')
					. "</a>.\n\n";
				?>
				<br/>&nbsp;
			</td>
		</tr>
		<?php
		if ($show_existing)
		{
			/* show existing mapping tables */
			?>
			<tr>
				<th class="concordtable" colspan="3">
					Currently stored mapping tables
				</th>
			</tr>
			<tr>
				<th class="concordtable">Name (and <em>handle</em>)</th>
				<th class="concordtable">Mapping table</th>
				<th class="concordtable">Actions</th>
			</tr>
			
			<?php
			foreach(get_all_tertiary_mapping_tables() as $table)
			{
				echo '<tr>'
					. '<td class="concordgeneral">' . $table->name . ' <br/>&nbsp;<br/>(<em>' . $table->handle . '</em>)</td>'
					. '<td class="concordgeneral"><font size="-2" face="courier new, monospace">' 
					. strtr($table->mappings, array("\n"=>'<br/>', "\t"=>'&nbsp;&nbsp;&nbsp;') )
					. '</font></td>'
					. '<td class="concordgeneral" align="center">'
					. '<a class="menuItem" href="index.php?admFunction=execute&function=drop_tertiary_mapping_table&args=' 
					. $table->handle . '&locationAfter=' . urlencode('index.php?thisF=mappingTables&showExisting=1&uT=y') 
					. '&uT=y">[Delete]</a></td>'
					. "</tr>\n\n";	
			}

		}
		else
		{
			/* add new mapping table */
			?>
			<tr>
				<th class="concordtable" colspan="3">
					Create a new mapping table
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="3">
					Your mapping table must start and end in a brace <strong>{ }</strong> ; each 
					alias-to-search-term mapping but the last must be followed by a comma. 
					Use perl-style escapes for quotation marks where necessary.
					
					<br/>&nbsp;<br/>
					
					You are strongly advised to save an offline copy of your mapping table,
					as it is a lot of work to recreate if it accidentally gets deleted from
					the database.
				</td>
			</tr>
			<form action="index.php" method="get">
				<tr>
					<td class="concordgeneral" align="center" valign="top">
						Enter an ID code
						<br/> 
						(letters, numbers, and _ only)
						<br/>&nbsp;<br/>
						<input type="text" size="30" name="newMappingTableId" onKeyUp="check_c_word(this)" />
					</td>
					<td class="concordgeneral" align="center" valign="top">
						Enter the name of the mapping table:
						<br/>&nbsp;<br/>&nbsp;<br/>
						<input type="text" size="30" name="newMappingTableName"/>
					</td>
					<td class="concordgeneral" align="center" valign="top">
						Enter the mapping table code here:
						<br/>&nbsp;<br/>&nbsp;<br/>
						<textarea name="newMappingTableCode" cols="60" rows="25"></textarea>					
					</td>				
				</tr>
				<tr>
					<td class="concordgeneral" colspan="3" align="center">
						<input type="submit" value="Create mapping table!"/>
					</td>				
				</tr>
				<input type="hidden" name="admFunction" value="newMappingTable" />
				<input type="hidden" name="uT" value="y" />
			</form>
			
			
			
			<?php
		}
		?>
		<tr>
			<th class="concordtable" colspan="3">
				Built-in mapping tables
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="3" align="center">
				CQPweb contains a number of built-in mapping tables, including the Oxford Simplified Tagset 
				devised for the BNC (highly recommended).
				<br/>&nbsp;<br/>
				Use the button below to insert them into the database.
				<br/>&nbsp;<br/>

				<form action="index.php" method="get">
					<input type="submit" value="Click here to regenerate built-in mapping tables."/>
					<br/>
					<input type="hidden" name="admFunction" value="execute" />
					<input type="hidden" name="function" value="regenerate_builtin_mapping_tables" />
					<input type="hidden" name="locationAfter" 
						value="index.php?thisF=mappingTables&showExisting=1&uT=y" />
					<input type="hidden" name="uT" value="y" />
				</form>					
			</td>
		</tr>
	</table>
	<?php
}




function printquery_systemsettings()
{
	//TODO
	echo "lah lah!";
}



function printquery_systemsnapshots()
{
	global $Config;
	
	/* this dir needs to exist for us to scan it... */
	if (!is_dir($d = "{$Config->dir->upload}/dump"))
		mkdir($d);
	
	if (isset($_GET['snapshotFunction']))
		switch($_GET['snapshotFunction'])
		{
		case 'createSystemSnapshot':
			cqpweb_dump_snapshot("$d/CQPwebFullDump-" . time());
			break;
		case 'createUserdataBackup':
			cqpweb_dump_userdata("$d/dump/CQPwebUserDataDump-" . time());
			break;
		case 'undumpSystemSnapshot':
			/* check that the argument is an approrpiate-format undump file that exists */
			if 	(	preg_match('/^CQPwebFullDump-\d+$/', $_GET['undumpFile']) > 0
					&&
					is_file($_GET['undumpFile'])
				)
				/* call the function */
				cqpweb_undump_snapshot("$d/".$_GET['undumpFile']);
			else
				exiterror_parameter("Invalid filename, or file does not exist!");
			break;
		case 'undumpUserdataBackup':
			/* check that the argument is an approrpiate-format undump file that exists */
			if 	(	preg_match('/^CQPwebUserDataDump-\d+$/', $_GET['undumpFile']) > 0
					&&
					is_file($_GET['undumpFile'])
				)
				/* call the function */
				cqpweb_undump_userdata("$d/{$_GET['undumpFile']}");
			else
				exiterror_parameter("Invalid filename, or file does not exist!");
			break;
		default:
			break;
		}
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="3">
				CQPweb system snapshots
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br/>
				Use the button below to create a system snapshot (a zip file containing all the data from this
				CQPweb system's current state, <em>except</em> the CWB registry and data files).
				<br/>&nbsp;<br/>
				Snapshot files are create as .tar.gz files in the "dump" subdirectory of the upload area.
				<br/>&nbsp;<br/>
				Warning: snapshot files <em>can be very big.</em>
				<br/>&nbsp;<br/>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form action="index.php" method="get">
					<br/>
					<input type="submit" value="Create a snapshot file!" />
					<br/>
					<input type="hidden" name="thisF" value="systemSnapshots"/>
					<input type="hidden" name="snapshotFunction" value="createSystemSnapshot"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br/>
				Use the button below to create a userdata backup (a zip file containing all the 
				<strong>irreplaceable</strong> data in the system).
				<br/>&nbsp;<br/>
				Currently, this means user-saved queries and categorised queries. It is assumed
				that the corpus itself and all associated metadata is <em>not</em> irreplaceable
				(as you will have your own backup systems in place) but that user-generated data
				<em>is</em>.
				<br/>&nbsp;<br/>
				These backups are placed initially in the same location as snapshot files, but
				you should move them as soon as possible to a backup location.
				<br/>&nbsp;<br/>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form action="index.php" method="get">
					<br/>
					<input type="submit" value="Create a userdata backup file!" />
					<br/>
					<input type="hidden" name="thisF" value="systemSnapshots"/>
					<input type="hidden" name="snapshotFunction" value="createUserdataBackup"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="3">
				The following files currently exist in the "dump" directory.
			</th>
		</tr>
		<tr>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>
		<?php
		$num_files = 0;
		$file_options = "\n";
		$file_list = scandir($d);
		foreach ($file_list as &$f)
		{
			$file = "$d/$f";
			
			if (!is_file($file))
				continue;
			$stat = stat($file);
			$num_files++;
			
			$file_options .= "\t\t\t<option>$f</option>\n";

			?>
			<tr>
				<td class="concordgeneral" align="left">
					<?php echo $f; ?>
				</td>
				
				<td class="concordgeneral" align="right";>
					<?php echo number_format(round($stat['size']/1024, 0)); ?>
				</td>
				
				<td class="concordgeneral" align="center">
					<?php echo date('Y-M-d H:i:s', $stat['mtime']); ?>
				</td>
			
			</tr>
			<?php
		}
		if ($num_files < 1)
			echo "\n\n\t<tr><td class='concordgrey' align='center' colspan='3'>
				&nbsp;<br/>This directory is currently empty.<br/>&nbsp;</td></tr>\n";

		?>
		<tr>
			<th class="concordtable" colspan="3">
				Undump system snapshot
			</th>
		<tr>
			<td class="concordgeneral" colspan="3">
				<strong>Warning: this function is experimental.</strong>
				<br/>&nbsp;<br/>
				It will overwrite the current state of the CQPweb system.
				<br/>&nbsp;<br/>
				Select a file from the "dump" directory:
				
				<form action="index.php" method="get">
					<select name="undumpFile">
						<?php 
						echo ($file_options == "\n" ? '<option>No undump files available</option>' : $file_options);
						?>
					</select>
					<br/>&nbsp;<br/>
					Press the button below to overwrite CQPweb with the contents of this snapshot:
					<br/>
					<input type="submit" value="Undump snapshot" />
					<input type="hidden" name="thisF" value="systemSnapshots"/>
					<input type="hidden" name="snapshotFunction" value="undumpSystemSnapshot"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="3">
				Reload backed-up userdata
			</th>
		<tr>
			<td class="concordgeneral" colspan="3">
				<strong>Warning: this function is experimental.</strong>
				<br/>&nbsp;<br/>
				It will overwrite any queries with the same name that are in the system already.
				<br/>&nbsp;<br/>
				Select a file from the "dump" directory:
				
				<form action="index.php" method="get">
					<select>
						<?php 
						echo ($file_options== "\n" ? '<option>No undump files available</option>' : $file_options);
						?>
					</select>
					<br/>&nbsp;<br/>
					Press the button below to overwrite CQPweb with the contents of this snapshot:
					<br/>
					<input type="submit" value="Reload user data" />
					<input type="hidden" name="thisF" value="systemSnapshots"/>
					<input type="hidden" name="snapshotFunction" value="undumpUserdataBackup"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
	</table>
	<?php
}


function printquery_systemdiagnostics()
{
	global $Config;

	if (empty($_GET['runDiagnostic']))
		$_GET['runDiagnostic'] = 'none';
		
	/* every case of this switch should print an entire table, then return */
	switch ($_GET['runDiagnostic'])
	{
	case 'general':
		//TODO
		return;
		
	case 'phpStubs':
		global $cqpweb_script_files;
		$probfiles = array();
		foreach (list_corpora() as $corpus)
			foreach ($cqpweb_script_files as $file)
				if (!file_exists($curr_file = "../$corpus/$file.php"))
				{
					file_put_contents($curr_file, "<?php require('../lib/$file.inc.php'); ?>");
					chmod($curr_file, 0664);
					$probfiles[] = $curr_file;
				}
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">
					Done diagnosing issues with PHP inclusion scripts
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					<?php echo count($probfiles); ?> missing files were identified. All should now be fixed.
					<br/>&nbsp;<br/>
					<?php
					if (!empty($probfiles))
					{
						?>
						The missing files were:
						
						<ul>
						<?php
							foreach ($probfiles as $p)
								echo "<li>$p</li>" 
						?>
						</ul>
						<?php
					}
					?> 
				</td>
			</tr>
		</table>
		<?php
		return;
	
		
	case 'cqp':
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">
					Diagnosing connection to child process for CQP back-end
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					<pre>
					<?php echo "\n" . CQP::diagnose_connection($Config->path_to_cwb, $Config->dir->registry) . "\n"; ?>
					</pre>
				</td>
			</tr>
		</table>
		<?php
		return;
		
		
	case 'none':
	default:
		/* this is the only route to the rest of the function */
		break;
	}
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				CQPweb system diagnostics
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				&nbsp;<br/>
				Use the controls below to run diagnostics for parts of CQPweb that aren't working properly.
				<br/><b>UNDER DEVELOPMENT. Only some of them work.</b>
				<br/>&nbsp;<br/>
			</td>
		</tr>
		<tr>
			<th class="concordtable">
				Generalised problem check
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form action="index.php" method="get">
					<br/>
					<input type="submit" value="Run general check for common problems" />
					<br/>
					<input type="hidden" name="thisF" value="systemDiagnostics"/>
					<input type="hidden" name="runDiagnostic" value="general"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
		<tr>
			<th class="concordtable">
				Check corpus PHP inclusion files
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form action="index.php" method="get">
					<br/>
					<input type="submit" value="Run a check for missing PHP script inclusion files in corpus webfolders" />
					<br/>
					<input type="hidden" name="thisF" value="systemDiagnostics"/>
					<input type="hidden" name="runDiagnostic" value="phpStubs"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
		<tr>
			<th class="concordtable">
				Check CQP back-end
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form action="index.php" method="get">
					<br/>
					<input type="submit" value="Run a system check on the CQP back-end process connection" />
					<br/>
					<input type="hidden" name="thisF" value="systemDiagnostics"/>
					<input type="hidden" name="runDiagnostic" value="cqp"/>
					<input type="hidden" name="uT" value="y" />
				</form>
			</td>
		</tr>
	</table>
	<?php
}




function printquery_systemannouncements()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Add a system message
			</th>
		</tr>
		<form action="index.php" method="get">
			<tr>
				<td class="concordgeneral">
					<center>
						<strong>Heading:</strong>
						<input type="text" name="systemMessageHeading" size="90" maxlength="100"/>
					</center>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<center>
						<textarea name="systemMessageContent" rows="5" cols="65" 
							style="font-size: 16px;"></textarea>
						<br/>&nbsp;<br/>
						<input type="submit" value="Add system message" />
						&nbsp;&nbsp;
						<input type="reset" value="Clear form" />
						<br/>&nbsp;
					</center>
				</td>
			</tr>
			<input type="hidden" name="admFunction" value="addSystemMessage"/>
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	
	<?php
	display_system_messages();
}



function printquery_tableview()
{
	if (isset($_GET['limit']) && strlen($_GET['limit']) > 0 )
		$limit = mysql_real_escape_string($_GET['limit']);
	else
		$limit = "NO_LIMIT";

	
	if(isset($_GET['table']))
	{
		/* a table has already been chosen */
		
		?>
		<table class="concordtable" width="100%">
		
			<tr>
				<th class="concordtable">Viewing mySQL table
				<?php echo $_GET['table']; ?>
				</th>
			</tr>
			
		<tr><td class="concordgeneral">
		
		<?php
		
		$table = mysql_real_escape_string($_GET['table']);
		$sql_string = "SELECT * FROM $table";		
		if ($limit != "NO_LIMIT")
			$sql_string .= " LIMIT $limit";
		
		$result = do_mysql_query($sql_string);
		
		/* print column headers */
		echo '<table class="concordtable"><tr>';
		for ( $i = 0 ; $i < mysql_num_fields($result) ; $i++ )
			echo "<th class='concordtable'>" . mysql_field_name($result, $i)
				. "</th>";
		echo '</tr>';
		
		/* print rows */
		while ( ($row = mysql_fetch_row($result)) != FALSE )
		{
			echo "<tr>";
			foreach ($row as $r)
				echo "<td class='concordgeneral'>$r</td>\n";
			echo "</tr>\n";	
		}
		
		echo '</table>';
	}
	else
	{
		/* no table has been chosen */
		$result = do_mysql_query("SHOW TABLES");

		?>
		<table class="concordtable" width="100%">
		
			<tr>
				<th class="concordtable">View a mySQL table</th>
			</tr>

		<tr><td class="concordgeneral">
		
			<form action="index.php" method="get"> 
				<input type="hidden" name="thisF" value="tableView"/>

				<table><tr>
				<td class="basicbox">Select table to show:</td>
				
				<td class="basicbox">
					<select name="table">

		<?php
			while ( ($row = mysql_fetch_row($result)) != FALSE )
				echo "<option value='$row[0]'>$row[0]</option>\n";
		?>
					</select>
				</td></tr>
				
				<tr><td class="basicbox">Optionally, enter a LIMIT:</td>
				
				<td class="basicbox">
					<input type="text" name="limit" />
				</td></tr>
				<tr><td class="basicbox">&nbsp;</td>

				<td class="basicbox">
					<!-- this input ALWAYS comes last -->
					<input type="hidden" name="uT" value="y"/>
					<input type="submit" value="Show table"/>
				</td></tr>
				</table>

			</form>

		<?php
	}

	?></td></tr></table><?php
}


function printquery_cachecontrol()
{
	global $Config;
	
	$saved_queries = $recorded_files = $unrecorded_files = array();
	
	// TODO no file queries = entry in DB, bbut no file on disk. 
	// less vital to sort these out. dno't worry about for now.
	// create an array now but don't worry about displaqy.
	
	/* list saved queries */
	$result = do_mysql_query("select query_name from saved_queries");
	while (false !== ($r = mysql_fetch_row($result)))
		$saved_queries[] = $r[0];

//	foreach(scandir($WHAT) as $f)
//	{
//		
//	}	
//	$no_file_queries = $SUMMAT;
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="2">Cache control</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				<p>
					The <b>query cache</b> contains binary files representing saved and cached queries.
				</p>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" width="50%">
				Maximum cache size (set in the configuration file)
			</td>
			<td class="concordgeneral">
				<?php echo number_format(((float)$Config->cache_size_limit)/1024.0), " KB\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				Current cache size
			</td>
			<td class="concordgeneral">
				<?php
				
				list($size_in_bytes) = mysql_fetch_row(do_mysql_query("select sum(file_size) from saved_queries"));
				if (empty($size_in_bytes))
					$size_in_bytes = 0;
				echo number_format(((float)$size_in_bytes) / 1024.0, 0)
					, " KB<br/>("
					, number_format( ( ((float)$size_in_bytes) / ((float)$Config->cache_size_limit) ) * 100.0, 0)
					, "% of maximum)\n"
					;
				 
				?>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				Number of entries in cache table
			</td>
			<td class="concordgeneral">
				<?php
				
				list($n_table_entries) = mysql_fetch_row(do_mysql_query("select count(*) from saved_queries"));
				if (empty($n_table_entries))
					$n_table_entries = 0;
				echo number_format($n_table_entries), "\n";
				
				?>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				Number of actual files in cache directory
				<br/>
				(includes temporary files, so will be larger than the N of cache table entries)
			</td>
			<td class="concordgeneral">
				<?php 
				echo number_format(count($recorded_files) + count($unrecorded_files)); 
				?>
			</td>
		</tr>

	</table>
	
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">Cache leak monitor</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="4">
				<p>
					This table lists files that are present in the cache directory
					but do not correspond to any entry in the database's cache table. 
				</p>
				<p>
					It is quite likely that these files result from glitches in CQPweb
					and should be deleted.
				</p>
				<p>
					Note that these files are not counted towards the size limit of the
					cache, and so if they are (individually or collectively) large, your cache
					directory may substantially exceed the limit set in the CQPweb configuration.
			</td>
		</tr>	
		<tr>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
			<th class="concordtable">Delete</th>
		</tr>
		<?php
		if (empty($unrecorded_files))
		{
			?>
			
			<tr>
				<td colspan="4" class="concordgrey">
					<p>
						There are <b>no</b> files in the cache directory that lack a matching entry in the cache table.
					</p> 
				</td>
			</tr>

			<?php
		}
		else
		{
			foreach ($unrecorded_files as $f)
			{
				
				
				
			}
		}
		
		
		?>

	</table>
	<?php
}



// currently just dumps the table to the page.
// we also want options to kill, etc.
// and ideally delete any associated temp files if their names can be worked out.
// also would be good to get information on how many connections from cqpweb to mysql there are
// TODO : this, properly!
function printquery_systemprocesses()
{
	$result = do_mysql_query('SELECT * from system_processes');
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="4">Viewing running mySQL processes</th>
		</tr>
		<tr>
			<th class="concordtable" >Database being created</th>
			<th class="concordtable" >Time process began</th>
			<th class="concordtable" >Process type</th>
			<th class="concordtable" >Process ID</th>
		</tr>
		<?php
		while (($process = mysql_fetch_object($result)) !== false)
		{
			echo '<tr>'
				, '<td class="concordgeneral">' , $process->dbname , '</td>'
				, '<td class="concordgeneral">' , date(DATE_RSS, $process->begin_time) , '</td>'
				, '<td class="concordgeneral">' , $process->process_type , '</td>'
				, '<td class="concordgeneral">' , $process->process_id , '</td>'
				, "</tr>\n";
		}
		?>
	</table>
	<?php
}



function printquery_statistic($type = 'user')
{
	global $default_history_per_page;

	/* note usage of the same system of "perpaging" as the "Query History" function */
	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	switch($type)
	{
	case 'corpus':
		$bigquery = 'select corpus, count(*) as c from query_history 
			group by corpus order by c desc';
		$colhead = 'Corpus';
		$pagehead = 'for corpora';
		$list_of_corpora = list_corpora();
		break;
	case 'query':
		$bigquery = 'select cqp_query, count(*) as c from query_history 
			group by cqp_query order by c desc';
		$colhead = 'Query';
		$pagehead = 'for particular query strings';
		break;
	case 'user':
	default:
		$bigquery = 'select user, count(*) as c from query_history 
			group by user order by c desc';
		$colhead = 'Username';
		$pagehead = 'for user accounts';
		break;
	}
	
	$result = do_mysql_query($bigquery);
	
	?>
	<table width="100%" class="concordtable">
		<tr>
			<th colspan="3" class="concordtable">Usage statistics <?php echo $pagehead;?></th>
		</tr>
		<tr>
			<th class="concordtable" width="10%">No.</th>
			<th class="concordtable" width="60%"><?php echo $colhead; ?></th>
			<th class="concordtable" width="30%">No. of queries</th>
		</tr>
		
		<?php
		
		$toplimit = $begin_at + $per_page;
		$alt_toplimit = mysql_num_rows($result);

		if (($alt_toplimit + 1) < $toplimit)
			$toplimit = $alt_toplimit + 1;

		for ( $i = 1 ; $i < $toplimit ; $i++ )
		{
			if ( !($row = mysql_fetch_row($result)) )
				break;
			if ($i < $begin_at)
				continue;
			
			if ($type == 'corpus')
				if( !in_array($row[0], $list_of_corpora))
					$row[0] .= ' <em>(deleted)</em>';

			echo "<tr>\n";
			echo '<td class="concordgeneral" align="center">' . "$i</td>\n";
			echo '<td class="concordgeneral" align="left">' . "{$row[0]}</td>\n";
			echo '<td class="concordgeneral" align="center">' . number_format((float)$row[1]) . "</td>\n";
			echo "\n</tr>\n";
		}
		?>
		
	</table>
	<?php

	$navlinks = '<table class="concordtable" width="100%"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{	
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Move up the list]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysql_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Move down the list] &gt;&gt;';
	if (mysql_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;
}


function printquery_phpconfig()
{
	if (isset ($_GET['showPhpInfo']) && $_GET['showPhpInfo'])
	{
		/* this messes up the HTML styling unfortunately, but I can't see a way to stop it from doing so */
		phpinfo();
		return;
	}
	?>

	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				Internal PHP settings relevant to CQPweb
			</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgrey" align="center">
				&nbsp;<br/>
				To see the full phpinfo() dump, 
					<a href="index.php?thisF=phpConfig&showPhpInfo=1&uT=y">click here</a>.
				<br/>&nbsp;	
			</td>
		</tr>
		
		<tr>
			<th colspan="2" class="concordtable">
				General
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				PHP version
			</td>
			<td class="concordgeneral">
				<?php echo phpversion(); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Location of INI file
			</td>
			<td class="concordgeneral">
				<?php echo php_ini_loaded_file(); ?>
			</td>
		</tr>
		
		<tr>
			<th colspan="2" class="concordtable">
				Magic quotes
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Magic quotes for GET, POST, COOKIE
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('magic_quotes_gpc') ? 'On' : 'Off'; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Magic quotes at runtime
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('magic_quotes_runtime')? 'On' : 'Off'; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Magic quotes sybase mode
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('magic_quotes_sybase')? 'On' : 'Off'; ?>
			</td>
		</tr>




		<tr>
			<th colspan="2" class="concordtable">
				Memory and runtime
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				PHP's memory limit
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('memory_limit'); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum script running time 
				<br/>
				<em>(turned off by some scripts)</em>
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('max_execution_time'); ?> seconds
			</td>
		</tr>




		<tr>
			<th colspan="2" class="concordtable">
				File uploads
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				File uploads enabled
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('file_uploads')? 'On' : 'Off'; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Temporary upload directory
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('upload_tmp_dir') ; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum upload size
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('upload_max_filesize'); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum size of HTTP post data
				<br/>
				<em>(NB: uploads cannot be bigger than this)</em>
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('post_max_size'); ?>
			</td>
		</tr>



		<tr>
			<th colspan="2" class="concordtable">
				MySQL
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Client API version
			</td>
			<td class="concordgeneral">
				<?php echo mysql_get_client_info(); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Socket on localhost
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('mysql.default_socket'); ?>
			</td>
		</tr>

	</table>
	
	<?php
}

function printquery_opcodecache()
{
	$mode = detect_php_opcaching();
	$mode_names = array ('apc'=>'APC', 'opcache'=>'OPcache', 'wincache'=>'WinCache');

	$codefiles = list_cqpweb_php_files('code');
	$stubfiles = list_cqpweb_php_files('stub');
	
	?>

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Opcode cache overview
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				&nbsp;<br/>
				Opcode caches are tools to speed up PHP applications like CQPweb. Several different ones are available,
				but any individual server will only use <i>one</i>. 
				<b>APC</b>, <b>OPcache</b> and <b>winCache</b> are three opcode caches that can be monitored from within CQPweb.
				<?php
				echo '<ul>'
					, '<li><b>APC</b> '     , $mode == 'apc'      ? 'is <u>active</u>' : 'is inactive or unavailable', '.</li>'
					, '<li><b>OPcache</b> ' , $mode == 'opcache'  ? 'is <u>active</u>' : 'is inactive or unavailable', '.</li>'
					, '<li><b>WinCache</b> ', $mode == 'wincache' ? 'is <u>active</u>' : 'is inactive or unavailable', '.</li>'
					, "</ul>\n";
				?>
				
				Use the controls below to monitor your opcode cache, and to clear/reload it if necessary (e.g. after a version upgrade;
				should not normally be necessary as a properly-working cache will reload from disk automatically as needed).
				<br/>&nbsp;
			</td>
		</tr>
	</table>
	
	<?php
	
	if ($mode === false)
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">
					Opcode cache monitor unavailable
				</th>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					&nbsp;<br/>
					Opcode cache monitoring is not available (opcode cache extension not installed?)
					<br/>&nbsp;
				</td>
			</tr>
		</table>
		<?php	
	}
	else
	{
//		show_var($codefiles);
//		show_var($stubfiles);
	
		switch($mode)
		{
		case 'apc':
			$info = apc_cache_info();
			$rawinfo = $info['cache_list'];
			$fnkey = 'filename';
			$func_date_timestamp = create_function('$x', 'return $x["creation_time"];');
			$hitkey = 'num_hits';
			break;
		case 'opcache':
			$info = opcache_get_status(true);
			$rawinfo = $info['scripts'];
			$fnkey = 'full_path';
			$func_date_timestamp = create_function('$x', 'return $x["timestamp"];');
			$hitkey = 'hits';
			break;
		case 'wincache':
			$info = wincache_ocache_fileinfo(false);
			$rawinfo = $info['file_entries'];
			$fnkey = 'file_name';
			$func_date_timestamp = create_function('$x', 'return (time() - $x["add_time"]);');
			$hitkey = 'hit_count';
			break;
		}
		$codeinfo = array();
		$stubinfo = array();
		
		foreach($rawinfo as $f)
		{
			if (in_array($f[$fnkey], $stubfiles))
				$stubinfo[$f[$fnkey]] = $f;
			else if(in_array($f[$fnkey], $codefiles))
				$codeinfo[$f[$fnkey]] = $f;
		}
		
		$n_cqpweb = ($n_stub = count($stubinfo)) + ($n_code = count($codeinfo));
		$n_overall = count($rawinfo);
		
		/* locationAfter for buttons */
		$loc = '&locationAfter=' . urlencode('index.php?thisF=opcodeCache&uT=y');
		
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable" colspan="4">
					<?php echo $mode_names[$mode], ' status as of ', date('H:i:s \o\n Y-M-d'); ?>
				</th>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="4" align="center">
					&nbsp;<br/>
					<?php 
					echo "The cache contains <b>", $n_overall, "</b> files, <b>", $n_cqpweb, "</b> of which are part of CQPweb."; 
					echo "<br/>&nbsp;<br/>";
					echo "<b>", $n_stub, "</b> of these are stub-files and <b>", $n_code, "</b> of these are library code files (see below).";
					echo "<br/>&nbsp; <br/>(Stub-files present on the system: <b>", count($stubfiles), "</b>)."; 
					?>
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<th class="concordtable" colspan="4">Manipulate cache</th>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="4" align="center">
					<table class="basicbox" width="100%">
						<tr>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admFunction=execute<?php echo $loc; ?>&function=do_opcache_full_unload&uT=y">
									[Clear all files from cache]
								</a>
							</td>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admFunction=execute<?php echo $loc; ?>&function=do_opcache_full_load&args=code&uT=y">
									[Insert library files to cache]
								</a>
							</td>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admFunction=execute<?php echo $loc; ?>&function=do_opcache_full_load&args=stub&uT=y">
									[Insert stub files to cache]
								</a>
							</td>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admFunction=execute<?php echo $loc; ?>&function=do_opcache_full_load&uT=y">
									[Insert all files to cache]
								</a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<th class="concordtable">Library file</th>
				<th class="concordtable">Last loaded</th>
				<th class="concordtable">Times reused</th>
				<th class="concordtable">Actions</th>
			</tr>
			<?php
			
			$chop_off = realpath('../lib/'). '/';
			
			foreach($codefiles as $f)
			{
				echo "<tr>\n"
					, '<td class="concordgeneral">', str_replace($chop_off, '', $f), "</td>\n";
				if (isset ($codeinfo[$f]))
				{
					$i = $codeinfo[$f];
					echo '<td class="concordgeneral" align="center">', date('H:i:s \o\n Y-M-d', $func_date_timestamp($i)), "</td>\n"
						, '<td class="concordgeneral" align="center">', number_format($i[$hitkey]), "</td>\n"
						, '<td class="concordgeneral" align="center">'
							, '<a class="menuItem" href="index.php?admFunction=execute'
							, $loc, '&function=do_opcache_unload_file&args=', urlencode($f), '&uT=y">[Unload]</a>' 
						, "</td>\n"
						;
				}
				else
				{
					echo  '<td class="concordgeneral" align="center" colspan="2">-</td>'
						, '<td class="concordgeneral" align="center">'
							, '<a class="menuItem" href="index.php?admFunction=execute'
							, $loc, '&function=do_opcache_load_file&args=', urlencode($f), '&uT=y">[Load]</a>' 
						, "</td>\n"
						, "\n"
						;
				}
				echo "</tr>\n";
			}
			
			?>
		</table>
		<?php
	}
}


function printquery_advancedstats()
{

	// TODO

}


function printquery_message()
{
	
	$msg = cqpweb_htmlspecialchars($_GET['message']);
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				CQPweb says:
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>&nbsp;</p>
				<p align="center">
					<?php echo $msg; ?>
				</p>
				<p>&nbsp;</p>
			</td>
		</tr>
	</table>
	<?php
}


?>