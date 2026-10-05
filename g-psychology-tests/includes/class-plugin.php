<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Core plugin bootstrap. Singleton. Loads all sub-systems.
 */
final class GPT_Plugin {

    private static ?self $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function run(): void {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'plugins_loaded', [ $this, 'init_components' ] );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'g-psychology-tests',
            false,
            dirname( plugin_basename( GPT_FILE ) ) . '/languages/'
        );
    }

    public function init_components(): void {
        // REST API
        require_once GPT_DIR . 'includes/api/class-rest-controller.php';
        require_once GPT_DIR . 'includes/api/class-rest-users.php';
        ( new GPT_Rest_Controller() )->register();
        ( new GPT_Rest_Users() )->register();

        // Public shortcode
        require_once GPT_DIR . 'includes/public/class-test-engine.php';
        require_once GPT_DIR . 'includes/public/class-results.php';
        require_once GPT_DIR . 'includes/public/class-shortcode.php';
        ( new GPT_Shortcode() )->register();

        // Admin
        if ( is_admin() ) {
            require_once GPT_DIR . 'includes/admin/class-csv-export.php';
            require_once GPT_DIR . 'includes/admin/class-users-list.php';
            require_once GPT_DIR . 'includes/admin/class-quiz-manager.php';
            require_once GPT_DIR . 'includes/admin/class-admin.php';
            ( new GPT_Admin() )->register();
        }
    }
}
