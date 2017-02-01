<?php

require_once dirname(__FILE__).'/../../lib/database/database.php';
error_reporting(0);
header('Content-Type: text/plain');

$db = new cm_db();
$dbtime = $db->now();
$phptime = date('Y-m-d H:i:s');

$diff = abs(strtotime($dbtime) - strtotime($phptime));
if ($diff > 600) {
	echo 'NG MySQL time and PHP time differ by over 10 minutes. Check time zone settings, make sure time zone data is present in MySQL, and run /admin/timecheck.php to verify.';
} else {
	echo 'OK MySQL time and PHP time are synchronized.';
}