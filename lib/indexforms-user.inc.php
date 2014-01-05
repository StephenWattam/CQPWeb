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
 * Each of these functions prints a table for the right-hand side interface.
 * 
 * This file contains the forms deployed by userhome and not queryhome. 
 * 
 */




//function printscreen_????()
//{
//	? >
//	<table class="concordtable" width="100%">
//		<tr>
//			<th class="concordtable">
//				Put the title here!!
//			</th>
//		</tr>
//		<tr>
//			<td class="concordgeneral">
//				<p>You can now:</p>
//				<ul>
//					<li>
//						Design and insert a text-metadata table for the corpus
//					</li>
//					<li>
//						<a href="index.php?thisQ=installCorpus&uT=y">
//							Install another corpus
//						</a>
//					</li>
//				</ul>
//				<p>&nbsp;</p>
//			</td>
//		</tr>
//	</table>
//	<?php
//}


function printscreen_welcome()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Put the title here!!
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>You can now:</p>
				<ul>
					<li>
						Design and insert a text-metadata table for the corpus
					</li>
					<li>
						<a href="index.php?thisQ=installCorpus&uT=y">
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

function printscreen_login()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">
				Log in to CQPweb
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				
				<form action="redirect.php" method="get">
					<table class="basicbox" style="margin:auto">
						<tr>
							<td class="basicbox">Enter your username:</td>
							<td class="basicbox">
								<input type="text" name="username" width="30" onKeyUp="check_c_word(this)" />
							</td>
						</tr>
						<tr>
							<td class="basicbox">Enter your password:</t6d>
							<td class="basicbox">
								<input type="password" name="password" width="100"  />
							</td>
						</tr>
					</table>
				</form>
				
				<ul>
					<li>
						<p>
							If you do not already have an account, you can 
							<a href="index.php?thisQ=create&uT=y">create one</a>.
						</p>
					</li>
					<li>
						<p>
							If you have forgotten your password, you can 
							<a href="index.php?thisQ=lost&uT=y">request a reset</a>.
					</li>
				</ul>
			</td>
		</tr>
	</table>
	<?php
}




