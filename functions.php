<?php
date_default_timezone_set('Asia/Shanghai');
define('DATA_DIR', __DIR__ . '/data/');
define('CACHE_DIR', DATA_DIR . 'cache/');
define('CONFIG_FILE', DATA_DIR . 'config.json');
define('RESOURCES_FILE', DATA_DIR . 'resources.json');
define('VISITORS_FILE', DATA_DIR . 'visitors.json');
define('MESSAGES_FILE', DATA_DIR . 'messages.json');
define('LOGS_FILE', DATA_DIR . 'logs.json');
define('LOGIN_LOGS_FILE', DATA_DIR . 'login_logs.json');
define('URGES_FILE', DATA_DIR . 'urges.json');
define('DB_FILE', DATA_DIR . 'app_data.db');

define('LOGIN_FAILS_FILE', DATA_DIR . 'login_fails.json');
define('RATE_LIMIT_FILE', DATA_DIR . 'rate_limits.json');

require_once __DIR__ . '/db_store.php';

global $rn_db;
$rn_db = null;
if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers())) {
    try { $rn_db = new DBStore(DB_FILE); } catch (Throwable $e) {}
}

global $rn_mysql;
$rn_mysql = null;
if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
    try {
        $db_cfg = [];
        $db_cfg_file = __DIR__ . '/db_config.php';
        if (file_exists($db_cfg_file)) {
            $db_cfg = require $db_cfg_file;
        }
        $host    = $db_cfg['host']    ?? '127.0.0.1';
        $port    = $db_cfg['port']    ?? '3306';
        $dbname  = $db_cfg['dbname']  ?? 'site_data';
        $user    = $db_cfg['user']    ?? 'site_user';
        $pass    = $db_cfg['pass']    ?? 'change_me';
        $charset = $db_cfg['charset'] ?? 'utf8mb4';
        $rn_mysql = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (Throwable $e) {}
}

