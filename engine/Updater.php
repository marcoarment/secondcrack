<?php

require_once(dirname(__FILE__) . '/Post.php');
require_once(dirname(__FILE__) . '/Hook.php');

class Updater
{
    public static $source_path;
    public static $dest_path;
    public static $cache_path;

    public static $frontpage_post_limit = 20;
    public static $frontpage_template = 'main.php';
    public static $frontpage_tag_filter = '!rss-only';
    public static $frontpage_type_filter = false;
    public static $frontpage_paginate = false;

    public static $rss_post_limit = 20;
    public static $rss_template = 'rss.php';
    public static $rss_tag_filter = '!site-only';
    public static $rss_type_filter = false;

    public static $archive_month_template = 'main.php';
    public static $archive_year_template = 'main.php';
    public static $archive_tag_filter = '!rss-only';
    public static $archive_type_filter = '!ad';
    
    public static $tag_page_post_limit = 20;
    
    public static $permalink_template = 'main.php';
    public static $tag_page_template  = 'main.php';
    public static $type_page_template = 'main.php';
    public static $page_template      = 'main.php';
    
    public static $api_blog_id = 1;
    public static $api_blog_username = '';
    public static $api_blog_password = '';

    public static $changes_were_written = false;
    
    private static $index_to_be_updated = false;
    private static $index_months_to_be_updated = array();
    private static $tags_to_be_updated = array();
    private static $types_to_be_updated = array();
    private static $posts_to_be_updated = array();
    private static $pages_to_be_updated = array();
        
    public static function posts_in_year_month($year, $month, $require_tag = false, $require_type = false)
    {
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $cache_fname = self::$cache_path . "/posts-$year-$month-" . md5($require_tag . ' ' . $require_type);
        if (file_exists($cache_fname)) {
            $files = unserialize(file_get_contents($cache_fname));
        } else {
            $all_files = self::filelist(self::$source_path . "/posts/$year/$month", true);
            ksort($all_files);
            $in_month = false;
            $files = array();
            foreach ($all_files as $fname => $info) {
                if (substr($fname, -4) != '.txt') continue;

                // Tag/type filtering
                list($ignored_hash, $type, $tags) = explode('|', $info, 3);
                $include = true;
                if ($require_type) {
                    if ($require_type[0] == '!' && $type == $require_type) $include = false;
                    else if ($require_type[0] != '!' && $type != $require_type) $include = false;
                }
                if ($require_tag) {
                    if ($require_tag[0] == '!' && in_array($require_tag, Post::parse_tag_str($tags))) $include = false;
                    else if ($require_tag[0] != '!' && ! in_array($require_tag, Post::parse_tag_str($tags))) $include = false;
                }
                if (! $include) continue;

                list($y, $m, $d) = array_map('intval', array_slice(explode('/', $fname), -3, 3));
                $d = intval(substr($d, 6));
                if ($year == $y && $month == $m) {
                    if (isset($files[$d])) $files[$d][] = $fname;
                    else $files[$d] = array($fname);
                } else {
                    if ($in_month) break;
                }
            }
            
            if (! file_exists(self::$cache_path)) mkdir_as_parent_owner(self::$cache_path, 0755, true);
            file_put_contents_as_dir_owner($cache_fname, serialize($files));
        }
        return $files;
    }
    
    public static function post_filenames_in_year_month($year, $month, $require_tag = false, $require_type = false)
    {
        $out = array();
        foreach (self::posts_in_year_month($year, $month, $require_tag, $require_type) as $day) {
            foreach ($day as $filename) $out[] = $filename;
        }
        return $out;
    }
    
    private static function resequence_post_offsets($year, $month, $day)
    {
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);

        error_log("Resequencing post offsets for $year-$month-$day");

        $prefix = self::$source_path . "/posts/$year/$month/$year$month$day-";
        $files = glob($prefix . '*.txt');
        if (count($files) > 99) {
            error_log("Cannot resequence offsets for $year-$month-$day: too many posts (" . count($files) . ')');
            return false;
        }
        
        $timestamps = array();
        foreach ($files as $filename) {
            $p = new Post($filename, false);
            if (in_array($p->timestamp, $timestamps)) {
                // Multiple posts with identical timestamp. No good way to handle this. Abort resequencing.
                error_log("Cannot resequence offsets for $year-$month-$day: multiple identical timestamps");
                return false;
            }
            $timestamps[$filename] = $p->timestamp;
        }
        asort($timestamps);
        
