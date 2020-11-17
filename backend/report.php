<?php

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

$store = \SleekDB\SleekDB::store('speedlogs', './',[
    'auto_cache' => false,
    'timeout' => 120
]);

$ip = filter_var($_POST['ip'], FILTER_SANITIZE_STRING);
$ipMask = preg_replace('/((?:\d{1,3}\.){3})\d{1,3}/','$1*', $ip);

$reportData = [
    "ip" => $ipMask,
    "isp" => filter_var($_POST['isp'], FILTER_SANITIZE_STRING),
    "addr" => filter_var($_POST['addr'], FILTER_SANITIZE_STRING),
    "dspeed" => filter_var($_POST['dspeed'], FILTER_SANITIZE_STRING),
    "uspeed" => filter_var($_POST['uspeed'], FILTER_SANITIZE_STRING),
    "ping" => filter_var($_POST['ping'], FILTER_SANITIZE_STRING),
    "jitter" => filter_var($_POST['jitter'], FILTER_SANITIZE_STRING),
    "created" => date('Y-m-d H:i:s', time()),
];

if (empty($reportData['ip'])) exit;

$oldLog = $store->where('ip', '=', $reportData['ip'])->fetch();

if (is_array($oldLog) && empty($oldLog)) {
     $results = $store->insert($reportData);
     if ($results['_id'] > MAX_LOG_COUNT) {
         $store->where('_id', '=', $results['_id'] - MAX_LOG_COUNT)->delete();
     }
} else {
    $ip = $reportData['ip'];
    unset($reportData['ip']);
    $store->where('ip', '=', $ip)->update($reportData);
}