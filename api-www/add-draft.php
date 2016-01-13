<?php
ob_start();
require_once(realpath(dirname(__FILE__) . '/..') . '/config.php');
ob_end_clean();

if (! isset($_SERVER['PHP_AUTH_USER']) || 
    ! isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != Updater::$api_blog_username ||
    $_SERVER['PHP_AUTH_PW'] != Updater::$api_blog_password
) {
    header('WWW-Authenticate: Basic realm="' . Post::$blog_title . '"');
    header('HTTP/1.0 401 Unauthorized');
    //sleep(3);
    exit;
}

$bookmarklet_code = <<<EOF
var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),l=d.location,e=encodeURIComponent;w.location.href='TARGET?u='+e(l.href)+'&t='+e(d.title)+'&s='+e(s)+'&EXTRA';
EOF;

$bookmarklet_code = str_replace('TARGET', $_SERVER['SCRIPT_URI'], trim($bookmarklet_code));

if (! isset($_GET['u'])) {
    ?>
    <p>
        <a href="javascript:<?= rawurlencode(str_replace('EXTRA', 'is-link=1', $bookmarklet_code)) ?>">Draft Link</a> &bull;
        <a href="javascript:<?= rawurlencode(str_replace('EXTRA', '', $bookmarklet_code)) ?>">Draft Article</a>
    </p>
    
    <p>
        Draft link code:<br/>
        <textarea>javascript:<?= h(rawurlencode(str_replace('EXTRA', 'is-link=1', $bookmarklet_code))) ?></textarea>
    </p>
    <p>
        Draft article code:<br/>
        <textarea>javascript:<?= h(rawurlencode(str_replace('EXTRA', '', $bookmarklet_code))) ?></textarea>
    </p>
    <?
    exit;
}

$url = substring_before(normalize_space($_GET['u']), ' ');
$title = normalize_space($_GET['t']);
$selection = trim($_GET['s']);
$is_link = isset($_GET['is-link']) && intval($_GET['is-link']);
$slug = trim(preg_replace('/[^a-z0-9-]+/ms', '-', strtolower(summarize($title, 60))), '-');
if (! $slug) $slug = 'draft';

if ($selection) {
    $body = "> " . str_replace("\n", "\n> ", trim($selection)) . "\n\n";
    if (! $is_link) $body = "[$title]($url):\n\n" . $body;
} else {
    $body = '';
}

$draft_contents = 
    $title . "\n" . 
    str_repeat('=', max(10, min(40, strlen($title)))) . "\n" .
    ($is_link ? "Link: " . $url . "\n" : '') .
    "publish-not-yet\n" .
    "\n" .
    $body
;

$output_path = Updater::$source_path . '/drafts';
if (! file_exists($output_path)) die("Drafts path doesn't exist: [$output_path]");
if (! is_writable($output_path)) die("Drafts path isn't writable: [$output_path]");

$output_filename = $output_path . '/' . $slug . Updater::$post_extension;
if (! file_put_contents($output_filename, $draft_contents)) die('File write failed');
if (! chmod($output_filename, 0666)) die('File permission-set failed');

// header('Content-Type: text/plain; charset=utf-8');
// echo "Saving to [$output_filename]:\n-----------------------\n$draft_contents\n------------------------\n";

?>
<html>
    <head>
        <meta http-equiv="Refresh" content="1;url=<?= h($url) ?>">
        <meta name="viewport" content="width=320"/>
        <title>Saved draft</title>
    </head>
    <body style="font: Normal 26px 'Lucida Grande', Verdana, sans-serif; text-align:center; color:#888; margin-top:100px;">
        Saved.
        <br/>
        <br/>
        <a href="<?= h($url) ?>" style="font-size: 11px; color: #aaa;">redirecting back...</a>
    </body>
</html>
