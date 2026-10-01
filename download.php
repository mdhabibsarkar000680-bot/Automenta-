<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/config.php';
$token=$_GET['token']??'';
if(!preg_match('/^[a-f0-9]{64}$/',$token)){
  http_response_code(404);
  exit('Invalid token format');
}
$s=db()->prepare("SELECT o.*,p.private_filename FROM orders o JOIN products p ON p.id=o.product_id WHERE o.status='paid' AND o.download_token_hash=? LIMIT 1");
$s->execute([hash('sha256',$token)]);
$o=$s->fetch();
if(!$o||!$o['private_filename']){
  http_response_code(404);
  exit('Download not available');
}
$f=UPLOAD_DIR.'/'.$o['private_filename'];
if(!is_file($f)){
  http_response_code(404);
  exit('File not found');
}
$filename='automenta-workflow-'.$o['product_id'].'.'.pathinfo($f,PATHINFO_EXTENSION);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($f));
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, no-store, must-revalidate');
readfile($f);