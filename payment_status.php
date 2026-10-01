<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/payment_config.php';
header('Content-Type: application/json');
$id=(int)($_GET['order']??0);
$st=db()->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$st->execute([$id]);
$o=$st->fetch();
$token=(($_SESSION['automenta_order_id']??0)===$id)?($_SESSION['automenta_order_token']??''):'';
if(!$o||!$token||!hash_equals((string)($o['download_token_hash']??''),hash('sha256',$token))){
  echo json_encode(['paid'=>false]);
  exit;
}
if($o['status']!=='paid' && $o['provider']==='sslcommerz' && $o['provider_reference']!==''){
  $ps=payment_settings();
  if($ps['store_id']!=='' && $ps['store_password']!==''){
    $live=$ps['live_mode']==='1';
    $host=$live?'https://securepay.sslcommerz.com':'https://sandbox.sslcommerz.com';
    $url=$host.'/validator/api/merchantTransIDvalidationAPI.php?tran_id='.rawurlencode($o['provider_reference']).'&store_id='.rawurlencode($ps['store_id']).'&store_passwd='.rawurlencode($ps['store_password']).'&format=json';
    $ch=curl_init($url);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT=>15,
      CURLOPT_CONNECTTIMEOUT=>10,
      CURLOPT_SSL_VERIFYPEER=>true
    ]);
    $res=curl_exec($ch);
    curl_close($ch);
    $q=json_decode($res??'',true);
    $item=$q['element'][0]??null;
    if(is_array($item) && in_array(($item['status']??''),['VALID','VALIDATED'],true) && ($item['tran_id']??'')===$o['provider_reference'] && strtoupper((string)($item['currency_type']??''))===strtoupper($o['currency']) && abs((float)($item['currency_amount']??-1)-(float)$o['amount'])<0.001 && (string)($item['risk_level']??'1')==='0'){
      db()->prepare("UPDATE orders SET status='paid',paid_at=NOW() WHERE id=? AND status='pending'")->execute([$id]);
      $o['status']='paid';
    }
  }
}
if($o['status']!=='paid'){
  echo json_encode(['paid'=>false]);
  exit;
}
echo json_encode(['paid'=>true,'download_url'=>'download.php?token='.rawurlencode($token)]);