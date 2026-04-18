<?php
/**
 * DLE MCP Server — Settings Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolSettings {
    public static function register($server) {
        global $db, $config;

        $blacklist = ['db_password', 'key', 'smtp_pass', 'db_user'];

        $server->register(
            'settings.get',
            'Get DLE configuration settings. Blacklisted keys are never returned.',
            ['type'=>'object','properties'=>[
                'keys' => ['type'=>'array','description'=>'Specific keys to return (omit for all)'],
            ],'required'=>[]],
            function(array $args) use (&$config, $blacklist) {
                $result = [];
                $keys   = !empty($args['keys']) && is_array($args['keys']) ? $args['keys'] : array_keys($config);
                foreach ($keys as $key) {
                    if (in_array($key, $blacklist)) continue;
                    if (array_key_exists($key, $config)) {
                        $result[$key] = $config[$key];
                    }
                }
                return $result;
            }
        );

        $server->register(
            'settings.update',
            'Update DLE configuration settings. Blacklisted keys cannot be modified.',
            ['type'=>'object','properties'=>[
                'settings' => ['type'=>'object','description'=>'Key-value pairs to update (required)'],
            ],'required'=>['settings']],
            function(array $args) use ($blacklist) {
                if (empty($args['settings']) || !is_array($args['settings'])) {
                    throw new \InvalidArgumentException('settings object is required');
                }
                foreach (array_keys($args['settings']) as $key) {
                    if (in_array($key, $blacklist)) {
                        throw new \InvalidArgumentException("Cannot modify blacklisted setting: {$key}");
                    }
                }
                $configFile = ENGINE_DIR . '/data/config.php';
                if (!file_exists($configFile)) {
                    throw new \RuntimeException('Config file not found');
                }
                $content = file_get_contents($configFile);
                $updated = [];
                foreach ($args['settings'] as $key => $value) {
                    $pattern = '/\$config\[' . preg_quote($key, '/') . '\]\s*=\s*[^;]+;/';
                    $replacement = "\$config['{$key}'] = " . var_export($value, true) . ";";
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, $replacement, $content);
                        $updated[] = $key;
                    }
                }
                if (!$updated) {
                    throw new \RuntimeException('No valid settings found to update');
                }
                file_put_contents($configFile, $content);
                return ['success' => true, 'updated' => $updated];
            }
        );
    }
}
