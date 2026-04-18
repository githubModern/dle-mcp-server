<?php
/**
 * DLE MCP Server — Token Authentication
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPAuth {

    public static function validate() {
        global $db;
        $header = '';
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
        }
        if (!$header && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (strpos($header, 'Bearer ') !== 0) return false;
        $token = substr($header, 7);
        if (strlen($token) !== 64) return false;
        $row = $db->super_query("SELECT id FROM " . PREFIX . "_mcp_tokens WHERE token = '" . $db->safesql($token) . "' AND active = 1");
        if (!$row) return false;
        $db->query("UPDATE " . PREFIX . "_mcp_tokens SET last_used = " . time() . " WHERE id = " . intval($row['id']));
        return intval($row['id']);
    }

    public static function generateToken($label) {
        global $db;
        $token = bin2hex(random_bytes(32));
        $label = $db->safesql(trim($label));
        $db->query("INSERT INTO " . PREFIX . "_mcp_tokens (token, label, created_at, active) VALUES ('" . $token . "', '" . $label . "', " . time() . ", 1)");
        return $token;
    }

    public static function revokeToken($id) {
        global $db;
        $db->query("UPDATE " . PREFIX . "_mcp_tokens SET active = 0 WHERE id = " . intval($id));
    }

    public static function listTokens() {
        global $db;
        $rows = [];
        $db->query("SELECT id, label, created_at, last_used, active FROM " . PREFIX . "_mcp_tokens ORDER BY id DESC");
        while ($row = $db->get_row()) { $rows[] = $row; }
        return $rows;
    }
}
