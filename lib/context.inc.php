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




/* context.inc.php */

/* this file contains the code for showing extended context for a single result */


/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */

/* before anything else */
header('Content-Type: text/html; charset=utf-8');


/* initialise variables from settings files  */

require("settings.inc.php");
require("../lib/defaults.inc.php");


/* include function library files */
include ("../lib/library.inc.php");
include ("../lib/concordance-lib.inc.php");
include ("../lib/metadata.inc.php");
include ("../lib/exiterror.inc.php");


/* and because I'm using the next two modules I need to... */
create_pipe_handle_constants();
include ("../lib/cwb.inc.php");
include ("../lib/cqp.inc.php");


if (!url_string_is_valid())
	exiterror_bad_url();




?>


<html>
<head>
<?php
echo '<title>' . $corpus_title . ' -- CQPweb showing extra context</title>';
echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

</head>
<body>

<?php




/* ------------------------------- */
/* initialise variables from $_GET */
/* and perform initial fiddling    */
/* ------------------------------- */



/* this script takes all of the GET parameters from concrdance.php */
/* but only qname is absolutely critical, the rest just get passed */
if (isset($_GET['qname']))
	$qname = $_GET['qname'];
else
	exit('<p class="errormessage">Critical parameter "qname" was not defined!</p></body></html>');
	
/* all scripts that pass on $_GET['theData'] have to do this, to stop arg passing adding slashes */
if (isset($_GET['theData']))
	$_GET['theData'] = prepare_query_string($_GET['theData']);


/* parameters unique to this script */

if (isset($_GET['batch']))
	$batch = $_GET['batch'];
else
	exit('<p class="errormessage">Critical parameter "batch" was not defined!</p></body></html>');

if (isset($_GET['tagshow']))
	$show_tags = $_GET['tagshow'];
else
	$show_tags = 0;

switch ($show_tags)
{
case 1:
	$show_tags = true;
	$reverseTag = "0";
	$reverseTagButtonText = 'Hide tags';
	break;

default:
	$show_tags = false;
	$reverseTag = "1";
	$reverseTagButtonText = 'Show tags';
	break;
}


if (isset($_GET['contextSize']))
	$context_size = $_GET['contextSize'];
else
	$context_size = $default_extended_context;





/* connect to mySQL and set up for UTF-8 */

$mysql_link = mysql_connect($mysql_server, $mysql_webuser, $mysql_webpass);

if (! $mysql_link)
{
	?>
	<p class="errormessage">
		mySQL did not connect - please try again later!
	</p></body></html> 
	<?php
	exit(1);
}

mysql_select_db($mysql_schema, $mysql_link);

/* utf-8 setting is dependent on a variable defined in settings.inc.php */
if ($utf8_set_required)
	mysql_query("SET NAMES utf8", $mysql_link);





/* connect to CQP */
$cqp = new CQP;

/* select an error handling function */
$cqp->set_error_handler("cqp_error_handler");
/* the other option is cqp_error_handler_full */

/* set CQP's temporary directory */
$cqp->execute("set DataDirectory '/$cqp_tempdir'");

/* select corpus */
$cqp->execute("$corpus_cqp_name");
/* note that corpus must be RESELECTED after calling "set DataDirectory" */

$primary_tag_handle = get_corpus_metadata('primary_annotation');

$cqp->execute("set Context $context_size words");
$cqp->execute("show +word +$primary_tag_handle");
$cqp->execute("set PrintStructures \"text_id\""); 
$cqp->execute("set LeftKWICDelim '--%%%--'");
$cqp->execute("set RightKWICDelim '--%%%--'");


/* get an array containing the lines of the query to show this time */
$kwic = $cqp->execute("cat $qname $batch $batch");




/* process the single result -- code largely filched from print_concordance_line() */

/* extract the text_id and delete that first bit of the line */
preg_match("/\A\s*\d+: <text_id (\w+)>:/", $kwic[0], $m);
$text_id = $m[1];
$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+>:/", '', $kwic[0]);

/* divide up the CQP line */
list($kwic_lc, $kwic_match, $kwic_rc) = preg_split("/--%%%--/", $cqp_line);	

/* just in case of unwanted spaces (there will deffo be some on the left) ... */
$kwic_rc = trim($kwic_rc);
$kwic_lc = trim($kwic_lc);
$kwic_match = trim($kwic_match);

/* create arrays of words from the incoming variables: split at space */	
$lc = split(' ', $kwic_lc);
$rc = split(' ', $kwic_rc);
$node = split(' ', $kwic_match);

/* how many words in each array? */
$lcCount = count($lc);
$rcCount = count($rc);
$nodeCount = count($node);


