<?php
/**
 * DLE MCP Server — Admin Interface
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

require_once __DIR__ . '/auth.class.php';

// Get settings
$settings = $db->super_query("SELECT * FROM " . PREFIX . "_mcp_settings LIMIT 1");
if (!$settings) {
    $db->query("INSERT INTO " . PREFIX . "_mcp_settings (server_enabled, disabled_tools, updated_at) VALUES (1, '', " . time() . ")");
    $settings = ['server_enabled' => 1, 'disabled_tools' => '', 'updated_at' => time()];
}

// Handle POST actions
if ($_POST['action'] ?? '' === 'generate') {
    $label    = trim($_POST['label'] ?? 'Token');
    $newToken = MCPAuth::generateToken($label);
    $_SESSION['mcp_new_token'] = $newToken;
    header('Location: ?mod=mcp');
    die();
}
if ($_POST['action'] ?? '' === 'revoke') {
    MCPAuth::revokeToken(intval($_POST['id']));
    header('Location: ?mod=mcp');
    die();
}
if ($_POST['action'] ?? '' === 'toggle_server') {
    $enabled = intval($_POST['enabled'] ?? 0);
    $db->query("UPDATE " . PREFIX . "_mcp_settings SET server_enabled = {$enabled}, updated_at = " . time());
    header('Location: ?mod=mcp&tab=tools');
    die();
}
if ($_POST['action'] ?? '' === 'save_tools') {
    $disabled = [];
    $allTools = getAllToolNames();
    foreach ($allTools as $tool) {
        if (empty($_POST['tool_' . str_replace('.', '_', $tool)])) {
            $disabled[] = $tool;
        }
    }
    $disabledStr = $db->safesql(implode(',', $disabled));
    $db->query("UPDATE " . PREFIX . "_mcp_settings SET disabled_tools = '{$disabledStr}', updated_at = " . time());
    header('Location: ?mod=mcp&tab=tools');
    die();
}

function getAllToolNames() {
    return [
        'articles.list', 'articles.get', 'articles.create', 'articles.update', 'articles.delete', 'articles.search', 'articles.massAction',
        'categories.list', 'categories.get', 'categories.create', 'categories.update', 'categories.delete',
        'comments.list', 'comments.get', 'comments.approve', 'comments.delete', 'comments.massApprove',
        'users.list', 'users.get', 'users.create', 'users.update', 'users.ban', 'users.unban',
        'usergroups.list', 'usergroups.get', 'usergroups.update',
        'static_pages.list', 'static_pages.get', 'static_pages.create', 'static_pages.update', 'static_pages.delete',
        'settings.get', 'settings.update',
        'xfields.list', 'xfields.get', 'xfields.create', 'xfields.delete',
        'plugins.list', 'plugins.enable', 'plugins.disable',
        'banners.list', 'banners.get', 'banners.create', 'banners.update', 'banners.delete',
        'templates.list', 'templates.getFile', 'templates.updateFile',
        'stats.overview', 'stats.topArticles', 'stats.adminLogs',
        'logs.list', 'logs.clear'
    ];
}

function getToolCategory($tool) {
    $parts = explode('.', $tool);
    return $parts[0];
}

function getToolIcon($category) {
    $icons = [
        'articles' => 'fa-file-text',
        'categories' => 'fa-folder',
        'comments' => 'fa-comments',
        'users' => 'fa-users',
        'usergroups' => 'fa-shield',
        'static_pages' => 'fa-file-o',
        'settings' => 'fa-cog',
        'xfields' => 'fa-plus-square',
        'plugins' => 'fa-plug',
        'banners' => 'fa-picture-o',
        'templates' => 'fa-paint-brush',
        'stats' => 'fa-bar-chart',
        'logs' => 'fa-list-alt'
    ];
    return $icons[$category] ?? 'fa-wrench';
}

$tokens = MCPAuth::listTokens();
$disabledTools = $settings['disabled_tools'] ? explode(',', $settings['disabled_tools']) : [];
$disabledTools = array_map('trim', $disabledTools);
$activeTab = $_GET['tab'] ?? 'tokens';

echoheader("<i class='fa fa-robot position-left'></i><span class='text-semibold'>MCP Server</span>", "AI-Powered DLE Control Panel");
?>

<style>
.mcp-container { max-width: 1200px; }
.mcp-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
.mcp-card-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; }
.mcp-card-header h3 { margin: 0; font-size: 18px; font-weight: 600; }
.mcp-card-body { padding: 20px; }
.mcp-tabs { display: flex; gap: 5px; margin-bottom: 20px; background: #f5f5f5; padding: 5px; border-radius: 8px; }
.mcp-tabs a { padding: 10px 20px; border-radius: 6px; text-decoration: none; color: #666; font-weight: 500; transition: all 0.2s; }
.mcp-tabs a:hover { background: #e8e8e8; }
.mcp-tabs a.active { background: #fff; color: #333; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.mcp-tab-content { display: none; }
.mcp-tab-content.active { display: block; }
.mcp-toggle { display: flex; align-items: center; gap: 15px; }
.mcp-toggle-switch { position: relative; width: 60px; height: 30px; background: #ddd; border-radius: 15px; cursor: pointer; transition: all 0.3s; }
.mcp-toggle-switch.enabled { background: #4CAF50; }
.mcp-toggle-switch::after { content: ''; position: absolute; width: 26px; height: 26px; background: #fff; border-radius: 50%; top: 2px; left: 2px; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.mcp-toggle-switch.enabled::after { left: 32px; }
.mcp-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.mcp-status-badge.online { background: #e8f5e9; color: #2e7d32; }
.mcp-status-badge.offline { background: #ffebee; color: #c62828; }
.mcp-tool-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
.mcp-tool-item { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; transition: all 0.2s; }
.mcp-tool-item:hover { border-color: #bdbdbd; background: #fafafa; }
.mcp-tool-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
.mcp-tool-item label { flex: 1; margin: 0; cursor: pointer; font-weight: 500; }
.mcp-tool-item .tool-desc { font-size: 12px; color: #888; font-weight: 400; }
.mcp-tool-icon { width: 36px; height: 36px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; }
.mcp-tool-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.mcp-tool-icon.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.mcp-tool-icon.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.mcp-tool-icon.purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
.mcp-category-header { display: flex; align-items: center; gap: 10px; margin: 20px 0 10px; padding: 10px 15px; background: #f8f9fa; border-radius: 6px; font-weight: 600; color: #555; }
.mcp-category-header i { color: #667eea; }
.mcp-btn { padding: 10px 24px; border-radius: 6px; border: none; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.mcp-btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
.mcp-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
.mcp-btn-danger { background: #e53935; color: #fff; }
.mcp-btn-success { background: #43a047; color: #fff; }
.mcp-token-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
.mcp-token-card h4 { margin: 0 0 15px; font-size: 16px; }
.mcp-token-value { font-family: 'Courier New', monospace; font-size: 18px; background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; word-break: break-all; }
.mcp-config-box { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; }
.mcp-config-box pre { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px; margin: 10px 0; }
.table-mcp th { background: #f8f9fa; font-weight: 600; }
.table-mcp td { vertical-align: middle; }
.mcp-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.mcp-badge-success { background: #e8f5e9; color: #2e7d32; }
.mcp-badge-danger { background: #ffebee; color: #c62828; }
.mcp-quick-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
.mcp-quick-actions button { display: flex; align-items: center; gap: 8px; }
</style>

<div class="mcp-container">
    <!-- Status Header -->
    <div class="mcp-card">
        <div class="mcp-card-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="mcp-tool-icon" style="width: 48px; height: 48px; font-size: 24px;">
                    <i class="fa fa-robot"></i>
                </div>
                <div>
                    <h3>MCP Server Status</h3>
                    <div style="margin-top: 5px; color: #888; font-size: 13px;">
                        Version 1.0.0 | DLE 19.0+ | 53 Tools Available
                    </div>
                </div>
            </div>
            <div class="mcp-toggle">
                <form method="post" style="display: flex; align-items: center; gap: 15px;">
                    <input type="hidden" name="action" value="toggle_server">
                    <span class="mcp-status-badge <?php echo $settings['server_enabled'] ? 'online' : 'offline'; ?>">
                        <i class="fa fa-<?php echo $settings['server_enabled'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo $settings['server_enabled'] ? 'ONLINE' : 'OFFLINE'; ?>
                    </span>
                    <input type="hidden" name="enabled" value="<?php echo $settings['server_enabled'] ? '0' : '1'; ?>">
                    <button type="submit" class="mcp-btn <?php echo $settings['server_enabled'] ? 'mcp-btn-danger' : 'mcp-btn-success'; ?>">
                        <i class="fa fa-power-off"></i>
                        <?php echo $settings['server_enabled'] ? 'Disable Server' : 'Enable Server'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="mcp-tabs">
        <a href="?mod=mcp&tab=tokens" class="<?php echo $activeTab === 'tokens' ? 'active' : ''; ?>">
            <i class="fa fa-key"></i> API Tokens
        </a>
        <a href="?mod=mcp&tab=tools" class="<?php echo $activeTab === 'tools' ? 'active' : ''; ?>">
            <i class="fa fa-wrench"></i> Tool Management
        </a>
        <a href="?mod=mcp&tab=logs" class="<?php echo $activeTab === 'logs' ? 'active' : ''; ?>">
            <i class="fa fa-list-alt"></i> Audit Logs
        </a>
        <a href="?mod=mcp&tab=connect" class="<?php echo $activeTab === 'connect' ? 'active' : ''; ?>">
            <i class="fa fa-plug"></i> Connect
        </a>
    </div>

    <!-- Tab: Tokens -->
    <div id="tab-tokens" class="mcp-tab-content <?php echo $activeTab === 'tokens' ? 'active' : ''; ?>">
        <div class="mcp-card">
            <div class="mcp-card-header">
                <h3><i class="fa fa-key" style="color: #667eea;"></i> API Tokens</h3>
                <button class="mcp-btn mcp-btn-primary" onclick="document.getElementById('token-form').scrollIntoView({behavior:'smooth'})">
                    <i class="fa fa-plus"></i> Generate New Token
                </button>
            </div>
            <div class="mcp-card-body">
                <?php if (!empty($_SESSION['mcp_new_token'])): ?>
                    <div class="mcp-token-card">
                        <h4><i class="fa fa-exclamation-triangle"></i> Copy Your New Token</h4>
                        <div class="mcp-token-value"><?php echo htmlspecialchars($_SESSION['mcp_new_token']); ?></div>
                        <p style="margin-top: 10px; font-size: 13px; opacity: 0.9;">
                            This token will only be shown once. Store it securely.
                        </p>
                    </div>
                    <?php unset($_SESSION['mcp_new_token']); ?>
                <?php endif; ?>

                <table class="table table-striped table-xs table-mcp">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Label</th>
                            <th>Token</th>
                            <th>Created</th>
                            <th>Last Used</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $t): ?>
                        <tr>
                            <td><?php echo intval($t['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($t['label']); ?></strong></td>
                            <td><code style="background: #f5f5f5; padding: 4px 8px; border-radius: 4px;">
                                <?php echo substr(htmlspecialchars($t['token']), 0, 12); ?>...
                            </code></td>
                            <td><?php echo date('Y-m-d H:i', intval($t['created_at'])); ?></td>
                            <td><?php echo $t['last_used'] ? date('Y-m-d H:i', intval($t['last_used'])) : '<span class="text-muted">Never</span>'; ?></td>
                            <td>
                                <?php if ($t['active']): ?>
                                    <span class="mcp-badge mcp-badge-success"><i class="fa fa-check"></i> Active</span>
                                <?php else: ?>
                                    <span class="mcp-badge mcp-badge-danger"><i class="fa fa-ban"></i> Revoked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['active']): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="id" value="<?php echo intval($t['id']); ?>">
                                    <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Revoke this token?')">
                                        <i class="fa fa-ban"></i> Revoke
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tokens)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No tokens generated yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <hr id="token-form">
                <h4><i class="fa fa-plus-circle"></i> Generate New Token</h4>
                <form method="post" class="form-inline" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="generate">
                    <div class="form-group" style="flex: 1; max-width: 400px;">
                        <input type="text" name="label" class="form-control" placeholder="e.g., 'Windsurf Desktop', 'Claude Desktop', 'Cursor IDE'" style="width: 100%;">
                    </div>
                    <button type="submit" class="mcp-btn mcp-btn-primary" style="margin-left: 10px;">
                        <i class="fa fa-magic"></i> Generate Token
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab: Tools -->
    <div id="tab-tools" class="mcp-tab-content <?php echo $activeTab === 'tools' ? 'active' : ''; ?>">
        <div class="mcp-card">
            <div class="mcp-card-header">
                <div>
                    <h3><i class="fa fa-wrench" style="color: #667eea;"></i> Tool Management</h3>
                    <div style="margin-top: 5px; color: #888; font-size: 13px;">
                        Enable or disable individual tools. Disabled tools will return "Tool disabled" error.
                    </div>
                </div>
                <div class="mcp-quick-actions">
                    <button type="button" class="btn btn-default btn-sm" onclick="toggleAll(true)">
                        <i class="fa fa-check-square-o"></i> Enable All
                    </button>
                    <button type="button" class="btn btn-default btn-sm" onclick="toggleAll(false)">
                        <i class="fa fa-square-o"></i> Disable All
                    </button>
                </div>
            </div>
            <div class="mcp-card-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_tools">
                    
                    <?php
                    $tools = getAllToolNames();
                    $currentCategory = '';
                    $categoryColors = [
                        'articles' => 'blue', 'categories' => 'green', 'comments' => 'purple',
                        'users' => 'blue', 'usergroups' => 'purple', 'static_pages' => 'green',
                        'settings' => 'orange', 'xfields' => 'purple', 'plugins' => 'orange',
                        'banners' => 'purple', 'templates' => 'blue', 'stats' => 'green', 'logs' => 'orange'
                    ];
                    foreach ($tools as $tool):
                        $cat = getToolCategory($tool);
                        if ($cat !== $currentCategory):
                            if ($currentCategory) echo '</div>';
                            $currentCategory = $cat;
                            $icon = getToolIcon($cat);
                            $color = $categoryColors[$cat] ?? '';
                    ?>
                    <div class="mcp-category-header">
                        <div class="mcp-tool-icon <?php echo $color; ?>" style="width: 32px; height: 32px; font-size: 14px;">
                            <i class="fa <?php echo $icon; ?>"></i>
                        </div>
                        <?php echo ucfirst(str_replace('_', ' ', $cat)); ?> Tools
                    </div>
                    <div class="mcp-tool-grid">
                    <?php endif; ?>
                        <div class="mcp-tool-item">
                            <input type="checkbox" 
                                   name="tool_<?php echo str_replace('.', '_', $tool); ?>" 
                                   id="tool_<?php echo str_replace('.', '_', $tool); ?>"
                                   <?php echo !in_array($tool, $disabledTools) ? 'checked' : ''; ?>>
                            <label for="tool_<?php echo str_replace('.', '_', $tool); ?>">
                                <?php echo htmlspecialchars($tool); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" class="mcp-btn mcp-btn-primary" style="font-size: 16px; padding: 12px 40px;">
                            <i class="fa fa-save"></i> Save Tool Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab: Logs -->
    <div id="tab-logs" class="mcp-tab-content <?php echo $activeTab === 'logs' ? 'active' : ''; ?>">
        <div class="mcp-card">
            <div class="mcp-card-header">
                <h3><i class="fa fa-list-alt" style="color: #667eea;"></i> Audit Logs</h3>
                <form method="get" class="form-inline">
                    <input type="hidden" name="mod" value="mcp">
                    <input type="hidden" name="tab" value="logs">
                    <input type="text" name="tool_filter" class="form-control" placeholder="Filter by tool..." 
                           value="<?php echo htmlspecialchars($_GET['tool_filter'] ?? ''); ?>">
                    <button type="submit" class="btn btn-default"><i class="fa fa-filter"></i></button>
                </form>
            </div>
            <div class="mcp-card-body">
                <?php
                $toolFilter = $_GET['tool_filter'] ?? '';
                $where = [];
                if ($toolFilter) $where[] = "tool LIKE '%" . $db->safesql(trim($toolFilter)) . "%'";
                $wSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                $logs = [];
                $db->query("SELECT tool, ip, response_status, created_at FROM " . PREFIX . "_mcp_logs" . $wSql . " ORDER BY id DESC LIMIT 100");
                while ($row = $db->get_row()) { $logs[] = $row; }
                ?>
                <table class="table table-striped table-xs table-mcp">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Tool</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', intval($log['created_at'])); ?></td>
                            <td><code style="background: #f5f5f5; padding: 4px 8px; border-radius: 4px;">
                                <?php echo htmlspecialchars($log['tool']); ?>
                            </code></td>
                            <td><?php echo htmlspecialchars($log['ip']); ?></td>
                            <td>
                                <?php if ($log['response_status'] === 'ok'): ?>
                                    <span class="mcp-badge mcp-badge-success"><i class="fa fa-check"></i> Success</span>
                                <?php else: ?>
                                    <span class="mcp-badge mcp-badge-danger"><i class="fa fa-times"></i> Error</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No logs yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab: Connect -->
    <div id="tab-connect" class="mcp-tab-content <?php echo $activeTab === 'connect' ? 'active' : ''; ?>">
        <div class="mcp-card">
            <div class="mcp-card-header">
                <h3><i class="fa fa-plug" style="color: #667eea;"></i> MCP Client Configuration</h3>
            </div>
            <div class="mcp-card-body">
                <div class="mcp-config-box">
                    <h4><i class="fa fa-code"></i> Configuration for MCP Clients</h4>
                    <p>Add this configuration to your MCP client (Windsurf, Claude Desktop, Cursor, etc.):</p>
                    <pre>{
  "mcpServers": {
    "dle": {
      "url": "<?php echo $config['http_home_url']; ?>engine/modules/mcp/mcp.php",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN_HERE"
      }
    }
  }
}</pre>
                    <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px;">
                        <i class="fa fa-info-circle"></i> <strong>Important:</strong> 
                        Replace <code>YOUR_TOKEN_HERE</code> with a token from the <a href="?mod=mcp&tab=tokens">API Tokens</a> tab.
                    </div>
                </div>

                <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <i class="fa fa-robot" style="font-size: 32px; color: #667eea; margin-bottom: 10px;"></i>
                        <h5>Windsurf</h5>
                        <p class="text-muted" style="font-size: 12px;">Add to Settings → MCP</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <i class="fa fa-comment" style="font-size: 32px; color: #764ba2; margin-bottom: 10px;"></i>
                        <h5>Claude Desktop</h5>
                        <p class="text-muted" style="font-size: 12px;">Add to claude_desktop_config.json</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <i class="fa fa-code" style="font-size: 32px; color: #11998e; margin-bottom: 10px;"></i>
                        <h5>Cursor</h5>
                        <p class="text-muted" style="font-size: 12px;">Add to Settings → MCP</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAll(enable) {
    document.querySelectorAll('.mcp-tool-item input[type="checkbox"]').forEach(function(cb) {
        cb.checked = enable;
    });
}
</script>

<?php
echofooter();
