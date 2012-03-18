<?php
define('LOCK_FILE', isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '/tmp/secondcrack-updater.pid');

// Ensure that no other instances are running
if (file_exists(LOCK_FILE) &&
    ($pid = intval(trim(file_get_contents(LOCK_FILE)))) &&
    posix_kill($pid, 0)
) {
    fwrite(STDERR, "Already running [pid $pid]\n");
    exit(1);
}

        function closeupp() {
            try { unlink(LOCK_FILE); } catch (Exception $e) {
                fwrite(STDERR, "Cannot remove lock file [" . LOCK_FILE . "]: " . $e->getMessage() . "\n");
            }
        }


if (file_put_contents(LOCK_FILE, posix_getpid())) {
    register_shutdown_function('closeupp');
} else {
    fwrite(STDERR, "Cannot write lock file: " . LOCK_FILE . "\n");
    exit(1);
}

$fdir = dirname(__FILE__);
require_once($fdir . '/Post.php');

$config_file = realpath(dirname(__FILE__) . '/..') . '/config.php';
if (! file_exists($config_file)) {
    fwrite(STDERR, "Missing config file [$config_file]\nsee [$config_file.default] for an example\n");
    exit(1);
}
require_once($config_file);

Updater::update();
exit(Updater::$changes_were_written ? 2 : 0);