function rn_is_db_configured() {
    global $rn_mysql;
    if ($rn_mysql === null) return false;
    try {
        $rn_mysql->query("SELECT 1 FROM visitors LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

define('BLACKLIST_FILE', DATA_DIR . 'blacklist.json');
define('UNBLOCK_REQUESTS_FILE', DATA_DIR . 'unblock_requests.json');

function rn_get_blacklist() { return rn_read_json(BLACKLIST_FILE); }
function rn_save_blacklist($data) { return rn_write_json(BLACKLIST_FILE, $data); }

function rn_is_ip_blocked($ip) {
    $blacklist = rn_get_blacklist();
    foreach ($blacklist as $entry) {
        if ($entry['ip'] === $ip) return true;
    }
    return false;
}

function rn_block_ip($ip, $reason = '', $blocked_by = 'admin') {
    $blacklist = rn_get_blacklist();
    foreach ($blacklist as $entry) {
        if ($entry['ip'] === $ip) {
            return false;
        }
    }
    $blacklist[] = [
        'ip' => $ip,
        'reason' => $reason ?: '手动拉黑',
        'blocked_by' => $blocked_by,
        'blocked_time' => date('Y-m-d H:i:s'),
        'ip_location' => rn_get_ip_location_display($ip)
    ];
    rn_save_blacklist($blacklist);
    rn_add_log('拉黑IP', $ip . ($reason ? ' - ' . $reason : ''));
    return true;
}

function rn_unblock_ip($ip) {
    $blacklist = rn_get_blacklist();
    $found = false;
    $blacklist = array_values(array_filter($blacklist, function($entry) use ($ip, &$found) {
        if ($entry['ip'] === $ip) { $found = true; return false; }
        return true;
    }));
    if ($found) {
        rn_save_blacklist($blacklist);
        rn_add_log('解除拉黑IP', $ip);
    }
    return $found;
}

function rn_get_unblock_requests() { return rn_read_json(UNBLOCK_REQUESTS_FILE); }
function rn_save_unblock_requests($data) { return rn_write_json(UNBLOCK_REQUESTS_FILE, $data); }

function rn_add_unblock_request($ip) {
    $requests = rn_get_unblock_requests();
    foreach ($requests as $r) {
        if ($r['ip'] === $ip && ($r['status'] ?? 'pending') === 'pending') return false;
    }
    $requests[] = [
        'id' => count($requests) + 1,
        'ip' => $ip,
        'ip_location' => rn_get_ip_location_display($ip),
        'status' => 'pending',
        'time' => date('Y-m-d H:i:s')
    ];
    rn_save_unblock_requests($requests);
    return true;
}

function rn_approve_unblock_request($id) {
    $requests = rn_get_unblock_requests();
    $found = false;
    foreach ($requests as &$r) {
        if ($r['id'] === $id && ($r['status'] ?? 'pending') === 'pending') {
            $r['status'] = 'approved';
            rn_unblock_ip($r['ip']);
            $found = true;
            break;
        }
    }
    if ($found) { rn_save_unblock_requests($requests); rn_add_log('批准解除拉黑', 'ID: ' . $id); }
    return $found;
}

function rn_reject_unblock_request($id) {
    $requests = rn_get_unblock_requests();
    $found = false;
    foreach ($requests as &$r) {
        if ($r['id'] === $id && ($r['status'] ?? 'pending') === 'pending') {
            $r['status'] = 'rejected';
            $found = true;
            break;
        }
    }
    if ($found) { rn_save_unblock_requests($requests); rn_add_log('拒绝解除拉黑', 'ID: ' . $id); }
    return $found;
}

function rn_read_json($file) {
    global $rn_db;
    if ($rn_db) {
        try {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $data = $rn_db->get($key, null);
            if ($data !== null) return $data;
        } catch (Throwable $e) {}
    }
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    $pos1 = strpos($content, '[');
    $pos2 = strpos($content, '{');
    if ($pos1 === false && $pos2 === false) return [];
    if ($pos1 === false) $pos = $pos2;
    elseif ($pos2 === false) $pos = $pos1;
    else $pos = min($pos1, $pos2);
    $json = substr($content, $pos);
    $data = json_decode($json, true);
    if ($data !== null) return $data;
    return [];
}

function rn_write_json($file, $data) {
    global $rn_db;
    if ($rn_db) {
        try {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $rn_db->set($key, $data);
        } catch (Throwable $e) {}
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $content = "<?php exit;?>\n" . $json;
    return @file_put_contents($file, $content) !== false;
}

function rn_get_config() { return rn_read_json(CONFIG_FILE); }
function rn_save_config($config) { return rn_write_json(CONFIG_FILE, $config); }
function rn_get_resources() { return rn_read_json(RESOURCES_FILE); }
function rn_save_resources($resources) { return rn_write_json(RESOURCES_FILE, $resources); }
function rn_get_visitors() {
    global $rn_mysql;
    if ($rn_mysql) {
        try {
            return $rn_mysql->query("SELECT * FROM visitors ORDER BY visit_time DESC")->fetchAll();
        } catch (Throwable $e) {}
    }
    return rn_read_json(VISITORS_FILE);
}
function rn_save_visitors($visitors) { return true; }
function rn_get_messages() {
    global $rn_mysql;
    if ($rn_mysql) {
        try {
            $rows = $rn_mysql->query("SELECT * FROM messages ORDER BY msg_time DESC")->fetchAll();
            $result = [];
            foreach ($rows as $row) {
                $result[] = [
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
            return $result;
        } catch (Throwable $e) {}
    }
    return rn_read_json(MESSAGES_FILE);
}
function rn_save_messages($messages) { return true; }
function rn_get_logs() { return rn_read_json(LOGS_FILE); }
function rn_save_logs($logs) { return rn_write_json(LOGS_FILE, $logs); }
function rn_get_login_logs() { return rn_read_json(LOGIN_LOGS_FILE); }
function rn_save_login_logs($logs) { return rn_write_json(LOGIN_LOGS_FILE, $logs); }
function rn_get_urges() { return rn_read_json(URGES_FILE); }
function rn_save_urges($urges) { return rn_write_json(URGES_FILE, $urges); }

function rn_add_log($action, $detail = '') {
    $logs = rn_get_logs();
    $ip = rn_get_client_ip();
    $logs[] = [
        'id' => count($logs) + 1,
        'action' => $action,
        'detail' => $detail,
        'ip' => $ip,
        'ip_location' => rn_get_ip_location_display($ip),
        'browser' => rn_get_browser_info(),
        'os' => rn_get_os_info(),
        'time' => date('Y-m-d H:i:s')
    ];
    if (count($logs) > 1000) $logs = array_slice($logs, -1000);
    @rn_save_logs($logs);
}

function rn_add_login_log($status, $password = '') {
    $logs = rn_get_login_logs();
    $ip = rn_get_client_ip();
    $geo = rn_get_ip_location($ip);
    $fails = rn_read_json(LOGIN_FAILS_FILE);
    $now = time();
    $fail_count = 0;
    foreach ($fails as $f) {
        if ($f['ip'] === $ip && ($now - $f['time']) < 3600) $fail_count++;
    }
    $logs[] = [
        'id' => count($logs) + 1,
        'status' => $status,
        'ip' => $ip,
        'country' => $geo['country'] ?? '',
        'region' => $geo['regionName'] ?? '',
        'city' => $geo['city'] ?? '',
        'district' => $geo['district'] ?? '',
        'zip' => $geo['zip'] ?? '',
        'isp' => $geo['isp'] ?? '',
        'browser' => rn_get_browser_info(),
        'os' => rn_get_os_info(),
        'device' => rn_get_device_info(),
        'fail_count' => $fail_count,
        'time' => date('Y-m-d H:i:s')
    ];
    if (count($logs) > 500) $logs = array_slice($logs, -500);
    @rn_save_login_logs($logs);
}

function rn_get_client_ip() {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) { $ips = explode(',', $ip); $ip = trim($ips[0]); }
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function rn_get_browser_info() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strpos($ua, 'MicroMessenger') !== false) return '微信内置';
    if (strpos($ua, 'QQ/') !== false) return 'QQ内置';
    if (strpos($ua, 'MQQBrowser') !== false) return 'QQ浏览器';
    if (strpos($ua, 'UCBrowser') !== false || strpos($ua, 'UCWEB') !== false) return 'UC浏览器';
    if (strpos($ua, 'Baiduspider') !== false || strpos($ua, 'Baiduboxapp') !== false) return '百度APP';
    if (strpos($ua, 'Quark') !== false) return '夸克';
    if (strpos($ua, '2345Explorer') !== false) return '2345浏览器';
    if (strpos($ua, 'Sogou') !== false) return '搜狗浏览器';
    if (strpos($ua, '360') !== false) return '360浏览器';
    if (strpos($ua, 'Edg') !== false) return 'Edge';
    if (strpos($ua, 'Firefox') !== false && strpos($ua, 'Seamonkey') === false) return 'Firefox';
    if (strpos($ua, 'Chrome') !== false) return 'Chrome';
    if (strpos($ua, 'Safari') !== false) return 'Safari';
    if (strpos($ua, 'OPR') !== false || strpos($ua, 'Opera') !== false) return 'Opera';
    return '其他';
}

function rn_get_os_info() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strpos($ua, 'Windows NT 10') !== false) return 'Windows 10/11';
    if (strpos($ua, 'Windows NT 6.3') !== false) return 'Windows 8.1';
    if (strpos($ua, 'Windows NT 6.2') !== false) return 'Windows 8';
    if (strpos($ua, 'Windows NT 6.1') !== false) return 'Windows 7';
    if (strpos($ua, 'Mac OS X') !== false) return 'macOS';
    if (strpos($ua, 'Linux') !== false) return 'Linux';
    if (strpos($ua, 'Android') !== false) return 'Android';
    if (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) return 'iOS';
    return 'Unknown';
}

function rn_get_device_info() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strpos($ua, 'Mobile') !== false) {
        if (strpos($ua, 'iPhone') !== false) return 'iPhone';
        if (strpos($ua, 'iPad') !== false) return 'iPad';
        return 'Mobile';
    }
    return 'Desktop';
}

