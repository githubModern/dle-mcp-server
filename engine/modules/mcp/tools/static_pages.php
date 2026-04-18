<?php
/**
 * DLE MCP Server — Static Pages Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolStaticPages {
    public static function register($server) {
        global $db;

        $server->register(
            'static_pages.list',
            'List all static pages.',
            ['type'=>'object','properties'=>[],'required'=>[]],
            function(array $args) use (&$db) {
                $rows = [];
                $db->query("SELECT id, title, alt_name, date, skin_name FROM " . PREFIX . "_static ORDER BY id DESC");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return $rows;
            }
        );

        $server->register(
            'static_pages.get',
            'Get a static page by ID or alt_name.',
            ['type'=>'object','properties'=>[
                'id'       => ['type'=>'integer','description'=>'Page ID'],
                'alt_name' => ['type'=>'string','description'=>'Page URL slug'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                if (!empty($args['id'])) {
                    $id  = intval($args['id']);
                    $row = $db->super_query("SELECT * FROM " . PREFIX . "_static WHERE id = {$id}");
                } elseif (!empty($args['alt_name'])) {
                    $alt = $db->safesql(trim($args['alt_name']));
                    $row = $db->super_query("SELECT * FROM " . PREFIX . "_static WHERE alt_name = '{$alt}'");
                } else {
                    throw new \InvalidArgumentException('Provide id or alt_name');
                }
                if (!$row) throw new \RuntimeException('Static page not found');
                return $row;
            }
        );

        $server->register(
            'static_pages.create',
            'Create a new static page.',
            ['type'=>'object','properties'=>[
                'title'       => ['type'=>'string','description'=>'Page title (required)'],
                'content'     => ['type'=>'string','description'=>'Page content (required)'],
                'alt_name'    => ['type'=>'string','description'=>'URL slug (auto-generated if blank)'],
                'skin_name'   => ['type'=>'string','description'=>'Template folder name'],
                'description' => ['type'=>'string','description'=>'Meta description'],
            ],'required'=>['title','content']],
            function(array $args) use (&$db) {
                if (empty($args['title']))   throw new \InvalidArgumentException('title is required');
                if (empty($args['content'])) throw new \InvalidArgumentException('content is required');
                $title   = $db->safesql(trim($args['title']));
                $content = $db->safesql($args['content']);
                $altName = !empty($args['alt_name']) ? $db->safesql(trim($args['alt_name'])) : $db->safesql(preg_replace('/[^a-z0-9_\-]/i', '-', strtolower(trim($args['title']))));
                $skin    = $db->safesql(trim($args['skin_name'] ?? ''));
                $descr   = $db->safesql(trim($args['description'] ?? ''));
                $date    = time();
                $db->query("INSERT INTO " . PREFIX . "_static (title, content, alt_name, skin_name, description, date) VALUES ('{$title}', '{$content}', '{$altName}', '{$skin}', '{$descr}', {$date})");
                return ['id' => intval($db->insert_id())];
            }
        );

        $server->register(
            'static_pages.update',
            'Update fields of a static page.',
            ['type'=>'object','properties'=>[
                'id'          => ['type'=>'integer','description'=>'Page ID (required)'],
                'title'       => ['type'=>'string','description'=>'New title'],
                'content'     => ['type'=>'string','description'=>'New content'],
                'alt_name'    => ['type'=>'string','description'=>'New URL slug'],
                'skin_name'   => ['type'=>'string','description'=>'New template folder'],
                'description' => ['type'=>'string','description'=>'New meta description'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id  = intval($args['id']);
                $set = [];
                if (array_key_exists('title', $args))       $set[] = "title = '" . $db->safesql(trim($args['title'])) . "'";
                if (array_key_exists('content', $args))     $set[] = "content = '" . $db->safesql($args['content']) . "'";
                if (array_key_exists('alt_name', $args))    $set[] = "alt_name = '" . $db->safesql(trim($args['alt_name'])) . "'";
                if (array_key_exists('skin_name', $args))   $set[] = "skin_name = '" . $db->safesql(trim($args['skin_name'])) . "'";
                if (array_key_exists('description', $args)) $set[] = "description = '" . $db->safesql(trim($args['description'])) . "'";
                if (!$set) throw new \InvalidArgumentException('No fields to update');
                $db->query("UPDATE " . PREFIX . "_static SET " . implode(', ', $set) . " WHERE id = {$id}");
                return ['success' => true];
            }
        );

        $server->register(
            'static_pages.delete',
            'Delete a static page by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Page ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id = intval($args['id']);
                $db->query("DELETE FROM " . PREFIX . "_static WHERE id = {$id}");
                return ['success' => true];
            }
        );
    }
}
