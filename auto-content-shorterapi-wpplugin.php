<?php
/**
 * Plugin Name: AutoContent ShorterAPI WP Plugin
 * Description: Semi-automatic editorial workflow with AI provider adapters and oziShortener integration.
 * Version: 0.1.0
 * Author: Codex
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OZI_ACWP_PLUGIN_FILE', __FILE__);
define('OZI_ACWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OZI_ACWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OZI_ACWP_VERSION', '0.1.0');

require_once OZI_ACWP_PLUGIN_DIR . 'src/Support/helpers.php';

spl_autoload_register(static function ($class) {
    $prefix = 'Ozi\\AutoContent\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative     = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $file         = OZI_ACWP_PLUGIN_DIR . 'src/' . $relativePath;
    if (file_exists($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, static function () {
    \Ozi\AutoContent\Support\Seeder::run();
});

\Ozi\AutoContent\Plugin::boot();
