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
	global $username;
	global $default_history_per_page;
	global $corpus_sql_name;
	
	if (isset($_GET['historyView']))
		$view = $_GET['historyView'];
	else
		$view = ( (boolean)get_user_setting($username, 'cqp_syntax') ? 'cqp' : 'simple');
	

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	/* variable for superuser usage */
	if (isset($_GET['showUser']) && user_is_superuser($username))
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $username;


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
	


	if (user_is_superuser($username))
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
				. "&qmode=cqp&qname=INIT&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
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
					. "&qmode=cqp&qname=INIT&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
					. $row['hits'] . "</a></td>";
			else if ($row['restrictions'] != 'no_restriction')
				echo "<td class='concordgeneral' align='center'><a href=\"concordance.php?"
					. "theData=" . urlencode($row['cqp_query']) 
					. "&simpleQuery=" . urlencode($row['simple_query'])
					. '&' . untranslate_restrictions_definition_string($row['restrictions'])
					. "&qmode=cqp&qname=INIT&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
					. $row['hits'] . "</a></td>";
			else
				echo "<td class='concordgeneral' align='center'><a href=\"concordance.php?"
					. "theData=" . urlencode($row['cqp_query']) 
					. "&simpleQuery=" . urlencode($row['simple_query'])
					. "&qmode=cqp&qname=INIT&uT=y\" onmouseover=\"return escape('Recreate query result')\">" 
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
	global $username;
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
	if (isset($_GET['showUser']) && user_is_superuser($username))
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $username;


	if ($user_to_show == '__ALL')
		$current_string = 'Currently showing history for all users';
	else
		$current_string = "Currently showing history for user <b>&ldquo;$user_to_show&rdquo;</b>";
	
	$usercolumn = (($user_to_show == '__ALL') && user_is_superuser($username));

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;


	/* form for admin controls */
	if (user_is_superuser($username))
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
		echo '<td class="concordgeneral" align="center">' . $catqueries_to_show[$i]['query_record']['hits'] 
			. '</td>';
		
		/* number and %of hits categorised */
		echo '<td class="concordgeneral" align="center"><center>' . $catqueries_to_show[$i]['number_categorised'] 
			. ' ('
			. round(100*$catqueries_to_show[$i]['number_categorised']/$catqueries_to_show[$i]['query_record']['hits'] , 0)
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
	global $default_history_per_page;
	global $username;
	global $corpus_sql_name;



	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $default_history_per_page;



	$show_unsaved = false;

	if (isset($_GET['showUser']) && user_is_superuser($username))
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $username;

	if ($user_to_show == '__ALL')
	{
		$current_string = 'Currently showing history for all users';
	}
	else
	{
		$current_string = "Currently showing history for user <b>&ldquo;$user_to_show&rdquo;</b>";
	}



	/* form for admin controls */
	if (user_is_superuser($username))
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




	print_cache_table($begin_at, $per_page, $user_to_show, $show_unsaved, false);
}




function print_cache_table($begin_at, $per_page, $user_to_show = NULL, $show_unsaved = true, $show_filesize = true)
{
	global $username;
	global $corpus_sql_name;
	
	if ($user_to_show == NULL)
		$user_to_show = $username;

	
	/* create sql query and set options */
	$sql_query = "select query_name, user, save_name, hits, file_size, saved, date_of_saving, hits_left
		from saved_queries where corpus = '$corpus_sql_name' ";
		
	if (($user_to_show == '__ALL') && user_is_superuser($username))
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
	if (!user_is_superuser($username))
		$show_filesize = false;

	$result = do_mysql_query($sql_query);

	
	?>
	<table class="concordtable" width="100%">
		<tr>
			<th class="concordtable">No.</th>
			<?php if ($usercolumn) echo '<th class="concordtable">User</th>'; ?>
			<th class="concordtable">Name</th>
			<th class="concordtable">No. of hits</th>
			<?php if ($show_filesize) echo '<th class="concordtable">File size</th>'; ?>
			<th class="concordtable">Date</th>
			<th class="concordtable">Rename</th>
			<th class="concordtable">Delete</th>	
		</tr>

	<?php

	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysql_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	
	if ($toplimit == 1)
		echo '<tr><td class="concordgrey" colspan="' . ($usercolumn ? '8' : '7') . '" align="center">
				&nbsp;<br/>No saved queries were found.<br/>&nbsp;
				</td</tr>';

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		$row = mysql_fetch_assoc($result);
		if (!$row)
			break;
		if ($i < $begin_at)
			continue;
		
		echo "<tr>\n<td class='concordgeneral'><center>$i</center></td>";
		
		if ($usercolumn)
			echo "<td class='concordgeneral'><center>" . $row['user'] . '</center></td>';
		
		if ($row['save_name'] != '')
			$print_name = $row['save_name'];
		else
			$print_name = $row['query_name'];
		
		echo '<td class="concordgeneral"><a href="concordance.php?qname='
			. $row['query_name'] . '&uT=y" onmouseover="return escape(\'Show query solutions\')">'
			. $print_name . '</a></td>';

		if (!empty($row['hits_left']))
			$hits_print = make_thousands(array_pop($temp_array = explode('~', $row['hits_left'])));
		else
			$hits_print = make_thousands($row['hits']);
		
		echo '<td class="concordgeneral"><center>' . $hits_print . '</center></td>';
		
		if ($show_filesize)
			echo "<td class='concordgeneral'><center>" . round(($row['file_size']/1024), 1) . ' Kb</center></td>';
			
		
		echo '<td class="concordgeneral"><center>' . $row['date_of_saving'] . '</center></td>';
		
		$temp_gets = url_printget(array(array('redirect', ''), array('saveScriptmode', ''), array('qname', '')));
		
		if ($row['saved'] == "1")
			echo '<td class="concordgeneral"><center>' 
				. '<a class="menuItem" href="redirect.php?redirect=saveHits&saveScriptMode=get_save_rename&qname='
				. $row['query_name'] . '&' . $temp_gets . '" onmouseover="return escape(\'Rename this saved query\')">'
				. '[rename]</a></center></td>';
		else
			echo '<td class="concordgeneral"><center>-</center></td>';

		echo '<td class="concordgeneral"><center>' 
			. '<a class="menuItem" href="redirect.php?redirect=saveHits&saveScriptMode=delete_saved&qname='
			. $row['query_name'] . '&' . $temp_gets . '" onmouseover="return escape(\'Delete this saved query\')">'
			. '[x]</a></center></td>';
		echo '</tr>
			';
		unset($temp_gets);
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




?>