        $offset = 0;
        $slugs = array();
        $out_files = array();
        $made_changes = false;
        foreach ($timestamps as $filename => $timestamp) {
            $offset++;

            $slug = preg_replace('/-p[0-9]{1,2}$/ms', '', substr(basename($filename), 12, -4));
            if (! isset($slugs[$slug])) $slugs[$slug] = 1;
            else $slugs[$slug]++;

            $correct_filename = 
                $prefix . 
                str_pad($offset, 2, '0', STR_PAD_LEFT) . '-' . 
                $slug . ($slugs[$slug] < 2 ? '' : '-p' .$slugs[$slug]) .
                '.txt'
            ;
            if ($filename != $correct_filename) {
                error_log("file [$filename] should be [$correct_filename]");
                if (isset($out_files[$correct_filename])) throw new Exception("Target-filename collision for [$correct_filename], this should never happen");
                $made_changes = true;
                $out_files[$correct_filename] = array(file_get_contents($filename), $timestamp);
                safe_unlink($filename);
            }
            
            foreach ($out_files as $correct_filename => $a) {
                list($contents, $timestamp) = $a;
                file_put_contents_as_dir_owner($correct_filename, $contents);
                @touch($correct_filename, $timestamp);
            }
        }
        
        return $made_changes;
    }
    
    private static function filelist($dir, $parse_info_in_text_files = true, $use_cached_info = array(), &$deleted_files = array())
    {
        $out = array();
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while ( ($file = readdir($dh) ) !== false) {
                    if ($file[0] == '.') continue;
                    $fullpath = $dir . '/' . $file;
                    if (is_dir($fullpath)) {
                        $out = array_merge($out, self::filelist($fullpath, $parse_info_in_text_files, $use_cached_info, $deleted_files));
                    } else {
                        if (isset($deleted_files[$fullpath])) unset($deleted_files[$fullpath]);
                        
                        $new_stat_prefix = filemtime($fullpath) . '-' . filesize($fullpath);
                        
                        if (isset($use_cached_info[$fullpath])) {
                            $stat_prefix = substr($use_cached_info[$fullpath], 0, strpos($use_cached_info[$fullpath], '|'));
                            if ($stat_prefix == $new_stat_prefix) continue;
                        }
                        
                        $tags = '';
                        $type = '';
                        if ($parse_info_in_text_files && substr($fullpath, -4) == '.txt') {
                            $post = new Post($fullpath);
                            $tags = implode(',', $post->tags);
                            $type = $post->type;
                            $expected_fname = $post->expected_source_filename();
                            if ($expected_fname != $fullpath && ! $post->is_draft) {
                                rename($fullpath, $expected_fname);
                                $fullpath = $expected_fname;
                            }
                        }
                        
                        $out[$fullpath] = $new_stat_prefix . '|' . $type . '|' . $tags;
                    }
                }
                closedir($dh);
            }
        }
        return $out;
    }
    
    public static function changed_files_in_directory($directory)
    {
        $cache_fname = self::$cache_path . '/dir-' . md5($directory);
        $changed = array();
        if (file_exists($cache_fname)) {
            $fileinfo = unserialize(file_get_contents($cache_fname));
            $doesnt_exist_anymore = $fileinfo;
            foreach (self::filelist($directory, true, $fileinfo, $doesnt_exist_anymore) as $filename => $new_info) {
                if (! isset($fileinfo[$filename]) || $fileinfo[$filename] != $new_info) {
                    $changed[$filename] = array(isset($fileinfo[$filename]) ? $fileinfo[$filename] : '||', $new_info);
                    $fileinfo[$filename] = $new_info;
                }
            }
            
            foreach ($doesnt_exist_anymore as $deleted_fname => $old_info) {
                error_log("Deleted file: $deleted_fname");
                $changed[$deleted_fname] = array($old_info, '||');
                unset($fileinfo[$deleted_fname]);
            }
        } else {
            $fileinfo = self::filelist($directory, true);
            foreach ($fileinfo as $filename => $new_info) $changed[$filename] = array('||', $new_info);
            if (! file_exists(self::$cache_path)) mkdir_as_parent_owner(self::$cache_path, 0755, true);
        }
        if ($changed) file_put_contents_as_dir_owner($cache_fname, serialize($fileinfo));
        return $changed;
    }
    
    public static function most_recent_post_filenames($limit = 0, $require_tag = false, $require_type = false)
    {
        $cache_fname = self::$cache_path . '/dir-' . md5(self::$source_path . '/posts');
        if (! file_exists($cache_fname)) throw new Exception('Cache files not found, expected in [' . self::$cache_path . ']');
        $fileinfo = unserialize(file_get_contents($cache_fname));
        krsort($fileinfo); // reverse-chrono

        $posts = array();
        foreach ($fileinfo as $filename => $info) {
            if (substr($filename, -4) != '.txt') continue;
            list($ignored_hash, $type, $tags) = explode('|', $info, 3);
            $include = true;
            if ($require_type) {
                if ($require_type[0] == '!' && $type == substr($require_type, 1)) $include = false;
                else if ($require_type[0] != '!' && $type != $require_type) $include = false;
            }
            
            if ($require_tag) {
                if ($require_tag[0] == '!' && in_array(substr($require_tag, 1), Post::parse_tag_str($tags))) $include = false;
                else if ($require_tag[0] != '!' && ! in_array($require_tag, Post::parse_tag_str($tags))) $include = false;
            }
            
            if (! $include) continue;
            $posts[] = $filename;
            if ($limit && count($posts) >= $limit) break;
        }
        return $posts;
    }
    
    public static function set_has_posts_for_month($year, $month, $scope = '')
    {
        $cache_fname = self::$cache_path . "/months-with-posts-$scope";
        if (file_exists($cache_fname)) {
            $existing = unserialize(file_get_contents($cache_fname));
        } else {
            $existing = array();
        }
        
        $changed = false;
        if (! isset($existing[$year])) {
            $existing[$year] = array($month => true);
            $changed = true;
        } else if (! isset($existing[$year][$month])) {
            $existing[$year][$month] = true;
            $changed = true;
        }

        if ($changed) {
            if (! file_exists(self::$cache_path)) mkdir_as_parent_owner(self::$cache_path, 0755, true);
            file_put_contents_as_dir_owner($cache_fname, serialize($existing));
        }
    }
    
    public static function months_with_posts($scope = '')
    {
        $cache_fname = self::$cache_path . "/months-with-posts-$scope";
        if (! file_exists($cache_fname)) throw new Exception('Cache files not found, expected');
        return unserialize(file_get_contents($cache_fname));
    }
    
    public static function archive_array($scope = '')
    {
        $out = array();
        foreach (self::months_with_posts($scope) as $year => $months) {
            foreach ($months as $month => $x) {
                $ts = mktime(0, 0, 0, $month, 15, $year);
                $out[$year . str_pad($month, 2, '0', STR_PAD_LEFT)] = array(
                    'archives-uri' => "/$year/" . str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . $scope,
                    'archives-year' => $year,
                    'archives-month-number' => intval($month),
                    'archives-month-name' => date('F', $ts),
                    'archives-month-short-name' => date('M', $ts),
                );
            }
        }
        arsort($out);
        return $out;
    }
    
    public static function update_drafts()
    {
        foreach (self::changed_files_in_directory(self::$source_path . '/drafts') as $filename => $info) {
            self::$changes_were_written = true;
            
            if (contains($filename, '/_publish-now/') && file_exists($filename)) {
                $post = new Post($filename, false);
                $expected_fname = $post->expected_source_filename(true);
                error_log("Publishing draft $filename");
                $dir = dirname($expected_fname);
                if (! file_exists($dir)) mkdir_as_parent_owner($dir, 0755, true);
                if (file_put_contents_as_dir_owner($expected_fname, $post->normalized_source())) safe_unlink($filename);
                self::post_hooks($post);
            }

            if (! file_exists($filename)) {
                if (ends_with($filename, '.txt')) {
                    error_log("Deleted draft $filename");
                    $html_preview_filename = self::$source_path . '/drafts/_previews/' . substring_before(basename($filename), '.', true) . '.html';
                    if (file_exists($html_preview_filename)) safe_unlink($html_preview_filename);
                }
                continue;
            }

            if (substr($filename, -4) == '.txt') {
                $post = new Post($filename, true);
                if ($post->publish_now) {
                    $post = new Post($filename, false);
                    $expected_fname = $post->expected_source_filename(true);
                    error_log("Publishing draft $filename");
                    $dir = dirname($expected_fname);
                    if (! file_exists($dir)) mkdir_as_parent_owner($dir, 0755, true);
                    if (file_put_contents_as_dir_owner($expected_fname, $post->normalized_source())) safe_unlink($filename);
                    self::post_hooks($post);
                } else {
                    $post->write_permalink_page(true);
                }
            } else {
                $draft_dest_path = self::$dest_path . '/www/drafts';
                if (! file_exists($draft_dest_path)) mkdir_as_parent_owner($draft_dest_path, 0755, true);
                copy($filename, $draft_dest_path . '/' . basename($filename));
            }
        }        
    }
    
    public static function post_hooks($post)
    {
        $dir = self::$source_path . '/hooks';
        if (is_dir($dir)) {
            if ( ($dh = opendir($dir)) ) {
                while ( ($file = readdir($dh) ) !== false) {
                    if ($file[0] == '.') continue;
                    if (substr($file, 0, 5) == 'post_') {
                        $fullpath = $dir . '/' . $file;
                        require_once($fullpath);
                        $className = ucfirst(substr($file, 5, strlen($file) - 9));
                        $hook = new $className;
                        $hook->doHook($post);
                    }
                }
                closedir($dh);
            }
        }
    }
    
    public static function update_pages()
    {
        foreach (self::changed_files_in_directory(self::$source_path . '/pages') as $filename => $info) {
            self::$changes_were_written = true;
            
            if (! file_exists($filename)) {
                if (ends_with($filename, '.txt')) {
                    error_log("Deleted page $filename");
                    $dest_filename = self::$dest_path . '/' . substring_before(basename($filename), '.', true);
                    if (file_exists($dest_filename)) safe_unlink($dest_filename);
                }
                continue;
            }

            if (substr($filename, -4) == '.txt') {
                $post = new Post($filename, true);
                $post->slug = basename($filename, '.txt');
                error_log("Writing page [{$post->slug}]");
                $post->write_page(true);
            }
        }        
    }
    
    const RESEQUENCED_POSTS = -1;
    public static function update()
    {
        if (! file_exists(self::$cache_path)) {
            // Starting fresh, probably multiple resequences to do.
            // Let them all happen at once.
            $status = self::_update(false);
        }
        
        do {
            $status = self::_update();
        } while ($status == self::RESEQUENCED_POSTS);

        return $status;
    }
    
    private static function _update($restart_if_resequenced = true)
    {
        if (! file_exists(self::$dest_path)) mkdir_as_parent_owner(self::$dest_path, 0755, true);
        if (! file_exists(self::$dest_path . '/.htaccess')) copy(dirname(__FILE__) . '/default.htaccess', self::$dest_path . '/.htaccess');

        if (! file_exists(self::$source_path . '/drafts/_publish-now')) mkdir_as_parent_owner(self::$source_path . '/drafts/_publish-now', 0755, true);
        if (! file_exists(self::$source_path . '/drafts/_previews')) mkdir_as_parent_owner(self::$source_path . '/drafts/_previews', 0755, true);
        if (! file_exists(self::$source_path . '/pages')) mkdir_as_parent_owner(self::$source_path . '/pages', 0755, true);
        
        self::update_pages();
        self::update_drafts();

        foreach (self::changed_files_in_directory(self::$source_path . '/media') as $filename => $info) {
            error_log("Changed media file: $filename");
            $uri = substring_after($filename, self::$source_path);
            $dest_filename = self::$dest_path . $uri;
            $output_path = dirname($dest_filename);
            if (! file_exists($output_path)) mkdir_as_parent_owner($output_path, 0755, true);
            copy($filename, $dest_filename);
            self::$changes_were_written = true;
        }

        $resequence_days = array();
        
        foreach (self::changed_files_in_directory(self::$source_path . '/posts') as $filename => $info) {
            self::$posts_to_be_updated[$filename] = true;
            
            if (substr($filename, -4) == '.txt') {
                list($old_info, $new_info) = $info;
                list($old_hash, $old_type, $old_tags) = explode('|', $old_info, 3);
                list($new_hash, $new_type, $new_tags) = explode('|', $new_info, 3);

                if ($old_type) self::$types_to_be_updated[$old_type] = true;
                if ($new_type) self::$types_to_be_updated[$new_type] = true;

                foreach (Post::parse_tag_str($old_tags) as $tag) self::$tags_to_be_updated[$tag] = true;
                foreach (Post::parse_tag_str($new_tags) as $tag) self::$tags_to_be_updated[$tag] = true;
            }
            
            $yearpos = strpos($filename, '/posts/') + 7;
            $year = substr($filename, $yearpos, 4);
            $month = substr($filename, $yearpos + 5, 2);
            $day = substr($filename, $yearpos + 14, 2);
            $resequence_days[$year . $month . $day] = array($year, $month, $day);
            self::$index_months_to_be_updated[$year . '-' . $month] = true;
            self::$index_to_be_updated = true;
        }

        foreach (self::$posts_to_be_updated as $filename => $x) { 
            if (! file_exists($filename)) {
                // This file was deleted. Delete corresponding output file
                $filename_datestr = substr(basename($filename), 0, 8);
                if (is_numeric($filename_datestr)) {
                    $year = substr($filename_datestr, 0, 4);
                    $month = substr($filename_datestr, 4, 2);
                    $day = substr($filename_datestr, 6, 2);
                    $slug = substr(basename($filename), 12, -4);
                    $target_filename = self::$dest_path . "/$year/$month/$day/$slug";
                    if ($year && $month && $day && $slug && file_exists($target_filename)) {
                        error_log("Deleting abandoned target file: $target_filename");
                        safe_unlink($target_filename);
                    }
                }
                continue;
            }
            
            error_log("Updating post: $filename");
            self::$changes_were_written = true;

            if (substr($filename, -4) == '.txt') {
                $post = new Post($filename);
                $post->write_permalink_page();

                self::set_has_posts_for_month($post->year, $post->month);
                foreach ($post->tags as $tag) self::set_has_posts_for_month($post->year, $post->month, 'tagged-' . $tag);
                if ($post->type) self::set_has_posts_for_month($post->year, $post->month, 'type-' . $post->type);

            } else {
                $filename_datestr = substr(basename($filename), 0, 8);
                if (is_numeric($filename_datestr)) {
                    $year = intval(substr($filename_datestr, 0, 4));
                    $month = intval(substr($filename_datestr, 4, 2));
                    $day = intval(substr($filename_datestr, 6, 2));
                    if ($year && $month && $day) {
                        $output_path = self::$dest_path . "/$year/" . str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        if (! file_exists($output_path)) mkdir_as_parent_owner($output_path, 0755, true);
                        $output_filename = $output_path . '/' . basename($filename);
                        copy($filename, $output_filename);
                        $resequence_days[$year . $month . $day] = array($year, $month, $day);
                    } else {
                        error_log("Can't figure out where to put unrecognized numeric filename [$filename]");
                    }
                } else {
                    error_log("Can't figure out where to put unrecognized filename [$filename]");
                }
                
            }
        }
        
        $sequence_changed = false;
        foreach ($resequence_days as $a) {
            list($year, $month, $day) = array_map('intval', $a);
            if (self::resequence_post_offsets($year, $month, $day)) $sequence_changed = true;
        }
        
        if ($sequence_changed && $restart_if_resequenced) {
            self::$changes_were_written = true;
            error_log("Resequencing was performed. Restarting update...");
            return self::RESEQUENCED_POSTS;
        }
        
        if (! self::$index_to_be_updated) self::$index_to_be_updated = self::$index_months_to_be_updated || self::$tags_to_be_updated || self::$types_to_be_updated || self::$posts_to_be_updated;

        if (self::$index_to_be_updated) {
            error_log("Updating frontpage...");
            self::$changes_were_written = true;

            $seq_count = 0;
            if (self::$frontpage_paginate) {
                $seq_count = Post::write_index_sequence(
                    self::$dest_path . "/index", 
                    Post::$blog_title, 
                    'frontpage', 
                    Post::from_files(self::most_recent_post_filenames(0, self::$frontpage_tag_filter, self::$frontpage_type_filter)),
                    self::$frontpage_template,
                    self::archive_array(),
                    self::$frontpage_post_limit
                );
            }
            
            Post::write_index(
                self::$dest_path . "/index.html", 
                Post::$blog_title, 
                'frontpage', 
                Post::from_files(self::most_recent_post_filenames(self::$frontpage_post_limit, self::$frontpage_tag_filter, self::$frontpage_type_filter)),
                self::$frontpage_template,
                self::archive_array(),
                $seq_count
            );

            error_log("Updating RSS...");
            Post::write_index(
                self::$dest_path . "/rss.xml", 
                Post::$blog_title, 
                'rss', 
                Post::from_files(self::most_recent_post_filenames(self::$rss_post_limit, self::$rss_tag_filter, self::$rss_type_filter)),
                self::$rss_template
            );
        }

        foreach (self::$index_months_to_be_updated as $ym => $x) {
            error_log("Updating month index: $ym");
            self::$changes_were_written = true;

            list($year, $month) = explode('-', $ym);
            $posts = Post::from_files(self::post_filenames_in_year_month($year, $month, self::$archive_tag_filter, self::$archive_type_filter));
            $ts = mktime(0, 0, 0, $month, 15, $year);
            Post::write_index(
                self::$dest_path . "/$year/$month/index.html", 
                date('F Y', $ts), 
                'archive', 
                $posts, 
                self::$archive_month_template,
                self::archive_array()
            );
        }
        
        foreach (self::$tags_to_be_updated as $tag => $x) {
            if (! strlen($tag)) continue;
            error_log("Updating tag: $tag");
            self::$changes_were_written = true;

            $seq_count = Post::write_index_sequence(
                self::$dest_path . "/tagged-$tag", 
                Post::$blog_title, 
                'frontpage', 
                Post::from_files(self::most_recent_post_filenames(0, $tag, self::$archive_tag_filter)),
                self::$tag_page_template,
                self::archive_array(),
                self::$tag_page_post_limit
            );

            Post::write_index(
                self::$dest_path . "/tagged-$tag.html", 
                Post::$blog_title, 
                'tag', 
                Post::from_files(self::most_recent_post_filenames(self::$frontpage_post_limit, $tag, self::$archive_tag_filter)),
                self::$tag_page_template,
                self::archive_array('tagged-' . $tag),
                $seq_count
            );

            Post::write_index(
                self::$dest_path . "/tagged-$tag.xml", 
                Post::$blog_title, 
                'tag', 
                Post::from_files(self::most_recent_post_filenames(self::$rss_post_limit, $tag, self::$archive_tag_filter)),
                self::$rss_template,
                self::archive_array('tagged-' . $tag)
            );

            $months_with_posts = self::months_with_posts('tagged-' . $tag);
            foreach (self::$index_months_to_be_updated as $ym => $x) {
                list($year, $month) = explode('-', $ym);
                if (! isset($months_with_posts[$year]) || ! isset($months_with_posts[$year][intval($month)])) continue;
                error_log("Updating month index: $ym for tag: $tag");
                $posts = Post::from_files(self::post_filenames_in_year_month($year, $month, $tag, self::$archive_type_filter));
                $ts = mktime(0, 0, 0, $month, 15, $year);
                Post::write_index(
                    self::$dest_path . "/$year/$month/tagged-$tag.html",
                    date('F Y', $ts),
                    'tag',
                    $posts,
                    self::$tag_page_template,
                    self::archive_array('tagged-' . $tag)
                );
            }
        }

        foreach (self::$types_to_be_updated as $type => $x) {
            if (! strlen($type)) continue;
            error_log("Updating type: $type");
            self::$changes_were_written = true;

            Post::write_index(
                self::$dest_path . "/type-$type.html", 
                Post::$blog_title, 
                'type', 
                Post::from_files(self::most_recent_post_filenames(self::$frontpage_post_limit, self::$archive_type_filter, $type)),
                self::$type_page_template,
                self::archive_array('type-' . $type)
            );

            Post::write_index(
                self::$dest_path . "/type-$type.xml", 
                Post::$blog_title, 
                'type', 
                Post::from_files(self::most_recent_post_filenames(self::$rss_post_limit, self::$archive_type_filter, $type)),
                self::$rss_template,
                self::archive_array('type-' . $type)
            );

            $months_with_posts = self::months_with_posts('type-' . $type);
            foreach (self::$index_months_to_be_updated as $ym => $x) {
                list($year, $month) = explode('-', $ym);
                if (! isset($months_with_posts[$year]) || ! isset($months_with_posts[$year][intval($month)])) continue;
                error_log("Updating month index: $ym for type: $type");
                $posts = Post::from_files(self::post_filenames_in_year_month($year, $month, self::$archive_tag_filter, $type));
                $ts = mktime(0, 0, 0, $month, 15, $year);
                Post::write_index(
                    self::$dest_path . "/$year/$month/type-$type.html",
                    date('F Y', $ts),
                    'type',
                    $posts,
                    self::$type_page_template,
                    self::archive_array('type-' . $type)
                );
            }
        }
    }
}

