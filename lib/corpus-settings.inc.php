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




class CQPwebSettings
{

	/* these four must be strings: disallow_nonwords is used on SQL name and CQP name of corpus */
	private $corpus_title;
	private $corpus_sql_name;
	private $corpus_cqp_name;
	private $css_path;

	/* these ones must be boolean */
	private $corpus_main_script_is_r2l;
	private $corpus_uses_case_sensitivity;

	/* context controlled by an int and a string (disallow_nonwords is used on the latter);
	 * if words are to be used for context, the s_attribute string is NULL */
	private $context_scope;
	private $context_s_attribute;

	/* extended context initial (also minimum) and max values - both ints, N of tokens. */
	private $initial_extended_context;
	private $max_extended_context;

	/* visualisation control variables (two bools and a string for each) */
	private $visualise_gloss_in_concordance;
	private $visualise_gloss_in_context;
	private $visualise_gloss_annotation;

	private $visualise_translate_in_concordance;
	private $visualise_translate_in_context;
	private $visualise_translate_s_att;

	/* and for position labels: */
	private $visualise_position_labels;
	private $visualise_position_label_attribute;

	/* management variables */
	private $cqpweb_root_directory_path;
	/* should be either absolute or relative to the working dir of the current script */


	/* getters and setters */

	public function get_corpus_title() { return $this->corpus_title; }
	public function set_corpus_title($new_value) { $this->corpus_title = (string)$new_value; }

	public function get_corpus_sql_name() { return $this->corpus_sql_name; }
	public function set_corpus_sql_name($new_value)
	{
		$this->corpus_sql_name = $this->disallow_nonwords($new_value);
	}

	public function get_corpus_cqp_name() { return $this->corpus_cqp_name; }
	public function set_corpus_cqp_name($new_value)
	{
		$this->corpus_cqp_name = $this->disallow_nonwords($new_value);
	}

	public function get_css_path() { return $this->css_path; }
	public function set_css_path($new_value) { $this->css_path = $new_value; }

	public function get_r2l() { return $this->corpus_main_script_is_r2l; }
	public function set_r2l($new_value) { $this->corpus_main_script_is_r2l = (bool) $new_value; }

	public function get_case_sens() { return $this->corpus_uses_case_sensitivity; }
	public function set_case_sens($new_value) { $this->corpus_uses_case_sensitivity = (bool) $new_value; }

	public function get_context_scope() { return $this->context_scope; }
	public function set_context_scope($new_value) { $this->context_scope = (int) $new_value; }

	public function get_context_s_attribute() { return $this->context_s_attribute; }
	public function set_context_s_attribute($new_value)
	{
		if ($new_value == NULL)
			$this->context_s_attribute = NULL;
		else
			$this->context_s_attribute = $this->disallow_nonwords($new_value);
	}

	public function get_directory_override_reg() { return $this->directory_override_reg; }
	public function set_directory_override_reg($new_value)
	{
		if (empty($new_value))
			unset($this->directory_override_reg);
		else if (is_dir($new_value))
			$this->directory_override_reg = $new_value;
	}

	public function get_directory_override_data() { return $this->directory_override_data; }
	public function set_directory_override_data($new_value)
	{
		if (empty($new_value))
			unset($this->directory_override_data);
		else if (is_dir($new_value))
			$this->directory_override_data = $new_value;
	}


	public function get_initial_extended_context() { return $this->initial_extended_context; }
	public function set_initial_extended_context($new_value) { $this->initial_extended_context = (int) $new_value; }
	public function get_max_extended_context() { return $this->max_extended_context; }
	public function set_max_extended_context($new_value) { $this->max_extended_context = (int) $new_value; }


