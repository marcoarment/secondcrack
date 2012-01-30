<?php

// Create an "app" from the Twitter account you want to post to: 
//   https://dev.twitter.com/
//
// From your app's Details page, request an Access Token (bottom).
// Reload periodically until you have it.
// Paste in your values for these strings:

class TwitterPostHookCredentials
{
	public static $consumer_key = '';
	public static $consumer_secret = '';
	public static $access_token = '';
	public static $access_token_secret = '';
}

// Test your credentials by executing this file alone from the command line:
//   php -f post_twitter.php

// Edit this function to customize the formatting of the posted tweets.
// The $post array is just like the $content['post'] arrays in the templates.
// You can even change the posted URL by overwriting $url_to_use.
//
// The string returned by this function will have a space and URL appended.
// $max_characters accounts for this, and is the most UTF-8 characters YOU can
// return from this function.
//
// The twitter_summarize($text, $max_characters) function is available. It
// will trim a string at the nearest word boundary and append an ellipsis to
// fit within the number of characters if it's too long.
//
function tweet_text_before_url_for_post(array $post, $max_characters, &$url_to_use)
{
	$title = $post['post-title'];
	if (isset($post['link'])) $title = "\xE2\x86\x92 " . $title; // right arrow
	return twitter_summarize($title, $max_characters);
}


// ======== You probably don't want to edit anything below this line =========

class ExtremelyBasicSelfContainedOAuthConsumer
{
    public $consumer_key;
    public $consumer_secret;
    public $token = '';
    public $token_secret = '';

	public $request_timeout_seconds = 10;
   
	public static function hmac_sha1($data, $key)
	{
	    if (strlen($key) > 64) $key =  pack('H40', sha1($key));
	    if (strlen($key) < 64) $key = str_pad($key, 64, chr(0));
	    $ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
	    $opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));
	    return sha1($opad . pack('H40', sha1($ipad . $data)), true);
	}

    public function signature($request_method, $target_url, $post_params = array())
    {
        if ( ($qs = @parse_url($target_url, PHP_URL_QUERY)) ) {
            parse_str($qs, $get_params);
            $post_params = array_merge($get_params, $post_params);
        }
        if (isset($post_params['oauth_signature'])) unset($post_params['oauth_signature']);
        ksort($post_params);

		if (false !== ($cutpos = strpos($target_url, '?')) ) $target_url = substr($target_url, 0, $cutpos);
        $parts = array($request_method, rawurlencode($target_url));
        $vars = array();
        foreach ($post_params as $name => $val) $vars[] = rawurlencode($name) . '=' . rawurlencode($val);
        $parts[] = rawurlencode(implode('&', $vars));
        $signature_base_string = implode('&', $parts);
        return base64_encode(self::hmac_sha1($signature_base_string, $this->consumer_secret . '&' . ($this->token_secret ? $this->token_secret : '')));
    }

    public function request_parameters($request_method, $target_url, $post_params = array())
    {
        $params = array(
            'oauth_consumer_key' => $this->consumer_key,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_nonce' => sha1($this->consumer_secret . microtime(true) . $this->token_secret),
            'oauth_version' => '1.0'
        );
        if (strlen($this->token)) $params['oauth_token'] = $this->token;

        $post_params = array_merge($post_params, $params);
        $sig = $this->signature($request_method, $target_url, $post_params);
        $params['oauth_signature'] = $sig;
        return $params;
    }

    public function authorization_header_value($oauth_request_parameters)
    {
        $value = 'OAuth ';
        foreach ($oauth_request_parameters as $key => $val) {
            $value .= $key . '="' . rawurlencode($val) . '", ';
        }
        $value = rtrim($value, ', ');
        return $value;
    }

	public function http_build_query_raw(array $arr)
	{
	    $out = array();
	    foreach ($arr as $name => $val) $out[] = rawurlencode($name) . '=' . rawurlencode($val);
	    return implode('&', $out);
	}
    
    public function request($method, $endpoint, $params = array())
    {
        $oauth_parameters = $this->request_parameters($method, $endpoint, $params);
        $auth = $this->authorization_header_value($oauth_parameters);
        
		$curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->request_timeout_seconds);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->request_timeout_seconds);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FAILONERROR, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: ' . $auth, 'Expect:'));
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, self::http_build_query_raw($params));
        }
       
        $data = curl_exec($curl);
		curl_close($curl);
        return $data;
    }
}

