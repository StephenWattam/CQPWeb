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




/* each of these functions prints a table for the right-hand side interface */



function printquery_history()
{
	global $User;
	global $default_history_per_page;
	global $corpus_sql_name;
	
	if (isset($_GET['historyView']))
		$view = $_GET['historyView'];
	else
		$view = ( (boolean)get_user_setting($User->username, 'cqp_syntax') ? 'cqp' : 'simple');
	

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	/* variable for superuser usage */
	if (isset($_GET['showUser']) && $User->is_admin())
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $User->username;


	/* create sql query and set options */
	switch ($user_to_show)
	{
	case '__ALL':
		$sql_query = "select instance_name, date_of_query, cqp_query, restrictions, subcorpus, simple_query, query_mode, hits,
			user from query_history where corpus = '$corpus_sql_name' order by date_of_query DESC";
		$column_count = 6;
		$usercolumn = true;
		$current_string = 'Currently showing history for all users';
		break;
	
	case '__SYNERR':
		/* I have forgotten why column_count is so high here - you see, it is not used if an admin user is plugged in */
		$column_count = 9;
		$sql_query = "select * from query_history where corpus = '$corpus_sql_name' and hits = -1 order by date_of_query DESC";
		$usercolumn = true;
		$current_string = 'Currently showing history of queries with a syntax error';
		break;
		
	case '__RUNNING':
		$sql_query = "select * from query_history where corpus = '$corpus_sql_name' and hits = -3 order by date_of_query DESC";
		$column_count = 9;
		$usercolumn = true;
		$current_string = 'Currently showing history of incompletely-run queries';
		break;
	
	default:
		$sql_query = "select instance_name, date_of_query, restrictions, subcorpus, cqp_query, simple_query, query_mode, hits
			from query_history where corpus = '$corpus_sql_name' and user = '$user_to_show' order by date_of_query DESC";
		$column_count = 5;
		$usercolumn = false;
		$current_string = "Currently showing history for user <b>&ldquo;$user_to_show&rdquo;</b>";
		break;
	}


	$result = do_mysql_query($sql_query);
	
	$linkChangeView = "&nbsp;&nbsp;&nbsp;&nbsp;(<a href=\"index.php?"
		. url_printget(array(array('historyView', ( ($view == 'simple') ? 'cqp' : 'simple' )))) 
		. '">Show ' . ( ($view == 'simple') ? 'in CQP syntax' : 'as Simple Query' ) . '</a>)';
	


	if ($User->is_admin())
	{
		/* there will be a delete column */
		$delete_lines = true;
		$column_count++;
	
		/* version giving superuser access to everything */
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Query history: admin controls</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<form action="index.php" method="get">
					<input type="hidden" name="thisQ" value="history"/>
					<input type="hidden" name="historyView" value="<?php echo $view;?>"/>
					<table>
						<tr>
							<td class="basicbox">Select a user...</td>
							<td class="basicbox">
							<select name="showUser">
								<option value="__ALL" selected="selected">Show all users' history</option>
								<option value="__RUNNING">Show incompletely-run queries</option>
								<option value="__SYNERR">Show queries with a syntax error</option>
					<?php
					
					$temp_sql_query = "SELECT distinct(user) FROM query_history  
										where corpus = '$corpus_sql_name' order by user";
					$temp_result = do_mysql_query($temp_sql_query);
				
					while ($r = mysql_fetch_row($temp_result))
						echo '<option value="' . $r[0] . '">' . $r[0] . '</option>';
					unset($temp_result);
					
					?>
							</select>
							</td>
							<td class="basicbox"><input type="submit" value="Show history"/></td>
						</tr>	
						<tr>
							<td class="basicbox">Number of records per page</td>
							<td class="basicbox">	
								<select name="pp">
									<option value="10"   <?php if ($per_page == 10)   echo 'selected="selected"'; ?>>10</option>
									<option value="50"   <?php if ($per_page == 50)   echo 'selected="selected"'; ?>>50</option>
									<option value="100"  <?php if ($per_page == 100)  echo 'selected="selected"'; ?>>100</option>
									<option value="250"  <?php if ($per_page == 250)  echo 'selected="selected"'; ?>>250</option>
									<option value="350"  <?php if ($per_page == 350)  echo 'selected="selected"'; ?>>350</option>
									<option value="500"  <?php if ($per_page == 500)  echo 'selected="selected"'; ?>>500</option>
									<option value="1000" <?php if ($per_page == 1000) echo 'selected="selected"'; ?>>1000</option>
								</select>
							</td>
							<td></td>
						</tr>
						</tr>
							<td colspan="3" class="basicbox">
								<?php echo "<p>$current_string.</p>"; ?>
							</td>
						</tr>
					</table>
					<!-- this input ALWAYS comes last -->
					<input type="hidden" name="uT" value="y"/>
					</form>
					
				</td>
			</tr>
		</table>
		<table class="concordtable" width="100%">
		<?php
	}
	else
	{
		$delete_lines = false;
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="<?php echo $column_count; ?>" class="concordtable">Query history</th>
			</tr>
		<?php	
	}
	
	
	
	?>
		<tr>
			<th class="concordtable">No.</th>
			<?php if ($usercolumn) echo '<th class="concordtable">User</th>'; ?>
			<th class="concordtable">Query <?php echo $linkChangeView; ?></th>
			<th class="concordtable">Restriction</th>
			<th class="concordtable">Hits</th>
			<th class="concordtable">Date</th>
			<?php if ($delete_lines) echo '<th class="concordtable">Delete</th>'; ?>
			
		</tr>

	<?php




	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysql_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		$row = mysql_fetch_assoc($result);
		if (!$row)
			break;
		if ($i < $begin_at)
			continue;
		
		echo "<tr>\n<td class='concordgeneral' align='center'>$i</td>";
		if ($usercolumn)
			echo "<td class='concordgeneral' align='center'>" . $row['user'] . '</td>';
		
		if ( $view == 'simple' && $row['simple_query'] != "" )
			echo '<td class="concordgeneral"><a href="index.php?thisQ=search&insertString=' 
				. urlencode($row['simple_query']) . '&insertType=' . $row['query_mode'] . '&uT=y"'
				. ' onmouseover="return escape(\'Insert query string into query window\')">' 
				. htmlspecialchars($row['simple_query']) . '</a>'
				. ($row['query_mode'] == 'sq_case' ? " (case sensitive)" : "") . '</td>';
		else
			echo '<td class="concordgeneral"><a href="index.php?thisQ=search&insertString=' 
				. urlencode($row['cqp_query']) . '&insertType=' 
				. ( $view == 'simple' ? $row['query_mode'] : 'cqp' ) . '&uT=y"'
				. ' onmouseover="return escape(\'Insert query string into query window\')">' 
				. htmlspecialchars($row['cqp_query']) . '</a></td>';

		if ($row['subcorpus'] != 'no_subcorpus')

			echo '<td class="concordgeneral">Subcorpus:<br/><a href="index.php?thisQ=search&insertString='
				. urlencode(($view == 'simple' && $row['simple_query'] != "") ? $row['simple_query'] : $row['cqp_query']) 
				. '&insertType=' . ( $view == 'simple' ? $row['query_mode'] : 'cqp' ) 
				. '&insertSubcorpus=' . $row['subcorpus'] . '&uT=y"'
				. ' onmouseover="return escape(\'Insert query string and textual restrictions into query window\')">'
				. $row['subcorpus']
				. '</a></td>';
		else if ($row['restrictions'] != 'no_restriction')
			echo '<td class="concordgeneral"><a href="index.php?thisQ=restrict&insertString='
				. urlencode(($view == 'simple' && $row['simple_query'] != "") ? $row['simple_query'] : $row['cqp_query']) 
				. '&insertType=' . ( $view == 'simple' ? $row['query_mode'] : 'cqp' ) 
				. '&insertRestrictions=' . urlencode($row['restrictions']) . '&uT=y"'
				. ' onmouseover="return escape(\'Insert query string and textual restrictions into query window\')">'
				. 'Textual restrictions</a>:<br/>' 
				. str_replace('; ', '; <br/>', translate_restrictions_to_prose($row['restrictions']))
				. '</td>';
		else
			echo '<td class="concordgeneral">-</td>';	

		
		switch($row['hits'])
		{
		/* maybe add links to explanations? (-3 and -1) */
		case -3:
			echo "<td class='concordgeneral' align='center'><a href=\"concordance.php?"
				. "theData=" . urlencode($row['cqp_query']) 
				. "&simpleQuery=" . urlencode($row['simple_query'])
				. "&qmode=cqp&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
				. "Run error</a></td>";
				break;
		case -1:
			echo "<td class='concordgeneral' align='center'>Syntax error</td>";
			break;
		default:
			if ($row['subcorpus'] != 'no_subcorpus')
				echo "<td class='concordgeneral' align='center'><a href=\"concordance.php?"
					. "theData=" . urlencode($row['cqp_query']) 
					. "&del=begin&t=subcorpus~{$row['subcorpus']}&del=end"
					. "&simpleQuery=" . urlencode($row['simple_query'])
					. "&qmode=cqp&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
					. $row['hits'] . "</a></td>";
			else if ($row['restrictions'] != 'no_restriction')
				echo "<td class='concordgeneral' align='center'><a href=\"concordance.php?"
					. "theData=" . urlencode($row['cqp_query']) 
					. "&simpleQuery=" . urlencode($row['simple_query'])
					. '&' . untranslate_restrictions_definition_string($row['restrictions'])
					. "&qmode=cqp&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
					. $row['hits'] . "</a></td>";
			else
				echo "<td class='concordgeneral' align='center'><a href=\"concordance.php?"
					. "theData=" . urlencode($row['cqp_query']) 
					. "&simpleQuery=" . urlencode($row['simple_query'])
					. "&qmode=cqp&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
					. $row['hits'] . "</a></td>";
			break;
		}
		echo "<td class='concordgeneral' align='center'>" . $row['date_of_query'] . "</td>";
		
		if ($delete_lines)
		{
			echo '<td class="concordgeneral" align="center"><a class="menuItem" href="execute.php'
				. '?function=history_delete&args=' . urlencode($row['instance_name'])
				. '&locationAfter=' . urlencode( 'index.php?' . url_printget() ) . '&uT=y" '
				. 'onmouseover="return escape(\'Delete history item\')">[x]</a></td>';
		}
		echo "\n</tr>\n";
	}
	
	echo '</table>';

	$navlinks = '<table class="concordtable" width="100%"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Newer queries]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysql_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older queries] &gt;&gt;';
	if (mysql_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;

}









