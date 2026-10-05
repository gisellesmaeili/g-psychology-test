<?php
/**
 * Plugin Name:       آزمون‌های روانشناسی دکتر دوزنده
 * Plugin URI:        https://drdouzandeh.com
 * Description:       پلاگین آزمون‌های روانشناسی با مدیریت کامل سوالات، نمایش نتایج تحلیلی و پنل ادمین حرفه‌ای
 * Version:           1.0.3
 * Author:            Giselle Esmaeili
 * Author URI:        https://www.linkedin.com/in/qazal-esmaeili-developer
 * Text Domain:       g-psychology-tests
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * License:           GPL v2 or later
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'GPT_VERSION', '1.0.3' );
define( 'GPT_FILE',    __FILE__ );
define( 'GPT_DIR',     plugin_dir_path( __FILE__ ) );
define( 'GPT_URL',     plugin_dir_url( __FILE__ ) );
define( 'GPT_DB_VER',  '1.0.3' );

spl_autoload_register( static function ( string $class ): void {
    if ( ! str_starts_with( $class, 'GPT_' ) ) {
        return;
    }
    $filename = 'class-' . strtolower( str_replace( [ 'GPT_', '_' ], [ '', '-' ], $class ) ) . '.php';
    $map = [
        'class-plugin.php'          => GPT_DIR . 'includes/class-plugin.php',
        'class-activator.php'       => GPT_DIR . 'includes/class-activator.php',
        'class-deactivator.php'     => GPT_DIR . 'includes/class-deactivator.php',
        'class-icons.php'           => GPT_DIR . 'includes/class-icons.php',
        'class-rest-controller.php' => GPT_DIR . 'includes/api/class-rest-controller.php',
        'class-rest-users.php'      => GPT_DIR . 'includes/api/class-rest-users.php',
        'class-admin.php'           => GPT_DIR . 'includes/admin/class-admin.php',
        'class-users-list.php'      => GPT_DIR . 'includes/admin/class-users-list.php',
        'class-quiz-manager.php'    => GPT_DIR . 'includes/admin/class-quiz-manager.php',
        'class-csv-export.php'      => GPT_DIR . 'includes/admin/class-csv-export.php',
        'class-shortcode.php'       => GPT_DIR . 'includes/public/class-shortcode.php',
        'class-test-engine.php'     => GPT_DIR . 'includes/public/class-test-engine.php',
        'class-results.php'         => GPT_DIR . 'includes/public/class-results.php',
    ];
    if ( isset( $map[ $filename ] ) && file_exists( $map[ $filename ] ) ) {
        require_once $map[ $filename ];
    }
} );

register_activation_hook(   GPT_FILE, [ 'GPT_Activator',   'activate'   ] );
register_deactivation_hook( GPT_FILE, [ 'GPT_Deactivator', 'deactivate' ] );

function gpt_run(): void {
    require_once GPT_DIR . 'includes/class-activator.php';
    require_once GPT_DIR . 'includes/class-deactivator.php';
    require_once GPT_DIR . 'includes/class-icons.php';
    require_once GPT_DIR . 'includes/class-plugin.php';
    GPT_Plugin::get_instance()->run();
}
gpt_run();
