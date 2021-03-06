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


/* Very first thing: Let's work in a subdirectory so that we can use the same subdirectory references! */
chdir('../bin');


require('../lib/environment.inc.php');

require('../lib/library.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/metadata.inc.php');
require('../lib/exiterror.inc.php');


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP | CQPWEB_STARTUP_DONT_CHECK_URLTEST);

if ($Config->homepage_use_corpus_categories)
{
	/* get a list of categories */
	$categories = list_corpus_categories();

	/* how many categories? if only one, it is either uncategorised or a single assigned cat: ergo don't use cats */
	$n = count($categories);
	if ($n < 2)
		$Config->homepage_use_corpus_categories = false;
}
else
{
	/* empty string: to make the loops cycle once */
	$categories = array(0=>'');
}


/* devise the HTML for the header-bar logos. */
$logo_divs = '';
foreach ( array('left', 'right') as $side)
{
	$addresses = 'homepage_logo_'.$side;
	if (empty($Config->$addresses))
		continue;
	if (false !== strpos($Config->$addresses, "\t"))
		list ($img_url, $link_url) = explode("\t", $Config->$addresses, 2);
	else
	{
		$img_url = $Config->$addresses;
		$link_url = false;
	}
	$logo_divs .= "<div style=\"float: $side;\">" .
		($link_url ? "<a href=\"$link_url\">" : '') .
		"<img src=\"$img_url\" height=\"80\"  border=\"0\" >" .
		($link_url ? '</a>' : '') .
		'</div>      ';
}





echo print_html_header('CQPweb Main Page', $Config->css_path);

?>


<!-- main table -->
<table class="concordtable" width="100%">
	<tr>
		<td valign="top">
            <?php print_menu(); ?>
		</td>
		<td valign="top" width="100%">

<h1 class="page-title"><?php echo $logo_divs, $homepage_welcome_message; ?></h1>

<table class="concordtable" width="100%">

	<tr>
	<?php



	if ($User->logged_in)
	{
		/* personalised wlecome message */
		if (empty($User->realname) || $User->realname == 'unknown person')
			$personalise = '';
		else
			$personalise = ', ' . cqpweb_htmlspecialchars($User->realname);

/* TODO: once we have the title in database, pull it here */
		$result = do_mysql_query("select corpus from query_history where user='{$User->username}' order by date_of_query desc");

		$recent_corpora = array();

		while (count($recent_corpora) < 6 && false !== ($o = mysql_fetch_object($result)))
		{
			foreach($recent_corpora as $rc)
				if ($rc->corpus == $o->corpus)
					continue 2;
			$recent_corpora[] = $o; // TODO make the value to the description, once we have it
		}
		?>
		<?php
		echo "\t<tr>\n\t\t\n";

	}
	else
	{
		echo "\t<tr>\n\t\t<td colspan=\"3\" class=\"concordgeneral\">\n";
		echo print_login_form();
		echo "\t\t\t<p align=\"center\"><a href=\"../usr/?thisQ=create&uT=y\">Create account</a>";
		echo " | <a href=\"../usr/\">Full account-control options</a>\n";
		echo "\t\t\t<p>&nbsp;</p>\n";
		echo "\t\t</td>\n\t</tr>\n";
	}



	?>
	<tr>
		<th colspan="3" class="concordtable">
			Corpora available on this server (<a href="../usr/index.php?thisQ=corpusAccess&uT=y">view access privileges</a>)
		</th>
	</tr>
<?php



foreach ($categories as $id => $cat)
{
	/* get a list of corpora */

	$sql_query = "select corpus, visible from corpus_info where visible = 1 "
		. ($Config->homepage_use_corpus_categories ? "and corpus_cat = $id" : '')
		. " order by corpus asc";

	$result = do_mysql_query($sql_query);

	$corpus_list = array();
	while ( ($x = mysql_fetch_object($result)) != false)
		$corpus_list[] = $x;

	/* don't print a table for empty categories */
	if (empty($corpus_list))
		continue;



	if ($Config->homepage_use_corpus_categories)
		echo "\t\t<tr><th colspan=\"3\" class=\"concordtable\">$cat</th></tr>\n\n";



	$i = 0;
	$celltype = 'concordgeneral';

	foreach ($corpus_list as $c)
	{
		if ($i == 0)
			echo "\t\t<tr>";

		/* get $corpus_title */
		include ("../{$c->corpus}/settings.inc.php");
		if (empty($corpus_title))
			$corpus_title = $c->corpus;

		echo "
			<td class=\"$celltype\" width=\"33%\" align=\"center\">
				&nbsp;<br/>
				<a href=\"../{$c->corpus}/\">$corpus_title</a>
				<br/>&nbsp;
			</td>\n";

		$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');

		if ($i == 2)
		{
			echo "\t\t</tr>\n";
			$i = 0;
		}
		else
		{
			$i++;
		}

		unset($corpus_title);
	}

	if ($i == 1)
	{
		echo "\t\t\t<td class=\"$celltype\" width=\"33%\" align=\"center\">&nbsp;</td>\n";
		$i++;
		$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');
	}
	if ($i == 2)
		echo "\t\t\t<td class=\"$celltype\" width=\"33%\" align=\"center\">&nbsp;</td>\n\t\t</tr>\n";
}

?>


</table>

<a name="messages"></a>

<?php

display_system_messages();

?>

		</td>
	</tr>
</table>
<?php

echo print_html_footer();

cqpweb_shutdown_environment();


/* END OF SCRIPT */

?>
