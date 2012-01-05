<?php
// ----- Don't edit this -----------------
require_once(dirname(__FILE__) . '/engine/Updater.php');
// ---------------------------------------


// ----- You can edit these settings: -----

date_default_timezone_set('America/New_York'); // Sometimes I hate PHP. This is one of those times.

// Meta Weblog API params
Updater::$api_blog_id = 1;
Updater::$api_blog_username = 'test';
Updater::$api_blog_password = 'test123';

Post::$blog_title = 'Marco.org';
Post::$blog_url   = 'http://www.marco.org/';
Post::$blog_description = 'I\'m Marco Arment, founder of Instapaper.';

if (__FILE__ == '/home/marco/secondcrack-staging/engine/update.php') {    
    // Production environment
    Updater::$source_path   = '/home/marco/Dropbox/staging-Marco.org';
    Template::$template_dir = '/home/marco/Dropbox/staging-Marco.org/templates';
    Updater::$dest_path     = '/home/marco/secondcrack-staging/www';
    Updater::$cache_path    = '/home/marco/secondcrack-staging/cache';
} else {
    // Local testing  
    Updater::$source_path   = '/Users/marco/staging-Marco.org';
    Template::$template_dir = '/Users/marco/staging-Marco.org/templates';
    Updater::$dest_path     = '/Users/marco/test-secondcrack/www';
    Updater::$cache_path    = '/Users/marco/test-secondcrack/cache';
}

