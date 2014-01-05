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


/**
 * Print the "about CQPweb" block that appears at the bottom of the menu for both queryhome and userhome.
 * 
 * Returns string (does not echo automatically!) 
 */
function print_menu_aboutblock()
{
	return  print_menurow_heading('About CQPweb') . 
		<<<HERE

<tr>
	<td class="concordgeneral">
		<a class="menuItem" href="../"
			onmouseover="return escape('Go to the main homepage for this CQPweb server')">
			CQPweb main menu
		</a>
	</td>
</tr>
<tr>
	<td class="concordgeneral">
		<a class="menuItem" target="_blank" href="../doc/CQPweb-man.pdf"
			onmouseover="return escape('CQPweb manual')">
			CQPweb manual
		</a>
	</td>
</tr>
HERE

		// TODO change manual link above. Is not good,. REplace with link to "Open Help Ssytem"
		. print_menurow_index('who_the_hell', 'Who did it?')
		. print_menurow_index('latest', 'Latest news')
		. print_menurow_index('bugs', 'Report bugs');
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
	global $User;
	
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
				if (!$User->logged_in)
					echo 'You are not logged in';
				else
					echo "You are logged in as user [{$User->username}]";
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



/**
 * Create an HTML header (everything from <html> to <body>,
 * which specified the title as provided, embeds a CSS link,
 * and finally imports the specified JavaScript files.
 */
function print_html_header($title, $css_url, $js_scripts = false)
{
	$s = "<html>\n<head>\n\t<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" >\n";

	$s .= "\t<title>$title</title>\n";

	if (!empty($css_url))
		$s .= "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$css_url\" >\n";

	if (! empty($js_scripts))
		foreach ($js_scripts as $js)
			$s .= "\t<script type=\"text/javascript\" src=\"../lib/javascript/$js.js\"></script>\n";
	
	$s .= "</head>\n<body>\n";
	
	return $s;
}

/**
 * The login form is used in more than one place, so this function 
 * puts the code in just one place.
 */
print_login_form()


?>
