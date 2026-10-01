<?php
require_once __DIR__.'/db.php';
$id=(int)($_GET['id']??0);
$slug=trim($_GET['slug']??'');
if($id){$s=db()->prepare("SELECT * FROM articles WHERE id=? AND published=1 LIMIT 1");$s->execute([$id]);}
elseif($slug){$s=db()->prepare("SELECT * FROM articles WHERE slug=? AND published=1 LIMIT 1");$s->execute([$slug]);}
else{$s=null;}
$a=$s?$s->fetch():false;
if(!$a){http_response_code(404);?><!doctype html><html><head><meta charset="utf-8"><title>Article not found | Automenta</title></head><body><h1>Article not found</h1><p><a href="index.php">Back to Automenta</a></p></body></html><?php exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sanitize_html($html){
    $allowed='<p><br><strong><b><em><i><u><ul><ol><li><blockquote><code><pre><h2><h3><h4><a><hr>';
    $html=strip_tags((string)$html,$allowed);
    $html=preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu','',$html);
    $html=preg_replace('/\s(href|src)\s*=\s*(["\'])\s*(?:javascript:|data:).*?\2/iu','',$html);
    return trim($html);
}
$content_html=sanitize_html($a['content_html']??'');
$desc=trim($a['excerpt']?:preg_replace('/\s+/',' ',strip_tags($a['content_html'])));
$desc=mb_substr($desc,0,160);
$canonical=rtrim(SITE_URL,'/').'/article.php?slug='.rawurlencode($a['slug']);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($a['title'])?> | Automenta</title><meta name="description" content="<?=h($desc)?>"><link rel="canonical" href="<?=h($canonical)?>"><meta property="og:type" content="article"><meta property="og:title" content="<?=h($a['title'])?>"><meta property="og:description" content="<?=h($desc)?>"><meta property="og:url" content="<?=h($canonical)?>"><style>body{margin:0;background:#07111f;color:#e5eef8;font-family:Inter,system-ui,Arial,sans-serif}.wrap{max-width:820px;margin:auto;padding:28px 18px 80px}a{color:#22d3ee}h1{font-size:clamp(2rem,5vw,3.6rem);line-height:1.1}.meta{color:#8194aa;font-size:14px}.content{font-size:18px;line-height:1.9}.content img{max-width:100%;height:auto}.content pre{overflow:auto;background:#0d1b2a;padding:16px;border-radius:12px}.content code{background:#0d1b2a;padding:2px 5px;border-radius:5px}</style></head><body><main class="wrap"><p><a href="index.php">← Automenta Home</a></p><div class="meta"><?=h($a['category'])?> · <?=h($a['author']??'Automenta')?> · <?=h(date('M d, Y',strtotime($a['created_at'])))?></div><h1><?=h($a['title'])?></h1><p class="meta"><?=h($a['excerpt'])?></p><article class="content"><?=$content_html?></article></main></body></html>