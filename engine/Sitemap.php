<?php

/**
* 
*/
class Sitemap
{
	private static $cache_path = "";
	private $xml_file_path = "";

	// override these in config.php
	public static $index_changefreq = "daily";
	public static $index_priority = "1.0";
	public static $page_changefreq = "monthly";
	public static $page_priority = "0.8";
	public static $post_changefreq = "monthly";
	public static $post_priority = "0.8";

	function __construct($dp, $cp)
	{
		self::$cache_path = $cp;

		$this->xml_file_path = $dp . "/sitemap.xml";

		self::write_header();
		self::entry("", time(), self::$index_changefreq, self::$index_priority);	// entry for the index page
		self::process_cache();
		self::write_closing();
	}

	function write_header()
	{
		$t = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		file_put_contents($this->xml_file_path, $t);

		$t = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		file_put_contents($this->xml_file_path, $t, FILE_APPEND);
	}

	function write_closing()
	{
		$t = '</urlset>' . "\n";
		file_put_contents($this->xml_file_path, $t, FILE_APPEND);
	}

	function process_cache()
	{
		error_log("generating sitemap...");
		if (is_dir(self::$cache_path)) {
			if ($dh = opendir(self::$cache_path)) {
				while ( ($file = readdir($dh) ) !== false) {
					if (substr($file, 0, 4) != "dir-") continue;
					$fullpath = self::$cache_path . '/' . $file;
					$fileinfo = unserialize(file_get_contents($fullpath));
#					print_r($fileinfo);
					foreach ($fileinfo as $key => $value) {
						$filename = pathinfo($key, PATHINFO_FILENAME);
						$parent = array_pop(split("/", pathinfo($key, PATHINFO_DIRNAME)));
						$ts = array_shift(split("-", $value));
						if ($parent == "pages")
						{
							self::entry("pages/" . $filename, $ts, self::$page_changefreq, self::$page_priority);
						}
						elseif (pathinfo($key, PATHINFO_EXTENSION) == substr(UPDATER::$post_extension, 1))
						{
							$location = substr($filename, 0, 4) . "/" . substr($filename, 4, 2) . "/" . substr($filename, 6, 2) . "/";
							$location .= substr($filename, 12);
							self::entry($location, $ts, self::$post_changefreq, self::$post_priority);
						}
					}
				}
			}
		}


	}

	function entry($loc, $lastmod_timestamp, $changefreq, $priority)
	{
		$entry  = "";
		$entry .= "<url>" . "\n";
		$entry .= "<loc>" . POST::$blog_url . $loc . "</loc>" . "\n";
		$entry .= "<lastmod>" . date("Y/m/d", $lastmod_timestamp) . "</lastmod>" . "\n";
		$entry .= "<changefreq>" . $changefreq . "</changefreq>" . "\n";
		$entry .= "<priority>" . $priority . "</priority>" . "\n";
		$entry .= "</url>" . "\n\n";

		file_put_contents($this->xml_file_path, $entry, FILE_APPEND);
	}
}
