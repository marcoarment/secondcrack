<?
if (! function_exists('title_for_post_callback')) {

    // Feel free to edit these -------------------------------------------------
    
    function title_for_post_callback($post) 
    { 
        if ($post['post-type'] == 'link') {
            // For link posts, prepend a Unicode right-pointing-arrow (&rarr;)
            return "\xE2\x86\x92 " . $post['post-title'];
        } else {
            return $post['post-title'];
        }
    }

    function url_for_post_callback($post) { return $post['post-permalink-or-link']; }

    function body_for_post_callback($post)
    {
        if ($post['post-type'] == 'link') {
            // Insert little permalink infinity-symbol into RSS body for link posts
            return $post['post-body'] . "\n\n<p><a href=\"http://www.YOURDOMAINHERE.com" . $post['post-permalink'] . "\">&#8734; Permalink</a></p>";
        } else {
            return $post['post-body'];
        }
    }

    // You should probably stop editing here -----------------------------------
}

$dom = new DOMDocument('1.0', 'UTF-8');
$root = $dom->createElement('rss');
$root->setAttribute('version', '2.0');
$channel = $dom->createElement('channel');

$title_node = $dom->createElement('title');
$title_node->appendChild($dom->createTextNode($content['blog-title']));
$channel->appendChild($title_node);

$link_node = $dom->createElement('link');
$link_node->appendChild($dom->createTextNode($content['blog-url']));
$channel->appendChild($link_node);

$desc_node = $dom->createElement('description');
$desc_node->appendChild($dom->createTextNode($content['blog-description']));
$channel->appendChild($desc_node);

foreach ($content['posts'] as $post) {
    $item_node = $dom->createElement('item');

    $title_node = $dom->createElement('title');
    $title_node->appendChild($dom->createTextNode(title_for_post_callback($post)));
    $item_node->appendChild($title_node);
    
    $link_node = $dom->createElement('link');
    $link_url = url_for_post_callback($post);
    if (! isset($link_url[0]) || $link_url[0] == '/') $link_url = rtrim($content['blog-url'], '/') . $link_url;
    $link_node->appendChild($dom->createTextNode($link_url));
    $item_node->appendChild($link_node);
    
    $guid = $dom->createElement('guid');
    $guid->setAttribute('isPermaLink', 'false');
    $guid->appendChild($dom->createTextNode($post['post-permalink-or-link']));
    $item_node->appendChild($guid);

    $date_node = $dom->createElement('pubDate');
    $date_node->appendChild($dom->createTextNode($post['post-rss-date']));
    $item_node->appendChild($date_node);
    
    $desc = $dom->createElement('description');
    $desc->appendChild($dom->createTextNode(body_for_post_callback($post)));
    $item_node->appendChild($desc);
    
    $channel->appendChild($item_node);
}

$root->appendChild($channel);
$dom->appendChild($root);
echo $dom->saveXML();

