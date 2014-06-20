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






/* initialise variables from settings files  */
require('../lib/environment.inc.php');


/* include function library files */
require('../lib/library.inc.php');
require('../lib/user-lib.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/metadata.inc.php');


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);




/* initialise variables from $_GET */

if (empty($_GET["text"]) )
	exiterror_general("No text was specified for metadata-view! Please reload CQPweb.");
else 
	$text_id = cqpweb_handle_enforce($_GET["text"]);
	

$result = do_mysql_query("SELECT * from text_metadata_for_$corpus_sql_name where text_id = '$text_id'");

if (mysql_num_rows($result) < 1)
	exiterror_general("The database doesn't appear to contain any metadata for text $text_id.");

$metadata = mysql_fetch_row($result);


/*
 * Render!
 */

echo print_html_header($corpus_title . ': viewing text metadata -- CQPweb', $Config->css_path);

?>

<table class="concordtable" width="100%">
	<tr>
		<th colspan="2" class="concordtable">Metadata for text <em><?php echo $text_id; ?></em></th>
	</tr>

<?php

$n = count($metadata);

for ( $i = 0 ; $i < $n ; $i++ )
{
	$att = metadata_expand_attribute(mysql_field_name($result, $i), $metadata[$i]);
	
	/* this expansion is hardwired */
	if ($att['field'] == 'text_id')
		$att['field'] =  'Text identification label';
	/* this expansion is hardwired */
	if ($att['field'] == 'words')
		$att['field'] =  'No. words in text';
	/* don't show the CQP delimiters for the file */
	if ($att['field'] == 'cqp_begin' || $att['field'] == 'cqp_end')
		continue;

	/* don't allow empty cells */
	if ($att['value'] == '')
		$att['value'] = '&nbsp;';
	/* if the value is a URL, convert it to a link */
	if ( 0 < preg_match('#^(https?|ftp)://#', $att['value']) )
	{
		/* pipe is used as a delimiter between URL and linktext to show. */
		if (false !== strpos($att['value'], '|'))
		{
			list($url, $linktext) = explode('|', $att['value']);
			$att['value'] = '<a target="_blank" href="'.$url.'">'.$linktext.'</a>';
		}
		else
			$att['value'] = '<a target="_blank" href="'.$att['value'].'">'.$att['value'].'</a>';
	}
	
	echo '<tr><td class="concordgrey">' . $att['field']
		. '</td><td class="concordgeneral">' . $att['value'] . '</td></tr>
		';
		
	unset($att);
}



echo '</table>';

echo print_html_footer();

cqpweb_shutdown_environment();

?>
