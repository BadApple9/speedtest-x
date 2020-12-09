<?php

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

function maskLastSegment($ip) {
    $ipaddr = inet_pton($ip);
    if (strlen($ipaddr) == 4) {
        $ipaddr[3] = chr(0);
    } elseif (strlen($ipaddr) == 16) {
        $ipaddr[14] = chr(0);
        $ipaddr[15] = chr(0);
    } else {
        return "";
    }
    return rtrim(inet_ntop($ipaddr),"0")."*";
}

$store = \SleekDB\SleekDB::store('speedlogs', './',[
    'auto_cache' => false,
    'timeout' => 120
]);

$reportData = [
    "key" => sha1(filter_var($_POST['key'], FILTER_SANITIZE_STRING)),
    "ip" => maskLastSegment(filter_var($_POST['ip'], FILTER_SANITIZE_STRING)),
    "isp" => filter_var($_POST['isp'], FILTER_SANITIZE_STRING),
    "addr" => filter_var($_POST['addr'], FILTER_SANITIZE_STRING),
    "dspeed" => (double) filter_var($_POST['dspeed'], FILTER_SANITIZE_STRING),
    "uspeed" => (double) filter_var($_POST['uspeed'], FILTER_SANITIZE_STRING),
    "ping" => (double) filter_var($_POST['ping'], FILTER_SANITIZE_STRING),
    "jitter" => (double) filter_var($_POST['jitter'], FILTER_SANITIZE_STRING),
    "created" => date('Y-m-d H:i:s', time()),
];

if (empty($reportData['ip'])) exit;

if (SAME_IP_MULTI_LOGS) {
    $oldLog = $store->where('key', '=', $reportData['key'])->fetch();
} else {
    $oldLog = $store->where('ip', '=', $reportData['ip'])->orderBy( 'desc', '_id' )->fetch();
}

if (is_array($oldLog) && empty($oldLog)) {
     $results = $store->insert($reportData);
     if ($results['_id'] > MAX_LOG_COUNT) {
         $store->where('_id', '=', $results['_id'] - MAX_LOG_COUNT)->delete();
     }
} else {
    $id = $oldLog[0]['_id'];
    if (SAME_IP_MULTI_LOGS) {
        $key = $reportData['key'];
        unset($reportData['key']);
        $store->where('_id', '=', $id)->update($reportData);
    } else {
        $ip = $reportData['ip'];
        unset($reportData['ip']);
        $store->where('_id', '=', $id)->update($reportData);
    }
}