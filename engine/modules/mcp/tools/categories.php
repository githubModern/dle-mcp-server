<?php
class MCPToolCategories {
    public static function register($server) {
        global $db;

        $server->register(
            'categories.list',
            'List all categories ordered by position.',
            ['type'=>'object','properties'=>[],'required'=>[]],
            function(array $args) use (&$db) {
                $rows = [];
                $db->query("SELECT id, name, alt_name, parentid, posi, news_number, descr, active FROM " . PREFIX . "_category ORDER BY posi ASC");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return $rows;
            }
        );

        $server->register(
            'categories.get',
            'Get a single category by ID or alt_name.',
            ['type'=>'object','properties'=>[
                'id'       => ['type'=>'integer','description'=>'Category ID'],
                'alt_name' => ['type'=>'string','description'=>'Category URL slug'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                if (!empty($args['id'])) {
                    $id  = intval($args['id']);
                    $row = $db->super_query("SELECT * FROM " . PREFIX . "_category WHERE id = {$id}");
                } elseif (!empty($args['alt_name'])) {
                    $alt = $db->safesql(trim($args['alt_name']));
                    $row = $db->super_query("SELECT * FROM " . PREFIX . "_category WHERE alt_name = '{$alt}'");
                } else {
                    throw new \InvalidArgumentException('Provide id or alt_name');
                }
                if (!$row) throw new \RuntimeException('Category not found');
                return $row;
            }
        );

        $server->register(
            'categories.create',
            'Create a new category.',
            ['type'=>'object','properties'=>[
                'name'     => ['type'=>'string','description'=>'Category name (required)'],
                'alt_name' => ['type'=>'string','description'=>'URL slug (auto-generated if blank)'],
                'parentid' => ['type'=>'integer','description'=>'Parent category ID, default 0'],
                'descr'    => ['type'=>'string','description'=>'Description'],
            ],'required'=>['name']],
            function(array $args) use (&$db) {
                if (empty($args['name'])) throw new \InvalidArgumentException('name is required');
                $name     = $db->safesql(trim($args['name']));
                $altName  = !empty($args['alt_name']) ? $db->safesql(trim($args['alt_name'])) : $db->safesql(preg_replace('/[^a-z0-9_\-]/i', '-', strtolower(trim($args['name']))));
                $parentId = intval($args['parentid'] ?? 0);
                $descr    = $db->safesql(trim($args['descr'] ?? ''));
                $posRow   = $db->super_query("SELECT MAX(posi)+1 as p FROM " . PREFIX . "_category");
                $posi     = intval($posRow['p'] ?? 1);
                $db->query("INSERT INTO " . PREFIX . "_category (name, alt_name, parentid, posi, pcount, descr) VALUES ('{$name}', '{$altName}', {$parentId}, {$posi}, 0, '{$descr}')");
                return ['id' => intval($db->insert_id())];
            }
        );

        $server->register(
            'categories.update',
            'Update fields of an existing category.',
            ['type'=>'object','properties'=>[
                'id'       => ['type'=>'integer','description'=>'Category ID (required)'],
                'name'     => ['type'=>'string','description'=>'New name'],
                'alt_name' => ['type'=>'string','description'=>'New URL slug'],
                'parentid' => ['type'=>'integer','description'=>'New parent ID'],
                'descr'    => ['type'=>'string','description'=>'New description'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id   = intval($args['id']);
                $set  = [];
                if (array_key_exists('name', $args))     $set[] = "name = '" . $db->safesql(trim($args['name'])) . "'";
                if (array_key_exists('alt_name', $args))  $set[] = "alt_name = '" . $db->safesql(trim($args['alt_name'])) . "'";
                if (array_key_exists('parentid', $args)) $set[] = "parentid = " . intval($args['parentid']);
                if (array_key_exists('descr', $args))    $set[] = "descr = '" . $db->safesql(trim($args['descr'])) . "'";
                if (!$set) throw new \InvalidArgumentException('No fields to update');
                $db->query("UPDATE " . PREFIX . "_category SET " . implode(', ', $set) . " WHERE id = {$id}");
                return ['success' => true];
            }
        );

        $server->register(
            'categories.delete',
            'Delete a category and remove it from all articles.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Category ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id = intval($args['id']);
                $db->query("DELETE FROM " . PREFIX . "_category WHERE id = {$id}");
                $db->query("UPDATE " . PREFIX . "_post SET category = TRIM(BOTH ',' FROM REPLACE(REPLACE(CONCAT(',',category,','), CONCAT(',',{$id},','), ','), ',,', ',')) WHERE FIND_IN_SET({$id}, category)");
                return ['success' => true];
            }
        );
    }
}