	public function get_visualise_gloss_in_concordance() { return $this->visualise_gloss_in_concordance; }
	public function set_visualise_gloss_in_concordance($new_value) { $this->visualise_gloss_in_concordance = (bool) $new_value; }
	public function get_visualise_gloss_in_context() { return $this->visualise_gloss_in_context; }
	public function set_visualise_gloss_in_context($new_value) { $this->visualise_gloss_in_context = (bool) $new_value; }
	public function set_visualise_gloss_annotation($new_value)
	{
		if ($new_value == NULL)
			$this->visualise_gloss_annotation = NULL;
		else
			$this->visualise_gloss_annotation = $this->disallow_nonwords($new_value);
	}


	public function get_visualise_translate_in_concordance() { return $this->visualise_translate_in_concordance; }
	public function set_visualise_translate_in_concordance($new_value) { $this->visualise_translate_in_concordance = (bool) $new_value; }
	public function get_visualise_translate_in_context() { return $this->visualise_translate_in_context; }
	public function set_visualise_translate_in_context($new_value) { $this->visualise_translate_in_context = (bool) $new_value; }
	public function set_visualise_translate_s_att($new_value)
	{
		if ($new_value == NULL)
			$this->visualise_translate_s_att = NULL;
		else
			$this->visualise_translate_s_att = $this->disallow_nonwords($new_value);
	}

	public function get_visualise_position_labels() { return $this->visualise_position_labels; }
	public function set_visualise_position_labels($new_value) { $this->visualise_position_labels = (bool) $new_value; }
	public function set_visualise_position_label_attribute($new_value)
	{
		if ($new_value == NULL)
			$this->visualise_position_label_attribute = NULL;
		else
			$this->visualise_position_label_attribute = $this->disallow_nonwords($new_value);
	}



	/**
	 * Constructor's sole parameter is path to the root directory of CQPweb;
	 * this can be absolute (beginning with '/') or relative to the script's
	 * working directory. Defaults to '.'.
	 */
	public function __construct($path = '.')
	{
		$this->cqpweb_root_directory_path = $path;
	}


	/**
	 * returns 0 for all OK otherwise a string describing the error
	 */
	public function load($sqlname = false)
	{
		if ($sqlname !== false)
			$this->corpus_sql_name = $sqlname;

		if (! isset($this->corpus_sql_name))
			return "CQPwebSettings Error: you haven't specified the corpus settings you want to load!";

		include("{$this->cqpweb_root_directory_path}/{$this->corpus_sql_name}/settings.inc.php");

		/* check whether each variable is set, if so, upload it to the private variables */

		if (isset($corpus_sql_name))
		{
			if ($corpus_sql_name !== $this->corpus_sql_name)
				return "CQPwebSettings Error: the original file does not match the specified SQL-name!";
		}
		// TODO could this be simplified with a "foreach" and variable variables?
		if (isset($corpus_title))
			$this->corpus_title = $corpus_title;
		if (isset($corpus_cqp_name))
			$this->corpus_cqp_name = $corpus_cqp_name;
		if (isset($css_path))
			$this->css_path = $css_path;
		if (isset($corpus_main_script_is_r2l))
			$this->corpus_main_script_is_r2l = (bool)$corpus_main_script_is_r2l;
		if (isset($corpus_uses_case_sensitivity))
			$this->corpus_uses_case_sensitivity = (bool)$corpus_uses_case_sensitivity;
		if (isset($context_scope))
			$this->context_scope = (int)$context_scope;
		if (isset($context_s_attribute))
			$this->context_s_attribute = $context_s_attribute;
		if (isset($initial_extended_context))
			$this->initial_extended_context = $initial_extended_context;
		if (isset($max_extended_context))
			$this->max_extended_context = $max_extended_context;
		if (isset($visualise_gloss_in_concordance))
			$this->visualise_gloss_in_concordance = (bool)$visualise_gloss_in_concordance;
		if (isset($visualise_gloss_in_context))
			$this->visualise_gloss_in_context = (bool)$visualise_gloss_in_context;
		if (isset($visualise_gloss_annotation))
			$this->visualise_gloss_annotation = $visualise_gloss_annotation;
		if (isset($visualise_translate_in_concordance))
			$this->visualise_translate_in_concordance = (bool)$visualise_translate_in_concordance;
		if (isset($visualise_translate_in_context))
			$this->visualise_translate_in_context = (bool)$visualise_translate_in_context;
		if (isset($visualise_translate_s_att))
			$this->visualise_translate_s_att = $visualise_translate_s_att;
		if (isset($visualise_position_labels))
			$this->visualise_position_labels = (bool)$visualise_position_labels;
		if (isset($visualise_position_label_attribute))
			$this->visualise_position_label_attribute = $visualise_position_label_attribute;
		return 0;
	}



