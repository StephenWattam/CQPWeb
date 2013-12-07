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


/* this is fundamentally a redirecting function */
function printquery_subcorpus()
{

	if(!isset($_GET['subcorpusFunction']))
		$function = 'list_subcorpora';
	else
		$function = $_GET['subcorpusFunction'];

	if (!isset($_GET['subcorpusCreateMethod']))
		$create_method = 'metadata';
	else
		$create_method = $_GET['subcorpusCreateMethod'];

	/* a short circuit for returning to the subcorpus list from the "define...." dropdown */
	if ($function == 'define_subcorpus' && $create_method == 'return')
		$function = 'list_subcorpora'; 

	if (!isset($_GET['subcorpusBadName']))
		$badname_entered = false;
	else
	{
		$badname_entered = ($_GET['subcorpusBadName'] == 'y' ? true : false);
		/* so it doesn't get passed to other scripts... */
		unset($_GET['subcorpusBadName']);
	}

		
	switch($function)
	{
	case 'list_subcorpora':
		print_sc_newform(false);
		print_sc_showsubcorpora();
		break;
	
	case 'view_subcorpus':
		print_sc_view_and_edit();
		break;
		
	case 'copy_subcorpus':
		print_sc_copy($badname_entered);
		break;

	case 'add_texts_to_subcorpus':
		print_sc_addtexts();
		break;	
	
	case 'list_of_files':
		print_sc_list_of_files();
		break;
	
	case 'define_subcorpus':
		print_sc_newform(true);	/* this is here to allow them to abort and select a new method */
		
		switch($create_method)
		{
		case 'query':
			print_sc_nameform($badname_entered, 2);
			print_sc_define_query();
			break;
		case 'metadata_scan':
			/* no name form in metadata scan -- the name is specified in the list page */
			print_sc_define_metadata_scan();
			break;		
		case 'manual':
			print_sc_nameform($badname_entered, 1);
			print_sc_define_filenames();
			break;
		case 'invert':
			print_sc_nameform($badname_entered, 4);
			print_sc_define_invert();
			break;
		case 'text_id':
			/* no nameform ! */
			print_sc_define_text_id();
			break;
		/* if an unrecognised method is passed, it is treated as "metadata " */
		default:
		case 'metadata':
			print_sc_nameform($badname_entered, 3);
			print_sc_define_metadata();
			break;
		}
		break;
	
	
	
	//more here
		

	default:
		break;
	}

}




function print_sc_newform($with_return_option)
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">Create and edit subcorpora</th>
		</tr>
		
		<tr>
			<td class="concordgeneral">
				<form action="index.php" method="get">
					<table align="center">
						<tr>
	 						<td class="basicbox">
								<strong>Define new subcorpus via:</strong>
							</td>
							<td class="basicbox">
								<select name="subcorpusCreateMethod">
									<option value="metadata"     >Corpus metadata</option>
									<option value="metadata_scan">Scan text metadata</option>
									<option value="manual"       >Manual entry of filenames</option>
									<option value="invert"       >Invert an existing subcorpus</option>
									<option value="query"        >Texts found in a saved query</option>
									<option value="text_id"      >Create a subcorpus for every text</option>
									<?php if ($with_return_option) echo "<option value=\"return\">Return to list of existing subcorpora</option>\n"; ?>
								</select>
							</td>
							<td class="basicbox">
								<input type="submit" value="Go!" />
							</td>
						</tr>
						<input type="hidden" name="subcorpusFunction" value="define_subcorpus" />
						<input type="hidden" name="thisQ" value="subcorpus" />
						<input type="hidden" name="uT" value="y" />
					</table>
				</form>
			</td>
		</tr>
	</table>
	<?php

}



