<?php
/**
 * 数据库安装脚本
 * 
 * 作用：创建 visitors / messages 表，并把 data/visitors.json 和 data/messages.json 里的历史数据导入 MySQL。
 * 
 * 使用方法：
 *   1. 编辑同目录下的 db_config.php，填入你的数据库账号密码
 *   2. 命令行执行：php db_install.php
 *   3. 看到 "安装完成" 即可删除本文件
 */

$cfgFile = __DIR__ . '/db_config.php';
if (!file_exists($cfgFile)) {
    die("[错误] 找不到 db_config.php，请先创建该文件并填写数据库信息。\n");
}
$cfg = require $cfgFile;
$host    = $cfg['host']    ?? '127.0.0.1';
$dbname  = $cfg['dbname']  ?? 'site_data';
$user    = $cfg['user']    ?? 'site_user';
$pass    = $cfg['pass']    ?? '';
$charset = $cfg['charset'] ?? 'utf8mb4';

echo "=== 数据库安装向导 ===\n";
echo "目标: {$host} / {$dbname} / 用户: {$user}\n\n";

// ---------- Step 1: 连接（不指定库名，先建库） ----------
try {
    $pdo = new PDO("mysql:host={$host};charset={$charset}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[1/5] 连接 MySQL 成功\n";
} catch (PDOException $e) {
    die("[错误] 连接失败: " . $e->getMessage() . "\n");
}

// ---------- Step 2: 建库 ----------
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
    echo "[2/5] 数据库 `{$dbname}` 就绪\n";
} catch (PDOException $e) {
    die("[错误] 建库失败: " . $e->getMessage() . "\n");
}

// 重新连接，指定库名
try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset={$charset}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("[错误] 连接数据库失败: " . $e->getMessage() . "\n");
}

// ---------- Step 3: 建表 ----------
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitors (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            country VARCHAR(100) DEFAULT '',
            region VARCHAR(100) DEFAULT '',
            city VARCHAR(100) DEFAULT '',
            district VARCHAR(100) DEFAULT '',
            zip VARCHAR(20) DEFAULT '',
            lat VARCHAR(20) DEFAULT '',
            lon VARCHAR(20) DEFAULT '',
            timezone VARCHAR(50) DEFAULT '',
            isp VARCHAR(200) DEFAULT '',
            os VARCHAR(50) DEFAULT '',
            browser VARCHAR(50) DEFAULT '',
            device VARCHAR(20) DEFAULT '',
            referer VARCHAR(500) DEFAULT '',
            visit_time DATETIME NOT NULL,
            visit_date DATE NOT NULL,
            INDEX idx_ip (ip),
            INDEX idx_visit_time (visit_time),
            INDEX idx_visit_date (visit_date),
            INDEX idx_ip_time (ip, visit_time)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$charset}_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL DEFAULT '匿名',
            content TEXT NOT NULL,
            reply_to INT DEFAULT NULL,
            likes INT DEFAULT 0,
            liked_ips TEXT DEFAULT NULL,
            ip VARCHAR(45) NOT NULL,
            ip_location VARCHAR(500) DEFAULT '',
            email VARCHAR(200) DEFAULT '',
            is_reply TINYINT(1) DEFAULT 0,
            msg_time DATETIME NOT NULL,
            INDEX idx_reply_to (reply_to),
            INDEX idx_msg_time (msg_time),
            INDEX idx_ip (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$charset}_unicode_ci
    ");
    echo "[3/5] 数据表 visitors / messages 创建完成\n";
} catch (PDOException $e) {
    die("[错误] 建表失败: " . $e->getMessage() . "\n");
}

// ---------- Step 4: 导入数据 ----------
$dataDir = __DIR__ . '/data/';
$sqlFile = $dataDir . 'site_data.sql';

// 优先使用 SQL 导出文件（推荐方式）
if (file_exists($sqlFile)) {
    echo "[4/5] 发现 site_data.sql，正在导入...\n";
    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    echo "[4/5] SQL 导入完成\n";
}

// 4a. 访客数据
$visitorsFile = $dataDir . 'visitors.json';
$vCount = 0;
if (file_exists($visitorsFile)) {
    $content = file_get_contents($visitorsFile);
    $pos = strpos($content, '[');
    if ($pos !== false) {
        $data = json_decode(substr($content, $pos), true);
        if (is_array($data)) {
            $existing = (int)$pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
            if ($existing > 0) {
                echo "[4/5] visitors 表已有 {$existing} 条数据，跳过导入\n";
            } else {
                $stmt = $pdo->prepare("INSERT INTO visitors (ip, country, region, city, district, zip, lat, lon, timezone, isp, os, browser, device, referer, visit_time, visit_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $pdo->beginTransaction();
                foreach ($data as $v) {
                    $time = $v['time'] ?? '2000-01-01 00:00:00';
                    $date = $v['date'] ?? substr($time, 0, 10);
                    $stmt->execute([
                        $v['ip'] ?? '', $v['country'] ?? '', $v['region'] ?? '', $v['city'] ?? '', $v['district'] ?? '',
                        $v['zip'] ?? '', $v['lat'] ?? '', $v['lon'] ?? '', $v['timezone'] ?? '',
                        $v['isp'] ?? '', $v['os'] ?? '', $v['browser'] ?? '', $v['device'] ?? '',
                        $v['referer'] ?? '', $time, $date
                    ]);
                    $vCount++;
                    if ($vCount % 500 === 0) { $pdo->commit(); $pdo->beginTransaction(); echo "  已导入 {$vCount} 条访客...\n"; }
                }
                $pdo->commit();
                echo "[4/5] visitors 导入完成，共 {$vCount} 条\n";
            }
        }
    }
} else {
    echo "[4/5] 未找到 visitors.json，跳过\n";
}

// 4b. 留言数据
$messagesFile = $dataDir . 'messages.json';
$mCount = 0;
if (file_exists($messagesFile)) {
    $content = file_get_contents($messagesFile);
    $pos = strpos($content, '[');
    if ($pos !== false) {
        $data = json_decode(substr($content, $pos), true);
        if (is_array($data)) {
            $existing = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
            if ($existing > 0) {
                echo "[4/5] messages 表已有 {$existing} 条数据，跳过导入\n";
            } else {
                $stmt = $pdo->prepare("INSERT INTO messages (id, name, content, reply_to, likes, liked_ips, ip, ip_location, email, is_reply, msg_time) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $pdo->beginTransaction();
                foreach ($data as $m) {
                    $time = $m['time'] ?? '2000-01-01 00:00:00';
                    $isReply = ($m['name'] ?? '') === '管理员' ? 1 : 0;
                    $likedIps = json_encode($m['liked_ips'] ?? [], JSON_UNESCAPED_UNICODE);
                    $stmt->execute([
                        $m['id'] ?? 0, $m['name'] ?? '匿名', $m['content'] ?? '',
                        $m['reply_to'] ?? null, $m['likes'] ?? 0, $likedIps,
                        $m['ip'] ?? '', $m['ip_location'] ?? '', $m['email'] ?? '', $isReply, $time
                    ]);
                    $mCount++;
                }
                $pdo->commit();
                echo "[4/5] messages 导入完成，共 {$mCount} 条\n";
            }
        }
    }
} else {
    echo "[4/5] 未找到 messages.json，跳过\n";
}

// ---------- Step 5: 收尾 ----------
echo "\n[5/5] 安装完成！\n\n";
echo "最终统计：\n";
echo "  visitors:  " . $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn() . " 条\n";
echo "  messages:  " . $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn() . " 条\n";
echo "\n建议：删除本文件 db_install.php 以保安全。\n";