function rn_get_referer() { 
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($ref)) return '直接访问';
    $host = parse_url($ref, PHP_URL_HOST);
    if (!$host) return '来源未知';
    if (strpos($host, 'weixin') !== false || strpos($host, 'wechat') !== false) return '微信';
    if (strpos($host, 'tieba.baidu.com') !== false) return '百度贴吧';
    if (strpos($host, 'baidu.com') !== false) return '百度搜索';
    if (strpos($host, 'google.') !== false) return 'Google搜索';
    if (strpos($host, 'bing.com') !== false) return 'Bing搜索';
    if (strpos($host, 'sogou.com') !== false) return '搜狗搜索';
    if (strpos($host, '360.cn') !== false || strpos($host, 'so.com') !== false) return '360搜索';
    if (strpos($host, 'qq.com') !== false || strpos($host, 'qzone') !== false) return 'QQ/空间';
    if (strpos($host, 'douyin.com') !== false) return '抖音';
    if (strpos($host, 'bilibili.com') !== false) return 'B站';
    if (strpos($host, 'zhihu.com') !== false) return '知乎';
    if (strpos($host, 'weibo') !== false) return '微博';
    if (strpos($host, 'jd.com') !== false) return '京东';
    if (strpos($host, 'taobao.com') !== false) return '淘宝';
    if (strpos($host, 'tmall.com') !== false) return '天猫';
    if (strpos($host, 'douban.com') !== false) return '豆瓣';
    if (strpos($host, 'csdn.net') !== false) return 'CSDN';
    if (strpos($host, 'jianshu.com') !== false) return '简书';
    if (strpos($host, 'juejin') !== false) return '掘金';
    if (strpos($host, 'github') !== false) return 'GitHub';
    if (strpos($host, 'gitee.com') !== false) return 'Gitee';
    return $host;
}

function rn_get_ip_location($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0' ||
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['country' => '', 'regionName' => '', 'city' => '', 'district' => '', 'zip' => '', 'isp' => ''];
    }
    $cache_key = md5('geo_' . $ip);
    $cache_file = CACHE_DIR . 'cache_' . $cache_key . '.json';
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 86400) {
        $cached = rn_read_json($cache_file);
        if (!empty($cached)) return $cached;
    }
    $result = [];
    // 主力: ip-api.com (lang=zh-CN, 返回城市级中文数据)
    try {
        $url = "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,message,country,regionName,city,district,zip,lat,lon,timezone,isp,org,as,query";
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && ($data['status'] ?? '') === 'success') {
                $result = [
                    'country' => $data['country'] ?? '',
                    'regionName' => $data['regionName'] ?? '',
                    'city' => $data['city'] ?? '',
                    'district' => $data['district'] ?? '',
                    'zip' => $data['zip'] ?? '',
                    'lat' => $data['lat'] ?? '',
                    'lon' => $data['lon'] ?? '',
                    'timezone' => $data['timezone'] ?? '',
                    'isp' => $data['isp'] ?? '',
                    'source' => 'ip-api'
                ];
            }
        }
    } catch (Exception $e) {}
    // 备用: uapis.cn (区县级精度)
    if (empty($result)) {
        try {
            $url = "https://uapis.cn/api/v1/network/ipinfo?ip={$ip}&source=commercial";
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && !empty($data['region'])) {
                    $region_str = trim($data['region']);
                    $parts = explode(' ', $region_str);
                    $two_word_countries = [
                        'United States', 'United Kingdom', 'South Korea', 'South Africa',
                        'North Korea', 'New Zealand', 'Sri Lanka', 'Costa Rica',
                        'El Salvador', 'Dominican Republic', 'Czech Republic',
                        'Hong Kong', 'Puerto Rico', 'Saudi Arabia', 'United Arab',
                        'San Marino', 'East Timor', 'Papua New', 'Sierra Leone',
                        'Burkina Faso', 'Ivory Coast', 'French Polynesia', 'Western Sahara',
                    ];
                    $is_two_word = false;
                    if (count($parts) >= 2) {
                        $prefix = $parts[0] . ' ' . $parts[1];
                        if (in_array($prefix, $two_word_countries)) {
                            $is_two_word = true;
                        }
                    }
                    if ($is_two_word) {
                        $result['country'] = $parts[0] . ' ' . $parts[1];
                        $result['regionName'] = $parts[2] ?? '';
                        $result['city'] = $parts[3] ?? '';
                    } else {
                        $result['country'] = $parts[0] ?? '';
                        $result['regionName'] = $parts[1] ?? '';
                        $result['city'] = $parts[2] ?? '';
                    }
                    $result['district'] = $data['district'] ?? '';
                    $result['zip'] = $data['zip_code'] ?? '';
                    $result['lat'] = $data['latitude'] ?? '';
                    $result['lon'] = $data['longitude'] ?? '';
                    $result['timezone'] = $data['time_zone'] ?? '';
                    $result['isp'] = $data['isp'] ?? '';
                    $result['source'] = 'uapis';
                }
            }
        } catch (Exception $e) {}
    }
    if (!empty($result)) rn_write_json($cache_file, $result);
    return $result;
}