/* this function STARTS the create form; other functions must finish it */
function print_sc_nameform($badname_entered, $colspan)
{
	if ($colspan == 1 || $colspan == 0)
		$colspan_text = '';
	else
		$colspan_text = " colspan=\"$colspan\"";
		
	?>
	<table class="concordtable" width="100%">
	<form action="subcorpus-admin.php" method="get">
		<tr>
			<th class="concordtable"<?php echo $colspan_text; ?>>Design a new subcorpus</th>
		</tr>
		<?php
		
		if($badname_entered)
		{
			?>
			<tr>
				<td class="concorderror"<?php echo $colspan_text; ?>>
					<center>
						<strong>Warning:</strong>
						The name you entered, &ldquo;<?php echo $_GET['subcorpusNewName'];?>&rdquo;,
						is not allowed as a name for a subcorpus.
					</center>
				</td>
			</tr>
			<?php	
		}
		
		?>
		
		<tr>
			<td class="concordgeneral"<?php echo $colspan_text; ?>>
				<table align="center">
					<tr>
	 					<td class="basicbox">
							<strong>Please enter a name for your new subcorpus.</strong>
							<br/>
							Names for subcorpora can only contain letters, numbers
							and the underscore character (&nbsp;_&nbsp;)!
						</td>
						<td class="basicbox">
							<input type ="text" size="34" maxlength="34" name="subcorpusNewName"
								<?php
								if(isset($_GET['subcorpusNewName']))
									echo ' value="' . $_GET['subcorpusNewName'] . '"';
								?>
							onKeyUp="check_c_word(this)" />
						</td>
					</tr>
				</table>
			</td>	
		</tr>
	<?php
}



/* this function ENDS the create form */
function print_sc_define_metadata()
{
	?>
		<tr>
			<td class="concordgeneral" colspan="3">
				<center>
					&nbsp;
					<br/>
					Choose the categories you want to include from the lists below. 
					<br/>&nbsp;<br/>
					Then either create the subcorpus directly from those categories, or view a list
					of texts to choose from.
					<br/>&nbsp;
					<br/>
					
					<input name="action" type="submit" value="Create subcorpus from selected categories"/>
					<br/>&nbsp;<br/>&nbsp;<br/>
					<input name="action" type="submit" value="Get list of texts"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="reset" value="Clear form"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel"/>
					<br/>&nbsp;<br/>
				</center>
			</td>
		</tr>
		<input type="hidden" name="scriptMode" value="create_from_metadata"/>
		<input type="hidden" name="thisQ" value="subcorpus"/>
	<?php

	echo printquery_build_restriction_block(NULL, 'subcorpus');
	echo '</table>';
}



/* this function ENDS the create form */
function print_sc_define_query()
{
	global $corpus_sql_name;
	global $username;
	
	$sql_query = $sql_query = "select query_name, save_name from saved_queries 
		where corpus = '$corpus_sql_name' and user = '$username' and saved = 1";
	
	$result = do_mysql_query($sql_query);
	
	$no_saved_queries = (mysql_num_rows($result) == 0);
	
	$field_options = '';
	while ( ($r = mysql_fetch_row($result)) !== false)
	{
		$selected = ($r[0] == $_GET['savedQueryToScan'] ? 'selected="selected"' : '');
		$field_options .= "\t<option value=\"{$r[0]}\" $selected>{$r[1]}</option>\n";
	}
	?>
		<tr>
			<td class="concordgeneral" colspan="2">
				<center>
					&nbsp;
					<br/>
					Select a query from your Saved Queries list using the control below.
					<br/>&nbsp;<br/>
					Then either directly create a subcorpus consisting of <strong>all texts that contain at
					least one result for that query</strong>, or view a list of texts to choose from.
					<br/>&nbsp;
					<br/>
				</center>
			</td>
		</tr>
		<tr>
			<?php
			if ($no_saved_queries)
			{
				?>
				<td class="concorderror" colspan="2">
					You do not have any saved queries.
				</td>
				<?php
			}
			else
			{
				?>
				<td class="concordgeneral" width="50%">
					&nbsp;<br/>
					Which Saved Query do you want to use as the basis of the subcorpus?
				<br/>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br/>
					<select name="savedQueryToScan">
						<?php echo $field_options; ?>
					</select>
				<br/>&nbsp;
				</td>
				<?php		
			}
			?>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2">
				<center>
					&nbsp;<br/>
					<input name="action" type="submit" value="Create subcorpus from selected query"/>
					<br/>&nbsp;<br/>&nbsp;<br/>
					<input name="action" type="submit" value="Get list of texts"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="reset" value="Clear form"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel"/>
					<br/>&nbsp;
				</center>
			</td>
		</tr>
		<input type="hidden" name="scriptMode" value="create_from_query"/>
		<input type="hidden" name="thisQ" value="subcorpus"/>
		<input type="hidden" name="uT" value="y"/>
	</table>
	<?php

}




