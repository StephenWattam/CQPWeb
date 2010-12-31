<?php

// TODO note. Should annotated query files be dealt with otherwise? e.g. in subfolder called "usr"?
function uploaded_file_to_upload_area($original_name, $file_type, $file_size, $temp_path, $error_code)
{
	global $cqpweb_uploaddir;
	global $username;

	/* convert back to int: execute.inc.php may have turned it to a string */
	$error_code = (int)$error_code;
	if ($error_code !== UPLOAD_ERR_OK)
		exiterror_fullpage('The file did not upload correctly! Please try again.', __FILE__, __LINE__);
	
	/* only superusers can upload REALLY BIG files
	 * TODO maybe make this variable - a user setting? */
	if (!user_is_superuser($username))
	{
		/* normal user limit is 2MB */
		if ($file_size > 2097152)
			exiterror_fullpage('The file did not upload correctly! Please try again.', __FILE__, __LINE__);
	}
	
	/* find a new name - a file that does not exist */
	for ($filename = basename($original_name); 1 ; $filename = '_' . $filename)
	{
		$new_path = "/$cqpweb_uploaddir/$filename";
		if (file_exists($new_path) === false)
			break;
	}
	
	if (move_uploaded_file($temp_path, $new_path)) 
		chmod($new_path, 0664);
	else
		exiterror_fullpage("The file could not be processed! Possible file upload attack", __FILE__, __LINE__);
}

/**
 * Change linebreaks in the named file in the upload area to Unix-style.
 */
function uploaded_file_fix_linebreaks($filename)
{
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);
	
	$intermed_path = "/$cqpweb_uploaddir/__________uploaded_file_fix_linebreaks________temp_________datoa__________.___";
	
	/*
	$data = file_get_contents($path);
	$data = str_replace("\xd\xa", "\xa", $data);
	file_put_contents($intermed_path, $data);
	*/
	$source = fopen($path, 'r');
	$dest = fopen($intermed_path, 'w');
	while ( false !== ($line = fgets($source)))
		fputs($dest, str_replace("\xd\xa", "\xa", $line));
	fclose($source);
	fclose($dest);
	
	unlink($path);
	rename($intermed_path, $path);
	chmod($path, 0666);
}


function uploaded_file_delete($filename)
{	
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);
	
	unlink($path);
}

function uploaded_file_gzip($filename)
{	
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);

	$zip_path = $path . '.gz';
	
	$in_file = fopen($path, "rb");
	if (!$out_file = gzopen ($zip_path, "wb"))
	{
		exiterror_fullpage('Your request could not be completed - compressed file could not be opened.', 
			__FILE__, __LINE__);
	}

	php_execute_time_unlimit();
	while (!feof ($in_file)) 
	{
		$buffer = fgets($in_file, 4096);
		gzwrite($out_file, $buffer, 4096);
	}
	php_execute_time_relimit();

	fclose ($in_file);
	gzclose ($out_file);
	
	unlink($path);
	chmod($zip_path, 0666);
}


function uploaded_file_gunzip($filename)
{
	global $cqpweb_uploaddir;

	$path = "/$cqpweb_uploaddir/$filename";
	
	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);
	
	if (preg_match('/(.*)\.gz$/', $filename, $m) < 1)
		exiterror_fullpage('Your request could not be completed - that file does not appear to be compressed.', 
			__FILE__, __LINE__);

	$unzip_path = "/$cqpweb_uploaddir/{$m[1]}";
	
	$in_file = gzopen($path, "rb");
	$out_file = fopen($unzip_path, "wb");

	php_execute_time_unlimit();
	while (!gzeof($in_file)) 
	{
		$buffer = gzread($in_file, 4096);
		fwrite($out_file, $buffer, 4096);
	}
	php_execute_time_relimit();

	gzclose($in_file);
	fclose ($out_file);
			
	unlink($path);
	chmod($unzip_path, 0666);
}

function uploaded_file_view($filename)
{
	global $cqpweb_uploaddir;
	
	$path = "/$cqpweb_uploaddir/$filename";

	if (!file_exists($path))
		exiterror_fullpage('Your request could not be completed - that file does not exist.', 
			__FILE__, __LINE__);

	$fh = fopen($path, 'r');
	
	$bytes_counted = 0;
	$data = '';
	
	while ((!feof($fh)) && $bytes_counted <= 102400)
	{
		$line = fgets($fh, 4096);
		$data .= $line;
		$bytes_counted += strlen($line);
	}

	fclose($fh);
	
	$data = htmlspecialchars($data);
	
	header('Content-Type: text/html; charset=utf-8');
	?>
	<html>
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>CQPweb: viewing uploaded file</title>
		</head>
		<body>
			<h1>Viewing uploaded file <i><?php echo $filename;?></i></h1>
			<p>NB: for very long files only the first 100K is shown
			<hr/>
			<pre>
			<?php echo "\n" . $data; ?>
			</pre>
		</body>
	</html>
	<?php
	exit();
}


?>