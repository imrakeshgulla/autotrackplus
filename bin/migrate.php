<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
if (!$pdo->query("SELECT GET_LOCK('autotrackplus_migrate', 10)")->fetchColumn()) throw new RuntimeException('Migration already running.');
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    foreach (['001_password_login', '002_sessions'] as $version) {
    $q = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?'); $q->execute([$version]);
    if ($q->fetchColumn()) { echo "$version already applied.\n"; continue; }
    $sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/../sql/migrations/' . $version . '.sql'));
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement); if (!$statement) continue;
        // MySQL DDL commits implicitly. Resume safely after a partial migration.
        if (preg_match('/^ALTER TABLE users ADD COLUMN (\w+)/', $statement, $matches)) {
            $q = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name=?");
            $q->execute([$matches[1]]); if ($q->fetchColumn()) continue;
        }
        $pdo->exec($statement);
    }
    $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    echo "Applied $version. Existing rows and IDs preserved.\n";
    }
} finally { $pdo->query("SELECT RELEASE_LOCK('autotrackplus_migrate')"); }