/* this function ENDS the create form */
function print_sc_define_metadata_scan()
{
	$in_fields = metadata_list_fields();

	/* allow sort by description... */
	foreach($in_fields as $if)
		$fields[$if] = metadata_expand_field($if);	
	
	natcasesort($fields);
	
	$field_options = "\n";

	foreach($fields as $f => $l)
		$field_options .= "<option value=\"$f\">$l</option>\n";
	?>
	<table class="concordtable" width="100%">
		<form action="subcorpus-admin.php" method="get">
			<tr>
				<th class="concordtable" colspan="2">Design a new subcorpus</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					Which metadata field do you want to search?
				</td>
				<td class="concordgeneral">
					<select name="metadataFieldToScan">
						<?php echo $field_options ?>
					</select>
				</td>			
			</tr>
			<tr>
				<td class="concordgeneral">
					Search for texts where this metadata field ....
				</td>
				<td class="concordgeneral">
					<select name="metadataScanType">
						<option value="begin">starts with</option>
						<option value="end">ends with</option>
						<option value="contain" selected="selected">contains</option>
						<option value="exact">matches exactly</option>
					</select>
					&nbsp;&nbsp;
					<input type="text" name="metadataScanString" size="32" />
				</td>			
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2">
					<center>
						&nbsp;<br/>
						<input type="submit" value="Get list of texts"/>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="reset" value="Clear form"/>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<input name="action" type="submit" value="Cancel"/>
						<br/>&nbsp;<br/>
					</center>
				</td>
			</tr>
			<input type="hidden" name="scriptMode" value="create_from_metadata_scan"/>
			<input type="hidden" name="uT" value="y"/>
		</form>
	</table>
	<?php
}





/* this function ENDS the create-form */
function print_sc_define_filenames()
{
	if (isset($_GET['subcorpusBadTexts']))
	{
		?>
		<tr>
			<td class="concorderror">
				<center>
					<strong>Warning:</strong>
					The following texts do not exist in the corpus &ldquo;<?php
					echo $_GET['subcorpusBadTexts'];
					?>&rdquo;.
				</center>
				</td>
		</tr>
		<?php
	}
	?>
		<tr>
			<td class="concordgeneral">
				<center>
					&nbsp;
					<br/>
					Enter the filenames you wish to combine to a subcorpus 
					(use commas or spaces to separate the individual files): 
					<br/>&nbsp;
					<br/>
					<textarea name="subcorpusListOfFiles" rows="5" cols="58"><?php
						if (isset($_GET['subcorpusListOfFiles']))
							echo $_GET['subcorpusListOfFiles'];
					?></textarea>
					<br/>&nbsp;<br/>
					
					<input type="submit" value="Create subcorpus"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="reset" value="Clear form"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel"/>
					<br/>&nbsp;<br/>
				</center>
			</td>
		</tr>
		<input type="hidden" name="scriptMode" value="create_from_manual"/>
		<input type="hidden" name="uT" value="y"/>
		<?php /*echo url_printinputs(array(
			array('subcorpusNewName', ''), 
			array('subcorpusListOfFiles', '')	));
			I really don't think this is needed, is it?
		*/?>

	</form>
	
	</table>
	<?php

}


