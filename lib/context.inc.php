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




/* context.inc.php */

/** @file this file contains the code for showing extended context for a single result */


/* ------------ */
/* BEGIN SCRIPT */
/* ------------ */



/* initialise variables from settings files  */
require('../lib/environment.inc.php');

/* include function library files */
require('../lib/library.inc.php');
require('../lib/html-lib.inc.php');
require('../lib/exiterror.inc.php');
require('../lib/concordance-lib.inc.php');
require('../lib/metadata.inc.php');
require('../lib/cwb.inc.php');
require('../lib/cqp.inc.php');


cqpweb_startup_environment();



/* ------------------------------- */
/* initialise variables from $_GET */
/* and perform initial fiddling    */
/* ------------------------------- */



/* this script takes all of the GET parameters from concrdance.php */
/* but only qname is absolutely critical, the rest just get passed */
$qname = safe_qname_from_get();
	
/* all scripts that pass on $_GET['theData'] have to do this, to stop arg passing adding slashes */
if (isset($_GET['theData']))
	$_GET['theData'] = prepare_query_string($_GET['theData']);


/* parameters unique to this script */

if (isset($_GET['batch']))
	$batch = (int)$_GET['batch'];
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
	$context_size = (int)$_GET['contextSize'];
else
	$context_size = $initial_extended_context;

/* restrict possible values */
if ($context_size > $max_extended_context)
	$context_size = $max_extended_context;
if ($context_size < $initial_extended_context)
	$context_size = $initial_extended_context;




/* before anything else */
header('Content-Type: text/html; charset=utf-8');

?>


<html>
<head>
	<?php
	echo '<title>' . $corpus_title . ' -- CQPweb showing extra context</title>';
	echo '<link rel="stylesheet" type="text/css" href="' . $css_path . '" />';
	?>
	<script type="text/javascript" src="../lib/javascript/cqpweb-clientside.js"></script> 
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

</head>
<body>

<?php





$primary_tag_handle = get_corpus_metadata('primary_annotation');

$cqp->execute("set Context $context_size words");
if ($visualise_gloss_in_context)
	$cqp->execute("show +word +$visualise_gloss_annotation ");
else
	$cqp->execute('show +word ' . (empty($primary_tag_handle) ? '' : "+$primary_tag_handle "));
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

//$word_extraction_pattern = (empty($primary_tag_handle) ? false : '/\A(.*)\/(.*?)\z/');

$line_breaker = ($corpus_main_script_is_r2l 
							? "</bdo>\n<br/>&nbsp;<br/>\n<bdo dir=\"rtl\">" 
							: '<br/>&nbsp;<br/>
				');

/* left context string */
$lc_string = '';
for ($i = 0; $i < $lcCount; $i++) 
{
	list($word, $tag) = extract_cqp_word_and_tag($lc[$i]);

	if ($i == 0 && preg_match('/\A[.,;:?\-!"\x{0964}\x{0965}]\Z/u', $word))
		/* don't show the first word of left context if it's just punctuation */
		continue;

	$lc_string .= $word . ( $show_tags ? bdo_tags_on_tag($tag) : '' ) . ' ';


	/* break line if this word is an end of sentence punctuation */
	if (preg_match('/\A[.?!\x{0964}]\Z/u', $word) || $word == '...'  )
		$lc_string .= $line_breaker;
}
	
/* node string */
$node_string = '';
for ($i = 0; $i < $nodeCount; $i++) 
{
	list($word, $tag) = extract_cqp_word_and_tag($node[$i]);

	$node_string .= $word . ( $show_tags ? bdo_tags_on_tag($tag) : '' ) . ' ';

	/* break line if this word is an end of sentence punctuation */
	if (preg_match('/\A[.?!\x{0964}]\Z/u', $word) || $word == '...'  )
		$node_string .= $line_breaker;
}

/* rc string */
$rc_string = "";
for ($i = 0; $i < $rcCount; $i++) 
{
	list($word, $tag) = extract_cqp_word_and_tag($rc[$i]);
	
	$rc_string .= $word . ( $show_tags ? bdo_tags_on_tag($tag) : '' ) . ' ';

	/* break line if this word is an end of sentence punctuation */
	// TODO
	// this is a BAD regex.
	// potentially better version? need to test it, though
	//	if (preg_match('/\A\p{P}\Z/u', $word) || $word == '...'  )
	if (preg_match('/\A[.?!\x{0964}]\Z/u', $word) || $word == '...'  )
		$rc_string .= $line_breaker;
}

/* tags for Arabic, etc.: */
$bdo_tag1 = ($corpus_main_script_is_r2l ? '<bdo dir="rtl">' : '');
$bdo_tag2 = ($corpus_main_script_is_r2l ? '</bdo>' : '');



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
					if ($context_size < $max_extended_context)
						echo '<option value="moreContext">More context</option>';
					if ($context_size > $initial_extended_context)
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
		<p class="query-match-context" align="<?php echo ($corpus_main_script_is_r2l ? 'right' : 'left'); ?>">
		<?php echo $bdo_tag1 . $lc_string . '<b>' . $node_string . '</b>' . $rc_string . $bdo_tag2; ?>
		</p>
	</tr>
	
</table>

<?php



echo print_html_footer();

cqpweb_shutdown_environment();



/* ------------- */
/* END OF SCRIPT */
/* ------------- */

/* Function that puts tags back into ltr order... */

function bdo_tags_on_tag($tag)
{
	return '_<bdo dir="ltr">' . substr($tag, 1) . '</bdo>';
}
?>
