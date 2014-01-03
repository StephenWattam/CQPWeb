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
 * A file full of functions that generate handy bits of HTML.
 * 
 * ALL functions in this library *retuirn* a string rather than echoing it.
 * 
 * So, the return value can be echoed (to browser), or stuffed into a variable.
 */


// TODO these should NOT use global state. Sjhould use a parameter.
/**
 * Creates a table row for the index-page left-hand-side menu, which is either a link,
 * or a greyed-out entry if the variable specified as $current_query is equal to
 * the link handle. It is returned as a string, -not- immediately echoed.
 *
 * This is the version for the normal user-facing index.
 */
function print_menurow_index($link_handle, $link_text)
{
	global $thisQ;
	return print_menurow_backend($link_handle, $link_text, $thisQ, 'thisQ');
}
function print_menurow_backend($link_handle, $link_text, $current_query, $http_varname)
{
	$s = "\n<tr>\n\t<td class=\"";
	if ($current_query != $link_handle)
		$s .= "concordgeneral\">\n\t\t<a class=\"menuItem\""
			. " href=\"index.php?$http_varname=$link_handle&uT=y\">";
	else 
		$s .= "concordgrey\">\n\t\t<a class=\"menuCurrentItem\">";
	$s .= "$link_text</a>\n\t</td>\n</tr>\n";
	return $s;
}
/**
 * Creates a table row for the index-page left-hand-side menu, which is either a link,
 * or a greyed-out entry if the variable specified as $current_query is equal to
 * the link handle. It is returned as a string, -not- immediately echoed.
 *
 * This is the version for adminhome.
 */
function print_menurow_admin($link_handle, $link_text)
{
	global $thisF;
	return print_menurow_backend($link_handle, $link_text, $thisF, 'thisF');
}

/**
 * Creates a table row for the index-page left-hand-side menu, which is a section heading
 * containing the label as provided.
 */
function print_menurow_heading($label)
{
	return "\n<tr><th class=\"concordtable\"><a class=\"menuHeaderItem\">$label</a></th></tr>\n\n";
}


// TODO make this RETURN rather than ECHO
/**
 * Creates a page footer for CQPweb.
 * 
 * Pass in the string "admin" for an admin-logon link. 
 * Default link is to a help page.
 */ 
function print_html_footer($link = 'help')
{
	global $username;
	
	/* javascript location diverter */
	$diverter = '../';
	
	if ($link == 'help')
	{
		$help_cell = '<td align="center" class="cqpweb_copynote" width="33%">
			<a class="cqpweb_copynote_link" href="help.php" target="_NEW">Corpus and tagset help</a>
		</td>';
	}
	else if ($link == 'admin')
	{
		/* use the help cell for an admin logon link instead */
		$help_cell = '<td align="center" class="cqpweb_copynote" width="33%">
			<a href="adm"  class="cqpweb_copynote_link" >[Admin logon]</a>
		</td>';	
		/* when link is admin, javascript is in lib, which is a subdir. */
		$diverter = '';
	}
	else
	{
		$help_cell = '<td align="center" class="cqpweb_copynote" width="33%">
			&nbsp;
		</td>';
	}
	
	?>
	<hr/>
	<table class="concordtable" width="100%">
		<tr>
			<td align="left" class="cqpweb_copynote" width="33%">
				CQPweb v<?php echo CQPWEB_VERSION; ?> &#169; 2008-2014
			</td>
			<?php echo $help_cell; ?>  
			<td align="right" class="cqpweb_copynote" width="33%">
				<?php
				if ($username == '__unknown_user')
					echo 'You are not logged in';
				else
					echo "You are logged in as user [$username]";
				?>
			</td>
		</tr>
	</table>
	<script language="JavaScript" type="text/javascript" src="<? echo $diverter; ?>lib/javascript/wz_tooltip.js">
	</script>
	</body>
</html>
	<?php
}




function print_html_header($title_label = false, $js_scripts = false)
{
	$s = "<html>\n<head>\n\t<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" >\n";
	
	global $corpus_title;
	global $css_path;
	
	if (empty($title_label))
		$title_label = 'CQPweb';
	
	$s .= "\t<title>$corpus_title -- $title_label</title>\n";
	$s .= "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$css_path\" >\n";

	if (! empty($js_scripts))
		foreach ($js_scripts as $js)
			$s .= "\t<script type=\"text/javascript\" src=\"../lib/javascript/$js.js\"></script>\n";
	
	$s .= "</head>\n<body>\n";
	
	return $s;
}


?>
