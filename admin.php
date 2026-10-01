<?php
require_once __DIR__ . '/db.php';
function h($x){return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');}
function csrf(){return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));}
function verify_csrf(){if(!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')){http_response_code(419);exit('Invalid request.');}}
function go($m=''){header('Location: admin.php'.($m?'?msg='.urlencode($m):''));exit;}
function clean_html($html){
    $allowed='<p><br><strong><b><em><i><u><ul><ol><li><blockquote><code><pre><h2><h3><h4><a><hr>';
    $html=strip_tags((string)$html,$allowed);
    $html=preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu','',$html);
    $html=preg_replace('/\s(href|src)\s*=\s*(["\'])\s*(?:javascript:|data:).*?\2/iu','',$html);
    return trim($html);
}
function throttle_login(){
    $ip=$_SERVER['REMOTE_ADDR']??'unknown';
    $s=db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");$s->execute([$ip]);
    return (int)$s->fetchColumn() >= 10;
}
function record_login_failure(){
    $s=db()->prepare('INSERT INTO login_attempts(ip_address,attempted_at) VALUES(?,NOW())');$s->execute([$_SERVER['REMOTE_ADDR']??'unknown']);
}
function current_settings(){
    $out=[]; foreach(db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $r)$out[$r['setting_key']]=$r['setting_value']; return $out;
}
try{db()->query('SELECT 1');}catch(Throwable $e){exit('Database not configured. Complete install.php first.');}

if(isset($_GET['logout'])){session_destroy();header('Location: admin.php');exit;}

if(empty($_SESSION['admin_id'])){
    $err='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        if(throttle_login()){$err='Too many login attempts. Please wait 15 minutes.';}
        else{
            $email=trim($_POST['email']??'');$pass=$_POST['password']??'';
            $s=db()->prepare('SELECT * FROM admins WHERE email=? LIMIT 1');$s->execute([$email]);$a=$s->fetch();
            if($a && password_verify($pass,$a['password_hash'])){
                db()->prepare('DELETE FROM login_attempts WHERE ip_address=?')->execute([$_SERVER['REMOTE_ADDR']??'unknown']);
                session_regenerate_id(true);$_SESSION['admin_id']=$a['id'];$_SESSION['admin_email']=$a['email'];$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: admin.php');exit;
            }
            record_login_failure();$err='Email or password is incorrect.';
        }
    }
    ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automenta Admin Login</title><style>body{margin:0;background:#07111f;color:#e5eef8;font-family:Arial;display:grid;place-items:center;min-height:100vh}.box{width:min(420px,92vw);background:#0d1b2a;border:1px solid #20334a;border-radius:20px;padding:28px;box-sizing:border-box}input{width:100%;box-sizing:border-box;padding:13px;margin:7px 0;background:#081321;color:white;border:1px solid #29415d;border-radius:10px}button{width:100%;padding:13px;border:0;border-radius:10px;background:#08b6d9;font-weight:bold}.err{color:#ff9b9b}</style></head><body><div class="box"><h1>Automenta Admin</h1><?php if($err):?><p class="err"><?=h($err)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="email" name="email" placeholder="Admin email" required autocomplete="username"><input type="password" name="password" placeholder="Password" required autocomplete="current-password"><button>Secure Login</button></form></div></body></html><?php exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$a=$_POST['action']??'';
    if($a==='article'){
        $id=(int)($_POST['id']??0);$title=trim($_POST['title']??'');$cat=trim($_POST['category']??'');$ex=trim($_POST['excerpt']??'');$body=clean_html($_POST['content_html']??'');$pub=!empty($_POST['published'])?1:0;$author=trim($_POST['author']??'Automenta');
        if(!$title||!$body)go('Title and content are required.');
        $base=preg_replace('/[^a-z0-9]+/','-',strtolower($title));$slug=trim($base,'-')?:'article-'.time();
        $slug=$slug.'-'.substr(hash('sha256',$title.'|'.$id.'|'.microtime(true)),0,6);
        if($id){$s=db()->prepare('UPDATE articles SET title=?,slug=?,category=?,author=?,excerpt=?,content_html=?,published=? WHERE id=?');$s->execute([$title,$slug,$cat,$author,$ex,$body,$pub,$id]);go('Article updated.');}
        $s=db()->prepare('INSERT INTO articles(title,slug,category,author,excerpt,content_html,published) VALUES(?,?,?,?,?,?,?)');$s->execute([$title,$slug,$cat,$author,$ex,$body,$pub]);go('Article saved.');
    }
    if($a==='delete_article'){db()->prepare('DELETE FROM articles WHERE id=?')->execute([(int)$_POST['id']]);go('Article deleted.');}
    if($a==='product'){
        $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$desc=trim($_POST['description']??'');$price=(float)($_POST['price']??0);$currency=strtoupper(trim($_POST['currency']??'BDT'));
        if(!$name||$price<10||!preg_match('/^[A-Z]{3}$/',$currency))go('Enter a valid product, price and 3-letter currency.');
        $file=null;
        if(!empty($_FILES['private_file']['name'])){
            if($_FILES['private_file']['error']!==UPLOAD_ERR_OK||$_FILES['private_file']['size']>MAX_UPLOAD_BYTES)go('Upload failed or file is too large.');
            $ext=strtolower(pathinfo($_FILES['private_file']['name'],PATHINFO_EXTENSION));if(!in_array($ext,['json','txt','zip'],true))go('Only JSON, TXT and ZIP files are allowed.');
            $file=bin2hex(random_bytes(16)).'.'.$ext;if(!is_dir(UPLOAD_DIR))mkdir(UPLOAD_DIR,0700,true);if(!move_uploaded_file($_FILES['private_file']['tmp_name'],UPLOAD_DIR.'/'.$file))go('Upload failed.');
        }
        if($id){
            if($file){$s=db()->prepare('UPDATE products SET name=?,description=?,price=?,currency=?,private_filename=? WHERE id=?');$s->execute([$name,$desc,$price,$currency,$file,$id]);}
            else{$s=db()->prepare('UPDATE products SET name=?,description=?,price=?,currency=? WHERE id=?');$s->execute([$name,$desc,$price,$currency,$id]);}
            go('Workflow updated.');
        }
        $s=db()->prepare('INSERT INTO products(name,description,price,currency,checkout_url,private_filename) VALUES(?,?,?,?,?,?)');$s->execute([$name,$desc,$price,$currency,'',$file]);go('Workflow added.');
    }
    if($a==='delete_product'){db()->prepare('DELETE FROM products WHERE id=?')->execute([(int)$_POST['id']]);go('Workflow deleted.');}
    if($a==='settings'){
        $keys=['site_url','site_name','contact_email','payment_currency','ssl_store_id','ssl_live_mode','adsense_id','ga4_id'];
        foreach($keys as $k){$v=trim((string)($_POST[$k]??''));if($k==='site_url')$v=rtrim($v,'/');db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$k,$v]);}
        $newPass=trim((string)($_POST['ssl_store_password']??''));
        if($newPass!==''){db()->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('ssl_store_password',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$newPass]);}
        go('Settings saved.');
    }
    if($a==='change_password'){$old=$_POST['old_password']??'';$new=$_POST['new_password']??'';$s=db()->prepare('SELECT password_hash FROM admins WHERE id=?');$s->execute([$_SESSION['admin_id']]);$a=$s->fetch();if(!$a||!password_verify($old,$a['password_hash'])||strlen($new)<10)go('Password change failed.');db()->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$_SESSION['admin_id']]);session_regenerate_id(true);go('Password changed successfully.');}
}

