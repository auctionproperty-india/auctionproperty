<?php
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table = 'sessions';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) { return true; }
    
    #[\ReturnTypeWillChange]
    public function close() { return true; }
    
    #[\ReturnTypeWillChange]
    public function read($sessionId) {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['data'] : '';
        } catch (Exception $e) { 
            error_log("Session Read Error: " . $e->getMessage());
            return ''; 
        }
    }
    
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table} (id, data, last_activity) VALUES (?, ?, ?) 
                 ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_activity = EXCLUDED.last_activity"
            );
            return $stmt->execute([$sessionId, $data, time()]);
        } catch (Exception $e) { 
            error_log("Session Write Error: " . $e->getMessage());
            return false; 
        }
    }
    
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) { 
            error_log("Session Destroy Error: " . $e->getMessage());
            return false; 
        }
    }
    
    #[\ReturnTypeWillChange]
    public function gc($maxLifetime) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_activity < ?");
            return $stmt->execute([time() - $maxLifetime]);
        } catch (Exception $e) { 
            error_log("Session GC Error: " . $e->getMessage());
            return false; 
        }
    }
}
?>
