<?php

class Sitemap
{
	private static $cache_path = "";
	private static $xml_file_path = "";
	private static $robots_file_path = "";

	// you can override these in config.php; e.g.:
	// Sitemap::$sitemap_name = "different_sitemap_name.xml";
	public static $sitemap_name = "sitemap.xml";
	public static $robot_name = "robots.txt";
	public static $index_changefreq = "daily";
	public static $index_priority = "1.0";
	public static $page_changefreq = "monthly";
	public static $page_priority = "0.8";
	public static $post_changefreq = "monthly";
	public static $post_priority = "0.8";

	public static function should_write_sitemap($dp)
	{
		return (!file_exists($dp . "/" . self::$sitemap_name));
	}

	public static function write_sitemap($dp, $cp)
	{
		self::$cache_path = $cp;

		self::$xml_file_path = $dp . "/" . self::$sitemap_name;
		self::$robots_file_path = $dp . "/" . self::$robot_name;

		error_log("writing sitemap (" . self::$xml_file_path . ")");
		self::write_header();
		self::entry("", time(), self::$index_changefreq, self::$index_priority);	// entry for the index page
		self::process_cache();
		self::write_closing();

		self::generate_robot_file();
	}

	private static function write_header()
	{
		$t = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		file_put_contents(self::$xml_file_path, $t);

		$t = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		file_put_contents(self::$xml_file_path, $t, FILE_APPEND);
	}

	private static function write_closing()
	{
		$t = '</urlset>' . "\n";
		file_put_contents(self::$xml_file_path, $t, FILE_APPEND);
	}

	private static function process_cache()
	{
		if (is_dir(self::$cache_path)) {
			if ($dh = opendir(self::$cache_path)) {
				while ( ($file = readdir($dh) ) !== false) {
					if (substr($file, 0, 4) != "dir-") continue;
					$fullpath = self::$cache_path . '/' . $file;
					$fileinfo = unserialize(file_get_contents($fullpath));
					foreach ($fileinfo as $key => $value) {
						$filename = pathinfo($key, PATHINFO_FILENAME);
						$parent = array_pop(split("/", pathinfo($key, PATHINFO_DIRNAME)));
						$ts = array_shift(split("-", $value));
						if ($parent == "pages")
						{
							self::entry($filename, $ts, self::$page_changefreq, self::$page_priority);
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

	private static function entry($loc, $lastmod_timestamp, $changefreq, $priority)
	{
		$entry  = "";
		$entry .= "<url>" . "\n";
		$entry .= "<loc>" . POST::$blog_url . $loc . "</loc>" . "\n";
		$entry .= "<lastmod>" . date("Y-m-d", $lastmod_timestamp) . "</lastmod>" . "\n";
		$entry .= "<changefreq>" . $changefreq . "</changefreq>" . "\n";
		$entry .= "<priority>" . $priority . "</priority>" . "\n";
		$entry .= "</url>" . "\n\n";

		file_put_contents(self::$xml_file_path, $entry, FILE_APPEND);
	}

	private static function generate_robot_file()
	{
		error_log("writing robots.txt");
		file_put_contents(self::$robots_file_path, "User-agent: *" . "\n");
		file_put_contents(self::$robots_file_path, "Disallow:" . "\n", FILE_APPEND);
		file_put_contents(self::$robots_file_path, "" . "\n", FILE_APPEND);
		file_put_contents(self::$robots_file_path, "SITEMAP: " . Post::$blog_url . self::$sitemap_name . "\n", FILE_APPEND);
	}
}