function printquery_catqueries()
{
	global $User;
	global $corpus_sql_name;
	global $default_history_per_page;
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">Categorised queries</th>
		</tr>
	</table>
	<?php


	/* variable for superuser usage */
	if (isset($_GET['showUser']) && $User-is_admin())
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $User->username;


	if ($user_to_show == '__ALL')
		$current_string = 'Currently showing history for all users';
	else
		$current_string = "Currently showing history for user <b>&ldquo;$user_to_show&rdquo;</b>";
	
	$usercolumn = (($user_to_show == '__ALL') && $User->is_admin());

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	/* form for admin controls */
	if ($User->is_admin())
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Categorised queries: admin controls</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<form action="index.php" method="get">
					<input type="hidden" name="thisQ" value="categorisedQs"/>
					<table>
						<tr>
							<td class="basicbox">Select a user...</td>
							<td class="basicbox">
							<select name="showUser">
								<option value="__ALL" selected="selected">all users</option>
					<?php
					
					$temp_sql_query = "SELECT distinct(user) FROM saved_catqueries 
											where corpus = '$corpus_sql_name' order by user";
					$temp_result = do_mysql_query($temp_sql_query);

					while ($r = mysql_fetch_row($temp_result))
						echo '<option value="' . $r[0] . '">' . $r[0] . '</option>';
					unset($temp_result);
					
					?>
							</select>
							</td>
							<td class="basicbox"><input type="submit" value="Show history"/></td>
						</tr>	
						<tr>
							<td class="basicbox">Number of records per page</td>
							<td class="basicbox">	
								<select name="pp">
									<option value="10"   <?php if ($per_page == 10)   echo 'selected="selected"'; ?>>10</option>
									<option value="50"   <?php if ($per_page == 50)   echo 'selected="selected"'; ?>>50</option>
									<option value="100"  <?php if ($per_page == 100)  echo 'selected="selected"'; ?>>100</option>
									<option value="250"  <?php if ($per_page == 250)  echo 'selected="selected"'; ?>>250</option>
									<option value="350"  <?php if ($per_page == 350)  echo 'selected="selected"'; ?>>350</option>
									<option value="500"  <?php if ($per_page == 500)  echo 'selected="selected"'; ?>>500</option>
									<option value="1000" <?php if ($per_page == 1000) echo 'selected="selected"'; ?>>1000</option>
								</select>
							</td>
							<td></td>
						</tr>
						</tr>
							<td colspan="3" class="basicbox">
								<?php echo "<p>$current_string.</p>"; ?>
							</td>
						</tr>
					</table>
					<!-- this input ALWAYS comes last -->
					<input type="hidden" name="uT" value="y"/>
					</form>
					
				</td>
			</tr>
		</table>
		<table class="concordtable" width="100%">
		<?php
	}
	else
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="1" class="concordtable">Categorised queries</th>
			</tr>
		</table>
		<?php	
	}
	
	
	/* now it's time to look up the categorised queries */

	
	/* 
	 * the saved_catqueries table does not contain the actual info, for that we need to look up the savename etc. 
	 * from the main query cache
	 */
	$user_clause = ($usercolumn ? '' : " user='$user_to_show' and ");
	$result = do_mysql_query("select catquery_name, category_list, dbname from saved_catqueries 
								where $user_clause corpus='$corpus_sql_name'");

	$catqueries_to_show = array();

	for ( $i = 1 ; true ; $i++ )
	{
		/* note, this loop includes some hefty mysql-ing 
		 * BUT it is not expected that the number of
		 * entries in the saved_catqueries table will be large
		 */ 
		if ( ($row = mysql_fetch_row($result)) === false)
			break;
		/* so we don't have to run the SQL query below unless 'tis needed */
		if ($i < $begin_at)
			continue;

		/* find out how many rows have been assigned a value */
		$inner_result = do_mysql_query("select count(*) from {$row[2]} where category is not NULL");
		list($n) = mysql_fetch_row($inner_result);
		
		/* assemble the info for this categorised query line */
		$catqueries_to_show[$i] = array(
			'qname' => $row[0],
			'catlist' => explode('|', $row[1]),
			'query_record' => check_cache_qname($row[0]),
			'number_categorised' => $n
			);
		$catqueries_to_show[$i]['number_of_hits'] 
			= (empty($catqueries_to_show[$i]['query_record']['hits_left']) ? $catqueries_to_show[$i]['query_record']['hits'] : array_pop(explode('~', $catqueries_to_show[$i]['query_record']['hits_left'])));
	}


	/* set this up as a variable, so it doesn't have to be used every time */
	$action_form_begin = 
		'<form action="redirect.php" method="get">
			<td class="concordgeneral">
				<select name="categoriseAction">
					<option value="enterNewValue">Add categories</option>
					<option value="separateQuery" selected="selected">Separate categories</option>
					<option value="deleteCategorisedQuery">Delete complete set</option>
				</select>
				<input type="submit" value="Go" />
			</td>
			<input type="hidden" name="redirect" value="categorise"/>
			<input type="hidden" name="qname" value="';
	
	$action_form_end = '"/>
			<input type="hidden" name="uT" value="y"/>
		</form>
		';
	/* so we simply echo $action_form_begin . $catqueries_to_show[$i]['qname'] . $action_form_end */


	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">No.</th>
			<?php if ($usercolumn) echo '<th class="concordtable">User</th>'; ?>
			<th class="concordtable">Name of set</th>
			<th class="concordtable">Categories</th>
			<th class="concordtable">No. of hits</th>
			<th class="concordtable">Categorised</th>
			<th class="concordtable">Date</th>
			<th class="concordtable">Action</th>
		</tr>

	<?php

	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysql_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		if (!isset($catqueries_to_show[$i]))
			break;

		/* no. */
		echo "<tr>\n<td class='concordgeneral' align='center'>$i</td>";
		
		/* user */
		if ($usercolumn)
			echo "<td class='concordgeneral' align='center'>" 
				. $catqueries_to_show[$i]['query_record']['user'] 
				. '</td>';
		
		/* Name of set */
		if (!empty($catqueries_to_show[$i]['query_record']['save_name']))
			$print_name = $catqueries_to_show[$i]['query_record']['save_name'];
		else
			$print_name = $catqueries_to_show[$i]['qname'];
		
		echo '<td class="concordgeneral"><a href="concordance.php?program=categorise&qname='
			. $catqueries_to_show[$i]['qname'] 
			. '&uT=y" onmouseover="return escape(\'View or amend category assignments\')">'
			. $print_name . '</a></td>';

		/* categories */
		echo '<td class="concordgeneral" align="center">' . implode(', ', $catqueries_to_show[$i]['catlist'])
			. '</td>';
		
		/* number of hits */
		echo '<td class="concordgeneral" align="center">' . $catqueries_to_show[$i]['number_of_hits'] 
			. '</td>';
		
		/* number and % of hits categorised */
		echo '<td class="concordgeneral" align="center"><center>' . $catqueries_to_show[$i]['number_categorised'] 
			. ' ('
			. round(100*$catqueries_to_show[$i]['number_categorised']/$catqueries_to_show[$i]['number_of_hits'], 0)
			. '%)</td>';
		
		/* date of saving */
		echo '<td class="concordgeneral" align="center">' . $catqueries_to_show[$i]['query_record']['date_of_saving'] 
			. '</td>';
		
		/* actions */
		echo $action_form_begin . $catqueries_to_show[$i]['qname'] . $action_form_end;
		
		echo '</tr>';

	}
	
	echo '</table>';

	$navlinks = '<table class="concordtable" width="100%"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Newer categorised queries]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysql_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older categorised queries] &gt;&gt;';
	if (mysql_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;

}





