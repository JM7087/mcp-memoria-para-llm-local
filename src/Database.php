<?php

declare(strict_types=1);

namespace McpMemoria;

use PDO;
use PDOException;

/**
 * Classe para gerenciar conexão e operações com SQLite
 */
class Database
{
    private PDO $pdo;
    private string $dbPath;

    public function __construct(string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? __DIR__ . '/../data/memories.db';
        $this->connect();
        $this->createTables();
    }

    private function connect(): void
    {
        $dataDir = dirname($this->dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        try {
            $this->pdo = new PDO("sqlite:{$this->dbPath}");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \RuntimeException("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }

    private function createTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS memories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT UNIQUE NOT NULL,
                content TEXT NOT NULL,
                category TEXT DEFAULT 'general',
                tags TEXT,
                importance INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_memories_key ON memories(key)
        ");

        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_memories_category ON memories(category)
        ");

        $this->pdo->exec("
            CREATE VIRTUAL TABLE IF NOT EXISTS memories_fts USING fts5(
                key,
                content,
                category,
                tags,
                content='memories',
                content_rowid='id'
            )
        ");

        // Triggers para manter FTS sincronizado
        $this->pdo->exec("
            CREATE TRIGGER IF NOT EXISTS memories_ai AFTER INSERT ON memories BEGIN
                INSERT INTO memories_fts(rowid, key, content, category, tags)
                VALUES (new.id, new.key, new.content, new.category, new.tags);
            END
        ");

        $this->pdo->exec("
            CREATE TRIGGER IF NOT EXISTS memories_ad AFTER DELETE ON memories BEGIN
                INSERT INTO memories_fts(memories_fts, rowid, key, content, category, tags)
                VALUES('delete', old.id, old.key, old.content, old.category, old.tags);
            END
        ");

        $this->pdo->exec("
            CREATE TRIGGER IF NOT EXISTS memories_au AFTER UPDATE ON memories BEGIN
                INSERT INTO memories_fts(memories_fts, rowid, key, content, category, tags)
                VALUES('delete', old.id, old.key, old.content, old.category, old.tags);
                INSERT INTO memories_fts(rowid, key, content, category, tags)
                VALUES (new.id, new.key, new.content, new.category, new.tags);
            END
        ");
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
