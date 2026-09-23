<?php
// ============================================================
// 🗄️ Database Session Handler – Auto-Detect Column (access/last_activity)
// Works with both MySQL-style TIMESTAMP and INT based columns
// ============================================================

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private $pdo;
    private $table = 'sessions';
    private $time_column = 'last_activity'; // default
    private $use_timestamp = false;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->detectSchema();
    }

    /**
     * Auto-detect the schema of the sessions table
     */
    private function detectSchema()
    {
        try {
            // Check if 'last_activity' column exists
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
                $this->use_timestamp = false; // INT
            } elseif (isset($columns['access'])) {
                $this->time_column = 'access';
                $this->use_timestamp = true; // TIMESTAMP
            } elseif (isset($columns['timestamp'])) {
                $this->time_column = 'timestamp';
                $this->use_timestamp = true;
            } else {
                // Fallback: Create the column if missing
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
            if ($this->use_timestamp) {
                $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->time_column} < NOW() - INTERVAL '{$maxLifetime} seconds'");
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->time_column} < ?");
                $stmt->execute([time() - $maxLifetime]);
            }
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            error_log("Session GC Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