function print_sc_define_invert()
{
	global $username;
	global $corpus_sql_name;
	
	?>
		<tr>
			<td class="concordgeneral" colspan="4">
				<center>
					&nbsp;
					<br/>
					When you "invert" a subcorpus, you create a new subcorpus containing all texts from
					the corpus, <strong>except</strong> those in the subcorpus you selected to invert. 
					<br/>&nbsp;<br/>
					Choose the subcorpus you want to invert from the list below. 
					<br/>&nbsp;<br/>

					<br/>
					
					<input type="submit" value="Create inverted subcorpus"/>
					<br/>&nbsp;<br/>&nbsp;<br/>
					<input type="reset" value="Clear form"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel"/>
					<br/>&nbsp;<br/>
				</center>
			</td>
		</tr>
		<input type="hidden" name="scriptMode" value="create_inverted"/>
		<input type="hidden" name="thisQ" value="subcorpus"/>
		
		<tr>
			<th class="concordtable">Select</th>
			<th class="concordtable">Name of subcorpus</th>
			<th class="concordtable">No. of texts</th>
			<th class="concordtable">No. of words</th>
	<?php


	$sql_query = "select subcorpus_name, numwords, numfiles from saved_subcorpora
		where corpus = '$corpus_sql_name' and user = '$username' order by subcorpus_name";
	$result = do_mysql_query($sql_query);


	while (($row = mysql_fetch_assoc($result)) != false)
	{
		// TODO: alter this so Last restrictions is always at the top (non-urgent)
		echo '<tr>';
		
		echo '<td class="concordgrey"><center><input name="subcorpusToInvert" type="radio" '
			. 'value="' . $row['subcorpus_name'] . '" '
			. ( $_GET['subcorpusToInvert'] == $row['subcorpus_name'] ? 'checked="checked" ' : '') 
			. '/></center></td>';
		
		if ($row['subcorpus_name'] == '__last_restrictions')
			echo '<td class="concordgeneral">Last restrictions</td>';
		else
			echo '<td class="concordgeneral">'
			. $row['subcorpus_name'] . '</td>';
		
		echo '<td class="concordgeneral"><center>' . make_thousands($row['numfiles']) 
			. '</center></td>'
			. '<td class="concordgeneral"><center>' . make_thousands($row['numwords'])
			. '</center></td>';
			
		echo "</tr>\n";
	}
	if (mysql_num_rows($result) == 0)
		echo '<tr><td class="concordgrey" colspan="4" align="center">
				&nbsp;<br/>No subcorpora were found.<br/>&nbsp;
				</td></tr>';


	
	?>
	
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	<?php
}


/**
 * Note that this function DOESN'T require a name form -- names are auto-generated.
 */
function print_sc_define_text_id()
{
	?>
	<table class="concordtable" width="100%">
	<form action="subcorpus-admin.php" method="get">
		<tr>
			<th class="concordtable">Design a new subcorpus</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<center>
					&nbsp;
					<br/>
					Click below to turn every text into a subcorpus. 
					<br/>&nbsp;<br/>
					Note that this function is currently only available for corpora with 100 or less texts. 
					<br/>&nbsp;<br/>

					<br/>
					
					<input type="submit" value="Create one subcorpus per text"/>
					<br/>&nbsp;<br/>&nbsp;<br/>
					<input type="reset" value="Clear form"/>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel"/>
					<br/>&nbsp;<br/>
				</center>
			</td>
		</tr>
		<input type="hidden" name="scriptMode" value="create_text_id"/>
		<input type="hidden" name="thisQ" value="subcorpus"/>
		<input type="hidden" name="uT" value="y" />
	</form>
	</table>
	<?php
}


