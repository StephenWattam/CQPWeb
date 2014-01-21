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
 * This file contains the functions joint to queryhome and userhome.  
 */






















function printquery_who()
{
?>
<table class="concordtable" width="100%">

	<tr>
		<th class="concordtable">Publications about CQPweb</th>
	</tr>

	<tr>
		<td class="concordgeneral">
		
			<p>If you use CQPweb in your published research, it would be very much appreciated if you could
			provide readers with a reference to the following paper:</p>
			
			<ul>
				<li>
					Hardie, A (2012) <strong>CQPweb - combining power, flexibility 
					and usability in a corpus analysis tool</strong>. 
					<em>International Journal of Corpus Linguistics</em> 17 (3): 380&ndash;409.
					<a href="http://www.ingentaconnect.com/content/jbp/ijcl/2012/00000017/00000003/art00004" target="_blank">
						[Full text on publisher's website]
					</a>
					<a href="http://www.lancaster.ac.uk/staff/hardiea/cqpweb-paper.pdf" target="_blank">
						[Alternative link]
					</a>
				</li>
			</ul>
						
			<p><a href="http://cwb.sourceforge.net/doc_links.php">Click here</a> for other references relating
			to Corpus Workbench software.</p>
			
			<p>&nbsp;</p>
			
		</td>
	</tr>

	<tr>
		<th class="concordtable">Who did it?</th>
	</tr>

	<tr>
		<td class="concordgeneral">
		
			<p>CQPweb was created by Andrew Hardie (Lancaster University).</p>
				
			<p>Most of the architecture, the look-and-feel, and even some snippets of code
			were shamelessly half-inched from <em>BNCweb</em>.</p>
			
			<p>BNCweb's most recent version was written by Sebastian Hoffmann 
			(University of Trier) and Stefan Evert (University of 
			Osnabr&uuml;ck). It was originally created by Hans-Martin Lehmann, 
			Sebastian Hoffmann, and Peter Schneider.</p>
			
			<p>The underlying technology of CQPweb is manifold.</p>
			
			<ul>
				<li>Concordancing is done using the
					<a target="_blank" href="http://cwb.sourceforge.net/">IMS Corpus Workbench</a>		
					with its
					<a target="_blank" href="http://www.cogsci.uni-osnabrueck.de/~korpora/ws/CWBdoc/CQP_Tutorial/">
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
				<!-- TODO -- copy BNCweb notice about wz tooltip code -->
			</ul>
		</td>
	</tr>
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
<!--		Did a lot of new work on the help pages.
-->
		<li>
		<b>Version 3.1.1</b>, 2014-01-20<br/>&nbsp;<br/>
		Fixes for the inevitable bugs following a large update.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.1.0</b>, 2014-01-20<br/>&nbsp;<br/>
		Revamped user account system.
		<br/>&nbsp;<br/>
		Added a script to automatically upgrade an existing CQPweb MySQL database to match a more recent version of the code.
		<br/>&nbsp;<br/>
		Added a script to import user groups from the old system.
		<br/>&nbsp;<br/>
		Added a script to import group privileges from the old system.
		<br/>&nbsp;<br/>
		Fixed bug affecting use of XML tags in CEQL queries.
		<br/>&nbsp;<br/>
		Rewrote configuration file format and added documentation to system administrator's manual.
		<br/>&nbsp;<br/>
		Many other miscellaneous tweaks, improvements and architectural changes.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.16</b>, 2013-12-24<br/>&nbsp;<br/>
		Fixed two minor bugs in the concordance download function.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.15</b>, 2013-11-20<br/>&nbsp;<br/>
		Improved background handling of frequency lists (no changes a user would notice).
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.14</b>, 2013-11-18<br/>&nbsp;<br/>
		Added protection against users compiling very, very large frequency tables for subcorpora or on-the-fly for collocations.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.13</b>, 2013-11-04<br/>&nbsp;<br/>
		Implemented context-width restrictions for limited-license corpora.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.12</b>, 2013-11-02<br/>&nbsp;<br/>
		Updated database template for newer MySQL servers.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.11</b>, 2013-08-30<br/>&nbsp;<br/>
		New feature: non-classification metadata fields can now be included in a concordance-download. 
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.10</b>, 2013-04-22<br/>&nbsp;<br/>
		Added some extra protection against possible XSS (cross-site-scripting) attacks. 
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 3.0.9</b>, 2013-04-06<br/>&nbsp;<br/>
		Added a new feature: queries can now be downloaded as &quot;tabulations&quot;. 
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 3.0.8</b>, 2013-03-22<br/>&nbsp;<br/>
		Added a debugging backtrace to the error messages seen by superusers.
		<br/>&nbsp;<br/>
		Added Yates' continuity correction to the calculation of Z-score in the Collocation function.
		<br/>&nbsp;<br/>
		The usual miscellaneous bug fixes, including one affecting character encoding.
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 3.0.7</b>, 2013-03-19<br/>&nbsp;<br/>
		Fixed a bug affecting creation of batches of user accounts. 
		<br/>&nbsp;<br/>
		Fixed a bug causing the number of hits in a categorised query to be displayed incorrectly.
		<br/>&nbsp;<br/>
		Fixed a bug causing insertion of line-breaks into queries with long lines.
		<br/>&nbsp;<br/>
		Fixed an inconsistency in how batches of usernames are created.
		<br/>&nbsp;<br/>
		Fixed a bug in the management of user groups, plus a bug affecting the installation of
		corpora that are not in UTF-8.
		<br/>&nbsp;<br/>
		Fixed a bug in the install/delete corpus procedures which made deletion of a corpus
		difficult if its installation had previously failed halfway through.
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 3.0.6</b>, 2012-05-15<br/>&nbsp;<br/>
		More bug fixes.
		<br/>&nbsp;<br/>
		Added a new feature: a full file-by-file distribution table can now be downloaded.
		<br/>&nbsp;<br/>
		Adjusted the Distribution interface to make it more like the Collocations interface.
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 3.0.5</b>, 2012-02-19<br/>&nbsp;<br/>
		Just bug fixes, but major ones!
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 3.0.4</b>, 2012-02-10<br/>&nbsp;<br/>
		New feature: optional position labels in concordance (just like "sentence numbers" in BNCweb) 
		(this feature originally planned for 3.0.3 but not complete in that release). 
		<br/>&nbsp;<br/>
		Extended the XML visualisation system to allow conditional visualisations (ditto).
		<br/>&nbsp;<br/>
		XML visualisations now actually appear in the concordance (but only paritally rendered: they look like raw XML).
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 3.0.3</b>, 2012-02-05<br/>&nbsp;<br/>
		Mostly a boring bug-fix release, with only one new feature: users can now 
		customise their default thin-mode setting. 
		<br/>&nbsp;<br/>
		Fixed a bug in concordance download function that was scrambling links to context.		
		<br/>&nbsp;<br/>
		Fixed a bug in categorisation system that allowed invalid category names to be created.
		<br/>&nbsp;<br/>
		Fixed a bug in frequency list creation that introduced forms in the wrong character set
		into the database.
		<br/>&nbsp;<br/>
		Fixed a bug in the keyword function's frequency table lookup process.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.2</b>, 2011-08-28<br/>&nbsp;<br/>
		Added the long-awaited "upload user's own query" function.
		<br/>&nbsp;<br/>
		Finished the administrator's management of XML visualisations. Coming next, implementation in concordance view.
		<br/>&nbsp;<br/>
		Made it possible for a user to have the same saved-query name in two different corpora.
		<br/>&nbsp;<br/>
		Fixed a bug that made non-reproducible random thinning, actually always reproducible!
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.1</b>, 2011-08-20<br/>&nbsp;<br/>
		Implemented a better system for sorting corpora into categories on the homepage.
		<br/>&nbsp;<br/>
		Fixed a fairly nasty bug that was blocking corpus indexing.
		<br/>&nbsp;<br/>
		Fixed an uninformative error message when textual restrictions are selected that no texts actually 
		match (zero-sized section of the corpus). The new error message explains the issue more clearly.
		<br/>&nbsp;</li>

		<li>
		<b>Version 3.0.0</b>, 2011-07-18<br/>&nbsp;<br/>
		New feature: custom postprocess plugins!
		<br/>&nbsp;<br/>
		Fixed some bugs in unused parts of the CQP interface.
		<br/>&nbsp;<br/>
		Added support for all ISO-8859 character sets.
		<br/>&nbsp;<br/>
		Version number bumped to 3.0.0 to match new CWB versioning rules, though CQPweb is in fact now
		compatible with the post-Unicode versions of CWB (3.2.0+).
		<br/>&nbsp;</li>

		<li>
		<b>Version 2.17</b>, 2011-05-18<br/>&nbsp;<br/>
		Fixed a fairly critical (and very silly) bug that was blocking compression of indexed corpora.
		<br/>&nbsp;<br/>
		Added extra significance-threshold options for keywords analysis.
		<br/>&nbsp;</li>

		<li>
		<b>Version 2.16</b>, 2011-03-08<br/>&nbsp;<br/>
		Added a workaround for a problem that arises with some MySQL security setups.
		<br/>&nbsp;<br/>
		Added an optional RSS feed of system messages, and made links in system messages display correctly
		both within webpages and in the RSS feed.
		<br/>&nbsp;<br/>
		Created a storage location for executable command-line scripts that perform offline administration
		tasks (in a stroke of unparalleled originality, I call it "bin").
		<br/>&nbsp;<br/>
		Added customisable headers and logos to the homepage (a default CWB logo is supplied).
		<br/>&nbsp;<br/>
		Fixed a bug in right-to-left corpora (Arabic etc.) where collocations were referred to as being "to
		the right" or "to the left" of the node even though this was wrong by about 180 degrees.
		<br/>&nbsp;</li>

		<li>
		<b>Version 2.15</b>, 2010-12-02<br/>&nbsp;<br/>
		Licence switched from GPLv3+ to GPLv2+ to match the rest of CWB. Some source files remain to be updated!
		<br/>&nbsp;<br/>
		A framework for "plugins" (semi-freestanding programlets) has been added. Three types of
		plugins are envisaged: transliterator plugins, annotator plugins, and format-checker plugins. Some
		"default" plugins will be supplied later.
		<br/>&nbsp;<br/>
		Some tweaks have been made to the concordance download options, in particular, giving a new default
		download style (&ldquo;field-data-style&rdquo;).
		<br/>&nbsp;<br/>
		For the adminstrator, there is a new group-access-cloning function.
		<br/>&nbsp;<br/>
		The required version of CWB has been dropped back down to a late v2, but you still need 3.2.x
		if you want UTF-8 regular expression matching to work properly in all languages.
		<br/>&nbsp;<br/>
		Improvements to query cache management internals.
		<br/>&nbsp;<br/>
		Plus the usual bug fixes, including some that deal with security issues, and further work on the R
		interface.
		<br/>&nbsp;</li>

		<li>
		<b>Version 2.14</b>, 2010-08-27<br/>&nbsp;<br/>
		Quite a few new features this time. First, finer control over concordance display has been added;
		if you have the data, you can how have concordance results rendered as three-line-examples (field
		data or typology style with interlinear glosses).
		<br/>&nbsp;<br/>
		The R interface is ready for use with this version, although it is not actually used anywhere yet, and
		additional interface methods will be added as the need for them becomes evident. It goes without saying 
		that you need R installed in order to do anything with this.
		<br/>&nbsp;<br/>
		The new Web API has been established, and the first two functions "query" and "concordance" created.
		Documentation for the Web API is still on the to-do list, and it's not quite ready for use...
		<br/>&nbsp;<br/>
		Plus, a new function for creating snapshots of the system (useful for making backups); a "diagnostic"
		interface for checking out common problems in setting up CQP (incomplete as yet); and some improvements
		to the documentation for system administrators.
		<br/>&nbsp;<br/>
		Also added a new subcorpus creation function which makes one subcorpus for every text in the corpus.
		<br/>&nbsp;<br/>
		
		<li>
		<b>Version 2.13</b>, 2010-05-31<br/>&nbsp;<br/>
		Increased required version of CWB to 3.2.0 (which has Unicode regular expression matching). This means
		that regular expression wildcards will work properly with non-Latin alphabets.
		<br/>&nbsp;<br/>
		Also added a function to create an "inverted" subcorpus (one that contains all the texts in the corpus
		except those in a specified existing subcorpus).
		<br/>&nbsp;<br/>
		Plus, as ever, more bug fixes and usability tweaks.
		<br/>&nbsp;</li>
		
		<li>
		<b>Version 2.12</b>, 2010-03-19<br/>&nbsp;<br/>
		Added first version of XML visualisation.
		<br/>&nbsp;<br/>
		Also made distribution tables sortable on frequency or category handle (latter remains the default). 
		<br/>&nbsp;<br/>
		Also added support for CQP macros and for configurable context
		width in concordances (including xml-based context width as well as word-based context width).
		<br/>&nbsp;<br/>
		Plus many bug fixes and minor tweaks.
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
		New features: a link to &ldquo;corpus and tagset help&rdquo; on every page from the middle of the footer.
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
		Upgrades made to database structure to speed up collocations and keywords.
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
		Minor (but urgent) fixes to the system as a result of changes to MySQL database structure.
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
		Development of core PHP scripts, the CQP interface object model and the MySQL database 
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
