<?php
require_once 'functions.php';
if (!rn_is_db_configured() && file_exists(__DIR__ . '/setup.php')) {
    header('Location: setup.php');
    exit;
}
if (rn_is_ip_blocked(rn_get_client_ip())) {
    $ip = rn_get_client_ip();
    $loc = rn_get_ip_location_display($ip);
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:#1a1a2e;color:#e0e0e0;margin:0;flex-direction:column;padding:20px}
    .card{background:#16213e;border-radius:20px;padding:40px 30px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.4)}
    h1{font-size:64px;background:linear-gradient(135deg,#667eea,#e53e3e);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px}
    .subtitle{color:#718096;font-size:14px;margin-bottom:24px}
    .info{background:rgba(102,126,234,0.08);border-radius:12px;padding:16px;margin-bottom:20px;font-size:13px;color:#a0aec0;text-align:left;line-height:1.8}
    .info code{color:#667eea;background:rgba(102,126,234,0.15);padding:2px 8px;border-radius:4px;font-size:12px}
    .btn{display:block;width:100%;padding:14px;border-radius:12px;border:none;font-size:15px;font-weight:600;cursor:pointer;transition:all .3s;margin-bottom:10px}
    .btn-apply{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:0 4px 15px rgba(102,126,234,0.4)}
    .btn-apply:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(102,126,234,0.5)}
    .btn-apply:disabled{opacity:.5;cursor:not-allowed;transform:none}
    .success-msg{color:#48bb78;font-size:13px;margin-top:8px;display:none}
    .error-msg{color:#e53e3e;font-size:13px;margin-top:8px;display:none}
</style></head><body>
<div class="card">
<h1>403</h1>
<p class="subtitle">您的IP已被限制访问此站点</p>
<div class="info">
    <div>IP: <code><?php echo htmlspecialchars($ip); ?></code></div>
    <div>地区: <?php echo htmlspecialchars($loc); ?></div>
    <div>如果您是正常访客，可以申请解除拉黑</div>
</div>
<button class="btn btn-apply" id="applyBtn" onclick="applyUnblock()">申请解除拉黑</button>
<p class="success-msg" id="applySuccess">申请已提交，请等待管理员审核</p>
<p class="error-msg" id="applyError"></p>
</div>
<script>
function applyUnblock(){
    var btn=document.getElementById('applyBtn');
    btn.disabled=true;btn.textContent='提交中...';
    var fd=new FormData();fd.append('action','submit_unblock_request');
    fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success){
            document.getElementById('applySuccess').style.display='block';
            document.getElementById('applyError').style.display='none';
            btn.style.display='none';
        }else{
            document.getElementById('applyError').textContent=d.message||'申请失败';
            document.getElementById('applyError').style.display='block';
            btn.disabled=false;btn.textContent='申请解除拉黑';
        }
    }).catch(function(){
        btn.disabled=false;btn.textContent='申请解除拉黑';
    });
}
</script>
</body></html><?php
    exit;
}
rn_record_visitor();
$config = rn_get_config();
$resources = rn_get_resources();
usort($resources, function($a, $b) {
    $ao = $a['sort_order'] ?? 999; $bo = $b['sort_order'] ?? 999;
    return $ao - $bo;
});
$contact_qq = $config['contact_qq'] ?? '';
$site_font = $config['site_font'] ?? '';
$font_family = $site_font ? "'$site_font', " : '';
$shop_icon = $config['shop_icon'] ?? '';
$shop_name = $config['shop_name'] ?? '资源导航';
$shop_sub = $config['shop_sub'] ?? '';
$carousel_speed = intval($config['carousel_speed'] ?? 4000);
$carousel_height_desktop = intval($config['carousel_height_desktop'] ?? 160);
$carousel_height_mobile = intval($config['carousel_height_mobile'] ?? 110);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($config['site_title'] ?? '资源导航'); ?></title>
<?php if($site_font): ?><link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($site_font); ?>&display=swap" rel="stylesheet"><?php endif; ?>
<style>
html{overflow-x:hidden;max-width:100vw;scroll-behavior:smooth}
*{margin:0;padding:0;box-sizing:border-box}
button:focus,button:focus-visible,a:focus,a:focus-visible,input:focus,input:focus-visible,textarea:focus,textarea:focus-visible,select:focus,select:focus-visible,[tabindex]:focus,[tabindex]:focus-visible{outline:none!important;box-shadow:none!important;-webkit-tap-highlight-color:transparent}
*{-webkit-tap-highlight-color:transparent}
button::-moz-focus-inner,input::-moz-focus-inner{border:0}
input,textarea,button,select{font-family:inherit}
body{font-family:<?php echo $font_family; ?>-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif;background:#e8ecf1;color:#4a5568;min-height:100vh;padding-bottom:60px;transition:background .2s,color .2s;overflow-x:hidden;max-width:100vw;-webkit-font-smoothing:antialiased}
body h1,body h2,body h3,body h4,body h5,body h6,body .card-name,body .modal-title span,body .stat-value,body .stat-label,body .shop-brand-name,body .shop-brand-sub{font-family:inherit;font-weight:700}
body.dark{background:#1a1a2e;color:#a0aec0}
body.dark .neu-card,body.dark .fold-header,body.dark .msg-card,body.dark .urge-card,body.dark .modal-box{background:#16213e}
body.dark .neu-card{box-shadow:4px 4px 12px rgba(0,0,0,0.4),-4px -4px 12px rgba(255,255,255,0.05)}
body.dark .card-name,body.dark .msg-name,body.dark .modal-title span{color:#e0e0e0}
body.dark .card-description,body.dark .card-contact,body.dark .urge-resource{color:#7b8cff}
body.dark .msg-content,body.dark .urge-content,body.dark .modal-body{color:#aaa}
body.dark .msg-time,body.dark .urge-time,body.dark .empty-state{color:#666}
body.dark .search-input{background:#16213e;color:#e0e0e0;box-shadow:inset 3px 3px 6px rgba(0,0,0,0.4),inset -3px -3px 6px rgba(255,255,255,0.05)}
body.dark .search-input::placeholder{color:#555}
body.dark .modal-input,body.dark .modal-textarea{background:#1a1a2e;color:#e0e0e0;box-shadow:inset 3px 3px 6px rgba(0,0,0,0.4),inset -3px -3px 6px rgba(255,255,255,0.05)}
body.dark .msg-like-btn,body.dark .msg-reply-btn,body.dark .card-copy-btn,body.dark .fold-toggle,body.dark .modal-close{background:#1a1a2e;color:#888;box-shadow:3px 3px 6px rgba(0,0,0,0.3),-3px -3px 6px rgba(255,255,255,0.03)}
body.dark .urge-phrase{background:#1a1a2e;color:#aaa;box-shadow:3px 3px 6px rgba(0,0,0,0.3),-3px -3px 6px rgba(255,255,255,0.03)}
body.dark .top-bar,.dark .header{background:#16213e}
body.dark .top-bar.scrolled{background:rgba(22,33,62,0.9)}
body.dark .footer-stats{color:#666}
body.dark .fold-count{color:#fff}
body.dark .card-icon-wrap{background:#1a1a2e;box-shadow:inset 3px 3px 6px rgba(0,0,0,0.3),inset -3px -3px 6px rgba(255,255,255,0.05)}
body.dark .fold-header{box-shadow:4px 4px 12px rgba(0,0,0,0.4),-4px -4px 12px rgba(255,255,255,0.05)}
body.dark .msg-card{box-shadow:3px 3px 8px rgba(0,0,0,0.3),-3px -3px 8px rgba(255,255,255,0.03)}
body.dark .urge-card{box-shadow:3px 3px 8px rgba(0,0,0,0.3),-3px -3px 8px rgba(255,255,255,0.03)}
body.dark .qq-hint{background:rgba(255,149,0,0.06);color:#ff9500}
body.dark .card-copy-btn{background:#1a1a2e}
body.dark .top-bar{box-shadow:0 4px 12px rgba(0,0,0,0.15)}

.header{background:#e8ecf1;padding:0 20px;text-align:center;position:sticky;top:0;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.05)}
.header h1{font-size:24px;font-weight:700;color:#2d3748;letter-spacing:2px}

.dynamic-island{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(1.2);z-index:101;text-align:center;padding:0;opacity:0;transition:all 0.8s cubic-bezier(0.16,1,0.3,1)}
.dynamic-island.entered{position:sticky;top:4px;transform:translate(0,0) scale(1);opacity:1}
.dynamic-island-content{display:inline-flex;align-items:center;gap:10px;padding:10px 18px;border-radius:28px;background:rgba(255,255,255,0.75);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);box-shadow:0 4px 24px rgba(0,0,0,0.06),inset 1px 1px 2px rgba(255,255,255,0.9);min-width:260px;max-width:600px;transform:translateZ(0);will-change:transform}
body.dark .dynamic-island-content{background:rgba(22,33,62,0.85);box-shadow:0 4px 24px rgba(0,0,0,0.35),inset 1px 1px 2px rgba(255,255,255,0.05)}
.dynamic-island-content:hover{box-shadow:0 8px 32px rgba(0,0,0,0.1)}
.dynamic-island-paused{opacity:0.6}
.dynamic-island-paused .dynamic-island-cover{animation-play-state:paused}

.dynamic-island-cover-wrap{position:relative;width:36px;height:36px;flex-shrink:0}
.dynamic-island-cover{width:36px;height:36px;border-radius:50%;object-fit:cover;box-shadow:0 3px 12px rgba(0,0,0,0.15);animation:islandSpin 8s linear infinite;transition:opacity 0.3s ease;opacity:0;position:absolute;top:0;left:0}
.dynamic-island-cover.loaded{opacity:1}
@keyframes islandSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.dynamic-island-cover-placeholder{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);flex-shrink:0;box-shadow:0 3px 12px rgba(0,0,0,0.15);display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;position:absolute;top:0;left:0}
.dynamic-island-cover-placeholder.loading{animation:islandPulse 1.2s ease-in-out infinite}
@keyframes islandPulse{0%,100%{transform:scale(1);opacity:0.7}50%{transform:scale(1.15);opacity:1}}

.dynamic-island-info{display:flex;flex-direction:column;align-items:flex-start;gap:1px;flex:1;min-width:0;cursor:pointer}
.dynamic-island-song{font-size:14px;font-weight:600;color:#2d3748;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px}
body.dark .dynamic-island-song{color:#e0e0e0}
.dynamic-island-artist{font-size:11px;color:#718096;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}
body.dark .dynamic-island-artist{color:#888}

.dynamic-island-btn{width:30px;height:30px;border-radius:50%;background:transparent;border:1.5px solid rgba(102,126,234,0.18);cursor:pointer;display:flex;align-items:center;justify-content:center;color:rgba(102,126,234,0.7);flex-shrink:0;transition:all 0.25s cubic-bezier(0.4,0,0.2,1)}
.dynamic-island-btn:hover{border-color:rgba(102,126,234,0.45);color:#667eea;background:rgba(102,126,234,0.06);transform:scale(1.08)}
.dynamic-island-btn:active{transform:scale(0.95)}
.dynamic-island-btn svg{width:14px;height:14px;pointer-events:none}

.dynamic-island-play{width:32px;height:32px;background:transparent;color:rgba(102,126,234,0.7);border:1.5px solid rgba(102,126,234,0.2);border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all 0.25s cubic-bezier(0.4,0,0.2,1)}
.dynamic-island-play:hover{border-color:rgba(102,126,234,0.5);color:#667eea;background:rgba(102,126,234,0.08);transform:scale(1.08);box-shadow:0 0 16px rgba(102,126,234,0.15)}
.dynamic-island-play:active{transform:scale(0.95)}
.dynamic-island-play.paused{border-color:rgba(102,126,234,0.1);color:rgba(102,126,234,0.35)}
.dynamic-island-play svg{width:16px;height:16px;pointer-events:none}

body.dark .dynamic-island-btn{color:rgba(129,140,248,0.7);border-color:rgba(129,140,248,0.18)}
body.dark .dynamic-island-btn:hover{color:#818cf8;border-color:rgba(129,140,248,0.45);background:rgba(129,140,248,0.08)}
body.dark .dynamic-island-play{color:rgba(129,140,248,0.7);border-color:rgba(129,140,248,0.2)}
body.dark .dynamic-island-play:hover{color:#818cf8;border-color:rgba(129,140,248,0.5);background:rgba(129,140,248,0.1);box-shadow:0 0 16px rgba(129,140,248,0.15)}
body.dark .dynamic-island-play.paused{color:rgba(129,140,248,0.3);border-color:rgba(129,140,248,0.08)}

.playlist-panel{display:none;position:absolute;top:100%;left:50%;transform:translateX(-50%);margin-top:8px;background:rgba(255,255,255,0.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-radius:16px;padding:8px 0;min-width:260px;max-width:400px;max-height:320px;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,0.12);z-index:200}
.playlist-panel.show{display:block}
body.dark .playlist-panel{background:rgba(22,33,62,0.92);box-shadow:0 8px 32px rgba(0,0,0,0.4)}
.playlist-item{display:flex;align-items:center;gap:10px;padding:10px 16px;cursor:pointer;transition:background 0.15s;font-size:13px;color:#4a5568}
.playlist-item:hover{background:rgba(102,126,234,0.06)}
.playlist-item.active{background:rgba(102,126,234,0.1);color:#667eea;font-weight:600}
.playlist-item.active::before{content:'♪';font-size:14px;color:#667eea;flex-shrink:0}
body.dark .playlist-item{color:#a0aec0}
body.dark .playlist-item:hover{background:rgba(102,126,234,0.08)}
body.dark .playlist-item.active{background:rgba(102,126,234,0.12);color:#7b8cff}
.playlist-item-cover{width:28px;height:28px;border-radius:6px;object-fit:cover;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.playlist-item-index{width:20px;text-align:center;font-size:11px;color:#a0aec0;flex-shrink:0}
.playlist-item-name{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.playlist-item-artist{font-size:11px;color:#a0aec0;flex-shrink:0;max-width:80px;overflow:hidden;text-overflow:ellipsis}

@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

.lrc-bar{text-align:center;padding:4px 12px;display:none;max-height:60px;overflow-y:auto;overflow-x:hidden;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.lrc-bar::-webkit-scrollbar{display:none}
.lrc-bar.show{display:block}
.lrc-text{display:inline-block;font-size:12px;color:#667eea;font-weight:400;white-space:normal;line-height:1.6;max-width:90vw;overflow:hidden;text-overflow:ellipsis;opacity:0;transition:opacity 0.3s}
.lrc-text.show{opacity:1}
body.dark .lrc-text{color:#818cf8}

.dynamic-island-loading-bar{width:120px;height:2px;background:rgba(102,126,234,0.1);border-radius:1px;overflow:hidden;margin:4px auto 0;display:none}
.dynamic-island-loading-bar.show{display:block}
.dynamic-island-loading-bar-fill{height:100%;width:0;background:linear-gradient(90deg,transparent,#667eea,transparent);border-radius:1px;animation:lrcLoadingSlide 1.5s ease-in-out infinite}
@keyframes lrcLoadingSlide{0%{width:0;margin-left:0}50%{width:70%;margin-left:15%}100%{width:0;margin-left:100%}}

.aplayer{display:none!important}
.top-bar{position:sticky;top:40px;z-index:100;background:#e8ecf1;padding:6px 10px;display:flex;align-items:center;gap:8px;box-shadow:0 2px 8px rgba(0,0,0,0.06);transition:all 0.2s ease;will-change:transform}
.top-bar.scrolled{background:rgba(232,236,241,0.92);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);box-shadow:0 2px 8px rgba(0,0,0,0.08)}
body.dark .top-bar.scrolled{background:rgba(22,33,62,0.9)}

.shop-brand{display:flex;flex-direction:column;flex-shrink:0;min-width:0;align-items:center;text-align:center}
.shop-brand-row{display:flex;align-items:center;gap:3px}
.shop-brand-icon{width:14px;height:14px;border-radius:3px;object-fit:cover;flex-shrink:0}
.shop-brand-name{font-size:12px;font-weight:700;color:#2d3748;white-space:nowrap;line-height:1}
body.dark .shop-brand-name{color:#e0e0e0}
.shop-brand-sub{font-size:7px;color:#a0aec0;white-space:nowrap;line-height:1.1}
body.dark .shop-brand-sub{color:#666}

.search-input{flex:1;min-width:0;padding:11px 16px;border-radius:10px;border:none;background:#e8ecf1;box-shadow:inset 2px 2px 4px #c8cdd5,inset -2px -2px 4px #ffffff;font-size:14px;color:#2d3748;outline:none;transition:all 0.2s ease}
.search-input:focus{box-shadow:inset 3px 3px 6px #c8cdd5,inset -3px -3px 6px #ffffff,0 0 0 2px rgba(102,126,234,0.2)}
.search-input::placeholder{color:#a0aec0}

.dark-toggle-btn{width:24px;height:24px;min-width:24px;border-radius:50%;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;color:#718096;flex-shrink:0;transition:all 0.15s ease}
body.dark .dark-toggle-btn{background:#1a1a2e;box-shadow:2px 2px 4px rgba(0,0,0,0.3),-2px -2px 4px rgba(255,255,255,0.03);color:#888}
.dark-toggle-btn:hover{box-shadow:inset 2px 2px 3px rgba(0,0,0,0.1),inset -2px -2px 3px rgba(255,255,255,0.8)}

.carousel-wrap{max-width:1200px;margin:0 auto 14px;padding:0 20px;position:relative;overflow:hidden;border-radius:16px;display:none}
.carousel-wrap.show{display:block}
.carousel-track{display:flex;transition:transform 0.5s ease}
.carousel-slide{min-width:100%;position:relative;cursor:pointer}
.carousel-slide img{width:100%;height:<?php echo $carousel_height_desktop; ?>px;object-fit:cover;display:block;border-radius:16px}
.carousel-dots{position:absolute;bottom:8px;right:12px;display:flex;gap:6px}
.carousel-dots span{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.5);cursor:pointer;transition:background 0.2s}
.carousel-dots span.active{background:#fff}
.carousel-arrow{position:absolute;top:50%;transform:translateY(-50%);width:32px;height:32px;border-radius:50%;border:none;background:rgba(255,255,255,0.75);color:#333;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;z-index:5;transition:background 0.2s;box-shadow:0 2px 6px rgba(0,0,0,0.15)}
.carousel-arrow:hover{background:rgba(255,255,255,0.95)}
.carousel-arrow.prev{left:8px}
.carousel-arrow.next{right:8px}
body.dark .carousel-arrow{background:rgba(0,0,0,0.5);color:#ccc}
body.dark .carousel-arrow:hover{background:rgba(0,0,0,0.75)}

.partition-bar{max-width:800px;margin:0 auto 10px;padding:0 20px;display:flex;gap:8px}
.partition-tab{flex:1;max-width:200px;padding:8px 14px;border-radius:12px;border:none;background:#e8ecf1;box-shadow:4px 4px 8px #d1d5db,-4px -4px 8px #ffffff;cursor:pointer;font-size:14px;font-weight:600;color:#718096;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);text-align:center;position:relative;overflow:hidden}
.partition-tab::after{content:'';position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:0;height:3px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:3px 3px 0 0;transition:width 0.3s ease}
.partition-tab:hover{color:#667eea;transform:translateY(-2px);box-shadow:6px 6px 12px #c8cdd5,-6px -6px 12px #ffffff}
.partition-tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:4px 4px 12px rgba(102,126,234,.35);transform:translateY(-1px)}
.partition-tab.active::after{width:60%}
body.dark .partition-tab{background:#16213e;color:#666;box-shadow:4px 4px 8px rgba(0,0,0,0.3),-4px -4px 8px rgba(255,255,255,0.03)}
body.dark .partition-tab:hover{color:#7b8cff}
body.dark .partition-tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.partition-badge{display:inline-block;background:rgba(255,255,255,0.2);border-radius:10px;padding:1px 8px;font-size:11px;margin-left:6px;font-weight:400}
body.dark .partition-badge{background:rgba(255,255,255,0.1)}
body.dark #musicPanel{background:#16213e}
body.dark #musicPanel span{color:#e0e0e0!important}
body.dark #musicPanel div[style*="color:#2d3748"]{color:#e0e0e0!important}
body.dark #musicPanel div[style*="color:#a0aec0"]{color:#666!important}
body.dark #musicToggleBtn{background:#16213e;box-shadow:4px 4px 10px rgba(0,0,0,.4),-4px -4px 10px rgba(255,255,255,.03)}
.container{max-width:1200px;margin:0 auto;padding:20px 20px}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:25px;margin-bottom:40px}
.neu-card{background:#e8ecf1;border-radius:20px;overflow:hidden;box-shadow:8px 8px 16px #c8cdd5,-8px -8px 16px #ffffff;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275),opacity 0.3s ease;will-change:transform;contain:content;content-visibility:auto;contain-intrinsic-height:200px}
.neu-card[style*="display:none"],.neu-card[style*="display: none"]{opacity:0;transform:scale(0.95)}
.neu-card:hover{transform:translateY(-8px);box-shadow:12px 12px 24px #b8bdc5,-12px -12px 24px #ffffff}
body.dark .neu-card:hover{box-shadow:8px 8px 20px rgba(0,0,0,0.5),-4px -4px 12px rgba(255,255,255,0.03)}
.card-body{padding:16px 18px 18px}
.card-header-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.card-icon-wrap{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:inset 3px 3px 6px rgba(0,0,0,0.1),inset -3px -3px 6px rgba(255,255,255,0.8);background:#e8ecf1}
.card-icon-wrap svg{width:20px;height:20px}
.card-name{font-size:17px;font-weight:600;color:#2d3748;margin-bottom:10px}
.card-contact{background:rgba(102,126,234,0.08);border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#667eea;display:flex;align-items:center;gap:6px}
.card-contact svg{width:14px;height:14px}
.card-actions{display:flex;gap:10px;margin-bottom:10px}
.card-btn{flex:1;padding:9px 0;border-radius:10px;border:none;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.3s ease;text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:5px}
.btn-jump{background:linear-gradient(135deg,#4facfe,#00f2fe);color:white;box-shadow:4px 4px 10px rgba(79,172,254,0.3)}
.btn-qq{background:linear-gradient(135deg,#a855f7,#ec4899);color:white;box-shadow:4px 4px 10px rgba(168,85,247,0.3)}
.btn-urge{background:linear-gradient(135deg,#ff9500,#ff5e00);color:white;box-shadow:4px 4px 10px rgba(255,149,0,0.3);font-size:12px}
.card-btn:hover{transform:translateY(-2px)}
.card-description{padding:12px 16px 8px;font-size:13px;font-weight:600;color:#667eea;line-height:1.5;background:rgba(102,126,234,0.06);border-radius:0 0 10px 10px}
.card-cat-tag{display:inline-block;padding:3px 8px;border-radius:12px;background:rgba(102,126,234,0.1);color:#667eea;font-size:11px;margin-left:8px}
.card-meta-row{display:flex;align-items:center;gap:16px;margin-top:6px;font-size:11px;color:#b0b8d0;flex-wrap:wrap}
.card-meta-row span{display:flex;align-items:center;gap:3px}
.card-meta-row svg{width:12px;height:12px}
.card-top-line{height:4px;border-radius:20px 20px 0 0}
.back-to-top{position:fixed;bottom:30px;right:20px;width:40px;height:40px;border-radius:50%;border:none;background:#e8ecf1;box-shadow:4px 4px 10px #c8cdd5,-4px -4px 10px #ffffff;cursor:pointer;display:none;align-items:center;justify-content:center;z-index:99;transition:all .3s ease;color:#718096}
.back-to-top:hover{color:#667eea;transform:translateY(-3px)}
.back-to-top.show{display:flex}
body.dark .back-to-top{background:#16213e;box-shadow:4px 4px 10px rgba(0,0,0,.4),-4px -4px 10px rgba(255,255,255,.03)}
.footer-text-wrap{max-width:1200px;margin:16px auto 30px;padding:0 20px;text-align:center}
.footer-text{font-size:12px;color:#a0aec0;line-height:1.6}
body.dark .footer-text{color:#666}

.fold-section{margin-top:30px;padding-top:8px}
.fold-header{display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:15px 20px;background:#e8ecf1;border-radius:16px;box-shadow:6px 6px 12px #d1d5db,-6px -6px 12px #ffffff;margin-bottom:15px;transition:all 0.3s ease;gap:10px}
.fold-header:hover{box-shadow:8px 8px 16px #c8cdd5,-8px -8px 16px #ffffff}
.fold-header h2{font-size:18px;font-weight:700;color:#2d3748;display:flex;align-items:center;gap:10px;flex:1}
body.dark .fold-header h2{color:#e0e0e0}
.fold-header-msg-btn{padding:5px 14px;border-radius:12px;border:none;background:#e8ecf1;box-shadow:3px 3px 6px #c8cdd5,-3px -3px 6px #ffffff;cursor:pointer;font-size:12px;color:#667eea;font-weight:600;white-space:nowrap;transition:all .15s ease}
.fold-header-msg-btn:hover{box-shadow:inset 2px 2px 3px #c8cdd5,inset -2px -2px 3px #ffffff}
body.dark .fold-header-msg-btn{background:#1a1a2e;box-shadow:3px 3px 6px rgba(0,0,0,0.3),-3px -3px 6px rgba(255,255,255,0.03);color:#7b8cff}
.fold-count{background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-size:12px;padding:2px 10px;border-radius:20px}
.fold-toggle{width:32px;height:32px;border-radius:50%;border:none;background:#e8ecf1;box-shadow:3px 3px 6px #c8cdd5,-3px -3px 6px #ffffff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.3s ease}
.fold-toggle:hover{box-shadow:inset 2px 2px 4px rgba(0,0,0,0.1),inset -2px -2px 4px rgba(255,255,255,0.8)}
.fold-toggle svg{transition:transform 0.3s ease}
.fold-toggle.open svg{transform:rotate(180deg)}
.fold-content{display:none;flex-direction:column;gap:12px;max-width:800px;margin:0 auto;animation:fadeIn 0.3s ease}
.fold-content.open{display:flex}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
@keyframes pulse{0%,100%{box-shadow:4px 4px 10px #c8cdd5,-4px -4px 10px #ffffff}50%{box-shadow:4px 4px 20px rgba(102,126,234,.5),-4px -4px 20px rgba(102,126,234,.3);transform:scale(1.08)}}

.msg-card{background:#e8ecf1;border-radius:14px;padding:14px 18px;box-shadow:4px 4px 8px #d1d5db,-4px -4px 8px #ffffff;position:relative}
.msg-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.msg-name{font-weight:600;color:#2d3748;font-size:14px}
.msg-time{font-size:11px;color:#a0aec0}
.msg-content{color:#4a5568;line-height:1.6;font-size:13px;word-break:break-word}
.msg-meta{font-size:10px;color:#bbb;margin-top:4px}
.msg-actions{display:flex;align-items:center;gap:12px;margin-top:10px}
.msg-like-btn{display:flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;font-size:12px;color:#718096;transition:all 0.2s ease}
.msg-like-btn:hover{box-shadow:inset 1px 1px 3px rgba(0,0,0,0.1),inset -1px -1px 3px rgba(255,255,255,0.8);color:#e53e3e}
.msg-like-btn.liked{color:#e53e3e;box-shadow:inset 2px 2px 4px rgba(0,0,0,0.1),inset -2px -2px 4px rgba(255,255,255,0.8)}
.msg-reply-btn{padding:5px 12px;border-radius:20px;border:none;background:#e8ecf1;box-shadow:2px 2px 4px #c8cdd5,-2px -2px 4px #ffffff;cursor:pointer;font-size:12px;color:#718096;transition:all 0.2s ease}
.msg-reply-btn:hover{box-shadow:inset 1px 1px 3px rgba(0,0,0,0.1),inset -1px -1px 3px rgba(255,255,255,0.8);color:#4a5568}
.msg-reply-tag{display:inline-block;background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-size:11px;padding:2px 8px;border-radius:10px;margin-bottom:8px}
.msg-reply-expand{font-size:10px;padding:2px 8px;border-radius:10px;border:1px solid #c8cdd5;background:transparent;color:#667eea;cursor:pointer;margin-left:6px}
.msg-reply-count{font-size:11px;color:#a0aec0}

.urge-card{background:#e8ecf1;border-radius:14px;padding:14px 18px;box-shadow:4px 4px 8px #d1d5db,-4px -4px 8px #ffffff}
.urge-header{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.urge-resource{font-size:13px;font-weight:600;color:#667eea}
.urge-time{font-size:11px;color:#a0aec0;margin-left:auto}
.urge-content{color:#4a5568;font-size:13px;line-height:1.6}

.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);display:none;align-items:center;justify-content:center;z-index:1000;backdrop-filter:blur(4px)}
.modal-overlay.active{display:flex}
.modal-box{background:#e8ecf1;border-radius:24px;padding:28px;max-width:420px;width:90%;box-shadow:12px 12px 24px #b8bdc5,-12px -12px 24px #ffffff;animation:modalIn 0.3s ease;max-height:85vh;overflow-y:auto}
@keyframes modalIn{from{opacity:0;transform:scale(0.9) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-title{font-size:18px;font-weight:700;color:#2d3748;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between}
body.dark .modal-title{color:#e0e0e0}
.modal-close{width:32px;height:32px;border-radius:50%;border:none;background:#e8ecf1;box-shadow:3px 3px 6px #c8cdd5,-3px -3px 6px #ffffff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#718096;transition:all 0.2s ease}
.modal-close:hover{color:#e53e3e;box-shadow:inset 2px 2px 4px rgba(0,0,0,0.1),inset -2px -2px 4px rgba(255,255,255,0.8)}
.modal-body{color:#4a5568;line-height:1.7;font-size:14px}
.modal-input{width:100%;padding:12px 16px;border-radius:12px;border:none;background:#e8ecf1;box-shadow:inset 4px 4px 8px #c8cdd5,inset -4px -4px 8px #ffffff;font-size:14px;color:#2d3748;margin-bottom:12px;outline:none}
.modal-textarea{min-height:80px;resize:vertical;font-family:inherit}
.modal-btn-primary{width:100%;padding:12px;border-radius:12px;border:none;background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-size:15px;font-weight:600;cursor:pointer;box-shadow:4px 4px 10px rgba(102,126,234,0.3);transition:all 0.3s ease;margin-top:8px}
.modal-btn-primary:hover{transform:translateY(-2px)}
.qq-hint{background:rgba(255,149,0,0.1);border-radius:10px;padding:12px;margin-bottom:15px;font-size:13px;color:#ff9500;line-height:1.6}
.qq-group-btns{display:flex;flex-direction:column;gap:10px;margin-top:15px}
.qq-group-btns a{display:block;padding:12px;border-radius:12px;text-align:center;text-decoration:none;color:white;font-weight:600;font-size:14px;transition:all 0.3s ease}
.qq-group-btns a:hover{transform:translateY(-2px)}
.qq-group-1{background:linear-gradient(135deg,#a855f7,#ec4899);box-shadow:4px 4px 10px rgba(168,85,247,0.3)}
.qq-group-2{background:linear-gradient(135deg,#f093fb,#f5576c);box-shadow:4px 4px 10px rgba(245,87,108,0.3)}
.urge-phrases{display:flex;flex-wrap:wrap;gap:8px;margin:15px 0}
.urge-phrase{padding:8px 14px;border-radius:20px;border:none;background:#e8ecf1;box-shadow:3px 3px 6px #c8cdd5,-3px -3px 6px #ffffff;cursor:pointer;font-size:13px;color:#4a5568;transition:all 0.2s ease}
.urge-phrase:hover{background:linear-gradient(135deg,#667eea,#764ba2);color:white;box-shadow:3px 3px 8px rgba(102,126,234,0.3)}
.urge-phrase.selected{background:linear-gradient(135deg,#667eea,#764ba2);color:white}
.toast{position:fixed;top:20px;left:50%;transform:translateX(-50%) translateY(-60px);background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:12px 28px;border-radius:50px;font-size:14px;font-weight:500;box-shadow:0 8px 20px rgba(102,126,234,0.4);z-index:2000;opacity:0;transition:all 0.4s ease;pointer-events:none}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.stat-bar-wrap{max-width:1200px;margin:30px auto 0;padding:0 20px}
.stat-bar{display:flex;align-items:center;justify-content:center;gap:32px;padding:18px 28px;background:#e8ecf1;border-radius:20px;box-shadow:6px 6px 14px #c8cdd5,-6px -6px 14px #ffffff;text-align:center;flex-wrap:wrap;transition:background .2s,box-shadow .2s}
body.dark .stat-bar{background:#16213e;box-shadow:6px 6px 14px rgba(0,0,0,0.4),-6px -6px 14px rgba(255,255,255,0.03)}
.stat-item{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:80px;position:relative}
.stat-item+.stat-item::before{content:'';position:absolute;left:-16px;top:8px;bottom:8px;width:1px;background:linear-gradient(to bottom,transparent,#c8cdd5,transparent)}
body.dark .stat-item+.stat-item::before{background:linear-gradient(to bottom,transparent,rgba(255,255,255,0.1),transparent)}
.stat-icon-wrap{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:inset 2px 2px 4px rgba(0,0,0,0.08),inset -2px -2px 4px rgba(255,255,255,0.8);transition:box-shadow .2s}
body.dark .stat-icon-wrap{box-shadow:inset 2px 2px 4px rgba(0,0,0,0.3),inset -2px -2px 4px rgba(255,255,255,0.03)}
.stat-icon-wrap svg{width:16px;height:16px}
.stat-value{font-size:22px;font-weight:800;color:#2d3748;line-height:1;transition:color .2s}
body.dark .stat-value{color:#e0e0e0}
.stat-label{font-size:11px;color:#a0aec0;font-weight:500}
.stat-value.c1{color:#667eea}body.dark .stat-value.c1{color:#7b8cff}
.stat-value.c2{color:#48bb78}body.dark .stat-value.c2{color:#68d391}
.stat-value.c3{color:#ed8936}body.dark .stat-value.c3{color:#f6ad55}
.stat-value.c4{color:#e53e3e}body.dark .stat-value.c4{color:#fc8181}
.stat-icon-wrap.c1{color:#667eea}body.dark .stat-icon-wrap.c1{color:#7b8cff}
.stat-icon-wrap.c2{color:#48bb78}body.dark .stat-icon-wrap.c2{color:#68d391}
.stat-icon-wrap.c3{color:#ed8936}body.dark .stat-icon-wrap.c3{color:#f6ad55}
.stat-icon-wrap.c4{color:#e53e3e}body.dark .stat-icon-wrap.c4{color:#fc8181}
.empty-state{text-align:center;padding:30px;color:#a0aec0}

@media(max-width:640px){
  .container{padding:15px 12px}
  .cards-grid{grid-template-columns:1fr;gap:18px}
  .top-bar{padding:6px 10px;gap:8px}
  .shop-brand-name{font-size:11px}
  .shop-brand-sub{font-size:7px}
  .shop-brand-icon{width:12px;height:12px}
  .search-input{font-size:12px;padding:7px 10px}
  .dark-toggle-btn{width:22px;height:22px;min-width:22px;font-size:11px}
  .carousel-slide img{height:<?php echo $carousel_height_mobile; ?>px}
  .carousel-arrow{width:28px;height:28px;font-size:12px}
  .dynamic-island-content{max-width:92vw;min-width:auto;padding:8px 12px;gap:6px}
  .dynamic-island-song{max-width:120px;font-size:12px}
  .dynamic-island-artist{max-width:100px;font-size:10px}
  .dynamic-island-btn{width:26px;height:26px}
}
</style>
</head>
<body>
<div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true"><input type="text" name="username" autocomplete="username"><input type="password" name="password" autocomplete="current-password"></div>

<div class="toast" id="toast"></div>

<div class="dynamic-island" id="dynamicIsland">
  <div class="dynamic-island-content" id="dynamicIslandContent">
    <div class="dynamic-island-cover-wrap">
      <div class="dynamic-island-cover-placeholder" id="islandCoverPlaceholder"></div>
      <img src="" class="dynamic-island-cover" id="islandCover" alt="">
    </div>
    <div class="dynamic-island-info" id="islandSongInfo">
      <span class="dynamic-island-song" id="islandSong">音乐加载中...</span>
      <span class="dynamic-island-artist" id="islandArtist">即将播放</span>
    </div>
    <button class="dynamic-island-btn" id="islandPrevBtn" title="上一曲">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>
    </button>
    <button class="dynamic-island-play" id="islandPlayBtn" title="播放/暂停">
      <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" width="14" height="14"><polygon points="8,5 19,12 8,19"/></svg>
    </button>
    <button class="dynamic-island-btn" id="islandNextBtn" title="下一曲">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>
    </button>
    <button class="dynamic-island-btn" id="islandListBtn" title="播放列表">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </button>
  </div>
  <div class="playlist-panel" id="playlistPanel"></div>
  <div class="dynamic-island-loading-bar" id="islandLoadingBar">
    <div class="dynamic-island-loading-bar-fill"></div>
  </div>
</div>

<div class="lrc-bar" id="lrcBar">
  <span class="lrc-text" id="lrcText"></span>
</div>

<div class="top-bar" id="topBar">
<div class="shop-brand">
    <div class="shop-brand-row">
        <?php if($shop_icon): ?><img src="data/<?php echo htmlspecialchars($shop_icon); ?>" class="shop-brand-icon" alt=""><?php else: ?><span style="width:14px;height:14px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;color:#667eea"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><?php endif; ?>
        <span class="shop-brand-name"><?php echo htmlspecialchars($shop_name); ?></span>
    </div>
    <div class="shop-brand-sub"><?php echo htmlspecialchars($shop_sub); ?></div>
</div>
<input type="text" class="search-input" id="searchInput" placeholder="搜索资源..." autocomplete="off">
<button class="dark-toggle-btn" id="darkToggle" title="切换暗黑模式">&#x25D0;</button>
</div>

<div class="carousel-wrap" id="carouselWrap">
    <div class="carousel-track" id="carouselTrack"></div>
    <div class="carousel-dots" id="carouselDots"></div>
    <button class="carousel-arrow prev" id="carouselPrev" title="上一张">&#x2039;</button>
    <button class="carousel-arrow next" id="carouselNext" title="下一张">&#x203A;</button>
</div>

<div class="partition-bar" id="partitionBar">
<?php 
$partitions = $config['partitions'] ?? [['id' => 'game', 'name' => '游戏内核分区'], ['id' => 'other', 'name' => '其他分区']];
foreach ($partitions as $p): 
?>
    <button class="partition-tab <?php echo $p['id'] === 'game' ? 'active' : ''; ?>" data-partition="<?php echo htmlspecialchars($p['id']); ?>" onclick="switchPartition('<?php echo htmlspecialchars($p['id']); ?>',this)">
        <?php echo htmlspecialchars($p['name']); ?><span class="partition-badge" id="partCount_<?php echo htmlspecialchars($p['id']); ?>">0</span>
    </button>
<?php endforeach; ?>
</div>

<div id="emptyPartitionMsg" class="empty-state" style="display:none;text-align:center;padding:40px 20px;font-size:16px;color:#a0aec0">该分区暂无资源</div>

<div class="container">
<div class="cards-grid">
<?php foreach ($resources as $res): ?>
<div class="neu-card" data-name="<?php echo htmlspecialchars($res['name']); ?>" data-desc="<?php echo htmlspecialchars($res['description'] ?? ''); ?>" data-partition="<?php echo htmlspecialchars($res['partition'] ?? 'game'); ?>">
<div class="card-top-line" style="background:<?php echo htmlspecialchars($res['color'] ?? '#4a90d9'); ?>"></div>
<?php if(!empty($res['description'])): ?>
<div class="card-description"><?php echo nl2br(htmlspecialchars($res['description'])); ?></div>
<?php endif; ?>
<div class="card-body">
<div class="card-header-row">
<div class="card-icon-wrap" style="color:<?php echo htmlspecialchars($res['color'] ?? '#4a90d9'); ?>">
<?php if(!empty($res['icon'])): ?><img src="data/<?php echo htmlspecialchars($res['icon']); ?>" style="max-width:100%;max-height:100%;object-fit:contain" alt=""><?php else: ?>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
<?php endif; ?>
</div>
<button class="card-copy-btn" onclick="copyLink('<?php echo htmlspecialchars($res['url']); ?>')" title="复制链接" style="width:28px;height:28px;border-radius:8px;border:none;background:#e8ecf1;box-shadow:3px 3px 6px #c8cdd5,-3px -3px 6px #ffffff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#718096">
<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
</button>
</div>
<div class="card-name">
    <?php echo htmlspecialchars($res['name']); ?>
    <?php if(!empty($res['category'])): ?><span class="card-cat-tag"><?php echo htmlspecialchars($res['category']); ?></span><?php endif; ?>
</div>
<div class="card-contact">
<?php echo htmlspecialchars($config['contact_text'] ?? '如有疑问请联系QQ'); ?> <?php echo htmlspecialchars($contact_qq); ?>
</div>
<div class="card-actions">
<a href="<?php echo htmlspecialchars($res['url']); ?>" target="_blank" class="card-btn btn-jump" rel="noopener" onclick="trackClick(<?php echo $res['id']; ?>)">
<?php echo htmlspecialchars($config['btn_jump_text'] ?? '蓝奏云下载'); ?>
</a>
<button class="card-btn btn-qq" onclick="openQQModal(<?php echo $res['id']; ?>)">
<?php echo htmlspecialchars($config['btn_qq_text'] ?? 'qq群(文件在q群文件)'); ?>
</button>
</div>
<div class="card-meta-row">
<?php if(!empty($res['version'])): ?>
<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> v<?php echo htmlspecialchars($res['version']); ?></span>
<?php endif; ?>
<?php if(!empty($res['updated_at'])): ?>
<span title="更新时间"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php echo htmlspecialchars($res['updated_at']); ?></span>
<?php endif; ?>
</div>
<button class="card-btn btn-urge" style="width:100%;margin-top:8px" onclick="openUrgeModal('<?php echo htmlspecialchars($res['name']); ?>')">
<?php echo htmlspecialchars($config['btn_urge_text'] ?? '催更'); ?>
</button>
</div>
</div>
<?php endforeach; ?>
</div>

<div class="fold-section">
<div class="fold-header" onclick="toggleFold('guestbook')">
<h2><?php echo htmlspecialchars($config['section_guestbook'] ?? '留言板'); ?> <span class="fold-count" id="msgCount">0</span></h2>
<button class="fold-header-msg-btn" onclick="event.stopPropagation();openMsgForm()">留言</button>
<button class="fold-toggle" id="guestbookToggle">
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
</button>
</div>
<div class="fold-content" id="guestbookContent">
<div id="msgList"></div>
</div>
</div>

<div class="fold-section">
<div class="fold-header" onclick="toggleFold('urges')">
<h2><?php echo htmlspecialchars($config['section_urge_wall'] ?? '催更墙'); ?> <span class="fold-count" id="urgeCount">0</span></h2>
<button class="fold-toggle" id="urgesToggle">
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
</button>
</div>
<div class="fold-content" id="urgesContent">
<div class="empty-state" id="urgeLoading">加载中...</div>
</div>
</div>
</div>

<div class="stat-bar-wrap">
<div class="stat-bar" id="statBar">
  <div class="stat-item">
    <div class="stat-icon-wrap c1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
    <div class="stat-value c1" id="totalViews">-</div>
    <div class="stat-label">总浏览量</div>
  </div>
  <div class="stat-item">
    <div class="stat-icon-wrap c2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
    <div class="stat-value c2" id="uniqueVisitors">-</div>
    <div class="stat-label">总访客</div>
  </div>
  <div class="stat-item">
    <div class="stat-icon-wrap c3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
    <div class="stat-value c3" id="todayVisitors">-</div>
    <div class="stat-label">今日访客</div>
  </div>
  <div class="stat-item">
    <div class="stat-icon-wrap c4"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
    <div class="stat-value c4" id="onlineCount">-</div>
    <div class="stat-label">当前在线</div>
  </div>
</div>
</div>

<?php if(!empty($config['footer_text'])): ?>
<div class="footer-text-wrap"><div class="footer-text"><?php echo nl2br(htmlspecialchars($config['footer_text'])); ?></div></div>
<?php endif; ?>

<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="回到顶部">
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<div class="modal-overlay" id="announcementModal">
<div class="modal-box" style="max-width:480px">
<div class="modal-title"><span><?php echo htmlspecialchars($config['popup_title'] ?? '公告'); ?></span><button class="modal-close" onclick="closeModal('announcementModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
<div class="modal-body" style="white-space:pre-wrap;line-height:1.8"><?php echo nl2br(htmlspecialchars($config['popup_content'] ?? '')); ?></div>
<button class="modal-btn-primary" style="margin-top:20px" onclick="closeModal('announcementModal')">知道了</button>
</div>
</div>

<div class="modal-overlay" id="qqModal">
<div class="modal-box">
<div class="modal-title"><span>加入QQ群</span><button class="modal-close" onclick="closeModal('qqModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
<div class="modal-body" id="qqModalBody">
<div class="qq-hint"><?php echo htmlspecialchars($config['qq_hint_text'] ?? '输入密码查看内容'); ?></div>
<input type="password" class="modal-input" id="qqPassword" placeholder="输入4位密码" autocomplete="new-password">
<button class="modal-btn-primary" onclick="verifyQQPassword()">验证</button>
</div>
<div id="qqGroupResult" style="display:none">
<p style="text-align:center;color:#718096;margin-bottom:10px">验证成功，点击下方按钮加入：</p>
<div class="qq-group-btns" id="qqGroupBtns"></div>
</div>
</div>
</div>

<div class="modal-overlay" id="urgeModal">
<div class="modal-box">
<div class="modal-title"><span id="urgeTitle">催更</span><button class="modal-close" onclick="closeModal('urgeModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
<div class="modal-body">
<p style="color:#718096;margin-bottom:12px">选择快捷催更内容，或自定义输入：</p>
<div class="urge-phrases" id="urgePhrases"></div>
<textarea class="modal-input modal-textarea" id="urgeCustom" placeholder="自定义催更内容..."></textarea>
<input type="text" class="modal-input" id="urgeQq" placeholder="QQ号（选填，回复将通知到QQ邮箱）" maxlength="15" autocomplete="off" inputmode="numeric" style="width:100%;margin-bottom:8px">
<button class="modal-btn-primary" onclick="submitUrge()">发送催更</button>
</div>
</div>
</div>

<div class="modal-overlay" id="msgModal">
<div class="modal-box">
<div class="modal-title"><span>留言</span><button class="modal-close" onclick="closeModal('msgModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
<div class="modal-body">
<textarea class="modal-input modal-textarea" id="msgContent" placeholder="写下你的留言..." maxlength="500" style="width:100%;margin-bottom:8px"></textarea>
<input type="text" class="modal-input" id="msgName" placeholder="昵称（默认匿名）" maxlength="20" autocomplete="off" style="width:100%;margin-bottom:8px">
<input type="text" class="modal-input" id="msgQq" placeholder="QQ号（选填，回复将通知到QQ邮箱）" maxlength="15" autocomplete="off" inputmode="numeric" style="width:100%;margin-bottom:8px">
<input type="hidden" id="msgReplyTo" value="0">
<button class="modal-btn-primary" onclick="submitMessage()">提交留言</button>
</div>
</div>
</div>

<div class="modal-overlay" id="urgeReplyModal">
<div class="modal-box">
<div class="modal-title"><span>回复催更</span><button class="modal-close" onclick="closeModal('urgeReplyModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
<div class="modal-body">
<textarea class="modal-input modal-textarea" id="urgeReplyContent" placeholder="写下你的回复..." maxlength="300" style="width:100%;margin-bottom:8px"></textarea>
<input type="text" class="modal-input" id="urgeReplyName" placeholder="昵称（默认匿名）" maxlength="20" autocomplete="off" style="width:100%;margin-bottom:8px">
<button class="modal-btn-primary" onclick="submitUrgeReply()">提交回复</button>
</div>
</div>
</div>

<script>
let currentResourceId=0,currentResourceName='';
const urgeTemplates=['求更新{name}','{name}链接失效了','{name}啥时候更新','{name}最新更新的是什么','什么时候上线{name}'];
const myIP='<?php echo rn_get_client_ip(); ?>';

function openUrgeModal(name){
  currentResourceName=name;
  document.getElementById('urgeTitle').textContent='催更：'+name;
  const container=document.getElementById('urgePhrases');
  container.innerHTML='';
  urgeTemplates.forEach(t=>{
    const text=t.replace(/{name}/g,name);
    const btn=document.createElement('button');
    btn.className='urge-phrase';
    btn.textContent=text;
    btn.onclick=function(){
      document.querySelectorAll('.urge-phrase').forEach(b=>b.classList.remove('selected'));
      btn.classList.add('selected');
      document.getElementById('urgeCustom').value=text;
    };
    container.appendChild(btn);
  });
  document.getElementById('urgeCustom').value='';
  openModal('urgeModal');
}
function submitUrge(){
  const text=document.getElementById('urgeCustom').value.trim();
  if(!text){showToast('请输入催更内容');return;}
  const btn=document.querySelector('#urgeModal .modal-btn-primary');
  if(btn.disabled)return;
  btn.disabled=true;btn.textContent='发送中...';
  const fd=new FormData();
  fd.append('action','add_urge');
  fd.append('resource_name',currentResourceName);
  fd.append('content',text);
  var qq=document.getElementById('urgeQq').value.trim();
  if(qq)fd.append('email',qq+'@qq.com');
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){showToast('催更成功！');closeModal('urgeModal');document.getElementById('urgeQq').value='';loadUrges();}
    else{showToast(d.message||'催更失败');}
  }).finally(function(){
    btn.disabled=false;btn.textContent='发送催更';
  });
}
let urgeReplyId=0;
function replyUrgeInline(id){
  urgeReplyId=id;
  document.getElementById('urgeReplyContent').value='';
  document.getElementById('urgeReplyName').value='';
  openModal('urgeReplyModal');
  document.getElementById('urgeReplyContent').focus();
}
function submitUrgeReply(){
  const content=document.getElementById('urgeReplyContent').value.trim();
  const name=document.getElementById('urgeReplyName').value||'匿名';
  if(!content){showToast('请输入回复内容');return;}
  const btn=document.querySelector('#urgeReplyModal .modal-btn-primary');
  if(btn.disabled)return;
  btn.disabled=true;btn.textContent='发送中...';
  const fd=new FormData();
  fd.append('action','reply_urge');
  fd.append('id',urgeReplyId);
  fd.append('name',name);
  fd.append('content',content);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){showToast('回复成功！');closeModal('urgeReplyModal');loadUrges();}
    else{showToast(d.message||'回复失败');}
  }).finally(function(){
    btn.disabled=false;btn.textContent='提交回复';
  });
}
function openQQModal(id){
  currentResourceId=id;
  document.getElementById('qqPassword').value='';
  document.getElementById('qqModalBody').style.display='block';
  document.getElementById('qqGroupResult').style.display='none';
  openModal('qqModal');
}
function verifyQQPassword(){
  const pwd=document.getElementById('qqPassword').value;
  if(pwd.length!==4){showToast('请输入4位密码');return;}
  const fd=new FormData();
  fd.append('action','get_qq_groups');
  fd.append('resource_id',currentResourceId);
  fd.append('password',pwd);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      const g=d.data;
      let html='';
      if(g.group1){
        html+='<a href="mqqapi://card/show_pslcard?src_type=internal&version=1&uin='+g.group1+'&card_type=group&source=external" class="qq-group-1">用浏览器打开 QQ群①</a>';
      }
      if(g.group2){
        html+='<a href="mqqapi://card/show_pslcard?src_type=internal&version=1&uin='+g.group2+'&card_type=group&source=external" class="qq-group-2">用浏览器打开 QQ群②</a>';
      }
      if(!g.group1&&!g.group2)html='<p style="text-align:center;color:#a0aec0">暂无QQ群</p>';
      else html+='<p style="text-align:center;color:#ff6b6b;font-size:12px;margin-top:8px">请勿在QQ/微信内打开链接</p>';
      document.getElementById('qqGroupBtns').innerHTML=html;
      document.getElementById('qqModalBody').style.display='none';
      document.getElementById('qqGroupResult').style.display='block';
    }else{showToast(d.message||'密码错误');}
  });
}
function submitMessage(){
  const name=document.getElementById('msgName').value||'匿名';
  const content=document.getElementById('msgContent').value.trim();
  const replyTo=parseInt(document.getElementById('msgReplyTo').value)||0;
  if(!content){showToast('请输入留言内容');return;}
  const btn=document.querySelector('#msgModal .modal-btn-primary');
  if(btn.disabled)return;
  btn.disabled=true;btn.textContent='发送中...';
  const fd=new FormData();
  fd.append('action','add_message');
  fd.append('name',name);
  fd.append('content',content);
  fd.append('reply_to',replyTo);
  var qq=document.getElementById('msgQq').value.trim();
  if(qq)fd.append('email',qq+'@qq.com');
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){showToast('留言成功！');closeModal('msgModal');document.getElementById('msgContent').value='';document.getElementById('msgReplyTo').value='0';document.getElementById('msgQq').value='';loadMessages();}
    else{showToast(d.message||'留言失败');}
  }).finally(function(){
    btn.disabled=false;btn.textContent='提交留言';
  });
}
function likeMessage(id){
  const fd=new FormData();
  fd.append('action','like_message');
  fd.append('id',id);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      loadMessages();
      showToast(d.message||'操作成功');
    }else{
      showToast(d.message||'操作失败');
    }
  });
}
function replyMsgInline(id,name){
  document.getElementById('msgReplyTo').value=id;
  document.getElementById('msgContent').value='';
  document.getElementById('msgContent').placeholder='回复 #'+id+' '+name+' ...';
  document.getElementById('msgQq').value='';
  openModal('msgModal');
  document.getElementById('msgContent').focus();
  showToast('在弹窗中输入回复内容');
}
function loadMessages(){
  fetch('api.php?action=get_messages').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    const msgs=d.data.messages||[];
    document.getElementById('msgCount').textContent=msgs.length;
    const msgMap={}; msgs.forEach(m=>msgMap[m.id]=m);
    let html='';
    // Only show top-level messages
    const topLevel=msgs.filter(m=>!m.reply_to);
    if(!topLevel.length){html='<div class="empty-state">暂无留言，来做第一个留言的人吧</div>';}
    topLevel.forEach(m=>{
      const liked=m.liked_ips&&m.liked_ips.includes(myIP);
      html+='<div class="msg-card">';
      html+='<div class="msg-header"><span class="msg-name">'+esc(m.name)+'</span><span class="msg-time">'+esc(m.time)+'</span></div>';
      html+='<div class="msg-content">'+esc(m.content)+'</div>';
      html+='<div class="msg-meta">'+esc(m.ip_location||'-')+'</div>';
      // Show replies inline
      const replies=msgs.filter(r=>r.reply_to===m.id);
      if(replies.length>0){
        replies.forEach(r=>{
          html+='<div style="margin:6px 0 0 12px;padding:6px 10px;background:rgba(102,126,234,0.08);border-radius:8px;font-size:12px;color:#667eea">';
          html+='<span style="font-weight:600">'+esc(r.name)+' 回复: </span>'+esc(r.content);
          html+=' <span style="font-size:10px;color:#b0b8d0">'+esc(r.time)+' '+esc(r.ip_location||'-')+'</span></div>';
        });
      }
      html+='<div class="msg-actions">';
      html+='<button class="msg-like-btn '+(liked?'liked':'')+'" onclick="likeMessage('+m.id+')">';
      html+='<svg width="14" height="14" viewBox="0 0 24 24" fill="'+(liked?'currentColor':'none')+'" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
      html+='<span>'+(m.likes||0)+'</span></button>';
      html+='<button class="msg-reply-btn" onclick="replyMsgInline('+m.id+',\''+esc(m.name).replace(/'/g,"\\'")+'\')">回复</button>';
      if(replies.length>0)html+='<span class="msg-reply-count">'+replies.length+'条回复</span>';
      html+='</div></div>';
    });
    document.getElementById('msgList').innerHTML=html;
  });
}
function loadUrges(){
  fetch('api.php?action=get_urges').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    const container=document.getElementById('urgesContent');
    const urges=d.data.urges||[];
    document.getElementById('urgeCount').textContent=urges.length;
    if(!urges.length){container.innerHTML='<div class="empty-state">暂无催更记录</div>';return;}
    let html='';
    urges.forEach(u=>{
      html+='<div class="urge-card">';
      html+='<div class="urge-header"><span class="urge-resource">'+esc(u.resource_name)+'</span><span class="urge-time">'+esc(u.time)+'</span></div>';
      html+='<div class="urge-content">'+esc(u.content)+'</div>';
      html+='<div class="urge-meta" style="font-size:10px;color:#b0b8d0;margin-top:4px">'+esc(u.ip_location||'-')+'</div>';
      // Show replies
      var replies=u.replies||[];
      // Support legacy single reply field
      if(!replies.length&&u.reply)replies=[{name:'管理员',content:u.reply,time:u.reply_time||'',ip_location:''}];
      if(replies.length>0){
        replies.forEach(function(r){
          html+='<div style="margin-top:6px;padding:8px 12px;background:rgba(102,126,234,.08);border-radius:8px;font-size:12px;color:#667eea">';
          if(r.name==='管理员')html+='<span style="font-weight:600">管理员回复: </span>';
          else html+='<span style="font-weight:600">'+esc(r.name)+' 回复: </span>';
          html+=esc(r.content)+' <span style="font-size:10px;color:#b0b8d0">'+esc(r.time)+' '+esc(r.ip_location||'')+'</span></div>';
        });
      }
      html+='<div style="margin-top:8px"><button class="msg-reply-btn" onclick="replyUrgeInline('+u.id+')" style="font-size:11px">回复</button></div>';
      html+='</div>';
    });
    container.innerHTML=html;
  });
}
function toggleFold(type){
  const content=document.getElementById(type+'Content');
  const btn=document.getElementById(type+'Toggle');
  content.classList.toggle('open');
  btn.classList.toggle('open');
  if(type==='guestbook'&&content.classList.contains('open'))loadMessages();
}
function openMsgForm(){
  document.getElementById('msgReplyTo').value='0';
  document.getElementById('msgContent').value='';
  document.getElementById('msgContent').placeholder='写下你的留言...';
  document.getElementById('msgQq').value='';
  openModal('msgModal');
  document.getElementById('msgContent').focus();
}
function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='';}
function copyLink(url){if(navigator.clipboard){navigator.clipboard.writeText(url).then(()=>showToast('链接已复制'));}else{const ta=document.createElement('textarea');ta.value=url;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);showToast('链接已复制');}}
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500);}
function esc(t){const d=document.createElement('div');d.textContent=t||'';return d.innerHTML;}

function trackClick(id){
  const fd=new FormData();fd.append('action','track_click');fd.append('id',id);
  fetch('api.php',{method:'POST',body:fd}).catch(()=>{});
}

function reportBroken(id){
  const fd=new FormData();fd.append('action','report_broken');fd.append('id',id);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    showToast(d.message||(d.success?'举报成功':'操作失败'));
  });
}

function viewPwd(id,el){
  const pwdEl=document.querySelector('[data-pwd-id="'+id+'"]');
  if(pwdEl&&pwdEl.style.display!=='none'){
    pwdEl.style.display='none';el.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> 查看密码';
    return;
  }
  const fd=new FormData();fd.append('action','view_password');fd.append('id',id);
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      if(pwdEl){
        pwdEl.textContent=d.data.password;pwdEl.style.display='inline';el.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> 隐藏';
      }
    }else{showToast(d.message||'获取失败');}
  });
}

function loadStats(){
  fetch('api.php?action=get_stats').then(r=>r.json()).then(d=>{
    if(!d.success)return;
    document.getElementById('totalViews').textContent=number(d.data.total_views||0);
    document.getElementById('uniqueVisitors').textContent=number(d.data.total_visitors||0);
    document.getElementById('todayVisitors').textContent=number(d.data.today_visitors||0);
    document.getElementById('onlineCount').textContent=number(d.data.online||0);
  });
}

function number(n){return parseInt(n).toLocaleString()}

<?php if(($config['popup_enabled']??false)): ?>if(!localStorage.getItem('announcement_seen_'+new Date().toDateString())){setTimeout(()=>openModal('announcementModal'),800);localStorage.setItem('announcement_seen_'+new Date().toDateString(),'1');}<?php endif; ?>

document.addEventListener('DOMContentLoaded',function(){
  Promise.all([
    loadStats(),
    loadMessages(),
    loadUrges(),
    initTopBar(),
    initSearch(),
    initDarkMode(),
    initCarousel(),
    initBackToTop(),
    initPartition()
  ]);
  setInterval(loadStats,60000);
});
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',function(e){if(e.target===this){this.classList.remove('active');document.body.style.overflow='';}});});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.modal-overlay.active').forEach(m=>m.classList.remove('active'));document.body.style.overflow='';}});

let currentPartition='game';

function switchPartition(partition,btn){
  currentPartition=partition;
  document.querySelectorAll('.partition-tab').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  doFilter();
  // Save preference
  try{localStorage.setItem('rn_partition',partition);}catch(e){}
}

function initPartition(){
  try{
    var saved=localStorage.getItem('rn_partition');
    if(saved){
      currentPartition=saved;
      document.querySelectorAll('.partition-tab').forEach(b=>{
        if(b.dataset.partition===saved)b.classList.add('active');
        else b.classList.remove('active');
      });
      doFilter();
    }
  }catch(e){}
  updatePartitionCounts();
}

function updatePartitionCounts(){
  var counts={};
  document.querySelectorAll('.neu-card').forEach(function(card){
    var part=card.dataset.partition||'game';
    counts[part]=(counts[part]||0)+1;
  });
  document.querySelectorAll('.partition-tab').forEach(function(btn){
    var partId=btn.dataset.partition;
    var badge=btn.querySelector('.partition-badge');
    if(badge)badge.textContent=counts[partId]||0;
  });
}

function initTopBar(){
  var topBar=document.getElementById('topBar');
  var ticking=false;
  window.addEventListener('scroll',function(){
    if(!ticking){
      window.requestAnimationFrame(function(){
        if(window.scrollY>30)topBar.classList.add('scrolled');
        else topBar.classList.remove('scrolled');
        ticking=false;
      });
      ticking=true;
    }
  });
}

function initSearch(){
  var input=document.getElementById('searchInput');
  var cards=document.querySelectorAll('.neu-card');
  input.addEventListener('input',function(){doFilter()});
}
function doFilter(){
  var keyword=(document.getElementById('searchInput').value||'').toLowerCase();
  var cards=document.querySelectorAll('.neu-card');
  var activePart=currentPartition||'game';
  var anyVisible=false;
  cards.forEach(function(card){
    var name=card.dataset.name||'';
    var desc=card.dataset.desc||'';
    var part=card.dataset.partition||'game';
    var match=keyword===''||name.toLowerCase().indexOf(keyword)!==-1||desc.toLowerCase().indexOf(keyword)!==-1;
    // Ignores partition filter when searching - search across ALL partitions
    var partMatch=keyword!==''||activePart==='all'||part===activePart;
    var show=(match&&partMatch);
    card.style.display=show?'':'none';
    if(show)anyVisible=true;
  });
  var msg=document.getElementById('emptyPartitionMsg');
  if(msg)msg.style.display=anyVisible?'none':'';
}

function initDarkMode(){
  var btn=document.getElementById('darkToggle');
  var saved=localStorage.getItem('rn_dark');
  function apply(on){
    document.body.classList.toggle('dark',on);
    btn.innerHTML=on?'&#x2600;':'&#x25D0;';
    localStorage.setItem('rn_dark',on?'1':'0');
  }
  var sys=window.matchMedia('(prefers-color-scheme:dark)').matches;
  var on=saved==='1'?true:(saved==='0'?false:sys);
  apply(on);
  btn.onclick=function(){on=!on;apply(on)};
  window.matchMedia('(prefers-color-scheme:dark)').addEventListener('change',function(e){if(saved===null)apply(e.matches)});
}

function initBackToTop(){
  var btn=document.getElementById('backToTop');
  var ticking=false;
  window.addEventListener('scroll',function(){
    if(!ticking){
      window.requestAnimationFrame(function(){
        btn.classList.toggle('show',window.scrollY>400);
        ticking=false;
      });
      ticking=true;
    }
  });
}

function initCarousel(){
  var SPEED=<?php echo $carousel_speed; ?>;
  var data={carousel:[],on:false,timer:null};
  try{
    var x=new XMLHttpRequest();
    x.open('GET','api.php?action=get_resources',false);
    x.send();
    var r=JSON.parse(x.responseText);
    if(r.success){
      data.carousel=r.data.carousel||[];
      data.on=r.data.carousel_enabled||false;
    }
  }catch(e){}
  if(!data.carousel.length)return;
  var wrap=document.getElementById('carouselWrap');
  var track=document.getElementById('carouselTrack');
  var dots=document.getElementById('carouselDots');
  wrap.classList.add('show');
  var ci=0,loadedCount=0;
  data.carousel.forEach(function(s,i){
    var slide=document.createElement('div');slide.className='carousel-slide';
    var img=document.createElement('img');img.src='data/'+esc(s.img||'');
    img.onload=function(){loadedCount++;if(loadedCount===data.carousel.length){startAuto()}};
    img.onerror=function(){loadedCount++;if(loadedCount===data.carousel.length){startAuto()}};
    if(s.url)img.addEventListener('click',function(){window.open(s.url,'_blank')});
    slide.appendChild(img);
    if(s.title){
      var cap=document.createElement('div');
      cap.style.cssText='position:absolute;bottom:0;left:0;right:0;padding:8px 16px;background:linear-gradient(transparent,rgba(0,0,0,0.7));color:#fff;font-size:13px';
      cap.textContent=s.title;slide.appendChild(cap);
    }
    track.appendChild(slide);
    var dot=document.createElement('span');
    dot.addEventListener('click',function(){goSlide(i)});
    dots.appendChild(dot);
  });
  function goSlide(n){ci=n;track.style.transform='translateX(-'+ci+'00%)';dots.querySelectorAll('span').forEach(function(s,i){s.classList.toggle('active',i===ci)});resetTimer()}
  function next(){ci=(ci+1)%data.carousel.length;goSlide(ci)}
  function prev(){ci=(ci-1+data.carousel.length)%data.carousel.length;goSlide(ci)}
  function resetTimer(){clearInterval(data.timer);data.timer=setInterval(next,SPEED)}
  function startAuto(){goSlide(0);resetTimer()}
  document.getElementById('carouselPrev').addEventListener('click',prev);
  document.getElementById('carouselNext').addEventListener('click',next);
}
</script>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://api.qijieya.cn">
<link rel="preload" href="https://cdn.jsdelivr.net/npm/aplayer/dist/APlayer.min.css" as="style">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aplayer/dist/APlayer.min.css">
<script src="https://cdn.jsdelivr.net/npm/aplayer/dist/APlayer.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var islandSong = document.getElementById('islandSong');
  var islandArtist = document.getElementById('islandArtist');
  var islandCover = document.getElementById('islandCover');
  var islandCoverPlaceholder = document.getElementById('islandCoverPlaceholder');
  var islandPlayBtn = document.getElementById('islandPlayBtn');
  var islandContent = document.getElementById('dynamicIslandContent');
  var dynamicIsland = document.getElementById('dynamicIsland');
  var islandLoadingBar = document.getElementById('islandLoadingBar');
  var islandSongInfo = document.getElementById('islandSongInfo');
  var lrcBar = document.getElementById('lrcBar');
  var lrcText = document.getElementById('lrcText');
  var playlistPanel = document.getElementById('playlistPanel');
  var islandPrevBtn = document.getElementById('islandPrevBtn');
  var islandNextBtn = document.getElementById('islandNextBtn');
  var islandListBtn = document.getElementById('islandListBtn');
  var ap = null;
  var allSongs = [];

  function initMusic() {
    var loadingStage = 0;
    var loadingStages = ['正在加载歌单...', '准备播放...'];
    var loadingTimer;
    islandCoverPlaceholder.classList.add('loading');
    islandCoverPlaceholder.innerHTML = '&#9835;';
    islandLoadingBar.classList.add('show');
    islandSong.textContent = '正在连接音乐服务...';
    islandArtist.textContent = '请稍候';
    islandCoverPlaceholder.style.cursor = 'default';
    islandCoverPlaceholder.onclick = null;
    loadingStage = 0;
    loadingTimer = setInterval(function() {
      if (loadingStage < loadingStages.length) {
        islandSong.textContent = loadingStages[loadingStage];
        loadingStage++;
      }
    }, 2000);
    
    fetch('https://api.qijieya.cn/meting/?server=netease&type=playlist&id=18119088016')
      .then(function(res) { return res.json(); })
      .then(function(data) {
        clearInterval(loadingTimer);
        allSongs = data.map(function(song) {
          return {
            name: song.name,
            artist: song.artist,
            url: song.url,
            cover: song.pic,
            lrc: song.lrc
          };
        }).filter(function(song) { return song.url && song.url !== 'null'; });
        
        var firstSong = {
          name: '一程山路',
          artist: '毛不易',
          url: 'https://api.qijieya.cn/meting/?server=netease&type=url&id=1417849873',
          cover: 'https://api.qijieya.cn/meting/?server=netease&type=pic&id=109951164640697307',
          lrc: 'https://api.qijieya.cn/meting/?server=netease&type=lrc&id=1417849873'
        };
        
        allSongs.unshift(firstSong);
        
        ap = new APlayer({
          container: document.getElementById('musicPlayer'),
          fixed: false,
          autoplay: true,
          loop: 'all',
          order: 'list',
          volume: 0.5,
          mutex: true,
          theme: '#667eea',
          lrcType: 3,
          audio: allSongs,
          preload: 'metadata'
        });
        
        islandLoadingBar.classList.remove('show');
        
        ap.on('play', updatePlayBtn);
        ap.on('pause', updatePlayBtn);
        
        ap.on('loadeddata', function() {
          var current = ap.list.audios[ap.list.index];
          islandSong.textContent = current.name;
          islandArtist.textContent = current.artist;
          loadCover(current.cover);
          islandCoverPlaceholder.classList.remove('loading');
          lrcText.textContent = '';
          lrcText.classList.remove('show');
          renderPlaylist();
        });
        
        ap.on('timeupdate', function() {
          var lrc = ap.lrc;
          if (lrc && lrc.lines && lrc.lines.length > 0) {
            var idx = lrc.currentIndex;
            if (idx >= 0 && idx < lrc.lines.length) {
              var line = lrc.lines[idx];
              if (line && line.text && line.text.trim()) {
                lrcText.textContent = line.text;
                lrcText.classList.add('show');
                lrcBar.classList.add('show');
              }
            }
          }
        });
        
        ap.on('ended', function() {
          lrcText.textContent = '';
          lrcText.classList.remove('show');
          setTimeout(function() { ap.play(); }, 100);
        });
      })
      .catch(function() {
        clearInterval(loadingTimer);
        islandSong.textContent = '加载失败';
        islandArtist.textContent = '点击重试';
        islandCoverPlaceholder.innerHTML = '&#8635;';
        islandCoverPlaceholder.classList.add('loading');
        islandCoverPlaceholder.style.cursor = 'pointer';
        islandCoverPlaceholder.onclick = function() { initMusic(); };
        islandLoadingBar.classList.remove('show');
      });
  }
  
  function isPlaying() { return ap && !ap.paused; }

  function updatePlayBtn() {
    var svg = islandPlayBtn.querySelector('svg');
    if (isPlaying()) {
      svg.innerHTML = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
      islandPlayBtn.classList.remove('paused');
      islandContent.classList.remove('dynamic-island-paused');
    } else {
      svg.innerHTML = '<polygon points="8,5 19,12 8,19"/>';
      islandPlayBtn.classList.add('paused');
      islandContent.classList.add('dynamic-island-paused');
    }
  }

  setTimeout(function() {
    dynamicIsland.classList.add('entered');
  }, 200);
  
  function loadCover(url) {
    islandCover.classList.remove('loaded');
    islandCoverPlaceholder.style.display = 'flex';
    islandCover.src = '';
    var img = new Image();
    img.onload = function() {
      islandCover.src = url;
      islandCover.classList.add('loaded');
      islandCoverPlaceholder.style.display = 'none';
      islandCoverPlaceholder.classList.remove('loading');
    };
    img.onerror = function() {
      islandCoverPlaceholder.style.display = 'flex';
      islandCoverPlaceholder.classList.remove('loading');
    };
    img.src = url;
  }

  function renderPlaylist() {
    if (!ap || !allSongs.length) return;
    var idx = ap.list.index;
    playlistPanel.innerHTML = allSongs.map(function(s, i) {
      var cls = i === idx ? 'playlist-item active' : 'playlist-item';
      return '<div class="'+cls+'" onclick="window.__switchSong('+i+')">'+
        '<span class="playlist-item-index">'+(i+1)+'</span>'+
        '<img src="'+esc(s.cover)+'" class="playlist-item-cover" alt="'+esc(s.name)+'">'+
        '<span class="playlist-item-name">'+esc(s.name)+'</span>'+
        '<span class="playlist-item-artist">'+esc(s.artist)+'</span>'+
        '</div>';
    }).join('');
  }

  window.__switchSong = function(i) {
    if (ap) {
      ap.list.switch(i);
      ap.play();
      playlistPanel.classList.remove('show');
    }
  };

  islandListBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    renderPlaylist();
    playlistPanel.classList.toggle('show');
  });

  document.addEventListener('click', function(e) {
    if (!playlistPanel.contains(e.target) && e.target !== islandListBtn) {
      playlistPanel.classList.remove('show');
    }
  });
  
  initMusic();

  islandPlayBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    if (ap) ap.toggle();
  });

  islandPrevBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    if (ap) ap.skipBack();
    playlistPanel.classList.remove('show');
  });

  islandNextBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    if (ap) ap.skipForward();
    playlistPanel.classList.remove('show');
  });

  islandSongInfo.addEventListener('click', function() {
    if (ap) ap.skipForward();
  });

  function esc(t) {
    var d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
  }
});
</script>
<div id="musicPlayer"></div>
<script src="https://player.xfyun.club/js/yinghua.js"></script>
</body>
</html>
