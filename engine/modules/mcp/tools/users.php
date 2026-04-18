<?php
class MCPToolUsers {
    public static function register($server) {
        global $db;

        $server->register(
            'users.list',
            'List users with optional filters. NEVER returns password field.',
            ['type'=>'object','properties'=>[
                'page'      => ['type'=>'integer','description'=>'Page number, default 1'],
                'limit'     => ['type'=>'integer','description'=>'Results per page, max 100, default 20'],
                'group_id'  => ['type'=>'integer','description'=>'Filter by user group ID'],
                'search'    => ['type'=>'string','description'=>'Search name or email'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $page   = max(1, intval($args['page']  ?? 1));
                $limit  = min(100, max(1, intval($args['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $where  = [];
                if (!empty($args['group_id'])) $where[] = "user_group = " . intval($args['group_id']);
                if (!empty($args['search'])) {
                    $s = $db->safesql(trim($args['search']));
                    $where[] = "(name LIKE '%{$s}%' OR email LIKE '%{$s}%')";
                }
                $wSql   = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                $cnt    = $db->super_query("SELECT COUNT(*) as c FROM " . USERPREFIX . "_users" . $wSql);
                $total  = intval($cnt['c'] ?? 0);
                $rows   = [];
                $db->query("SELECT user_id, name, email, reg_date, lastdate, user_group, banned, news_num FROM " . USERPREFIX . "_users" . $wSql . " ORDER BY reg_date DESC LIMIT {$limit} OFFSET {$offset}");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return ['total'=>$total,'page'=>$page,'users'=>$rows];
            }
        );

        $server->register(
            'users.get',
            'Get a single user by ID or name. NEVER returns password.',
            ['type'=>'object','properties'=>[
                'id'   => ['type'=>'integer','description'=>'User ID'],
                'name' => ['type'=>'string','description'=>'Username'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                if (!empty($args['id'])) {
                    $id  = intval($args['id']);
                    $row = $db->super_query("SELECT user_id, name, email, reg_date, lastdate, user_group, banned, news_num FROM " . USERPREFIX . "_users WHERE user_id = {$id}");
                } elseif (!empty($args['name'])) {
                    $name = $db->safesql(trim($args['name']));
                    $row  = $db->super_query("SELECT user_id, name, email, reg_date, lastdate, user_group, banned, news_num FROM " . USERPREFIX . "_users WHERE name = '{$name}'");
                } else {
                    throw new \InvalidArgumentException('Provide id or name');
                }
                if (!$row) throw new \RuntimeException('User not found');
                return $row;
            }
        );

        $server->register(
            'users.create',
            'Create a new user. Password is hashed using DLE method.',
            ['type'=>'object','properties'=>[
                'name'     => ['type'=>'string','description'=>'Username (required)'],
                'email'    => ['type'=>'string','description'=>'Email (required)'],
                'password' => ['type'=>'string','description'=>'Password (required)'],
                'group_id' => ['type'=>'integer','description'=>'User group ID, default 4 (member)'],
            ],'required'=>['name','email','password']],
            function(array $args) use (&$db) {
                if (empty($args['name']))     throw new \InvalidArgumentException('name is required');
                if (empty($args['email']))    throw new \InvalidArgumentException('email is required');
                if (empty($args['password'])) throw new \InvalidArgumentException('password is required');
                $name     = $db->safesql(trim($args['name']));
                $email    = $db->safesql(trim($args['email']));
                $password = md5(md5($args['password']));
                $groupId  = intval($args['group_id'] ?? 4);
                $time     = time();
                $db->query("INSERT INTO " . USERPREFIX . "_users (name, email, password, user_group, reg_date, active, banned) VALUES ('{$name}', '{$email}', '{$password}', {$groupId}, {$time}, 1, 0)");
                return ['id' => intval($db->insert_id())];
            }
        );

        $server->register(
            'users.update',
            'Update fields of an existing user. Whitelist only: email, group_id, name.',
            ['type'=>'object','properties'=>[
                'id'       => ['type'=>'integer','description'=>'User ID (required)'],
                'email'    => ['type'=>'string','description'=>'New email'],
                'group_id' => ['type'=>'integer','description'=>'New group ID'],
                'name'     => ['type'=>'string','description'=>'New username'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id  = intval($args['id']);
                $set = [];
                if (array_key_exists('email', $args))    $set[] = "email = '" . $db->safesql(trim($args['email'])) . "'";
                if (array_key_exists('group_id', $args)) $set[] = "user_group = " . intval($args['group_id']);
                if (array_key_exists('name', $args))     $set[] = "name = '" . $db->safesql(trim($args['name'])) . "'";
                if (!$set) throw new \InvalidArgumentException('No fields to update');
                $db->query("UPDATE " . USERPREFIX . "_users SET " . implode(', ', $set) . " WHERE user_id = {$id}");
                return ['success' => true];
            }
        );

        $server->register(
            'users.ban',
            'Ban a user by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'User ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id = intval($args['id']);
                $db->query("UPDATE " . USERPREFIX . "_users SET banned=1 WHERE user_id = {$id}");
                return ['success' => true];
            }
        );

        $server->register(
            'users.unban',
            'Unban a user by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'User ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id = intval($args['id']);
                $db->query("UPDATE " . USERPREFIX . "_users SET banned=0 WHERE user_id = {$id}");
                return ['success' => true];
            }
        );
    }
}