$articles=db()->query('SELECT * FROM articles ORDER BY created_at DESC')->fetchAll();$products=db()->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();$orders=db()->query("SELECT o.*,p.name FROM orders o JOIN products p ON p.id=o.product_id ORDER BY o.created_at DESC LIMIT 100")->fetchAll();$settings=current_settings();$msg=$_GET['msg']??'';
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automenta Control Center</title><style>body{margin:0;background:#06101d;color:#e8f1f8;font-family:Arial}.wrap{max-width:1200px;margin:auto;padding:25px}.top{display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}@media(max-width:850px){.grid{grid-template-columns:1fr}}.card{background:#0c1a29;border:1px solid #20334a;border-radius:18px;padding:20px;margin-top:18px}input,textarea,select{width:100%;box-sizing:border-box;background:#071321;color:white;border:1px solid #29415d;border-radius:10px;padding:12px;margin:6px 0}textarea{min-height:180px}.btn{border:0;border-radius:10px;padding:10px 14px;background:#0bb5d5;color:#001018;font-weight:bold;cursor:pointer;text-decoration:none;display:inline-block}.danger{background:#4b1720;color:#ffb5bd}.row{display:flex;justify-content:space-between;gap:12px;border-top:1px solid #1c2c40;padding:12px 0;align-items:center}.muted{color:#8194aa}.msg{color:#91f5c7}.mini{font-size:12px;color:#8194aa}</style></head><body><div class="wrap"><div class="top"><div><h1>Automenta Control Center</h1><div class="muted">Server-side admin</div></div><div><a class="btn" href="index.php">View Site</a> <a class="btn danger" href="?logout=1">Logout</a></div></div><?php if($msg):?><p class="msg"><?=h($msg)?></p><?php endif;?>
<div class="card"><h2>Site & Payment Settings</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="settings"><div class="grid"><div><label>Site URL</label><input name="site_url" value="<?=h($settings['site_url']??'')?>" placeholder="https://yourdomain.com"><label>Site Name</label><input name="site_name" value="<?=h($settings['site_name']??'Automenta')?>"><label>Contact Email</label><input type="email" name="contact_email" value="<?=h($settings['contact_email']??'')?>"><label>AdSense Publisher ID</label><input name="adsense_id" value="<?=h($settings['adsense_id']??'')?>"><label>GA4 Measurement ID</label><input name="ga4_id" value="<?=h($settings['ga4_id']??'')?>"></div><div><label>Payment Currency</label><select name="payment_currency"><option value="BDT" <?=($settings['payment_currency']??'BDT')==='BDT'?'selected':''?>>BDT</option><option value="USD" <?=($settings['payment_currency']??'')==='USD'?'selected':''?>>USD</option><option value="EUR" <?=($settings['payment_currency']??'')==='EUR'?'selected':''?>>EUR</option><option value="GBP" <?=($settings['payment_currency']??'')==='GBP'?'selected':''?>>GBP</option><option value="SGD" <?=($settings['payment_currency']??'')==='SGD'?'selected':''?>>SGD</option><option value="INR" <?=($settings['payment_currency']??'')==='INR'?'selected':''?>>INR</option><label>SSLCOMMERZ Store ID</label><input name="ssl_store_id" value="<?=h($settings['ssl_store_id']??'')?>" autocomplete="off"><label>SSLCOMMERZ Store Password</label><input type="password" name="ssl_store_password" value="" placeholder="Leave blank to keep current password" autocomplete="new-password"><label>Mode</label><select name="ssl_live_mode"><option value="0" <?=($settings['ssl_live_mode']??'0')==='0'?'selected':''?>>Sandbox / Test</option><option value="1" <?=($settings['ssl_live_mode']??'0')==='1'?'selected':''?>>Live</option></select><p class="mini">Use only currencies your merchant account supports. SSLCOMMERZ converts non-BDT transactions to BDT and returns the original currency amount separately; the code validates both.</p></div></div><button class="btn">Save Settings</button></form></div>
<div class="grid"><div class="card"><h2>New / Edit Article</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="article"><input type="hidden" name="id" value="0"><input name="title" placeholder="Title" required><input name="category" placeholder="Category" value="AI Automation"><input name="author" placeholder="Author name" value="Automenta"><input name="excerpt" placeholder="Short excerpt"><textarea name="content_html" placeholder="Article content (basic HTML allowed)" required></textarea><label><input type="checkbox" name="published" value="1" checked> Publish now</label><br><br><button class="btn">Save Article</button></form></div><div class="card"><h2>New / Edit Workflow</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="product"><input type="hidden" name="id" value="0"><input name="name" placeholder="Workflow name" required><input name="description" placeholder="Description"><input type="number" step=".01" min="10" name="price" placeholder="Price" required><select name="currency"><option>BDT</option><option>USD</option><option>EUR</option><option>GBP</option><option>SGD</option><option>INR</option></select><input type="file" name="private_file" accept=".json,.txt,.zip"><br><br><button class="btn">Add Workflow</button></form></div></div>
<div class="card"><h2>Articles</h2><?php foreach($articles as $x):?><div class="row"><div><b><?=h($x['title'])?></b><div class="muted"><?=h($x['category'])?> · <?=h($x['author']??'Automenta')?> · <?=!empty($x['published'])?'Published':'Draft'?></div></div><div><a class="btn" href="article.php?slug=<?=rawurlencode($x['slug'])?>" target="_blank">View</a><form style="display:inline" method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete_article"><input type="hidden" name="id" value="<?=$x['id']?>"><button class="btn danger" onclick="return confirm('Delete?')">Delete</button></form></div></div><?php endforeach;?></div>
<div class="card"><h2>Premium Workflows</h2><?php foreach($products as $x):?><div class="row"><div><b><?=h($x['name'])?></b><div class="muted"><?=h($x['currency'])?> <?=number_format($x['price'],2)?> · <?=!empty($x['active'])?'Active':'Hidden'?></div></div><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?=$x['id']?>"><button class="btn danger">Delete</button></form></div><?php endforeach;?></div>
<div class="card"><h2>Orders</h2><?php foreach($orders as $o):?><div class="row"><div><b><?=h($o['name'])?></b><div class="muted"><?=h($o['customer_email'])?> · <?=h($o['currency'])?> <?=number_format($o['amount'],2)?> · <?=h(strtoupper($o['status']))?></div></div><div class="mini"><?=h($o['created_at'])?></div></div><?php endforeach;?></div>
<div class="card"><h2>Change Admin Password</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="change_password"><input type="password" name="old_password" placeholder="Current password" required><input type="password" name="new_password" placeholder="New password (10+ characters)" required><button class="btn">Change Password</button></form></div></div></body></html>