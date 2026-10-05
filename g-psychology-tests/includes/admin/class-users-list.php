<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Renders the admin users/respondents page.
 * Uses flex layout — no <table> elements.
 */
class GPT_Users_List {

    public function render(): void {
        global $wpdb;

        // Quizzes for filter dropdown
        $quizzes = $wpdb->get_results(
            "SELECT id, title FROM {$wpdb->prefix}gpt_quizzes WHERE status='active' ORDER BY sort_order, id",
            ARRAY_A
        );

        $csv_url = add_query_arg( [
            'action'   => 'gpt_export_csv',
            '_wpnonce' => wp_create_nonce( 'gpt_export_csv' ),
        ], admin_url( 'admin-post.php' ) );

        $nonce = wp_create_nonce( 'gpt_quiz_nonce' );

        require GPT_DIR . 'templates/admin/users-list.php';
    }
}