function print_sc_showsubcorpora()
{
	global $username;
	global $default_history_per_page;	/* the same variable used for query history is used here */
	global $collocation_disallow_cutoff;
	global $corpus_sql_name;

	if (user_is_superuser($username))
	{
		// 	TODO -- "Show subcorpora of all users"	link in title bar
	}
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="7">Existing subcorpora</th>
		</tr>
		<tr>
			<th class="concordtable">Name of subcorpus</th>
			<th class="concordtable">No. of texts</th>
			<th class="concordtable">No. of words</th>
			<th class="concordtable">Frequency list</th>
			<th class="concordtable" colspan="2">Actions</th>
			<th class="concordtable">Delete</th>
		</tr>
		<?php
		


		$sql_query = "select subcorpus_name, numwords, numfiles from saved_subcorpora
			where corpus = '$corpus_sql_name' and user = '$username' order by subcorpus_name";
		$result = do_mysql_query($sql_query);
				
		$subcorpora_with_freqtables = list_freqtabled_subcorpora();

		while (($row = mysql_fetch_assoc($result)) != false)
		{
			// TODO: alter this so Last restrictions is always at the top (non-urgent)
			echo '<tr>';
			if ($row['subcorpus_name'] == '__last_restrictions')
				echo '<td class="concordgeneral">Last restrictions</td>';
			else
				echo '<td class="concordgeneral"><a href="index.php?thisQ=subcorpus&subcorpusFunction=view_subcorpus'
				. '&subcorpusToView=' . $row['subcorpus_name'] .'&uT=y" '
				. 'onmouseover="return escape(\'View (or remove) texts in this subcorpus\')">'
				. $row['subcorpus_name'] 
				. '</a></td>';
			
			echo '<td class="concordgeneral"><center>' . make_thousands($row['numfiles']) 
				. '</center></td>'
				. '<td class="concordgeneral"><center>' . make_thousands($row['numwords'])
				. '</center></td>';
			
			echo '<td class="concordgeneral"><center>';
			if ($row['subcorpus_name'] == '__last_restrictions')
				echo 'N/A';
			else if (in_array($row['subcorpus_name'], $subcorpora_with_freqtables))
				/* freq tables exist for this subcorpus, ergo... */
				echo 'Available';
			else
			{
// TODO we use $collocation_disallow_cutoff but it should really be a more generalised "freq list user build" cutoff
				if ($row['numwords'] >= $collocation_disallow_cutoff)
					echo '<a class="menuItem" " onmouseover="return escape(\'Cannot compile frequency tables for this subcorpus ,'
						, ' as it is too big\')">Cannot compile</a>';
				else
					echo '<a class="menuItem" href="freqtable-compile.php?compileSubcorpus=' 
						, $row['subcorpus_name']
						, '&compileAfter=index_sc&uT=y'
						, '" onmouseover="return escape(\'Compile frequency tables for subcorpus <b>'
						, $row['subcorpus_name']
						, '</b>, allowing calculation of collocations and keywords\')">Compile</a>';
			}
			echo '</center></td>';
			
			echo '<td class="concordgeneral"><center><a class="menuItem" ' 
				, 'href="index.php?thisQ=subcorpus&subcorpusFunction=copy_subcorpus&subcorpusToCopy=' 
				, $row['subcorpus_name'] 
				, '&uT=y" onmouseover="return escape(\'Copy this subcorpus\')">'   
				, '[copy]</a></center></td>';
	
			echo '<td class="concordgeneral"><center><a class="menuItem" ' 
				, 'href="index.php?thisQ=subcorpus&subcorpusFunction=add_texts_to_subcorpus&subcorpusToAddTo=' 
				, $row['subcorpus_name'] 
				, '&uT=y" onmouseover="return escape(\'Add texts to this subcorpus\')">'   
				, '[add]</a></center></td>';

			echo '<td class="concordgeneral"><center>' 
				, '<a class="menuItem" href="subcorpus-admin.php?scriptMode=delete&subcorpusToDelete='
				, $row['subcorpus_name'] . '&uT=y" onmouseover="return escape(\'Delete this subcorpus\')">'
				, '[x]</a></center></td>';
				
			echo "</tr>\n";
		}
		if (mysql_num_rows($result) == 0)
			echo '<tr><td class="concordgrey" colspan="7" align="center">
					&nbsp;<br/>No subcorpora were found.<br/>&nbsp;
					</td</tr>';
		else
		{
			?>
			<!-- in retrospet, this button is a very bad idea. Switched off! both here and in freqtable-compile.
			<tr>
				<td colspan="7" class="concordgrey" align="center">
					&nbsp<br/>
					<form action="freqtable-compile.php" method="get">
						<input type="submit" value="Compile frequency lists for all subcorpora" />
						<input type="hidden" name="compileSubcorpusAll" value="1" />
						<input type="hidden" name="uT" value="y" />
					</form>
				</td>
			</tr>
			-->
			<?php
		}
		
		?>
	</table>
	<?php
}