function rn_get_ip_location_display($ip) {
    $loc = rn_get_ip_location($ip);
    $map = [
        'United States' => '美国', 'United Kingdom' => '英国', 'Germany' => '德国',
        'France' => '法国', 'Japan' => '日本', 'South Korea' => '韩国',
        'Canada' => '加拿大', 'Australia' => '澳大利亚', 'Singapore' => '新加坡',
        'Netherlands' => '荷兰', 'Russia' => '俄罗斯', 'Brazil' => '巴西',
        'India' => '印度', 'Hong Kong' => '中国香港', 'Taiwan' => '中国台湾',
        'Vietnam' => '越南', 'Thailand' => '泰国', 'Malaysia' => '马来西亚',
        'Indonesia' => '印度尼西亚', 'Philippines' => '菲律宾', 'Italy' => '意大利',
        'Spain' => '西班牙', 'Sweden' => '瑞典', 'Switzerland' => '瑞士',
        'Turkey' => '土耳其', 'Ukraine' => '乌克兰', 'Poland' => '波兰',
        'Belgium' => '比利时', 'Austria' => '奥地利', 'Czechia' => '捷克',
        'Norway' => '挪威', 'Denmark' => '丹麦', 'Finland' => '芬兰',
        'Ireland' => '爱尔兰', 'New Zealand' => '新西兰', 'Mexico' => '墨西哥',
        'Argentina' => '阿根廷', 'Chile' => '智利', 'Colombia' => '哥伦比亚',
        'Saudi Arabia' => '沙特阿拉伯', 'UAE' => '阿联酋', 'Israel' => '以色列',
        'South Africa' => '南非', 'Egypt' => '埃及', 'Nigeria' => '尼日利亚',
        'Romania' => '罗马尼亚', 'Hungary' => '匈牙利', 'Portugal' => '葡萄牙',
        'Greece' => '希腊', 'Bulgaria' => '保加利亚', 'Serbia' => '塞尔维亚',
        'Slovakia' => '斯洛伐克', 'Lithuania' => '立陶宛', 'Latvia' => '拉脱维亚',
        'Estonia' => '爱沙尼亚', 'Croatia' => '克罗地亚', 'Slovenia' => '斯洛文尼亚',
        'Luxembourg' => '卢森堡', 'Iceland' => '冰岛', 'Cyprus' => '塞浦路斯',
        'Kazakhstan' => '哈萨克斯坦', 'Pakistan' => '巴基斯坦', 'Bangladesh' => '孟加拉国',
        'Iran' => '伊朗', 'Iraq' => '伊拉克', 'Qatar' => '卡塔尔',
        'Kuwait' => '科威特', 'Oman' => '阿曼', 'Bahrain' => '巴林',
        'Mongolia' => '蒙古', 'Myanmar' => '缅甸', 'Cambodia' => '柬埔寨',
        'Laos' => '老挝', 'North Korea' => '朝鲜', 'Nepal' => '尼泊尔',
        'Sri Lanka' => '斯里兰卡', 'Maldives' => '马尔代夫', 'Morocco' => '摩洛哥',
        'Tunisia' => '突尼斯', 'Algeria' => '阿尔及利亚', 'Kenya' => '肯尼亚',
        'Ethiopia' => '埃塞俄比亚', 'Tanzania' => '坦桑尼亚', 'Uganda' => '乌干达',
        'Ghana' => '加纳', 'Ivory Coast' => '科特迪瓦', 'Angola' => '安哥拉',
        'Mozambique' => '莫桑比克', 'Zimbabwe' => '津巴布韦', 'Zambia' => '赞比亚',
        'Madagascar' => '马达加斯加', 'Mauritius' => '毛里求斯', 'Seychelles' => '塞舌尔',
        'Peru' => '秘鲁', 'Venezuela' => '委内瑞拉', 'Ecuador' => '厄瓜多尔',
        'Bolivia' => '玻利维亚', 'Paraguay' => '巴拉圭', 'Uruguay' => '乌拉圭',
        'Costa Rica' => '哥斯达黎加', 'Panama' => '巴拿马', 'Cuba' => '古巴',
        'Jamaica' => '牙买加', 'Dominican Republic' => '多米尼加', 'Honduras' => '洪都拉斯',
        'Guatemala' => '危地马拉', 'El Salvador' => '萨尔瓦多', 'Nicaragua' => '尼加拉瓜',
        // US States
        'Virginia' => '弗吉尼亚', 'Georgia' => '佐治亚', 'California' => '加利福尼亚',
        'New York' => '纽约', 'Texas' => '得克萨斯', 'Florida' => '佛罗里达',
        'Illinois' => '伊利诺伊', 'Pennsylvania' => '宾夕法尼亚', 'Ohio' => '俄亥俄',
        'Michigan' => '密歇根', 'New Jersey' => '新泽西', 'North Carolina' => '北卡罗来纳',
        'Massachusetts' => '马萨诸塞', 'Washington' => '华盛顿', 'Arizona' => '亚利桑那',
        'Tennessee' => '田纳西', 'Indiana' => '印第安纳', 'Missouri' => '密苏里',
        'Maryland' => '马里兰', 'Wisconsin' => '威斯康星', 'Colorado' => '科罗拉多',
        'Minnesota' => '明尼苏达', 'South Carolina' => '南卡罗来纳', 'Alabama' => '亚拉巴马',
        'Louisiana' => '路易斯安那', 'Kentucky' => '肯塔基', 'Oregon' => '俄勒冈',
        'Oklahoma' => '俄克拉何马', 'Connecticut' => '康涅狄格', 'Utah' => '犹他',
        'Iowa' => '艾奥瓦', 'Nevada' => '内华达', 'Arkansas' => '阿肯色',
        'Mississippi' => '密西西比', 'Kansas' => '堪萨斯', 'New Mexico' => '新墨西哥',
        'Nebraska' => '内布拉斯加', 'Idaho' => '爱达荷', 'West Virginia' => '西弗吉尼亚',
        'Hawaii' => '夏威夷', 'New Hampshire' => '新罕布什尔', 'Maine' => '缅因',
        'Montana' => '蒙大拿', 'Rhode Island' => '罗得岛', 'Delaware' => '特拉华',
        'South Dakota' => '南达科他', 'North Dakota' => '北达科他', 'Alaska' => '阿拉斯加',
        'Vermont' => '佛蒙特', 'Wyoming' => '怀俄明', 'District of Columbia' => '哥伦比亚特区',
        // US Cities
        'Ashburn' => '阿什本', 'Atlanta' => '亚特兰大', 'New York City' => '纽约市',
        'Los Angeles' => '洛杉矶', 'Chicago' => '芝加哥', 'Houston' => '休斯顿',
        'Phoenix' => '菲尼克斯', 'Philadelphia' => '费城', 'San Antonio' => '圣安东尼奥',
        'San Diego' => '圣地亚哥', 'Dallas' => '达拉斯', 'San Jose' => '圣何塞',
        'Austin' => '奥斯汀', 'Jacksonville' => '杰克逊维尔', 'Fort Worth' => '沃思堡',
        'Columbus' => '哥伦布', 'Charlotte' => '夏洛特', 'Indianapolis' => '印第安纳波利斯',
        'San Francisco' => '旧金山', 'Seattle' => '西雅图', 'Denver' => '丹佛',
        'Nashville' => '纳什维尔', 'Oklahoma City' => '俄克拉何马城',
        'El Paso' => '埃尔帕索', 'Boston' => '波士顿', 'Las Vegas' => '拉斯维加斯',
        'Portland' => '波特兰', 'Memphis' => '孟菲斯', 'Louisville' => '路易斯维尔',
        'Baltimore' => '巴尔的摩', 'Milwaukee' => '密尔沃基', 'Albuquerque' => '阿尔伯克基',
        'Tucson' => '图森', 'Fresno' => '弗雷斯诺', 'Sacramento' => '萨克拉门托',
        'Mesa' => '梅萨', 'Kansas City' => '堪萨斯城', 'Omaha' => '奥马哈',
        'Colorado Springs' => '科罗拉多斯普林斯', 'Raleigh' => '罗利',
        'Long Beach' => '长滩', 'Virginia Beach' => '弗吉尼亚比奇', 'Miami' => '迈阿密',
        'Oakland' => '奥克兰', 'Minneapolis' => '明尼阿波利斯', 'Tampa' => '坦帕',
        'Tulsa' => '塔尔萨', 'Arlington' => '阿灵顿', 'New Orleans' => '新奥尔良',
        'Wichita' => '威奇托', 'Cleveland' => '克利夫兰', 'Bakersfield' => '贝克斯菲尔德',
        'Aurora' => '奥罗拉',
    ];
    $parts = [];
    $country = $loc['country'] ?? '';
    if (!empty($country)) {
        $parts[] = $map[$country] ?? $country;
    }
    $region = $loc['regionName'] ?? '';
    if (!empty($region)) {
        $parts[] = $map[$region] ?? $region;
    }
    $city = $loc['city'] ?? '';
    if (!empty($city) && $city !== $region) {
        $parts[] = $map[$city] ?? $city;
    }
    $district = $loc['district'] ?? '';
    if (!empty($district) && $district !== $city) {
        $parts[] = $map[$district] ?? $district;
    }
    $addr = !empty($parts) ? implode(' ', $parts) : '未知';
    return $addr;
}

