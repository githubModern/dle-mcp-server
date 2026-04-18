<?php
class MCPServer {

    private $tools = [];
    private $tokenId = 0;

    public function __construct($tokenId) {
        $this->tokenId = intval($tokenId);
    }

    public function register($name, $description, $inputSchema, $handler) {
        $this->tools[$name] = [
            'name'        => $name,
            'description' => $description,
            'inputSchema' => $inputSchema,
            'handler'     => $handler,
        ];
    }

    public function run() {
        $this->handleRequest();
    }

    private function handleRequest() {
        header('Cache-Control: no-cache');
        header('X-Robots-Tag: noindex');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET')       { $this->handleSSE(); }
        elseif ($method === 'POST')  { $this->handlePost(); }
        else { http_response_code(405); exit; }
    }

    private function handlePost() {
        header('Content-Type: application/json; charset=utf-8');
        $raw = file_get_contents('php://input');
        $req = json_decode($raw, true);
        if ($req === null) { $this->jsonRpcError(null, -32700, 'Parse error'); return; }
        $id     = $req['id']     ?? null;
        $method = $req['method'] ?? '';
        $params = is_array($req['params'] ?? null) ? $req['params'] : [];
        switch ($method) {
            case 'initialize':
                $this->handleInitialize($id, $params);
                break;
            case 'notifications/initialized':
                http_response_code(200);
                break;
            case 'tools/list':
                $this->handleToolsList($id);
                break;
            case 'tools/call':
                $this->handleToolsCall($id, $params);
                break;
            default:
                $this->jsonRpcError($id, -32601, 'Method not found: ' . $method);
        }
    }

    private function handleInitialize($id, $params) {
        $this->jsonRpcResult($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => new stdClass()],
            'serverInfo'      => ['name' => 'DLE MCP Server', 'version' => '1.0.0'],
        ]);
    }

    private function handleToolsList($id) {
        $tools = [];
        foreach ($this->tools as $t) {
            $tools[] = [
                'name'        => $t['name'],
                'description' => $t['description'],
                'inputSchema' => $t['inputSchema'],
            ];
        }
        $this->jsonRpcResult($id, ['tools' => $tools]);
    }

    private function handleToolsCall($id, $params) {
        $name = $params['name']      ?? '';
        $args = $params['arguments'] ?? [];
        if (!is_array($args)) $args = [];
        if (!isset($this->tools[$name])) {
            $this->jsonRpcError($id, -32602, 'Unknown tool: ' . $name);
            return;
        }
        try {
            $result = call_user_func($this->tools[$name]['handler'], $args);
            $this->logCall($name, $args, 'ok');
            $this->jsonRpcResult($id, [
                'content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_UNICODE)]]
            ]);
        } catch (\Throwable $e) {
            $this->logCall($name, $args, 'error');
            $this->jsonRpcResult($id, [
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                'isError'  => true,
            ]);
        }
    }

    private function handleSSE() {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        $uri = '/engine/modules/mcp/mcp.php';
        echo "event: endpoint\ndata: " . json_encode(['uri' => $uri]) . "\n\n";
        if (ob_get_level()) { ob_flush(); }
        flush();
        $i = 0;
        while ($i < 30) {
            sleep(1);
            if (connection_aborted()) break;
            echo ": heartbeat\n\n";
            if (ob_get_level()) { ob_flush(); }
            flush();
            $i++;
        }
    }

    private function logCall($tool, $args, $status) {
        global $db;
        $params  = substr(json_encode($args), 0, 2000);
        $ip      = $db->safesql($_SERVER['REMOTE_ADDR'] ?? '');
        $db->query("INSERT INTO " . PREFIX . "_mcp_logs (token_id, tool, params, response_status, ip, created_at) VALUES (" . $this->tokenId . ", '" . $db->safesql($tool) . "', '" . $db->safesql($params) . "', '" . $db->safesql($status) . "', '" . $ip . "', " . time() . ")");
    }

    private function jsonRpcError($id, $code, $message) {
        echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }

    private function jsonRpcResult($id, $result) {
        echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_UNICODE);
    }
}