function print_sc_copy($badname_entered)
{
	if (!isset($_GET['subcorpusToCopy']))
		exiterror_parameter('No subcorpus specified to copy!', __FILE__, __LINE__);	
	else
		$copyme = $_GET['subcorpusToCopy'];
	?>
	<table class="concordtable" width="100%">
	<form action="subcorpus-admin.php" method="get">
		<tr>
			<th class="concordtable">
			<?php
			if ($copyme == '__last_restrictions')
				echo "Copying last restrictions used to saved subcorpus";
			else
				echo "Copying subcorpus <em>$copyme</em>"; 
			?>
			</th>
		</tr>
		
		<?php
		
		if($badname_entered)
		{
			?>
			<tr>
				<td class="concorderror">
					<center>
						<strong>Warning:</strong>
						The name you entered, &ldquo;<?php echo $_GET['subcorpusNewName'];?>&rdquo;,
						is not allowed as a name for a subcorpus.
					</center>
				</td>
			</tr>
			<?php	
		}
		
		?>
		<tr>
			<td class="concordgeneral">
			&nbsp;<br/>
				<table align="center">
					<tr>
	 					<td class="basicbox">
							<strong>What name do you want to give to the copied subcorpus?</strong>
							<br/>
							Names for subcorpora can only contain letters, numbers
							and the underscore character (&nbsp;_&nbsp;)!
						</td>
						<td class="basicbox">
							<input type ="text" size="34" maxlength="34" name="subcorpusNewName"
								<?php
								if(isset($_GET['subcorpusNewName']))
									echo ' value="' . $_GET['subcorpusNewName'] . '"';
								?>
							onKeyUp="check_c_word(this)" />
						</td>
					</tr>
				</table>
				<center>
					&nbsp;<br/>
					<input type="submit" name="action" value="Copy subcorpus"/>
					<input type="submit" name="action" value="Cancel"/>
					&nbsp;&nbsp;&nbsp;&nbsp;
					<br/>&nbsp;
					<br/>&nbsp;
				</center>
			</td>	
		</tr>
		<input type="hidden" name="scriptMode" value="copy"/>
		<?php echo url_printinputs(array( array('subcorpusNewName', '') )); ?>
	</form>
	</table>

	<?php

}


function print_sc_addtexts()
{
	if (!isset($_GET['subcorpusToAddTo']))
		exiterror_parameter('No subcorpus specified to add to!', __FILE__, __LINE__);	
	else
		$subcorpus = mysql_real_escape_string($_GET['subcorpusToAddTo']);
	?>
	<table class="concordtable" width="100%">
		<form action="subcorpus-admin.php" method="get">
			<tr>
				<th class="concordtable">
					Adding texts to subcorpus &ldquo;<?php echo $subcorpus; ?>&rdquo;
				</th>
			</tr>
			<?php
			if (isset($_GET['subcorpusBadTexts']))
			{
				?>
				<tr>
					<td class="concorderror">
						<center>
							<strong>Warning:</strong>
							The following texts do not exist in the corpus &ldquo;<?php
							echo $_GET['subcorpusBadTexts'];
							?>&rdquo;.
						</center>
						</td>
				</tr>
				<?php
			}
			?>
			<tr>
				<td class="concordgeneral">
					<center>
						&nbsp;
						<br/>
						Enter the filenames you wish to add to this subcorpus 
						(use commas or spaces to separate the individual files): 
						<br/>&nbsp;
						<br/>
						<textarea name="subcorpusListOfFiles" rows="5" cols="58"><?php
							if (isset($_GET['subcorpusListOfFiles']))
								echo $_GET['subcorpusListOfFiles'];
						?></textarea>
						<br/>&nbsp;<br/>
						
						<input type="submit" value="Add texts to subcorpus"/>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="reset" value="Clear form"/>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<input name="action" type="submit" value="Cancel"/>
						<br/>&nbsp;<br/>
					</center>
				</td>
			</tr>
			<input type="hidden" name="subcorpusToAddTo" value="<?php echo $subcorpus; ?>"/>
			<input type="hidden" name="scriptMode" value="add_texts"/>
			<input type="hidden" name="uT" value="y"/>
		</form>
	</table>

	<?php

}


