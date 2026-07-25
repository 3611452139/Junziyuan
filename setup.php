<?php
date_default_timezone_set('Asia/Shanghai');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['action'] === 'install') {
        $host    = $_POST['host'] ?? '127.0.0.1';
        $port    = $_POST['port'] ?? '3306';
        $dbname  = $_POST['dbname'] ?? 'site_data';
        $user    = $_POST['user'] ?? 'site_user';
        $pass    = $_POST['pass'] ?? '';
        $charset = 'utf8mb4';

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'MySQL 连接失败: ' . $e->getMessage()]);
            exit;
        }

        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => '创建数据库失败: ' . $e->getMessage()]);
            exit;
        }

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => '连接数据库失败: ' . $e->getMessage()]);
            exit;
        }

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
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => '创建数据表失败: ' . $e->getMessage()]);
            exit;
        }

        $importCount = 0;
        $sqlFile = __DIR__ . '/data/site_data.sql';
        if (file_exists($sqlFile)) {
            try {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
                $vCount = (int)$pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
                $mCount = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
                $importCount = "{$vCount} 条访客 + {$mCount} 条留言";
            } catch (PDOException $e) {
                $importCount = '导入失败: ' . $e->getMessage();
            }
        } else {
            $importCount = '无历史数据';
        }

        $cfgContent = "<?php\nreturn [\n    'host'    => '" . addcslashes($host, "'") . "',\n    'port'    => '" . addcslashes($port, "'") . "',\n    'dbname'  => '" . addcslashes($dbname, "'") . "',\n    'user'    => '" . addcslashes($user, "'") . "',\n    'pass'    => '" . addcslashes($pass, "'") . "',\n    'charset' => 'utf8mb4'\n];\n";
        if (file_put_contents(__DIR__ . '/db_config.php', $cfgContent) === false) {
            echo json_encode(['ok' => false, 'msg' => '无法写入 db_config.php，请检查目录权限']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'msg' => '安装成功！历史数据: ' . $importCount
        ]);
        exit;
    }
    exit;
}

$isConfigured = false;
if (file_exists(__DIR__ . '/db_config.php')) {
    $cfg = require __DIR__ . '/db_config.php';
    $host    = $cfg['host'] ?? '127.0.0.1';
    $port    = $cfg['port'] ?? '3306';
    $dbname  = $cfg['dbname'] ?? 'site_data';
    $user    = $cfg['user'] ?? 'site_user';
    $pass    = $cfg['pass'] ?? '';
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->query("SELECT 1 FROM visitors LIMIT 1");
        $isConfigured = true;
    } catch (Throwable $e) {}
}