function rn_record_visitor() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'admin.php') !== false || strpos($uri, 'api.php') !== false) return;
    $ip = rn_get_client_ip();
    $config = rn_get_config();
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $now_ts = time();
    $config['total_views'] = ($config['total_views'] ?? 0) + 1;
    if (!isset($config['unique_ips'])) $config['unique_ips'] = [];
    if (!in_array($ip, $config['unique_ips'])) $config['unique_ips'][] = $ip;
    rn_save_config($config);
    $geo = rn_get_ip_location($ip);

    global $rn_mysql;
    if ($rn_mysql) {
        try {
            $stmt = $rn_mysql->prepare("INSERT INTO visitors (ip, country, region, city, district, zip, lat, lon, timezone, isp, os, browser, device, referer, visit_time, visit_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $ip,
                $geo['country'] ?? '', $geo['regionName'] ?? '', $geo['city'] ?? '', $geo['district'] ?? '',
                $geo['zip'] ?? '', $geo['lat'] ?? '', $geo['lon'] ?? '', $geo['timezone'] ?? '',
                $geo['isp'] ?? '', rn_get_os_info(), rn_get_browser_info(), rn_get_device_info(),
                rn_get_referer(), $now, $today
            ]);

            $auto_block = $config['auto_block_enabled'] ?? true;
            if ($auto_block && !rn_is_ip_blocked($ip)) {
                $threshold = intval($config['auto_block_threshold'] ?? 30);
                $window = intval($config['auto_block_window'] ?? 60);
                $whitelist = $config['auto_block_whitelist'] ?? '山东,威海';
                $ip_whitelist = $config['auto_block_ip_whitelist'] ?? '';

                $wl_ips = array_map('trim', explode(',', $ip_whitelist));
                $is_ip_whitelisted = in_array($ip, $wl_ips, true);
                if ($is_ip_whitelisted) return;

                $stmt2 = $rn_mysql->prepare("SELECT COUNT(*) FROM visitors WHERE ip = ? AND visit_time >= ?");
                $since = date('Y-m-d H:i:s', $now_ts - $window);
                $stmt2->execute([$ip, $since]);
                $count = (int)$stmt2->fetchColumn();

                if ($count >= $threshold) {
                    $country = $geo['country'] ?? '';
                    $region = $geo['regionName'] ?? '';
                    $city = $geo['city'] ?? '';
                    $has_geo = !empty($country) || !empty($region) || !empty($city);

                    $is_region_whitelisted = false;
                    if ($has_geo) {
                        $wl_regions = array_map('trim', explode(',', $whitelist));
                        foreach ($wl_regions as $wl) {
                            if ($wl === '') continue;
                            if (mb_stripos($country, $wl) !== false ||
                                mb_stripos($region, $wl) !== false ||
                                mb_stripos($city, $wl) !== false ||
                                mb_stripos($geo['isp'] ?? '', $wl) !== false) {
                                $is_region_whitelisted = true;
                                break;
                            }
                        }
                    }

                    if (!$is_region_whitelisted && $has_geo) {
                        $location = ($country === '中国' || $country === 'China') ? ($region ?: $city) : $country;
                        $location .= $city ? ' ' . $city : '';
                        rn_block_ip($ip, "自动拉黑: {$window}秒内访问{$count}次 [{$location}]", 'system');
                    }
                }
            }
            return;
        } catch (Throwable $e) {}
    }

    // Fallback to JSON storage if MySQL is unavailable
    $visitors = rn_read_json(VISITORS_FILE);
    $visitors[] = [
        'id' => count($visitors) + 1,
        'ip' => $ip,
        'country' => $geo['country'] ?? '',
        'region' => $geo['regionName'] ?? '',
        'city' => $geo['city'] ?? '',
        'district' => $geo['district'] ?? '',
        'zip' => $geo['zip'] ?? '',
        'lat' => $geo['lat'] ?? '',
        'lon' => $geo['lon'] ?? '',
        'timezone' => $geo['timezone'] ?? '',
        'isp' => $geo['isp'] ?? '',
        'os' => rn_get_os_info(),
        'browser' => rn_get_browser_info(),
        'device' => rn_get_device_info(),
        'referer' => rn_get_referer(),
        'time' => $now,
        'date' => $today
    ];
    if (count($visitors) > 2000) $visitors = array_slice($visitors, -2000);
    rn_write_json(VISITORS_FILE, $visitors);

    $auto_block = $config['auto_block_enabled'] ?? true;
    if ($auto_block && !rn_is_ip_blocked($ip)) {
        $threshold = intval($config['auto_block_threshold'] ?? 30);
        $window = intval($config['auto_block_window'] ?? 60);
        $whitelist = $config['auto_block_whitelist'] ?? '山东,威海';
        $ip_whitelist = $config['auto_block_ip_whitelist'] ?? '';

        $wl_ips = array_map('trim', explode(',', $ip_whitelist));
        $is_ip_whitelisted = in_array($ip, $wl_ips, true);

        if ($is_ip_whitelisted) return;

        $count = 0;
        foreach ($visitors as $v) {
            if ($v['ip'] === $ip) {
                $v_ts = strtotime($v['time'] ?? '0');
                if (($now_ts - $v_ts) <= $window) $count++;
            }
        }

        if ($count >= $threshold) {
            $country = $geo['country'] ?? '';
            $region = $geo['regionName'] ?? '';
            $city = $geo['city'] ?? '';

            $has_geo = !empty($country) || !empty($region) || !empty($city);

            $is_region_whitelisted = false;
            if ($has_geo) {
                $wl_regions = array_map('trim', explode(',', $whitelist));
                foreach ($wl_regions as $wl) {
                    if ($wl === '') continue;
                    if (mb_stripos($country, $wl) !== false ||
                        mb_stripos($region, $wl) !== false ||
                        mb_stripos($city, $wl) !== false ||
                        mb_stripos($geo['isp'] ?? '', $wl) !== false) {
                        $is_region_whitelisted = true;
                        break;
                    }
                }
            }

            if (!$is_region_whitelisted && $has_geo) {
                $location = ($country === '中国' || $country === 'China') ? ($region ?: $city) : $country;
                $location .= $city ? ' ' . $city : '';
                rn_block_ip($ip, "自动拉黑: {$window}秒内访问{$count}次 [{$location}]", 'system');
            }
        }
    }
}

