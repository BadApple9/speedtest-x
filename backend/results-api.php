<?php

require_once "./SleekDB/SleekDB.php";
require_once "./config.php";

$store = \SleekDB\SleekDB::store('speedlogs', './',[
    'auto_cache' => false,
    'timeout' => 120
]);

$logs = $store
    ->orderBy( 'desc', 'created' )
    ->limit( MAX_LOG_COUNT )
    ->fetch();

$data = [
    'code' => 0,
    'data' => $logs,
];

echo json_encode($data);