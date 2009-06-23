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







function printquery_corpusoptions()
{
	global $corpus_sql_name;
	global $mysql_link;
	
	if (isset($_GET['settingsUpdateURL']))
	{
		$sql_query = "update corpus_metadata_fixed set external_url = '"
			. mysql_real_escape_string($_GET['settingsUpdateURL']) 
			. "' where corpus = '$corpus_sql_name'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);		
	}
	if (isset($_GET['settingsUpdatePrimaryClassification']))
	{
		$sql_query = "update corpus_metadata_fixed set primary_classification_field = '"
			. mysql_real_escape_string($_GET['settingsUpdatePrimaryClassification']) 
			. "' where corpus = '$corpus_sql_name'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);		
	}

	$classifications = metadata_list_classifications();
	$class_options = '';
	
	$primary = get_corpus_metadata('primary_classification_field');
	
	foreach ($classifications as &$class)
	{
		$class_options .= "<option value=\"{$class['handle']}\"";
		$class_options .= ($class['handle'] === $primary ? 'selected="selected"' : '');
		$class_options .= '>' . $class['description'] . '</option>';
	}


//	show_var($c = get_corpus_matadata('corpus_cat'));
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">Corpus options</th>
		</tr>
	</table>
	
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="3">General options</th>
		</tr>
		<form action="execute.php" method="get">
			<tr>
				<td class="concordgrey" align="center">
					The corpus is currently in the following category:
				</td>
				<td class="concordgeneral" align="center">
					<input type="text" name="args" value="<?php echo get_corpus_metadata('corpus_cat'); ?>" />
				</td>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Update" />
				</td>
			</tr>
			<input type="hidden" name="locationAfter" value="index.php?thisQ=corpusSettings&uT=y" />
			<input type="hidden" name="function" value="update_corpus_category" />
			<input type="hidden" name="uT" value="y" />
		</form>
		<form action="index.php" method="get">
			<tr>
				<td class="concordgrey" align="center">
					The external URL (for documentation/help links) is:
				</td>
				<td class="concordgeneral" align="center">
					<input type="text" name="settingsUpdateURL" maxlength="200" value="<?php 
						echo get_corpus_metadata('external_url'); 
					?>" />
				</td>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Update" />
				</td>
			</tr>
			<input type="hidden" name="thisQ" value="corpusSettings" />
			<input type="hidden" name="uT" value="y" />
		</form>
		<form action="index.php" method="get">
			<tr>
				<td class="concordgrey" align="center">
					The primary text categorisation scheme is currently:
				</td>
				<td class="concordgeneral" align="center">
					<select name="settingsUpdatePrimaryClassification">
						<?php echo $class_options; ?>
					</select>
				</td>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Update" />
				</td>
			</tr>
			<input type="hidden" name="thisQ" value="corpusSettings" />
			<input type="hidden" name="uT" value="y" />
		</form>			
	</table>
	<?php
}





function printquery_manageaccess()
{
	global $corpus_sql_name;
	
	$access = get_apache_object(realpath('.'));
	$access->load();
	
	$all_groups = $access->list_groups();
	$allowed_groups = $access->get_allowed_groups();
	$disallowed_groups = array();
	foreach($all_groups as &$g)
		if (! in_array($g, $allowed_groups))
			$disallowed_groups[] = $g;

	$options_groups_to_add = '';
	foreach($disallowed_groups as &$dg)
		$options_groups_to_add .= "\n\t\t<option>$dg</option>";
		
	$options_groups_to_remove = '';
	foreach($allowed_groups as &$ag)
	{
		if ($ag == 'superusers')
			continue;
		$options_groups_to_remove .= "\n\t\t<option>$ag</option>";
	}
		
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="2">Corpus access control panel</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center" colspan="2">
				&nbsp;<br/>
				The following user groups have access to this corpus:
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" width="50%">Group</th>
			<th class="concordtable">Members</th>
		</tr>
		
		<?php
		foreach ($allowed_groups as &$group)
		{
			echo "\n<tr>\n<td class=\"concordgeneral\" align=\"center\"><strong>$group</strong></td>\n";
			$member_list = $access->list_users_in_group($group);
			sort($member_list);
			echo "\n<td class=\"concordgeneral\">";
			$i = 0;
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
			echo '</td>';
		}
		?>
		
		<tr>
			<th class="concordtable">Add group</th>
			<th class="concordtable">Remove group</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form action="../adm/index.php" method="get">
					<br/>
					<select name="groupToAdd">
						<?php echo $options_groups_to_add ?>
					</select>
					&nbsp;
					<input type="submit" value="Go!" />
					<br/>
					<input type="hidden" name="corpus" value="<?php echo $corpus_sql_name ?>"/>
					<input type="hidden" name="admFunction" value="accessAddGroup"/>
					<input type="hidden" name="uT" value="y"/>
				</form>
			</td>
			<td class="concordgeneral" align="center">
				<form action="../adm/index.php" method="get">
					<br/>
					<select name="groupToRemove">
						<?php echo $options_groups_to_remove ?>
					</select>
					&nbsp;
					<input type="submit" value="Go!" />
					<br/>
					<input type="hidden" name="corpus" value="<?php echo $corpus_sql_name ?>"/>
					<input type="hidden" name="admFunction" value="accessRemoveGroup"/>
					<input type="hidden" name="uT" value="y"/>
				</form>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" align="center" colspan="2">
				&nbsp;<br/>
				You can manage group membership via the 
				<a href="../adm/index.php?thisF=groupAdmin&uT=y">Sysadmin Control Panel</a>.
				<br/>&nbsp;
			</th>
		</tr>
	</table>
	
	<?php
}



