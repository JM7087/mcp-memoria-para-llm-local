# MCP Memoria para LLMs local

Servidor MCP (Model Context Protocol) de memória persistente para LLMs, implementado em PHP com SQLite.

## Funcionalidades

Este MCP permite que LLMs salvem e consultem informações importantes entre conversas:

- **Salvar memórias** com chave única, conteúdo, categoria, tags e nível de importância
- **Busca full-text** nas memórias armazenadas
- **Filtrar por categoria** ou tags
- **Listar memórias** ordenadas por importância
- **Deletar memórias** individuais ou por categoria
- **Estatísticas** sobre as memórias armazenadas

## Requisitos

- PHP 8.1 ou superior
- Extensões PHP: `pdo`, `pdo_sqlite`, `json`

## Instalação

1. Clone ou copie este projeto
2. Certifique-se que o PHP está instalado e acessível pelo PATH

```bash
# Verificar versão do PHP
php -v

# Verificar extensões
php -m | findstr sqlite
```

## Configuração no VS Code

Adicione o conteúdo do arquivo `.vscode/mcp.json` no mcp.json do lmstudio, ajustando o caminho para o `server.php`:

```json
{
    "mcpServers": {
        "memoria": {
            "command": "php",
            "args": [
                "C:\\caminho\\para\\mcpMemoria\\server.php"
            ]
        }
    }
}
```

## Configuração no LM Studio

Para que o MCP funcione corretamente, adicione o seguinte prompt de sistema no LM Studio:

```
## Memória Persistente — OBRIGATÓRIO

Ada SEMPRE executa `memory_search` antes de responder qualquer mensagem.
Sem exceção. Mesmo que pareça desnecessário.

**Fluxo obrigatório a cada mensagem:**
1. CHAMAR `memory_search, memory_get é memory_list ` com palavras-chave da pergunta
2. Ler os resultados
3. Só então formular a resposta

**Exemplos:**
- "qual o nome da minha mãe?" → buscar: "mãe nome"
- "onde nasci?" → buscar: "nascimento cidade"
- "o que você sabe sobre mim?" → usar `memory_list`

**AO INICIAR QUALQUER CONVERSA**,  executa `memory_list` imediatamente
para carregar todo o contexto disponível sobre o usuário.
Isso acontece SEMPRE, na primeira mensagem, sem exceção.

**Durante a conversa**, ao receber uma pergunta sobre informações pessoais:
1. Verificar o contexto já carregado do `memory_list`
2. Se não encontrar, executar `memory_search` com MÚLTIPLOS termos:
   - Ex: "onde estudou ou trabalhou" → buscar também "educação", "carreira", "curso"
3. Se ainda não encontrar, informar que não há registro

**Salvar (`memory_save`)** — executar imediatamente quando o usuário mencionar
qualquer informação pessoal, decisão de projeto ou pedir para lembrar ou não esqueça de algo.
Também quando o usuário corrigir ela em alguma coisa que ela errou.
Ada salva sem perguntar e confirma naturalmente na resposta.

**Categorias:** perfil, projetos, preferencias, decisoes, tecnico
Ada não pergunta se deve salvar — simplesmente salva o que for relevante e confirma de forma natural na resposta.
```

## Ferramentas Disponíveis

### memory_save
Salva uma informação importante na memória.

```json
{
    "key": "user_preference_theme",
    "content": "O usuário prefere temas escuros",
    "category": "preferences",
    "tags": ["ui", "visual"],
    "importance": 3
}
```

### memory_get
Recupera uma memória pela chave.

```json
{
    "key": "user_preference_theme"
}
```

### memory_search
Busca memórias por texto.

```json
{
    "query": "preferência tema",
    "limit": 10
}
```

### memory_list
Lista todas as memórias.

```json
{
    "limit": 50,
    "offset": 0
}
```

### memory_list_by_category
Lista memórias de uma categoria.

```json
{
    "category": "preferences"
}
```

### memory_list_by_tags
Busca memórias por tags.

```json
{
    "tags": ["importante", "projeto"]
}
```

### memory_delete
Remove uma memória.

```json
{
    "key": "user_preference_theme"
}
```

### memory_delete_by_category
Remove todas memórias de uma categoria.

```json
{
    "category": "temporary"
}
```

### memory_stats
Retorna estatísticas das memórias.

### memory_important
Lista as memórias mais importantes.

```json
{
    "limit": 10
}
```

## Estrutura do Projeto

```
mcpMemoria/
├── server.php           # Ponto de entrada do servidor
├── composer.json        # Configuração do projeto
├── README.md           
├── src/
│   ├── Database.php     # Gerenciamento do SQLite
│   ├── MemoryManager.php # Operações de memória
│   └── McpServer.php    # Servidor MCP JSON-RPC
├── data/
│   └── memories.db      # Banco de dados (criado automaticamente)
└── .vscode/
    └── mcp.json         # Configuração do MCP para VS Code
```

## Testando

Você pode testar o servidor manualmente:

```bash
# Iniciar servidor
php server.php

# Em seguida, digite comandos JSON-RPC:
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"memory_save","arguments":{"key":"test","content":"Teste de memória"}}}
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"memory_list","arguments":{}}}
```

## Contribuição

Contribuições são bem-vindas! Se você encontrar algum problema ou tiver alguma sugestão de melhoria, por favor, abra uma issue ou envie um pull request.

## Créditos

- Desenvolvido por [João Marcos](https://links.jm7087.com/)

## Licença

Este projeto é licenciado sob a Licença MIT - veja o arquivo [LICENSE](LICENSE) para mais detalhes.
