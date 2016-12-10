<?php

/** The name of the database */
define('DB_NAME', 'database');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'password');

/** MySQL hostname */
define('DB_HOST', 'localhost');

function date_tz($format, $timestamp = false, $tz = 'Europe/London'){
	if(!$timestamp) $timestamp = time();
	$dt = new DateTime("now", new DateTimeZone($tz)); 
	$dt->setTimestamp($timestamp);
	if($format == 'DateTime') return $dt;
	return $dt->format($format);	
}

function delete_files($bhavcopy_file, $bhavcopy_csvfile){
	printf(_pstr("Deleting downloaded files... %s and %s"), $bhavcopy_file, $bhavcopy_csvfile);
	unlink($bhavcopy_file);
	unlink($bhavcopy_csvfile);
}

function _pstr($t, $m = '') {
	if(PHP_SAPI !== 'cli'){
		$color = ($m == 'error' ? 'color: red' : '');
		return '<p style="font-family: monospace;'.$color.'">'.$t.'</p>'.PHP_EOL;
	} else {
		$color = ($m == 'error' ? chr(27).'[0;31m' : '');
		return $color.$t.' '.chr(27).'[0m'.PHP_EOL;
	}
}

if(PHP_SAPI === 'cli'){
	$_REQUEST = getopt('', array('date::'));
}

if(!isset($_REQUEST['date'])) {
	$date = date_tz('DateTime', time(), 'Asia/Kolkata');
} else {
	$date = date_tz('DateTime', strtotime($_REQUEST['date']), 'Asia/Kolkata');
}

$bhavcopy_file = sprintf('cm%s%s%dbhav.csv.zip', 
	$date->format('d'), 
	strtoupper($date->format('M')), 
	$date->format('Y')
);
$bhavcopy_url = sprintf('https://www.nseindia.com/content/historical/EQUITIES/%d/%s/%s', 
	$date->format('Y'), 
	strtoupper($date->format('M')), 
	$bhavcopy_file
);

printf(_pstr("Downloading bhavcopy file from %s..."), $bhavcopy_url);

$ch = curl_init($bhavcopy_url);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0'); 
$output = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if($httpcode != 200){
	printf(_pstr("Bhavcopy not found at %s. HTTP error: %s", 'error'), $bhavcopy_url, $httpcode);
	exit();	
}

$fh = fopen($bhavcopy_file, 'w');
fwrite($fh, $output);
fclose($fh);

printf(_pstr("Saving bhavcopy file %s"), $bhavcopy_file);

$zip = new ZipArchive;
$res = $zip->open($bhavcopy_file);

if ($res === TRUE) {

	printf(_pstr("Extracting bhavcopy file %s"), $bhavcopy_file);
	$zip->extractTo(getcwd());
	$zip->close();
	$bhavcopy_csvfile = str_replace('.zip','',$bhavcopy_file);

	printf(_pstr("Parsing bhavcopy csv file %s"), $bhavcopy_csvfile);
	$bhavcopy_rows = array_map('str_getcsv', file($bhavcopy_csvfile));
	$header = array_shift($bhavcopy_rows);
	$bhavcopy_array = array();
	foreach ($bhavcopy_rows as $bhavcopy_row) {
		$bhavcopy_array[] = array_combine($header, $bhavcopy_row);
	}

	printf(_pstr("Checking database..."));
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	if (mysqli_connect_errno()) {
	    printf(_pstr("Database connect failed. Error: %s", 'error'), mysqli_connect_error());
	    delete_files($bhavcopy_file, $bhavcopy_csvfile);
	    exit();
	}
	$mysqli->set_charset("utf8");
	$mysql_date = $date->format('Y-m-d');
	$count = $mysqli->query('SELECT count(*) as count FROM nse_bhavcopy WHERE timestamp = "'.$mysqli->real_escape_string($mysql_date).'"');
	$count_row = $count->fetch_array(MYSQLI_ASSOC);
	$count->free();
	if($count_row['count'] > 0){
		printf(_pstr("Data already exists for date %s", 'error'), $mysql_date);
		delete_files($bhavcopy_file, $bhavcopy_csvfile);
		exit();
	}

	printf(_pstr("Attempting to insert %s records..."), count($bhavcopy_array));
	$insert = $mysqli->prepare("INSERT INTO nse_bhavcopy (symbol, series, open, high, low, close, last, prevclose, tottrdqty, tottrdval, timestamp, totaltrades, isin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
	foreach ($bhavcopy_array as $record) {
		$insert->bind_param("sssssssssssss",
			$record['SYMBOL'],
	    	$record['SERIES'],
	    	$record['OPEN'],
	    	$record['HIGH'],
	    	$record['LOW'],
	    	$record['CLOSE'],
	    	$record['LAST'],
	    	$record['PREVCLOSE'],
	    	$record['TOTTRDQTY'],
	    	$record['TOTTRDVAL'],
	    	$mysql_date,
	    	$record['TOTALTRADES'],
	    	$record['ISIN']		
		);
		$insert->execute();	 
		if($insert->error) {
			printf(_pstr("Error inserting %s: %s.", 'error'), $record['SYMBOL'], $insert->error);
		}   
	}
	$insert->close();
	$mysqli->close();

	delete_files($bhavcopy_file, $bhavcopy_csvfile);

} else {
	printf(_pstr("Extracting failed. Error code: %s", 'error'), $res);
	exit();
}