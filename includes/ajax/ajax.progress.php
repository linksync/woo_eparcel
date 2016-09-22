<?php
include_once('../../../../../wp-config.php');
header('Content-Type: application/json');

define( 'linksynceparcel_UPLOAD_URL', WP_CONTENT_DIR );

$file = str_replace(".", "", $_GET['sesid']);
$file = $file . ".txt";
$file = linksynceparcel_UPLOAD_URL .'/linksync/session-logs/'. $file;

if (file_exists($file)) {
	$text = file_get_contents($file);
	echo $text;

	$obj = json_decode($text);
	if ($obj->percentage == 100 || $obj->percentage == null) {
		unlink($file);
	}
} else {
	echo json_encode(array("percentage" => null, "message" => null));
}