function printquery_savedqueries()
{
	global $User;
	
	global $default_history_per_page;
	global $corpus_sql_name;


	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	if (isset($_GET['showUser']) && $User->is_admin)
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $User->username;

	if ($user_to_show == '__ALL')
	{
		$current_string = 'Currently showing history for all users';
	}
	else
	{
		$current_string = "Currently showing history for user <b>&ldquo;$user_to_show&rdquo;</b>";
	}



	/* form for admin controls */
	if ($User->is_admin)
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable">Saved queries: admin controls</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<form action="index.php" method="get">
					<input type="hidden" name="thisQ" value="savedQs"/>
					<table>
						<tr>
							<td class="basicbox">Select a user...</td>
							<td class="basicbox">
							<select name="showUser">
								<option value="__ALL" selected="selected">all users</option>
					<?php
					
					$temp_sql_query = "SELECT distinct(user) FROM saved_queries where saved = 1 
										and corpus = '$corpus_sql_name' order by user";
					$temp_result = do_mysql_query($temp_sql_query);
				

					while (($r = mysql_fetch_row($temp_result)) !== false)
						echo '<option value="' . $r[0] . '">' . $r[0] . '</option>';
					unset($temp_result);
					
					?>
							</select>
							</td>
							<td class="basicbox"><input type="submit" value="Show history"/></td>
						</tr>	
						<tr>
							<td class="basicbox">Number of records per page</td>
							<td class="basicbox">	
								<select name="pp">
									<option value="10"   <?php if ($per_page == 10)   echo 'selected="selected"'; ?>>10</option>
									<option value="50"   <?php if ($per_page == 50)   echo 'selected="selected"'; ?>>50</option>
									<option value="100"  <?php if ($per_page == 100)  echo 'selected="selected"'; ?>>100</option>
									<option value="250"  <?php if ($per_page == 250)  echo 'selected="selected"'; ?>>250</option>
									<option value="350"  <?php if ($per_page == 350)  echo 'selected="selected"'; ?>>350</option>
									<option value="500"  <?php if ($per_page == 500)  echo 'selected="selected"'; ?>>500</option>
									<option value="1000" <?php if ($per_page == 1000) echo 'selected="selected"'; ?>>1000</option>
								</select>
							</td>
							<td></td>
						</tr>
						</tr>
							<td colspan="3" class="basicbox">
								<?php echo "<p>$current_string.</p>"; ?>
							</td>
						</tr>
					</table>
					<!-- this input ALWAYS comes last -->
					<input type="hidden" name="uT" value="y"/>
					</form>
					
				</td>
			</tr>
		</table>
		<table class="concordtable" width="100%">
		<?php
	}
	else
	{
		?>
		<table class="concordtable" width="100%">
			<tr>
				<th colspan="1" class="concordtable">Saved queries</th>
			</tr>
		</table>
		<?php	
	}




	echo print_cache_table($begin_at, $per_page, $user_to_show, false, false);
}