$defaultHost = $cfg['host'] ?? '127.0.0.1';
$defaultPort = $cfg['port'] ?? '3306';
$defaultDb   = $cfg['dbname'] ?? 'site_data';
$defaultUser = $cfg['user'] ?? 'site_user';
$defaultPass = $cfg['pass'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>站点安装向导</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);color:#e0e0e0;padding:20px}
.card{background:rgba(22,33,62,0.85);border-radius:20px;padding:40px 35px;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.5);border:1px solid rgba(102,126,234,0.15);backdrop-filter:blur(10px)}
.logo{text-align:center;margin-bottom:30px}
.logo h1{font-size:24px;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:6px}
.logo p{color:#718096;font-size:13px}
.field{margin-bottom:16px}
.field label{display:block;font-size:13px;color:#a0aec0;margin-bottom:6px;font-weight:500}
.field input{width:100%;padding:12px 14px;background:rgba(255,255,255,0.05);border:1px solid rgba(102,126,234,0.2);border-radius:10px;color:#e0e0e0;font-size:14px;outline:none;transition:border .3s}
.field input:focus{border-color:#667eea}
.field-row{display:flex;gap:12px}
.field-row .field{flex:1}
.btn{display:block;width:100%;padding:14px;border-radius:12px;border:none;font-size:15px;font-weight:600;cursor:pointer;transition:all .3s;margin-top:8px}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:0 4px 15px rgba(102,126,234,0.4)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(102,126,234,0.5)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none}
.btn-goto{background:rgba(72,187,120,0.15);color:#48bb78;border:1px solid rgba(72,187,120,0.3);margin-top:20px}
.btn-goto:hover{background:rgba(72,187,120,0.25)}
.progress{display:none;margin-top:16px}
.progress-bar{height:4px;background:rgba(255,255,255,0.1);border-radius:2px;overflow:hidden}
.progress-bar-inner{height:100%;width:0;background:linear-gradient(90deg,#667eea,#764ba2);border-radius:2px;transition:width .5s}
.progress-msg{font-size:12px;color:#a0aec0;margin-top:8px;text-align:center}
.result{display:none;margin-top:16px;text-align:center}
.result.success{color:#48bb78}
.result.error{color:#e53e3e}
.installed-box{text-align:center}
.installed-box .icon{font-size:48px;color:#48bb78;margin-bottom:16px}
.installed-box p{color:#a0aec0;font-size:14px;margin-bottom:20px;line-height:1.8}
.tip{background:rgba(102,126,234,0.08);border-radius:10px;padding:12px 16px;margin-top:20px;font-size:12px;color:#718096;line-height:1.6}
</style>
</head>
<body>
<div class="card">
<?php if ($isConfigured): ?>
<div class="installed-box">
    <div class="icon">&#10003;</div>
    <h1 style="background:linear-gradient(135deg,#48bb78,#38a169);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:22px;margin-bottom:10px">数据库已配置</h1>
    <p>当前连接: <?php echo htmlspecialchars($defaultUser . '@' . $defaultHost . '/' . $defaultDb); ?></p>
    <button class="btn btn-goto" onclick="location.href='index.php'">进入网站</button>
    <div class="tip">如需重新安装，请删除 db_config.php 文件后刷新本页面。</div>
</div>
<?php else: ?>
<div class="logo">
    <h1>站点安装向导</h1>
    <p>首次使用？配置 MySQL 数据库即可运行</p>
</div>
<form id="setupForm">
    <div class="field-row">
        <div class="field">
            <label>主机地址</label>
            <input type="text" name="host" value="<?php echo htmlspecialchars($defaultHost); ?>" required>
        </div>
        <div class="field" style="max-width:120px">
            <label>端口</label>
            <input type="text" name="port" value="<?php echo htmlspecialchars($defaultPort); ?>" required>
        </div>
    </div>
    <div class="field">
        <label>数据库名</label>
        <input type="text" name="dbname" value="<?php echo htmlspecialchars($defaultDb); ?>" required>
    </div>
    <div class="field">
        <label>用户名</label>
        <input type="text" name="user" value="<?php echo htmlspecialchars($defaultUser); ?>" required>
    </div>
    <div class="field">
        <label>密码</label>
        <input type="password" name="pass" value="<?php echo htmlspecialchars($defaultPass); ?>">
    </div>
    <div class="tip">
        确保 MySQL 已运行，且用户有 CREATE DATABASE 和 CREATE TABLE 权限。
        如果是远程数据库，请将主机地址改为服务器 IP。
    </div>
    <button type="submit" class="btn btn-primary" id="submitBtn">一键安装</button>
</form>
<div class="progress" id="progress">
    <div class="progress-bar"><div class="progress-bar-inner" id="progressBar"></div></div>
    <div class="progress-msg" id="progressMsg">正在连接数据库...</div>
</div>
<div class="result" id="result"></div>
<button class="btn btn-goto" id="gotoBtn" style="display:none" onclick="location.href='index.php'">进入网站</button>
<?php endif; ?>
</div>
<script>
document.getElementById('setupForm').addEventListener('submit',function(e){
    e.preventDefault();
    var btn=document.getElementById('submitBtn');
    var bar=document.getElementById('progressBar');
    var msg=document.getElementById('progressMsg');
    var result=document.getElementById('result');
    var progress=document.getElementById('progress');
    var gotoBtn=document.getElementById('gotoBtn');

    btn.disabled=true;
    btn.textContent='安装中...';
    progress.style.display='block';
    result.style.display='none';
    gotoBtn.style.display='none';
    bar.style.width='0%';
    msg.textContent='正在连接数据库...';

    var fd=new FormData(this);
    fd.append('action','install');

    fetch('setup.php',{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
        bar.style.width='100%';
        if(d.ok){
            msg.textContent=d.msg;
            result.style.display='block';
            result.className='result success';
            result.textContent='安装完成，即将跳转...';
            btn.style.display='none';
            setTimeout(function(){location.href='index.php';},2000);
        }else{
            msg.textContent='安装失败';
            result.style.display='block';
            result.className='result error';
            result.textContent=d.msg;
            btn.disabled=false;
            btn.textContent='重试安装';
        }
    })
    .catch(function(err){
        bar.style.width='100%';
        msg.textContent='安装失败';
        result.style.display='block';
        result.className='result error';
        result.textContent='请求失败: '+err.message;
        btn.disabled=false;
        btn.textContent='重试安装';
    });
});
</script>
</body>
</html>
