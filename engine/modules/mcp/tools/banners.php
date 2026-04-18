<?php
/**
 * DLE MCP Server — Banners Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolBanners {
    public static function register($server) {
        global $db;

        $server->register(
            'banners.list',
            'List all banners, optionally filtered by rubric.',
            ['type'=>'object','properties'=>[
                'rubric_id' => ['type'=>'integer','description'=>'Filter by rubric ID'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $where = [];
                if (!empty($args['rubric_id'])) $where[] = "rubric = " . intval($args['rubric_id']);
                $wSql  = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                $rows  = [];
                $db->query("SELECT id, name, code, views, clicks, active, rubric FROM " . PREFIX . "_banners" . $wSql . " ORDER BY id DESC");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return $rows;
            }
        );

        $server->register(
            'banners.get',
            'Get a banner by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Banner ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id  = intval($args['id']);
                $row = $db->super_query("SELECT * FROM " . PREFIX . "_banners WHERE id = {$id}");
                if (!$row) throw new \RuntimeException('Banner not found');
                return $row;
            }
        );

        $server->register(
            'banners.create',
            'Create a new banner.',
            ['type'=>'object','properties'=>[
                'name'      => ['type'=>'string','description'=>'Banner name (required)'],
                'code'      => ['type'=>'string','description'=>'Banner HTML code (required)'],
                'rubric_id' => ['type'=>'integer','description'=>'Rubric ID, default 0'],
                'active'    => ['type'=>'integer','description'=>'Active status, default 1'],
            ],'required'=>['name','code']],
            function(array $args) use (&$db) {
                if (empty($args['name'])) throw new \InvalidArgumentException('name is required');
                if (empty($args['code'])) throw new \InvalidArgumentException('code is required');
                $name   = $db->safesql(trim($args['name']));
                $code   = $db->safesql($args['code']);
                $rubric = intval($args['rubric_id'] ?? 0);
                $active = isset($args['active']) ? intval($args['active']) : 1;
                $db->query("INSERT INTO " . PREFIX . "_banners (name, code, rubric, active, views, clicks) VALUES ('{$name}', '{$code}', {$rubric}, {$active}, 0, 0)");
                return ['id' => intval($db->insert_id())];
            }
        );

        $server->register(
            'banners.update',
            'Update fields of a banner.',
            ['type'=>'object','properties'=>[
                'id'      => ['type'=>'integer','description'=>'Banner ID (required)'],
                'name'    => ['type'=>'string','description'=>'New name'],
                'code'    => ['type'=>'string','description'=>'New code'],
                'rubric'  => ['type'=>'integer','description'=>'New rubric ID'],
                'active'  => ['type'=>'integer','description'=>'New active status'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id  = intval($args['id']);
                $set = [];
                if (array_key_exists('name', $args))   $set[] = "name = '" . $db->safesql(trim($args['name'])) . "'";
                if (array_key_exists('code', $args))   $set[] = "code = '" . $db->safesql($args['code']) . "'";
                if (array_key_exists('rubric', $args)) $set[] = "rubric = " . intval($args['rubric']);
                if (array_key_exists('active', $args)) $set[] = "active = " . intval($args['active']);
                if (!$set) throw new \InvalidArgumentException('No fields to update');
                $db->query("UPDATE " . PREFIX . "_banners SET " . implode(', ', $set) . " WHERE id = {$id}");
                return ['success' => true];
            }
        );

        $server->register(
            'banners.delete',
            'Delete a banner by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Banner ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id = intval($args['id']);
                $db->query("DELETE FROM " . PREFIX . "_banners WHERE id = {$id}");
                return ['success' => true];
            }
        );
    }
}
