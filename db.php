<?php
// db.php — PDO connection (SQLite)
function db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dbPath = __DIR__ . '/filetransfer.db';
    $dsn  = "sqlite:$dbPath";
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, null, null, $opts);
    
    // Initialize database schema if it doesn't exist
    initializeDatabase($pdo);
    
    return $pdo;
}

function initializeDatabase($pdo) {
    // Create files table
    $pdo->exec("CREATE TABLE IF NOT EXISTS files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        orig_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        mime VARCHAR(120) DEFAULT NULL,
        size BIGINT DEFAULT 0,
        max_downloads INTEGER DEFAULT NULL,
        downloads INTEGER DEFAULT 0,
        expires_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expires_at ON files(expires_at)");
    
    // Create users table for admin authentication
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_admin INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if default admin exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Create default admin user (username: admin, password: 87654321)
        $passwordHash = password_hash('87654321', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_admin) VALUES (:username, :password_hash, 1)");
        $stmt->execute([
            ':username' => 'admin',
            ':password_hash' => $passwordHash
        ]);
    }
}