function printscreen_usersettings()
{
	global $User;
	
	list ($optionsfrom, $optionsto) = print_fromto_form_options(10, $User->coll_from, $User->coll_to);
	
	?>
<table class="concordtable" width="100%">

	<form action="redirect.php" method="get">
	
		<tr>
			<th colspan="2" class="concordtable">User settings</th>
		</tr>
	
		<tr>
			<td colspan="2" class="concordgrey" align="center">
				Important note: all these options can be set, but they may not have their full 
				intended effect, if the part of CQPweb they apply to is still under development.
			<!--
				Important note: these settigns apply to all the corpora that you access on CQPweb.
			-->
			</td>
		</tr>		

		<tr>
			<th colspan="2" class="concordtable">Display options</th>
		</tr>		

		<tr>
			<td class="concordgeneral">Default view</td>
			<td class="concordgeneral">
				<select name="newSetting_conc_kwicview">
					<option value="1"<?php echo ($User->conc_kwicview == '0' ? ' selected="selected"' : '');?>>KWIC view</option>
					<option value="0"<?php echo ($User->conc_kwicview == '0' ? ' selected="selected"' : '');?>>Sentence view</option>
				</select>
			</td>
		</tr>


		<tr>
			<td class="concordgeneral">Default display order of concordances</td>
			<td class="concordgeneral">
				<select name="newSetting_conc_corpus_order">
					<option value="1"<?php echo ($User->conc_corpus_order == '1' ? ' selected="selected"' : '');?>>Corpus order</option>
					<option value="0"<?php echo ($User->conc_corpus_order == '0' ? ' selected="selected"' : '');?>>Random order</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">
				Show Simple Query translated into CQP syntax (in title bar and query history)
			</td>
			<td class="concordgeneral">
				<select name="newSetting_cqp_syntax">
					<option value="1"<?php echo ($User->cqp_syntax == '1' ? ' selected="selected"' : '');?>>Yes</option>
					<option value="0"<?php echo ($User->cqp_syntax == '0' ? ' selected="selected"' : '');?>>No</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Context display</td>
			<td class="concordgeneral">
				<select name="newSetting_context_with_tags">
					<option value="0"<?php echo ($User->context_with_tags == '0' ? ' selected="selected"' : '');?>>Without tags</option>
					<option value="1"<?php echo ($User->context_with_tags == '1' ? ' selected="selected"' : '');?>>With tags</option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="concordgeneral">
				Show tooltips (JavaScript enabled browsers only)
				<br/>
				<em>(When moving the mouse over some links (e.g. in a concordance), additional 
				information will be displayed in tooltip boxes.)</em>
			</td>
			<td class="concordgeneral">
				<select name="newSetting_use_tooltips">
					<option value="1"<?php echo ($User->use_tooltips == '1' ? ' selected="selected"' : '');?>>Yes</option>
					<option value="0"<?php echo ($User->use_tooltips == '0' ? ' selected="selected"' : '');?>>No</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Default setting for thinning queries</td>
			<td class="concordgeneral">
				<select name="newSetting_thin_default_reproducible">
					<option value="0"<?php echo ($User->thin_default_reproducible == '0' ? ' selected="selected"' : '');?>>Random: selection is not reproducible</option>
					<option value="1"<?php echo ($User->thin_default_reproducible == '1' ? ' selected="selected"' : '');?>>Random: selection is reproducible</option>
				</select>
			</td>
		</tr>

		<tr>
			<th colspan="2" class="concordtable">Collocation options</th>
		</tr>		

		<tr>
			<td class="concordgeneral">Default statistic to use when calculating collocations</td>
			<td class="concordgeneral">
				<select name="newSetting_coll_statistic">
					<?php echo print_statistic_form_options($User->coll_statistic); ?>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">
				Default minimum for freq(node, collocate) [<em>frequency of co-occurrence</em>]
			</td>
			<td class="concordgeneral">
				<select name="newSetting_coll_freqtogether">
					<?php echo print_freqtogether_form_options($User->coll_freqtogether); ?>
				</select>
			</td>
		</tr>

		<tr>                               
			<td class="concordgeneral">
				Default minimum for freq(collocate) [<em>overall frequency of collocate</em>]
				</td>
			<td class="concordgeneral">    
				<select name="newSetting_coll_freqalone">
					<?php echo print_freqalone_form_options($User->coll_freqalone); ?>
				</select>
			</td>
		</tr>

		<tr>                               
			<td class="concordgeneral">
				Default range for calculating collocations
			</td>
			<td class="concordgeneral">   
				From
				<select name="newSetting_coll_from">
					<?php echo $optionsfrom; ?>
				</select>
				to
				<select name="newSetting_coll_to">
					<?php echo $optionsto; ?>
				</select>				
			</td>
		</tr>

		<tr>
			<th colspan="2" class="concordtable">Download options</th>
		</tr>
		
		<tr>
			<td class="concordgeneral">File format to use in text-only downloads</td>
			<td class="concordgeneral">
				<select name="newSetting_linefeed">
					<option value="au"<?php echo ($User->linefeed == 'au' ? ' selected="selected"' : '');?>>Automatically detect my computer</option>
					<option value="da"<?php echo ($User->linefeed == 'da' ? ' selected="selected"' : '');?>>Windows</option>
					<option value="a"<?php  echo ($User->linefeed == 'a'  ? ' selected="selected"' : '');?>>Unix / Linux (inc. Mac OS X)</option>
					<option value="d"<?php  echo ($User->linefeed == 'd'  ? ' selected="selected"' : '');?>>Macintosh (OS 9 and below)</option>
				</select>
			</td>
		</tr>


		<tr>
			<th colspan="2" class="concordtable">Other options</th>
		</tr>		
		<tr>
			<td class="concordgeneral">Real name</td>
			<td class="concordgeneral">
				<input name="newSetting_realname" type="text" width="64" value="<?php echo cqpweb_htmlspecialchars($User->realname); ?>"/>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Email address (system admin may use this if s/he needs to contact you!)</td>
			<td class="concordgeneral">
				<input name="newSetting_email" type="text" width="64" value="<?php echo cqpweb_htmlspecialchars($User->email); ?>"/>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" align="right">
				<input type="submit" value="Update settings" />
			</td>
			<td class="concordgrey" align="left">
				<input type="reset" value="Clear changes" />
			</td>
		</tr>
		<input type="hidden" name="redirect" value="newUserSettings" />
		<input type="hidden" name="uT" value="y" />

	</form>
</table>

	<?php

}