// TODO move this function?
function printquery_showmatrix()
{
	global $corpus_sql_name;
	global $User;
	
	/* note that this function is always called via printquery_analysecorpus() */
	
	$matrix = get_feature_matrix( $_GET['showMatrix'] );
	
	if (false === $matrix)
		exiterror_general("Could not retrieve any information on the specified matrix!");
	
	if (!$User->is_admin())
		if ( $User->username != $matrix->user )
			exiterror_general("The specified matrix does not belong to this user account!");
	
	if ( $corpus_sql_name != $matrix->corpus )
		exiterror_general("The specified matrix is not associated with this corpus!");
	
	$variable_list  = feature_matrix_list_variables($matrix->id);
	$object_names   = feature_matrix_list_objects($matrix->id);
	
	$tablename = feature_matrix_id_to_tablename($matrix->id);
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="2">Analyse corpus: Viewing feature matrix control</th>
		</tr>
		<tr>
			<td class="concordgrey">Name:</td>
			<td class="concordgeneral"><?php echo cqpweb_htmlspecialchars($matrix->savename); ?></td>
		</tr>	
		<tr>
			<td class="concordgrey">Uses subcorpus:</td>
			<td class="concordgeneral">
				<?php echo empty($matrix->subcorpus) ? 'Whole corpus' : $matrix->subcorpus; ?>
			</td>
		</tr>	

		<tr>
			<td class="concordgrey">Data objects are units of:</td>
			<td class="concordgeneral"><?php echo $matrix->unit; ?></td>
		</tr>	

		<tr>
			<td class="concordgrey">Date created:</td>
			<td class="concordgeneral"><?php echo date($matrix->create_time); ?></td>
		</tr>	

		<tr>
			<td class="concordgrey">Number of variables (columns):</td>
			<td class="concordgeneral"><?php echo count($variable_list); ?></td>
		</tr>	

		<tr>
			<td class="concordgrey">Number of data objects (rows):</td>
			<td class="concordgeneral"><?php echo count($object_names); ?></td>
		</tr>
	</table>	

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="2">Feature matrix variable list</th>
		</tr>
		<tr>
			<th class="concordtable">Variable label</th>
			<th class="concordtable">Source of variable</th>
		</tr>
		
		<?php
		
		if (empty($variable_list))
			echo "\n\t\t<tr>"
				, '<td class="concordgrey" colspan="2">&nbsp;<br>No variables found; data may be corrupted.<br>&nbsp;</td>'
				, "</tr>\n" 
				;
		else
			foreach($variable_list as $v)
				echo "\n\t\t<tr>"
					, '<td class="concordgeneral">' , $v->label , '</td>'
					, '<td class="concordgeneral">' , cqpweb_htmlspecialchars($v->source_info) , '</td>'
					, "</tr>\n" 
					;
		?>
		
	</table>

	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">Full matrix content</th>
		</tr>
	</table>

	<?php 
	
	echo print_mysql_result_dump(do_mysql_query("select * from $tablename")); 

	/* that separate table is closed off in that function, so no need for more HTML here. */
	
	// TODO jQuery to make all this appear / disappear as necessary
	// TODO a way of getting back to the matrix list
	// TODO a way of getting back to the main analysis menu
}


