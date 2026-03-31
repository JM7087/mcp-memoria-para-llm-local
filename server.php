#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * MCP Memoria - Servidor de Memória para LLM
 * 
 * Ponto de entrada para o servidor MCP que permite
 * LLMs salvarem e consultarem informações importantes.
 */

// Autoload
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/MemoryManager.php';
require_once __DIR__ . '/src/McpServer.php';

use McpMemoria\Database;
use McpMemoria\MemoryManager;
use McpMemoria\McpServer;

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/data/error.log');

// Inicializa o banco de dados
$database = new Database(__DIR__ . '/data/memories.db');

// Inicializa o gerenciador de memórias
$memoryManager = new MemoryManager($database);

// Inicia o servidor MCP
$server = new McpServer($memoryManager);
$server->run();
