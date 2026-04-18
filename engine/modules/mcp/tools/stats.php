<?php
class MCPToolStats {
    public static function register($server) {
        global $db;

        $server->register(
            'stats.overview',
            'Get site statistics overview: posts, users, comments.',
            ['type'=>'object','properties'=>[],'required'=>[]],
            function(array $args) use (&$db) {
                $postsTotal = $db->super_query("SELECT COUNT(*) as c FROM " . PREFIX . "_post")['c'] ?? 0;
                $postsPub   = $db->super_query("SELECT COUNT(*) as c FROM " . PREFIX . "_post WHERE approve=1")['c'] ?? 0;
                $usersTotal = $db->super_query("SELECT COUNT(*) as c FROM " . USERPREFIX . "_users")['c'] ?? 0;
                $comments   = $db->super_query("SELECT COUNT(*) as c FROM " . PREFIX . "_comments")['c'] ?? 0;
                $hits       = 0;
                $tablesRes = $db->super_query("SHOW TABLES LIKE '" . PREFIX . "_stat'");
                if ($tablesRes) {
                    $statRow = $db->super_query("SELECT SUM(dayhit) as h FROM " . PREFIX . "_stat");
                    if ($statRow && $statRow['h']) $hits = intval($statRow['h']);
                }
                return [
                    'posts_total'     => intval($postsTotal),
                    'posts_published' => intval($postsPub),
                    'users_total'     => intval($usersTotal),
                    'comments_total'  => intval($comments),
                    'hits_total'      => $hits,
                ];
            }
        );

        $server->register(
            'stats.topArticles',
            'Get most commented articles.',
            ['type'=>'object','properties'=>[
                'limit' => ['type'=>'integer','description'=>'Number of articles, max 50, default 10'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $limit = min(50, max(1, intval($args['limit'] ?? 10)));
                $rows  = [];
                $db->query("SELECT id, title, comm_num, date FROM " . PREFIX . "_post ORDER BY comm_num DESC, date DESC LIMIT {$limit}");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return $rows;
            }
        );

        $server->register(
            'stats.adminLogs',
            'View admin action logs.',
            ['type'=>'object','properties'=>[
                'page'   => ['type'=>'integer','description'=>'Page number, default 1'],
                'limit'  => ['type'=>'integer','description'=>'Results per page, max 100, default 20'],
                'search' => ['type'=>'string','description'=>'Search name or extras'],
            ],'required'=>[]],
            function(array $args) use (&$db) {
                $page   = max(1, intval($args['page']  ?? 1));
                $limit  = min(100, max(1, intval($args['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $where  = [];
                if (!empty($args['search'])) {
                    $s = $db->safesql(trim($args['search']));
                    $where[] = "(name LIKE '%{$s}%' OR extras LIKE '%{$s}%')";
                }
                $wSql  = $where ? ' WHERE ' . implode(' AND ', $where) : '';
                $cnt   = $db->super_query("SELECT COUNT(*) as c FROM " . USERPREFIX . "_admin_logs" . $wSql)['c'] ?? 0;
                $rows  = [];
                $db->query("SELECT id, name, date, ip, action, extras FROM " . USERPREFIX . "_admin_logs" . $wSql . " ORDER BY date DESC LIMIT {$limit} OFFSET {$offset}");
                while ($row = $db->get_row()) { $rows[] = $row; }
                return ['total'=>intval($cnt),'page'=>$page,'logs'=>$rows];
            }
        );
    }
}