// TODO move this function?
function printquery_analysecorpus()
{
	global $Corpus;
	global $User;
	global $corpus_sql_name;
	
	if (! empty($_GET['showMatrix']))
	{
		printquery_showmatrix();
		return;
	}
	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="3">Analyse corpus</th>
		</tr>
		<tr>
			<td class="concorderror" colspan="3">
				&nbsp;<br>
				This page contains controls for advanced corpus analysis functions.
				<b>WARNING</b>: currently under development. You have been warned.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="3">Select analysis</th>
		</tr>
		<tr>
			<td class="concordgrey" width="33.3%">
				&nbsp;<br>
				Choose an option for corpus analysis:
				<br>&nbsp;
			</td>
			<td class="concordgeneral" align="center" width="33.3%">
				<select id="analysisToolChoice">
					<!-- values match the ID of the hideable element they refer to -->
					<option value="featureMatrixDesign" selected="selected">Design feature matrix for multivariate analysis</option>
					<option value="featureMatrixList"                      >View existing feature matrix analyses</option>
					<!-- More options will be added here later. -->
					<!-- Also: interface to corpus analysis plugins will be added here. -->
				</select>
			</td>
			<td class="concordgeneral" align="center" width="33.3%">
				<input type="button" id="analysisToolChoiceGo" value="Show analysis controls" />
			</td>
		</tr>
	</table>
	
	
	<!-- begin saved feature matrix list block -->
	<table id="featureMatrixList" class="concordtable" width="100%">
		<tr>
			<th class="concordtable" colspan="7">
				Saved feature matrices
			</th>
		</tr>
		<tr>
			<th class="concordtable">Name</th>
			<th class="concordtable">Subcorpus</th>
			<th class="concordtable">Object unit</th>
			<th class="concordtable">Date created</th>
			<th class="concordtable" colspan="3">Actions</th>
		</tr>
		
		<?php
		
		// TODO add N features, N objects to the display?
		
		
		$list = list_feature_matrices($corpus_sql_name, $User->username);

		if (empty($list))
			echo '<tr><td class="concordgrey" colspan="7">'
				, '&nbsp;<br>You have no saved features matrices.<br>&nbsp;</td></tr>'
				;
		else
			foreach($list as $fm)
				echo '<tr>'
					, '<td class="concordgeneral">' , cqpweb_htmlspecialchars($fm->savename), '</td>'
					, '<td class="concordgeneral">'
					, (empty($fm->subcorpus) ? '(whole corpus)' : $fm->subcorpus)
					, '</td>'
					, '<td class="concordgeneral">' , $fm->unit , '</td>'
					, '<td class="concordgeneral">' , date($fm->unit, DATE_RSS) , '</td>'
					, '<td class="concordgeneral" align="center">' 
					, '<a class="menuItem" href="index.php?thisQ=analyseCorpus&showMatrix='
					, $fm->id
					, '&uT=y">[View/Analyse]</a>' 
					, '</td>'
					, '<td class="concordgeneral" align="center">' , '[Download]' , '</td>'
					, '<td class="concordgeneral" align="center">' , '[Delete]' , '</td>'
					, "</tr>\n\t\t" 
					;
				
		?>

	</table>
	
	

	<!-- begin feature matrix control block -->
	<form id="featureMatrixDesign" action="redirect.php" method="get">
		
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable" colspan="2">Design feature matrix for multivariate analysis</th>
			</tr>
			
			<tr>
				<td class="concordgrey" colspan="2">
					&nbsp;<br>
					Explanation fo the use of feature matrices goes ehre.
					Note it can be used for PCA, cluster analysis or facotr analysis.
					<br>&nbsp;
				</td>
			</tr>
	
			<tr>
				<th class="concordtable" colspan="2">Select unit of analysis</th>
			</tr>
			<tr>
				<td class="concordgrey">
					Choose a unit of analysis (for factoring, clustering, etc.)
				</td>
				<td class="concordgeneral">
					At the moment, the only choice is "text".
					<!--
					Howeve,r in the future, we might want ot make it possibl;e to use other elvels of XML in the ciorpus
					e.g. utterance, paragraph, chapter, etc·
					-->
				</td>
			</tr>
			<tr>
				<th class="concordtable" colspan="2">Define object labelling method</th>
			</tr>
			<tr>
				<td class="concordgrey">
					All data objects (e.g. texts) in a feature matrix need to have a label.
					<br>
					Choose one of the methods opposite for creation of object labels.
				</td>
				<td class="concordgeneral">
					<select name="labelMethod">
						<option value="id"  selected="selected">Use &ldquo;id&rdquo; attributes, if available (recommended!)</option> 
						<option value="n"                      >Use &ldquo;n&rdquo; attributes, if available</option> 
						<option value="seq"                    >Assign a number to each object in order (fallback method)</option>
					</select>
				</td> 
			</tr>
			<tr>
				<th class="concordtable" colspan="2">Select texts<!--or, units more generally --></th>
			</tr>
			<tr>
				<td class="concordgrey" width="50%">
					Select a subcorpus or the full corpus. 
					<br>
					Only the texts in the subcorpus you select will be included in the corpus.
					<!--
					<br>
					(when we add other possible levels, it will be possible to use subcorpora based on those divisions)
					-->
				</td>
				<td class="concordgeneral">
					<select name="corpusSubdiv">
						<option selected="selected" value="~~full~corpus~~">Use the entire corpus</option>
						<?php
						$sql_query = "select subcorpus_name, numfiles from saved_subcorpora
							where corpus = '$corpus_sql_name' and user = '{$User->username}' order by subcorpus_name";
						$result = do_mysql_query($sql_query);
						
						while (false !== ($sc = mysql_fetch_object($result)))#
							echo "\n\t\t\t\t\t\t<option value=\"{$sc->subcorpus_name}\">"
								, "Subcorpus &ldquo;" , $sc->subcorpus_name , "&rdquo; (", $sc->numfiles , " texts)" 
								, "</option>"
								;
						
						
						?>
						
					</select>
				</td>
			</tr>
		</table>
			
		<table class="concordtable" width="100%">
			<tr>
				<th class="concordtable" colspan="4">Select features (from saved queries)</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="4">
					Use the tickboxes below to select the saved queries you want to include as features.
				</td>
			</tr>
			
			<tr>
				<th class="concordtable">Use?</th>
				<th class="concordtable">Name</th>
				<th class="concordtable">No. of hits</th>
				<th class="concordtable">Date</th>
			</tr>
			
			<?php
			
			$sql_query = "select query_name, save_name, date_of_saving, hits, hits_left
				from saved_queries where corpus = '$corpus_sql_name' and user = '{$User->username}'  and saved = 1";			
			
			$result = do_mysql_query($sql_query);

			for ($i = 0 ; false !== ($q = mysql_fetch_object($result)) ; ++$i)
			{
				if (!empty($q->hits_left))
					$hits_print = number_format((float)array_pop($junk_temp_array = explode('~', $q->hits_left)));
				else
					$hits_print = number_format((float)$q->hits);
				echo "\n<tr>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\"><input type=\"checkbox\" value=\"{$q->query_name}\" name=\"useQuery$i\" /></td>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\">{$q->save_name}</td>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\">$hits_print</td>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\">{$q->date_of_saving}</td>"
					, "\n</tr>\n"
					;
			}
			?>
		</table>
		
		<table class="concordtable" width="100%">
		<!--
			<tr>
				<th class="concordtable" colspan="2">Select features (based on query permutation)</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					This is for features whose value can only be deuced by mathemtaical manipulation of more than one saved query.
					Typical example: where a feature is equalo to (search for soemthing ) minus (search for something elsE) 
				</td>
			</tr>
			
			<tr>
				<th class="concordtable" colspan="2">Select additional features</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					This is for features whose value can only be deuced by mathemtaical manipulation of more than one saved query.
					Typical example: where a feature is equalo to (search for soemthing ) minus (search for something elsE) 
					
					<p>
						Allow extra features to be added that are not queries. The list of these is:
					</p>
					
					<ul>
						<li>Standardised type-token ratio</li>
						<li>Type-token ratio</li>
						<li>Average word length</li>
						<li>Average sub-unit length (as indicated by any XML element: s, p)</li>
						<li>Lexical density</li>
					</ul>
					
					<p>Other statistical features can be defined via the saved-query feature function: e.g. lexical density.</p>
				</td>
			</tr>
			-->
			
			<tr>
				<td class="concordgrey" width="50%">Enter a name for this new feature matrix:</td>
				<td class="concordgeneral" width="50%">
					<!-- Does not need to be a handle. Can be anything. -->
					<input type="text" name="matrixName" />
				</td>
			</tr>
			
			
			<tr>
				<td class="concordgeneral" align="center" colspan="2">
					<input type="submit" value="Build feature matrix database!" />
					
					<!--
					<p>
						The action above takes us to a new screen where the matrix already exists, and we then have
						the options for factor analysis.
					</p>
					-->
				</td>
			</tr>
		</table>
		<input type="hidden" name="" value="" ?
		
		
		<input type="hidden" name="uT" value="y" />
	</form>
	<!-- end feature matrix control block -->

	<?php
	
	/*
	Here is what will be on the controls for a saved feature matrix.
	
	(1) Export feature matrix.
		- as a plain-text file for offline analysis 
	
	(2) Configure factor analysis.
		Anything that is not a pre-calculated statistic (avg word lenghtr etc.)
		is normalised by dividing by text length.
		
	
	THE KMO test - code is in the HTML file I downloaded from the web,
	
	it requires the ginv function from the MASS library, but full code for ginv()
	is available in the MASS manual.
	
	Have an option to do it.
	
	
	*/
	
}