function print_sc_view_and_edit()
{
	global $username;
	global $default_history_per_page;
	global $corpus_sql_name;
	
	$subcorpus = mysql_real_escape_string($_GET['subcorpusToView']);
	
	if(empty($subcorpus))
		exiterror_parameter('No subcorpus was specified!', __FILE__, __LINE__);
	
	$size = subcorpus_sizeof($subcorpus);


	if (!isset($_GET['subcorpusFieldToShow']))
		$show_field = get_corpus_metadata('primary_classification_field');
	else
		$show_field = mysql_real_escape_string($_GET['subcorpusFieldToShow']);

	if (empty($show_field))
	{
		$show_field = false;
		$catdescs = false;
		$field_options = "\n<option selected=\"selected\"></option>";
	}
	else
	{
		if (metadata_field_is_classification($show_field))
			$catdescs = metadata_category_listdescs($show_field);
		else
			$catdescs = false;
		$field_options = "\n";
	}


	
	foreach(metadata_list_fields() as $f)
	{
		$l = metadata_expand_field($f);
		$selected = ($f == $show_field ? 'selected="selected"' : '');
		$field_options .= "<option value=\"$f\" $selected>$l</option>\n";
	}
	
	
	$text_list = explode(' ', subcorpus_get_text_list($subcorpus));

	$i = 1;
	

	
	
	// TODO add a control bar and limit the number of texts per page, like sebastian does; (longterm)
	?>
	<script type="text/javascript">
	<!--
	function subcorpusAlterForm()
	{
		document.getElementById('subcorpusTextListMainForm').action = "index.php";
		document.getElementById('inputSubcorpusToDeleteFrom').name = "subcorpusToView";
		document.getElementById('inputScriptMode').name = "subcorpusFunction";
		document.getElementById('inputScriptMode').value = "view_subcorpus";
	}
	//-->
	</script>
	<table class="concordtable" width="100%">
		<form id="subcorpusTextListMainForm" action="subcorpus-admin.php" method="get">
			<tr>
				<th class="concordtable" colspan="5">Create and edit subcorpora</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="5" align="center">
					<strong>
						&nbsp;<br/>
						Viewing subcorpus
						<?php
						echo "<em>$subcorpus</em>: this subcorpus consists of "
							. make_thousands($size['files']) . " texts with a total of "
							. make_thousands($size['words']) . " words.";
						?>
						<br/>
					</strong>
					&nbsp;<br/>
					<input type="submit" value="Delete marked texts from subcorpus" />
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel" /> 
					<br/>&nbsp;
				</td>
			<tr>
				<th class="concordtable">No.</th>		
				<th class="concordtable">Text</th>		
					<th class="concordtable">
						Showing:
						<select name="subcorpusFieldToShow">
							<?php echo $field_options; ?>
						</select>
						<input type="submit" onclick="subcorpusAlterForm()" value="Show" />
					</th>
				<th class="concordtable">Size in words</th>		
				<th class="concordtable">Delete</th>		
			</tr>
	<?php
	
	foreach($text_list as &$text)
	{
		$meta = metadata_of_text($text);
		
		echo '
			<tr>';
		
		/* number */
		echo '<td class="concordgrey" align="right"><strong>' . $i++ . '</strong></td>';
		
		/* text id with metadata link */
		echo '<td class="concordgeneral"><strong>'
			. '<a ' . metadata_tooltip($text) . ' href="textmeta.php?text=' . $text . '&uT=y">'
			. $text
			. '</a></strong></td>';
			
		/* primary classification (or whatever classification has been selected) */
		echo '<td class="concordgeneral">'
			. ($show_field === false 
					? '&nbsp;'
					: ($catdescs !== false ? $catdescs[$meta[$show_field]] : $meta[$show_field])
					)
			. '</td>';
		

		/* number of words in file */
		echo '<td class="concordgeneral" align="center">'
			. make_thousands($meta['words'])
			. '</td>';
			
		/* tickbox for delete */
		echo '<td class="concordgrey" align="center">'
			. '<input type="checkbox" name="dT_' . $text . '" value="1" />'
			. '</td>';

		
		echo '</tr>';
	}
	?>
			<input type="hidden" name="thisQ" value="subcorpus" />
			<input id="inputSubcorpusToDeleteFrom" type="hidden" name="subcorpusToDeleteFrom" 
				value="<?php echo $subcorpus; ?>" />
			<input id="inputScriptMode" type="hidden" name="scriptMode" value="delete_texts" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	<?php

}