function rn_is_admin_logged_in() {
    session_start();
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function rn_check_login_fail_limit($ip) {
    $fails = rn_read_json(LOGIN_FAILS_FILE);
    $now = time();
    $window = 300;
    $max_fails = 5;
    $recent = 0;
    foreach ($fails as $f) {
        if ($f['ip'] === $ip && ($now - $f['time']) < $window) $recent++;
    }
    return $recent >= $max_fails;
}

function rn_record_login_fail($ip) {
    $fails = rn_read_json(LOGIN_FAILS_FILE);
    $fails[] = ['ip' => $ip, 'time' => time()];
    $fails = array_values(array_filter($fails, function($f) { return (time() - $f['time']) < 3600; }));
    rn_write_json(LOGIN_FAILS_FILE, $fails);
}

function rn_clear_login_fails($ip) {
    $fails = rn_read_json(LOGIN_FAILS_FILE);
    $fails = array_values(array_filter($fails, function($f) use ($ip) { return $f['ip'] !== $ip; }));
    rn_write_json(LOGIN_FAILS_FILE, $fails);
}

function rn_get_rate_limit_config() {
    $config = rn_get_config();
    return [
        'enabled' => $config['rate_limit_enabled'] ?? true,
        'msg_per_minute' => intval($config['rate_limit_msg'] ?? 3),
        'urge_per_minute' => intval($config['rate_limit_urge'] ?? 3),
        'like_per_minute' => intval($config['rate_limit_like'] ?? 10)
    ];
}

function rn_check_rate_limit($ip, $type) {
    $cfg = rn_get_rate_limit_config();
    if (!$cfg['enabled']) return ['ok' => true];
    $max = $cfg[$type . '_per_minute'] ?? 3;
    $limits = rn_read_json(RATE_LIMIT_FILE);
    $now = time();
    $window = 60;
    $count = 0;
    foreach ($limits as $l) {
        if ($l['ip'] === $ip && $l['type'] === $type && ($now - $l['time']) < $window) $count++;
    }
    if ($count >= $max) return ['ok' => false, 'wait' => $window - ($now - $limits[0]['time'])];
    return ['ok' => true];
}

function rn_record_rate_limit($ip, $type) {
    $limits = rn_read_json(RATE_LIMIT_FILE);
    $limits[] = ['ip' => $ip, 'type' => $type, 'time' => time()];
    $limits = array_values(array_filter($limits, function($l) { return (time() - $l['time']) < 120; }));
    rn_write_json(RATE_LIMIT_FILE, $limits);
}

function rn_admin_login($password) {
    $ip = rn_get_client_ip();
    if (rn_check_login_fail_limit($ip)) {
        rn_add_log('登录被锁定', 'IP: ' . $ip . ' 失败次数过多被暂时锁定');
        return false;
    }
    $config = rn_get_config();
    if ($password === ($config['admin_password'] ?? 'admin')) {
        session_start();
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        rn_clear_login_fails($ip);
        rn_add_login_log('success');
        rn_add_log('登录后台', '登录成功');
        return true;
    }
    rn_record_login_fail($ip);
    rn_add_login_log('failed', $password);
    rn_add_log('登录失败', '密码错误');
    return false;
}

function rn_admin_logout() {
    session_start();
    if (!empty($_SESSION['admin_logged_in'])) rn_add_log('登出后台', '管理员登出');
    session_destroy();
}

function rn_get_stats() {
    $config = rn_get_config();
    $today = date('Y-m-d');
    $total_visitors = count($config['unique_ips'] ?? []);
    $today_visitors = 0;
    $online = 0;

    global $rn_mysql;
    if ($rn_mysql) {
        try {
            $today_visitors = (int)$rn_mysql->query("SELECT COUNT(DISTINCT ip) FROM visitors WHERE visit_date = '{$today}'")->fetchColumn();
            $online = (int)$rn_mysql->query("SELECT COUNT(DISTINCT ip) FROM visitors WHERE visit_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
        } catch (Throwable $e) {}
    } else {
        $visitors = rn_read_json(VISITORS_FILE);
        $today_set = [];
        $online_ips = [];
        $now = time();
        foreach ($visitors as $v) {
            if (($v['date'] ?? '') === $today) {
                if (!in_array($v['ip'], $today_set)) { $today_set[] = $v['ip']; $today_visitors++; }
            }
            $vtime = strtotime($v['time'] ?? '0');
            if ($now - $vtime < 300) {
                if (!in_array($v['ip'], $online_ips)) $online_ips[] = $v['ip'];
            }
        }
        $online = count($online_ips);
    }

    $views_offset = intval($config['views_offset'] ?? 0);
    $visitors_offset = intval($config['visitors_offset'] ?? 0);
    $today_visitors_offset = intval($config['today_visitors_offset'] ?? 0);
    return [
        'total_visitors' => $total_visitors + $visitors_offset,
        'today_visitors' => $today_visitors + $today_visitors_offset,
        'total_views' => ($config['total_views'] ?? 0) + $views_offset,
        'online' => $online,
        'real_total_views' => $config['total_views'] ?? 0,
        'real_total_visitors' => $total_visitors,
        'real_today_visitors' => $today_visitors
    ];
}

function rn_json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function rn_error_response($msg, $code = 400) { rn_json_response(['success' => false, 'message' => $msg], $code); }
function rn_success_response($data = null, $msg = '操作成功') { rn_json_response(['success' => true, 'message' => $msg, 'data' => $data]); }

function rn_wechat_notify($title, $content = '') {
    $config = rn_get_config();
    $http_opts = ['http' => ['timeout' => 5]];
    
    $sct_key = $config['sct_key'] ?? $config['wechat_key'] ?? '';
    if (!empty($sct_key)) {
        try {
            $ctx = stream_context_create($http_opts);
            @file_get_contents('https://sctapi.ftqq.com/' . $sct_key . '.send?title=' . urlencode($title) . '&desp=' . urlencode($content), false, $ctx);
        } catch (Throwable $e) {}
    }
    
    $wxpusher_token = $config['wxpusher_token'] ?? '';
    $wxpusher_uid = $config['wxpusher_uid'] ?? '';
    if (!empty($wxpusher_token) && !empty($wxpusher_uid)) {
        try {
            $ctx = stream_context_create(['http' => [
                'timeout' => 5, 'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode([
                    'appToken' => $wxpusher_token,
                    'content' => $title . "\n" . $content,
                    'contentType' => 1,
                    'uids' => [$wxpusher_uid]
                ], JSON_UNESCAPED_UNICODE)
            ]]);
            @file_get_contents('https://wxpusher.zjiecode.com/api/send/message', false, $ctx);
        } catch (Throwable $e) {}
    }
    
    $bark_key = $config['bark_key'] ?? '';
    if (!empty($bark_key)) {
        try {
            $ctx = stream_context_create($http_opts);
            @file_get_contents('https://api.day.app/' . $bark_key . '/' . urlencode($title) . '/' . urlencode($content), false, $ctx);
        } catch (Throwable $e) {}
    }
    
    $pushplus_token = $config['pushplus_token'] ?? '';
    if (!empty($pushplus_token) && strpos($pushplus_token, 'SCT') !== 0) {
        try {
            $ctx = stream_context_create(['http' => [
                'timeout' => 5, 'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode(['token' => $pushplus_token, 'title' => $title, 'content' => $content, 'template' => 'txt'], JSON_UNESCAPED_UNICODE)
            ]]);
            @file_get_contents('https://www.pushplus.plus/send', false, $ctx);
        } catch (Throwable $e) {}
    }
}