function printquery_uploadquery()
{
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th colspan="2" class="concordtable">Upload a query from an external data file</th>
		</tr>
		<form action="upload-query.php" method="POST" enctype="multipart/form-data">
			<tr>
				<td class="concordgrey">
					&nbsp;<br/>
					Select file for upload:
					<br/>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br/>
					<input type="file" name="uploadQueryFile" />
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgrey">
					&nbsp;<br/>
					Enter a name for the new saved query:
					<br/>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br/>
					<input type="text" size="30" maxlength="30" name="uploadQuerySaveName" />
					<br/>&nbsp;
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgeneral" align="center">
					&nbsp;<br/>
					<input type="submit" value="Upload file" />
					<br/>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="uT" value="y" />			
		</form>
		<tr>
			<td colspan="2" class="concordgrey">
				<strong>Instructions:</strong>
				<ul>
					<li>You can use this page to upload a file to CQPweb and create a new saved query from it.</li>
					<li>The file must contain (only) two columns of corpus positions, separated by tabs.</li>
					<li>
						The numbers refer to the start point and end point of each individual &ldquo;hits&rdquo;
						of the query you want create.
					</li>
					<li>Normally, you would use (a subset of the) lines from a previously-exported query.</li>
					<li>Your query will be generated within <em>the current corpus only</em>.</li>
					<li>
						The name of the saved query can only contain letters, numbers and the underscore 
						character ("_"); it cannot contain any spaces.
					</li>
				</ul>
			</td>
		</tr>
	</table>
	<?php
	/* TODO wouldn't it be pretty easy to upload a subcorpus through another form here,
	 * will relatively minor tweaks only to the code (parameterisable!) ? */
}


