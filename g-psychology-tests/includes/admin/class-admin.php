<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Admin panel: registers menus, enqueues assets, and delegates to sub-pages.
 */
class GPT_Admin {

    public function register(): void {
        add_action( 'admin_menu',             [ $this, 'add_menus'    ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue'      ] );

        // Register AJAX handlers
        ( new GPT_Quiz_Manager() )->register();
        GPT_CSV_Export::register();
    }

    // ── Menus ──────────────────────────────────────────────────────────────

    public function add_menus(): void {
        $icon_b64 = 'data:image/svg+xml;base64,' . base64_encode(
            GPT_Icons::get( 'clipboard-list', 20 )
        );

        add_menu_page(
            'آزمون‌های روانشناسی',
            'آزمون روانشناسی',
            'manage_options',
            'gpt-dashboard',
            [ $this, 'page_dashboard' ],
            $icon_b64,
            30
        );

        add_submenu_page(
            'gpt-dashboard',
            'کاربران',
            GPT_Icons::get( 'users', 16 ) . '<span>کاربران</span>',
            'manage_options',
            'gpt-users',
            [ $this, 'page_users' ]
        );

        add_submenu_page(
            'gpt-dashboard',
            'مدیریت آزمون‌ها',
            GPT_Icons::get( 'file-text', 16 ) . '<span>مدیریت آزمون‌ها</span>',
            'manage_options',
            'gpt-quizzes',
            [ $this, 'page_quizzes' ]
        );

        add_submenu_page(
            'gpt-dashboard',
            'تنظیمات',
            GPT_Icons::get( 'settings', 16 ) . '<span>تنظیمات</span>',
            'manage_options',
            'gpt-settings',
            [ $this, 'page_settings' ]
        );
    }

    // ── Pages ──────────────────────────────────────────────────────────────

    public function page_dashboard(): void {
        require GPT_DIR . 'templates/admin/dashboard.php';
    }

    public function page_users(): void {
        ( new GPT_Users_List() )->render();
    }

    public function page_quizzes(): void {
        require GPT_DIR . 'templates/admin/quiz-manager.php';
    }

    public function page_settings(): void {
        if ( isset( $_POST['gpt_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['gpt_settings_nonce'] ), 'gpt_settings' ) ) {
            update_option( 'gpt_doctor_name',  sanitize_text_field( $_POST['gpt_doctor_name']  ?? '' ) );
            update_option( 'gpt_doctor_phone', sanitize_text_field( $_POST['gpt_doctor_phone'] ?? '' ) );
            update_option( 'gpt_cta_text',     sanitize_text_field( $_POST['gpt_cta_text']     ?? '' ) );
            add_settings_error( 'gpt_settings', 'saved', 'تنظیمات ذخیره شد.', 'success' );
        }
        require GPT_DIR . 'templates/admin/settings.php';
    }

    // ── Assets ─────────────────────────────────────────────────────────────

    public function enqueue( string $hook ): void {
        if ( ! str_contains( $hook, 'gpt-' ) && ! str_contains( $hook, 'page_gpt' ) ) {
            return;
        }

        wp_enqueue_style(
            'gpt-admin',
            GPT_URL . 'assets/css/admin.css',
            [],
            GPT_VERSION
        );

        wp_enqueue_script(
            'gpt-admin-users',
            GPT_URL . 'assets/js/admin/users-list.js',
            [],
            GPT_VERSION,
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        // Quiz manager page
        if ( str_contains( $hook, 'gpt-quizzes' ) ) {
            wp_enqueue_script(
                'sortablejs',
                GPT_URL . 'assets/js/sortable.min.js',
                [],
                '1.15.2',
                [ 'strategy' => 'defer', 'in_footer' => true ]
            );
            wp_enqueue_script(
                'gpt-quiz-manager',
                GPT_URL . 'assets/js/admin/quiz-manager.js',
                [ 'sortablejs' ],
                GPT_VERSION,
                [ 'strategy' => 'defer', 'in_footer' => true ]
            );
        }

        wp_localize_script( 'gpt-admin-users', 'GPT_ADMIN', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'rest_url'   => rest_url( 'gpt/v1/' ),
            'nonce'      => wp_create_nonce( 'gpt_quiz_nonce' ),
            'wp_nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }
}
