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
var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),l=d.location,e=encodeURIComponent;w.location.href='TARGETadd-draft.php?u='+e(l.href)+'&t='+e(d.title)+'&s='+e(s)+'&EXTRA';
EOF;

$bookmarklet_code = str_replace('TARGET', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/', trim($bookmarklet_code));

if (! isset($_GET['u'])) {
    ?>
    <p>
        <a href="javascript:<?= rawurlencode(str_replace('EXTRA', 'is-link=1', $bookmarklet_code)) ?>">Draft Link</a> &bull;
        <a href="javascript:<?= rawurlencode(str_replace('EXTRA', '', $bookmarklet_code)) ?>">Draft Article</a>  &bull;
        <a href="javascript:<?= rawurlencode(str_replace('EXTRA', 'is-video=1', $bookmarklet_code)) ?>">Draft Video Link</a>
    </p>
    
    <p>
        Draft link code:<br/>
        <textarea>javascript:<?= h(rawurlencode(str_replace('EXTRA', 'is-link=1', $bookmarklet_code))) ?></textarea>
    </p>
    <p>
        Draft article code:<br/>
        <textarea>javascript:<?= h(rawurlencode(str_replace('EXTRA', '', $bookmarklet_code))) ?></textarea>
    </p>
    <p>
        Draft video link code:<br/>
        <textarea>javascript:<?= h(rawurlencode(str_replace('EXTRA', 'is-video=1', $bookmarklet_code))) ?></textarea>
    </p>
    <?
    exit;
}

$url = substring_before(normalize_space($_GET['u']), ' ');
$is_video = isset($_GET['is-video']) && intval($_GET['is-video']);
$html = get_html($url);
$video = video_embed($html);
$title = normalize_space($_GET['t']);
$selection = trim($_GET['s']);
$is_link = isset($_GET['is-link']) && intval($_GET['is-link']);
$slug = trim(preg_replace('/[^a-z0-9-]+/ms', '-', strtolower(summarize($title, 60))), '-');
if (! $slug) $slug = 'draft';

if ($selection) {
    $body = "> " . str_replace("\n", "\n> ", trim($selection)) . "\n\n";
    if (! ($is_link or $is_video)) $body = "[$title]($url):\n\n" . $body;
} else {
    $body = '';
}

if ($is_video) $body = $video . $body;

// Get the HTML with cURL
function get_html($url) {
  $ch = curl_init();
  $timeout = 5;
  curl_setopt($ch,CURLOPT_URL,$url);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
  $data = curl_exec($ch);
  curl_close($ch);
  return $data;
}

function video_embed($html) {
	// Regex for parsing HTML
    $vimeo = array('#player.vimeo.com/video/([0-9]*)#i');
    $youtube = array('#/embed/([0-9a-z_-]+)#i', '#youtu.be/([0-9a-z_-]+)#i', '#/v/([0-9a-z_-]+)#i', '#v=([0-9a-z_-]+)#i');

    // Perform preg_match on Vimeo regex array
	for ($i = 0; $i < sizeof($vimeo); $i++) {
		preg_match($vimeo[$i], $html, $id);
		if ($id[1] != NULL) {
			return '<iframe src="http://player.vimeo.com/video/' . $id[1] . '?title=0&amp;byline=0&amp;portrait=0&amp;color=ff0000&amp;fullscreen=1&amp;autoplay=0" frameborder="0"></iframe>';	    	
	    }		
	}
	
	// Perform preg_match on Youtube regex array
	for ($i = 0; $i < sizeof($youtube); $i++) {
    	    preg_match($youtube[$i], $html, $id);
    	    // Return embed URL with video ID if a match is found
    	    if ($id[1] != NULL) {
	        return '<iframe src="http://www.youtube.com/embed/' . $id[1] . '?autohide=1&amp;fs=1&amp;autoplay=0&amp;iv_load_policy=3&amp;rel=0&amp;modestbranding=1&amp;showinfo=0&amp;hd=1" frameborder="0" allowfullscreen></iframe>';	
    	    }
	}
	return NULL;	
}

$draft_contents = 
    $title . "\n" . 
    str_repeat('=', max(10, min(40, strlen($title)))) . "\n" .
    (($is_link or $is_video) ? "Link: " . $url . "\n" : '') .
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