// note, this page is  a bit of a mishmash -- contains all sorts of tools, not all relating very much to metadata.
// most are actually "setup"
// could do with spreading out between headings
function printquery_managemeta()
{	
	global $cqpweb_uploaddir;
	global $corpus_sql_name;
	
	?>
	<table class="concordtable" width="100%">
	
		<tr>
			<th class="concordtable">Admin tools for managing corpus metadata</th>
		</tr>
	
	<?php	
	
	if (!text_metadata_table_exists())
	{
		/* we need to create a text metadata table for this corpus */
		$number_of_fields_in_form 
			= ( isset($_GET['metadataFormFieldCount']) ? (int)$_GET['metadataFormFieldCount'] : 8);
		?>
		
			<tr>
				<td class="concordgrey">
					The text metadata table for this corpus has not yet been set up. You must create it,
					using the controls below, before you can search this corpus.
				</td>
			</tr>
		</table>
		
		<!-- i want a form with more slots! -->

		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable" colspan="3">I need more fields!</th>
			</tr>
			<form action="index.php" method="get">
				<tr>
					<td class="concordgeneral">
						Do you need more metadata fields? Use this control:
					</td>
					<td class="concordgeneral">						
						I want a metadata form with 
						<select name="metadataFormFieldCount">
							<option>9</option>
							<option>10</option>
							<option>11</option>
							<option>12</option>
							<option>13</option>
							<option>14</option>
							<option>15</option>
							<option>16</option>
						</select>
						slots!
					</td>
					<td class="concordgeneral">
						<input type="submit" value="Create bigger form!" />
					</td>
				</td>
				<input type="hidden" name="thisQ" value="manageMetadata" />
				<input type="hidden" name="uT" value="y" />
			</form>
		</table>
		
		
		<form action="../adm/index.php" method="get">
		
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable" colspan="5">Choose the file containing the metadata</th>
			</tr>

			<tr>
				<th class="concordtable">Use?</th>
				<th colspan="2" class="concordtable">Filename</th>
				<th class="concordtable">Size (K)</th>
				<th class="concordtable">Date modified</th>
			</tr>
			<?php
			$file_list = scandir("/$cqpweb_uploaddir/");
	
			foreach ($file_list as &$f)
			{
				$file = "/$cqpweb_uploaddir/$f";
				
				if (!is_file($file)) continue;
				
				if (substr($f,-3) === '.gz') continue;
	
				$stat = stat($file);
				?>
				
				<tr>
					<td class="concordgeneral" align="center">
						<?php 
						echo '<input type="radio" name="dataFile" value="' . urlencode($f) . '" />'; 
						?>
					</td>
					
					<td class="concordgeneral" colspan="2" align="left"><?php echo $f; ?></td>
					
					<td class="concordgeneral" align="right";>
						<?php echo make_thousands(round($stat['size']/1024, 0)); ?>
					</td>
				
					<td class="concordgeneral" align="center">
						<?php echo date('Y-M-d H:i:s', $stat['mtime']); ?>
					</td>		
				</tr>
				<?php
			}
			?>

	
				
			<tr>
				<th class="concordtable" colspan="5">Describe the contents of the file you have selected</th>
			</tr>
			
			<tr>
				<td class="concordgrey" colspan="5">
					Note: you should not specify text_id, which must be the first field. 
					This is inserted automatically.
					<br/>&nbsp;<br/>
					<em>Classification</em> fields contain one of a set number of handles indicating text 
					categories. <em>Free-text metadata</em> fields can contain anything, and don't indicate
					categories of texts.
				</td>
			</tr>
			
			<tr>
				<th class="concordtable">&nbsp;</th>
				<th class="concordtable">Handle for this field</th>
				<th class="concordtable">Description for this field</th>
				<th class="concordtable">Does the field classify texts or provide free-text info?</th>
				<th class="concordtable">Which field is the primary classification?</th>
			</tr>
				
			<?php		
			for ( $i = 1 ; $i <= $number_of_fields_in_form ; $i++ )
				echo "<tr>
					<td class=\"concordgeneral\">Field $i</td>
					<td class=\"concordgeneral\">
						<input type=\"text\" name=\"fieldHandle$i\" maxlength=\"12\"/>
					</td>
					<td class=\"concordgeneral\">
						<input type=\"text\" name=\"fieldDescription$i\" maxlength=\"200\"/>
					</td>
					<td class=\"concordgeneral\">
						<select name=\"isClassificationField$i\">
							<option value=\"1\" selected=\"selected\">Classification</option>
							<option value=\"0\">Free text</option>
						</select>
					</td>
					<td class=\"concordgeneral\" align=\"center\">
						<input type=\"radio\" name=\"primaryClassification\" value=\"$i\"/>
					</td>
				</tr>
				";
			?>
			

			
			<tr>
				<td align="center" class="concordgeneral" colspan="5">
					<input type="submit" value="Install metadata table using the settings above" />
				</td>
			</tr>
			
		</table>
		
			<input type="hidden" name="admFunction" value="createMetadataTable" />
			<input type="hidden" name="fieldCount" value="<?php echo $number_of_fields_in_form; ?>" />
			<input type="hidden" name="corpus" value="<?php echo $corpus_sql_name; ?>" />
			<input type="hidden" name="uT" value="y" />
		</form>
		
		<?php
	}
	else
	{
		/* table exists, so allow other actions */
		
		global $corpus_title;
		?>
		</table>
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">Add item of corpus metadata</th>
			</tr>
			<tr>
				<td class="concordgrey" align="center">Attribute</td>
				<td class="concordgrey" align="center">Value</td>
			</tr>
			<form action="../adm/index.php" method="get">
				<tr>
					<td class="concordgeneral" align="center">
						<input type="text" maxlength="200" name="variableMetadataAttribute" />
					</td>
					<td class="concordgeneral" align="center">
						<input type="text" maxlength="200" name="variableMetadataValue" />
					</td>
					<input type="hidden" name="admFunction" value="variableMetadata" />
					<input type="hidden" name="corpus" value="<?php echo $corpus_sql_name; ?>" />
				</tr>
				<tr>
					<td class="concordgeneral" align="center" colspan="2" />
						<input type="submit" value="Add this item to corpus metadata" />
					</td>
				</tr>
				<input type="hidden" name="uT" value="y" />
			</form>
		</table>

		<table class="concordtable" width="100%">
			<tr>
				<th colspan="2" class="concordtable">Reset the metadata table for this corpus</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey" align="center">
					Are you sure you want to do this?
				</td>
			</tr>
			<form action="../adm/index.php" method="get">
				<tr>
					<td class="concordgeneral" align="center">
						<input type="checkbox" name="clearMetadataAreYouReallySure" value="yesYesYes"/>
						Yes, I'm really sure and I know I can't undo it.
					</td>
					<td class="concordgeneral" align="center">
						<input type="submit" value="Delete metadata table for this corpus" />
					</td>
					<input type="hidden" name="admFunction" value="clearMetadataTable" />
					<input type="hidden" name="corpus" value="<?php echo $corpus_sql_name; ?>" />
					<input type="hidden" name="uT" value="y" />
				</tr>
			</form>
		</table>

		<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">Insert or update text category descriptions</th>
		</tr>
		<?php

		$classification_list = metadata_list_classifications();

		foreach ($classification_list as $scheme)
		{
			?>
			<tr>
				<td class="concordgrey" align="center">
					Categories in classifications scheme <em><?php echo $scheme['handle'];?><em>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<form action="../adm/index.php" method="get">
						<table>
							<tr>
								<td class="basicbox" align="center"><strong>Scheme = Category</strong></td>
								<td class="basicbox" align="center"><strong>Category description</strong></td>
							</tr>
							<?php
							
							$category_list = metadata_category_listdescs($scheme['handle']);
				
							foreach ($category_list as $handle => $description)
							{
								echo '<tr><td class="basicbox">' . "{$scheme['handle']} = $handle" . '</td>';
								echo '<td class="basicbox">
									<input type="text" name="' . "desc-{$scheme['handle']}-$handle"
									. '" value="' . $description . '"/>
									</td>
								</tr>';
							}
							
							?>
							<tr>
								<td class="basicbox" align="center" colspan="2">
									<input type="submit" value="Update category descriptions" />
								</td>
							</tr>
						</table>
						<input type="hidden" name="corpus" value="<?php echo $corpus_sql_name; ?>" />
						<input type="hidden" name="admFunction" value="updateCategoryDescriptions" />
						<input type="hidden" name="uT" value="y" />
					</form>
				</td>
			</tr>
			<?php
		}
	
		?>
		</table>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Other metadata controls</th>
			</tr>
		
			<?php	
			//TODO show the current status of each button
			//TODO some of the buttons may be better off elsewhere
			// eg..... public, freq tables, CWB freq table.
			// the other two are probably OK here.
			if ( get_corpus_metadata('public_freqlist_desc' ) != NULL) /* corpus is public on the system */
			/* nb NULL in mySQL comes back as NULL */
			{
				?>
				<tr>
					<td class="concordgeneral">
						&nbsp;<br/>
						<center>
							This corpus's frequency list is publicly available across the system (for
							keywords, etc). Use the button below to undo this!
						</center>
						&nbsp;<br/>
						<form action="execute.php" method="get">
							<center>
								<input type="submit" value="Make this corpus's frequency list private again!"/>
							</center>
							<br/>
							<input type="hidden" name="function" value="unpublicise_this_corpus_freqtable" />
							<input type="hidden" name="locationAfter" value="index.php?thisQ=manageMetadata&uT=y" />
							<input type="hidden" name="uT" value="y" />
						</form>
					</td>
				</tr>
				<?php
			}
			else /* corpus is not public on the system */
			{
				?>
				<tr>
					<td class="concordgeneral">
						&nbsp;<br/>
						<center>
							Use this control to make the frequency list for this corpus public on the
							system, so that anyone can use it for calculation of keywords, etc.
						</center>
						<form action="execute.php" method="get">
							<center>
								The frequency list will be identified by this descriptor 
								(you may wish to modify):
								<br/>
								<input type="text" name="args" value="<?php 
									echo $corpus_title;
									?>" size="40" maxlength="100" />
								<br/>
								&nbsp;
								<br/>
								<input type="submit" value="Make this frequency table public"/>
							</center>
							&nbsp;<br/>
							<input type="hidden" name="function" value="publicise_this_corpus_freqtable" />
							<input type="hidden" name="locationAfter" value="index.php?thisQ=manageMetadata&uT=y" />
							<input type="hidden" name="uT" value="y" />
						</form>
					</td>
				</tr>
				<?php
			}
			?>
			
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					<center>
						Use the button below to create or recreate the word and annotation frequency 
						tables for this corpus.
					</center>
					&nbsp;<br/>
					<form action="execute.php" method="get">
						<center>
							<input type="submit" value="Create frequency tables"/>
						</center>
						<br/>
						<input type="hidden" name="function" value="corpus_make_freqtables" />
						<input type="hidden" name="uT" value="y" />
					</form>
				</td>
			</tr>
			
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					<center>
						Use the button below to create CWB text-by-text frequency lists for this corpus. 
						(These are used to generate subcorpus frequency lists.)
					</center>
					&nbsp;<br/>
					<form action="execute.php" method="get">
						<center>
							<input type="submit" value="Create CWB frequency table"/>
						</center>
						<br/>
						<input type="hidden" name="function" value="make_cwb_freq_index" />
						<input type="hidden" name="uT" value="y" />
					</form>
				</td>
			</tr>
	
	
	
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					<center>
						Use the button below to populate/update the word count tables for the different 
						text classification categories in this corpus.
					</center>
					&nbsp;<br/>
					<form action="execute.php" method="get">
						<center>
							<input type="submit" value="Update word and file counts"/>
						</center>
						<br/>
						<input type="hidden" name="function" value="metadata_calculate_category_sizes" />
						<input type="hidden" name="uT" value="y" />
					</form>
				</td>
			</tr>
			
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					<center>
						Use the button below to populate/update the text metadata table with begin/end
						offset positions in the CQP-indexed corpus.
					</center>
					&nbsp;<br/>
					<form action="execute.php" method="get">
						<center>
							<input type="submit" value="Update CQP text corpus position records"/>
						</center>
						<br/>
						<input type="hidden" name="function" value="populate_corpus_cqp_positions" />
						<input type="hidden" name="uT" value="y" />
					</form>
				</td>
			</tr>
			
		<?php
		/* create table statement for the textmetadata table for this corpus * /
			<tr>
				<th class="concordtable">
					Below is a CREATE TABLE statement for this corpus' metadata table.
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					&nbsp;<br/>
					<pre>
		
		global $corpus_title;
		global $corpus_sql_name;
		global $mysql_link;
	
		$table_title =  "text_metadata_for_" . $corpus_sql_name;
	
		$sql_query = "select handle from text_metadata_fields where corpus = '$corpus_sql_name'";
		
		$result = mysql_query($sql_query, $mysql_link);
		
		if ($result == false)
		{
			echo "Couldn't run mysql!";
			die;
		}
		
		//* note, size of text_id is 50 to allow possibility of non-decoded UTF8 - they should be shorter 
		//* note also, varchar(20) seems ungenerous - fix this? 
		echo "
		CREATE TABLE `$table_title` (
		  `text_id` varchar(50) NOT NULL,
		  `words` INTEGER NOT NULL,
		  `cqp_begin` BIGINT UNSIGNED NOT NULL default '0',
		  `cqp_end` BIGINT UNSIGNED NOT NULL default '0',
		  key (text_id)";
		while (($row = mysql_fetch_row($result)) != false)
		{
			echo ",
		  `" . $row[0] . '` varchar(20) default NULL';
		}
		echo "
		) CHARSET=utf8 ;\n\n";
					</pre>
				</td>
			</tr>
		*/
		?>
	
	
		</table>
		<?php
	}
}





