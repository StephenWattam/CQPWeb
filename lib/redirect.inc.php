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



/* This script processes the different commands that can be issued by the 
 * "redirect box" --  little dropdown that contains commands on various pages */

/* NOTE that all the "gets" are still available to the included script! 
 * so the included script just runs as per normal */



// consider using header(Location :) in some cases for a cleaner URL

// and use include include urlfailure to distinguish the two types of problem


if (isset($_GET['redirect']) && isset($_GET['uT']))
{	
	$redirect_script_redirector = $_GET['redirect'];
	unset ($_GET['redirect']);
	
	/* allow for custom plugins in concordance.php, whose redirect could be ANYTHING */
	if (substr($redirect_script_redirector, 0, 11) == 'CustomPost:')
	{
		$custom_pp_parameter = $redirect_script_redirector;
		$redirect_script_redirector = 'customPostprocess';
	}
	
	
	
	switch($redirect_script_redirector)
	{
	
	/* from more than one control box */
	
	case 'newQuery':
		foreach ($_GET as $k=>&$g)
			unset($_GET[$k]);
		$this_script = 'index.php';
		require("../lib/index.inc.php");
		break;
	
	
	/* from control box in concordance.php */
	
	case 'thin':
		require("../lib/thin-control.inc.php");
		break;

	case 'freqList':
	case 'breakdown':
		require("../lib/breakdown.inc.php");
		break;

	case 'distribution':
		require("../lib/distribution.inc.php");
		break;

	case 'sort':
		$_GET['program'] = 'sort';
		$_GET['newPostP'] = 'sort';
		$_GET['newPostP_sortPosition'] = 1;
		$_GET['newPostP_sortThinTag'] = '';
		$_GET['newPostP_sortThinTagInvert'] = 0;
		$_GET['newPostP_sortThinString'] = '';
		$_GET['newPostP_sortThinStringInvert'] = 0;
		unset($_GET['pageNo']);
		require("../lib/concordance.inc.php");
		break;
		
	case 'collocations':
		require("../lib/colloc-options.inc.php");
		break;

	case 'download':
		require("../lib/conc-download.inc.php");
		break;

	case 'categorise':
		if (empty($_GET['categoriseAction']))
			$_GET['categoriseAction'] = 'enterCategories';
		require("../lib/categorise-admin.inc.php");
		break;
		
	case 'saveHits':
		require("../lib/savequery.inc.php");
		break;

	case 'customPostprocess':
		$_GET['newPostP'] = $custom_pp_parameter;
		unset($_GET['pageNo']);
		require("../lib/concordance.inc.php");
		break;


	/* from control box in context.php */
	
	case 'fileInfo':
		require("../lib/textmeta.inc.php");
		break;
		
	case 'moreContext':
		if (isset($_GET['contextSize']))
			$_GET['contextSize'] += 100;
		require("../lib/context.inc.php");
		break;
		
	case 'lessContext':
		if (isset($_GET['contextSize']))
			$_GET['contextSize'] -= 100;
		require("../lib/context.inc.php");
		break;
		
	case 'backFromContext':
		require("../lib/concordance.inc.php");
		break;


	/* from the form with new user settings */
	
	// TODO this is probably not even needed, that form coulds go straight to user-admin....
	case 'newUserSettings':
		require("../lib/user-admin.inc.php");
		break;



	/* from control box in distribution.php */
	
	case 'backFromDistribution':
		require("../lib/concordance.inc.php");
		break;
	
	case 'refreshDistribution':
		require("../lib/distribution.inc.php");
		break;
	
	case 'distributionDownload':
		$_GET['tableDownloadMode'] = 1;
		require("../lib/distribution.inc.php");
		break;
		
		
		
	/* from control box in collocation.php */

	case 'backFromCollocation':
		require("../lib/concordance.inc.php");
		break;

	case 'rerunCollocation':
		require("../lib/collocation.inc.php");
		break;

	case 'collocationDownload':
		$_GET['tableDownloadMode'] = 1;
		require("../lib/collocation.inc.php");
		break;
		


	/* from control box in keywords.php */
	
	case 'newKeywords':
		unset($_GET);
		$_GET['thisQ'] = 'keywords';
		require("../lib/index.inc.php");
		break;

	case 'downloadKeywords':
		$_GET['tableDownloadMode'] = 1;
		require("../lib/keywords.inc.php");
		break;
		
	case 'showAll':
		unset($_GET['redirect']);
		unset($_GET['kwWhatToShow']);
		unset($_GET['pageNo']);
		require('../lib/keywords.inc.php');
		break;
		
	case 'showPos':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'onlyPos';
		unset($_GET['pageNo']);
		require('../lib/keywords.inc.php');
		break;
		
	case 'showNeg':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'onlyNeg';
		unset($_GET['pageNo']);
		require('../lib/keywords.inc.php');
		break;
		
	


	/* from control box in freqlist.php */
	
	case 'newFreqlist':
		unset($_GET);
		$_GET['thisQ'] = 'freqList';
		require("../lib/index.inc.php");
		break;

	case 'downloadFreqList':
		$_GET['tableDownloadMode'] = 1;
		require("../lib/freqlist.inc.php");
		break;



	/* from control box in breakdown.php */
	
	case 'concBreakdownWords':
		$_GET['concBreakdownOf'] = 'words';
		require("../lib/breakdown.inc.php");
		break;

	case 'concBreakdownAnnot':
		$_GET['concBreakdownOf'] = 'annot';
		require("../lib/breakdown.inc.php");
		break;

	case 'concBreakdownBoth':
		$_GET['concBreakdownOf'] = 'both';
		require("../lib/breakdown.inc.php");
		break;

	case 'concBreakdownNodeSort':
		$qname = $_GET['qname'];
		unset($_GET);
		$_GET['qname'] = $qname;
		$_GET['program'] = 'sort';
		$_GET['newPostP'] = 'sort';
		$_GET['newPostP_sortPosition'] = 0;
		$_GET['newPostP_sortThinTag'] = '';
		$_GET['newPostP_sortThinTagInvert'] = 0;
		$_GET['newPostP_sortThinString'] = '';
		$_GET['newPostP_sortThinStringInvert'] = 0;
		$_GET['newPostP_sortThinStringInvert'] = 0;
		$_GET['uT'] = 'y';
		unset($qname);
		require("../lib/concordance.inc.php");
		break;




	/* from wordlookup */
	case 'lookup':
		require('../lib/wordlookup.inc.php');
		break;



	/* from corpus settings page */
	
	case 'adminResetCWBDir':
		$_GET['args'] = $_GET['arg1'] . $_GET['arg2'];
		require('../lib/execute.inc.php');
		break;


	/* special case */
	
	case 'comingSoon':
		require("../lib/library.inc.php");
		coming_soon_page();
		break;







	/* the remainder of the script deals with malformed URLS */
	
	default:
		?>
		<html>
		<head><title>Error!</title></head>
		<body>
			<pre>
			
			ERROR: Redirect type unrecognised.
			
			<a href="index.php">Please reload CQPweb</a>.
			</pre>
		</body>
		</html>
		<?php
		break;
	}
	/* end of switch */
	/* (is end of script) */
}
else
{
	?>
	<html>
	<head><title>Error!</title></head>
	<body>
		<pre>
		
			ERROR: Incorrectly formatted URL, or no redirect parameter provided.
			
			<a href="index.php">Please reload CQPweb</a>.
		</pre>
	</body>
	</html>
	<?php
}
?>