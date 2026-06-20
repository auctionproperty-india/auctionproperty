<?php
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table = 'sessions';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }
    
    public function close() {
        return true;
    }
    
    public function read($sessionId) {
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ?");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }
    
    public function write($sessionId, $data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (id, data, access) VALUES (?, ?, NOW()) ON CONFLICT (id) DO UPDATE SET data = ?, access = NOW()");
        return $stmt->execute([$sessionId, $data, $data]);
    }
    
    public function destroy($sessionId) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$sessionId]);
    }
    
    public function gc($maxLifetime) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE access < NOW() - INTERVAL ? SECOND");
        return $stmt->execute([$maxLifetime]);
    }
}
?>
