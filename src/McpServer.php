<?php

declare(strict_types=1);

namespace McpMemoria;

/**
 * Servidor MCP para gerenciamento de memórias de LLM
 * Implementa o Model Context Protocol via JSON-RPC sobre stdio
 */
class McpServer
{
    private const VERSION = '1.0.0';
    private const PROTOCOL_VERSION = '2024-11-05';

    private MemoryManager $memoryManager;
    private bool $running = false;

    public function __construct(MemoryManager $memoryManager)
    {
        $this->memoryManager = $memoryManager;
    }

    /**
     * Inicia o servidor MCP
     */
    public function run(): void
    {
        $this->running = true;
        $this->log("Servidor MCP de Memória iniciado");

        while ($this->running) {
            $line = fgets(STDIN);

            if ($line === false) {
                break;
            }

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            try {
                $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $response = $this->handleRequest($request);

                if ($response !== null) {
                    $this->sendResponse($response);
                }
            } catch (\JsonException $e) {
                $this->sendError(null, -32700, "Parse error: " . $e->getMessage());
            } catch (\Exception $e) {
                $this->sendError(null, -32603, "Internal error: " . $e->getMessage());
            }
        }
    }

    /**
     * Processa uma requisição JSON-RPC
     */
    private function handleRequest(array $request): ?array
    {
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        // Notificações não precisam de resposta
        if ($id === null && !in_array($method, ['initialize', 'initialized'])) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->handleInitialize($id, $params),
            'initialized' => null,
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $params),
            'shutdown' => $this->handleShutdown($id),
            default => $this->createError($id, -32601, "Method not found: {$method}")
        };
    }

    /**
     * Handle initialize
     */
    private function handleInitialize(mixed $id, array $params): array
    {
        return $this->createResult($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false
                ]
            ],
            'serverInfo' => [
                'name' => 'mcp-memoria-php',
                'version' => self::VERSION
            ]
        ]);
    }

    /**
     * Lista todas as ferramentas disponíveis
     */
    private function handleToolsList(mixed $id): array
    {
        $tools = [
            [
                'name' => 'memory_save',
                'description' => 'Salva uma informação importante na memória persistente. Use para guardar fatos, preferências, decisões ou qualquer informação que deve ser lembrada em conversas futuras.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'Identificador único para esta memória (ex: user_name, project_goal, important_decision_1)'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'O conteúdo/informação a ser salva'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Categoria da memória (ex: preferences, facts, decisions, context)',
                            'default' => 'general'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Tags para facilitar busca (ex: ["importante", "projeto", "config"])'
                        ],
                        'importance' => [
                            'type' => 'integer',
                            'description' => 'Nível de importância de 1 a 5 (5 = muito importante)',
                            'minimum' => 1,
                            'maximum' => 5,
                            'default' => 1
                        ]
                    ],
                    'required' => ['key', 'content']
                ]
            ],
            [
                'name' => 'memory_get',
                'description' => 'Recupera uma memória específica pela sua chave única.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'A chave única da memória a ser recuperada'
                        ]
                    ],
                    'required' => ['key']
                ]
            ],
            [
                'name' => 'memory_search',
                'description' => 'Busca memórias por texto usando busca full-text. Encontra memórias que contêm as palavras pesquisadas.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Texto a ser pesquisado nas memórias'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Número máximo de resultados',
                            'default' => 20
                        ]
                    ],
                    'required' => ['query']
                ]
            ],
            [
                'name' => 'memory_list',
                'description' => 'Lista todas as memórias armazenadas, ordenadas por importância e data.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Número máximo de memórias a retornar',
                            'default' => 100
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Número de memórias a pular (para paginação)',
                            'default' => 0
                        ]
                    ]
                ]
            ],
            [
                'name' => 'memory_list_by_category',
                'description' => 'Lista memórias de uma categoria específica.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'A categoria das memórias a listar'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Número máximo de resultados',
                            'default' => 50
                        ]
                    ],
                    'required' => ['category']
                ]
            ],
            [
                'name' => 'memory_list_by_tags',
                'description' => 'Busca memórias que possuem uma ou mais das tags especificadas.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Lista de tags para filtrar'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Número máximo de resultados',
                            'default' => 50
                        ]
                    ],
                    'required' => ['tags']
                ]
            ],
            [
                'name' => 'memory_delete',
                'description' => 'Remove uma memória específica pelo sua chave.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'A chave única da memória a ser deletada'
                        ]
                    ],
                    'required' => ['key']
                ]
            ],
            [
                'name' => 'memory_delete_by_category',
                'description' => 'Remove todas as memórias de uma categoria.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'A categoria das memórias a deletar'
                        ]
                    ],
                    'required' => ['category']
                ]
            ],
            [
                'name' => 'memory_stats',
                'description' => 'Retorna estatísticas sobre as memórias armazenadas.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ]
            ],
            [
                'name' => 'memory_important',
                'description' => 'Lista as memórias mais importantes (maior nível de importância).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Número máximo de resultados',
                            'default' => 10
                        ]
                    ]
                ]
            ]
        ];

        return $this->createResult($id, ['tools' => $tools]);
    }

    /**
     * Executa uma ferramenta
     */
    private function handleToolsCall(mixed $id, array $params): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        try {
            $result = match ($toolName) {
                'memory_save' => $this->toolMemorySave($arguments),
                'memory_get' => $this->toolMemoryGet($arguments),
                'memory_search' => $this->toolMemorySearch($arguments),
                'memory_list' => $this->toolMemoryList($arguments),
                'memory_list_by_category' => $this->toolMemoryListByCategory($arguments),
                'memory_list_by_tags' => $this->toolMemoryListByTags($arguments),
                'memory_delete' => $this->toolMemoryDelete($arguments),
                'memory_delete_by_category' => $this->toolMemoryDeleteByCategory($arguments),
                'memory_stats' => $this->toolMemoryStats(),
                'memory_important' => $this->toolMemoryImportant($arguments),
                default => throw new \InvalidArgumentException("Unknown tool: {$toolName}")
            };

            return $this->createResult($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return $this->createResult($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'error' => true,
                            'message' => $e->getMessage()
                        ], JSON_UNESCAPED_UNICODE)
                    ]
                ],
                'isError' => true
            ]);
        }
    }

    // Tool implementations

    private function toolMemorySave(array $args): array
    {
        $key = $args['key'] ?? throw new \InvalidArgumentException('key is required');
        $content = $args['content'] ?? throw new \InvalidArgumentException('content is required');
        $category = $args['category'] ?? 'general';
        $tags = $args['tags'] ?? [];
        $importance = (int) ($args['importance'] ?? 1);

        $memory = $this->memoryManager->save($key, $content, $category, $tags, $importance);

        return [
            'success' => true,
            'message' => "Memória salva com sucesso",
            'memory' => $memory
        ];
    }

    private function toolMemoryGet(array $args): array
    {
        $key = $args['key'] ?? throw new \InvalidArgumentException('key is required');
        $memory = $this->memoryManager->getByKey($key);

        if ($memory === null) {
            return [
                'found' => false,
                'message' => "Memória não encontrada com a chave: {$key}"
            ];
        }

        return [
            'found' => true,
            'memory' => $memory
        ];
    }

    private function toolMemorySearch(array $args): array
    {
        $query = $args['query'] ?? throw new \InvalidArgumentException('query is required');
        $limit = (int) ($args['limit'] ?? 20);

        $memories = $this->memoryManager->search($query, $limit);

        return [
            'count' => count($memories),
            'query' => $query,
            'memories' => $memories
        ];
    }

    private function toolMemoryList(array $args): array
    {
        $limit = (int) ($args['limit'] ?? 100);
        $offset = (int) ($args['offset'] ?? 0);

        $memories = $this->memoryManager->list($limit, $offset);

        return [
            'count' => count($memories),
            'limit' => $limit,
            'offset' => $offset,
            'memories' => $memories
        ];
    }

    private function toolMemoryListByCategory(array $args): array
    {
        $category = $args['category'] ?? throw new \InvalidArgumentException('category is required');
        $limit = (int) ($args['limit'] ?? 50);

        $memories = $this->memoryManager->getByCategory($category, $limit);

        return [
            'count' => count($memories),
            'category' => $category,
            'memories' => $memories
        ];
    }

    private function toolMemoryListByTags(array $args): array
    {
        $tags = $args['tags'] ?? throw new \InvalidArgumentException('tags is required');
        $limit = (int) ($args['limit'] ?? 50);

        $memories = $this->memoryManager->getByTags($tags, $limit);

        return [
            'count' => count($memories),
            'tags' => $tags,
            'memories' => $memories
        ];
    }

    private function toolMemoryDelete(array $args): array
    {
        $key = $args['key'] ?? throw new \InvalidArgumentException('key is required');
        $deleted = $this->memoryManager->delete($key);

        return [
            'success' => $deleted,
            'message' => $deleted
                ? "Memória '{$key}' deletada com sucesso"
                : "Memória '{$key}' não encontrada"
        ];
    }

    private function toolMemoryDeleteByCategory(array $args): array
    {
        $category = $args['category'] ?? throw new \InvalidArgumentException('category is required');
        $count = $this->memoryManager->deleteByCategory($category);

        return [
            'success' => true,
            'deleted_count' => $count,
            'message' => "{$count} memória(s) da categoria '{$category}' deletada(s)"
        ];
    }

    private function toolMemoryStats(): array
    {
        return $this->memoryManager->getStats();
    }

    private function toolMemoryImportant(array $args): array
    {
        $limit = (int) ($args['limit'] ?? 10);
        $memories = $this->memoryManager->getMostImportant($limit);

        return [
            'count' => count($memories),
            'memories' => $memories
        ];
    }

    /**
     * Handle shutdown
     */
    private function handleShutdown(mixed $id): array
    {
        $this->running = false;
        return $this->createResult($id, null);
    }

    /**
     * Cria uma resposta de sucesso
     */
    private function createResult(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
    }

    /**
     * Cria uma resposta de erro
     */
    private function createError(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }

    /**
     * Envia resposta para stdout
     */
    private function sendResponse(array $response): void
    {
        echo json_encode($response, JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Envia erro para stdout
     */
    private function sendError(mixed $id, int $code, string $message): void
    {
        $this->sendResponse($this->createError($id, $code, $message));
    }

    /**
     * Log para stderr (não interfere com comunicação MCP)
     */
    private function log(string $message): void
    {
        fwrite(STDERR, "[MCP-Memoria] {$message}\n");
    }
}
