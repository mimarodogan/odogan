<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(array $cfg)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );
        $this->pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Buffered mode — bir statement'ın sonucu fetch edilmeden bir sonraki
            // query atılırsa "Cannot execute queries while other unbuffered queries
            // are active" hatasını engeller. Özellikle DDL + INSERT sıralı çalışan
            // migration runner için kritik.
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$cfg['charset']}",
        ]);
        // Runtime garanti — PDO sürümüne göre constructor option ignore
        // edilebilir; setAttribute ile zorla buffered moda al.
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            try {
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            } catch (\Throwable) { /* ignore — bazı driver'larda set edilemez */ }
        }
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self([
                'host' => (string) Config::get('DB_HOST', '127.0.0.1'),
                'port' => (string) Config::get('DB_PORT', '3306'),
                'database' => (string) Config::get('DB_DATABASE', 'odogan_cms'),
                'username' => (string) Config::get('DB_USERNAME', 'root'),
                'password' => (string) Config::get('DB_PASSWORD', ''),
                'charset' => (string) Config::get('DB_CHARSET', 'utf8mb4'),
            ]);
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->run($sql, $params);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->run($sql, $params);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        return $rows;
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->run($sql, $params);
        $value = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $value;
    }

    public function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $cols),
            implode(', ', $placeholders)
        );
        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $v;
        }
        $stmt = $this->run($sql, $params);
        $stmt->closeCursor();
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "`$k` = :s_$k";
            $params[":s_$k"] = $v;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
        $stmt = $this->run($sql, $params + $whereParams);
        $count = $stmt->rowCount();
        $stmt->closeCursor();
        return $count;
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        $stmt = $this->run($sql, $params);
        $count = $stmt->rowCount();
        $stmt->closeCursor();
        return $count;
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
