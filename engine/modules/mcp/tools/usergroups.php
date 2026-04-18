<?php
/**
 * DLE MCP Server — UserGroups Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolUserGroups {
    public static function register($server) {
        global $db;

        $server->register(
            'usergroups.list',
            'List all user groups with key permissions.',
            ['type'=>'object','properties'=>[],'required'=>[]],
            function(array $args) use (&$db) {
                $rows = [];
                $db->query("SELECT id, name, max_news, admin_comments, admin_editnews, admin_addnews, admin_categories, admin_static, admin_banners, admin_xfields, admin_editusers, allow_all_edit FROM " . USERPREFIX . "_usergroups ORDER BY id ASC");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return $rows;
            }
        );

        $server->register(
            'usergroups.get',
            'Get full permissions of a user group by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Group ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id  = intval($args['id']);
                $row = $db->super_query("SELECT * FROM " . USERPREFIX . "_usergroups WHERE id = {$id}");
                if (!$row) throw new \RuntimeException('Group not found');
                return $row;
            }
        );

        $server->register(
            'usergroups.update',
            'Update permissions of a user group. Whitelist enforced.',
            ['type'=>'object','properties'=>[
                'id'                  => ['type'=>'integer','description'=>'Group ID (required)'],
                'name'                => ['type'=>'string','description'=>'Group name'],
                'max_news'            => ['type'=>'integer','description'=>'Max news per day'],
                'admin_comments'      => ['type'=>'integer','description'=>'Can manage comments'],
                'admin_editnews'      => ['type'=>'integer','description'=>'Can edit news'],
                'admin_addnews'       => ['type'=>'integer','description'=>'Can add news'],
                'admin_categories'    => ['type'=>'integer','description'=>'Can manage categories'],
                'admin_static'        => ['type'=>'integer','description'=>'Can manage static pages'],
                'admin_banners'       => ['type'=>'integer','description'=>'Can manage banners'],
                'admin_xfields'       => ['type'=>'integer','description'=>'Can manage extra fields'],
                'admin_editusers'     => ['type'=>'integer','description'=>'Can edit users'],
                'allow_all_edit'      => ['type'=>'integer','description'=>'Allow editing all content'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id      = intval($args['id']);
                $allowed = ['name','max_news','admin_comments','admin_editnews','admin_addnews','admin_categories','admin_static','admin_banners','admin_xfields','admin_editusers','allow_all_edit'];
                $set     = [];
                foreach ($allowed as $key) {
                    if (array_key_exists($key, $args)) {
                        if ($key === 'name') {
                            $set[] = "{$key} = '" . $db->safesql(trim($args[$key])) . "'";
                        } else {
                            $set[] = "{$key} = " . intval($args[$key]);
                        }
                    }
                }
                if (!$set) throw new \InvalidArgumentException('No fields to update');
                $db->query("UPDATE " . USERPREFIX . "_usergroups SET " . implode(', ', $set) . " WHERE id = {$id}");
                return ['success' => true];
            }
        );
    }
}
