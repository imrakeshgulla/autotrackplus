<?php
// Captures or compares existing business columns around an additive migration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
$path = '/tmp/autotrackplus-migration-baseline.json';
$mode = $argv[1] ?? '';
if ($mode === 'capture') {
    $baseline = [];
    foreach (['users','vehicles','repairs','notifications','activity_log'] as $table) {
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        $rows = $pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll();
        $baseline[$table] = ['columns'=>$columns, 'hash'=>hash('sha256',json_encode($rows)), 'count'=>count($rows)];
    }
    file_put_contents($path,json_encode($baseline)); chmod($path,0600);
    echo "Captured hashes of original data.\n";
} elseif ($mode === 'compare') {
    if (!is_readable($path)) { fwrite(STDERR, "No baseline found. Capture immediately before migration, and compare before recreating the app container.\n"); exit(1); }
    $baseline = json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    foreach ($baseline as $table=>$expected) {
        $columns=implode(',',array_map(fn($c)=>"`$c`",$expected['columns']));
        $rows=$pdo->query("SELECT $columns FROM `$table` ORDER BY id")->fetchAll();
        if (!hash_equals($expected['hash'],hash('sha256',json_encode($rows)))) throw new RuntimeException("Data changed in $table");
        echo "$table: {$expected['count']} rows, original values unchanged.\n";
    }
} else { fwrite(STDERR,"Use capture immediately before migration, then compare.\n"); exit(1); }
