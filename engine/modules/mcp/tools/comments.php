<?php
/**
 * DLE MCP Server — Comments Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolComments {
    public static function register($server) {
        global $db;

        $server->register(
            'comments.list',
            'List comments with optional filters.',
            ['type'=>'object','properties'=>[
                'post_id' => ['type'=>'integer','description'=>'Filter by article ID'],
                'approve' => ['type'=>'integer','description'=>'Filter by status: 1=approved, 0=pending'],
                'page'    => ['type'=>'integer','description'=>'Page number, default 1'],
                'limit'   => ['type'=>'integer','description'=>'Results per page, max 100, default 20'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $page   = max(1, intval($args['page']  ?? 1));
                $limit  = min(100, max(1, intval($args['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $where  = [];
                if (!empty($args['post_id'])) $where[] = "post_id = " . intval($args['post_id']);
                if (isset($args['approve']))  $where[] = "approve = " . intval($args['approve']);
                $wSql   = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                $cnt    = $db->super_query("SELECT COUNT(*) as c FROM " . PREFIX . "_comments" . $wSql);
                $total  = intval($cnt['c'] ?? 0);
                $rows   = [];
                $db->query("SELECT id, post_id, user_id, autor, email, date, text, approve FROM " . PREFIX . "_comments" . $wSql . " ORDER BY date DESC LIMIT {$limit} OFFSET {$offset}");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return ['total'=>$total,'page'=>$page,'comments'=>$rows];
            }
        );

        $server->register(
            'comments.get',
            'Get a single comment by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Comment ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id  = intval($args['id']);
                $row = $db->super_query("SELECT * FROM " . PREFIX . "_comments WHERE id = {$id}");
                if (!$row) throw new \RuntimeException("Comment {$id} not found");
                return $row;
            }
        );

        $server->register(
            'comments.approve',
            'Approve a pending comment by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Comment ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id = intval($args['id']);
                $db->query("UPDATE " . PREFIX . "_comments SET approve=1 WHERE id = {$id}");
                $db->query("UPDATE " . PREFIX . "_post SET comm_num = (SELECT COUNT(*) FROM " . PREFIX . "_comments WHERE post_id = (SELECT post_id FROM " . PREFIX . "_comments WHERE id = {$id})) WHERE id = (SELECT post_id FROM " . PREFIX . "_comments WHERE id = {$id})");
                return ['success' => true];
            }
        );

        $server->register(
            'comments.delete',
            'Delete a comment by ID and recalculate article comment count.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Comment ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id      = intval($args['id']);
                $postRow = $db->super_query("SELECT post_id FROM " . PREFIX . "_comments WHERE id = {$id}");
                if (!$postRow) throw new \RuntimeException("Comment not found");
                $postId  = intval($postRow['post_id']);
                $db->query("DELETE FROM " . PREFIX . "_comments WHERE id = {$id}");
                $db->query("UPDATE " . PREFIX . "_post SET comm_num = (SELECT COUNT(*) FROM " . PREFIX . "_comments WHERE post_id = {$postId}) WHERE id = {$postId}");
                return ['success' => true];
            }
        );

        $server->register(
            'comments.massApprove',
            'Approve multiple comments at once.',
            ['type'=>'object','properties'=>[
                'ids' => ['type'=>'array','description'=>'Array of comment IDs (required)'],
            ],'required'=>['ids']],
            function(array $args) use (&$db) {
                if (empty($args['ids']) || !is_array($args['ids'])) throw new \InvalidArgumentException('ids array is required');
                $ids   = array_map('intval', $args['ids']);
                $inSql = implode(',', $ids);
                $db->query("UPDATE " . PREFIX . "_comments SET approve=1 WHERE id IN ({$inSql})");
                $count = 0;
                foreach ($ids as $id) {
                    $postRow = $db->super_query("SELECT post_id FROM " . PREFIX . "_comments WHERE id = {$id}");
                    if ($postRow) {
                        $postId = intval($postRow['post_id']);
                        $db->query("UPDATE " . PREFIX . "_post SET comm_num = (SELECT COUNT(*) FROM " . PREFIX . "_comments WHERE post_id = {$postId}) WHERE id = {$postId}");
                        $count++;
                    }
                }
                return ['success'=>true,'count'=>$count];
            }
        );
    }
}
