<?php
/**
 * DLE MCP Server — XFields Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolXFields {
    public static function register($server) {
        global $db;

        $server->register(
            'xfields.list',
            'List all extra fields (xfields).',
            ['type'=>'object','properties'=>[],'required'=>[]],
            function(array $args) use (&$db) {
                $rows = [];
                $db->query("SELECT name, title, type, default_val, description FROM " . USERPREFIX . "_xfields ORDER BY position ASC");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return $rows;
            }
        );

        $server->register(
            'xfields.get',
            'Get an extra field definition by name.',
            ['type'=>'object','properties'=>[
                'name' => ['type'=>'string','description'=>'Field name (required)'],
            ],'required'=>['name']],
            function(array $args) use (&$db) {
                if (empty($args['name'])) throw new \InvalidArgumentException('name is required');
                $name = $db->safesql(trim($args['name']));
                $row  = $db->super_query("SELECT * FROM " . USERPREFIX . "_xfields WHERE name = '{$name}'");
                if (!$row) throw new \RuntimeException('XField not found');
                return $row;
            }
        );

        $server->register(
            'xfields.create',
            'Create a new extra field.',
            ['type'=>'object','properties'=>[
                'name'        => ['type'=>'string','description'=>'Field name (required)'],
                'title'       => ['type'=>'string','description'=>'Field title (required)'],
                'type'        => ['type'=>'string','description'=>'Field type: text|select|checkbox|multiselect|image|file (required)'],
                'default_val' => ['type'=>'string','description'=>'Default value'],
                'description' => ['type'=>'string','description'=>'Field description'],
            ],'required'=>['name','title','type']],
            function(array $args) use (&$db) {
                if (empty($args['name']))  throw new \InvalidArgumentException('name is required');
                if (empty($args['title'])) throw new \InvalidArgumentException('title is required');
                if (empty($args['type']))  throw new \InvalidArgumentException('type is required');
                $validTypes = ['text','select','checkbox','multiselect','image','file'];
                $type = trim($args['type']);
                if (!in_array($type, $validTypes)) {
                    throw new \InvalidArgumentException('type must be one of: ' . implode(', ', $validTypes));
                }
                $name  = $db->safesql(trim($args['name']));
                $title = $db->safesql(trim($args['title']));
                $def   = $db->safesql(trim($args['default_val'] ?? ''));
                $desc  = $db->safesql(trim($args['description'] ?? ''));
                $posRow = $db->super_query("SELECT MAX(position)+1 as p FROM " . USERPREFIX . "_xfields");
                $pos   = intval($posRow['p'] ?? 1);
                $db->query("INSERT INTO " . USERPREFIX . "_xfields (name, title, type, default_val, description, position) VALUES ('{$name}', '{$title}', '{$type}', '{$def}', '{$desc}', {$pos})");
                return ['success' => true];
            }
        );

        $server->register(
            'xfields.delete',
            'Delete an extra field by name.',
            ['type'=>'object','properties'=>[
                'name' => ['type'=>'string','description'=>'Field name (required)'],
            ],'required'=>['name']],
            function(array $args) use (&$db) {
                if (empty($args['name'])) throw new \InvalidArgumentException('name is required');
                $name = $db->safesql(trim($args['name']));
                $db->query("DELETE FROM " . USERPREFIX . "_xfields WHERE name = '{$name}'");
                return ['success' => true];
            }
        );
    }
}
