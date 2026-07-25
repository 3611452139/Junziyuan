<?php
require_once 'functions.php';
if (rn_is_ip_blocked(rn_get_client_ip())) {
    rn_json_response(['success' => false, 'message' => '您的IP已被限制访问'], 403);
}
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'get_resources':
        $resources = rn_get_resources();
        $config = rn_get_config();
        $partition = $_GET['partition'] ?? 'all';
        $filtered = [];
        foreach ($resources as &$r) {
            $r['has_group1'] = !empty($r['qq_group_1']);
            $r['has_group2'] = !empty($r['qq_group_2']);
            $r['click_count'] = intval($r['click_count'] ?? 0);
            $r['broken_reports'] = count($r['broken_ips'] ?? []);
            $r['version'] = $r['version'] ?? '';
            $r['updated_at'] = $r['updated_at'] ?? '';
            $r['partition'] = $r['partition'] ?? 'game';
            unset($r['qq_group_1']);
            unset($r['qq_group_2']);
            unset($r['password']);
            unset($r['broken_ips']);
            if ($partition === 'all' || ($r['partition'] ?? 'game') === $partition) {
                $filtered[] = $r;
            }
        }
        rn_success_response([
            'resources' => $filtered,
            'popup' => [
                'enabled' => $config['popup_enabled'] ?? false,
                'title' => $config['popup_title'] ?? '',
                'content' => $config['popup_content'] ?? ''
            ],
            'contact_qq' => $config['contact_qq'] ?? '',
            'site_font' => $config['site_font'] ?? '',
            'carousel' => $config['carousel'] ?? [],
            'carousel_enabled' => $config['carousel_enabled'] ?? false,
            'footer_text' => $config['footer_text'] ?? '',
            'password_protection' => $config['password_protection'] ?? true
        ]);
        break;

    case 'get_qq_groups':
        $resource_id = intval($_POST['resource_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $config = rn_get_config();
        $resources = rn_get_resources();
        $resource = null;
        foreach ($resources as $r) {
            if ($r['id'] === $resource_id) { $resource = $r; break; }
        }
        if (!$resource) rn_error_response('资源不存在');
        $resource_pwd = $resource['password'] ?? '';
        if (empty($resource_pwd)) rn_error_response('未设置验证密码');
        if ($password !== $resource_pwd) rn_error_response('密码错误');
        rn_success_response([
            'group1' => $resource['qq_group_1'] ?? '',
            'group2' => $resource['qq_group_2'] ?? ''
        ]);
        break;

    case 'get_messages':
        global $rn_mysql;
        if ($rn_mysql) {
            $rows = $rn_mysql->query("SELECT * FROM messages ORDER BY msg_time DESC")->fetchAll();
            $messages = [];
            foreach ($rows as $row) {
                $messages[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'content' => $row['content'],
                    'reply_to' => $row['reply_to'] ? (int)$row['reply_to'] : null,
                    'likes' => (int)$row['likes'],
                    'liked_ips' => json_decode($row['liked_ips'] ?? '[]', true) ?: [],
                    'ip' => $row['ip'],
                    'ip_location' => $row['ip_location'],
                    'email' => $row['email'],
                    'time' => $row['msg_time']
                ];
            }
            rn_success_response(['messages' => $messages]);
        } else {
            $messages = rn_get_messages();
            usort($messages, function($a, $b) {
                return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0');
            });
            rn_success_response(['messages' => $messages]);
        }
        break;

    case 'add_message':
        $rl = rn_check_rate_limit(rn_get_client_ip(), 'msg');
        if (!$rl['ok']) rn_error_response('发送太频繁，请' . ceil($rl['wait']) . '秒后再试');
        rn_record_rate_limit(rn_get_client_ip(), 'msg');
        $name = trim($_POST['name'] ?? '匿名');
        $content = trim($_POST['content'] ?? '');
        $reply_to = intval($_POST['reply_to'] ?? 0);
        if (empty($content)) rn_error_response('留言内容不能为空');
        if (mb_strlen($content) > 500) rn_error_response('留言内容过长（最多500字）');
        $msg_time = date('Y-m-d H:i:s');
        $ip = rn_get_client_ip();
        $ip_location = rn_get_ip_location_display($ip);
        $email = trim($_POST['email'] ?? '');

        global $rn_mysql;
        if ($rn_mysql) {
            try {
                $stmt = $rn_mysql->prepare("INSERT INTO messages (name, content, reply_to, likes, liked_ips, ip, ip_location, email, is_reply, msg_time) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                    $reply_to > 0 ? $reply_to : null,
                    0, '[]', $ip, $ip_location, $email,
                    $name === '管理员' ? 1 : 0,
                    $msg_time
                ]);
                $newId = (int)$rn_mysql->lastInsertId();
                $msg = [
                    'id' => $newId,
                    'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                    'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                    'reply_to' => $reply_to > 0 ? $reply_to : null,
                    'likes' => 0,
                    'liked_ips' => [],
                    'ip' => $ip,
                    'ip_location' => $ip_location,
                    'email' => $email,
                    'time' => $msg_time
                ];
                rn_add_log('新增留言', 'IP: ' . $ip);
                rn_wechat_notify('新留言', $name . ': ' . mb_substr($content, 0, 50));
                rn_success_response($msg, '留言成功');
            } catch (Throwable $e) {
                rn_error_response('留言失败，请稍后重试');
            }
            break;
        }

        $messages = rn_get_messages();
        $msg = [
            'id' => count($messages) + 1,
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'reply_to' => $reply_to > 0 ? $reply_to : null,
            'likes' => 0,
            'liked_ips' => [],
            'ip' => $ip,
            'ip_location' => $ip_location,
            'email' => $email,
            'time' => $msg_time
        ];
        $messages[] = $msg;
        if (count($messages) > 1000) $messages = array_slice($messages, -1000);
        rn_save_messages($messages);
        rn_add_log('新增留言', 'IP: ' . $ip);
        rn_wechat_notify('新留言', $name . ': ' . mb_substr($content, 0, 50));
        rn_success_response($msg, '留言成功');
        break;

    case 'like_message':
        $rl = rn_check_rate_limit(rn_get_client_ip(), 'like');
        if (!$rl['ok']) rn_error_response('操作太频繁，请' . ceil($rl['wait']) . '秒后再试');
        rn_record_rate_limit(rn_get_client_ip(), 'like');
        $msg_id = intval($_POST['id'] ?? 0);
        $ip = rn_get_client_ip();

        global $rn_mysql;
        if ($rn_mysql) {
            $stmt = $rn_mysql->prepare("SELECT liked_ips, likes FROM messages WHERE id = ?");
            $stmt->execute([$msg_id]);
            $row = $stmt->fetch();
            if (!$row) rn_error_response('留言不存在');
            $liked_ips = json_decode($row['liked_ips'] ?? '[]', true) ?: [];
            $likes = (int)$row['likes'];

            if (in_array($ip, $liked_ips)) {
                $likes = max(0, $likes - 1);
                $liked_ips = array_values(array_diff($liked_ips, [$ip]));
                $rn_mysql->prepare("UPDATE messages SET likes = ?, liked_ips = ? WHERE id = ?")->execute([$likes, json_encode($liked_ips), $msg_id]);
                rn_success_response(['likes' => $likes, 'liked' => false], '取消点赞');
            } else {
                $likes++;
                $liked_ips[] = $ip;
                $rn_mysql->prepare("UPDATE messages SET likes = ?, liked_ips = ? WHERE id = ?")->execute([$likes, json_encode($liked_ips), $msg_id]);
                rn_success_response(['likes' => $likes, 'liked' => true], '点赞成功');
            }
            break;
        }

        $messages = rn_get_messages();
        $found = false;
        foreach ($messages as &$m) {
            if ($m['id'] === $msg_id) {
                if (!isset($m['liked_ips'])) $m['liked_ips'] = [];
                if (!isset($m['likes'])) $m['likes'] = 0;
                if (in_array($ip, $m['liked_ips'])) {
                    $m['likes'] = max(0, $m['likes'] - 1);
                    $m['liked_ips'] = array_diff($m['liked_ips'], [$ip]);
                    $found = true;
                    rn_save_messages($messages);
                    rn_success_response(['likes' => $m['likes'], 'liked' => false], '取消点赞');
                    break;
                } else {
                    $m['likes']++;
                    $m['liked_ips'][] = $ip;
                    $found = true;
                    rn_save_messages($messages);
                    rn_success_response(['likes' => $m['likes'], 'liked' => true], '点赞成功');
                    break;
                }
            }
        }
        if (!$found) rn_error_response('留言不存在');
        break;

    case 'get_urges':
        $urges = rn_get_urges();
        usort($urges, function($a, $b) {
            return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0');
        });
        rn_success_response(['urges' => $urges]);
        break;

    case 'add_urge':
        $rl = rn_check_rate_limit(rn_get_client_ip(), 'urge');
        if (!$rl['ok']) rn_error_response('发送太频繁，请' . ceil($rl['wait']) . '秒后再试');
        rn_record_rate_limit(rn_get_client_ip(), 'urge');
        $resource_name = trim($_POST['resource_name'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) rn_error_response('催更内容不能为空');
        if (mb_strlen($content) > 200) rn_error_response('催更内容过长（最多200字）');
        $urges = rn_get_urges();
        $urge = [
            'id' => count($urges) + 1,
            'resource_name' => htmlspecialchars($resource_name, ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'replies' => [],
            'ip' => rn_get_client_ip(),
            'ip_location' => rn_get_ip_location_display(rn_get_client_ip()),
            'email' => trim($_POST['email'] ?? ''),
            'time' => date('Y-m-d H:i:s')
        ];
        $urges[] = $urge;
        if (count($urges) > 500) $urges = array_slice($urges, -500);
        rn_save_urges($urges);
        rn_add_log('新增催更', $resource_name . ': ' . $content);
        rn_wechat_notify('新催更', $resource_name . ': ' . mb_substr($content, 0, 50));
        rn_success_response($urge, '催更成功');
        break;

    case 'reply_urge':
        $urge_id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '匿名');
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) rn_error_response('回复内容不能为空');
        if (mb_strlen($content) > 300) rn_error_response('回复内容过长（最多300字）');
        $urges = rn_get_urges();
        $found = false;
        $reply = null;
        foreach ($urges as &$u) {
            if ($u['id'] === $urge_id) {
                if (!isset($u['replies'])) $u['replies'] = [];
                $reply = [
                    'id' => count($u['replies']) + 1,
                    'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                    'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                    'ip' => rn_get_client_ip(),
                    'ip_location' => rn_get_ip_location_display(rn_get_client_ip()),
                    'time' => date('Y-m-d H:i:s')
                ];
                $u['replies'][] = $reply;
                $found = true;
                break;
            }
        }
        if (!$found) rn_error_response('催更不存在');
        rn_save_urges($urges);
        rn_add_log('回复催更', '#' . $urge_id . ': ' . $name . ' - ' . mb_substr($content, 0, 50));
        rn_success_response($reply, '回复成功');
        break;

    case 'get_stats':
        $stats = rn_get_stats();
        rn_success_response($stats);
        break;

    case 'track_click':
        $rid = intval($_POST['id'] ?? 0);
        $resources = rn_get_resources();
        foreach ($resources as &$r) {
            if ($r['id'] === $rid) {
                $r['click_count'] = ($r['click_count'] ?? 0) + 1;
                break;
            }
        }
        rn_save_resources($resources);
        rn_success_response(null);
        break;

    case 'report_broken':
        $rid = intval($_POST['id'] ?? 0);
        $ip = rn_get_client_ip();
        $resources = rn_get_resources();
        $found = false;
        foreach ($resources as &$r) {
            if ($r['id'] === $rid) {
                if (!isset($r['broken_ips'])) $r['broken_ips'] = [];
                if (in_array($ip, $r['broken_ips'])) {
                    rn_success_response(null, '你已举报过该资源');
                    exit;
                }
                $r['broken_ips'][] = $ip;
                $found = true;
                break;
            }
        }
        if ($found) {
            rn_save_resources($resources);
            rn_add_log('资源失效举报', 'ID: ' . $rid);
            rn_success_response(null, '举报成功，感谢反馈');
        } else {
            rn_error_response('资源不存在');
        }
        break;

    case 'view_password':
        $rid = intval($_POST['id'] ?? 0);
        $resources = rn_get_resources();
        $password = '';
        foreach ($resources as $r) {
            if ($r['id'] === $rid) {
                $password = $r['password'] ?? '';
                break;
            }
        }
        if ($password === '') rn_error_response('该资源无密码');
        rn_add_log('查看密码', 'ID: ' . $rid . ' - ' . rn_get_ip_location_display(rn_get_client_ip()));
        rn_success_response(['password' => $password]);
        break;

    case 'get_top_referers':
        global $rn_mysql;
        if ($rn_mysql) {
            $refs = []; $browsers = []; $devices = [];
            $rows = $rn_mysql->query("SELECT referer, browser, device FROM visitors")->fetchAll();
            foreach ($rows as $v) {
                $ref = $v['referer'];
                if (empty($ref)) $ref = '直接访问';
                if ($ref === 'Direct') $ref = '直接访问';
                if (!isset($refs[$ref])) $refs[$ref] = 0;
                $refs[$ref]++;
                $br = $v['browser'] ?? '其他';
                if (!isset($browsers[$br])) $browsers[$br] = 0;
                $browsers[$br]++;
                $dv = $v['device'] ?? 'Desktop';
                if (!isset($devices[$dv])) $devices[$dv] = 0;
                $devices[$dv]++;
            }
            arsort($refs); arsort($browsers); arsort($devices);
            rn_success_response(['referers' => array_slice($refs, 0, 10), 'browsers' => $browsers, 'devices' => $devices]);
            break;
        }
        $visitors = rn_get_visitors();
        $refs = [];
        $browsers = [];
        $devices = [];
        foreach ($visitors as $v) {
            $ref = $v['referer'] ?? '';
            if (empty($ref)) $ref = '直接访问';
            if ($ref === 'Direct') $ref = '直接访问';
            if (!isset($refs[$ref])) $refs[$ref] = 0;
            $refs[$ref]++;
            $br = $v['browser'] ?? '其他';
            if (!isset($browsers[$br])) $browsers[$br] = 0;
            $browsers[$br]++;
            $dv = $v['device'] ?? 'Desktop';
            if (!isset($devices[$dv])) $devices[$dv] = 0;
            $devices[$dv]++;
        }
        arsort($refs);
        arsort($browsers);
        arsort($devices);
        $top_refs = array_slice($refs, 0, 10);
        rn_success_response(['referers' => $top_refs, 'browsers' => $browsers, 'devices' => $devices]);
        break;

    case 'admin_login':
        $password = $_POST['password'] ?? '';
        if (rn_admin_login($password)) rn_success_response(null, '登录成功');
        else rn_error_response('密码错误', 401);
        break;

    case 'admin_logout':
        rn_admin_logout();
        rn_success_response(null, '已登出');
        break;

    case 'check_login':
        if (rn_is_admin_logged_in()) rn_success_response(null, '已登录');
        else rn_error_response('未登录', 401);
        break;

    case 'admin_get_stats':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $config = rn_get_config();
        $stats = rn_get_stats();
        $stats['resources_count'] = count(rn_get_resources());
        $stats['messages_count'] = count(rn_get_messages());
        $stats['urges_count'] = count(rn_get_urges());
        $stats['total_visitors'] = count($config['unique_ips'] ?? []);
        $stats['login_count'] = count(rn_get_login_logs());
        rn_success_response($stats);
        break;

    case 'admin_get_resources':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $res = rn_get_resources();
        usort($res, function($a, $b) { return ($a['sort_order'] ?? 999) - ($b['sort_order'] ?? 999); });
        rn_success_response(['resources' => $res]);
        break;

    case 'admin_save_resource':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $id = intval($_POST['id'] ?? 0);
        $resources = rn_get_resources();
        $data = [
            'id' => $id,
            'name' => trim($_POST['name'] ?? ''),
            'url' => trim($_POST['url'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'qq_group_1' => trim($_POST['qq_group_1'] ?? ''),
            'qq_group_2' => trim($_POST['qq_group_2'] ?? ''),
            'color' => trim($_POST['color'] ?? '#4a90d9'),
            'description' => trim($_POST['description'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'partition' => trim($_POST['partition'] ?? 'game'),
            'icon' => trim($_POST['icon'] ?? ''),
            'sort_order' => intval($_POST['sort_order'] ?? 999),
            'version' => trim($_POST['version'] ?? ''),
            'updated_at' => trim($_POST['updated_at'] ?? '')
        ];
        if (empty($data['name']) || empty($data['url'])) rn_error_response('名称和链接不能为空');

        // Keep existing icon if not changed
        $found = false;
        foreach ($resources as &$r) {
            if ($r['id'] === $id) {
                if (empty($data['icon'])) $data['icon'] = $r['icon'] ?? '';
                $r = array_merge($r, $data);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $data['id'] = count($resources) > 0 ? max(array_column($resources, 'id')) + 1 : 1;
            $resources[] = $data;
        }
        rn_save_resources($resources);
        $changed = $found ? '已更新' : '新增';
        rn_add_log('保存资源', $changed . ': ' . $data['name'] . ' [分类:' . $data['category'] . '] [颜色:' . $data['color'] . ']');
        rn_success_response(null, '保存成功');
        break;

    case 'admin_delete_resource':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $id = intval($_POST['id'] ?? 0);
        $resources = rn_get_resources();
        // Remove icon file
        foreach ($resources as $r) {
            if ($r['id'] === $id && !empty($r['icon'])) {
                $icon_path = __DIR__ . '/data/' . $r['icon'];
                if (file_exists($icon_path)) @unlink($icon_path);
            }
        }
        $resources = array_values(array_filter($resources, function($r) use ($id) { return $r['id'] !== $id; }));
        rn_save_resources($resources);
        rn_add_log('删除资源', 'ID: ' . $id);
        rn_success_response(null, '删除成功');
        break;

    case 'admin_upload_icon':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        if (empty($_FILES['icon'])) rn_error_response('未选择文件');
        $file = $_FILES['icon'];
        if ($file['error'] !== UPLOAD_ERR_OK) rn_error_response('上传失败');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) rn_error_response('仅支持 jpg/png/gif/webp/svg');
        $icons_dir = __DIR__ . '/data/icons/';
        if (!is_dir($icons_dir)) mkdir($icons_dir, 0755, true);
        $newname = 'icon_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $dest = $icons_dir . $newname;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            rn_success_response(['filename' => 'icons/' . $newname], '上传成功');
        } else {
            rn_error_response('文件保存失败');
        }
        break;

    case 'admin_get_visitors':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $mode = $_GET['mode'] ?? 'simple';
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(5, intval($_GET['per_page'] ?? 20));

        global $rn_mysql;
        if ($rn_mysql) {
            if ($mode === 'simple') {
                $totalStmt = $rn_mysql->query("SELECT COUNT(DISTINCT ip) FROM visitors");
                $total = (int)$totalStmt->fetchColumn();
                $total_pages = max(1, ceil($total / $per_page));
                $offset = ($page - 1) * $per_page;

                $rows = $rn_mysql->query("
                    SELECT ip, MAX(visit_time) as last_time, MIN(visit_time) as first_time, COUNT(*) as visit_count,
                           MAX(os) as os, MAX(browser) as browser, MAX(device) as device,
                           MAX(country) as country, MAX(region) as region, MAX(city) as city,
                           MAX(isp) as isp, MAX(referer) as referer
                    FROM visitors
                    GROUP BY ip
                    ORDER BY last_time DESC
                    LIMIT {$per_page} OFFSET {$offset}
                ")->fetchAll();

                $visitors = [];
                foreach ($rows as $row) {
                    $ip = $row['ip'];
                    $details = $rn_mysql->query("SELECT * FROM visitors WHERE ip = '{$ip}' ORDER BY visit_time DESC LIMIT 50")->fetchAll();
                    $detailArr = [];
                    foreach ($details as $d) {
                        $detailArr[] = [
                            'time' => $d['visit_time'],
                            'referer' => $d['referer'],
                            'isp' => $d['isp'],
                            'zip' => $d['zip'],
                            'lat' => $d['lat'],
                            'lon' => $d['lon'],
                        ];
                    }
                    $visitors[] = [
                        'ip' => $ip,
                        'location_display' => rn_get_ip_location_display($ip),
                        'os' => $row['os'],
                        'device' => $row['device'],
                        'browser' => $row['browser'],
                        'visit_count' => (int)$row['visit_count'],
                        'first_time' => $row['first_time'],
                        'last_time' => $row['last_time'],
                        'details' => $detailArr
                    ];
                }

                rn_success_response(['visitors' => $visitors, 'mode' => $mode, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
            } else {
                $totalStmt = $rn_mysql->query("SELECT COUNT(*) FROM visitors");
                $total = (int)$totalStmt->fetchColumn();
                $total_pages = max(1, ceil($total / $per_page));
                $offset = ($page - 1) * $per_page;

                $rows = $rn_mysql->query("SELECT * FROM visitors ORDER BY visit_time DESC LIMIT {$per_page} OFFSET {$offset}")->fetchAll();
                $visitors = [];
                foreach ($rows as $row) {
                    $visitors[] = [
                        'ip' => $row['ip'],
                        'location_display' => rn_get_ip_location_display($row['ip']),
                        'isp' => $row['isp'],
                        'os' => $row['os'],
                        'browser' => $row['browser'],
                        'device' => $row['device'],
                        'referer' => $row['referer'],
                        'lat' => $row['lat'],
                        'lon' => $row['lon'],
                        'timezone' => $row['timezone'],
                        'time' => $row['visit_time']
                    ];
                }
                rn_success_response(['visitors' => $visitors, 'mode' => $mode, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
            }
            break;
        }

        $visitors = rn_get_visitors();
        usort($visitors, function($a, $b) { return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0'); });
        if ($mode === 'simple') {
            $grouped = [];
            foreach ($visitors as $v) {
                $ip = $v['ip'];
                if (!isset($grouped[$ip])) {
                    $v['location_display'] = rn_get_ip_location_display($ip);
                    $v['visit_count'] = 0;
                    $v['first_time'] = $v['time'];
                    $v['last_time'] = $v['time'];
                    $v['details'] = [];
                    $grouped[$ip] = $v;
                }
                $grouped[$ip]['visit_count']++;
                $grouped[$ip]['last_time'] = $v['time'];
                if (strtotime($v['time']) < strtotime($grouped[$ip]['first_time'])) {
                    $grouped[$ip]['first_time'] = $v['time'];
                }
                $grouped[$ip]['details'][] = $v;
            }
            foreach ($grouped as &$g) {
                usort($g['details'], function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
            }
            $visitors = array_values($grouped);
            usort($visitors, function($a, $b) { return strtotime($b['last_time']) - strtotime($a['last_time']); });
        } else {
            usort($visitors, function($a, $b) { return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0'); });
            $total = count($visitors);
            $total_pages = ceil($total / $per_page);
            $offset = ($page - 1) * $per_page;
            $visitors = array_slice($visitors, $offset, $per_page);
            foreach ($visitors as &$v) {
                $v['location_display'] = rn_get_ip_location_display($v['ip']);
            }
            rn_success_response(['visitors' => $visitors, 'mode' => $mode, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
            break;
        }
        $total = count($visitors);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $visitors = array_slice($visitors, $offset, $per_page);
        rn_success_response(['visitors' => $visitors, 'mode' => $mode, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
        break;

    case 'admin_get_messages':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(5, intval($_GET['per_page'] ?? 20));

        global $rn_mysql;
        if ($rn_mysql) {
            $total = (int)$rn_mysql->query("SELECT COUNT(*) FROM messages")->fetchColumn();
            $total_pages = max(1, ceil($total / $per_page));
            $offset = ($page - 1) * $per_page;
            $rows = $rn_mysql->query("SELECT * FROM messages ORDER BY msg_time DESC LIMIT {$per_page} OFFSET {$offset}")->fetchAll();
            $messages = [];
            foreach ($rows as $row) {
                $messages[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'content' => $row['content'],
                    'reply_to' => $row['reply_to'] ? (int)$row['reply_to'] : null,
                    'likes' => (int)$row['likes'],
                    'liked_ips' => json_decode($row['liked_ips'] ?? '[]', true) ?: [],
                    'ip' => $row['ip'],
                    'ip_location' => $row['ip_location'],
                    'email' => $row['email'],
                    'time' => $row['msg_time']
                ];
            }
            rn_success_response(['messages' => $messages, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
            break;
        }

        $messages = rn_get_messages();
        usort($messages, function($a, $b) { return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0'); });
        $total = count($messages);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $messages = array_slice($messages, $offset, $per_page);
        rn_success_response(['messages' => $messages, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
        break;

    case 'admin_reply_message':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $reply_to = intval($_POST['reply_to'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) rn_error_response('回复内容不能为空');
        $msg_time = date('Y-m-d H:i:s');

        global $rn_mysql;
        if ($rn_mysql) {
            $stmt = $rn_mysql->prepare("SELECT email FROM messages WHERE id = ?");
            $stmt->execute([$reply_to]);
            $row = $stmt->fetch();
            $parent_email = $row ? ($row['email'] ?? '') : '';

            $stmt = $rn_mysql->prepare("INSERT INTO messages (name, content, reply_to, likes, liked_ips, ip, ip_location, email, is_reply, msg_time) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute(['管理员', htmlspecialchars($content, ENT_QUOTES, 'UTF-8'), $reply_to, 0, '[]', rn_get_client_ip(), '', '', 1, $msg_time]);
            $newId = (int)$rn_mysql->lastInsertId();
            rn_add_log('回复留言', '回复ID: ' . $reply_to);

            if (!empty($parent_email)) {
                $config = rn_get_config();
                rn_send_smtp_mail($parent_email, ($config['site_title'] ?? '资源导航') . ' - 留言回复通知', "您的留言收到了管理员回复：\n\n" . $content . "\n\n——" . ($config['site_title'] ?? '资源导航'));
            }
            rn_success_response([
                'id' => $newId, 'name' => '管理员', 'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                'reply_to' => $reply_to, 'likes' => 0, 'liked_ips' => [], 'ip' => rn_get_client_ip(), 'time' => $msg_time
            ], '回复成功');
            break;
        }

        $messages = rn_get_messages();
        $parent_email = '';
        foreach ($messages as $m) {
            if ($m['id'] === $reply_to) { $parent_email = $m['email'] ?? ''; break; }
        }
        $msg = [
            'id' => count($messages) + 1,
            'name' => '管理员',
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'reply_to' => $reply_to,
            'likes' => 0,
            'liked_ips' => [],
            'ip' => rn_get_client_ip(),
            'time' => date('Y-m-d H:i:s')
        ];
        $messages[] = $msg;
        rn_save_messages($messages);
        rn_add_log('回复留言', '回复ID: ' . $reply_to);
        if (!empty($parent_email)) {
            $config = rn_get_config();
            rn_send_smtp_mail($parent_email, ($config['site_title'] ?? '资源导航') . ' - 留言回复通知', "您的留言收到了管理员回复：\n\n" . $content . "\n\n——" . ($config['site_title'] ?? '资源导航'));
        }
        rn_success_response($msg, '回复成功');
        break;

    case 'admin_delete_message':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $id = intval($_POST['id'] ?? 0);

        global $rn_mysql;
        if ($rn_mysql) {
            $rn_mysql->prepare("DELETE FROM messages WHERE id = ?")->execute([$id]);
            rn_add_log('删除留言', 'ID: ' . $id);
            rn_success_response(null, '删除成功');
            break;
        }

        $messages = rn_get_messages();
        $messages = array_values(array_filter($messages, function($m) use ($id) { return $m['id'] !== $id; }));
        rn_save_messages($messages);
        rn_add_log('删除留言', 'ID: ' . $id);
        rn_success_response(null, '删除成功');
        break;

    case 'admin_get_urges':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(5, intval($_GET['per_page'] ?? 20));
        $urges = rn_get_urges();
        usort($urges, function($a, $b) { return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0'); });
        $total = count($urges);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $urges = array_slice($urges, $offset, $per_page);
        rn_success_response(['urges' => $urges, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
        break;

    case 'admin_reply_urge':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $urge_id = intval($_POST['id'] ?? 0);
        $reply_content = trim($_POST['reply'] ?? '');
        if (empty($reply_content)) rn_error_response('回复内容不能为空');
        $urges = rn_get_urges();
        $found = false;
        $urge_email = '';
        foreach ($urges as &$u) {
            if ($u['id'] === $urge_id) {
                if (!isset($u['replies'])) $u['replies'] = [];
                $urge_email = $u['email'] ?? '';
                $admin_reply = [
                    'id' => count($u['replies']) + 1,
                    'name' => '管理员',
                    'content' => htmlspecialchars($reply_content, ENT_QUOTES, 'UTF-8'),
                    'ip' => rn_get_client_ip(),
                    'ip_location' => rn_get_ip_location_display(rn_get_client_ip()),
                    'time' => date('Y-m-d H:i:s')
                ];
                $u['replies'][] = $admin_reply;
                $found = true;
                break;
            }
        }
        if (!$found) rn_error_response('催更不存在');
        rn_save_urges($urges);
        rn_add_log('回复催更', 'ID: ' . $urge_id);
        if (!empty($urge_email)) {
            $config = rn_get_config();
            rn_send_smtp_mail($urge_email, ($config['site_title'] ?? '资源导航') . ' - 催更回复通知', "您的催更收到了管理员回复：\n\n" . $reply_content . "\n\n——" . ($config['site_title'] ?? '资源导航'));
        }
        rn_success_response(null, '回复成功');
        break;

    case 'admin_delete_urge':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $id = intval($_POST['id'] ?? 0);
        $urges = rn_get_urges();
        $urges = array_values(array_filter($urges, function($u) use ($id) { return $u['id'] !== $id; }));
        rn_save_urges($urges);
        rn_add_log('删除催更', 'ID: ' . $id);
        rn_success_response(null, '删除成功');
        break;

    case 'admin_get_logs':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(5, intval($_GET['per_page'] ?? 20));
        $logs = rn_get_logs();
        usort($logs, function($a, $b) { return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0'); });
        $total = count($logs);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $logs = array_slice($logs, $offset, $per_page);
        rn_success_response(['logs' => $logs, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
        break;

    case 'admin_get_login_logs':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(5, intval($_GET['per_page'] ?? 20));
        $logs = rn_get_login_logs();
        foreach ($logs as &$l) {
            $l['location_display'] = rn_get_ip_location_display($l['ip']);
        }
        usort($logs, function($a, $b) { return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0'); });
        $total = count($logs);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $logs = array_slice($logs, $offset, $per_page);
        rn_success_response(['logs' => $logs, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages]);
        break;

    case 'admin_get_settings':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $config = rn_get_config();
        rn_success_response([
            'popup_enabled' => $config['popup_enabled'] ?? false,
            'popup_title' => $config['popup_title'] ?? '',
            'popup_content' => $config['popup_content'] ?? '',
            'qq_group_password' => $config['qq_group_password'] ?? '',
            'contact_qq' => $config['contact_qq'] ?? '',
            'admin_password' => '',
            'btn_jump_text' => $config['btn_jump_text'] ?? '蓝奏云下载',
            'btn_qq_text' => $config['btn_qq_text'] ?? 'qq群(文件在q群文件)',
            'btn_urge_text' => $config['btn_urge_text'] ?? '催更',
            'contact_text' => $config['contact_text'] ?? '如有疑问请联系QQ',
            'section_guestbook' => $config['section_guestbook'] ?? '留言板',
            'section_urge_wall' => $config['section_urge_wall'] ?? '催更墙',
            'qq_hint_text' => $config['qq_hint_text'] ?? '输入密码查看内容',
            'shop_name' => $config['shop_name'] ?? '资源导航',
            'shop_sub' => $config['shop_sub'] ?? '',
            'site_title' => $config['site_title'] ?? '资源导航',
            'site_font' => $config['site_font'] ?? '',
            'dark_mode' => $config['dark_mode'] ?? 'auto',
            'wechat_key' => $config['sct_key'] ?? $config['wechat_key'] ?? '',
            'sct_key' => $config['sct_key'] ?? $config['wechat_key'] ?? '',
            'wxpusher_token' => $config['wxpusher_token'] ?? '',
            'wxpusher_uid' => $config['wxpusher_uid'] ?? '',
            'bark_key' => $config['bark_key'] ?? '',
            'pushplus_token' => $config['pushplus_token'] ?? '',
            'smtp_host' => $config['smtp_host'] ?? 'smtp.qq.com',
            'smtp_port' => $config['smtp_port'] ?? '465',
            'smtp_user' => $config['smtp_user'] ?? '',
            'smtp_pass' => $config['smtp_pass'] ?? '',
            'shop_icon' => $config['shop_icon'] ?? '',
            'carousel' => $config['carousel'] ?? [],
            'carousel_enabled' => $config['carousel_enabled'] ?? false,
            'carousel_speed' => $config['carousel_speed'] ?? '4000',
            'carousel_height_desktop' => $config['carousel_height_desktop'] ?? '160',
            'carousel_height_mobile' => $config['carousel_height_mobile'] ?? '110',
            'rate_limit_enabled' => $config['rate_limit_enabled'] ?? true,
            'rate_limit_msg' => $config['rate_limit_msg'] ?? '3',
            'rate_limit_urge' => $config['rate_limit_urge'] ?? '3',
            'rate_limit_like' => $config['rate_limit_like'] ?? '10',
            'footer_text' => $config['footer_text'] ?? '',
            'password_protection' => $config['password_protection'] ?? true,
            'auto_block_enabled' => $config['auto_block_enabled'] ?? true,
            'auto_block_window' => $config['auto_block_window'] ?? '60',
            'auto_block_threshold' => $config['auto_block_threshold'] ?? '30',
            'auto_block_whitelist' => $config['auto_block_whitelist'] ?? '山东,威海',
            'auto_block_ip_whitelist' => $config['auto_block_ip_whitelist'] ?? '',
            'views_offset' => $config['views_offset'] ?? '0',
            'visitors_offset' => $config['visitors_offset'] ?? '0',
            'today_visitors_offset' => $config['today_visitors_offset'] ?? '0',
            'partitions' => $config['partitions'] ?? []
        ]);
        break;

    case 'admin_save_settings':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $config = rn_get_config();
        $config['popup_enabled'] = ($_POST['popup_enabled'] ?? 'false') === 'true';
        $config['popup_title'] = trim($_POST['popup_title'] ?? '');
        $config['popup_content'] = trim($_POST['popup_content'] ?? '');
        $config['qq_group_password'] = trim($_POST['qq_group_password'] ?? '');
        $config['contact_qq'] = trim($_POST['contact_qq'] ?? '');
        $config['btn_jump_text'] = trim($_POST['btn_jump_text'] ?? '蓝奏云下载');
        $config['btn_qq_text'] = trim($_POST['btn_qq_text'] ?? 'qq群(文件在q群文件)');
        $config['btn_urge_text'] = trim($_POST['btn_urge_text'] ?? '催更');
        $config['contact_text'] = trim($_POST['contact_text'] ?? '如有疑问请联系QQ');
        $config['section_guestbook'] = trim($_POST['section_guestbook'] ?? '留言板');
        $config['section_urge_wall'] = trim($_POST['section_urge_wall'] ?? '催更墙');
        $config['qq_hint_text'] = trim($_POST['qq_hint_text'] ?? '输入密码查看内容');
        $config['shop_name'] = trim($_POST['shop_name'] ?? '资源导航');
        $config['shop_sub'] = trim($_POST['shop_sub'] ?? '');
        $config['site_title'] = trim($_POST['site_title'] ?? '资源导航');
        $config['site_font'] = trim($_POST['site_font'] ?? '');
        $config['dark_mode'] = trim($_POST['dark_mode'] ?? 'auto');
        $config['pushplus_token'] = trim($_POST['pushplus_token'] ?? '');
        $config['sct_key'] = trim($_POST['sct_key'] ?? '');
        if (empty($config['sct_key'])) $config['sct_key'] = trim($_POST['wechat_key'] ?? '');
        $config['wxpusher_token'] = trim($_POST['wxpusher_token'] ?? '');
        $config['wxpusher_uid'] = trim($_POST['wxpusher_uid'] ?? '');
        $config['bark_key'] = trim($_POST['bark_key'] ?? '');
        $config['smtp_host'] = trim($_POST['smtp_host'] ?? 'smtp.qq.com');
        $config['smtp_port'] = trim($_POST['smtp_port'] ?? '465');
        $config['smtp_user'] = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');
        if (!empty($smtp_pass)) $config['smtp_pass'] = $smtp_pass;
        $config['shop_icon'] = trim($_POST['shop_icon'] ?? '');
        $config['carousel_enabled'] = ($_POST['carousel_enabled'] ?? 'false') === 'true';
        $config['carousel_speed'] = trim($_POST['carousel_speed'] ?? '4000');
        $config['carousel_height_desktop'] = trim($_POST['carousel_height_desktop'] ?? '160');
        $config['carousel_height_mobile'] = trim($_POST['carousel_height_mobile'] ?? '110');
        $config['rate_limit_enabled'] = ($_POST['rate_limit_enabled'] ?? 'true') === 'true';
        $config['rate_limit_msg'] = trim($_POST['rate_limit_msg'] ?? '3');
        $config['rate_limit_urge'] = trim($_POST['rate_limit_urge'] ?? '3');
        $config['rate_limit_like'] = trim($_POST['rate_limit_like'] ?? '10');
        $config['footer_text'] = trim($_POST['footer_text'] ?? '');
        $config['password_protection'] = ($_POST['password_protection'] ?? 'true') === 'true';
        $config['auto_block_enabled'] = ($_POST['auto_block_enabled'] ?? 'true') === 'true';
        $config['auto_block_window'] = trim($_POST['auto_block_window'] ?? '60');
        $config['auto_block_threshold'] = trim($_POST['auto_block_threshold'] ?? '30');
        $config['auto_block_whitelist'] = trim($_POST['auto_block_whitelist'] ?? '山东,威海');
        $config['auto_block_ip_whitelist'] = trim($_POST['auto_block_ip_whitelist'] ?? '');
        $config['views_offset'] = trim($_POST['views_offset'] ?? '0');
        $config['visitors_offset'] = trim($_POST['visitors_offset'] ?? '0');
        $config['today_visitors_offset'] = trim($_POST['today_visitors_offset'] ?? '0');
        // carousel slides
        $slides = [];
        $slide_titles = $_POST['slide_title'] ?? [];
        $slide_urls = $_POST['slide_url'] ?? [];
        $slide_imgs = $_POST['slide_img'] ?? [];
        foreach ($slide_titles as $i => $t) {
            $slides[] = ['title' => trim($t), 'url' => trim($slide_urls[$i] ?? ''), 'img' => trim($slide_imgs[$i] ?? '')];
        }
        $config['carousel'] = $slides;
        // partitions
        $partitions = [];
        $part_ids = $_POST['partition_id'] ?? [];
        $part_names = $_POST['partition_name'] ?? [];
        foreach ($part_ids as $i => $pid) {
            $pid = trim($pid);
            $pname = trim($part_names[$i] ?? '');
            if (!empty($pid) && !empty($pname)) {
                $partitions[] = ['id' => $pid, 'name' => $pname];
            }
        }
        if (empty($partitions)) {
            $partitions = [['id' => 'game', 'name' => '游戏内核分区'], ['id' => 'other', 'name' => '其他分区']];
        }
        $config['partitions'] = $partitions;
        $new_password = trim($_POST['admin_password'] ?? '');
        if (!empty($new_password)) $config['admin_password'] = $new_password;
        rn_save_config($config);
        $changedItems = [];
        if (!empty($config['shop_name'])) $changedItems[] = '店铺名';
        if ($config['popup_enabled']) $changedItems[] = '弹窗';
        if ($config['carousel_enabled']) $changedItems[] = '轮播图';
        if (!empty($_POST['admin_password'])) $changedItems[] = '密码已修改';
        rn_add_log('修改设置', '更新: ' . (implode(',', $changedItems) ?: '基本配置'));
        rn_success_response(null, '保存成功');
        break;

    case 'admin_sort_resources':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $ids = explode(',', $_POST['ids'] ?? '');
        $resources = rn_get_resources();
        foreach ($resources as &$r) {
            $idx = array_search((string)$r['id'], $ids);
            if ($idx !== false) $r['sort_order'] = $idx;
        }
        rn_save_resources($resources);
        rn_success_response(null, '排序已保存');
        break;

    case 'admin_clear_visitors':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        global $rn_mysql;
        if ($rn_mysql) {
            $rn_mysql->exec("TRUNCATE TABLE visitors");
        } else {
            rn_save_visitors([]);
        }
        rn_add_log('清空访客记录');
        rn_success_response(null, '访客记录已清空');
        break;

    case 'admin_clear_logs':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        rn_save_logs([]);
        rn_add_log('清空操作日志');
        rn_success_response(null, '操作日志已清空');
        break;

    case 'admin_clear_login_logs':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        rn_save_login_logs([]);
        rn_add_log('清空登录记录');
        rn_success_response(null, '登录记录已清空');
        break;

    case 'admin_clear_messages':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        global $rn_mysql;
        if ($rn_mysql) {
            $rn_mysql->exec("TRUNCATE TABLE messages");
        } else {
            rn_save_messages([]);
        }
        rn_add_log('清空留言');
        rn_success_response(null, '留言已清空');
        break;

    case 'admin_clear_urges':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        rn_save_urges([]);
        rn_add_log('清空催更');
        rn_success_response(null, '催更已清空');
        break;

    case 'admin_clear_broken':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $rid = intval($_POST['id'] ?? 0);
        $resources = rn_get_resources();
        foreach ($resources as &$r) {
            if ($r['id'] === $rid) { $r['broken_ips'] = []; break; }
        }
        rn_save_resources($resources);
        rn_success_response(null, '举报已清除');
        break;

    case 'admin_sort_by_clicks':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $resources = rn_get_resources();
        usort($resources, function($a, $b) { return ($b['click_count'] ?? 0) - ($a['click_count'] ?? 0); });
        foreach ($resources as $i => &$r) { $r['sort_order'] = $i + 1; }
        rn_save_resources($resources);
        rn_add_log('按热度重新排序');
        rn_success_response(null, '已按热度排序');
        break;

    case 'admin_upload_carousel':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        if (empty($_FILES['image'])) rn_error_response('未选择文件');
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) rn_error_response('上传失败');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) rn_error_response('仅支持 jpg/png/webp/gif');
        $cdir = __DIR__ . '/data/banners/';
        if (!is_dir($cdir)) mkdir($cdir, 0755, true);
        $newname = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $cdir . $newname))
            rn_success_response(['filename' => 'banners/' . $newname], '上传成功');
        else rn_error_response('文件保存失败');
        break;

    case 'admin_blacklist_list':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $blacklist = rn_get_blacklist();
        rn_success_response(['blacklist' => $blacklist, 'total' => count($blacklist)]);
        break;

    case 'admin_blacklist_add':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $ip = trim($_POST['ip'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if (empty($ip)) rn_error_response('IP地址不能为空');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) rn_error_response('无效的IP地址');
        if (rn_block_ip($ip, $reason)) rn_success_response(null, 'IP已加入黑名单');
        else rn_error_response('该IP已在黑名单中');
        break;

    case 'admin_blacklist_remove':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $ip = trim($_POST['ip'] ?? '');
        if (empty($ip)) rn_error_response('IP地址不能为空');
        if (rn_unblock_ip($ip)) rn_success_response(null, 'IP已从黑名单移除');
        else rn_error_response('该IP不在黑名单中');
        break;

    case 'admin_blacklist_batch':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $ips = trim($_POST['ips'] ?? '');
        $reason = trim($_POST['reason'] ?? '批量拉黑');
        if (empty($ips)) rn_error_response('IP列表不能为空');
        $ip_list = preg_split('/[\s,;]+/', $ips, -1, PREG_SPLIT_NO_EMPTY);
        $added = 0;
        $skipped = 0;
        foreach ($ip_list as $ip) {
            $ip = trim($ip);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) { $skipped++; continue; }
            if (rn_block_ip($ip, $reason)) $added++;
            else $skipped++;
        }
        rn_success_response(['added' => $added, 'skipped' => $skipped], "成功拉黑 {$added} 个IP，跳过 {$skipped} 个");
        break;

    case 'submit_unblock_request':
        $ip = rn_get_client_ip();
        if (!rn_is_ip_blocked($ip)) rn_error_response('您的IP未被拉黑');
        if (rn_add_unblock_request($ip)) rn_success_response(null, '解除拉黑申请已提交，请等待管理员审核');
        else rn_error_response('您已提交过申请，请耐心等待审核');
        break;

    case 'admin_unblock_requests':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $requests = rn_get_unblock_requests();
        usort($requests, function($a, $b) { return strtotime($b['time'] ?? '0') - strtotime($a['time'] ?? '0'); });
        rn_success_response(['requests' => $requests, 'total' => count($requests)]);
        break;

    case 'admin_approve_unblock':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $id = intval($_POST['id'] ?? 0);
        if (rn_approve_unblock_request($id)) rn_success_response(null, '已批准解除拉黑');
        else rn_error_response('申请不存在或已处理');
        break;

    case 'admin_reject_unblock':
        if (!rn_is_admin_logged_in()) rn_error_response('未登录', 401);
        $id = intval($_POST['id'] ?? 0);
        if (rn_reject_unblock_request($id)) rn_success_response(null, '已拒绝该申请');
        else rn_error_response('申请不存在或已处理');
        break;

    case 'get_visitor_trend':
        global $rn_mysql;
        if ($rn_mysql) {
            $labels = []; $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $labels[] = date('m/d', strtotime($d));
                $count = (int)$rn_mysql->query("SELECT COUNT(DISTINCT ip) FROM visitors WHERE visit_date = '{$d}'")->fetchColumn();
                $data[] = $count;
            }
            rn_success_response(['labels' => $labels, 'data' => $data]);
            break;
        }
        $visitors = rn_get_visitors();
        $labels = []; $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('m/d', strtotime($d));
            $count = 0; $ips = [];
            foreach ($visitors as $v) { if (($v['date'] ?? '') === $d && !in_array($v['ip'], $ips)) { $ips[] = $v['ip']; $count++; } }
            $data[] = $count;
        }
        rn_success_response(['labels' => $labels, 'data' => $data]);
        break;

    default:
        rn_error_response('未知操作');
}
