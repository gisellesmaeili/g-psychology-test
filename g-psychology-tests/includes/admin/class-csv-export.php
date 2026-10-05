<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Handles CSV export of respondents.
 * Triggered via admin-post action with nonce verification.
 */
class GPT_CSV_Export {

    public static function register(): void {
        add_action( 'admin_post_gpt_export_csv', [ __CLASS__, 'export' ] );
    }

    public static function export(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }

        check_admin_referer( 'gpt_export_csv' );

        global $wpdb;

        $quiz_id = (int) ( $_GET['quiz_id'] ?? 0 );
        $search  = sanitize_text_field( $_GET['search'] ?? '' );

        $where  = [ '1=1' ];
        $params = [];

        if ( $quiz_id ) {
            $where[]  = 'rsp.quiz_id = %d';
            $params[] = $quiz_id;
        }
        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(rsp.name LIKE %s OR rsp.phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        $rows = $wpdb->get_results(
            empty( $params )
                ? "SELECT rsp.name, rsp.phone, rsp.email, rsp.age, rsp.gender, rsp.created_at,
                          q.title AS quiz_title, res.scores
                   FROM {$wpdb->prefix}gpt_respondents rsp
                   INNER JOIN {$wpdb->prefix}gpt_quizzes  q   ON q.id = rsp.quiz_id
                   LEFT  JOIN {$wpdb->prefix}gpt_results  res ON res.respondent_id = rsp.id
                   WHERE $where_sql
                   ORDER BY rsp.created_at DESC"
                : $wpdb->prepare(
                    "SELECT rsp.name, rsp.phone, rsp.email, rsp.age, rsp.gender, rsp.created_at,
                            q.title AS quiz_title, res.scores
                     FROM {$wpdb->prefix}gpt_respondents rsp
                     INNER JOIN {$wpdb->prefix}gpt_quizzes  q   ON q.id = rsp.quiz_id
                     LEFT  JOIN {$wpdb->prefix}gpt_results  res ON res.respondent_id = rsp.id
                     WHERE $where_sql
                     ORDER BY rsp.created_at DESC",
                    ...$params
                ),
            ARRAY_A
        );

        // Stream CSV
        $filename = 'psychology-respondents-' . date( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );

        $output = fopen( 'php://output', 'w' );
        // BOM for Excel UTF-8
        fputs( $output, "\xEF\xBB\xBF" );

        // Header row
        fputcsv( $output, [ 'نام', 'تلفن', 'ایمیل', 'سن', 'جنسیت', 'آزمون', 'امتیاز کل', 'جزئیات', 'تاریخ' ] );

        foreach ( $rows as $row ) {
            $scores = json_decode( $row['scores'] ?? '{}', true );
            $total  = $scores['total'] ?? '';
            $detail = '';
            if ( ! empty( $scores['components'] ) ) {
                $parts = [];
                foreach ( $scores['components'] as $comp => $score ) {
                    $parts[] = "$comp: $score";
                }
                $detail = implode( ' | ', $parts );
            }

            fputcsv( $output, [
                $row['name'],
                $row['phone'],
                $row['email'],
                $row['age'],
                self::gender_label( $row['gender'] ),
                $row['quiz_title'],
                $total,
                $detail,
                $row['created_at'],
            ] );
        }

        fclose( $output );
        exit;
    }

    private static function gender_label( string $g ): string {
        return match ( $g ) {
            'male'   => 'مرد',
            'female' => 'زن',
            'other'  => 'سایر',
            default  => '',
        };
    }
}
