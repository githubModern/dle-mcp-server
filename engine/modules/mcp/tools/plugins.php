<?php
class MCPToolPlugins {
    public static function register($server) {
        global $db;

        $server->register(
            'plugins.list',
            'List all installed plugins.',
            ['type'=>'object','properties'=>[],'required'=>[]],
            function(array $args) use (&$db) {
                $rows = [];
                $db->query("SELECT name, description, active, version FROM " . PREFIX . "_plugins ORDER BY name ASC");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return $rows;
            }
        );

        $server->register(
            'plugins.enable',
            'Enable a plugin by name.',
            ['type'=>'object','properties'=>[
                'name' => ['type'=>'string','description'=>'Plugin name (required)'],
            ],'required'=>['name']],
            function(array $args) use (&$db) {
                if (empty($args['name'])) throw new \InvalidArgumentException('name is required');
                $name = $db->safesql(trim($args['name']));
                $db->query("UPDATE " . PREFIX . "_plugins SET active=1 WHERE name = '{$name}'");
                return ['success' => true];
            }
        );

        $server->register(
            'plugins.disable',
            'Disable a plugin by name.',
            ['type'=>'object','properties'=>[
                'name' => ['type'=>'string','description'=>'Plugin name (required)'],
            ],'required'=>['name']],
            function(array $args) use (&$db) {
                if (empty($args['name'])) throw new \InvalidArgumentException('name is required');
                $name = $db->safesql(trim($args['name']));
                $db->query("UPDATE " . PREFIX . "_plugins SET active=0 WHERE name = '{$name}'");
                return ['success' => true];
            }
        );
    }
}
