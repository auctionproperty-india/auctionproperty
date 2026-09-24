<?php
// ============================================================
// 🗄️ Database Session Handler – Auto-Detect + 90-Day Minimum GC
// ============================================================

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private $pdo;
    private $table = 'sessions';
    private $time_column = 'last_activity';
    private $use_timestamp = false;
    private $min_lifetime = 7776000; // 90 days in seconds

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->detectSchema();
    }

    private function detectSchema()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT column_name, data_type 
                FROM information_schema.columns 
                WHERE table_name = 'sessions'
            ");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[$row['column_name']] = $row['data_type'];
            }

            if (isset($columns['last_activity'])) {
                $this->time_column = 'last_activity';
                $this->use_timestamp = false;
            } elseif (isset($columns['access'])) {
                $this->time_column = 'access';
                $this->use_timestamp = true;
            } elseif (isset($columns['timestamp'])) {
                $this->time_column = 'timestamp';
                $this->use_timestamp = true;
            } else {
                $this->pdo->exec("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS last_activity INT");
                $this->time_column = 'last_activity';
                $this->use_timestamp = false;
            }
        } catch (Exception $e) {
            error_log("Session schema detection failed: " . $e->getMessage());
        }
    }

    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) { return true; }

    #[\ReturnTypeWillChange]
    public function close() { return true; }

    #[\ReturnTypeWillChange]
    public function read($sessionId)
    {
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
    public function write($sessionId, $data)
    {
        try {
            $time_value = $this->use_timestamp ? date('Y-m-d H:i:s') : time();
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table} (id, data, {$this->time_column}) 
                 VALUES (?, ?, ?) 
                 ON CONFLICT (id) 
                 DO UPDATE SET data = EXCLUDED.data, {$this->time_column} = EXCLUDED.{$this->time_column}"
            );
            return $stmt->execute([$sessionId, $data, $time_value]);
        } catch (Exception $e) {
            error_log("Session Write Error: " . $e->getMessage());
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($sessionId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            error_log("Session Destroy Error: " . $e->getMessage());
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function gc($maxLifetime)
    {
        try {
            // 🔥 FIX: Enforce minimum 90 days – never delete earlier
            if ($maxLifetime < $this->min_lifetime) {
                $maxLifetime = $this->min_lifetime;
            }
            
            if ($this->use_timestamp) {
                $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->time_column} < NOW() - INTERVAL '{$maxLifetime} seconds'");
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->time_column} < ?");
                $stmt->execute([time() - $maxLifetime]);
            }
            return true;
        } catch (Exception $e) {
            error_log("Session GC Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
