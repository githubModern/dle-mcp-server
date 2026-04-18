<?php
/**
 * DLE MCP Server — Logs Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolLogs {
    public static function register($server) {
        global $db;

        $server->register(
            'logs.list',
            'View MCP audit logs.',
            ['type'=>'object','properties'=>[
                'page'   => ['type'=>'integer','description'=>'Page number, default 1'],
                'limit'  => ['type'=>'integer','description'=>'Results per page, max 100, default 20'],
                'search' => ['type'=>'string','description'=>'Search tool name or params'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $page   = max(1, intval($args['page']  ?? 1));
                $limit  = min(100, max(1, intval($args['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $where  = [];
                if (!empty($args['search'])) {
                    $s = $db->safesql(trim($args['search']));
                    $where[] = "(tool LIKE '%{$s}%' OR params LIKE '%{$s}%')";
                }
                $wSql  = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                $cnt   = $db->super_query("SELECT COUNT(*) as c FROM " . PREFIX . "_mcp_logs" . $wSql)['c'] ?? 0;
                $rows  = [];
                $db->query("SELECT id, tool, params, response_status, ip, created_at FROM " . PREFIX . "_mcp_logs" . $wSql . " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return ['total'=>intval($cnt),'page'=>$page,'logs'=>$rows];
            }
        );

        $server->register(
            'logs.clear',
            'Clear admin logs older than specified days.',
            ['type'=>'object','properties'=>[
                'days_older_than' => ['type'=>'integer','description'=>'Delete logs older than N days, default 90'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $days   = max(1, intval($args['days_older_than'] ?? 90));
                $cutoff = time() - ($days * 86400);
                $db->query("DELETE FROM " . USERPREFIX . "_admin_logs WHERE date < {$cutoff}");
                return ['success'=>true,'deleted'=>$db->affected_rows()];
            }
        );
    }
}
