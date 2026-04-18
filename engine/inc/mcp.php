<?php
/**
 * DLE MCP Server — Admin Loader
 *
 * Copyright (c) 2026 Atia Hegazy — https://atiaeno.com
 * All rights reserved.
 */

if (!defined('DATALIFEENGINE') OR !defined('LOGGED_IN')) {
    header("HTTP/1.1 403 Forbidden");
    die("Hacking attempt!");
}
if (!$user_group[$member_id['user_group']]['admin_xfields'] && $member_id['user_group'] != '1') {
    msg("error", $lang['index_denied'], $lang['index_denied']);
}
require_once (ENGINE_DIR . '/modules/mcp/admin.php');
