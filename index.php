<?php
/*
Plugin Name: Rendimiento
Plugin URI: https://github.com/franciscoblancojn/rendimiento
Description: Detecta categorías de productos vacías y páginas sin enlaces desde la home, y permite eliminarlas.
Version: 1.1.3
Author: franciscoblancojn
Author URI: https://franciscoblanco.vercel.app/
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: rendimiento
Requires at least: 5.8
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active'))
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');

require_once __DIR__ . '/libs/autoload.php';

//RND_
// RND_KEY, RND_LOG, RND_LOG_KEY y RND_LOG_COUNT los lee FWUSystemLog por nombre: no renombrarlas.
define("RND_KEY", 'RND');
define("RND_FILE", __FILE__);
define("RND_CONFIG", 'RND_CONFIG');
define("RND_CAPABILITY", 'manage_options');
define("RND_REPOSITORY", 'franciscoblancojn/rendimiento');
define("RND_LOG", true);
define("RND_LOG_KEY", "RND_LOG");
define("RND_LOG_COUNT", 100);
define("RND_BASENAME", plugin_basename(__FILE__));
define("RND_DIR", plugin_dir_path(__FILE__));
define("RND_URL", plugin_dir_url(__FILE__));

function RND_get_version()
{
    $plugin_data = get_plugin_data(__FILE__, false, false);
    return $plugin_data['Version'];
}

use franciscoblancojn\wordpress_utils\FWUUpdate;
use franciscoblancojn\wordpress_utils\FWUSystemLog;

if (is_admin()) {
    FWUUpdate::init([
        'basename' => RND_BASENAME,
        'dir' => RND_DIR,
        'file' => "index.php",
        'path_repository' => RND_REPOSITORY,
        'branch' => 'master',
        'token_array_split' => ["g", "h", "p", "_", "G", "4", "W", "E", "W", "F", "p", "V", "U", "E", "F", "V", "x", "F", "U", "n", "b", "M", "k", "P", "R", "x", "o", "f", "t", "Y", "8", "z", "j", "t", "4", "E", "x", "b", "i", "9"],
    ]);

    FWUSystemLog::init(RND_KEY);
}

// WooCommerce es opcional: comprobar con function_exists('WC') antes de usarlo.
require_once RND_DIR . 'src/_.php';
