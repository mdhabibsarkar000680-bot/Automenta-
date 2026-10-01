<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['product'])) { 
  http_response_code(400); 
  exit('Product required'); 
}

$productId=(int)($_POST['product_id']??$_GET['product']??0);
$pstmt=db()->prepare("SELECT * FROM products WHERE id=? AND active=1 LIMIT 1");
$pstmt->execute([$productId]);
$p=$pstmt->fetch();

if(!$p){
  http_response_code(404);
  exit('Product not found');
}

$ps=payment_settings();
if($ps['provider']!=='sslcommerz'||$ps['store_id']===''||$ps['store_password']===''){
  http_response_code(503);
  exit('Payment gateway not configured.');
}

$email=filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL);
$name=trim($_POST['name']??'');
$phone=trim($_POST['phone']??'');

if(!$email||strlen($name)<2){
  http_response_code(422);
  exit('Valid name and email required.');
}

$currency=strtoupper($p['currency']?:$ps['currency']);
if(!preg_match('/^[A-Z]{3}$/',$currency)){
  http_response_code(422);
  exit('Invalid currency.');
}

$amount=number_format((float)$p['price'],2,'.','');
if((float)$amount<10){
  http_response_code(422);
  exit('Amount too low.');
}

$tranId='AM-'.date('YmdHis').'-'.bin2hex(random_bytes(5));
$st=db()->prepare("INSERT INTO orders(product_id,customer_email,customer_name,customer_phone,amount,currency,status,provider,provider_reference) VALUES(?,?,?,?,?,?,?,?,?)");
$st->execute([$productId,$email,$name,$phone,$amount,$currency,'pending','sslcommerz',$tranId]);
$orderId=(int)db()->lastInsertId();

$token=bin2hex(random_bytes(32));
$_SESSION['automenta_order_token']=$token;
$_SESSION['automenta_order_id']=$orderId;

db()->prepare('UPDATE orders SET download_token_hash=? WHERE id=?')->execute([hash('sha256',$token),$orderId]);

$siteUrl='';
try{
  $q=db()->prepare("SELECT setting_value FROM settings WHERE setting_key='site_url'");
  $q->execute();
  $siteUrl=(string)$q->fetchColumn();
}catch(Throwable $e){}

$base=rtrim($siteUrl?:SITE_URL,'/');
if(!$base||strpos($base,'YOUR-DOMAIN')!==false){
  http_response_code(500);
  exit('Site URL not configured.');
}

$data=[
 'store_id'=>$ps['store_id'],'store_passwd'=>$ps['store_password'],'total_amount'=>$amount,'currency'=>$currency,'tran_id'=>$tranId,
 'success_url'=>$base.'/payment_success.php?order='.$orderId,'fail_url'=>$base.'/payment_fail.php?order='.$orderId,'cancel_url'=>$base.'/payment_cancel.php?order='.$orderId,
 'ipn_url'=>$base.'/payment_ipn.php','cus_name'=>$name,'cus_email'=>$email,'cus_phone'=>$phone,'cus_add1'=>'Bangladesh','cus_city'=>'Bangladesh','cus_country'=>'Bangladesh',
 'shipping_method'=>'NO','product_name'=>$p['name'],'product_category'=>'Digital Workflow','product_profile'=>'general','value_a'=>(string)$orderId
];

$live=$ps['live_mode']==='1';
$endpoint=$live?'https://securepay.sslcommerz.com/gwprocess/v4/api.php':'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';

$ch=curl_init($endpoint);
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
$res=curl_exec($ch);
$err=curl_error($ch);
curl_close($ch);

if($res===false){
  http_response_code(502);
  exit('Gateway connection failed.');
}

$j=json_decode($res,true);
$url=$j['GatewayPageURL']??$j['redirectGatewayURL']??'';

if(!$url){
  http_response_code(502);
  exit('Gateway error.');
}

header('Location: '.$url,true,302);
exit;