<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
$q = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()");
if ((int)$q->fetchColumn() !== 0) { fwrite(STDERR, "Database is not empty; initialization refused. Use migration for existing databases.\n"); exit(1); }
$sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
$sql = preg_replace('/^CREATE DATABASE[^;]*;\s*$/m', '', $sql);
$sql = preg_replace('/^USE [^;]*;\s*$/m', '', $sql);
$sql = preg_replace('/-- Seed default admin.*?ON DUPLICATE KEY UPDATE email = email;/s', '', $sql);
$sql = preg_replace('/--[^\n]*/', '', $sql);
foreach (explode(';', $sql) as $statement) { if (trim($statement) !== '') $pdo->exec($statement); }
echo "Initialized empty database without a default administrator. Run bin/migrate.php next.\n";
