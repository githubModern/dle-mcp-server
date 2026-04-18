<?php
define('DATALIFEENGINE', true);
define('ROOT_DIR', dirname(dirname(dirname(dirname(__FILE__)))));
define('ENGINE_DIR', ROOT_DIR . '/engine');

require_once(ENGINE_DIR . '/classes/plugins.class.php');
require_once(DLEPlugins::Check(ENGINE_DIR . '/data/config.php'));
require_once(DLEPlugins::Check(ENGINE_DIR . '/data/dbconfig.php'));
require_once(DLEPlugins::Check(ENGINE_DIR . '/classes/mysql.php'));

$db    = new db;
$_TIME = time();

// Check server status
$settings = $db->super_query("SELECT * FROM " . PREFIX . "_mcp_settings LIMIT 1");
if (!$settings || empty($settings['server_enabled'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode(['error' => 'MCP Server is disabled']);
    exit;
}

// Parse disabled tools
$disabledTools = [];
if (!empty($settings['disabled_tools'])) {
    $disabledTools = array_map('trim', explode(',', $settings['disabled_tools']));
}

require_once __DIR__ . '/auth.class.php';

$tokenId = MCPAuth::validate();
if ($tokenId === false) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized — provide Authorization: Bearer <token>']);
    exit;
}

require_once __DIR__ . '/server.class.php';
require_once __DIR__ . '/tools/articles.php';
require_once __DIR__ . '/tools/categories.php';
require_once __DIR__ . '/tools/comments.php';
require_once __DIR__ . '/tools/users.php';
require_once __DIR__ . '/tools/usergroups.php';
require_once __DIR__ . '/tools/static_pages.php';
require_once __DIR__ . '/tools/settings.php';
require_once __DIR__ . '/tools/xfields.php';
require_once __DIR__ . '/tools/plugins.php';
require_once __DIR__ . '/tools/banners.php';
require_once __DIR__ . '/tools/templates.php';
require_once __DIR__ . '/tools/stats.php';
require_once __DIR__ . '/tools/logs.php';

$server = new MCPServer($tokenId);

// Wrap server->register to filter disabled tools
class MCPServerWrapper {
    private $server;
    private $disabledTools;
    
    public function __construct($server, $disabledTools) {
        $this->server = $server;
        $this->disabledTools = $disabledTools;
    }
    
    public function register($name, $description, $inputSchema, $handler) {
        if (in_array($name, $this->disabledTools)) {
            return; // Skip disabled tools
        }
        $this->server->register($name, $description, $inputSchema, $handler);
    }
}

$wrapper = new MCPServerWrapper($server, $disabledTools);

MCPToolArticles::register($wrapper);
MCPToolCategories::register($wrapper);
MCPToolComments::register($wrapper);
MCPToolUsers::register($wrapper);
MCPToolUserGroups::register($wrapper);
MCPToolStaticPages::register($wrapper);
MCPToolSettings::register($wrapper);
MCPToolXFields::register($wrapper);
MCPToolPlugins::register($wrapper);
MCPToolBanners::register($wrapper);
MCPToolTemplates::register($wrapper);
MCPToolStats::register($wrapper);
MCPToolLogs::register($wrapper);

$server->run();
