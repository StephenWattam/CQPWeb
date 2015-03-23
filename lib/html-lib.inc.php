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
function print_menurow_index($link_handle, $link_text) {

	global $thisQ;
	return print_menurow_backend($link_handle, $link_text, $thisQ, 'thisQ');
}
function print_menurow_backend($link_handle, $link_text, $current_query, $http_varname) {

    $s = print_menurow($link_text, "index.php?$http_varname=$link_handle&uT=y", $current_query == $link_handle);
    return $s;
}

function print_menurow($link_text, $href, $selected = false, $mouseover = false, $new_window = false){

    # Construct header with optional class if selected
    $s = "<tr><td class=\"menu-item";
    if($selected){
        $s .= " selected";
    }
    $s .= "\">";
    
    # Write link.  TODO: include mouseover text
    $s .= "<a class=\"menuItem\" href=\"$href\"";
    if($mouseover)
        $s .= " onmouseover=\"return escape('" . addcslashes($mouseover, "'") . "');\"";
    if($new_window)
        $s .= " target=\"_blank\"";
    $s .= ">$link_text</a>";
    $s .= "</td></tr>";

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
	return  print_menurow_heading('About CQPweb') 

        . print_menurow('Video tutorials', 'http://www.youtube.com/playlist?list=PL2XtJIhhrHNQgf4Dp6sckGZRU4NiUVw1e', false, 'CQPweb video tutorials', true)
        . print_menurow('Main menu', '../', false, 'Go to the main homepage for this CQPweb server')

		// TODO change manual link above. Is not good,. REplace with link to "Open Help Ssytem"
		. print_menurow_index('who_the_hell', 'Contributors')
		. print_menurow_index('latest', 'Latest news')
		. print_menurow_index('bugs', 'Report bugs');
}







/** Display the entire menu.
 *
 * Really stateful, really messy.  Call only when necessary...
 */
function print_menu(){

    global $User;
    global $corpus_sql_name;

    /* ******************* *
     * PRINT SIDE BAR MENU *
     * ******************* */

    echo '<div id="showMenu" style="display: none; text-decoration: underline; font-size: smaller; position: fixed; left: 0; top: 0;">';
    echo '<a href="#" onclick="document.getElementById(\'menuTable\').style.display=\'block\';document.getElementById(\'showMenu\').style.display=\'none\';">Show menu</a>';
    echo '</div>';

    echo ('<table class="menu" width="100%" id="menuTable">');
    echo '<tr><td style="font-size: smaller; text-decoration: underline; color: black;">';
    echo '<a href="#" onclick="document.getElementById(\'menuTable\').style.display=\'none\';document.getElementById(\'showMenu\').style.display=\'block\';">Hide menu</a>';
    echo '</td></tr>';

    echo print_menurow_heading('Corpus info');

    /* note that most of this section is links-out, so we can't use the print-row function */

    /* print a link to a corpus manual, if there is one */
    $sql_query = "select external_url from corpus_info where corpus = '$corpus_sql_name' and external_url IS NOT NULL";
    $result = do_mysql_query($sql_query);
    if (mysql_num_rows($result) >= 1)
    {
        $row = mysql_fetch_row($result);

        # FIXME: 'currently viewing' will never be set to true below.  This is a small bug.
        /* echo print_menurow('Corpus documentation', $row[0], false, "Info on $corpus_title", true); */
        echo print_menurow_index('corpusDocs', 'Corpus documentation');
    }

    /* SHOW CORPUS METADATA */
    echo print_menurow_index('corpusMetadata', 'View corpus metadata');






    echo print_menurow_heading('Corpus queries');
    echo print_menurow_index('search', 'Standard query');
    echo print_menurow_index('restrict', 'Restricted query');
/* TODO
   note for future: "Restrict query by text" vs "Restrict quey by XML"
   OR: Restrict query (by XXXX) to be part of the configuration in the DB?
   with a row for every XXXX that is an XML in the db that has been set up
   for restricting-via? 
   and the normal "Restricted query" is jut a special case for text / text_id

   OR: just have "Restricted query" and open up sub-options when that is clicked on?
 */
    echo print_menurow_index('lookup', 'Word lookup');
    echo print_menurow_index('freqList', 'Frequency lists');
    echo print_menurow_index('keywords', 'Keywords');

    echo print_menurow_heading('User controls');
    echo print_menurow_index('history', 'Query history');
    echo print_menurow_index('savedQs', 'Saved queries');
    echo print_menurow_index('categorisedQs', 'Categorised queries');
    echo print_menurow_index('uploadQ', 'Upload a query');
    /* TODO: this is only for admin users while under development */
    //if ($User->is_admin())
    echo print_menurow_index('analyseCorpus', 'Analyse corpus');
    echo print_menurow_index('subcorpus', 'Create/edit subcorpora');




    /* print a link to each tagset for which an external_url is declared in metadata */
    // todo: change this to use get_corpus_annotation_info()
    $sql_query = "select description, tagset, external_url from annotation_metadata "
        . "where corpus = '$corpus_sql_name' and external_url IS NOT NULL";
    $result = do_mysql_query($sql_query);

    while (($row = mysql_fetch_assoc($result)) != false)
    {
        if ($row['external_url'] != '')
            echo '<tr><td class="concordgeneral"><a target="_blank" class="menuItem" href="'
            . $row['external_url'] . '" onmouseover="return escape(\'' . $row['description']
            . ': view documentation\')">' . $row['tagset'] . '</a></td></tr>';
    }




    /* these are the super-user options */
    if ($User->is_admin())
    {
        echo print_menurow_heading('Admin tools');
        echo print_menurow('Admin control panel', '../adm');
        echo print_menurow_index('corpusSettings', 'Corpus settings');
        echo print_menurow_index('userAccess', 'Manage access');
        echo print_menurow_index('manageMetadata', 'Manage metadata');
        echo print_menurow_index('manageCategories', 'Manage text categories');
        echo print_menurow_index('manageAnnotation', 'Manage annotation');
        echo print_menurow_index('manageVisualisation', 'Manage visualisations');
        echo print_menurow_index('cachedQueries', 'Cached queries');
        echo print_menurow_index('cachedDatabases', 'Cached databases');
        echo print_menurow_index('cachedFrequencyLists', 'Cached frequency lists');

    } /* end of "if user is a superuser" */

    /* all the rest is encapsulated */
    echo print_menu_aboutblock();

    echo('</table>');


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
	// TODO - we can get rid of the diverter if the wz_tooltip is rewritten and integrated into the JS
	// that goes in the page header (which would be better).
	$diverter = '../';

?>    
    <div class="footer">
        <span class="footer-item">
            CQPweb v<?php echo CQPWEB_VERSION; ?> &#169; 2008-2014
        </span>


    <?php 
        if ($link == 'help') { ?>
            <span class="footer-item">
                <a class="cqpweb_copynote_link" href="help.php" target="_NEW">Corpus and tagset help</a>
                </span> <?php
        }
		
        if ($User->logged_in) { ?>
            <span class="footer-item">
            You are logged in as <?php echo $User->username ?>
            </span> <?php
        }
    ?>

	<script language="JavaScript" type="text/javascript" src="<?php echo $diverter; ?>jsc/wz_tooltip.js">
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
	global $Config;
	
	/* also set the generic header (will only be sent when the header is echo'd, though) */
    header('Content-Type: text/html; charset=utf-8');
	
	$s = "<!DOCTYPE html><html>\n<head>\n\t<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" >\n";

	$s .= "\t<title>$title</title>\n";

	if (!empty($css_url))
		$s .= "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$css_url\" >\n";

	$js_path = ($Config->run_location == RUN_LOCATION_MAINHOME ? 'jsc' : '../jsc');

	if (empty($js_scripts))
		$js_scripts = array('jquery', 'always');
	else
		array_unshift($js_scripts, 'jquery', 'always');
	
	foreach ($js_scripts as $js)
		$s .= "\t<script type=\"text/javascript\" src=\"$js_path/$js.js\"></script>\n";
	
	$s .= "</head>\n<body>\n";
	
	return $s;
}

