<?php
/**
 * ============================================================
 * دليل الهاتف الدولي - Database Layer
 * International Phone Directory
 * ============================================================
 * Singleton PDO connection with helper functions
 * Auto-creates database file and tables on first run
 */

require_once __DIR__ . '/config.php';

class Database
{
    /** @var Database|null Singleton instance */
    private static ?Database $instance = null;

    /** @var PDO The PDO connection */
    private PDO $pdo;

    /** @var bool Whether the database has been initialized */
    private bool $initialized = false;

    /**
     * Private constructor - singleton pattern
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }

    /**
     * Get the singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish PDO connection to SQLite
     *
     * @return void
     * @throws \RuntimeException
     */
    private function connect(): void
    {
        $dbDir = dirname(DB_FILE);

        // Ensure database directory exists
        if (!is_dir($dbDir)) {
            if (!@mkdir($dbDir, 0755, true)) {
                throw new \RuntimeException(
                    'Cannot create database directory: ' . $dbDir
                );
            }
        }

        // Ensure database file is writable (or can be created)
        if (file_exists(DB_FILE) && !is_writable(DB_FILE)) {
            throw new \RuntimeException('Database file is not writable: ' . DB_FILE);
        }

        try {
            $dsn = 'sqlite:' . DB_FILE;

            $this->pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // Enable WAL mode for better concurrent read performance
            $this->pdo->exec('PRAGMA journal_mode = WAL;');
            $this->pdo->exec('PRAGMA synchronous = NORMAL;');
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
            $this->pdo->exec('PRAGMA busy_timeout = 5000;');
            $this->pdo->exec('PRAGMA cache_size = -8000;'); // 8MB cache

            // Auto-initialize tables if needed
            if (!$this->initialized) {
                $this->initializeTables();
                $this->initialized = true;
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Initialize database tables from schema file
     *
     * @return void
     */
    private function initializeTables(): void
    {
        if (!file_exists(SCHEMA_FILE)) {
            error_log('Schema file not found: ' . SCHEMA_FILE);
            return;
        }

        $schema = file_get_contents(SCHEMA_FILE);
        if ($schema === false) {
            error_log('Failed to read schema file: ' . SCHEMA_FILE);
            return;
        }

        // Split schema into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            fn($stmt) => $stmt !== ''
        );

        foreach ($statements as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (\PDOException $e) {
                // Table or index may already exist - that's fine
                if (strpos($e->getMessage(), 'already exists') === false) {
                    error_log('Schema error: ' . $e->getMessage());
                }
            }
        }

        // Migrate: add auth_token columns if they don't exist (for existing DBs)
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN auth_token TEXT DEFAULT NULL");
        } catch (\PDOException $e) {
            // Column already exists - ignore
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN auth_token_expires_at TEXT DEFAULT NULL");
        } catch (\PDOException $e) {
            // Column already exists - ignore
        }

        // Migrate: add user_balances table if not exists
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_balances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                plan TEXT NOT NULL,
                balance REAL DEFAULT 0,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now'))
            )");
        } catch (\PDOException $e) {
            error_log('user_balances table creation error: ' . $e->getMessage());
        }
    }

    /**
     * Get the raw PDO instance
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=:name"
        );
        $stmt->execute([':name' => $table]);
        return $stmt->fetch() !== false;
    }

    /**
     * Get the last inserted ID
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if inside a transaction
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Execute a raw SQL query with optional parameters
     *
     * @param string $sql
     * @param array  $params
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row
     *
     * @param string $sql
     * @param array  $params
     * @return array|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Fetch all rows
     *
     * @param string $sql
     * @param array  $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Insert a record into a table
     *
     * @param string $table
     * @param array  $data  Associative array of column => value
     * @return string The last inserted ID
     */
    public function insert(string $table, array $data): string
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Insert data cannot be empty');
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return $this->lastInsertId();
    }

    /**
     * Update records in a table
     *
     * @param string $table
     * @param array  $data  Associative array of column => value
     * @param string $where WHERE clause (without "WHERE" keyword)
     * @param array  $whereParams Parameters for the WHERE clause
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty');
        }

        $setParts = [];
        $params = [];

        foreach ($data as $col => $val) {
            $setParts[] = $this->quoteIdentifier($col) . ' = :' . $col;
            $params[':' . $col] = $val;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $setParts),
            $where
        );

        $params = array_merge($params, $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete records from a table
     *
     * @param string $table
     * @param string $where WHERE clause (without "WHERE" keyword)
     * @param array  $params Parameters for the WHERE clause
     * @return int Number of affected rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            $where
        );
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Count records
     *
     * @param string $table
     * @param string $where Optional WHERE clause
     * @param array  $params
     * @return int
     */
    public function count(string $table, string $where = '', array $params = []): int
    {
        $sql = sprintf('SELECT COUNT(*) as total FROM %s', $this->quoteIdentifier($table));
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $row = $this->fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Quote a table or column identifier
     *
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier(string $identifier): string
    {
        // Remove any existing quotes
        $identifier = trim($identifier, '"\'` ');
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $identifier);
        }
        return '"' . $identifier . '"';
    }
}

// ============================================================
// Global Helper Functions
// ============================================================

/**
 * Get the Database singleton instance
 *
 * @return Database
 */
function db(): Database
{
    return Database::getInstance();
}

/**
 * Execute a query with prepared statements
 *
 * @param string $sql
 * @param array  $params
 * @return \PDOStatement
 */
function query(string $sql, array $params = []): \PDOStatement
{
    return db()->query($sql, $params);
}

/**
 * Fetch a single row
 *
 * @param string $sql
 * @param array  $params
 * @return array|null
 */
function fetch(string $sql, array $params = []): ?array
{
    return db()->fetch($sql, $params);
}

/**
 * Fetch all rows
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function fetchAll(string $sql, array $params = []): array
{
    return db()->fetchAll($sql, $params);
}

/**
 * Insert a record
 *
 * @param string $table
 * @param array  $data
 * @return string
 */
function insert(string $table, array $data): string
{
    return db()->insert($table, $data);
}

/**
 * Update records
 *
 * @param string $table
 * @param array  $data
 * @param string $where
 * @param array  $whereParams
 * @return int
 */
function update(string $table, array $data, string $where, array $whereParams = []): int
{
    return db()->update($table, $data, $where, $whereParams);
}

/**
 * Delete records
 *
 * @param string $table
 * @param string $where
 * @param array  $params
 * @return int
 */
function delete(string $table, string $where, array $params = []): int
{
    return db()->delete($table, $where, $params);
}

/**
 * Count records
 *
 * @param string $table
 * @param string $where
 * @param array  $params
 * @return int
 */
function countRecords(string $table, string $where = '', array $params = []): int
{
    return db()->count($table, $where, $params);
}
