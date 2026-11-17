<?php
// MySQL advisory locks serialize requests sharing a session, including regeneration.
final class MysqlSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private PDO $pdo;
    private ?string $lock = null;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function open(string $path, string $name): bool { return true; }
    private function acquire(string $id): void {
        if ($this->lock !== null) $this->release();
        $lock = 'atp_session_' . substr(hash('sha256', $id), 0, 50);
        $q = $this->pdo->prepare('SELECT GET_LOCK(?, 10)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('Could not lock session');
        $this->lock = $lock;
    }
    private function release(): void {
        if ($this->lock !== null) { $q = $this->pdo->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$this->lock]); $this->lock = null; }
    }
    public function close(): bool { $this->release(); return true; }
    public function read(string $id): string|false {
        $this->acquire($id);
        $q = $this->pdo->prepare('SELECT data FROM app_sessions WHERE id=? AND updated_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 HOUR)');
        $q->execute([$id]); $data = $q->fetchColumn(); return $data === false ? '' : $data;
    }
    public function write(string $id, string $data): bool {
        $q = $this->pdo->prepare('INSERT INTO app_sessions (id,data,updated_at) VALUES (?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE data=VALUES(data),updated_at=UTC_TIMESTAMP()');
        return $q->execute([$id,$data]);
    }
    public function destroy(string $id): bool { $q = $this->pdo->prepare('DELETE FROM app_sessions WHERE id=?'); return $q->execute([$id]); }
    public function gc(int $max_lifetime): int|false {
        $q = $this->pdo->prepare('DELETE FROM app_sessions WHERE updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? SECOND)');
        $q->execute([$max_lifetime]); return $q->rowCount();
    }
    public function validateId(string $id): bool {
        $q = $this->pdo->prepare('SELECT 1 FROM app_sessions WHERE id=? AND updated_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 HOUR)');
        $q->execute([$id]); return (bool)$q->fetchColumn();
    }
    public function updateTimestamp(string $id, string $data): bool { return $this->write($id, $data); }
}
