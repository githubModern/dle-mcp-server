# DLE MCP Server

> **AI-Powered Control for DataLife Engine**  
> Full MCP Protocol Bridge | 53 Tools | Bearer Token Auth | Zero Core Modifications

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![DLE](https://img.shields.io/badge/DLE-19.0%2B-green.svg)](https://dle-news.ru)
[![MCP](https://img.shields.io/badge/MCP-2025--03--26-purple.svg)](https://modelcontextprotocol.io)

Transform your DLE site into an AI-controllable entity. The MCP Server exposes **all native DLE functionality** through the standardized [Model Context Protocol (MCP)](https://modelcontextprotocol.io/), enabling seamless integration with AI agents, IDEs like Windsurf/Cursor, and automation tools.

---

## ✨ Key Features

| Feature | Description |
|---------|-------------|
| 🔌 **Full MCP Protocol** | JSON-RPC 2.0 + HTTP/SSE transport |
| 🛡️ **Bearer Token Auth** | SHA-256 secure tokens with revocable access |
| ⚡ **53 Production Tools** | Complete coverage across 13 namespaces |
| 📊 **Audit Logging** | Full request/response logging for compliance |
| 🔧 **Granular Control** | Enable/disable individual tools per environment |
| 🚫 **Kill Switch** | One-click server disable for maintenance |
| 📱 **Modern Admin UI** | Professional interface with status monitoring |

---

## 📦 Tools Available (53 Total)

```
📄 Articles        → list, get, create, update, delete, search, massAction
📁 Categories     → list, get, create, update, delete
💬 Comments       → list, get, approve, delete, massApprove
👥 Users          → list, get, create, update, ban, unban
🔐 UserGroups     → list, get, update
📄 Static Pages   → list, get, create, update, delete
⚙️ Settings       → get, update (with blacklist protection)
📌 XFields        → list, get, create, delete
🔌 Plugins        → list, enable, disable
🎯 Banners        → list, get, create, update, delete
🎨 Templates      → list, getFile, updateFile
📈 Stats          → overview, topArticles, adminLogs
📝 Logs           → list, clear (MCP audit logs)
```

---

## 🚀 Quick Start

### 1. Install Plugin

```bash
# Copy to your DLE installation
cp -r engine/modules/mcp/ /path/to/dle/engine/modules/
cp engine/inc/mcp.php /path/to/dle/engine/inc/
```

### 2. Install via DLE Plugin Manager

1. Go to **Admin Panel → Plugins → Upload Plugin**
2. Upload `engine/modules/mcp/install/install.xml`
3. Click **Install**

### 3. Configure Access

Add to `engine/.htaccess`:
```apache
<FilesMatch "mcp\.php$">
    Order allow,deny
    Allow from all
</FilesMatch>
```

### 4. Generate Token

Navigate to **Admin Panel → MCP Server** and generate your first API token.

### 5. Connect Your AI

#### Windsurf / Cursor
```json
{
  "mcpServers": {
    "dle": {
      "url": "https://yoursite.com/engine/modules/mcp/mcp.php",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN_HERE"
      }
    }
  }
}
```

#### Claude Desktop
```json
{
  "mcpServers": {
    "dle": {
      "command": "curl",
      "args": [
        "-H", "Authorization: Bearer YOUR_TOKEN",
        "https://yoursite.com/engine/modules/mcp/mcp.php"
      ]
    }
  }
}
```

---

## 🔒 Security

| Feature | Implementation |
|---------|----------------|
| **Authentication** | SHA-256 Bearer tokens with expiry tracking |
| **Sensitive Data** | Password fields never exposed in API responses |
| **Config Protection** | Blacklist: `db_password`, `key`, `smtp_pass`, `db_user` |
| **Audit Trail** | Every call logged with IP, tool, params, status |
| **Tool Access** | Individual tool enable/disable per environment |

---

## 📋 File Structure

```
engine/modules/mcp/
├── install/
│   └── install.xml           # Plugin manifest for DLE
├── tools/
│   ├── articles.php        # 7 tools for article management
│   ├── categories.php      # 5 tools for category management
│   ├── comments.php        # 5 tools for comment moderation
│   ├── users.php           # 6 tools for user management
│   ├── usergroups.php      # 3 tools for user group management
│   ├── static_pages.php    # 5 tools for static page management
│   ├── settings.php        # 2 tools for DLE configuration
│   ├── xfields.php         # 4 tools for extra fields
│   ├── plugins.php         # 3 tools for plugin management
│   ├── banners.php         # 5 tools for banner management
│   ├── templates.php       # 3 tools for template editing
│   ├── stats.php           # 3 tools for statistics
│   └── logs.php            # 2 tools for MCP audit logs
├── admin.php               # Modern admin interface
├── auth.class.php          # Token authentication
├── server.class.php        # MCP protocol engine
└── mcp.php                 # HTTP entry point

engine/inc/mcp.php          # Admin panel loader
```

---

## 🗄️ Database Schema

```sql
-- API Tokens
CREATE TABLE _mcp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL,
    label VARCHAR(100) DEFAULT '',
    created_at INT DEFAULT 0,
    last_used INT DEFAULT 0,
    active TINYINT DEFAULT 1
);

-- Audit Logs
CREATE TABLE _mcp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT DEFAULT 0,
    tool VARCHAR(100) DEFAULT '',
    params TEXT,
    response_status VARCHAR(20) DEFAULT 'ok',
    ip VARCHAR(45) DEFAULT '',
    created_at INT DEFAULT 0
);

-- Server Settings
CREATE TABLE _mcp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_enabled TINYINT DEFAULT 1,
    disabled_tools TEXT,
    updated_at INT DEFAULT 0
);
```

---

## 📊 Admin Interface

The admin panel provides 4 management tabs:

1. **API Tokens** — Generate, view, and revoke bearer tokens
2. **Tool Management** — Enable/disable individual tools with one-click actions
3. **Audit Logs** — View complete API call history with filtering
4. **Connect** — MCP client configuration examples

---

## 🔧 System Requirements

- **PHP**: 7.4+ (8.x recommended)
- **DLE**: 19.0+
- **MySQL**: 5.7+ / MariaDB 10.2+
- **Extensions**: PDO, JSON, cURL

---

## 🧪 Testing

```bash
# Test MCP connection
curl -X POST https://yoursite.com/engine/modules/mcp/mcp.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'

# Expected: 200 OK with list of 53 tools
```

---

## 🆘 Troubleshooting

| Issue | Solution |
|-------|----------|
| 503 Error | Server is disabled. Enable at **Admin → MCP Server** |
| 401 Unauthorized | Check token at **Admin → MCP Server → API Tokens** |
| Tool Not Found | Tool may be disabled. Check **Tool Management** tab |
| 403 Forbidden | Verify `.htaccess` allows `mcp.php` |

---

## 📜 License

MIT License — Free for personal and commercial use.

---

## 👨‍💻 Credits

**Tech Lead & Architecture**: [Atia Hegazy](https://atiaeno.com) — Senior PHP Engineering Team  
**MCP Protocol**: [Model Context Protocol](https://modelcontextprotocol.io)  
**DLE Platform**: [DataLife Engine](https://dle-news.ru)

---

## 🌐 Links

- **Repository**: `https://github.com/githubModern/dle-mcp-server`
- **DLE Official**: `https://dle-news.ru/`
- **MCP Spec**: `https://modelcontextprotocol.io/`

---

**Version**: 1.0.0  
**Updated**: April 2026  
**Compatibility**: DLE 19.0+ | PHP 7.4+
