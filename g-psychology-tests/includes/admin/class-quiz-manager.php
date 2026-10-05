<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Admin AJAX handlers for quiz/question/option CRUD.
 */
class GPT_Quiz_Manager {

    public function register(): void {
        $actions = [
            'gpt_save_quiz'       => 'save_quiz',
            'gpt_delete_quiz'     => 'delete_quiz',
            'gpt_get_quiz'        => 'get_quiz',
            'gpt_reorder_questions' => 'reorder_questions',
        ];

        foreach ( $actions as $action => $method ) {
            add_action( "wp_ajax_$action", [ $this, $method ] );
        }
    }

    // ── Save Quiz (create or update) ───────────────────────────────────────

    public function save_quiz(): void {
        $this->verify_nonce( 'gpt_quiz_nonce' );

        global $wpdb;
        $p = $wpdb->prefix;

        $id          = (int) ( $_POST['id'] ?? 0 );
        $title       = sanitize_text_field( $_POST['title'] ?? '' );
        $description = wp_kses_post( $_POST['description'] ?? '' );
        $instructions= wp_kses_post( $_POST['instructions'] ?? '' );
        $slug        = sanitize_title( $_POST['slug'] ?? $title );
        $status      = in_array( $_POST['status'] ?? '', [ 'active', 'draft' ], true ) ? $_POST['status'] : 'active';
        $components  = $_POST['components'] ?? '[]';
        $questions   = json_decode( stripslashes( $_POST['questions'] ?? '[]' ), true );

        if ( ! $title ) {
            wp_send_json_error( [ 'message' => 'عنوان آزمون الزامی است.' ] );
        }

        // Upsert quiz
        $quiz_data = [
            'title'        => $title,
            'description'  => $description,
            'instructions' => $instructions,
            'slug'         => $slug,
            'components'   => is_string( $components ) ? $components : wp_json_encode( $components ),
            'status'       => $status,
        ];
        $quiz_fmt = [ '%s', '%s', '%s', '%s', '%s', '%s' ];

        if ( $id ) {
            $wpdb->update( "{$p}gpt_quizzes", $quiz_data, [ 'id' => $id ], $quiz_fmt, [ '%d' ] );
        } else {
            $quiz_data['sort_order'] = (int) $wpdb->get_var( "SELECT COALESCE(MAX(sort_order),0)+1 FROM {$p}gpt_quizzes" );
            $wpdb->insert( "{$p}gpt_quizzes", $quiz_data, [ ...$quiz_fmt, '%d' ] );
            $id = (int) $wpdb->insert_id;
        }

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'خطا در ذخیره آزمون.' ] );
        }

        // Sync questions & options
        if ( is_array( $questions ) ) {
            $this->sync_questions( $id, $questions );
        }

        wp_send_json_success( [ 'quiz_id' => $id, 'message' => 'آزمون با موفقیت ذخیره شد.' ] );
    }

    // ── Delete Quiz ────────────────────────────────────────────────────────

    public function delete_quiz(): void {
        $this->verify_nonce( 'gpt_quiz_nonce' );

        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'شناسه آزمون نامعتبر است.' ] );
        }

        $p = $wpdb->prefix;

        // Cascade delete
        $q_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT id FROM {$p}gpt_questions WHERE quiz_id = %d", $id )
        );

        if ( $q_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $q_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$p}gpt_options WHERE question_id IN ($placeholders)", ...$q_ids ) );
        }

        $wpdb->delete( "{$p}gpt_questions",  [ 'quiz_id' => $id ], [ '%d' ] );
        $wpdb->delete( "{$p}gpt_results",    [ 'quiz_id' => $id ], [ '%d' ] );
        $wpdb->delete( "{$p}gpt_respondents",[ 'quiz_id' => $id ], [ '%d' ] );
        $wpdb->delete( "{$p}gpt_quizzes",    [ 'id'      => $id ], [ '%d' ] );

        wp_send_json_success( [ 'message' => 'آزمون حذف شد.' ] );
    }

    // ── Get Quiz (for editor) ──────────────────────────────────────────────

    public function get_quiz(): void {
        $this->verify_nonce( 'gpt_quiz_nonce' );

        $engine = new GPT_Test_Engine();
        $id     = (int) ( $_GET['id'] ?? 0 );

        $quiz = $engine->get_quiz( $id );
        if ( ! $quiz ) {
            // Try draft too
            global $wpdb;
            $quiz = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gpt_quizzes WHERE id = %d LIMIT 1", $id ),
                ARRAY_A
            );
        }

        if ( ! $quiz ) {
            wp_send_json_error( [ 'message' => 'آزمون یافت نشد.' ] );
        }

        $quiz['components_data'] = json_decode( $quiz['components'] ?? '[]', true ) ?: [];

        global $wpdb;
        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gpt_questions WHERE quiz_id = %d ORDER BY sort_order, id",
                $id
            ),
            ARRAY_A
        );

        foreach ( $questions as &$q ) {
            $q['options'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gpt_options WHERE question_id = %d ORDER BY sort_order, id",
                    $q['id']
                ),
                ARRAY_A
            );
        }

        wp_send_json_success( [ 'quiz' => $quiz, 'questions' => $questions ] );
    }

    // ── Reorder Questions ──────────────────────────────────────────────────

    public function reorder_questions(): void {
        $this->verify_nonce( 'gpt_quiz_nonce' );

        global $wpdb;
        $order = $_POST['order'] ?? []; // [{id, sort_order}]

        foreach ( (array) $order as $item ) {
            $wpdb->update(
                $wpdb->prefix . 'gpt_questions',
                [ 'sort_order' => (int) $item['sort_order'] ],
                [ 'id' => (int) $item['id'] ],
                [ '%d' ], [ '%d' ]
            );
        }

        wp_send_json_success();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Sync questions array to DB (full replace strategy per quiz).
     *
     * @param list<array{id?:int,question:string,sort_order:int,options:list<array>}> $questions
     */
    private function sync_questions( int $quiz_id, array $questions ): void {
        global $wpdb;
        $p = $wpdb->prefix;

        // Collect submitted question IDs to determine which to delete
        $submitted_ids = array_filter( array_column( $questions, 'id' ) );

        // Delete removed questions
        if ( $submitted_ids ) {
            $ph = implode( ',', array_fill( 0, count( $submitted_ids ), '%d' ) );
            $old_ids = $wpdb->get_col(
                $wpdb->prepare( "SELECT id FROM {$p}gpt_questions WHERE quiz_id = %d AND id NOT IN ($ph)", $quiz_id, ...$submitted_ids )
            );
        } else {
            $old_ids = $wpdb->get_col(
                $wpdb->prepare( "SELECT id FROM {$p}gpt_questions WHERE quiz_id = %d", $quiz_id )
            );
        }

        if ( $old_ids ) {
            $ph = implode( ',', array_fill( 0, count( $old_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$p}gpt_options WHERE question_id IN ($ph)", ...$old_ids ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$p}gpt_questions WHERE id IN ($ph)", ...$old_ids ) );
        }

        foreach ( $questions as $sort => $q ) {
            $q_text = sanitize_textarea_field( $q['question'] ?? '' );
            if ( ! $q_text ) continue;

            $q_id = (int) ( $q['id'] ?? 0 );

            if ( $q_id ) {
                $wpdb->update(
                    "{$p}gpt_questions",
                    [ 'question' => $q_text, 'sort_order' => $sort ],
                    [ 'id' => $q_id ],
                    [ '%s', '%d' ], [ '%d' ]
                );
            } else {
                $wpdb->insert(
                    "{$p}gpt_questions",
                    [ 'quiz_id' => $quiz_id, 'question' => $q_text, 'sort_order' => $sort ],
                    [ '%d', '%s', '%d' ]
                );
                $q_id = (int) $wpdb->insert_id;
            }

            if ( ! $q_id ) continue;

            // Sync options
            $this->sync_options( $q_id, $q['options'] ?? [] );
        }
    }

    private function sync_options( int $question_id, array $options ): void {
        global $wpdb;
        $p = $wpdb->prefix;

        $submitted_ids = array_filter( array_column( $options, 'id' ) );

        if ( $submitted_ids ) {
            $ph = implode( ',', array_fill( 0, count( $submitted_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$p}gpt_options WHERE question_id = %d AND id NOT IN ($ph)", $question_id, ...$submitted_ids ) );
        } else {
            $wpdb->delete( "{$p}gpt_options", [ 'question_id' => $question_id ], [ '%d' ] );
        }

        foreach ( $options as $sort => $opt ) {
            $label     = sanitize_text_field( $opt['label']     ?? '' );
            $component = sanitize_text_field( $opt['component'] ?? '' );
            $score     = (int) ( $opt['score'] ?? 1 );
            $opt_id    = (int) ( $opt['id']    ?? 0 );

            if ( ! $label ) continue;

            $data = [ 'label' => $label, 'component' => $component, 'score' => $score, 'sort_order' => $sort ];
            $fmt  = [ '%s', '%s', '%d', '%d' ];

            if ( $opt_id ) {
                $wpdb->update( "{$p}gpt_options", $data, [ 'id' => $opt_id ], $fmt, [ '%d' ] );
            } else {
                $wpdb->insert( "{$p}gpt_options", array_merge( [ 'question_id' => $question_id ], $data ), [ '%d', ...$fmt ] );
            }
        }
    }

    private function verify_nonce( string $action ): void {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( $action, 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ], 403 );
        }
    }
}
