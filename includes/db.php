<?php
/**
 * NexusSync - Database Connection + PostgreSQL Session Handler
 */

require_once __DIR__ . '/config.php';

/**
 * Get PDO singleton
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Support Unix socket (host starts with /) and TCP
        if (str_starts_with(DB_HOST, '/')) {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        } else {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=3', DB_HOST, DB_PORT, DB_NAME);
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS ?: null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
        ]);
    }
    return $pdo;
}

/**
 * PostgreSQL-backed session handler for Railway's ephemeral containers
 */
class PgSessionHandler implements SessionHandlerInterface {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function open(string $path, string $name): bool {
        return true;
    }
    
    public function close(): bool {
        return true;
    }
    
    public function read(string $id): string|false {
        $stmt = $this->pdo->prepare(
            "SELECT data FROM php_sessions WHERE id = ? AND last_access > NOW() - (lifetime || ' seconds')::INTERVAL"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }
    
    public function write(string $id, string $data): bool {
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        $stmt = $this->pdo->prepare(
            "INSERT INTO php_sessions (id, data, last_access, lifetime) VALUES (?, ?, NOW(), ?)
             ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_access = NOW(), lifetime = EXCLUDED.lifetime"
        );
        return $stmt->execute([$id, $data, $lifetime]);
    }
    
    public function destroy(string $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function gc(int $max_lifetime): int|false {
        $stmt = $this->pdo->prepare(
            "DELETE FROM php_sessions WHERE last_access < NOW() - (lifetime || ' seconds')::INTERVAL"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}

/**
 * Initialize PostgreSQL-backed sessions
 */
function init_session(): void {
    static $initialized = false;
    if ($initialized) return;
    
    try {
        $pdo = get_db();
        $handler = new PgSessionHandler($pdo);
        session_set_save_handler($handler, true);
    } catch (Exception $e) {
        // Fallback to file sessions if DB is unavailable
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $initialized = true;
}
