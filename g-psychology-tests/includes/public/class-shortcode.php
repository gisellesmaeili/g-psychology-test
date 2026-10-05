<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Registers [psychology_test id="N"] shortcode.
 * Enqueues assets only when shortcode is rendered (not globally).
 */
class GPT_Shortcode {

    public function register(): void {
        add_shortcode( 'psychology_test', [ $this, 'render' ] );
    }

    /**
     * @param array|string $atts
     */
    public function render( $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'psychology_test' );
        $id   = (int) $atts['id'];

        if ( ! $id ) {
            return '';
        }

        $engine = new GPT_Test_Engine();
        $quiz   = $engine->get_quiz( $id );

        if ( ! $quiz ) {
            return '<p class="gpt-error">آزمون مورد نظر در دسترس نیست.</p>';
        }

        $this->enqueue_assets();

        $questions   = $engine->get_questions( $id );
        $components  = $quiz['components_data'];
        $doctor_name = esc_html( get_option( 'gpt_doctor_name',  'دکتر راجیه دوزنده' ) );
        $doctor_phone= esc_html( get_option( 'gpt_doctor_phone', '09030429138'       ) );
        $cta_text    = esc_html( get_option( 'gpt_cta_text',     'مشاوره با دکتر دوزنده' ) );

        ob_start();
        include GPT_DIR . 'templates/frontend/test-wrapper.php';
        return ob_get_clean();
    }

    private function enqueue_assets(): void {
        // Font: IRANYekanX
        wp_enqueue_style(
            'gpt-fonts',
            GPT_URL . 'assets/css/fonts.css',
            [],
            GPT_VERSION
        );

        // Design tokens + frontend styles
        wp_enqueue_style(
            'gpt-frontend',
            GPT_URL . 'assets/css/frontend.css',
            [ 'gpt-fonts' ],
            GPT_VERSION
        );

        // Test flow JS
        wp_enqueue_script(
            'gpt-app',
            GPT_URL . 'assets/js/app.js',
            [],
            GPT_VERSION,
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        // Chart.js — loaded lazily by app.js only when results screen is shown
        wp_register_script(
            'chartjs',
            GPT_URL . 'assets/js/chart.umd.min.js',
            [],
            '4.4.4',
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        // Floating CTA
        wp_enqueue_script(
            'gpt-floating-cta',
            GPT_URL . 'assets/js/floating-cta.js',
            [],
            GPT_VERSION,
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        wp_localize_script( 'gpt-app', 'GPT', [
            'rest_url'    => rest_url( 'gpt/v1/' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'chartjs_url' => GPT_URL . 'assets/js/chart.umd.min.js',
        ] );
    }
}
