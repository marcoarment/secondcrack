<?php
// Auto-posting requires Facebook's PHP API. It can be found on GitHub.
// https://github.com/facebook/facebook-php-sdk 

require_once(dirname(__FILE__) . '/facebook-php-sdk/src/facebook.php');

// Obtaining proper credentials will take a few steps, listed below.
// More here: http://jeremygibbs.com/2012/02/11/how-to-autopost-facebook
//
// 1.) Create Facebook App
//     Go to Facebook app page: https://developers.facebook.com/apps
//     and click "Create New App" button in top right corner. Choose a 
//     name (you can leave namespace empty) and continue. Next, you will 
//     customize the app. Write down App ID and App Secret located in header.
//     In basic info section, enter email/domain from which you will be 
//     posting. Finally, declare how the app integrates with Facebook. You 
//     will likely want the website option. Enter your site's URL and save.
//
// 2.) Obtain Credentials
//     You now must authorize your app to post content on your behalf. 
//     Normally, once an app is authorized, the user must be logged in to 
//     verify permissions. You can avoid this by requesting a long-term 
//     token. To do this, you will create a simple php webpage - grab the 
//     example here: https://gist.github.com/2114528. Visit the webpage in 
//     your app, which will forward you to Facebook. You will grant 
//     permissions and be redirected back to the webpage. That page will 
//     will display your offline access token. Save this token, and put
//     it with your App ID and App Secret. Now you are good to go.

class FacebookCredentials
{
    public static $app_id       = 'YOUR APP ID';
    public static $app_secret   = 'YOUR APP SECRET';
    public static $access_token = 'YOUR ACCESS TOKEN';
}

function construct_post_text(array $post)
{
    $post_txt = $post['post-title'];
    $post_url = $post['post-absolute-permalink'];
	$post_txt .= ' - ' . $post_url;
	    
    if (isset($post['link'])) $post_txt = "\xE2\x86\x92 " . $post_txt;
    return $post_txt;
}

function post_facebook_link_to_post(array $post)
{
    $post_text = construct_post_text($post);
    
    $facebook = new Facebook(array(
        'appId' => FacebookCredentials::$app_id,
        'secret' => FacebookCredentials::$app_secret,
        'cookie' => true));
    
    $req =  array(
        'access_token' => FacebookCredentials::$access_token,
        'message' => $post_text);
    
    $res = $facebook->api('/me/feed', 'POST', $req);
}

class fb extends Hook
{
    public function doHook(Post $post)
    {
        post_facebook_link_to_post($post->array_for_template());
    }
}
?>