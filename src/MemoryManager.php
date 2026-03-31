<?php

declare(strict_types=1);

namespace McpMemoria;

use PDO;

/**
 * Classe para gerenciar memórias da LLM
 */
class MemoryManager
{
    private PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->getPdo();
    }

    /**
     * Salvar uma nova memória
     */
    public function save(
        string $key,
        string $content,
        string $category = 'general',
        array $tags = [],
        int $importance = 1
    ): array {
        $tagsStr = !empty($tags) ? implode(',', $tags) : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO memories (key, content, category, tags, importance)
            VALUES (:key, :content, :category, :tags, :importance)
            ON CONFLICT(key) DO UPDATE SET
                content = excluded.content,
                category = excluded.category,
                tags = excluded.tags,
                importance = excluded.importance,
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':key' => $key,
            ':content' => $content,
            ':category' => $category,
            ':tags' => $tagsStr,
            ':importance' => $importance
        ]);

        return $this->getByKey($key);
    }

    /**
     * Buscar memória por chave
     */
    public function getByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM memories WHERE key = :key");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch();

        if ($result && $result['tags']) {
            $result['tags'] = explode(',', $result['tags']);
        }

        return $result ?: null;
    }

    /**
     * Buscar memórias por categoria
     */
    public function getByCategory(string $category, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM memories 
            WHERE category = :category 
            ORDER BY importance DESC, updated_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':category', $category);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->processResults($stmt->fetchAll());
    }

    /**
     * Buscar memórias por texto (full-text search)
     */
    public function search(string $query, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.* FROM memories m
            JOIN memories_fts fts ON m.id = fts.rowid
            WHERE memories_fts MATCH :query
            ORDER BY rank, m.importance DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->processResults($stmt->fetchAll());
    }

    /**
     * Buscar memórias por tags
     */
    public function getByTags(array $tags, int $limit = 50): array
    {
        $conditions = [];
        $params = [];
        
        foreach ($tags as $i => $tag) {
            $conditions[] = "tags LIKE :tag{$i}";
            $params[":tag{$i}"] = "%{$tag}%";
        }
        
        $sql = "SELECT * FROM memories WHERE " . implode(' OR ', $conditions) . "
                ORDER BY importance DESC, updated_at DESC LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->processResults($stmt->fetchAll());
    }

    /**
     * Listar todas as memórias
     */
    public function list(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM memories 
            ORDER BY importance DESC, updated_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->processResults($stmt->fetchAll());
    }

    /**
     * Deletar uma memória
     */
    public function delete(string $key): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM memories WHERE key = :key");
        $stmt->execute([':key' => $key]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deletar memórias por categoria
     */
    public function deleteByCategory(string $category): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM memories WHERE category = :category");
        $stmt->execute([':category' => $category]);
        return $stmt->rowCount();
    }

    /**
     * Obter estatísticas das memórias
     */
    public function getStats(): array
    {
        $total = $this->pdo->query("SELECT COUNT(*) FROM memories")->fetchColumn();
        
        $byCategory = $this->pdo->query("
            SELECT category, COUNT(*) as count 
            FROM memories 
            GROUP BY category
        ")->fetchAll();
        
        $byImportance = $this->pdo->query("
            SELECT importance, COUNT(*) as count 
            FROM memories 
            GROUP BY importance 
            ORDER BY importance DESC
        ")->fetchAll();

        return [
            'total' => (int) $total,
            'by_category' => $byCategory,
            'by_importance' => $byImportance
        ];
    }

    /**
     * Obter memórias mais importantes
     */
    public function getMostImportant(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM memories 
            ORDER BY importance DESC, updated_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->processResults($stmt->fetchAll());
    }

    /**
     * Processar resultados convertendo tags em array
     */
    private function processResults(array $results): array
    {
        return array_map(function ($row) {
            if ($row['tags']) {
                $row['tags'] = explode(',', $row['tags']);
            } else {
                $row['tags'] = [];
            }
            return $row;
        }, $results);
    }
}
