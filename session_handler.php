<?php
// ============================================================
// ✅ Database Session Handler – PHP 8+ Compatible
// ============================================================

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table = 'sessions';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) {
        return true;
    }
    
    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }
    
    #[\ReturnTypeWillChange]
    public function read($sessionId) {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['data'] : '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table} (id, data, access) VALUES (?, ?, NOW()) 
                 ON CONFLICT (id) DO UPDATE SET data = ?, access = NOW()"
            );
            return $stmt->execute([$sessionId, $data, $data]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    #[\ReturnTypeWillChange]
    public function gc($maxLifetime) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE access < NOW() - INTERVAL ? SECOND");
            return $stmt->execute([$maxLifetime]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
