<?php

class DBStore {
    private $pdo;

    public function __construct($dbFile) {
        if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers())) {
            throw new Exception('SQLite不可用');
        }
        $dir = dirname($dbFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!is_writable($dir)) throw new Exception('data目录不可写');
        $this->pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS store (key TEXT PRIMARY KEY, data TEXT, updated_at TEXT)");
    }

    public function get($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT data FROM store WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row) {
            $data = json_decode($row['data'], true);
            return $data !== null ? $data : $default;
        }
        return $default;
    }

    public function set($key, $data) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO store (key, data, updated_at) VALUES (?, ?, ?)");
        return $stmt->execute([$key, $json, date('Y-m-d H:i:s')]);
    }
}