function print_cache_table($begin_at, $per_page, $user_to_show = NULL, $show_unsaved = true, $show_filesize = true)
{
	global $User;
	global $corpus_sql_name;
	
	if ($user_to_show == NULL)
		$user_to_show = $User->username;

	
	/* create sql query and set options */
	$sql_query = "select query_name, user, save_name, hits, file_size, saved, date_of_saving, hits_left
		from saved_queries where corpus = '$corpus_sql_name' ";
		
	if (($user_to_show == '__ALL') && $User->is_admin())
		$usercolumn = true;
	else
	{
		$usercolumn = false;
		$sql_query .= " and user = '$user_to_show' ";
	}
	if (! $show_unsaved)
		$sql_query .= " and saved = 1";
	else
		$sql_query .= " and saved != 2";
		/* saved != 2 excludes categorised queries */
	
	$sql_query .= ' order by date_of_saving DESC';

	/* only allow superusers to see file size */		
	if (!$User->is_admin())
		$show_filesize = false;

	$result = do_mysql_query($sql_query);

	
	$s = '
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">No.</th>
			' . ($usercolumn ? '<th class="concordtable">User</th>' : '') . '
			<th class="concordtable">Name</th>
			<th class="concordtable">No. of hits</th>
			' . ($show_filesize ? '<th class="concordtable">File size</th>' : '') . '
			<th class="concordtable">Date</th>
			<th class="concordtable">Rename</th>
			<th class="concordtable">Delete</th>	
		</tr>
	';

	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysql_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	
	if ($toplimit == 1)
		$s .= '<tr><td class="concordgrey" colspan="' . ($usercolumn ? '8' : '7') . '" align="center">
				&nbsp;<br/>No saved queries were found.<br/>&nbsp;
				</td</tr>';

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		$row = mysql_fetch_assoc($result);
		if (!$row)
			break;
		if ($i < $begin_at)
			continue;
		
		$s .= "<tr>\n<td class='concordgeneral'><center>$i</center></td>";
		
		if ($usercolumn)
			$s .=  "<td class='concordgeneral'><center>" . $row['user'] . '</center></td>';
		
		if ($row['save_name'] != '')
			$print_name = $row['save_name'];
		else
			$print_name = $row['query_name'];
		
		$s .= '<td class="concordgeneral"><a href="concordance.php?qname='
			. $row['query_name'] . '&uT=y" onmouseover="return escape(\'Show query solutions\')">'
			. $print_name . '</a></td>';

		if (!empty($row['hits_left']))
			$hits_print = number_format((float)array_pop($temp_array = explode('~', $row['hits_left'])));
		else
			$hits_print = number_format((float)$row['hits']);
		
		$s .= '<td class="concordgeneral"><center>' . $hits_print . '</center></td>';
		
		if ($show_filesize)
			$s .= "<td class='concordgeneral'><center>" . round(($row['file_size']/1024), 1) . ' Kb</center></td>';
			
		
		$s .= '<td class="concordgeneral"><center>' . $row['date_of_saving'] . '</center></td>';
		
		$temp_gets = url_printget(array(array('redirect', ''), array('saveScriptmode', ''), array('qname', '')));
		
		if ($row['saved'] == "1")
			$s .= '<td class="concordgeneral"><center>' 
				. '<a class="menuItem" href="redirect.php?redirect=saveHits&saveScriptMode=get_save_rename&qname='
				. $row['query_name'] . '&' . $temp_gets . '" onmouseover="return escape(\'Rename this saved query\')">'
				. '[rename]</a></center></td>';
		else
			$s .= '<td class="concordgeneral"><center>-</center></td>';

		$s .= '<td class="concordgeneral"><center>' 
			. '<a class="menuItem" href="redirect.php?redirect=saveHits&saveScriptMode=delete_saved&qname='
			. $row['query_name'] . '&' . $temp_gets . '" onmouseover="return escape(\'Delete this saved query\')">'
			. '[x]</a></center></td>';
		$s .= '</tr>
			';
		unset($temp_gets);
	}
	
	$s .= "</table>\n\n\n";

	$navlinks = '<table class="concordtable" width="100%"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Newer queries]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysql_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older queries] &gt;&gt;';
	if (mysql_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= "</td></tr></table>\n\n\n";
		
	return $s . $navlinks;
}




?>
