<?php

// Paste in your values for these strings:
class TumblrPostHookCredentials
{
    public static $account_email = '';
    public static $account_password = '';
    
     // The part that goes here: (blog name).tumblr.com
    public static $blog_name = 'test';
}

// Test your credentials by executing this file alone from the command line:
//   php -f post_tumblr.php

// Edit this function to customize the generated Tumblr posts.
// The $post array is just like the $content['post'] arrays in the templates.
// This function MUST return an associative array of Tumblr post variables documented here:
// http://www.tumblr.com/docs/en/api/v1#api_write
//
// Or return false to skip posting this to Tumblr.
//
function tumblr_post_for_post(array $post)
{
    if (isset($post['link'])) {
        return false;
    } else {
        return array(
            'type' => 'link',
            'name' => $post['post-title'],
            'url' => $post['post-absolute-permalink'],
            'description' => isset($post['sharing-summary']) ? $post['sharing-summary'] : ''
        );
    }
}

// ======== You probably don't want to edit anything below this line =========

function tumblr_http_build_query_raw(array $arr)
{
    $out = array();
    foreach ($arr as $name => $val) $out[] = rawurlencode($name) . '=' . rawurlencode($val);
    return implode('&', $out);
}

function post_tumblr_link_to_post(array $post_array_for_template)
{
    $tumblr_post = tumblr_post_for_post($post_array_for_template);
    if (! $tumblr_post) {
        error_log("Not posting this to Tumblr");
        return;
    }
    
    $request_data = tumblr_http_build_query_raw(
        array_merge(
            array(
                'email'     => TumblrPostHookCredentials::$account_email,
                'password'  => TumblrPostHookCredentials::$account_password,
                'group'     => TumblrPostHookCredentials::$blog_name . '.tumblr.com',
                'generator' => 'Second Crack'
            ),
        	$tumblr_post
        )
    );

    $c = curl_init('https://www.tumblr.com/api/write');
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($c, CURLOPT_TIMEOUT, 10);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_FAILONERROR, 0);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);

    // Check for success
    if ($status == 201) {
        error_log("Posted to Tumblr: http://" . TumblrPostHookCredentials::$blog_name . ".tumblr.com/post/$result");
    } else {
        error_log("Posting to Tumblr failed (HTTP $status): $response");
    }
}

$command_line_test_mode = isset($_SERVER['argv'][0]) && substr($_SERVER['argv'][0], -15) == 'post_tumblr.php';
if ($command_line_test_mode) {
    // test auth
    
    $c = curl_init('https://www.tumblr.com/api/authenticate');
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($c, CURLOPT_TIMEOUT, 10);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_FAILONERROR, 0);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, 
        tumblr_http_build_query_raw(array(
            'email'     => TumblrPostHookCredentials::$account_email,
            'password'  => TumblrPostHookCredentials::$account_password,
        ))
    );
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);

    // Check for success
    libxml_use_internal_errors(true);
    if ($status == 200 &&
        ($dom = new DOMDocument()) &&
        $dom->loadXML($result) &&
        ($tumblelogs = $dom->getElementsByTagName('tumblelog'))
    ) {
        $permitted = false;
        $names = array();
        foreach ($tumblelogs as $tumblelog) {
            if (! ($name = $tumblelog->getAttribute('name')) ) continue;
            if ($name == TumblrPostHookCredentials::$blog_name) {
                $permitted = true;
                break;
            }
            $names[] = $name;
        }
        
        if ($permitted) {
            echo "\nSuccessfully authenticated for " . TumblrPostHookCredentials::$blog_name . "\n";
        } else {
            echo "\nTumblrPostHookCredentials::\$blog_name is not set to one of your blog names.\nBlog names available on your account:\n\n\t" . implode("\n\t", $names) . "\n\n";
            exit(1);            
        }        
    } else if ($status == 403) {
        echo "\nInvalid Tumblr account email address or password.\n\n";
        exit(1);
    } else {
        echo "\nTumblr returned an error:\n$result\n\n";
        exit(1);
    }
    
    if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'post') {
        post_tumblr_link_to_post(array(
            'post-title' => 'Test post title',
            'post-absolute-permalink' => 'http://www.tumblr.com/',
            'sharing-summary' => 'This is a post about nothing.',
        ));
        echo "\n";
    } else {
        echo "Re-run with argument 'post' to create a test post on Tumblr.\n\n";
    }
} else {
    class Tumblr extends Hook
    {
        public function doHook(Post $post)
        {
            post_tumblr_link_to_post($post->array_for_template());
        }
    }
}

