<?php
require_once __DIR__.'/config.php';

if(session_status()===PHP_SESSION_NONE){
  session_name(ADMIN_SESSION_NAME);
  session_set_cookie_params([
    'httponly'=>true,
    'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),
    'samesite'=>'Lax'
  ]);
  session_start();
}

function db(){
  static $pdo;
  if($pdo)return $pdo;
  
  try{
    $pdo=new PDO(
      'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
      DB_USER,
      DB_PASS,
      [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT=>30
      ]
    );
  }catch(PDOException $e){
    error_log('Database connection failed: '.$e->getMessage());
    throw new Exception('Database connection failed. Check config.php.');
  }
  
  return $pdo;
}
