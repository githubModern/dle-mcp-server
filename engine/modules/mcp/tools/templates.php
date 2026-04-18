<?php
/**
 * DLE MCP Server — Templates Tool
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

class MCPToolTemplates {
    public static function register($server) {
        global $db;

        $server->register(
            'templates.list',
            'List all template folders in /templates/.',
            ['type'=>'object','properties'=>[],'required'=>[]],
            function(array $args) {
                $templatesDir = ROOT_DIR . '/templates/';
                $folders = [];
                if (is_dir($templatesDir)) {
                    $items = scandir($templatesDir);
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $path = $templatesDir . $item;
                        if (is_dir($path)) {
                            $folders[] = ['folder' => $item, 'path' => '/templates/' . $item];
                        }
                    }
                }
                return $folders;
            }
        );

        $server->register(
            'templates.getFile',
            'Get content of a template file. Uses basename() to prevent traversal.',
            ['type'=>'object','properties'=>[
                'template' => ['type'=>'string','description'=>'Template folder name (required)'],
                'filename' => ['type'=>'string','description'=>'Template filename e.g. main.tpl (required)'],
            ],'required'=>['template','filename']],
            function(array $args) {
                if (empty($args['template'])) throw new \InvalidArgumentException('template is required');
                if (empty($args['filename'])) throw new \InvalidArgumentException('filename is required');
                $template = basename(trim($args['template']));
                $filename = basename(trim($args['filename']));
                $path = ROOT_DIR . '/templates/' . $template . '/' . $filename;
                if (!file_exists($path)) {
                    throw new \RuntimeException('File not found: ' . $filename);
                }
                return ['content' => file_get_contents($path)];
            }
        );

        $server->register(
            'templates.updateFile',
            'Write content to a template file. Uses basename() to prevent traversal.',
            ['type'=>'object','properties'=>[
                'template' => ['type'=>'string','description'=>'Template folder name (required)'],
                'filename' => ['type'=>'string','description'=>'Template filename (required)'],
                'content'  => ['type'=>'string','description'=>'New file content (required)'],
            ],'required'=>['template','filename','content']],
            function(array $args) {
                if (empty($args['template'])) throw new \InvalidArgumentException('template is required');
                if (empty($args['filename'])) throw new \InvalidArgumentException('filename is required');
                $template = basename(trim($args['template']));
                $filename = basename(trim($args['filename']));
                $dir = ROOT_DIR . '/templates/' . $template;
                $path = $dir . '/' . $filename;
                if (!is_dir($dir)) {
                    throw new \RuntimeException('Template folder does not exist: ' . $template);
                }
                file_put_contents($path, $args['content']);
                return ['success' => true];
            }
        );
    }
}
