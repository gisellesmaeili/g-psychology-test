<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * REST API: admin-facing user/respondent data endpoints.
 */
class GPT_Rest_Users {

    private const NS = 'gpt/v1';

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NS, '/admin/respondents', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_respondents' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        register_rest_route( self::NS, '/admin/respondents/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_respondent' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );
    }

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public function get_respondents( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        $page     = max( 1, (int) $req->get_param( 'page' )     );
        $per_page = min( 100, max( 10, (int) ( $req->get_param( 'per_page' ) ?: get_option( 'gpt_per_page', 20 ) ) ) );
        $search   = sanitize_text_field( $req->get_param( 'search' ) ?? '' );
        $quiz_id  = (int) $req->get_param( 'quiz_id' );
        $offset   = ( $page - 1 ) * $per_page;

        $where  = [ '1=1' ];
        $params = [];

        if ( $search ) {
            $like          = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]       = '(rsp.name LIKE %s OR rsp.phone LIKE %s)';
            $params[]      = $like;
            $params[]      = $like;
        }

        if ( $quiz_id ) {
            $where[]  = 'rsp.quiz_id = %d';
            $params[] = $quiz_id;
        }

        $where_sql = implode( ' AND ', $where );

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gpt_respondents rsp WHERE $where_sql",
                ...$params
            )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT rsp.id, rsp.name, rsp.phone, rsp.age, rsp.gender, rsp.created_at,
                        q.title AS quiz_title,
                        res.scores, res.token
                 FROM {$wpdb->prefix}gpt_respondents rsp
                 INNER JOIN {$wpdb->prefix}gpt_quizzes  q   ON q.id   = rsp.quiz_id
                 LEFT  JOIN {$wpdb->prefix}gpt_results  res ON res.respondent_id = rsp.id AND res.quiz_id = rsp.quiz_id
                 WHERE $where_sql
                 ORDER BY rsp.created_at DESC
                 LIMIT %d OFFSET %d",
                ...[ ...$params, $per_page, $offset ]
            ),
            ARRAY_A
        );

        foreach ( $rows as &$row ) {
            $row['scores'] = json_decode( $row['scores'] ?? '{}', true );
        }

        return rest_ensure_response( [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'pages'      => (int) ceil( $total / $per_page ),
            'respondents'=> $rows,
        ] );
    }

    public function delete_respondent( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );

        $wpdb->delete( $wpdb->prefix . 'gpt_results',     [ 'respondent_id' => $id ], [ '%d' ] );
        $deleted = $wpdb->delete( $wpdb->prefix . 'gpt_respondents', [ 'id' => $id ], [ '%d' ] );

        if ( ! $deleted ) {
            return new WP_Error( 'not_found', 'کاربر یافت نشد.', [ 'status' => 404 ] );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }
}
