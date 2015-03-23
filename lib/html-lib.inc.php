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
function print_menurow_index($link_handle, $link_text, $section = '') {
	global $thisQ;
	return print_menurow_backend($link_handle, $link_text, $thisQ, 'thisQ', $section);
}

function print_menurow_backend($link_handle, $link_text, $current_query, $http_varname, $section = '') {
    $s = print_menurow($link_text, "?$http_varname=$link_handle&uT=y", $section, $current_query == $link_handle);
    return $s;
}

function print_menurow($link_text, $href, $section = '', $selected = false, $mouseover = false, $new_window = false){

    # Construct header with optional class if selected
    $s = "<tr><td class=\"menu-item";
    if($selected){
        $s .= " selected";
    }
    $s .= "\">";

    # Make an attempt to unify sections
    if($section != ''){
        $href = "../$section/$href";
    }

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
function print_about_menu()
{
	return  print_menurow_heading('About CQPweb')

        . print_menurow('Video tutorials', 'http://www.youtube.com/playlist?list=PL2XtJIhhrHNQgf4Dp6sckGZRU4NiUVw1e', false, 'CQPweb video tutorials', true)
        . print_menurow('Main menu', '../', false, 'Go to the main homepage for this CQPweb server')

		// TODO change manual link above. Is not good,. REplace with link to "Open Help Ssytem"
		. print_menurow_index('who_the_hell', 'Contributors')
		. print_menurow_index('latest', 'Latest news')
		. print_menurow_index('bugs', 'Report bugs');
}




function print_admin_menu(){



    /* ******************* */
    /* PRINT SIDE BAR MENU */
    /* ******************* */

    // Show/hide
    echo '<div id="showMenu" onclick="$(\'.menu\').fadeToggle(200);">&#8660;</div>';

    // Menu header and contents
    echo ('<div class="menu">');
    echo ('<table width="100%" id="menuTable">');

    print_places_menu();

    echo print_menurow_heading('Corpora');
    echo print_menurow_admin('showCorpora', 'Show corpora');
    echo print_menurow_admin('installCorpus', 'Install new corpus');
    echo print_menurow_admin('manageCorpusCategories', 'Manage corpus categories');
    echo print_menurow_admin('annotationTemplates', 'Annotation templates');
    echo print_menurow_admin('metadataTemplates', 'Metadata templates');
    echo print_menurow_admin('xmlTemplates', 'XML templates');

    echo print_menurow_heading('Uploads');
    echo print_menurow_admin('newUpload', 'Upload a file');
    echo print_menurow_admin('uploadArea', 'View upload area');

    echo print_menurow_heading('Users and privileges');
    echo print_menurow_admin('userAdmin', 'Manage users');
    echo print_menurow_admin('groupAdmin', 'Manage groups');
    echo print_menurow_admin('groupMembership', 'Manage group membership');
    echo print_menurow_admin('privilegeAdmin', 'Manage privileges');
    echo print_menurow_admin('userGrants', 'Manage user grants');
    echo print_menurow_admin('groupGrants', 'Manage group grants');

    echo print_menurow_heading('Frontend interface');
    echo print_menurow_admin('systemMessages', 'System messages');
    echo print_menurow_admin('mappingTables', 'Mapping tables');

    echo print_menurow_heading('Backend system');
    echo print_menurow_admin('cacheControl', 'Cache control');
    echo print_menurow_admin('manageProcesses', 'Manage MySQL processes');
    echo print_menurow_admin('tableView', 'View a MySQL table');
    echo print_menurow_admin('phpConfig', 'PHP configuration');
    echo print_menurow_admin('opcodeCache', 'PHP opcode cache');
    /* echo print_menurow_admin('publicTables', 'Public frequency lists'); */
    echo print_menurow_admin('systemSnapshots', 'System snapshots');
    echo print_menurow_admin('systemDiagnostics', 'System diagnostics');

    echo print_menurow_heading('Usage Statistics');
    echo print_menurow_admin('corpusStatistics', 'Corpus statistics');
    echo print_menurow_admin('userStatistics', 'User statistics');
    echo print_menurow_admin('queryStatistics', 'Query statistics');
    echo print_menurow_admin('advancedStatistics', 'Advanced statistics');


    print_user_menu();
    print_about_menu();

    echo('</table>');
    echo('</div>');
}



/** Display the entire menu.
 *
 * Really stateful, really messy.
 *
 * Should be called for every normal user page (i.e. not admin.)
 */
function print_menu(){

    global $corpus_sql_name;

    // Show/hide
    echo '<div id="showMenu" onclick="$(\'.menu\').fadeToggle(200);">&#8660;</div>';

    // Menu header and contents
    echo ('<div class="menu">');
    echo ('<table width="100%" id="menuTable">');


    print_places_menu();

    if(isset($corpus_sql_name)){
        print_corpus_menu($corpus_sql_name);
    }

    print_user_menu();
    print_about_menu();
    echo('</table>');
    echo('</div>');
}



/** PRints the places menu, showing users where they are in the interface.
 *
 */
function print_places_menu(){

    global $User;
    global $corpus_sql_name;

    echo print_menurow_heading('Places');
    echo print_menurow('Home', '', 'home');

    if($corpus_sql_name) {
        echo print_menurow('Current Corpus', '', $corpus_sql_name);
    }
    echo print_menurow('User menu', '', 'usr');
    if($User->is_admin()) {
        echo print_menurow_index('', 'Admin interface', 'adm');
    }
}

/** Print user-specific menu options */
function print_user_menu(){

    global $User;
    /* The menu is different for when we are logged on, versus when we are not */

    if ($User->logged_in)
    {
        echo print_menurow_heading('Your account');
        echo print_menurow_index('welcome', 'Overview', 'usr');
        echo print_menurow_index('userSettings', 'Interface settings', 'usr');
        echo print_menurow_index('userMacros', 'User macros', 'usr');
        echo print_menurow_index('corpusAccess', 'Corpus permissions', 'usr');
        echo print_menurow_heading('Account actions');
        echo print_menurow_index('userDetails', 'Account details', 'usr');
        echo print_menurow_index('changePassword', 'Change password', 'usr');
        echo print_menurow_index('userLogout', 'Log out of CQPweb', 'usr');
        if ($User->is_admin())
        {
            echo print_menurow_index('', 'Admin interface', 'adm');
        }
    }
    else
    {
        /* i we are not logged in, then we want to show a different default ... */
        global $thisQ;
        if ($thisQ == 'welcome')
            $thisQ = 'login';

        /* menu seen when no user is logged in */
        echo print_menurow_heading('Account actions');
        echo print_menurow_index('login', 'Log in to CQPweb', 'usr');
        echo print_menurow_index('create', 'Create new user account', 'usr');
        echo print_menurow_index('verify', 'Activate new account', 'usr');
        echo print_menurow_index('resend', 'Resend account activation', 'usr');
        echo print_menurow_index('lostUsername', 'Retrieve lost username', 'usr');
        echo print_menurow_index('lostPassword', 'Reset lost password', 'usr');
    }


}


function print_corpus_menu($corpus_sql_name){

    global $User;

    /* ******************* *
     * PRINT SIDE BAR MENU *
     * ******************* */

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
        echo print_menurow_index('corpusDocs', 'Corpus documentation', $corpus_sql_name);
    }

    /* SHOW CORPUS METADATA */
    echo print_menurow_index('corpusMetadata', 'View corpus metadata', $corpus_sql_name);


    echo print_menurow_heading('Corpus queries');
    echo print_menurow_index('search', 'Standard query', $corpus_sql_name);
    echo print_menurow_index('restrict', 'Restricted query', $corpus_sql_name);
/* TODO
   note for future: "Restrict query by text" vs "Restrict quey by XML"
   OR: Restrict query (by XXXX) to be part of the configuration in the DB?
   with a row for every XXXX that is an XML in the db that has been set up
   for restricting-via?
   and the normal "Restricted query" is jut a special case for text / text_id

   OR: just have "Restricted query" and open up sub-options when that is clicked on?
 */
    echo print_menurow_index('lookup', 'Word lookup', $corpus_sql_name);
    echo print_menurow_index('freqList', 'Frequency lists', $corpus_sql_name);
    echo print_menurow_index('keywords', 'Keywords', $corpus_sql_name);

    echo print_menurow_heading('User controls');
    echo print_menurow_index('history', 'Query history', $corpus_sql_name);
    echo print_menurow_index('savedQs', 'Saved queries', $corpus_sql_name);
    echo print_menurow_index('categorisedQs', 'Categorised queries', $corpus_sql_name);
    echo print_menurow_index('uploadQ', 'Upload a query', $corpus_sql_name);
    /* TODO: this is only for admin users while under development */
    //if ($User->is_admin())
    echo print_menurow_index('analyseCorpus', 'Analyse corpus', $corpus_sql_name);
    echo print_menurow_index('subcorpus', 'Create/edit subcorpora', $corpus_sql_name);




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
        echo print_menurow_index('userAccess', 'Manage access', $corpus_sql_name);
        echo print_menurow_index('manageMetadata', 'Manage metadata', $corpus_sql_name);
        echo print_menurow_index('manageCategories', 'Manage text categories', $corpus_sql_name);
        echo print_menurow_index('manageAnnotation', 'Manage annotation', $corpus_sql_name);
        echo print_menurow_index('manageVisualisation', 'Manage visualisations', $corpus_sql_name);
        echo print_menurow_index('cachedQueries', 'Cached queries', $corpus_sql_name);
        echo print_menurow_index('cachedDatabases', 'Cached databases', $corpus_sql_name);
        echo print_menurow_index('cachedFrequencyLists', 'Cached frequency lists', $corpus_sql_name);

    } /* end of "if user is a superuser" */




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
            CQPweb (<a href="http://www.gla.ac.uk/schools/critical/research/fundedresearchprojects/samuels/" target="_blank">SAMUELS</a> fork) v<?php echo CQPWEB_VERSION; ?>
        </span>


    <?php
        /* if ($link == 'help') { ?> */
        /*     <span class="footer-item"> */
        /*         <a class="cqpweb_copynote_link" href="help.php" target="_NEW">Corpus and tagset help</a> */
        /*         </span> <?php */
        /* } */

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

	$js_path = '../jsc';

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
