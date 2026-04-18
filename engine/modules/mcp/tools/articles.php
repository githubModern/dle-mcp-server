<?php
class MCPToolArticles {
    public static function register($server) {
        global $db;
        // Accepts MCPServer or MCPServerWrapper

        $server->register(
            'articles.list',
            'List articles with optional filters. Returns paginated results.',
            ['type'=>'object','properties'=>[
                'page'        => ['type'=>'integer','description'=>'Page number, default 1'],
                'limit'       => ['type'=>'integer','description'=>'Results per page, max 100, default 20'],
                'category_id' => ['type'=>'integer','description'=>'Filter by category ID'],
                'approve'     => ['type'=>'integer','description'=>'Filter by approve status: 1=published, 0=pending'],
                'author'      => ['type'=>'string', 'description'=>'Filter by author login name'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $page  = max(1, intval($args['page']  ?? 1));
                $limit = min(100, max(1, intval($args['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $where = [];
                if (isset($args['approve']))     $where[] = "approve = " . intval($args['approve']);
                if (!empty($args['category_id'])) $where[] = "FIND_IN_SET(" . intval($args['category_id']) . ", category)";
                if (!empty($args['author']))     $where[] = "autor = '" . $db->safesql(trim($args['author'])) . "'";
                $wSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                $cnt  = $db->super_query("SELECT COUNT(*) as c FROM " . PREFIX . "_post" . $wSql);
                $total = intval($cnt['c'] ?? 0);
                $posts = [];
                $db->query("SELECT id, title, short_story, date, category, alt_name, approve, autor, comm_num, allow_main, fixed FROM " . PREFIX . "_post" . $wSql . " ORDER BY date DESC LIMIT {$limit} OFFSET {$offset}");
                while ($row = $db->get_row()) { $posts[] = $row; }
                return ['total'=>$total,'page'=>$page,'limit'=>$limit,'posts'=>$posts];
            }
        );

        $server->register(
            'articles.get',
            'Get a single article by ID including parsed xfields.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Article ID'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id  = intval($args['id']);
                $row = $db->super_query("SELECT * FROM " . PREFIX . "_post WHERE id = {$id}");
                if (!$row) throw new \RuntimeException("Article {$id} not found");
                $xparsed = [];
                if (!empty($row['xfields'])) {
                    foreach (explode('|', $row['xfields']) as $chunk) {
                        $parts = explode('||', $chunk, 2);
                        if (count($parts) === 2) $xparsed[$parts[0]] = $parts[1];
                    }
                }
                $row['xfields_parsed'] = $xparsed;
                return $row;
            }
        );

        $server->register(
            'articles.create',
            'Create a new article. Returns the new article ID.',
            ['type'=>'object','properties'=>[
                'title'       => ['type'=>'string', 'description'=>'Article title (required)'],
                'short_story' => ['type'=>'string', 'description'=>'Short description / intro (required)'],
                'full_story'  => ['type'=>'string', 'description'=>'Full article body (optional)'],
                'category'    => ['type'=>'string', 'description'=>'Category IDs comma-separated e.g. "3,5"'],
                'tags'        => ['type'=>'string', 'description'=>'Comma-separated tags'],
                'xfields'     => ['type'=>'object', 'description'=>'Extra fields as key-value object'],
                'approve'     => ['type'=>'integer','description'=>'1=published, 0=pending, default 1'],
                'autor'       => ['type'=>'string', 'description'=>'Author name, default admin'],
                'date'        => ['type'=>'string', 'description'=>'Date Y-m-d H:i:s, default now'],
            ],'required'=>['title','short_story']],
            function(array $args) use (&$db) {
                if (empty($args['title']))       throw new \InvalidArgumentException('title is required');
                if (empty($args['short_story'])) throw new \InvalidArgumentException('short_story is required');
                $title      = $db->safesql(trim($args['title']));
                $shortStory = $db->safesql(trim($args['short_story']));
                $fullStory  = $db->safesql(trim($args['full_story']  ?? ''));
                $category   = $db->safesql(trim($args['category']    ?? '0'));
                $tags       = $db->safesql(trim($args['tags']        ?? ''));
                $approve    = isset($args['approve']) ? intval($args['approve']) : 1;
                $autor      = $db->safesql(trim($args['autor']       ?? 'admin'));
                $date       = $db->safesql(trim($args['date']        ?? date('Y-m-d H:i:s')));
                $xfieldsStr = '';
                if (!empty($args['xfields']) && is_array($args['xfields'])) {
                    $parts = [];
                    foreach ($args['xfields'] as $k => $v) {
                        $parts[] = trim($k) . '||' . trim($v);
                    }
                    $xfieldsStr = $db->safesql(implode('|', $parts));
                }
                $altName = $db->safesql(preg_replace('/[^a-z0-9_\-]/i', '-', strtolower(trim($args['title']))));
                if (!$altName) $altName = 'article-' . time();
                $db->query("INSERT INTO " . PREFIX . "_post (autor, date, short_story, full_story, title, category, alt_name, approve, xfields, tags, views, comm_num) VALUES ('{$autor}', '{$date}', '{$shortStory}', '{$fullStory}', '{$title}', '{$category}', '{$altName}', {$approve}, '{$xfieldsStr}', '{$tags}', 0, 0)");
                $newId = $db->insert_id();
                return ['id' => intval($newId), 'alt_name' => $altName];
            }
        );

        $server->register(
            'articles.update',
            'Update fields of an existing article by ID.',
            ['type'=>'object','properties'=>[
                'id'          => ['type'=>'integer','description'=>'Article ID (required)'],
                'title'       => ['type'=>'string', 'description'=>'New title'],
                'short_story' => ['type'=>'string', 'description'=>'New short story'],
                'full_story'  => ['type'=>'string', 'description'=>'New full story'],
                'category'    => ['type'=>'string', 'description'=>'Category IDs comma-separated'],
                'tags'        => ['type'=>'string', 'description'=>'Comma-separated tags'],
                'xfields'     => ['type'=>'object', 'description'=>'Replace xfields as key-value object'],
                'approve'     => ['type'=>'integer','description'=>'1=published, 0=pending'],
                'autor'       => ['type'=>'string', 'description'=>'Author name'],
                'alt_name'    => ['type'=>'string', 'description'=>'URL slug'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id      = intval($args['id']);
                $allowed = ['title','short_story','full_story','category','tags','approve','autor','alt_name','date'];
                $set     = [];
                foreach ($allowed as $key) {
                    if (array_key_exists($key, $args)) {
                        $val   = $db->safesql(trim((string)$args[$key]));
                        $set[] = "{$key} = '{$val}'";
                    }
                }
                if (array_key_exists('xfields', $args) && is_array($args['xfields'])) {
                    $parts = [];
                    foreach ($args['xfields'] as $k => $v) { $parts[] = trim($k) . '||' . trim($v); }
                    $xStr  = $db->safesql(implode('|', $parts));
                    $set[] = "xfields = '{$xStr}'";
                }
                if (!$set) throw new \InvalidArgumentException('No fields to update provided');
                $db->query("UPDATE " . PREFIX . "_post SET " . implode(', ', $set) . " WHERE id = {$id}");
                return ['success' => true];
            }
        );

        $server->register(
            'articles.delete',
            'Delete an article and all its comments by ID.',
            ['type'=>'object','properties'=>[
                'id' => ['type'=>'integer','description'=>'Article ID (required)'],
            ],'required'=>['id']],
            function(array $args) use (&$db) {
                if (empty($args['id'])) throw new \InvalidArgumentException('id is required');
                $id = intval($args['id']);
                $db->query("DELETE FROM " . PREFIX . "_post WHERE id = {$id}");
                $db->query("DELETE FROM " . PREFIX . "_comments WHERE post_id = {$id}");
                return ['success' => true];
            }
        );

        $server->register(
            'articles.search',
            'Search articles by keyword in title or short story.',
            ['type'=>'object','properties'=>[
                'query' => ['type'=>'string', 'description'=>'Search keyword (required)'],
                'page'  => ['type'=>'integer','description'=>'Page number, default 1'],
                'limit' => ['type'=>'integer','description'=>'Results per page, max 100, default 20'],
            ],'required'=>['query']],
            function(array $args) use (&$db) {
                if (empty($args['query'])) throw new \InvalidArgumentException('query is required');
                $q      = $db->safesql(trim($args['query']));
                $page   = max(1, intval($args['page']  ?? 1));
                $limit  = min(100, max(1, intval($args['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $where  = "WHERE title LIKE '%{$q}%' OR short_story LIKE '%{$q}%'";
                $cnt    = $db->super_query("SELECT COUNT(*) as c FROM " . PREFIX . "_post {$where}");
                $total  = intval($cnt['c'] ?? 0);
                $posts  = [];
                $db->query("SELECT id, title, date, approve, autor, comm_num, fixed FROM " . PREFIX . "_post {$where} ORDER BY date DESC LIMIT {$limit} OFFSET {$offset}");
                while ($row = $db->get_row()) { $posts[] = $row; }
                return ['total'=>$total,'page'=>$page,'posts'=>$posts];
            }
        );

        $server->register(
            'articles.massAction',
            'Perform a bulk action on multiple articles: approve, unapprove, delete, or move to category.',
            ['type'=>'object','properties'=>[
                'ids'         => ['type'=>'array', 'description'=>'Array of article IDs (required)'],
                'action'      => ['type'=>'string','description'=>'Action: approve | unapprove | delete | move (required)'],
                'category_id' => ['type'=>'integer','description'=>'Target category ID (required for move)'],
            ],'required'=>['ids','action']],
            function(array $args) use (&$db) {
                if (empty($args['ids']) || !is_array($args['ids'])) throw new \InvalidArgumentException('ids array is required');
                if (empty($args['action'])) throw new \InvalidArgumentException('action is required');
                $ids    = array_map('intval', $args['ids']);
                $inSql  = implode(',', $ids);
                $action = trim($args['action']);
                switch ($action) {
                    case 'approve':
                        $db->query("UPDATE " . PREFIX . "_post SET approve=1 WHERE id IN ({$inSql})");
                        break;
                    case 'unapprove':
                        $db->query("UPDATE " . PREFIX . "_post SET approve=0 WHERE id IN ({$inSql})");
                        break;
                    case 'delete':
                        $db->query("DELETE FROM " . PREFIX . "_post WHERE id IN ({$inSql})");
                        $db->query("DELETE FROM " . PREFIX . "_comments WHERE post_id IN ({$inSql})");
                        break;
                    case 'move':
                        if (empty($args['category_id'])) throw new \InvalidArgumentException('category_id required for move');
                        $cat = intval($args['category_id']);
                        $db->query("UPDATE " . PREFIX . "_post SET category='{$cat}' WHERE id IN ({$inSql})");
                        break;
                    default:
                        throw new \InvalidArgumentException("Unknown action: {$action}. Use: approve, unapprove, delete, move");
                }
                return ['success'=>true,'count'=>count($ids)];
            }
        );
    }
}
