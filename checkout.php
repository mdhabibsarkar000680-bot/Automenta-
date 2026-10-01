<?php
require_once __DIR__.'/db.php';
$id=(int)($_GET['product']??0);
$st=db()->prepare("SELECT id,name,description,price,currency FROM products WHERE id=? AND active=1 LIMIT 1");
$st->execute([$id]);
$p=$st->fetch();
if(!$p){
  http_response_code(404);
  exit('Product not found');
}
function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Checkout - <?=h($p['name'])?></title><style>body{margin:0;background:#06101d;color:#e8f1f8;font-family:Arial}.wrap{max-width:720px;margin:50px auto;padding:20px}.box{background:#0c1a29;border:1px solid #20334a;border-radius:22px;padding:28px}input{width:100%;box-sizing:border-box;background:#071321;color:white;border:1px solid #29415d;border-radius:10px;padding:13px;margin:7px 0 15px}.btn{width:100%;border:0;border-radius:12px;padding:14px;background:#0bb5d5;color:#001018;font-weight:bold;font-size:16px;cursor:pointer}.muted{color:#91a4b8}a{color:#61d7f1}</style></head><body><div class="wrap"><div class="box"><p class="muted">Automenta Secure Checkout</p><h1><?=h($p['name'])?></h1><p class="muted"><?=h($p['description']??'Digital workflow')?></p><h2><?=h($p['currency'])?> <?=number_format((float)$p['price'],2)?></h2><form method="post" action="pay.php"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><label>Name</label><input name="name" required maxlength="190" autocomplete="name"><label>Email</label><input type="email" name="email" required maxlength="190" autocomplete="email"><label>Phone</label><input name="phone" maxlength="50" autocomplete="tel"><button class="btn">Continue to Secure Payment</button></form><p class="muted">Payment is processed by SSLCOMMERZ. Automenta does not store your card or mobile-banking credentials.</p><a href="index.php">← Back to Automenta</a></div></div></body></html>