/**
 * The login form is used in more than one place, so this function 
 * puts the code in just one place.
 */
function print_login_form($location_after = false)
{
	global $Config;
	
	if ($Config->run_location == RUN_LOCATION_USR)
		$pathbegin = '';	
	else if ($Config->run_location == RUN_LOCATION_MAINHOME)
		$pathbegin = 'usr/';
	else
		/* in a corpus, or in adm */
		$pathbegin = '../usr/';
	
	/* pass through a location after, if one was given */
	$input_location_after = (empty($location_after) 
								? '' 
								: '<input type="hidden" name="locationAfter" value="'.cqpweb_htmlspecialchars($location_after).'" />'
								);
		
	return <<<HERE

				<form action="{$pathbegin}redirect.php" method="POST">
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
						<tr>
							<td class="basicbox">Tick here to stay logged in on this computer:</t6d>
							<td class="basicbox">
								<input type="checkbox" name="persist" value="1"  />
							</td>
						</tr>
						<tr>
							<td class="basicbox" align="right">
								<input type="submit" value="Click here to log in"  />
							</td>
							<td class="basicbox" align="left">
								<input type="reset" value="Clear form"  />
							</td>
						</tr>
						$input_location_after
						<input type="hidden" name="redirect" value="userLogin" />
						<input type="hidden" name="uT" value="y" />
					</table>
				</form>

HERE;

}


/**
 * Dumps out a reasonably-nicely-formatted representation of an
 * arbitrary MySQL query result.
 * 
 * For debug purposes, or for when we have not yet written the code for a nicer layout.
 * 
 * @param $result  A result resource returned by do_mysql_query().  
 */ 
function print_mysql_result_dump($result)
{
	/* print column headers */
	$table = "\n\n<!-- MYSQL RESULT DUMP -->\n\n" . '<table class="concordtable" width="100%"><tr>';
	for ( $i = 0 ; $i < mysql_num_fields($result) ; $i++ )
		$table .= "<th class='concordtable'>" . mysql_field_name($result, $i) . "</th>";
	$table .= '</tr>';
	
	/* print rows */
	while ( ($row = mysql_fetch_row($result)) !== false )
	{
		$table .= "<tr>";
		foreach ($row as $r)
			$table .= "<td class='concordgeneral' align='center'>$r</td>\n";
		$table .= "</tr>\n";
	}
	
	$table .= "</table>\n\n";	
	return $table;
}