function twitter_summarize($text, $length, $ellipsis = "\xE2\x80\xA6" /* UTF-8 &hellip; */)
{
    if ($length == 0) return $text;    
    if (mb_strlen($text, 'UTF-8') <= $length) return $text;

	$word_delimiter = ' ';
	$word_delimiter_mblen = 1;

	$length -= mb_strlen($ellipsis, 'UTF-8');
    $cut_str = mb_substr($text, 0, $length + $word_delimiter_mblen, 'UTF-8');
	if (mb_substr($cut_str, 0 - $word_delimiter_mblen) != $word_delimiter) {
		$cut_str = mb_substr($cut_str, 0, 0 - $word_delimiter_mblen);
	}
	
    $split_pos = $cut_str ? mb_strrpos($cut_str, $word_delimiter, 'UTF-8') : false;
    if ($split_pos) {
        return mb_substr($text, 0, $split_pos, 'UTF-8') . $ellipsis;
    } else {
        return $cut_str . $ellipsis;
    }
}

function tweet_link_to_post(array $post_array_for_template)
{
	$oauth = new ExtremelyBasicSelfContainedOAuthConsumer();
	$oauth->consumer_key = TwitterPostHookCredentials::$consumer_key;
	$oauth->consumer_secret = TwitterPostHookCredentials::$consumer_secret;
	$oauth->token = TwitterPostHookCredentials::$access_token;
	$oauth->token_secret = TwitterPostHookCredentials::$access_token_secret;	

	$short_url_length = 24; // Big, safe default (at time of writing, Twitter's value is actually 20)
	try {
		$config = json_decode($oauth->request('GET', 'http://api.twitter.com/1/help/configuration.json'), true);
		if (isset($config['short_url_length'])) $short_url_length = intval($config['short_url_length']);
		if ($short_url_length < 16) $short_url_length = 24; // sanity check
	} catch (Exception $e) { }

	$url_to_use = $post_array_for_template['post-absolute-permalink'];
	$tweet_text = tweet_text_before_url_for_post(
		$post_array_for_template, 
		140 - ($short_url_length + 1 /* the space before the URL */),
		$url_to_use
	);

	$tweet_text .= ' ' . $url_to_use;
	$response = $oauth->request(
		'POST', 'https://api.twitter.com/1/statuses/update.json', 
		array('trim_user' => 'true', 'status' => $tweet_text)
	);

	$post = @json_decode($response, true);
	if ($post && isset($post['id_str']) && isset($post['text'])) {
		error_log("Posted to Twitter: [{$post['text']}]");
	} else {
		error_log("Posting to Twitter failed: $response");
	}
}


$command_line_test_mode = isset($_SERVER['argv'][0]) && substr($_SERVER['argv'][0], -16) == 'post_twitter.php';
if ($command_line_test_mode) {
	$oauth = new ExtremelyBasicSelfContainedOAuthConsumer();
	$oauth->consumer_key = TwitterPostHookCredentials::$consumer_key;
	$oauth->consumer_secret = TwitterPostHookCredentials::$consumer_secret;
	$oauth->token = TwitterPostHookCredentials::$access_token;
	$oauth->token_secret = TwitterPostHookCredentials::$access_token_secret;	

	$response = $oauth->request('GET', 'https://api.twitter.com/1/account/verify_credentials.json');
	$user = @json_decode($response, true);
	
	if ($user && isset($user['error'])) {
		echo "\nTwitter returned an error: {$user['error']}\n\n";
		exit(1);
	} else if ($user && isset($user['name']) && isset($user['screen_name'])) {
		echo "\nSuccessfully authenticated as @{$user['screen_name']} ({$user['name']})\n";
	} else {
		echo "\nGot unrecognized response from Twitter:\n$response\n\n";
		exit(1);
	}
	
	if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'post') {
		tweet_link_to_post(array(
			'post-title' => 'Test post title',
			'post-absolute-permalink' => 'http://twitter.com/',
		));
		echo "\n";
	} else {
		echo "Re-run with argument 'post' to create a test post on Twitter.\n\n";
	}
} else {
	class Twitter extends Hook
	{
	    public function doHook(Post $post)
	    {
			tweet_link_to_post($post->array_for_template());
	    }
	}
}
