<?php
require_once 'functions.php';
if (!rn_is_db_configured() && file_exists(__DIR__ . '/setup.php')) {
    header('Location: setup.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if (rn_check_login_fail_limit(rn_get_client_ip())) {
        $login_error = '失败次数过多，请5分钟后再试';
    } elseif (rn_admin_login($_POST['login_password'])) {
        header('Location: admin.php'); exit;
    } else { $login_error = '密码错误'; }
}
if (isset($_GET['logout'])) { rn_admin_logout(); header('Location: admin.php'); exit; }
$is_logged_in = rn_is_admin_logged_in();
$resources = rn_get_resources();
$config = rn_get_config();
$site_font = $config['site_font'] ?? '';
$shop_icon = $config['shop_icon'] ?? '';
$font_family = $site_font ? "'$site_font', " : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台管理 - 资源导航</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
button:focus,button:focus-visible,a:focus,a:focus-visible,input:focus,input:focus-visible,textarea:focus,textarea:focus-visible,select:focus,select:focus-visible,[tabindex]:focus,[tabindex]:focus-visible{outline:none!important;box-shadow:none!important}
button::-moz-focus-inner,input::-moz-focus-inner{border:0}
input,textarea,button,select{font-family:inherit}
body{font-family:<?php echo $font_family; ?>-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif;background:#e8ecf1;color:#4a5568;min-height:100vh;font-size:14px}
body h1,body h2,body h3,body h4,body h5,body h6{font-family:inherit;font-weight:700}

.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.login-box{background:#e8ecf1;border-radius:24px;padding:40px;max-width:380px;width:100%;box-shadow:12px 12px 24px #b8bdc5,-12px -12px 24px #fff;text-align:center}
.login-box h2{font-size:22px;color:#2d3748;margin-bottom:8px}
.login-box p{color:#a0aec0;font-size:13px;margin-bottom:30px}
.login-input{width:100%;padding:14px 18px;border-radius:14px;border:none;background:#e8ecf1;box-shadow:inset 5px 5px 10px #c8cdd5,inset -5px -5px 10px #fff;font-size:15px;color:#2d3748;margin-bottom:20px;outline:none}
.login-btn{width:100%;padding:14px;border-radius:14px;border:none;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:16px;font-weight:600;cursor:pointer;box-shadow:5px 5px 12px rgba(102,126,234,.3),-3px -3px 8px rgba(255,255,255,.8);transition:all .3s ease}
.login-btn:hover{transform:translateY(-2px)}
.login-error{color:#e53e3e;font-size:13px;margin-top:12px}

.admin-wrap{display:none;min-height:100vh;padding-bottom:80px}
.admin-wrap.active{display:block}

.sidebar-pc{width:220px;background:#e8ecf1;box-shadow:4px 0 12px rgba(0,0,0,.08);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200}
.sidebar-pc-header{padding:20px;display:flex;align-items:center;gap:8px}
.sidebar-pc-header h1{font-size:16px;color:#2d3748;font-weight:700;white-space:nowrap}
.sidebar-pc-nav{flex:1;display:flex;flex-direction:column;gap:4px;padding:10px;overflow-y:auto}
.sidebar-pc-item{padding:12px 16px;border-radius:12px;border:none;background:none;cursor:pointer;color:#718096;font-size:14px;display:flex;align-items:center;gap:12px;transition:all .2s ease;text-align:left;white-space:nowrap}
.sidebar-pc-item svg{width:20px;height:20px;flex-shrink:0}
.sidebar-pc-item:hover{background:rgba(102,126,234,.08);color:#667eea}
.sidebar-pc-item.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:4px 4px 10px rgba(102,126,234,.3)}
.sidebar-pc-footer{padding:15px;border-top:1px solid rgba(0,0,0,.05)}
.sidebar-pc-footer-btn{width:100%;padding:10px;border-radius:10px;border:none;background:#e8ecf1;box-shadow:3px 3px 6px #c8cdd5,-3px -3px 6px #ffffff;cursor:pointer;font-size:13px;color:#718096;display:flex;align-items:center;gap:8px;transition:all .2s ease;margin-bottom:8px}
.sidebar-pc-footer-btn:hover{box-shadow:inset 2px 2px 4px rgba(0,0,0,.1),inset -2px -2px 4px rgba(255,255,255,.8);color:#e53e3e}

.main-pc{margin-left:220px;min-height:100vh}
.topbar-pc{background:#e8ecf1;padding:15px 25px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 12px rgba(0,0,0,.05);position:sticky;top:0;z-index:100}
.topbar-pc h2{font-size:18px;color:#2d3748}

.tab-nav-mobile{display:none;position:fixed;bottom:0;left:0;right:0;background:#e8ecf1;justify-content:space-around;padding:6px 0 8px;box-shadow:0 -4px 16px rgba(0,0,0,.08);z-index:200;overflow-x:auto;-webkit-overflow-scrolling:touch}
.tab-nav-mobile-item{flex:0 0 auto;min-width:52px;display:flex;flex-direction:column;align-items:center;gap:1px;padding:4px 6px;border:none;background:none;cursor:pointer;color:#a0aec0;font-size:9px;transition:all .2s ease}
.tab-nav-mobile-item svg{width:18px;height:18px}
.tab-nav-mobile-item.active{color:#667eea}
.tab-nav-mobile-item.active svg{stroke:#667eea}

.content-area{padding:25px;max-width:1200px}
.tab-content{display:none}
.tab-content.active{display:block;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:15px;margin-bottom:25px}
.stat-card{background:#e8ecf1;border-radius:16px;padding:18px;box-shadow:6px 6px 12px #d1d5db,-6px -6px 12px #ffffff}
.stat-label{font-size:12px;color:#a0aec0;margin-bottom:6px}
.stat-value{font-size:24px;font-weight:700;color:#2d3748}
.stat-card:nth-child(1) .stat-value{color:#667eea}
.stat-card:nth-child(2) .stat-value{color:#48bb78}
.stat-card:nth-child(3) .stat-value{color:#ed8936}
.stat-card:nth-child(4) .stat-value{color:#e53e3e}
.stat-card:nth-child(5) .stat-value{color:#9f7aea}
.stat-card:nth-child(6) .stat-value{color:#38b2ac}
.stat-card:nth-child(7) .stat-value{color:#f6ad55}
.stat-card:nth-child(8) .stat-value{color:#4299e1}

.resource-edit-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:15px}
.resource-edit-card{background:#e8ecf1;border-radius:16px;padding:18px;box-shadow:5px 5px 10px #d1d5db,-5px -5px 10px #ffffff}
.resource-edit-card h3{font-size:14px;color:#2d3748;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between}
.edit-field{margin-bottom:10px}
.edit-field label{display:block;font-size:11px;color:#a0aec0;margin-bottom:4px}
.edit-input{width:100%;padding:8px 12px;border-radius:8px;border:none;background:#e8ecf1;box-shadow:inset 2px 2px 4px #c8cdd5,inset -2px -2px 4px #ffffff;font-size:13px;color:#2d3748;outline:none}
.edit-input:focus{box-shadow:inset 2px 2px 4px #c8cdd5,inset -2px -2px 4px #ffffff,0 0 0 2px rgba(102,126,234,.2)}
.edit-actions{display:flex;gap:6px;margin-top:12px}
.edit-btn{flex:1;padding:8px;border-radius:8px;border:none;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s ease;color:#fff}
.edit-btn-save{background:linear-gradient(135deg,#48bb78,#38a169);box-shadow:2px 2px 6px rgba(72,187,120,.3)}
.edit-btn-test{background:linear-gradient(135deg,#4facfe,#00f2fe);box-shadow:2px 2px 6px rgba(79,172,254,.3)}
.edit-btn-del{background:linear-gradient(135deg,#fc8181,#e53e3e);box-shadow:2px 2px 6px rgba(252,129,129,.3)}
.edit-btn:hover{transform:translateY(-1px);filter:brightness(1.1)}

.icon-upload{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.icon-preview{width:48px;height:48px;border-radius:10px;background:#e8ecf1;box-shadow:inset 3px 3px 6px rgba(0,0,0,.1),inset -3px -3px 6px rgba(255,255,255,.8);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0}
.icon-preview img{max-width:100%;max-height:100%;object-fit:contain}
.icon-upload-btn{padding:6px 12px;border-radius:8px;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;font-size:12px;color:#718096;transition:all .2s ease}
.icon-upload-btn:hover{box-shadow:inset 1px 1px 2px rgba(0,0,0,.1),inset -1px -1px 2px rgba(255,255,255,.8);color:#667eea}

.pwd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.pwd-card{background:#e8ecf1;border-radius:12px;padding:14px;box-shadow:4px 4px 8px #d1d5db,-4px -4px 8px #ffffff;display:flex;align-items:center;justify-content:space-between}
.pwd-info{flex:1}
.pwd-name{font-size:13px;font-weight:600;color:#2d3748;margin-bottom:3px}
.pwd-val{font-size:12px;color:#667eea;font-family:monospace}
.pwd-copy{width:28px;height:28px;border-radius:6px;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#718096;flex-shrink:0;margin-left:8px}
.pwd-copy:hover{box-shadow:inset 1px 1px 2px rgba(0,0,0,.1),inset -1px -1px 2px rgba(255,255,255,.8);color:#667eea}

.data-table-wrap{background:#e8ecf1;border-radius:16px;padding:15px;box-shadow:5px 5px 10px #d1d5db,-5px -5px 10px #ffffff;overflow-x:auto;margin-bottom:15px}
.data-table{width:100%;border-collapse:collapse;font-size:12px;min-width:600px}
.data-table th{text-align:left;padding:10px 8px;color:#a0aec0;font-weight:600;font-size:11px;border-bottom:2px solid rgba(0,0,0,.05)}
.data-table td{padding:10px 8px;border-bottom:1px solid rgba(0,0,0,.03);color:#4a5568;vertical-align:top}
.data-table tr:hover td{background:rgba(102,126,234,.03)}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.badge-success{background:rgba(72,187,120,.15);color:#38a169}
.badge-danger{background:rgba(229,62,62,.15);color:#e53e3e}
.badge-info{background:rgba(66,153,225,.15);color:#3182ce}
.badge-reply{background:rgba(102,126,234,.15);color:#667eea}
.table-actions{display:flex;gap:4px}
.table-btn{padding:4px 8px;border-radius:6px;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;font-size:11px;color:#718096;transition:all .2s ease;white-space:nowrap}
.table-btn:hover{box-shadow:inset 1px 1px 2px rgba(0,0,0,.1),inset -1px -1px 2px rgba(255,255,255,.8);color:#4a5568}
.table-btn-reply{color:#667eea}
.table-btn-del{color:#e53e3e}
.mode-toggle{display:flex;gap:8px;margin-bottom:15px;flex-wrap:wrap}
.mode-btn{padding:6px 16px;border-radius:8px;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;font-size:12px;color:#718096;transition:all .2s ease}
.mode-btn.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:2px 2px 6px rgba(102,126,234,.3)}

.settings-form{max-width:600px}
.settings-group{background:#e8ecf1;border-radius:16px;padding:20px;box-shadow:5px 5px 10px #d1d5db,-5px -5px 10px #ffffff;margin-bottom:15px}
.settings-group h3{font-size:15px;color:#2d3748;margin-bottom:15px}
.switch-wrap{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.switch-label{font-size:13px;color:#4a5568}
.switch{position:relative;width:44px;height:24px}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#c8cdd5;border-radius:24px;transition:.3s;box-shadow:inset 2px 2px 4px rgba(0,0,0,.1),inset -2px -2px 4px rgba(255,255,255,.5)}
.slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:1px 1px 3px rgba(0,0,0,.2)}
input:checked+.slider{background:linear-gradient(135deg,#667eea,#764ba2)}
input:checked+.slider:before{transform:translateX(20px)}

select.edit-input{-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 24 24' fill='none' stroke='%23a0aec0' stroke-width='2' xmlns='http://www.w3.org/2000/svg'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;background-size:16px;padding-right:36px}

.empty-state{text-align:center;padding:40px 20px;color:#a0aec0;font-size:13px}

.pagination{display:flex;align-items:center;justify-content:center;gap:4px;margin-top:15px;flex-wrap:wrap}
.pagination button{padding:6px 12px;border-radius:6px;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;font-size:12px;color:#718096;transition:all .2s ease;min-width:32px}
.pagination button:hover{box-shadow:inset 1px 1px 2px rgba(0,0,0,.1),inset -1px -1px 2px rgba(255,255,255,.8);color:#4a5568}
.pagination button.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:2px 2px 6px rgba(102,126,234,.3)}
.pagination button:disabled{opacity:.4;cursor:not-allowed}
.pagination .page-info{font-size:12px;color:#a0aec0;padding:0 8px}

.toast{position:fixed;top:20px;left:50%;transform:translateX(-50%) translateY(-60px);background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:10px 24px;border-radius:50px;font-size:13px;font-weight:500;box-shadow:0 8px 20px rgba(102,126,234,0.4);z-index:3000;opacity:0;transition:all 0.4s ease;pointer-events:none}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.error{background:linear-gradient(135deg,#fc8181,#e53e3e)}

.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:2000;backdrop-filter:blur(4px)}
.modal-overlay.active{display:flex}
.modal-box{background:#e8ecf1;border-radius:20px;padding:24px;max-width:400px;width:90%;box-shadow:12px 12px 24px #b8bdc5,-12px -12px 24px #fff;animation:modalIn .3s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-title{font-size:16px;font-weight:700;color:#2d3748;margin-bottom:12px}
.modal-input{width:100%;padding:10px 14px;border-radius:10px;border:none;background:#e8ecf1;box-shadow:inset 3px 3px 6px #c8cdd5,inset -3px -3px 6px #fff;font-size:14px;color:#2d3748;margin-bottom:12px;outline:none}
.modal-textarea{min-height:80px;resize:vertical;font-family:inherit}
.modal-actions{display:flex;gap:10px;margin-top:15px}
.modal-btn{padding:10px 20px;border-radius:10px;border:none;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s ease}
.modal-btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;flex:1}
.modal-btn-cancel{background:#e8ecf1;box-shadow:3px 3px 6px #c8cdd5,-3px -3px 6px #fff;color:#718096}
.modal-btn-danger{background:linear-gradient(135deg,#fc8181,#e53e3e);color:#fff}
.modal-btn:hover{transform:translateY(-1px)}

@media(max-width:768px){
  .sidebar-pc{display:none}
  .main-pc{margin-left:0}
  .tab-nav-mobile{display:flex}
  .admin-wrap.active{padding-bottom:90px}
  .topbar-pc{padding:12px 15px;justify-content:center}
  .topbar-pc h2{font-size:16px}
  .content-area{padding:15px;max-width:100%}
  .stats-grid{grid-template-columns:repeat(2,1fr);gap:10px}
  .stat-card{padding:14px}
  .stat-value{font-size:20px}
  .resource-edit-grid{grid-template-columns:1fr}
  .data-table-wrap{padding:10px;-webkit-overflow-scrolling:touch}
  .data-table{font-size:11px;min-width:500px}
  .data-table th,.data-table td{padding:8px 6px}
  .pwd-grid{grid-template-columns:1fr;gap:10px}
  .modal-box{max-width:90vw;padding:20px;width:95%}
  .modal-input,.modal-textarea{font-size:16px}
  .modal-btn{font-size:13px;padding:10px 16px}
  .settings-form{max-width:100%}
  .settings-group{padding:15px}
  .edit-input{font-size:16px;padding:10px 14px}
  .edit-field label{font-size:12px}
  .mode-toggle{justify-content:center}
  .pagination{flex-wrap:wrap;justify-content:center}
  .table-actions{flex-wrap:wrap;gap:2px}
  .table-btn{font-size:10px;padding:3px 6px}
  .icon-upload{flex-wrap:wrap;gap:8px}
}

@media(min-width:769px){
  .tab-nav-mobile{display:none}
  .admin-wrap.active{padding-bottom:0}
}
</style>
</head>
<body>
<div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true"><input type="text" name="username" autocomplete="username"><input type="password" name="password" autocomplete="current-password"></div>

<?php if (!$is_logged_in): ?>
<div class="login-wrap">
  <div class="login-box">
    <h2>后台管理</h2>
    <p>请输入管理密码登录</p>
    <form method="POST" action="admin.php">
      <input type="password" name="login_password" class="login-input" placeholder="管理密码" required autofocus>
      <button type="submit" class="login-btn">登录</button>
      <?php if (!empty($login_error)): ?><div class="login-error"><?php echo $login_error; ?></div><?php endif; ?>
    </form>
    <p style="color:#a0aec0;font-size:12px;margin-top:15px">连续输错5次将锁定5分钟</p>
  </div>
</div>
<?php else: ?>
<div class="admin-wrap active" id="adminPage">

<aside class="sidebar-pc" id="sidebarPc">
  <div class="sidebar-pc-header">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
    <h1>资源导航后台</h1>
  </div>
  <nav class="sidebar-pc-nav">
    <button class="sidebar-pc-item active" onclick="switchTab('overview',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>概览
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('resources',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>资源管理
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('passwords',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>密码统计
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('visitors',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>访客记录
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('messages',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>留言管理
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('urges',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>催更管理
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('logs',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>日志
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('blacklist',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>IP拉黑
    </button>
    <button class="sidebar-pc-item" onclick="switchTab('settings',this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>系统设置
    </button>
  </nav>
  <div class="sidebar-pc-footer">
    <button class="sidebar-pc-footer-btn" onclick="location.href='index.php'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>返回前台
    </button>
    <button class="sidebar-pc-footer-btn" onclick="location.href='admin.php?logout=1'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>退出登录
    </button>
  </div>
</aside>

<main class="main-pc" id="mainPc">
  <div class="topbar-pc">
    <h2 id="pageTitle">数据概览</h2>
  </div>

  <div class="content-area">
    <div class="tab-content active" id="tab-overview">
      <div class="chart-wrap" style="margin-bottom:20px;display:none" id="overviewChartWrap">
        <div class="settings-group"><h3>近7日访问趋势</h3><canvas id="overviewChart" style="max-height:180px"></canvas></div>
      </div>
      <div class="stats-grid" id="statsGrid">
        <div class="stat-card"><div class="stat-label">总访客</div><div class="stat-value">-</div></div>
        <div class="stat-card"><div class="stat-label">今日访客</div><div class="stat-value">-</div></div>
        <div class="stat-card"><div class="stat-label">总访问量</div><div class="stat-value">-</div></div>
        <div class="stat-card"><div class="stat-label">当前在线</div><div class="stat-value">-</div></div>
        <div class="stat-card"><div class="stat-label">资源数量</div><div class="stat-value">-</div></div>
        <div class="stat-card"><div class="stat-label">留言数量</div><div class="stat-value">-</div></div>
        <div class="stat-card"><div class="stat-label">催更数量</div><div class="stat-value">-</div></div>
        <div class="stat-card"><div class="stat-label">登录记录</div><div class="stat-value">-</div></div>
      </div>
      <div class="settings-group" id="refererPanel" style="display:none">
        <h3>访问来源分析</h3>
        <div style="display:flex;flex-direction:column;gap:16px">
          <div>
            <h4 style="font-size:13px;color:#a0aec0;margin-bottom:8px">浏览器统计</h4>
            <div class="data-table-wrap"><table class="data-table"><thead><tr><th>浏览器</th><th>次数</th></tr></thead><tbody id="browserTbody"></tbody></table></div>
          </div>
          <div>
            <h4 style="font-size:13px;color:#a0aec0;margin-bottom:8px">设备统计</h4>
            <div class="data-table-wrap"><table class="data-table"><thead><tr><th>设备</th><th>次数</th></tr></thead><tbody id="deviceTbody"></tbody></table></div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-content" id="tab-resources">
      <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
        <button class="icon-upload-btn" onclick="addResource()" style="padding:8px 20px;font-size:14px">+ 添加资源</button>
        <button class="icon-upload-btn" onclick="sortByClicks()" style="padding:8px 14px;font-size:13px">按热度排序</button>
      </div>
      <div class="resource-edit-grid" id="resourceGrid"><div class="empty-state">加载中...</div></div>
    </div>

    <div class="tab-content" id="tab-passwords">
      <h3 style="font-size:15px;color:#2d3748;margin-bottom:15px">资源密码一览</h3>
      <div class="pwd-grid" id="pwdGrid">
        <?php foreach ($resources as $res): ?>
        <div class="pwd-card">
          <div class="pwd-info">
            <div class="pwd-name"><?php echo htmlspecialchars($res['name']); ?></div>
            <div class="pwd-val"><?php echo htmlspecialchars($res['password'] ?? '无'); ?></div>
          </div>
          <button class="pwd-copy" onclick="copyText('<?php echo htmlspecialchars($res['password'] ?? ''); ?>')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          </button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-content" id="tab-visitors">
      <div class="mode-toggle">
        <button class="mode-btn active" onclick="loadVisitors('simple',this)">简洁模式</button>
        <button class="mode-btn" onclick="loadVisitors('detail',this)">详细模式</button>
        <button class="mode-btn" onclick="clearData('visitors')" style="color:#e53e3e;margin-left:auto">清空访客</button>
      </div>
      <div class="data-table-wrap" style="margin-bottom:8px;font-size:11px;color:#a0aec0;padding:8px 15px;">
        简洁: 每个IP只显示最近访问 / 详细: 显示所有访问记录
      </div>
      <div class="data-table-wrap">
        <table class="data-table" id="visitorTable">
          <thead><tr><th>IP</th><th>地址</th><th>ISP</th><th>系统</th><th>浏览器</th><th>设备</th><th>来源</th><th>时间</th></tr></thead>
          <tbody><tr><td colspan="8" class="empty-state">加载中...</td></tr></tbody>
        </table>
      </div>
      <div id="visitorPagination" class="pagination"></div>
    </div>

    <div class="tab-content" id="tab-messages">
      <div style="margin-bottom:8px"><button class="mode-btn" onclick="clearData('messages')" style="color:#e53e3e">清空留言</button></div>
      <div class="data-table-wrap">
        <table class="data-table" id="messageTable">
          <thead><tr><th>ID</th><th>昵称</th><th>内容</th><th>回复</th><th>点赞</th><th>IP</th><th>时间</th><th>操作</th></tr></thead>
          <tbody><tr><td colspan="8" class="empty-state">加载中...</td></tr></tbody>
        </table>
      </div>
      <div id="msgPagination" class="pagination"></div>
    </div>

    <div class="tab-content" id="tab-urges">
      <div style="margin-bottom:8px"><button class="mode-btn" onclick="clearData('urges')" style="color:#e53e3e">清空催更</button></div>
      <div class="data-table-wrap">
        <table class="data-table" id="urgeTable">
          <thead><tr><th>ID</th><th>资源</th><th>内容</th><th>回复</th><th>IP</th><th>时间</th><th>操作</th></tr></thead>
          <tbody><tr><td colspan="7" class="empty-state">加载中...</td></tr></tbody>
        </table>
      </div>
      <div id="urgePagination" class="pagination"></div>
    </div>

    <div class="tab-content" id="tab-logs">
      <div class="mode-toggle">
        <button class="mode-btn active" onclick="loadLogs('action',this)">操作日志</button>
        <button class="mode-btn" onclick="loadLogs('login',this)">登录记录</button>
        <button class="mode-btn" onclick="clearData('logs')" style="color:#e53e3e;margin-left:auto">清空操作日志</button>
        <button class="mode-btn" onclick="clearData('login_logs')" style="color:#e53e3e">清空登录记录</button>
      </div>
      <div class="data-table-wrap">
        <table class="data-table" id="logTable">
          <thead><tr><th>操作</th><th>详情</th><th>IP</th><th>浏览器</th><th>系统</th><th>时间</th></tr></thead>
          <tbody><tr><td colspan="6" class="empty-state">加载中...</td></tr></tbody>
        </table>
      </div>
      <div id="logPagination" class="pagination"></div>
    </div>

    <div class="tab-content" id="tab-blacklist">
      <h3 style="font-size:15px;color:#2d3748;margin-bottom:15px">IP黑名单管理</h3>
      <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:15px">
        <div style="flex:1;min-width:200px">
          <label style="display:block;font-size:11px;color:#a0aec0;margin-bottom:4px">IP地址</label>
          <input type="text" class="edit-input" id="blockIp" placeholder="如: 192.168.1.1" style="width:100%">
        </div>
        <div style="flex:1;min-width:200px">
          <label style="display:block;font-size:11px;color:#a0aec0;margin-bottom:4px">拉黑原因</label>
          <input type="text" class="edit-input" id="blockReason" placeholder="如: 恶意刷流量" style="width:100%">
        </div>
        <div>
          <button class="login-btn" onclick="addBlacklist()" style="padding:10px 24px;font-size:14px;max-width:none;width:auto">拉黑</button>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:15px">
        <div style="flex:1;min-width:300px">
          <label style="display:block;font-size:11px;color:#a0aec0;margin-bottom:4px">批量拉黑（每行一个IP，或空格/逗号分隔）</label>
          <textarea class="edit-input modal-textarea" id="batchIps" placeholder="192.168.1.1&#10;10.0.0.1&#10;172.16.0.1" rows="3" style="width:100%;min-height:60px"></textarea>
        </div>
        <div style="flex:1;min-width:200px">
          <label style="display:block;font-size:11px;color:#a0aec0;margin-bottom:4px">批量原因</label>
          <input type="text" class="edit-input" id="batchReason" placeholder="批量拉黑" style="width:100%">
        </div>
        <div>
          <button class="login-btn" onclick="batchBlacklist()" style="padding:10px 24px;font-size:14px;max-width:none;width:auto;background:linear-gradient(135deg,#ed8936,#dd6b20)">批量拉黑</button>
        </div>
      </div>
      <div class="data-table-wrap">
        <table class="data-table" id="blacklistTable">
          <thead><tr><th>IP</th><th>地区</th><th>原因</th><th>拉黑时间</th><th>操作</th></tr></thead>
          <tbody><tr><td colspan="5" class="empty-state">加载中...</td></tr></tbody>
        </table>
      </div>
      <div style="font-size:12px;color:#a0aec0;margin-top:8px;text-align:right" id="blacklistCount"></div>
      <h3 style="font-size:15px;color:#2d3748;margin-top:30px;margin-bottom:15px">解除拉黑申请 <span style="font-size:11px;color:#ed8936" id="unblockBadge"></span></h3>
      <div class="data-table-wrap">
        <table class="data-table" id="unblockRequestTable">
          <thead><tr><th>IP</th><th>地区</th><th>申请时间</th><th>状态</th><th>操作</th></tr></thead>
          <tbody><tr><td colspan="5" class="empty-state">加载中...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="tab-content" id="tab-settings">
      <div class="settings-form">
        <div class="settings-group">
          <h3>弹窗公告</h3>
          <div class="switch-wrap">
            <span class="switch-label">启用公告弹窗</span>
            <label class="switch"><input type="checkbox" id="popupEnabled"><span class="slider"></span></label>
          </div>
          <div class="edit-field"><label>公告标题</label><input type="text" class="edit-input" id="popupTitle" placeholder="公告"></div>
          <div class="edit-field"><label>公告内容</label><textarea class="edit-input" id="popupContent" rows="4" placeholder="公告内容..."></textarea></div>
        </div>
        <div class="settings-group">
          <h3>外观设置</h3>
          <div class="edit-field"><label>网站标题</label><input type="text" class="edit-input" id="siteTitle" placeholder="资源导航"></div>
          <div class="icon-upload" style="margin-bottom:12px">
            <label style="font-size:13px;font-weight:600;color:#4a5568;display:block;margin-bottom:6px">店铺图标 (显示在店铺名称旁)</label>
            <div class="icon-preview" id="shopIconPrev" style="width:48px;height:48px"><?php if($shop_icon): ?><img src="data/<?php echo htmlspecialchars($shop_icon); ?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><?php endif; ?></div>
            <button class="icon-upload-btn" onclick="document.getElementById('shopIconFile').click()" style="margin-top:6px">上传图标</button>
            <input type="file" id="shopIconFile" accept="image/*" style="display:none" onchange="doUploadShopIcon()">
            <input type="hidden" id="shopIcon" value="<?php echo htmlspecialchars($shop_icon); ?>">
          </div>
          <div class="edit-field"><label>全站字体</label>
          <select class="edit-input" id="siteFont">
            <option value="">系统默认</option>
            <option value="ZCOOL KuaiLe">ZCOOL KuaiLe (站酷快乐体)</option>
            <option value="ZCOOL XiaoWei">ZCOOL XiaoWei (站酷小薇体)</option>
            <option value="ZCOOL QingKe HuangYou">ZCOOL QingKe HuangYou (站酷庆科黄油体)</option>
            <option value="Ma Shan Zheng">Ma Shan Zheng (马山正楷)</option>
            <option value="Noto Serif SC">Noto Serif SC (思源宋体)</option>
            <option value="Noto Sans SC">Noto Sans SC (思源黑体)</option>
            <option value="Long Cang">Long Cang (龙藏体)</option>
            <option value="Zhi Mang Xing">Zhi Mang Xing (志莽行书)</option>
            <option value="Liu Jian Mao Cao">Liu Jian Mao Cao (柳建毛草)</option>
            <option value="LXGW WenKai">LXGW WenKai (霞鹜文楷)</option>
            <option value="Cute Font">Cute Font (可爱卡通)</option>
            <option value="Hi Melody">Hi Melody (韩系手写)</option>
            <option value="Dokdo">Dokdo (韩系粗体)</option>
          </select></div>
        </div>
        <div class="settings-group">
          <h3>安全设置</h3>
          <div class="switch-wrap"><span class="switch-label">前台密码保护(点击查看)</span><label class="switch"><input type="checkbox" id="passwordProtection"><span class="slider"></span></label></div>
          <div class="edit-field"><label>QQ群验证密码</label><input type="text" class="edit-input" id="qqGroupPwd" placeholder="密码"></div>
          <div class="edit-field"><label>联系QQ</label><input type="text" class="edit-input" id="contactQQ" placeholder="请输入QQ号"></div>
          <div class="edit-field"><label>修改管理密码</label><input type="password" class="edit-input" id="adminPwd" placeholder="新密码" autocomplete="new-password"></div>
        </div>
        <div class="settings-group">
          <h3>前台文字</h3>
          <div class="edit-field"><label>店铺名称</label><input type="text" class="edit-input" id="shopName" placeholder="资源导航"></div>
          <div class="edit-field"><label>店铺副标题</label><input type="text" class="edit-input" id="shopSub" placeholder="副标题"></div>
          <div class="edit-field"><label>跳转按钮文字</label><input type="text" class="edit-input" id="btnJumpText" placeholder="蓝奏云下载"></div>
          <div class="edit-field"><label>QQ群按钮文字</label><input type="text" class="edit-input" id="btnQqText" placeholder="qq群(文件在q群文件)"></div>
          <div class="edit-field"><label>催更按钮文字</label><input type="text" class="edit-input" id="btnUrgeText" placeholder="催更"></div>
          <div class="edit-field"><label>联系文字</label><input type="text" class="edit-input" id="contactText" placeholder="如有疑问请联系QQ"></div>
          <div class="edit-field"><label>留言板标题</label><input type="text" class="edit-input" id="sectionGuestbook" placeholder="留言板"></div>
          <div class="edit-field"><label>催更墙标题</label><input type="text" class="edit-input" id="sectionUrgeWall" placeholder="催更墙"></div>
          <div class="edit-field"><label>QQ群提示文字</label><input type="text" class="edit-input" id="qqHintText" placeholder="输入密码查看内容"></div>
          <div class="edit-field"><label>底部引流文案</label><textarea class="edit-input" id="footerText" rows="2" placeholder="如: 所有资源均来自网络收集，仅供学习交流..."></textarea></div>
        </div>
        <div class="settings-group">
          <h3>功能设置</h3>
           <div class="edit-field"><label>暗黑模式</label><select class="edit-input" id="darkMode"><option value="auto">跟随系统</option><option value="light">浅色</option><option value="dark">深色</option></select></div>
             <div class="edit-field"><label>Server酱 SendKey <a href="#" onclick="alert('获取步骤:\n1. 打开 https://sct.ftqq.com/\n2. 微信扫码登录\n3. 点击「发送消息」复制SendKey\n4. 填入此处保存\n5. 关注Server酱公众号接收推送');return false" style="font-size:11px;color:#ff9800;text-decoration:none">[怎么获取?]</a><span style="font-size:10px;color:#48bb78;margin-left:4px">免费</span></label><input type="text" class="edit-input" id="sctKey" placeholder="SCT开头的SendKey..."></div>
             <div class="edit-field"><label>WxPusher AppToken <a href="#" onclick="alert('获取步骤:\n1. 打开 https://wxpusher.zjiecode.com/\n2. 微信扫码登录后台\n3. 创建应用获取AppToken（AT_开头）\n4. 关注「WxPusher」公众号');return false" style="font-size:11px;color:#ff9800;text-decoration:none">[怎么获取?]</a><span style="font-size:10px;color:#48bb78;margin-left:4px">免费</span></label><input type="text" class="edit-input" id="wxpusherToken" placeholder="AT_开头的AppToken..."></div>
             <div class="edit-field"><label>WxPusher UID <a href="#" onclick="alert('获取方法:\n1. WxPusher后台→应用→用户管理\n2. 查看你的UID（UID_开头）\n3. 如果没有，先去关注WxPusher公众号');return false" style="font-size:11px;color:#ff9800;text-decoration:none">[怎么看?]</a></label><input type="text" class="edit-input" id="wxpusherUid" placeholder="UID_开头的用户ID..."></div>
             <div class="edit-field"><label>Bark DeviceKey (仅iOS) <a href="#" onclick="alert('获取步骤:\n1. 在App Store下载Bark\n2. 打开App自动生成推送链接\n3. 链接末尾/后面的就是DeviceKey\n4. 复制填入此处');return false" style="font-size:11px;color:#ff9800;text-decoration:none">[怎么获取?]</a><span style="font-size:10px;color:#48bb78;margin-left:4px">免费</span></label><input type="text" class="edit-input" id="barkKey" placeholder="Bark设备Key..."></div>
             <div class="edit-field"><label>PushPlus Token (备用) <a href="#" onclick="alert('使用步骤:\n1. 打开 https://www.pushplus.plus/\n2. 微信扫码登录\n3. 在「发送消息」页面复制Token\n4. 填入此处保存\n\n注意：新用户可能需要付费认证');return false" style="font-size:11px;color:#98a2b3;text-decoration:none">[怎么获取?]</a><span style="font-size:10px;color:#e53e3e;margin-left:4px">可能收费</span></label><input type="text" class="edit-input" id="pushplusToken" placeholder="PushPlus Token..."></div>
            <div class="edit-field"><label>QQ邮箱SMTP服务器</label><input type="text" class="edit-input" id="smtpHost" placeholder="smtp.qq.com"></div>
            <div class="edit-field"><label>SMTP端口</label><input type="number" class="edit-input" id="smtpPort" value="465" placeholder="465" style="width:120px"></div>
            <div class="edit-field"><label>发件QQ邮箱</label><input type="text" class="edit-input" id="smtpUser" placeholder="your@qq.com"></div>
            <div class="edit-field"><label>QQ邮箱授权码 <a href="#" onclick="alert('获取方法:\n1. 登录QQ邮箱网页版\n2. 设置→账户→POP3/SMTP服务\n3. 开启并获取授权码（16位）\n4. 填入此处');return false" style="font-size:11px;color:#ff9800;text-decoration:none">[怎么获取?]</a></label><input type="password" class="edit-input" id="smtpPass" placeholder="QQ邮箱授权码（16位）" autocomplete="new-password"></div>
          </div>
          <div class="settings-group">
            <h3>频率限制</h3>
            <div class="switch-wrap"><span class="switch-label">启用频率限制</span><label class="switch"><input type="checkbox" id="rateLimitEnabled"><span class="slider"></span></label></div>
            <div class="edit-field"><label>留言 - 每IP每分钟最多</label><input type="number" class="edit-input" id="rateLimitMsg" value="3" min="1" max="30" style="width:120px"></div>
            <div class="edit-field"><label>催更 - 每IP每分钟最多</label><input type="number" class="edit-input" id="rateLimitUrge" value="3" min="1" max="30" style="width:120px"></div>
            <div class="edit-field"><label>点赞 - 每IP每分钟最多</label><input type="number" class="edit-input" id="rateLimitLike" value="10" min="1" max="60" style="width:120px"></div>
          </div>
          <div class="settings-group">
            <h3>自动拉黑（防刷流量）</h3>
            <div class="switch-wrap"><span class="switch-label">启用自动拉黑</span><label class="switch"><input type="checkbox" id="autoBlockEnabled"><span class="slider"></span></label></div>
            <div class="edit-field"><label>检测时间窗口(秒, 默认60)</label><input type="number" class="edit-input" id="autoBlockWindow" value="60" min="10" max="600" style="width:120px"></div>
            <div class="edit-field"><label>时间窗口内访问次数阈值(默认30)</label><input type="number" class="edit-input" id="autoBlockThreshold" value="30" min="5" max="500" style="width:120px"></div>
            <div class="edit-field"><label>白名单地区(逗号分隔, 如: 山东,威海)</label><input type="text" class="edit-input" id="autoBlockWhitelist" placeholder="山东,威海" style="width:100%"></div>
            <div class="edit-field"><label>白名单IP(逗号分隔, 这些IP永不自动拉黑)</label><input type="text" class="edit-input" id="autoBlockIpWhitelist" placeholder="你的IP, 如: 123.45.67.89" style="width:100%"></div>
            <p style="font-size:11px;color:#a0aec0;margin-top:6px">白名单地区内的IP不会被自动拉黑。匹配规则：IP归属地包含任一关键词即豁免。</p>
          </div>
          <div class="settings-group">
            <h3>数据统计偏移量（显示值 = 真实值 + 偏移量）</h3>
            <div class="edit-field"><label>总浏览量偏移</label><input type="number" class="edit-input" id="viewsOffset" value="0" min="0" step="100" style="width:160px"></div>
            <div class="edit-field"><label>总访客量偏移</label><input type="number" class="edit-input" id="visitorsOffset" value="0" min="0" step="100" style="width:160px"></div>
            <div class="edit-field"><label>今日访客量偏移</label><input type="number" class="edit-input" id="todayVisitorsOffset" value="0" min="0" step="10" style="width:160px"></div>
            <p style="font-size:11px;color:#a0aec0;margin-top:6px">设置偏移量后，前台显示的数据 = 真实统计 + 偏移量。设0则不偏移。</p>
          </div>
          <div class="settings-group">
          <h3>轮播图</h3>
          <div class="switch-wrap"><span class="switch-label">启用轮播图</span><label class="switch"><input type="checkbox" id="carouselEnabled"><span class="slider"></span></label></div>
          <div class="edit-field"><label>切换速度(毫秒,默认4000)</label><input type="number" class="edit-input" id="carouselSpeed" value="4000" min="1000" max="20000" step="500"></div>
          <div class="edit-field"><label>电脑端高度(px,默认160)</label><input type="number" class="edit-input" id="carouselHeightDesktop" value="160" min="60" max="500"></div>
          <div class="edit-field"><label>手机端高度(px,默认110)</label><input type="number" class="edit-input" id="carouselHeightMobile" value="110" min="40" max="300"></div>
          <div id="carouselSlides"></div>
          <button class="icon-upload-btn" onclick="addCarouselSlide()" style="margin-top:8px">+ 添加轮播项</button>
        </div>
        <div class="settings-group">
          <h3>分区管理</h3>
          <div id="partitionList"></div>
          <button class="icon-upload-btn" onclick="addPartition()" style="margin-top:8px">+ 添加新分区</button>
        </div>
        <button class="login-btn" onclick="saveSettings()" style="max-width:200px">保存设置</button>
      </div>
    </div>
  </div>
</main>

<nav class="tab-nav-mobile" id="tabNavMobile">
  <button class="tab-nav-mobile-item active" onclick="switchTabMobile('overview',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>概览
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('resources',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>资源
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('passwords',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>密码
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('visitors',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>访客
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('messages',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>留言
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('urges',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>催更
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('logs',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>日志
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('blacklist',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>拉黑
  </button>
  <button class="tab-nav-mobile-item" onclick="switchTabMobile('settings',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>设置
  </button>
</nav>
</div>

<div class="toast" id="toast"></div>

<div class="modal-overlay" id="confirmModal">
  <div class="modal-box"><div class="modal-title" id="confirmTitle"></div><div class="modal-actions"><button class="modal-btn modal-btn-cancel" onclick="closeConfirm()">取消</button><button class="modal-btn modal-btn-primary" id="confirmOk" onclick="execConfirm()">确认</button></div></div>
</div>

<div class="modal-overlay" id="replyModal">
  <div class="modal-box"><div class="modal-title" id="replyTitle"></div><textarea class="modal-input modal-textarea" id="replyInput" placeholder="回复内容..."></textarea><div class="modal-actions"><button class="modal-btn modal-btn-cancel" onclick="closeModal('replyModal')">取消</button><button class="modal-btn modal-btn-primary" id="replySubmitBtn" onclick="execReply()">发送</button></div></div>
</div>

<script>
const pageTitles={overview:'数据概览',resources:'资源管理',passwords:'密码统计',visitors:'访客记录',messages:'留言管理',urges:'催更管理',logs:'日志',blacklist:'IP拉黑',settings:'系统设置'};
let currentLogType='action',confirmCallback=null,replyCallback=null,rCurrentId=0;
let currentVisitorMode='simple',currentVisitorPage=1,currentMsgPage=1,currentUrgePage=1,currentLogPage=1;
let overviewRefreshTimer=null;

function startOverviewRefresh(){
  stopOverviewRefresh();
  overviewRefreshTimer=setInterval(function(){
    loadStats();
  },30000);
}

function stopOverviewRefresh(){
  if(overviewRefreshTimer){clearInterval(overviewRefreshTimer);overviewRefreshTimer=null;}
}

function switchTab(tab,btn){
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+tab).classList.add('active');
  document.querySelectorAll('.sidebar-pc-item').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  document.getElementById('pageTitle').textContent=pageTitles[tab]||'';
  stopOverviewRefresh();
  if(tab==='overview'){loadStats();startOverviewRefresh();}
  if(tab==='resources')loadResources();
  if(tab==='visitors')loadVisitors('simple');
  if(tab==='messages')loadMessages();
  if(tab==='urges')loadUrges();
  if(tab==='logs')loadLogs('action');
  if(tab==='blacklist')loadBlacklist();
  if(tab==='settings')loadSettings();
}

function switchTabMobile(tab,btn){
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+tab).classList.add('active');
  document.querySelectorAll('.tab-nav-mobile-item').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  document.getElementById('pageTitle').textContent=pageTitles[tab]||'';
  stopOverviewRefresh();
  if(tab==='overview'){loadStats();startOverviewRefresh();}
  if(tab==='resources')loadResources();
  if(tab==='visitors')loadVisitors('simple');
  if(tab==='messages')loadMessages();
  if(tab==='urges')loadUrges();
  if(tab==='logs')loadLogs('action');
  if(tab==='blacklist')loadBlacklist();
  if(tab==='settings')loadSettings();
}

function loadStats(){
  fetch('api.php?action=admin_get_stats').then(r=>r.json()).then(d=>{
    if(d.success){
      const s=d.data;
      document.getElementById('statsGrid').innerHTML=
        '<div class="stat-card"><div class="stat-label">总访客</div><div class="stat-value">'+s.total_visitors+'</div></div>'+
        '<div class="stat-card"><div class="stat-label">今日访客</div><div class="stat-value">'+s.today_visitors+'</div></div>'+
        '<div class="stat-card"><div class="stat-label">总访问量</div><div class="stat-value">'+s.total_views+'</div></div>'+
        '<div class="stat-card"><div class="stat-label">当前在线</div><div class="stat-value">'+s.online+'</div></div>'+
        '<div class="stat-card"><div class="stat-label">资源数量</div><div class="stat-value">'+s.resources_count+'</div></div>'+
        '<div class="stat-card"><div class="stat-label">留言数量</div><div class="stat-value">'+s.messages_count+'</div></div>'+
        '<div class="stat-card"><div class="stat-label">催更数量</div><div class="stat-value">'+s.urges_count+'</div></div>'+
        '<div class="stat-card"><div class="stat-label">登录记录</div><div class="stat-value">'+s.login_count+'</div></div>';
      // chart
      fetch('api.php?action=get_visitor_trend').then(r2=>r2.json()).then(d2=>{
        var w=document.getElementById('overviewChartWrap');
        if(d2.data&&d2.data.labels&&d2.data.labels.length){
          w.style.display='block';
          new Chart(document.getElementById('overviewChart').getContext('2d'),{
            type:'line',data:{labels:d2.data.labels,datasets:[{label:'访客',data:d2.data.data,borderColor:'#667eea',backgroundColor:'rgba(102,126,234,0.1)',fill:true,tension:0.3,pointRadius:3}]},
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
          });
        }
      });
      // stats
      fetch('api.php?action=get_top_referers').then(r2=>r2.json()).then(d2=>{
        if(d2.data){
          document.getElementById('refererPanel').style.display='block';
          // browsers
          var bro=document.getElementById('browserTbody');
          var brs=d2.data.browsers||{};
          var bkeys=Object.keys(brs);
          bro.innerHTML=bkeys.length?bkeys.map(function(k){
            return '<tr><td>'+esc(k)+'</td><td><span class="badge badge-info">'+brs[k]+'次</span></td></tr>';
          }).join(''):'<tr><td colspan="2" class="empty-state">暂无数据</td></tr>';
          // devices - filter out Unknown/Other
          var dev=document.getElementById('deviceTbody');
          var dvs=d2.data.devices||{};
          var dkeys=Object.keys(dvs).filter(function(k){return k!=='Other'&&k!=='Unknown';});
          dev.innerHTML=dkeys.length?dkeys.map(function(k){
            var label=k==='Desktop'?'电脑':k==='Mobile'?'手机':k==='iPhone'?'苹果手机':k==='iPad'?'苹果平板':k;
            return '<tr><td>'+esc(label)+'</td><td><span class="badge badge-info">'+dvs[k]+'次</span></td></tr>';
          }).join(''):'<tr><td colspan="2" class="empty-state">暂无数据</td></tr>';
        }
      });
    }
  });
}

function addResource(){
  const container=document.getElementById('resourceGrid');
  const iconHtml='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
  const card=document.createElement('div');
  card.className='resource-edit-card';card.setAttribute('data-id','0');
  card.innerHTML='<h3><span>#新资源</span><input type="color" class="edit-input" style="width:36px;height:28px;padding:0 2px" data-field="color" value="#4a90d9"></h3>'+
    '<div class="icon-upload"><div class="icon-preview" id="iconPrev_0">'+iconHtml+'</div><button class="icon-upload-btn" onclick="uploadIcon(0)">上传图标</button><input type="file" id="iconFile_0" accept="image/*" style="display:none" onchange="doUploadIcon(0)"><input type="hidden" data-field="icon" value=""></div>'+
    '<div class="edit-field"><label>显示名称</label><input type="text" class="edit-input" data-field="name" value=""></div>'+
    '<div class="edit-field"><label>链接</label><input type="text" class="edit-input" data-field="url" value=""></div>'+
    '<div class="edit-field"><label>密码</label><input type="text" class="edit-input" data-field="password" value=""></div>'+
    '<div class="edit-field"><label>QQ群1</label><input type="text" class="edit-input" data-field="qq_group_1" value=""></div>'+
    '<div class="edit-field"><label>QQ群2</label><input type="text" class="edit-input" data-field="qq_group_2" value=""></div>'+
    '<div class="edit-field"><label>卡片描述</label><input type="text" class="edit-input" data-field="description" value=""></div>'+
    '<div class="edit-field"><label>分类</label><input type="text" class="edit-input" data-field="category" value="" placeholder="如: FPS辅助、内核工具"></div>'+
    '<div class="edit-field"><label>归属分区</label><select class="edit-input" data-field="partition"><option value="game" selected>游戏内核分区</option><option value="other">其他分区</option></select></div>'+
    '<div class="edit-field"><label>版本号</label><input type="text" class="edit-input" data-field="version" value="" placeholder="如: 1.2.3"></div>'+
    '<div class="edit-field"><label>更新时间</label><input type="text" class="edit-input" data-field="updated_at" value="" placeholder="如: 2024-06-15"></div>'+
    '<div class="edit-field"><label>排序号 (数字越小越靠前)</label><input type="number" class="edit-input" data-field="sort_order" value="99" min="1" style="width:120px"></div>'+
    '<div class="edit-actions">'+
    '<button class="edit-btn edit-btn-save" onclick="saveResource(0)">添加</button>'+
    '<button class="edit-btn edit-btn-del" onclick="this.closest(\'.resource-edit-card\').remove()">取消</button>'+
    '</div>';
  container.insertBefore(card,container.firstChild);
  card.querySelector('[data-field=name]').focus();
}

function loadResources(){
  fetch('api.php?action=admin_get_resources').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    const container=document.getElementById('resourceGrid');
    container.innerHTML='';
    d.data.resources.forEach(res=>{
      const iconHtml=res.icon?'<img src="data/'+res.icon+'" alt="">':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
      const brokenCount=res.broken_ips?res.broken_ips.length:0;
      const brokenBadge=brokenCount>0?' <span style="background:rgba(229,62,62,.15);color:#e53e3e;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:4px;cursor:pointer" title="点击清除举报" onclick="event.stopPropagation();clearBroken('+res.id+')">'+brokenCount+'次举报 ✕</span>':'';
      const clickBadge=' <span style="background:rgba(66,153,225,.15);color:#3182ce;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:4px">'+(res.click_count||0)+'次点击</span>';
      container.innerHTML+='<div class="resource-edit-card" data-id="'+res.id+'">'+
        '<h3><span>#'+res.id+' '+esc(res.name)+'</span><span style="font-size:11px">'+clickBadge+brokenBadge+'</span><input type="color" class="edit-input" style="width:36px;height:28px;padding:0 2px" data-field="color" value="'+(res.color||'#4a90d9')+'"></h3>'+
        '<div class="icon-upload"><div class="icon-preview" id="iconPrev_'+res.id+'">'+iconHtml+'</div><button class="icon-upload-btn" onclick="uploadIcon('+res.id+')">上传图标</button><input type="file" id="iconFile_'+res.id+'" accept="image/*" style="display:none" onchange="doUploadIcon('+res.id+')"><input type="hidden" data-field="icon" value="'+esc(res.icon||'')+'"></div>'+
        '<div class="edit-field"><label>显示名称</label><input type="text" class="edit-input" data-field="name" value="'+esc(res.name)+'"></div>'+
        '<div class="edit-field"><label>链接</label><input type="text" class="edit-input" data-field="url" value="'+esc(res.url)+'"></div>'+
        '<div class="edit-field"><label>密码</label><input type="text" class="edit-input" data-field="password" value="'+esc(res.password||'')+'"></div>'+
        '<div class="edit-field"><label>QQ群1</label><input type="text" class="edit-input" data-field="qq_group_1" value="'+esc(res.qq_group_1||'')+'"></div>'+
        '<div class="edit-field"><label>QQ群2</label><input type="text" class="edit-input" data-field="qq_group_2" value="'+esc(res.qq_group_2||'')+'"></div>'+
        '<div class="edit-field"><label>卡片描述</label><input type="text" class="edit-input" data-field="description" value="'+esc(res.description||'')+'"></div>'+
        '<div class="edit-field"><label>分类</label><input type="text" class="edit-input" data-field="category" value="'+esc(res.category||'')+'" placeholder="如: FPS辅助、内核工具"></div>'+
        '<div class="edit-field"><label>归属分区</label><select class="edit-input" data-field="partition"><option value="game"'+(res.partition!=='other'?' selected':'')+'>游戏内核分区</option><option value="other"'+(res.partition==='other'?' selected':'')+'>其他分区</option></select></div>'+
        '<div class="edit-field"><label>版本号</label><input type="text" class="edit-input" data-field="version" value="'+esc(res.version||'')+'" placeholder="如: 1.2.3"></div>'+
        '<div class="edit-field"><label>更新时间</label><input type="text" class="edit-input" data-field="updated_at" value="'+esc(res.updated_at||'')+'" placeholder="如: 2024-06-15"></div>'+
        '<div class="edit-field"><label>排序号 (数字越小越靠前)</label><input type="number" class="edit-input" data-field="sort_order" value="'+(res.sort_order||res.id)+'" min="1" style="width:120px"></div>'+
        '<div class="edit-actions">'+
        '<button class="edit-btn edit-btn-save" onclick="saveResource('+res.id+')">保存</button>'+
        '<button class="edit-btn edit-btn-test" onclick="window.open(\''+esc(res.url)+'\',\'_blank\')">测试</button>'+
        '<button class="edit-btn edit-btn-del" onclick="delResource('+res.id+')">删除</button>'+
        '<button class="icon-upload-btn" style="margin-left:auto" onclick="moveResourceUp('+res.id+')">&#x25B2;</button>'+
        '<button class="icon-upload-btn" onclick="moveResourceDown('+res.id+')">&#x25BC;</button>'+
        '</div></div>';
    });
  });
}

function uploadIcon(id){document.getElementById('iconFile_'+id).click();}

function doUploadIcon(id){
  const file=document.getElementById('iconFile_'+id).files[0];
  if(!file)return;
  const fd=new FormData();fd.append('action','admin_upload_icon');fd.append('icon',file);
  document.getElementById('iconPrev_'+id).innerHTML='<span style="font-size:10px">上传中...</span>';
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      document.getElementById('iconPrev_'+id).innerHTML='<img src="data/'+d.data.filename+'" alt="">';
      const card=document.querySelector('.resource-edit-card[data-id="'+id+'"]');
      card.querySelector('[data-field=icon]').value=d.data.filename;
      showToast('图标上传成功');
    }else{showToast(d.message||'上传失败','error');}
  });
}

function saveResource(id){
  const card=document.querySelector('.resource-edit-card[data-id="'+id+'"]');
  const fd=new FormData();
  fd.append('action','admin_save_resource');fd.append('id',id);
  fd.append('name',card.querySelector('[data-field=name]').value);
  fd.append('url',card.querySelector('[data-field=url]').value);
  fd.append('password',card.querySelector('[data-field=password]').value);
  fd.append('qq_group_1',card.querySelector('[data-field=qq_group_1]').value);
  fd.append('qq_group_2',card.querySelector('[data-field=qq_group_2]').value);
  fd.append('description',card.querySelector('[data-field=description]').value);
  fd.append('category',card.querySelector('[data-field=category]').value);
  fd.append('partition',card.querySelector('[data-field=partition]').value);
  fd.append('color',card.querySelector('[data-field=color]').value);
  fd.append('icon',card.querySelector('[data-field=icon]').value);
  fd.append('sort_order',card.querySelector('[data-field=sort_order]').value);
  fd.append('version',card.querySelector('[data-field=version]').value);
  fd.append('updated_at',card.querySelector('[data-field=updated_at]').value);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    showToast(d.success?'保存成功':(d.message||'保存失败'),d.success?'':'error');
    if(d.success)loadResources();
  });
}

function delResource(id){
  confirm('确定删除该资源？',function(){
    const fd=new FormData();fd.append('action','admin_delete_resource');fd.append('id',id);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?'删除成功':(d.message||'删除失败'),d.success?'':'error');
      if(d.success)loadResources();
    });
  });
}

function sortByClicks(){
  confirm('确定按点击热度重新排序所有资源？',function(){
    const fd=new FormData();fd.append('action','admin_sort_by_clicks');
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?'排序完成':(d.message||'操作失败'),d.success?'':'error');
      if(d.success)loadResources();
    });
  });
}

function clearBroken(id){
  const fd=new FormData();fd.append('action','admin_clear_broken');fd.append('id',id);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success)loadResources();
    showToast(d.success?'已清除':(d.message||'操作失败'),d.success?'':'error');
  });
}

function loadBlacklist(){
  fetch('api.php?action=admin_blacklist_list').then(r=>r.json()).then(d=>{
    const tbody=document.querySelector('#blacklistTable tbody');
    if(!d.success){tbody.innerHTML='<tr><td colspan="5" class="empty-state">加载失败</td></tr>';return;}
    const list=d.data.blacklist||[];
    document.getElementById('blacklistCount').textContent='共 '+list.length+' 个IP被拉黑';
    if(list.length===0){tbody.innerHTML='<tr><td colspan="5" class="empty-state">暂无拉黑记录</td></tr>';} else {
    tbody.innerHTML=list.map(function(b){
      return '<tr>'+
        '<td><code style="color:#e53e3e;font-weight:600">'+esc(b.ip)+'</code></td>'+
        '<td>'+esc(b.ip_location||'-')+'</td>'+
        '<td>'+esc(b.reason||'手动拉黑')+'</td>'+
        '<td style="font-size:11px">'+esc(b.blocked_time||'-')+'</td>'+
        '<td><button class="table-btn table-btn-del" onclick="removeBlacklist(\''+esc(b.ip)+'\')">解除</button></td>'+
        '</tr>';
    }).join('');}
  });
  loadUnblockRequests();
}

function loadUnblockRequests(){
  fetch('api.php?action=admin_unblock_requests').then(r=>r.json()).then(d=>{
    const tbody=document.querySelector('#unblockRequestTable tbody');
    if(!d.success){tbody.innerHTML='<tr><td colspan="5" class="empty-state">加载失败</td></tr>';return;}
    const list=d.data.requests||[];
    var pending=list.filter(function(r){return r.status==='pending'}).length;
    document.getElementById('unblockBadge').textContent=pending>0?'('+pending+'条待处理)':'';
    if(list.length===0){tbody.innerHTML='<tr><td colspan="5" class="empty-state">暂无申请</td></tr>';return;}
    tbody.innerHTML=list.map(function(r){
      var statusBadge=r.status==='pending'?'<span class="badge badge-info">待审核</span>':
        r.status==='approved'?'<span class="badge badge-success">已批准</span>':
        '<span class="badge badge-danger">已拒绝</span>';
      var actions=r.status==='pending'?
        '<button class="table-btn table-btn-reply" onclick="approveUnblock('+r.id+')">批准</button>'+
        '<button class="table-btn table-btn-del" onclick="rejectUnblock('+r.id+')">拒绝</button>':'';
      return '<tr>'+
        '<td><code>'+esc(r.ip)+'</code></td>'+
        '<td>'+esc(r.ip_location||'-')+'</td>'+
        '<td style="font-size:11px">'+esc(r.time||'-')+'</td>'+
        '<td>'+statusBadge+'</td>'+
        '<td>'+actions+'</td>'+
        '</tr>';
    }).join('');
  });
}

function approveUnblock(id){
  confirm('确定批准该解除拉黑申请吗？',function(){
    const fd=new FormData();fd.append('action','admin_approve_unblock');fd.append('id',id);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?(d.message||'已批准'):(d.message||'操作失败'),d.success?'':'error');
      if(d.success){loadBlacklist();loadUnblockRequests();}
    });
  });
}

function rejectUnblock(id){
  confirm('确定拒绝该解除拉黑申请吗？',function(){
    const fd=new FormData();fd.append('action','admin_reject_unblock');fd.append('id',id);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?(d.message||'已拒绝'):(d.message||'操作失败'),d.success?'':'error');
      if(d.success)loadUnblockRequests();
    });
  });
}
function addBlacklist(){
  const ip=document.getElementById('blockIp').value.trim();
  const reason=document.getElementById('blockReason').value.trim();
  if(!ip){showToast('请输入IP地址','error');return;}
  const fd=new FormData();
  fd.append('action','admin_blacklist_add');fd.append('ip',ip);fd.append('reason',reason);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    showToast(d.success?(d.message||'拉黑成功'):(d.message||'操作失败'),d.success?'':'error');
    if(d.success){document.getElementById('blockIp').value='';document.getElementById('blockReason').value='';loadBlacklist();}
  });
}

function batchBlacklist(){
  const ips=document.getElementById('batchIps').value.trim();
  const reason=document.getElementById('batchReason').value.trim();
  if(!ips){showToast('请输入IP地址','error');return;}
  const fd=new FormData();
  fd.append('action','admin_blacklist_batch');fd.append('ips',ips);fd.append('reason',reason);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    showToast(d.success?(d.message||'操作完成'):(d.message||'操作失败'),d.success?'':'error');
    if(d.success){document.getElementById('batchIps').value='';loadBlacklist();}
  });
}

function removeBlacklist(ip){
  confirm('确定解除拉黑 '+ip+' 吗？',function(){
    const fd=new FormData();
    fd.append('action','admin_blacklist_remove');fd.append('ip',ip);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?(d.message||'已解除'):(d.message||'操作失败'),d.success?'':'error');
      if(d.success)loadBlacklist();
    });
  });
}

function loadVisitors(mode,btn){
  if(btn){document.querySelectorAll('#tab-visitors .mode-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active');}
  currentVisitorMode=mode;currentVisitorPage=1;
  _loadVisitorsPage(mode,1);
}
function _loadVisitorsPage(mode,page){
  fetch('api.php?action=admin_get_visitors&mode='+mode+'&page='+page+'&per_page=15').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    const tbody=document.querySelector('#visitorTable tbody');
    const thead=document.querySelector('#visitorTable thead tr');
    if(mode==='simple'){
      thead.innerHTML='<th>来访地址</th><th>系统</th><th>设备</th><th>浏览器</th><th>次数</th><th>首次</th><th>最近</th>';
      tbody.innerHTML='';
      if(!d.data.visitors||d.data.visitors.length===0){tbody.innerHTML='<tr><td colspan="7" class="empty-state">暂无记录</td></tr>';renderPagination('visitorPagination',d.data);return;}
      d.data.visitors.forEach(v=>{
        const locDisplay=v.location_display?v.location_display+' ('+v.ip+')':v.ip;
        const count=v.visit_count||1;
        tbody.innerHTML+='<tr class="visitor-row" data-ip="'+esc(v.ip)+'" style="cursor:pointer" onclick="toggleVisitorDetail(this)">'+
          '<td><span class="expand-icon" style="display:inline-block;width:16px;font-size:10px;transition:transform .2s">+</span> '+esc(locDisplay)+'</td>'+
          '<td>'+esc(v.os||'-')+'</td>'+
          '<td>'+esc(v.device||'-')+'</td>'+
          '<td>'+esc(v.browser||'-')+'</td>'+
          '<td><span class="badge badge-info">'+count+'次</span></td>'+
          '<td style="font-size:11px">'+esc(v.first_time||'-')+'</td>'+
          '<td style="font-size:11px">'+esc(v.last_time||'-')+'</td>'+
          '</tr><tr class="visitor-detail" style="display:none"><td colspan="7" style="padding:0"><div style="padding:10px 15px;background:rgba(102,126,234,.03);font-size:12px">'+
          (v.details||[]).map(d=>'<div style="padding:6px 0;border-bottom:1px solid rgba(0,0,0,.04);display:flex;flex-wrap:wrap;gap:10px"><span>'+esc(d.time)+'</span><span style="color:#a0aec0">'+esc(d.referer||'直接访问')+'</span><span style="color:#a0aec0">ISP:'+esc(d.isp||'-')+'</span><span style="color:#a0aec0">'+esc(d.zip||'')+' '+esc(d.lat||'')+','+esc(d.lon||'')+'</span></div>').join('')+
          '</div></td></tr>';
      });
    }else{
      thead.innerHTML='<th>来访地址</th><th>ISP</th><th>系统</th><th>浏览器</th><th>设备</th><th>来源</th><th>经纬度</th><th>时区</th><th>时间</th>';
      tbody.innerHTML='';
      if(!d.data.visitors||d.data.visitors.length===0){tbody.innerHTML='<tr><td colspan="9" class="empty-state">暂无记录</td></tr>';renderPagination('visitorPagination',d.data);return;}
      d.data.visitors.forEach(v=>{
        const locDisplay=v.location_display?v.location_display+' ('+v.ip+')':v.ip;
        tbody.innerHTML+='<tr><td>'+esc(locDisplay)+'</td><td>'+esc(v.isp||'-')+'</td><td>'+esc(v.os||'-')+'</td><td>'+esc(v.browser||'-')+'</td><td>'+esc(v.device||'-')+'</td><td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(v.referer||'')+'">'+esc(v.referer||'-')+'</td><td>'+esc((v.lat||'')+','+(v.lon||''))+'</td><td>'+esc(v.timezone||'-')+'</td><td style="font-size:11px">'+esc(v.time)+'</td></tr>';
      });
    }
    renderPagination('visitorPagination',d.data);
  });
}

function toggleVisitorDetail(row){
  const icon=row.querySelector('.expand-icon');
  const detail=row.nextElementSibling;
  if(detail.style.display==='none'){
    detail.style.display='table-row';
    icon.textContent='-';
    icon.style.transform='rotate(0deg)';
  }else{
    detail.style.display='none';
    icon.textContent='+';
  }
}

function loadMessages(page){
  page=page||1;currentMsgPage=page;
  fetch('api.php?action=admin_get_messages&page='+page+'&per_page=15').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    const tbody=document.querySelector('#messageTable tbody');
    tbody.innerHTML='';
    if(!d.data.messages||d.data.messages.length===0){tbody.innerHTML='<tr><td colspan="8" class="empty-state">暂无留言</td></tr>';renderPagination('msgPagination',d.data);return;}
    d.data.messages.forEach(m=>{
      tbody.innerHTML+='<tr><td>'+m.id+'</td><td>'+esc(m.name)+'</td><td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(m.content)+'</td><td>'+(m.reply_to?'#'+m.reply_to:'-')+'</td><td>'+(m.likes||0)+'</td><td>'+(m.ip_location?esc(m.ip_location)+' ('+m.ip+')':esc(m.ip||'-'))+'</td><td style="font-size:11px">'+esc(m.time)+'</td><td class="table-actions"><button class="table-btn table-btn-reply" onclick="replyMsg('+m.id+')">回复</button><button class="table-btn table-btn-del" onclick="delMsg('+m.id+')">删除</button></td></tr>';
    });
    renderPagination('msgPagination',d.data);
  });
}

function loadUrges(page){
  page=page||1;currentUrgePage=page;
  fetch('api.php?action=admin_get_urges&page='+page+'&per_page=15').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    const tbody=document.querySelector('#urgeTable tbody');
    tbody.innerHTML='';
    if(!d.data.urges||d.data.urges.length===0){tbody.innerHTML='<tr><td colspan="7" class="empty-state">暂无催更</td></tr>';renderPagination('urgePagination',d.data);return;}
    d.data.urges.forEach(u=>{
      var replies=u.replies||[];
      if(!replies.length&&u.reply)replies=[{name:'管理员',content:u.reply,time:u.reply_time||''}];
      var replyHtml='';
      if(replies.length>0){
        replyHtml='<div style="font-size:11px">';
        replies.forEach(function(r){
          replyHtml+='<span class="badge '+(r.name==='管理员'?'badge-reply':'badge-info')+'">'+(r.name==='管理员'?'管理员':'回复')+': '+esc(r.content)+' <span style="font-size:10px;opacity:0.7">'+esc(r.time||'')+'</span></span> ';
        });
        replyHtml+='</div>';
      }else{
        replyHtml='<span style="color:#a0aec0">暂无回复</span>';
      }
      tbody.innerHTML+='<tr><td>'+u.id+'</td><td>'+esc(u.resource_name)+'</td><td>'+esc(u.content)+'</td><td>'+replyHtml+'</td><td>'+(u.ip_location?esc(u.ip_location)+' ('+u.ip+')':esc(u.ip||'-'))+'</td><td style="font-size:11px">'+esc(u.time)+'</td><td class="table-actions"><button class="table-btn table-btn-reply" onclick="replyUrge('+u.id+')">回复</button><button class="table-btn table-btn-del" onclick="delUrge('+u.id+')">删除</button></td></tr>';
    });
    renderPagination('urgePagination',d.data);
  });
}

function replyMsg(id){
  openReply('回复留言 #'+id,function(content){
    const fd=new FormData();fd.append('action','admin_reply_message');fd.append('reply_to',id);fd.append('content',content);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?'回复成功':(d.message||'回复失败'),d.success?'':'error');
      if(d.success)loadMessages(currentMsgPage);
    });
  });
}

function replyUrge(id){
  openReply('回复催更 #'+id,function(content){
    const fd=new FormData();fd.append('action','admin_reply_urge');fd.append('id',id);fd.append('reply',content);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?'回复成功':(d.message||'回复失败'),d.success?'':'error');
      if(d.success)loadUrges(currentUrgePage);
    });
  });
}

function delMsg(id){
  confirm('确定删除该留言？',function(){
    const fd=new FormData();fd.append('action','admin_delete_message');fd.append('id',id);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?'删除成功':(d.message||'删除失败'),d.success?'':'error');
      if(d.success)loadMessages(currentMsgPage);
    });
  });
}

function delUrge(id){
  confirm('确定删除该催更？',function(){
    const fd=new FormData();fd.append('action','admin_delete_urge');fd.append('id',id);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?'删除成功':(d.message||'删除失败'),d.success?'':'error');
      if(d.success)loadUrges(currentUrgePage);
    });
  });
}

function loadLogs(type,btn,page){
  if(btn){document.querySelectorAll('#tab-logs .mode-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active');}
  page=page||1;
  currentLogType=type;currentLogPage=page;
  const url=type==='login'?'api.php?action=admin_get_login_logs&page='+page+'&per_page=15':'api.php?action=admin_get_logs&page='+page+'&per_page=15';
  const thead=document.querySelector('#logTable thead tr');
  const tbody=document.querySelector('#logTable tbody');
  if(type==='login'){thead.innerHTML='<th>状态</th><th>来源</th><th>ISP</th><th>输错次数</th><th>浏览器</th><th>系统</th><th>设备</th><th>时间</th>';}
  else{thead.innerHTML='<th>操作</th><th>详情</th><th>来源</th><th>浏览器</th><th>系统</th><th>时间</th>';}
  tbody.innerHTML='<tr><td colspan="8" class="empty-state">加载中...</td></tr>';
  fetch(url).then(r=>r.json()).then(d=>{
    if(!d.success){tbody.innerHTML='<tr><td colspan="8" class="empty-state">加载失败,请重试</td></tr>';return;}
    if(!d.data.logs||d.data.logs.length===0){tbody.innerHTML='<tr><td colspan="8" class="empty-state">暂无记录</td></tr>';renderPagination('logPagination',d.data);return;}
    tbody.innerHTML='';
    d.data.logs.forEach(l=>{
      if(type==='login'){
        const locDisplay=l.location_display||l.ip;
        const failCount=l.fail_count||0;
        tbody.innerHTML+='<tr><td><span class="badge '+(l.status==='success'?'badge-success':'badge-danger')+'">'+(l.status==='success'?'成功':'失败')+'</span></td><td>'+esc(locDisplay)+'</td><td>'+esc(l.isp||'-')+'</td><td><span class="badge '+(failCount>0?'badge-danger':'badge-info')+'">'+failCount+'次</span></td><td>'+esc(l.browser)+'</td><td>'+esc(l.os)+'</td><td>'+esc(l.device)+'</td><td>'+esc(l.time)+'</td></tr>';
      }else{
        tbody.innerHTML+='<tr><td><span class="badge badge-info">'+esc(l.action)+'</span></td><td>'+esc(l.detail)+'</td><td>'+(l.ip_location?esc(l.ip_location)+' ('+l.ip+')':esc(l.ip||'-'))+'</td><td>'+esc(l.browser)+'</td><td>'+esc(l.os)+'</td><td>'+esc(l.time)+'</td></tr>';
      }
    });
    renderPagination('logPagination',d.data);
  }).catch(function(e){
    tbody.innerHTML='<tr><td colspan="8" class="empty-state">加载失败,请检查网络</td></tr>';
  });
}

function loadSettings(){
  fetch('api.php?action=admin_get_settings').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    document.getElementById('popupEnabled').checked=d.data.popup_enabled||false;
    document.getElementById('popupTitle').value=d.data.popup_title||'';
    document.getElementById('popupContent').value=d.data.popup_content||'';
    document.getElementById('qqGroupPwd').value=d.data.qq_group_password||'';
    document.getElementById('contactQQ').value=d.data.contact_qq||'';
    document.getElementById('adminPwd').value='';
    document.getElementById('btnJumpText').value=d.data.btn_jump_text||'蓝奏云下载';
    document.getElementById('btnQqText').value=d.data.btn_qq_text||'qq群(文件在q群文件)';
    document.getElementById('btnUrgeText').value=d.data.btn_urge_text||'催更';
    document.getElementById('contactText').value=d.data.contact_text||'如有疑问请联系QQ';
    document.getElementById('sectionGuestbook').value=d.data.section_guestbook||'留言板';
    document.getElementById('sectionUrgeWall').value=d.data.section_urge_wall||'催更墙';
    document.getElementById('qqHintText').value=d.data.qq_hint_text||'输入密码查看内容';
    document.getElementById('shopName').value=d.data.shop_name||'资源导航';
    document.getElementById('shopSub').value=d.data.shop_sub||'';
    document.getElementById('siteTitle').value=d.data.site_title||'资源导航';
    document.getElementById('siteFont').value=d.data.site_font||'';
    document.getElementById('darkMode').value=d.data.dark_mode||'auto';
    document.getElementById('sctKey').value = d.data.sct_key || d.data.wechat_key || '';
    document.getElementById('wxpusherToken').value=d.data.wxpusher_token||'';
    document.getElementById('wxpusherUid').value=d.data.wxpusher_uid||'';
    document.getElementById('barkKey').value=d.data.bark_key||'';
    document.getElementById('pushplusToken').value=d.data.pushplus_token||'';
    document.getElementById('smtpHost').value=d.data.smtp_host||'smtp.qq.com';
    document.getElementById('smtpPort').value=d.data.smtp_port||'465';
    document.getElementById('smtpUser').value=d.data.smtp_user||'';
    document.getElementById('smtpPass').value=d.data.smtp_pass||'';
    document.getElementById('carouselEnabled').checked=d.data.carousel_enabled||false;
    document.getElementById('carouselSpeed').value=d.data.carousel_speed||4000;
    document.getElementById('carouselHeightDesktop').value=d.data.carousel_height_desktop||160;
    document.getElementById('carouselHeightMobile').value=d.data.carousel_height_mobile||110;
    document.getElementById('rateLimitEnabled').checked=d.data.rate_limit_enabled!==false;
    document.getElementById('rateLimitMsg').value=d.data.rate_limit_msg||3;
    document.getElementById('rateLimitUrge').value=d.data.rate_limit_urge||3;
    document.getElementById('rateLimitLike').value=d.data.rate_limit_like||10;
    document.getElementById('footerText').value=d.data.footer_text||'';
    document.getElementById('passwordProtection').checked=d.data.password_protection!==false;
    document.getElementById('autoBlockEnabled').checked=d.data.auto_block_enabled!==false;
    document.getElementById('autoBlockWindow').value=d.data.auto_block_window||60;
    document.getElementById('autoBlockThreshold').value=d.data.auto_block_threshold||30;
    document.getElementById('autoBlockWhitelist').value=d.data.auto_block_whitelist||'山东,威海';
    document.getElementById('autoBlockIpWhitelist').value=d.data.auto_block_ip_whitelist||'';
    document.getElementById('viewsOffset').value=d.data.views_offset||'0';
    document.getElementById('visitorsOffset').value=d.data.visitors_offset||'0';
    document.getElementById('todayVisitorsOffset').value=d.data.today_visitors_offset||'0';
    if(d.data.shop_icon){document.getElementById('shopIconPrev').innerHTML='<img src="data/'+esc(d.data.shop_icon)+'" alt="">';document.getElementById('shopIcon').value=d.data.shop_icon;}
    renderCarouselSlides(d.data.carousel||[]);
    renderPartitions(d.data.partitions||[]);
  });
}

function saveSettings(){
  const fd=new FormData();fd.append('action','admin_save_settings');
  fd.append('popup_enabled',document.getElementById('popupEnabled').checked);
  fd.append('popup_title',document.getElementById('popupTitle').value);
  fd.append('popup_content',document.getElementById('popupContent').value);
  fd.append('qq_group_password',document.getElementById('qqGroupPwd').value);
  fd.append('contact_qq',document.getElementById('contactQQ').value);
  fd.append('admin_password',document.getElementById('adminPwd').value);
  fd.append('btn_jump_text',document.getElementById('btnJumpText').value);
  fd.append('btn_qq_text',document.getElementById('btnQqText').value);
  fd.append('btn_urge_text',document.getElementById('btnUrgeText').value);
  fd.append('contact_text',document.getElementById('contactText').value);
  fd.append('section_guestbook',document.getElementById('sectionGuestbook').value);
  fd.append('section_urge_wall',document.getElementById('sectionUrgeWall').value);
  fd.append('qq_hint_text',document.getElementById('qqHintText').value);
  fd.append('shop_name',document.getElementById('shopName').value);
  fd.append('shop_sub',document.getElementById('shopSub').value);
  fd.append('site_title',document.getElementById('siteTitle').value);
  fd.append('site_font',document.getElementById('siteFont').value);
  fd.append('dark_mode',document.getElementById('darkMode').value);
  fd.append('sct_key',document.getElementById('sctKey').value||'');
  fd.append('wxpusher_token',document.getElementById('wxpusherToken').value||'');
  fd.append('wxpusher_uid',document.getElementById('wxpusherUid').value||'');
  fd.append('bark_key',document.getElementById('barkKey').value||'');
  fd.append('pushplus_token',document.getElementById('pushplusToken').value||'');
  fd.append('smtp_host',document.getElementById('smtpHost').value);
  fd.append('smtp_port',document.getElementById('smtpPort').value);
  fd.append('smtp_user',document.getElementById('smtpUser').value);
  var smtpPass=document.getElementById('smtpPass').value;
  if(smtpPass)fd.append('smtp_pass',smtpPass);
  fd.append('shop_icon',(document.getElementById('shopIcon')||{}).value||'');
  fd.append('carousel_enabled',document.getElementById('carouselEnabled').checked);
  fd.append('carousel_speed',document.getElementById('carouselSpeed').value);
  fd.append('carousel_height_desktop',document.getElementById('carouselHeightDesktop').value);
  fd.append('carousel_height_mobile',document.getElementById('carouselHeightMobile').value);
  fd.append('rate_limit_enabled',document.getElementById('rateLimitEnabled').checked);
  fd.append('rate_limit_msg',document.getElementById('rateLimitMsg').value);
  fd.append('rate_limit_urge',document.getElementById('rateLimitUrge').value);
  fd.append('rate_limit_like',document.getElementById('rateLimitLike').value);
  fd.append('footer_text',document.getElementById('footerText').value);
  fd.append('password_protection',document.getElementById('passwordProtection').checked);
  fd.append('auto_block_enabled',document.getElementById('autoBlockEnabled').checked);
  fd.append('auto_block_window',document.getElementById('autoBlockWindow').value);
  fd.append('auto_block_threshold',document.getElementById('autoBlockThreshold').value);
  fd.append('auto_block_whitelist',document.getElementById('autoBlockWhitelist').value);
  fd.append('auto_block_ip_whitelist',document.getElementById('autoBlockIpWhitelist').value);
  fd.append('views_offset',document.getElementById('viewsOffset').value||'0');
  fd.append('visitors_offset',document.getElementById('visitorsOffset').value||'0');
  fd.append('today_visitors_offset',document.getElementById('todayVisitorsOffset').value||'0');
  // carousel slides
  var slides=document.querySelectorAll('.carousel-slide-row');
  slides.forEach(function(s,i){
    fd.append('slide_title['+i+']',(s.querySelector('.slide-title')||{}).value||'');
    fd.append('slide_url['+i+']',(s.querySelector('.slide-url')||{}).value||'');
    fd.append('slide_img['+i+']',(s.querySelector('.slide-img')||{}).value||'');
  });
  // partitions
  var pRows=document.querySelectorAll('.partition-row');
  pRows.forEach(function(row,i){
    var pid=row.querySelector('.partition-id');
    var pname=row.querySelector('.partition-name');
    if(pid&&pname){
      fd.append('partition_id['+i+']',pid.value||'');
      fd.append('partition_name['+i+']',pname.value||'');
    }
  });
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    showToast(d.success?'保存成功':(d.message||'保存失败'),d.success?'':'error');
    if(d.success){document.getElementById('adminPwd').value='';loadSettings();}
  });
}

function copyText(text){
  if(!text){showToast('没有可复制的内容','error');return;}
  if(navigator.clipboard){navigator.clipboard.writeText(text).then(()=>showToast('已复制'));}else{const ta=document.createElement('textarea');ta.value=text;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);showToast('已复制');}
}

function showToast(msg,type){
  const t=document.getElementById('toast');t.textContent=msg;t.className='toast show';
  if(type==='error')t.classList.add('error');
  setTimeout(()=>t.classList.remove('show'),2500);
}

function moveResourceUp(id){
  var fd=new FormData();fd.append('action','admin_get_resources');
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    var rs=d.data.resources||[]; var idx=rs.findIndex(function(r){return r.id===id});
    if(idx<=0)return; var tmp=rs[idx];rs[idx]=rs[idx-1];rs[idx-1]=tmp;
    var ids=rs.map(function(r){return r.id}).join(',');
    var fd2=new FormData();fd2.append('action','admin_sort_resources');fd2.append('ids',ids);
    fetch('api.php',{method:'POST',body:fd2}).then(function(){loadResources()});
  });
}
function moveResourceDown(id){
  var fd=new FormData();fd.append('action','admin_get_resources');
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    var rs=d.data.resources||[]; var idx=rs.findIndex(function(r){return r.id===id});
    if(idx<0||idx>=rs.length-1)return; var tmp=rs[idx];rs[idx]=rs[idx+1];rs[idx+1]=tmp;
    var ids=rs.map(function(r){return r.id}).join(',');
    var fd2=new FormData();fd2.append('action','admin_sort_resources');fd2.append('ids',ids);
    fetch('api.php',{method:'POST',body:fd2}).then(function(){loadResources()});
  });
}

function doUploadShopIcon(){
  var file=document.getElementById('shopIconFile').files[0];if(!file)return;
  var fd=new FormData();fd.append('action','admin_upload_icon');fd.append('icon',file);
  document.getElementById('shopIconPrev').innerHTML='<span style="font-size:10px">上传中...</span>';
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){document.getElementById('shopIconPrev').innerHTML='<img src="data/'+d.data.filename+'" alt="" style="max-width:100%;max-height:100%;object-fit:contain">';document.getElementById('shopIcon').value=d.data.filename;showToast('上传成功');}
    else{showToast(d.message||'上传失败','error')}
  }).catch(function(){showToast('上传失败,请检查网络','error')});
}

function renderCarouselSlides(slides){
  var el=document.getElementById('carouselSlides');
  if(!slides.length)slides=[{}];
  el.innerHTML=slides.map(function(s,i){return'<div class="carousel-slide-row" style="margin-bottom:8px;padding:8px;border-radius:8px;background:rgba(0,0,0,.02)"><div class="icon-upload">'+
    '<div class="icon-preview">'+(s.img?'<img src="data/'+esc(s.img)+'" alt="">':'')+'</div>'+
    '<button class="icon-upload-btn" onclick="var row=this.closest(\'.carousel-slide-row\');var f=document.createElement(\'input\');f.type=\'file\';f.accept=\'image/*\';f.onchange=function(){if(this.files[0])uploadCarouselImg(row,this.files[0])};f.click()">上传</button>'+
    '<input type="hidden" class="slide-img" value="'+esc(s.img||'')+'">'+
    '</div><div class="edit-field"><label>标题</label><input class="edit-input slide-title" value="'+esc(s.title||'')+'"></div>'+
    '<div class="edit-field"><label>链接(选填)</label><input class="edit-input slide-url" value="'+esc(s.url||'')+'"></div>'+
    '<button class="icon-upload-btn" onclick="this.parentElement.remove()" style="color:#e53e3e">删除此项</button></div>'}).join('');
}

function uploadCarouselImg(row,file){
  var fd=new FormData();fd.append('action','admin_upload_carousel');fd.append('image',file);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      row.querySelector('.slide-img').value=d.data.filename;
      row.querySelector('.icon-preview').innerHTML='<img src="data/'+d.data.filename+'" alt="">';
      showToast('上传成功');
    }else{showToast(d.message||'上传失败','error')}
  });
}

function addCarouselSlide(){
  var el=document.getElementById('carouselSlides');
  var d=document.createElement('div');d.className='carousel-slide-row';d.style.cssText='margin-bottom:8px;padding:8px;border-radius:8px;background:rgba(0,0,0,.02)';
  d.innerHTML='<div class="icon-upload"><div class="icon-preview"></div><button class="icon-upload-btn" onclick="var row=this.closest(\'.carousel-slide-row\');var f=document.createElement(\'input\');f.type=\'file\';f.accept=\'image/*\';f.onchange=function(){if(this.files[0])uploadCarouselImg(row,this.files[0])};f.click()">上传</button><input type="hidden" class="slide-img" value=""></div><div class="edit-field"><label>标题</label><input class="edit-input slide-title"></div><div class="edit-field"><label>链接(选填)</label><input class="edit-input slide-url"></div><button class="icon-upload-btn" onclick="this.parentElement.remove()" style="color:#e53e3e">删除此项</button>';
  el.appendChild(d);
}

function renderPartitions(partitions){
  var el=document.getElementById('partitionList');
  if(!partitions||!partitions.length)partitions=[{id:'game',name:'游戏内核分区'},{id:'other',name:'其他分区'}];
  el.innerHTML=partitions.map(function(p,i){return'<div class="partition-row" style="margin-bottom:8px;padding:8px;border-radius:8px;background:rgba(0,0,0,.02)"><div style="display:flex;gap:8px;align-items:center"><div class="edit-field" style="flex:1"><label>分区ID</label><input class="edit-input partition-id" value="'+esc(p.id)+'" placeholder="如: game"></div><div class="edit-field" style="flex:1"><label>分区名称</label><input class="edit-input partition-name" value="'+esc(p.name)+'" placeholder="如: 游戏内核分区"></div><button class="icon-upload-btn" onclick="this.parentElement.parentElement.remove()" style="color:#e53e3e;margin-top:auto" '+(i<2?'disabled':'')+'>删除</button></div></div>'}).join('');
}

function addPartition(){
  var el=document.getElementById('partitionList');
  var d=document.createElement('div');d.className='partition-row';d.style.cssText='margin-bottom:8px;padding:8px;border-radius:8px;background:rgba(0,0,0,.02)';
  d.innerHTML='<div style="display:flex;gap:8px;align-items:center"><div class="edit-field" style="flex:1"><label>分区ID</label><input class="edit-input partition-id" placeholder="如: game"></div><div class="edit-field" style="flex:1"><label>分区名称</label><input class="edit-input partition-name" placeholder="如: 游戏内核分区"></div><button class="icon-upload-btn" onclick="this.parentElement.parentElement.remove()" style="color:#e53e3e;margin-top:auto">删除</button></div>';
  el.appendChild(d);
}

function esc(t){const d=document.createElement('div');d.textContent=t||'';return d.innerHTML;}

function renderPagination(containerId,data){
  var el=document.getElementById(containerId);
  if(!el)return;
  var total=data.total||0,totalPages=data.total_pages||1,page=data.page||1;
  if(totalPages<=1){el.innerHTML='';return;}
  var html='<span class="page-info">共 '+total+' 条 / '+totalPages+' 页</span>';
  html+='<button '+(page<=1?'disabled':'')+' onclick="_goPage(\''+containerId+'\','+(page-1)+')">&laquo;</button>';
  var start=Math.max(1,page-2),end=Math.min(totalPages,page+2);
  for(var i=start;i<=end;i++){
    html+='<button class="'+(i===page?'active':'')+'" onclick="_goPage(\''+containerId+'\','+i+')">'+i+'</button>';
  }
  html+='<button '+(page>=totalPages?'disabled':'')+' onclick="_goPage(\''+containerId+'\','+(page+1)+')">&raquo;</button>';
  el.innerHTML=html;
}

function _goPage(containerId,p){
  if(containerId==='visitorPagination')_loadVisitorsPage(currentVisitorMode,p);
  else if(containerId==='msgPagination')loadMessages(p);
  else if(containerId==='urgePagination')loadUrges(p);
  else if(containerId==='logPagination')loadLogs(currentLogType,null,p);
}

function confirm(title,cb){confirmCallback=cb;document.getElementById('confirmTitle').textContent=title;document.getElementById('confirmModal').classList.add('active');}
function closeConfirm(){document.getElementById('confirmModal').classList.remove('active');confirmCallback=null;}
function execConfirm(){document.getElementById('confirmModal').classList.remove('active');if(confirmCallback)confirmCallback();confirmCallback=null;}

function openReply(title,cb){replyCallback=cb;document.getElementById('replyTitle').textContent=title;document.getElementById('replyInput').value='';document.getElementById('replyModal').classList.add('active');setTimeout(()=>document.getElementById('replyInput').focus(),200);}
function closeModal(id){document.getElementById(id).classList.remove('active');replyCallback=null;}
function execReply(){const content=document.getElementById('replyInput').value.trim();if(!content){showToast('请输入内容','error');return;}document.getElementById('replyModal').classList.remove('active');if(replyCallback)replyCallback(content);replyCallback=null;}

function clearData(type){
  var names={visitors:'访客记录',logs:'操作日志',login_logs:'登录记录',messages:'留言',urges:'催更'};
  confirm('确定清空所有'+names[type]+'？此操作不可恢复！',function(){
    var fd=new FormData();fd.append('action','admin_clear_'+type);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.success?'已清空':(d.message||'操作失败'),d.success?'':'error');
      if(d.success){
        if(type==='visitors')loadVisitors('simple');
        if(type==='messages')loadMessages();
        if(type==='urges')loadUrges();
        if(type==='logs'||type==='login_logs')loadLogs(currentLogType);
      }
    });
  });
}

document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',function(e){if(e.target===this){this.classList.remove('active');replyCallback=confirmCallback=null;}});});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.modal-overlay.active').forEach(m=>{m.classList.remove('active');replyCallback=confirmCallback=null;});}});

<?php if ($is_logged_in): ?>
document.addEventListener('DOMContentLoaded',function(){loadStats();});
<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</body>
</html>
<?php endif; ?>