function printscreen_usermacros()
{
	global $username;
	
	// TODO - prob better to have these actions in user_admin instead.
	
	/* add a macro? */
	if (!empty($_GET['macroNewName']))
		user_macro_create($_GET['macroUsername'], $_GET['macroNewName'],$_GET['macroNewBody']); 
	
	/* delete a macro? */
	if (!empty($_GET['macroDelete']))
		user_macro_delete($_GET['macroUsername'], $_GET['macroDelete'],$_GET['macroDeleteNArgs']);
	// TODO use ID field instead
	
	?>
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable" colspan="3">User's CQP macros</th>
	</tr>
	
	<?php
	
	$result = do_mysql_query("select * from user_macros where user='$username'");
	if (mysql_num_rows($result) == 0)
	{
		?>
		
		<tr>
			<td colspan="3" align="center" class="concordgrey">
				&nbsp;<br/>
				You have not created any user macros.
				<br/>&nbsp;
			</td>
		</tr>
		
		<?php
	}
	else
	{
		?>
		
		<th class="concordtable">Macro</th>
		<th class="concordtable">Macro expansion</th>
		<th class="concordtable">Actions</th>
		
		<?php
		
		while (false !== ($r = mysql_fetch_object($result)))
		{
			echo '<tr>';
			
			echo "<td class=\"concordgeneral\">{$r->macro_name}({$r->macro_num_args})</td>";
			
			echo '<td class="concordgrey"><pre>'
				. $r->macro_body
				. '</pre></td>';
			
			echo '<form action="index.php" method="get"><td class="concordgeneral" align="center">'
				. '<input type="submit" value="Delete macro" /></td>'
				. '<input type="hidden" name="macroDelete" value="'.$r->macro_name.'" />'
				. '<input type="hidden" name="macroDeleteNArgs" value="'.$r->macro_num_args.'" />'
				. '<input type="hidden" name="macroUsername" value="'.$username.'" />'
				. '<input type="hidden" name="thisQ" value="userSettings" />'
				. '<input type="hidden" name="uT" value="y" />'
				. '</form>';
			
			echo '</tr>';	
		}	
	}
	
	?>
	
</table>

<table class="concordtable" width="100%">
	<tr>
		<th colspan="2" class="concordtable">Create a new CQP macro</th>
	</tr>
	<form action="index.php" method="get">
		<tr>
			<td class="concordgeneral">Enter a name for the macro:</td>
			<td class="concordgeneral">
				<input type="text" name="macroNewName" />
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Enter the body of the macro:</td>
			<td class="concordgeneral">
				<textarea rows="25" cols="80" name="macroNewBody"></textarea>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Click here to save your macro</br>(It will be available in all CQP queries)</td>
			<td class="concordgrey"><input type="submit" value="Create macro"/></td>
		</tr>
		
		<input type="hidden" name="macroUsername" value="<?php echo $username;?>" />
		<input type="hidden" name="thisQ" value="userSettings" />
		<input type="hidden" name="uT" value="y" />
		
	</form>
</table>
	<?php

}









?>