function rn_send_smtp_mail($to, $subject, $body) {
    $config = rn_get_config();
    $host = $config['smtp_host'] ?? 'smtp.qq.com';
    $port = intval($config['smtp_port'] ?? 465);
    $user = $config['smtp_user'] ?? '';
    $pass = $config['smtp_pass'] ?? '';
    $from_name = $config['site_title'] ?? '资源导航';
    if (empty($user) || empty($pass)) return false;
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $from = $user;
    $errno = 0; $errstr = '';

    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        ]
    ]);

    if ($port == 465) {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) return false;
        stream_set_timeout($socket, 10);
        @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
    } elseif ($port == 587) {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) return false;
        stream_set_timeout($socket, 10);
    } else {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) return false;
        stream_set_timeout($socket, 10);
    }

    $gets = function() use ($socket) {
        $r = '';
        while ($line = @fgets($socket, 512)) {
            $r .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $r;
    };
    $send = function($cmd) use ($socket) { @fwrite($socket, $cmd . "\r\n"); };

    $greeting = $gets();
    if (strpos($greeting, '220') === false) { @fclose($socket); return false; }

    $send("EHLO localhost");
    $gets();

    if ($port == 587) {
        $send("STARTTLS");
        $starttls_resp = $gets();
        if (strpos($starttls_resp, '220') === false) { @fclose($socket); return false; }
        @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        $send("EHLO localhost");
        $gets();
    }

    $send("AUTH LOGIN");
    $gets();
    $send(base64_encode($user));
    $gets();
    $send(base64_encode($pass));
    $auth_resp = $gets();
    if (strpos($auth_resp, '235') === false) { @fclose($socket); return false; }

    $send("MAIL FROM:<{$from}>");
    $gets();
    $send("RCPT TO:<{$to}>");
    $gets();
    $send("DATA");
    $gets();

    $subject_enc = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    @fwrite($socket, "Subject: {$subject_enc}\r\nFrom: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from}>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n.\r\n");
    $data_resp = $gets();
    if (strpos($data_resp, '250') === false) { @fclose($socket); return false; }

    $send("QUIT");
    @fclose($socket);
    return true;
}