/* left context string */
$lc_string = "";
for ($i = 0; $i < $lcCount; $i++) 
{
	/* forward slash can be part of a word, but not part of a tag */
	preg_match('/\A(.*)\/(.*?)\Z/', cqpweb_htmlspecialchars($lc[$i]), $m);
	$word = $m[1];
	$tag = $m[2];
	
	if ($i == 0 && preg_match('/\A[.,;:?\-!"\x{0964}]\Z/u', $word))
		/* don't show the first word of left context if it's just punctuation */
		continue;

	$word = cqpweb_htmlspecialchars($word);

	
	if ($show_tags)
		$lc_string .= $word . '_' . $tag . ' ';
	else
		$lc_string .= $word . ' ';

	/* break line if this word is an end of sentence punctuation */
	if (preg_match('/\A[.?!\x{0964}]\Z/u', $word) || $word == '...'  )
		$lc_string .= '<br/>&nbsp;<br/>
			';
}
	
/* node string */
$node_string = "";
for ($i = 0; $i < $nodeCount; $i++) 
{
	/* forward slash can be part of a word, but not part of a tag */
	preg_match('/\A(.*)\/(.*?)\Z/', cqpweb_htmlspecialchars($node[$i]), $m);
	$word = $m[1];
	$tag = $m[2];
	
	if ($show_tags)
		$node_string .= $word . '_' . $tag . ' ';
	else
		$node_string .= $word . ' ';

	/* break line if this word is an end of sentence punctuation */
	if (preg_match('/\A[.?!\x{0964}]\Z/u', $word) || $word == '...'  )
		$node_string .= '<br/>&nbsp;<br/>
			';
}

/* rc string */
$rc_string = "";
for ($i = 0; $i < $rcCount; $i++) 
{
	/* forward slash can be part of a word, but not part of a tag */
	preg_match('/\A(.*)\/(.*?)\Z/', cqpweb_htmlspecialchars($rc[$i]), $m);
	$word = $m[1];
	$tag = $m[2];
	
	if ($show_tags)
		$rc_string .= $word . '_' . $tag . ' ';
	else
		$rc_string .= $word . ' ';

	/* break line if this word is an end of sentence punctuation */
	// this is a BAD regex. Hopefully, PHP 6 will have some function that does it for me.
// potentially better version? but might be slow (see PHP PCRE manual)
//	if (preg_match('/\A\p{P}\Z/u', $word) || $word == '...'  )
	if (preg_match('/\A[.?!\x{0964}]\Z/u', $word) || $word == '...'  )
		$rc_string .= '<br/>&nbsp;<br/>
			';
}



/* print everything */


?>
<table class="concordtable" width="100%">
	<tr>
		<th colspan="2" class="concordtable">
			Displaying extended context for query match in text <i><?php echo $text_id; ?></i>
		</th>
	</tr>
	<tr>
		<form action="redirect.php" method="get">
			<td width="50%" align="center" class="concordgrey">
				<select name="redirect">
					<option value="fileInfo" selected="selected">
						File info for text <?php echo $text_id; ?>
					</option>
					<?php 
					if ($context_size < $default_max_context)
						echo '<option value="moreContext">More context</option>';
					if ($context_size > $default_extended_context)
						echo '<option value="lessContext">Less context</option>';
					?>
					<option value="backFromContext">Back to main query result</option>
					<option value="newQuery">New query</option>
				</select>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="submit" value="Go!" />
			</td>
		<input type="hidden" name="text" value="<?php echo $text_id; ?>" />
		<?php echo url_printinputs(array(
			array('text', ""), 
			array('contextSize', "$context_size"),
			array('redirect', "")
			)); ?>
		</form>
		
		
		
		<form action="context.php" method="get">
			<td width="50%" align="center" class="concordgrey">
				&nbsp;
				<input type="submit" value="<?php echo $reverseTagButtonText; ?>" />
				&nbsp;
			</td>
		<input type="hidden" name="tagshow" value="<?php echo $reverseTag; ?>" />
		<?php echo url_printinputs(array(array('tagshow', ""))); ?>
		</form>
		
		
	</tr>
	<tr>
		<td colspan="2" class="concordgeneral">

		<p class="query-match-context">
		<?php echo $lc_string . '<b>' . $node_string . '</b>' . $rc_string ; ?>
		</p>
	</tr>
</table>

<?php




/* create page end HTML */
print_footer();

/* disconnect CQP child process using destructor function */
$cqp->disconnect();

/* disconnect mysql */
mysql_close($mysql_link);


/* ------------- */
/* END OF SCRIPT */
/* ------------- */
?>