	/**
	 * returns 0 for all OK, otherwise a string describing the error
	 */
	public function save()
	{
		if ( ! $this->ready_to_save())
			return "CQPwebSettings Error: not ready to save (necessary variables not all set)";

		$data = "<?php\n\n";

		/* variables that must be written */
		$data .= "\$corpus_title = '" . addcslashes($this->corpus_title, "'") . "';\n";
		$data .= "\$corpus_sql_name = '{$this->corpus_sql_name}';\n";
		$data .= "\$corpus_cqp_name = '{$this->corpus_cqp_name}';\n";
		$data .= "\$css_path = '{$this->css_path}';\n";

		/* variables that are only written if they are present */
		if (isset($this->corpus_main_script_is_r2l))
			$data .= "\$corpus_main_script_is_r2l = " . ($this->corpus_main_script_is_r2l ? 'true' : 'false') . ";\n";
		if (isset($this->corpus_uses_case_sensitivity))
			$data .= "\$corpus_uses_case_sensitivity = " . ($this->corpus_uses_case_sensitivity ? 'true' : 'false') . ";\n";

		if (isset($this->context_scope))
			$data .= "\$context_scope = {$this->context_scope};\n";
		if (isset($this->context_s_attribute))
			$data .= "\$context_s_attribute = '{$this->context_s_attribute}';\n";

		if (isset($this->initial_extended_context))
			$data .= "\$initial_extended_context = {$this->initial_extended_context};\n";
		if (isset($this->max_extended_context))
			$data .= "\$max_extended_context = {$this->max_extended_context};\n";

		if (isset($this->visualise_gloss_in_concordance))
			$data .= "\$visualise_gloss_in_concordance = " . ($this->visualise_gloss_in_concordance ? 'true' : 'false') . ";\n";
		if (isset($this->visualise_gloss_in_context))
			$data .= "\$visualise_gloss_in_context = " . ($this->visualise_gloss_in_context ? 'true' : 'false') . ";\n";
		if (isset($this->visualise_gloss_annotation))
			$data .= "\$visualise_gloss_annotation = '{$this->visualise_gloss_annotation}';\n";

		if (isset($this->visualise_translate_in_concordance))
			$data .= "\$visualise_translate_in_concordance = " . ($this->visualise_translate_in_concordance ? 'true' : 'false') . ";\n";
		if (isset($this->visualise_translate_in_context))
			$data .= "\$visualise_translate_in_context = " . ($this->visualise_translate_in_context ? 'true' : 'false') . ";\n";
		if (isset($this->visualise_translate_s_att))
			$data .= "\$visualise_translate_s_att = '{$this->visualise_translate_s_att}';\n";

		if (isset($this->visualise_position_labels))
			$data .= "\$visualise_position_labels = " . ($this->visualise_position_labels ? 'true' : 'false') . ";\n";
		if (isset($this->visualise_position_label_attribute))
			$data .= "\$visualise_position_label_attribute = '{$this->visualise_position_label_attribute}';\n";

		$data .= "?>\n";

		file_put_contents("{$this->cqpweb_root_directory_path}/{$this->corpus_sql_name}/settings.inc.php", $data);

		return 0;
	}



	private function ready_to_save()
	{
		if (! isset(
			$this->corpus_title ,
			$this->corpus_sql_name ,
			$this->corpus_cqp_name ,
			$this->css_path
			) )
			return false;
		return true;
	}



	private function disallow_nonwords($argument)
	{
		return preg_replace('/\W/', '', (string)$argument);
	}
}


?>
