<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * REST API: quiz data + answer submission.
 *
 * Namespace: gpt/v1
 * Routes:
 *   GET  /quizzes
 *   GET  /quiz/{id}
 *   POST /submit
 *   GET  /result/{token}
 */
class GPT_Rest_Controller {

    private const NS      = 'gpt/v1';
    private const RATE_LIMIT = 5;      // max submissions per window
    private const RATE_WINDOW = 600;   // seconds (10 min)

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NS, '/quizzes', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_quizzes' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NS, '/quiz/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_quiz' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0 ],
            ],
        ] );

        register_rest_route( self::NS, '/submit', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'submit_answers' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NS, '/result/(?P<token>[a-f0-9]{64})', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_result' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ── Handlers ───────────────────────────────────────────────────────────

    public function get_quizzes( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, title, description, slug, components, sort_order
             FROM {$wpdb->prefix}gpt_quizzes
             WHERE status = 'active'
             ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );

        $data = array_map( static function ( array $r ): array {
            $r['components'] = json_decode( $r['components'] ?? '[]', true ) ?: [];
            return $r;
        }, $rows );

        return rest_ensure_response( [ 'quizzes' => $data ] );
    }

    public function get_quiz( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        $engine = new GPT_Test_Engine();
        $id     = (int) $req->get_param( 'id' );
        $quiz   = $engine->get_quiz( $id );

        if ( ! $quiz ) {
            return new WP_Error( 'not_found', 'آزمون مورد نظر یافت نشد.', [ 'status' => 404 ] );
        }

        $questions = $engine->get_questions( $id );

        return rest_ensure_response( [
            'quiz'      => $quiz,
            'questions' => $questions,
        ] );
    }

    public function submit_answers( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        // Rate limiting
        $rate_err = $this->check_rate_limit();
        if ( is_wp_error( $rate_err ) ) {
            return $rate_err;
        }

        // Validate & sanitize input
        $quiz_id = (int) $req->get_param( 'quiz_id' );
        $answers = $req->get_param( 'answers' );  // {question_id: option_id}
        $user    = $req->get_param( 'user' );     // {name, phone, email, age, gender}

        if ( ! $quiz_id || ! is_array( $answers ) || empty( $answers ) ) {
            return new WP_Error( 'invalid_data', 'داده‌های ارسالی ناقص هستند.', [ 'status' => 400 ] );
        }

        $phone = sanitize_text_field( $user['phone'] ?? '' );
        if ( ! preg_match( '/^09[0-9]{9}$/', $phone ) ) {
            return new WP_Error( 'invalid_phone', 'شماره تلفن معتبر نیست.', [ 'status' => 422 ] );
        }

        $engine = new GPT_Test_Engine();
        $quiz   = $engine->get_quiz( $quiz_id );
        if ( ! $quiz ) {
            return new WP_Error( 'not_found', 'آزمون یافت نشد.', [ 'status' => 404 ] );
        }

        // Sanitize answers: int keys, int values
        $clean_answers = [];
        foreach ( $answers as $q_id => $opt_id ) {
            $clean_answers[ (int) $q_id ] = (int) $opt_id;
        }

        // Score
        $scores = $engine->calculate_scores( $quiz_id, $clean_answers );

        // Save respondent
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'gpt_respondents',
            [
                'name'       => sanitize_text_field( $user['name']   ?? '' ),
                'phone'      => $phone,
                'email'      => sanitize_email( $user['email']       ?? '' ),
                'age'        => absint( $user['age'] ?? 0 ) ?: null,
                'gender'     => in_array( $user['gender'] ?? '', [ 'male', 'female', 'other' ], true ) ? $user['gender'] : '',
                'quiz_id'    => $quiz_id,
                'ip_address' => $this->get_ip(),
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%d', '%s' ]
        );
        $respondent_id = (int) $wpdb->insert_id;

        // Generate unique token
        $token = bin2hex( random_bytes( 32 ) );

        // Save result
        $wpdb->insert(
            $wpdb->prefix . 'gpt_results',
            [
                'respondent_id'  => $respondent_id,
                'quiz_id'        => $quiz_id,
                'answers'        => wp_json_encode( $clean_answers ),
                'scores'         => wp_json_encode( $scores ),
                'interpretation' => '',
                'token'          => $token,
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        return rest_ensure_response( [
            'success' => true,
            'token'   => $token,
            'scores'  => $scores,
        ] );
    }

    public function get_result( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        global $wpdb;
        $token = sanitize_text_field( $req->get_param( 'token' ) );

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, q.title AS quiz_title, q.components AS quiz_components,
                        rsp.name, rsp.phone
                 FROM {$wpdb->prefix}gpt_results r
                 INNER JOIN {$wpdb->prefix}gpt_quizzes q     ON q.id = r.quiz_id
                 INNER JOIN {$wpdb->prefix}gpt_respondents rsp ON rsp.id = r.respondent_id
                 WHERE r.token = %s LIMIT 1",
                $token
            ),
            ARRAY_A
        );

        if ( ! $result ) {
            return new WP_Error( 'not_found', 'نتیجه‌ای با این توکن یافت نشد.', [ 'status' => 404 ] );
        }

        $result['scores']          = json_decode( $result['scores'],          true );
        $result['answers']         = json_decode( $result['answers'],         true );
        $result['quiz_components'] = json_decode( $result['quiz_components'], true );

        return rest_ensure_response( $result );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function check_rate_limit(): bool | WP_Error {
        $ip  = $this->get_ip();
        $key = 'gpt_rl_' . md5( $ip );
        $count = (int) get_transient( $key );

        if ( $count >= self::RATE_LIMIT ) {
            return new WP_Error(
                'rate_limit',
                'تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً بعداً دوباره امتحان کنید.',
                [ 'status' => 429 ]
            );
        }

        set_transient( $key, $count + 1, self::RATE_WINDOW );
        return true;
    }

    private function get_ip(): string {
        $keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
        foreach ( $keys as $k ) {
            $ip = sanitize_text_field( $_SERVER[ $k ] ?? '' );
            if ( $ip ) {
                return explode( ',', $ip )[0];
            }
        }
        return '';
    }
}