function print_sc_list_of_files()
{
	global $username;
	global $corpus_sql_name;

	global $list_of_texts_to_show_in_form;
	global $header_cell_text;
	global $field_to_show;

	$field_to_show_desc = metadata_expand_field($field_to_show);
	
	
	$form_full_list = str_replace(' ', '|', $list_of_texts_to_show_in_form);
	
	$form_full_list_idcode = longvalue_store($form_full_list);
	
	
	$text_list = ( empty($list_of_texts_to_show_in_form) ? NULL : explode(' ', $list_of_texts_to_show_in_form) );
	
	
	
	$sql_query = "select subcorpus_name from saved_subcorpora
		where corpus = '$corpus_sql_name' and user = '$username' order by subcorpus_name";
	$result = do_mysql_query($sql_query);
	$subcorpus_options = "\n";
	while ( ($r= mysql_fetch_row($result)) != false)
		$subcorpus_options .= '<option value="' . $r[0] . '">Add to ' . $r[0]. '</option>';
	$subcorpus_options .= "\n";


	$i = 1;

	?>
	<table class="concordtable" width="100%">
		<form action="subcorpus-admin.php" method="get">
			<tr>
				<th class="concordtable" colspan="5">Create and edit subcorpora</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="5" align="center">
					<strong>
						&nbsp;<br/>
						<?php echo $header_cell_text; ?>
						<br/>&nbsp;<br/>
					</strong>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="5" align="center">
					<table width="100%">
						<tr>
							<td class="basicbox">
								Add files to subcorpus...
							</td>
							<td class="basicbox" align="">
								<select name="subcorpusToAddTo">
									<option value="!__NEW">Use specified name for new subcorpus:</option>
									<?php echo $subcorpus_options; ?>
								</select>
							</td>
							<td class="basicbox">
								&nbsp;<br/>
								New subcorpus: <input type="text" name="subcorpusNewName" />
								<br/>
								(may only contain letters, numbers and underscore)
							</td>
							<td class="basicbox">
								<input type="checkbox" name="processFileListAddAll" 
									value="<?php echo $form_full_list_idcode; ?>" 
								/>
								include all texts
							</td>
							<td class="basicbox">
								<input type="submit" value="Add texts" />
								<br/>&nbsp;<br/>
								<input type="submit" name="action" value="Cancel" />
							</td>
						</tr>
					</table>
				</td>
			<tr>
				<th class="concordtable">No.</th>		
				<th class="concordtable">Text</th>		
				<th class="concordtable"><?php echo $field_to_show_desc;?></th>		
				<th class="concordtable">Size in words</th>		
				<th class="concordtable">Include in subcorpus</th>
			</tr>
	<?php

	if (! empty($text_list))
	{
		foreach($text_list as &$text)
		{
			$meta = metadata_of_text($text);
			
			echo '
				<tr>';
			
			/* number */
			echo '<td class="concordgrey" align="right"><strong>' . $i++ . '</strong></td>';
			
			/* text id with metadata link */
			echo '<td class="concordgeneral"><strong>'
				. '<a ' . metadata_tooltip($text) . ' href="textmeta.php?text=' . $text . '&uT=y">'
				. $text
				. '</a></strong></td>';
				
			/* primary classification */
			echo '<td class="concordgeneral">'
				. $meta[$field_to_show]
				. '</td>';
			
	
			/* number of words in file */
			echo '<td class="concordgeneral" align="center">'
				. make_thousands($meta['words'])
				. '</td>';
				
			/* tickbox for add */
			echo '<td class="concordgrey" align="center">'
				. '<input type="checkbox" name="aT_' . $text . '" value="1" />'
				. '</td>';
	
			echo '</tr>';
		}
	}
	else
	{
		?>
			<tr>
				<td class="concordgrey" colspan="5" align="center">
					&nbsp;<br/>
					No texts found.
					<br/>&nbsp;
				</td>
			</tr>
		<?php	
	}
	?>
			<input type="hidden" name="scriptMode" value="process_from_file_list" />
			<input type="hidden" name="uT" value="y" />
		</form>
	</table>
	<?php
	
}


?>
