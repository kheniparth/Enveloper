<?php
// run from CLI with the directory that the HTML docs are in
// example 'php pdf.php "C:/emails/html"'
if (!isset($argv[1])) die('ERROR: No argument for HTML directory supplied!');
$dir = $argv[1];
process($dir);
die;


function process($dir) {
	foreach (glob($dir."/*.html") as $file) {
		//var_dump(pathinfo($file)); exit;
		//$filename = substr($file, strpos($file, "/"));
		$filename = pathinfo($file);
		//echo $filename['filename'] . PHP_EOL;
		//$argument = "pdf.js ".$file." pdf\\".$filename.".pdf";
		$argument = 'pdf.js "'.$file.'" "pdf'. DIRECTORY_SEPARATOR . $filename['filename'] .'.pdf"';
		//echo $argument . PHP_EOL;
		exec("phantomjs.exe $argument");
		echo "Converted " . $filename['filename'] . ".pdf" . PHP_EOL;
	}
	
	/*
	foreach (glob($dir."/*.html") as $file) {
		$filename = substr($file, strpos($file, "/"));
		//echo "Found $filename" . PHP_EOL;
		//$argument = "pdf.js ".$file." pdf\\".$filename.".pdf";
		$argument = 'pdf.js "'.$file.'" "pdf'.$filename.'.pdf"';
		exec("phantomjs.exe $argument");
		echo "Converted $filename" . PHP_EOL;
	}
	*/
}
?>