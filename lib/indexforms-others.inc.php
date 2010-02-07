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







function printquery_usersettings()
{
	global $username;

// the majority of user settings should be boolean, in mysql they should be tinyint(1)
// and their column names should reflect this
// parameters of this form should be of the format newSetting_$mysql_column_name

	$settings = get_all_user_settings($username);
	
	list ($optionsfrom, $optionsto) 
		= print_fromto_form_options(10, $settings->coll_from, $settings->coll_to);
	
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
					<option value="1"<?php echo ($settings->conc_kwicview == '0' ? ' selected="selected"' : '');?>>KWIC view</option>
					<option value="0"<?php echo ($settings->conc_kwicview == '0' ? ' selected="selected"' : '');?>>Sentence view</option>
				</select>
			</td>
		</tr>


		<tr>
			<td class="concordgeneral">Default display order of concordances</td>
			<td class="concordgeneral">
				<select name="newSetting_conc_corpus_order">
					<option value="1"<?php echo ($settings->conc_corpus_order == '1' ? ' selected="selected"' : '');?>>Corpus order</option>
					<option value="0"<?php echo ($settings->conc_corpus_order == '0' ? ' selected="selected"' : '');?>>Random order</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">
				Show Simple Query translated into CQP syntax (in title bar and query history)
			</td>
			<td class="concordgeneral">
				<select name="newSetting_cqp_syntax">
					<option value="1"<?php echo ($settings->cqp_syntax == '1' ? ' selected="selected"' : '');?>>Yes</option>
					<option value="0"<?php echo ($settings->cqp_syntax == '0' ? ' selected="selected"' : '');?>>No</option>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Context display</td>
			<td class="concordgeneral">
				<select name="newSetting_context_with_tags">
					<option value="0"<?php echo ($settings->context_with_tags == '0' ? ' selected="selected"' : '');?>>Without tags</option>
					<option value="1"<?php echo ($settings->context_with_tags == '1' ? ' selected="selected"' : '');?>>With tags</option>
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
					<option value="1"<?php echo ($settings->use_tooltips == '1' ? ' selected="selected"' : '');?>>Yes</option>
					<option value="0"<?php echo ($settings->use_tooltips == '0' ? ' selected="selected"' : '');?>>No</option>
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
					<?php echo print_statistic_form_options($settings->coll_statistic); ?>
				</select>
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">
				Default minimum for freq(node, collocate) [<em>frequency of co-occurrence</em>]
			</td>
			<td class="concordgeneral">
				<select name="newSetting_coll_freqtogether">
					<?php echo print_freqtogether_form_options($settings->coll_freqtogether); ?>
				</select>
			</td>
		</tr>

		<tr>                               
			<td class="concordgeneral">
				Default minimum for freq(collocate) [<em>overall frequency of collocate</em>]
				</td>
			<td class="concordgeneral">    
				<select name="newSetting_coll_freqalone">
					<?php echo print_freqalone_form_options($settings->coll_freqalone); ?>
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
					<option value="au"<?php echo ($settings->linefeed == 'au' ? ' selected="selected"' : '');?>>Automatically detect my computer</option>
					<option value="da"<?php echo ($settings->linefeed == 'da' ? ' selected="selected"' : '');?>>Windows</option>
					<option value="a"<?php  echo ($settings->linefeed == 'a'  ? ' selected="selected"' : '');?>>Unix / Linux (inc. Mac OS X)</option>
					<option value="d"<?php  echo ($settings->linefeed == 'd'  ? ' selected="selected"' : '');?>>Macintosh (OS 9 and below)</option>
				</select>
			</td>
		</tr>


		<tr>
			<th colspan="2" class="concordtable">Other options</th>
		</tr>		
		<tr>
			<td class="concordgeneral">Real name</td>
			<td class="concordgeneral">
				<input name="newSetting_realname" type="text" width="64" value="<?php echo $settings->realname; ?>"/>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Email address (system admin may use this if s/he needs to contact you!)</td>
			<td class="concordgeneral">
				<input name="newSetting_email" type="text" width="64" value="<?php echo $settings->email; ?>"/>
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



function printquery_usermacros()
{
	global $username;
	
	/* add a macro? */
	if (!empty($_GET['macroNewName']))
		user_macro_create($_GET['macroUsername'], $_GET['macroNewName'],$_GET['macroNewBody']); 
	
	/* delete a macro? */
	if (!empty($_GET['macroDelete']))
		user_macro_delete($_GET['macroUsername'], $_GET['macroDelete'],$_GET['macroDeleteNArgs']); 
	
	?>
<table class="concordtable" width="100%">
	<tr>
		<th class="concordtable" colspan="3">User's CQP macros</th>
	</tr>
	
	<?php
	
	$result = do_mysql_query("select * from user_macros where username='$username'");
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








function printquery_corpusmetadata()
{
	global $mysql_link;
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
	
	/* load metadata into two result arrays */

	$sql_query = "select * from corpus_metadata_fixed where corpus = '$corpus_sql_name'";
	$result_fixed = do_mysql_query($sql_query);
	/* this will only contain a single row */
	$metadata_fixed = mysql_fetch_assoc($result_fixed);
	
	$sql_query = "select * from corpus_metadata_variable where corpus = '$corpus_sql_name'";
	$result_variable = do_mysql_query($sql_query);	
	
	/* now get total word length of all files */
	$words_in_all_texts = make_thousands(get_corpus_wordcount());
	
	/* get a list of metadata_fields */
	$sql_query = "select handle from text_metadata_fields where corpus = '$corpus_sql_name'";
	$result_textfields = do_mysql_query($sql_query);

	/* get a list of annotations */
	$sql_query = "select * from annotation_metadata where corpus = '$corpus_sql_name'";
	$result_annotations = do_mysql_query($sql_query);
	
	/* get the primary annotation's description */
	$sql_query = 'select description from annotation_metadata where handle = "' 
		. $metadata_fixed['primary_annotation'] . '"';
	$result_primary_tag = do_mysql_query($sql_query);
	if (mysql_num_rows($result_primary_tag) > 0 )
	{
		$primary_row = mysql_fetch_row($result_primary_tag);
		$primary_annotation_string = $primary_row[0];
	}
	else
		$primary_annotation_string = $metadata_fixed['primary_annotation'];
	unset($primary_row);
	unset($result_primary_tag);
		
	
	
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
			<td class="concordgrey">Total words in all corpus texts</td>
			<td class="concordgeneral"><?php echo $words_in_all_texts; ?></td>
		</tr>
	<?php
	
	
	/* VARIABLE METADATA */
	while (($metadata = mysql_fetch_assoc($result_variable)) != false)
	{
		?>
		<tr>
			<td class="concordgrey"><?php echo $metadata['attribute']; ?></td>
			<td class="concordgeneral"><?php echo $metadata['value']; ?></td>
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
			echo $annotation['description'];
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





















function printquery_who()
{
?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable">Who did it?</th>
	</tr>

	<tr><td class="concordgeneral">
	
		<p>CQPweb was created by Andrew Hardie (Lancaster University).</p>
		
		<p>Most of the architecture, the look-and-feel, and even some of the code
		was shamelessly half-inched from <em>BNCweb</em>.</p>
		
		<p>BNCweb's most recent version was written by Sebastian Hoffmann 
		(Lancaster University) and Stefan Evert (University of 
		Osnabr&uuml;ck). It was originally created by Hans-Martin Lehmann, 
		Sebastian Hoffmann, and Peter Schneider.</p>
		
		<p>The underlying technology of CQPweb is manifold.</p>
		
		<ul>
			<li>Concordancing is done using the
				<a target="_blank" href="http://cwb.sourceforge.net/">IMS Corpus Workbench</a>		
				with its
				<a target="_blank" href="http://www.ims.uni-stuttgart.de/projekte/tc/TCDetails.html">
					CQP corpus query processor</a>.
				Thus the name.
				<br/>&nbsp;
			</li>
			<li>Other functions (collocations, corpus management etc.) are powered by
				<a target="_blank" href="http://www.mysql.com/">MySQL</a> databases.
				<br/>&nbsp;
			</li>
			<li>The system uses 
				<a target="_blank" href="http://www.cogsci.uni-osnabrueck.de/~severt/">
					Stefan Evert</a>'s
				Simple Query (CEQL) parser, which is written in
				<a target="_blank" href="http://www.perl.org/">Perl</a>.
				<br/>&nbsp;
			</li>
			<li>The web-scripts are written in 
				<a target="_blank" href="http://www.php.net/">PHP</a>.
				<br/>&nbsp;
			</li>
			<li>Some 
				<a target="_blank" href="http://www.w3schools.com/JS/default.asp/">JavaScript</a>
				is used to create interactive links and forms.
				<br/>&nbsp;
			</li>
			<li>The look-and-feel relies on
				<a target="_blank" href="http://www.w3schools.com/css/default.asp">
					Cascading Style Sheets</a>
				plus good old fashioned
				<a target="_blank" href="http://www.w3schools.com/html/">HTML</a>.
				<br/>&nbsp;
			</li>
		</ul>
	</td></tr>
</table>
<?php
}





function printquery_latest()
{
?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable">Latest news</th>
	</tr>

	<tr><td class="concordgeneral">
	
	<p>&nbsp;</p>
	
	<ul>
		<b>Version 2.12</b>, 2010-01-xx<br/>&nbsp;<br/>
		Added first version of XML visualisation.
		<br/>&nbsp;<br/>
		Also made distribution tables sortable on frequency or category handle (latter remains the default). 
		<br/>&nbsp;<br/>
		Also added support for CQP macros and for configurable context
		width in concordances (including xml-based context width as well as word-based context width).
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.11</b>, 2010-01-20<br/>&nbsp;<br/>
		First release of 2010! CQPweb is now two years old.
		<br/>&nbsp;<br/>
		Added improved group access management, and a setting allowing corpora to be processed 
		in a case-sensitive way throughout (not recommended in general, but potentially useful 
		for some languages e.g. German).
		<br/> 
		Also added a big red warning that pops up when a user types an invalid character in a 
		"letters-and-numbers-only" entry on a form.
		<br/>
		Plus lots of bug fixes.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.10</b>, 2009-12-18<br/>&nbsp;<br/>
		Added customisable mapping tables for use with CEQL tertiary-annotations.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.09</b>, 2009-12-13<br/>&nbsp;<br/>
		New metadata-importing functions and other improvements to the internals of CQPweb.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.08</b>, 2009-11-27<br/>&nbsp;<br/>
		Updated internal database-query interaction. As a result, CQPweb requires CWB version 2.2.101 or later.
		<br/>
		Other changes (mostly behind-the-scenes):  enabled Latin-1 corpora; accelerated concordance display 
		by caching number of texts in a query in the database; plus assorted bug fixes.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.07</b>, 2009-09-08<br/>&nbsp;<br/>
		Fixed a bug in context display affecting untagged corpora.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.07</b>, 2009-08-07<br/>&nbsp;<br/>
		Enabled frequency-list comparison; fixed a bug in the sort function and another in the corpus 
		setup procedure.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.06</b>, 2009-07-27<br/>&nbsp;<br/>
		Added distribution-thin postprocessing function.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.05</b>, 2009-07-26<br/>&nbsp;<br/>
		Added frequency-list-thin postprocessing function.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.04</b>, 2009-07-05<br/>&nbsp;<br/>
		Bug fixes (thanks to Rob Malouf for spotting the bugs in question!) plus improvements to CQP interface
		object model.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.03</b>, 2009-06-18<br/>&nbsp;<br/>
		Added interface to install pre-indexed CWB corpus and made further tweaks to admin functions.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.02</b>, 2009-06-06<br/>&nbsp;<br/>
		Fixed some minor bugs, added categorised corpus display to main page, 
		added option to sort frequency lists alphabetically.
		<br/>&nbsp;</li>
		<li>
		<b>Version 2.01</b>, 2009-05-27<br/>&nbsp;<br/>
		Added advanced subcorpus editing tools. All the most frequently-used BNCweb functionality is now replicated.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.26</b>, 2009-05-25<br/>&nbsp;<br/>
		Added Categorise Query function.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.25</b>, 2009-04-05<br/>&nbsp;<br/>
		Added Word lookup function.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.24</b>, 2009-03-18<br/>&nbsp;<br/>
		Added concordance sorting.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.23</b>, 2009-03-01<br/>&nbsp;<br/>
		Minor updates to admin functions.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.22</b>, 2009-01-20<br/>&nbsp;<br/>
		Added support for right-to-left scripts (e.g. Arabic).
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.21</b>, 2009-01-06<br/>&nbsp;<br/>
		Added (a) concordance downloads and (b) concordance thinning function.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.20</b>, 2008-12-19<br/>&nbsp;<br/>
		Added (a) improved concordance Frequency Breakdown function and (b) downloadable concordance tables.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.19</b>, 2008-11-24<br/>&nbsp;<br/>
		New-style simple queries are now in place! This means that "lemma-tags" will now work for
		most corpora.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.18</b>, 2008-11-20<br/>&nbsp;<br/>
		The last bits of the Collocation function have been added in. Full BNCweb-style functionality
		is now available. The next upgrade will be to the new version of CEQL.
		<br/>&nbsp;</li>		
		<li>
		<b>Version 1.17</b>, 2008-11-12<br/>&nbsp;<br/>
		Links have been added to collocates in collocation display, leading to full statistics for
		each collocate (plus position breakdown).
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.16</b>, 2008-10-23<br/>&nbsp;<br/>
		Concordance random-order button has now been activated.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.15</b>, 2008-10-11<br/>&nbsp;<br/>
		A range of bugs have been fixed.<br/>
		New features: a link to &ldquo;corpus and tagset help&rdquo;help on every page from the middle of the footer.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.14</b>, 2008-09-16<br/>&nbsp;<br/>
		Not much change that the user would notice, but the admin functions have been completely overhauled.<br/>
		The main user-noticeable change is that UTF-8 simple queries are now possible.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.13</b>, 2008-08-04<br/>&nbsp;<br/>
		Added collocation concordances (i.e. concordances of X collocating with Y).<br/>
		Also added system-messages function.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.12</b>, 2008-07-27<br/>&nbsp;<br/>
		Upgrades made to databse structure to speed up collocations and keywords.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.11</b>, 2008-07-25<br/>&nbsp;<br/>
		Added improved user options database.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.10</b>, 2008-07-13<br/>&nbsp;<br/>
		Added frequency list view function, plus download capability for keywords and frequency lists.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.09</b>, 2008-07-03<br/>&nbsp;<br/>
		Added keywords, made fixes to frequency lists.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.08</b>, 2008-06-27<br/>&nbsp;<br/>
		Added collocations (now with full functionality). Added frequency list support for subcorpora.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.07</b>, 2008-06-10<br/>&nbsp;<br/>
		Added collocations function (beta version only).
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.06</b>, 2008-06-07<br/>&nbsp;<br/>
		Minor (but urgent) fixes to distribution as a result of changes to mySQL database structure.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.05</b>, 2008-05-23<br/>&nbsp;<br/>
		Added subcorpus functionality (not yet as extensive as BNCweb's).
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.04</b>, 2008-02-04<br/>&nbsp;<br/>
		Added restricted queries, and successfully trialled the system on a 4M word corpus.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.03</b>, 2008-01-23<br/>&nbsp;<br/>
		Added distribution function.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.02</b>, 2008-01-08<br/>&nbsp;<br/>
		Added save-query function and assorted cache management features for sysadmin.
		<br/>&nbsp;</li>
		<li>
		<b>Version 1.01</b>, 2008-01-06<br/>&nbsp;<br/>
		First version of CQPweb with fully working concordance function, cache management, 
		CSS layouts, metadata view capability and basic admin functions (including 
		username control) -- trial release with small test corpus only.
		<br/>&nbsp;</li>
		<li>
		<b>Autumn 2007</b>.<br/>&nbsp;<br/>
		Development of core PHP scripts, the CQP interface object model and the mySQL database 
		architecture.
		<br/>&nbsp;</li>
	</ul>
	</td></tr>

	<tr>
		<th class="concordtable">Known bugs <em>as of 2008-12-19</em></th>
	</tr>

	<tr><td class="concordgeneral">
	
	<p>&nbsp;</p>
	
	<ul>
		<li>
			<b>Query history</b> 
			<br/>&nbsp;<br/>
			Items in query history should be auto-deleted after a set time (one week); this doesn't
			seem to be happening. (added 2008-06-15)
			<br/>&nbsp;
		</li>
		<li>
			<b>Text metadata table</b> 
			<br/>&nbsp;<br/>
			Text metadata does not make &gt; &lt; &amp; safe as entities for HTML. (added 2008-06-10)
			<br/>&nbsp;
		</li>
		<li>
			<b>Query history</b> 
			<br/>&nbsp;<br/>
			"Insert query" links in column 3 of the history display don't work if the restriction 
			is a subcorpus. (added 2008-06-07)
			<br/>&nbsp;
		</li>
		<li>
			<b>Flyby infoboxes</b>
			<br/>&nbsp;<br/>
			In Internet Explorer, flyby infoboxes don't appear. (added 2008-06-07)
			<br/>&nbsp;<br/>
			This only happens when CQPweb is accessed over the Internet. Over Intranet, the 
			popup boxes appear fine. This seems to be something to do with Windows/IE security 
			settings blocking the JavaScript that creates the infoboxes. IE doesn't block the script
			over Intranet; nor, apparently, over HTTPS.
			<br/>&nbsp;<br/>
			Update: in Google Chrome, the flyby boxes appear intermittently for some corpora (haven't yet checked on 
			other browsers).
			<br/>&nbsp;
		</li>
	</ul>
	</td></tr>


</table>
<?php
}




function printquery_bugs()
{
?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable">Bugs in CQPweb</th>
	</tr>

	<tr>
		<td class="concordgeneral">
		
		<p class="spacer">&nbsp;</p>
		
		<h3>Send email about bugs to Andrew Hardie:</h3>
		
		<!-- form start -->
		<form action="http://www.lancs.ac.uk/mailto/" method=POST>
			<input name="MSG_FIELDS" type=hidden value="name,email,subject">
			<input name="NONBLANK_FIELDS" type=hidden value="name,email,subject">
			<input name="MSG_BODY" type=hidden value="message">
			<input name="ID" type=hidden value="andrewhardie">
			<table>
			  <tr>
			    <td align="right">Your Name:</td>
			    <td><input name="name" size=40 value=""></td>
			  </tr>
			  <tr>
			    <td align="right">Your email address:</td>
			    <td><input name="email" size=40 value=""></td>
			  </tr>
			  <tr>
			    <td align="right">Subject:</td>
			    <td><input name="subject" size=40 value=""></td>
			  </tr>
			  <tr>
			    <td align="right" valign="top">Message:</td>
			    <td>
					<textarea name="message" cols="60" rows="10"></textarea>
					<br/>
					(Describe in as much detail as possible 
					<br/>
					what you were trying to do and what happened)
				</td>
			  </tr>
			  <tr>
			    <td></td><td><input type=submit value="Send"></td>
			  </tr>
			</table>
		</form>
		<!-- form end -->
		<p class="spacer">&nbsp;</p>
	
		</td>
	</tr>
</table>
<?php
}

?>