function printquery_manageannotation()
{
	global $corpus_sql_name;
	global $mysql_link;
	
	if ($_GET['updateMe'] === 'CEQL')
	{
		/* we have incoming values from the CEQL table to update */
		$new_primary = mysql_real_escape_string($_GET['setPrimaryAnnotation']);
		$new_primary = ($new_primary == '__UNSET__' ? 'NULL' : "'$new_primary'");
		$new_secondary = mysql_real_escape_string($_GET['setSecondaryAnnotation']);
		$new_secondary = ($new_secondary == '__UNSET__' ? 'NULL' : "'$new_secondary'");
		$new_tertiary = mysql_real_escape_string($_GET['setTertiaryAnnotation']);
		$new_tertiary = ($new_tertiary == '__UNSET__' ? 'NULL' : "'$new_tertiary'");
		$new_combo = mysql_real_escape_string($_GET['setComboAnnotation']);
		$new_combo = ($new_combo == '__UNSET__' ? 'NULL' : "'$new_combo'");
		$new_maptable = mysql_real_escape_string($_GET['setMaptable']);
		$new_maptable = ($new_maptable == '__UNSET__' ? 'NULL' : "'$new_maptable'");
		
		$sql_query = "update corpus_metadata_fixed set
			primary_annotation = $new_primary,
			secondary_annotation = $new_secondary,
			tertiary_annotation = $new_tertiary,
			combo_annotation = $new_combo,
			tertiary_annotation_tablehandle = $new_maptable
			where corpus = '$corpus_sql_name'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
	}
	else if ($_GET['updateMe'] === 'annotation_metadata')
	{
		/* we have incoming annotation metadata to update */
		if (! check_is_real_corpus_annotation($handle_to_change=mysql_real_escape_string($_GET['annotationHandle'])))
			exiterror_general("Couldn't update $handle_to_change - not a real annotation!",
				__FILE__, __LINE__);
		$new_desc = ( empty($_GET['annotationDescription']) ? 'NULL'
						: '\''.mysql_real_escape_string($_GET['annotationDescription']).'\'');
		$new_tagset = ( empty($_GET['annotationTagset']) ? 'NULL' 
						: '\''.mysql_real_escape_string($_GET['annotationTagset']).'\'');
		$new_url = ( empty($_GET['annotationURL']) ? 'NULL'
						: '\''.mysql_real_escape_string($_GET['annotationURL']).'\'');

		$sql_query = "update annotation_metadata set
			description = $new_desc,
			tagset = $new_tagset,
			external_url = $new_url
			where corpus = '$corpus_sql_name' and handle = '$handle_to_change'";
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
	}


	$sql_query = "select * from corpus_metadata_fixed where corpus='$corpus_sql_name'";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	$data = mysql_fetch_object($result);
	
	$annotation_list = get_corpus_annotations();

	/* set variables */
	
	$select_for_primary = '<select name="setPrimaryAnnotation">';
	$selector = ($data->primary_annotation === NULL ? 'selected="selected"' : '');
	$select_for_primary .= '<option value="__UNSET__"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($data->primary_annotation === $handle ? 'selected="selected"' : '');
		$select_for_primary .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_primary .= "\n</select>";

	$select_for_secondary = '<select name="setSecondaryAnnotation">';
	$selector = ($data->secondary_annotation === NULL ? 'selected="selected"' : '');
	$select_for_secondary .= '<option value="__UNSET__"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($data->secondary_annotation === $handle ? 'selected="selected"' : '');
		$select_for_secondary .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_secondary .= "\n</select>";

	$select_for_tertiary = '<select name="setTertiaryAnnotation">';
	$selector = ($data->tertiary_annotation === NULL ? 'selected="selected"' : '');
	$select_for_tertiary .= '<option value="__UNSET__"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($data->tertiary_annotation === $handle ? 'selected="selected"' : '');
		$select_for_tertiary .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_tertiary .= "\n</select>";

	$select_for_combo = '<select name="setComboAnnotation">';
	$selector = ($data->combo_annotation === NULL ? 'selected="selected"' : '');
	$select_for_combo .= '<option value="__UNSET__"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($data->combo_annotation === $handle ? 'selected="selected"' : '');
		$select_for_combo .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_combo .= "\n</select>";


	/* and the mapping table */
	
	$mapping_table_list = get_list_of_tertiary_mapping_tables();
	$select_for_maptable = '<select name="setMaptable">';
	$selector = ($data->tertiary_annotation_tablehandle === NULL ? 'selected="selected"' : '');
	$select_for_maptable .= '<option value="__UNSET__"' . $selector . '>Not in use in this corpus</option>';
	foreach ($mapping_table_list as $handle=>$desc)
	{
		$selector = ($data->tertiary_annotation_tablehandle === $handle ? 'selected="selected"' : '');
		$select_for_maptable .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_maptable .= "\n</select>";


	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Manage annotation
			</th>
		</tr>
	</table>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				Annotation setup for CEQL queries for <?php echo $corpus_sql_name;?>
			</th>
		</tr>
		<form action="index.php" method="get">
			<tr>
				<td class="concordgrey">
					<b>Primary annotation</b>
					- used for tags given after the underscore character (typically POS)
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_primary;?>
				</td>
			<tr>
				<td class="concordgrey">
					<b>Secondary annotation</b>
					- used for searches like <em>{...}</em> (typically lemma)	
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_secondary;?>
				</td>
			<tr>
				<td class="concordgrey">
					<b>Tertiary annotation</b>
					- used for searches like <em>_{...}</em> (typically simplified POS tag)	
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_tertiary;?>
				</td>
			<tr>
				<td class="concordgrey">
					<b>Tertiary annotation mapping table</b>
					- handle for the list of aliases used in the tertiary annotation
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_maptable;?>
				</td>
			<tr>
				<td class="concordgrey">
					<b>Combination annotation</b>
					- typically lemma_simpletag, used for searches in the form <em>{.../...}</em>
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_combo;?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgeneral" align="center">
					&nbsp;<br/>
					<input type="submit" value="Update annotation settings"/>
					<br/>&nbsp;
				</td>
			<input type="hidden" name="updateMe" value="CEQL"/>
			<input type="hidden" name="thisQ" value="manageAnnotation"/>
			<input type="hidden" name="uT" value="y"/>
		</form>
	</table>

	<table class="concordtable" width="100%">
		<tr>
			<th colspan="5" class="concordtable">
				Annotation metadata
			</th>
		</tr>
		<tr>
			<th class="concordtable">Handle</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Tagset name</th>
			<th class="concordtable">External URL</th>
			<th class="concordtable">Update?</th>
		</tr>
		
		<?php
		
		$sql_query = "select * from annotation_metadata where corpus='$corpus_sql_name'"; 
		$result = mysql_query($sql_query, $mysql_link);
		if ($result == false) 
			exiterror_mysqlquery(mysql_errno($mysql_link), 
				mysql_error($mysql_link), __FILE__, __LINE__);
		if (mysql_num_rows($result) < 1)
			echo '<tr><td colspan="5" class="concordgrey" align="center">&nbsp;<br/>
				This corpus has no annotation.<br/>&nbsp;</td></tr>';
		
		while( ($tag = mysql_fetch_object($result)) !== false)
		{
			echo '<form action="index.php" method= "get"><tr>';
			
			echo '<td class="concordgrey"><strong>' . $tag->handle . '</strong></td>'; 
			echo '<td class="concordgeneral" align="center">
				<input name="annotationDescription" maxlength="230" type="text" value="'
				. $tag->description	. '"/></td>
				'; 
			echo '<td class="concordgeneral" align="center">
				<input name="annotationTagset" maxlength="230" type="text" value="'
				. $tag->tagset	. '"/></td>
				'; 
			echo '<td class="concordgeneral" align="center">
				<input name="annotationURL" maxlength="230" type="text" value="'
				. $tag->external_url	. '"/></td>
				';
			?>
					<td class="concordgeneral" align="center">
						<input type="submit" value="Go!" />			
					</td>
				</tr>
				<input type="hidden" name="annotationHandle" value="<?php echo $tag->handle; ?>" />
				<input type="hidden" name="updateMe" value="annotation_metadata" />
				<input type="hidden" name="thisQ" value="manageAnnotation" />
				<input type="hidden" name="uT" value="y" />
			</form>
			
			<?php
		}
	
		?>
		<tr>
			<td colspan="5" class="concordgeneral">&nbsp;<br/>&nbsp;</td>
		</tr> 
	</table>
	
	
	<?php

}








function printquery_showcache()
{
	global $corpus_sql_name;
	global $default_history_per_page;
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				Showing CQPweb cache for corpus <?php echo $corpus_sql_name;?>
			</th>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">
				<i>Admin controls over query cache and query-history log</i>
			</th>
		</tr>
		<tr>
	<?php
	
	$return_to_url = urlencode('index.php?' . url_printget());

	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=delete_cached_queries&locationAfter='
		. $return_to_url
		. '&uT=y">Delete cache overflow</a></th>';

	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=delete_old_query_history&locationAfter='
		. $return_to_url
		. '&uT=y">Discard old query history</a></th>';

// onmouseover="return escape(\'Insert query string into query window\')">'
	echo '</tr> <tr>';
			
	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=clear_cache&locationAfter='
		. $return_to_url
		. '&uT=y">Clear entire cache<br/>(but keep saved queries)</a></th>';
		
	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=clear_cache&args=0&locationAfter='
		. $return_to_url
		. '&uT=y">Clear entire cache<br/>(clear all saved queries)</a></th>';
		
	echo '</td></tr></table>';


	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;

	print_cache_table($begin_at, $per_page, '__ALL', true, true);
}



function printquery_showfreqtables()
{
	global $mysql_link;
	global $corpus_sql_name;
	global $default_history_per_page;
	global $mysql_freqtables_size_limit;
	
	$sql_query = "select sum(ft_size) from saved_freqtables";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	list($size) = mysql_fetch_row($result);
	if (!isset($size))
		$size = 0;
	$percent = round(((float)$size / (float)$mysql_freqtables_size_limit) * 100.0, 2);
	
	unset($result);
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				Showing frequency table cache for corpus <em><?php echo $corpus_sql_name;?></em>
			</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgeneral">
				&nbsp;<br/>
				The currently saved frequency tables for all corpora have a total size of 
				<?php echo make_thousands($size) . " bytes, $percent%"; ?>
				of the maximum cache.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">
				<i>Admin controls over cached frequency tables</i>
			</th>
		</tr>
		<tr>
	<?php
	
	$return_to_url = urlencode('index.php?' . url_printget());

	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=delete_saved_freqtables&locationAfter='
		. $return_to_url
		. '&uT=y">Delete frequency table cache overflow</a></th>';


	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=clear_freqtables&locationAfter='
		. $return_to_url
		. '&uT=y">Clear entire frequency table cache</a></th>';
	
	
	
	?>
		</tr>
	</table>
	
	
	
	
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">No.</th>
			<th class="concordtable">FT name</th>
			<th class="concordtable">User</th>
			<th class="concordtable">Size</th>
			<th class="concordtable">Restrictions</th>
			<th class="concordtable">Subcorpus</th>
			<th class="concordtable">Created</th>
			<th class="concordtable">Public?</th>
			<th class="concordtable">Delete</th>
		</tr>


	<?php
	
	$sql_query = "SELECT freqtable_name, user, ft_size, restrictions, subcorpus, create_time,
		public
		FROM saved_freqtables WHERE corpus = '$corpus_sql_name' order by create_time desc";
		
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysql_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	
	$name_trim_factor = strlen($corpus_sql_name) + 9;

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		$row = mysql_fetch_assoc($result);
		if (!$row)
			break;
		if ($i < $begin_at)
			continue;
		
		echo "<tr>\n<td class='concordgeneral'><center>$i</center></td>";
		echo "<td class='concordgeneral'><center>" . substr($row['freqtable_name'], $name_trim_factor) . '</center></td>';
		echo "<td class='concordgeneral'><center>" . $row['user'] . '</center></td>';
		echo "<td class='concordgeneral'><center>" . $row['ft_size'] . '</center></td>';
		echo "<td class='concordgeneral'><center>" 
			. ($row['restrictions'] != 'no_restriction' ? $row['restrictions'] : '-')
			. '</center></td>';
		echo "<td class='concordgeneral'><center>" 
			. ($row['subcorpus'] != 'no_subcorpus' ? $row['subcorpus'] : '-')
			. '</center></td>';
		echo "<td class='concordgeneral'><center>" . date('Y-m-d H:i:s', $row['create_time']) 
			. '</center></td>';
		
		if ( $row['subcorpus'] != 'no_subcorpus' )
		{
			if ((bool)$row['public'])
			{
				echo '<td class="concordgeneral"><center><a class="menuItem" 
					onmouseover="return escape(\'This frequency list is public on the system!\')">Yes</a>
					<a class="menuItem" href="execute.php?function=unpublicise_freqtable&args='
					. $row['freqtable_name'] . "&locationAfter=$return_to_url&uT=y"
					. '" onmouseover="return escape(\'Make this frequency list unpublic\')">[&ndash;]</a>
					</center></td>';
			}
			else
			{
				echo '<td class="concordgeneral"><center><a class="menuItem" 
					onmouseover="return escape(\'This frequency list is not publicly accessible\')">No</a>	
					<a class="menuItem" href="execute.php?function=publicise_freqtable&args='
					. $row['freqtable_name'] . "&locationAfter=$return_to_url&uT=y"
					. '" onmouseover="return escape(\'Make this frequency list public\')">[+]</a>
					</center></td>';
			}
		}
		else
			/* only freqtables from subcorpora can be made public, not freqtables from restrictions*/
			echo '<td class="concordgeneral"><center>N/A</center></td>';

		echo '<td class="concordgeneral"><center><a class="menuItem" href="execute.php?function=delete_freqtable&args='
			. $row['freqtable_name'] . "&locationAfter=$return_to_url&uT=y"
			. '" onmouseover="return escape(\'Delete this frequency table\')">[x]</a></center></td>';
	}
	$navlinks = '<table class="concordtable" width="100%"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Newer frequency tables]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysql_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older frequency tables] &gt;&gt;';
	if (mysql_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;



}




function printquery_showdbs()
{

	global $mysql_link;
	global $corpus_sql_name;
	global $default_history_per_page;
	global $mysql_db_size_limit;
	
	$sql_query = "select sum(db_size) from saved_dbs";
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	
	list($size) = mysql_fetch_row($result);
	if (!isset($size))
		$size = 0;
	$percent = round(((float)$size / (float)$mysql_db_size_limit) * 100.0, 2);
	
	unset($result);
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">
				Showing database cache for corpus <em><?php echo $corpus_sql_name;?></em>
			</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgeneral">
				&nbsp;<br/>
				The currently saved databases for all corpora have a total size of 
				<?php echo make_thousands($size) . " bytes, $percent%"; ?>
				of the maximum cache.
				<br/>&nbsp;
			</td>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">
				<i>Admin controls over cached databases</i>
			</th>
		</tr>
		<tr>
	<?php
	
	$return_to_url = urlencode('index.php?' . url_printget());

	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=delete_saved_dbs&locationAfter='
		. $return_to_url
		. '&uT=y">Delete DB cache overflow</a></th>';


	echo '<th width="50%" class="concordtable">'
		. '<a onmouseover="return escape(\'This function affects <b>all</b> corpora in the CQPweb database\')"'
		. 'href="execute.php?function=clear_dbs&locationAfter='
		. $return_to_url
		. '&uT=y">Clear entire DB cache</a></th>';
	
	
	
	?>
		</tr>
	</table>
	
	
	
	
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">No.</th>
			<th class="concordtable">User</th>
			<th class="concordtable">DB name</th>
			<th class="concordtable">DB type</th>
			<th class="concordtable">DB size</th>
			<th class="concordtable">Matching query...</th>
			<th class="concordtable">Restrictions</th>
			<th class="concordtable">Subcorpus</th>
			<th class="concordtable">Created</th>
			<th class="concordtable">Delete</th>	
		</tr>


	<?php
	
	$sql_query = "SELECT user, dbname, db_type, db_size, cqp_query, restrictions, subcorpus, create_time 
		FROM saved_dbs WHERE corpus = '$corpus_sql_name' order by create_time desc";
		
	$result = mysql_query($sql_query, $mysql_link);
	if ($result == false) 
		exiterror_mysqlquery(mysql_errno($mysql_link), 
			mysql_error($mysql_link), __FILE__, __LINE__);
	

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysql_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		$row = mysql_fetch_assoc($result);
		if (!$row)
			break;
		if ($i < $begin_at)
			continue;
		
		echo "<tr>\n<td class='concordgeneral'><center>$i</center></td>";
		echo "<td class='concordgeneral'><center>" . $row['user'] . '</center></td>';
		echo "<td class='concordgeneral'><center>" . $row['dbname'] . '</center></td>';
		echo "<td class='concordgeneral'><center>" . $row['db_type'] . '</center></td>';
		echo "<td class='concordgeneral'><center>" . $row['db_size'] . '</center></td>';
		echo "<td class='concordgeneral'><center>" . $row['cqp_query'] . '</center></td>';
		echo "<td class='concordgeneral'><center>" 
			. ($row['restrictions'] != 'no_restriction' ? $row['restrictions'] : '-')
			. '</center></td>';
		echo "<td class='concordgeneral'><center>" 
			. ($row['subcorpus'] != 'no_subcorpus' ? $row['subcorpus'] : '-')
			. '</center></td>';
		echo "<td class='concordgeneral'><center>" . date('Y-m-d H:i:s', $row['create_time']) 
			. '</center></td>';
			
		echo '<td class="concordgeneral"><center><a class="menuItem" href="execute.php?function=delete_db&args='
			. $row['dbname'] . "&locationAfter=$return_to_url&uT=y"
			. '" onmouseover="return escape(\'Delete this table\')">[x]</a></center></td>';
	}
	$navlinks = '<table class="concordtable" width="100%"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Newer databases]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysql_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older databases] &gt;&gt;';
	if (mysql_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;


}




?>