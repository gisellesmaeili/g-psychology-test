<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Scoring engine: calculates per-component and total scores.
 */
class GPT_Test_Engine {

    private static array $quiz_cache    = [];
    private static array $options_cache = [];

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Return quiz row (with parsed components JSON) or null.
     */
    public function get_quiz( int $id ): ?array {
        if ( isset( self::$quiz_cache[ $id ] ) ) {
            return self::$quiz_cache[ $id ];
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gpt_quizzes WHERE id = %d AND status = 'active' LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $row['components_data'] = json_decode( $row['components'] ?? '[]', true ) ?: [];
        self::$quiz_cache[ $id ] = $row;
        return $row;
    }

    /**
     * Return all questions with their options for a quiz.
     *
     * @return list<array{id:int,question:string,sort_order:int,options:list<array>}>
     */
    public function get_questions( int $quiz_id ): array {
        global $wpdb;

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question, component, sort_order
                 FROM {$wpdb->prefix}gpt_questions
                 WHERE quiz_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $quiz_id
            ),
            ARRAY_A
        );

        if ( empty( $questions ) ) {
            return [];
        }

        $q_ids      = array_column( $questions, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $q_ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_id, label, score, sort_order
                 FROM {$wpdb->prefix}gpt_options
                 WHERE question_id IN ($placeholders)
                 ORDER BY question_id, sort_order ASC, id ASC",
                ...$q_ids
            ),
            ARRAY_A
        );

        // Index options by question_id
        $opts_by_q = [];
        foreach ( $options as $opt ) {
            $opts_by_q[ (int) $opt['question_id'] ][] = $opt;
        }

        foreach ( $questions as &$q ) {
            $q['options'] = $opts_by_q[ (int) $q['id'] ] ?? [];
        }

        return $questions;
    }

    /**
     * Calculate scores from submitted answers.
     *
     * @param  array<int,int>  $answers  {question_id: option_id}
     * @return array{components:array<string,int>,total:int,max:int,percentages:array<string,float>}
     */
    public function calculate_scores( int $quiz_id, array $answers ): array {
        $quiz       = $this->get_quiz( $quiz_id );
        $components = $quiz['components_data'] ?? [];

        // Build option lookup: option_id → [component, score]
        $option_map = $this->get_option_map( $quiz_id );

        // Init per-component accumulators
        $scores = [];
        $max    = [];
        foreach ( $components as $comp ) {
            $scores[ $comp['id'] ] = 0;
            $max[ $comp['id'] ]    = (int) ( $comp['max_score'] ?? 0 );
        }

        $total     = 0;
        $total_max = array_sum( $max );

        foreach ( $answers as $q_id => $opt_id ) {
            if ( ! isset( $option_map[ (int) $opt_id ] ) ) {
                continue;
            }
            $opt   = $option_map[ (int) $opt_id ];
            $comp  = $opt['component'];
            $score = (int) $opt['score'];

            if ( isset( $scores[ $comp ] ) ) {
                $scores[ $comp ] += $score;
            }
            $total += $score;
        }

        // Percentages
        $percentages = [];
        foreach ( $scores as $comp_id => $score ) {
            $comp_max = $max[ $comp_id ] ?? 0;
            $percentages[ $comp_id ] = $comp_max > 0
                ? round( ( $score / $comp_max ) * 100, 1 )
                : 0.0;
        }

        return [
            'components'  => $scores,
            'total'       => $total,
            'max'         => $total_max,
            'max_per'     => $max,
            'percentages' => $percentages,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * @return array<int,array{component:string,score:int}>
     */
    private function get_option_map( int $quiz_id ): array {
        if ( isset( self::$options_cache[ $quiz_id ] ) ) {
            return self::$options_cache[ $quiz_id ];
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.id, q.component, o.score
                 FROM {$wpdb->prefix}gpt_options o
                 INNER JOIN {$wpdb->prefix}gpt_questions q ON q.id = o.question_id
                 WHERE q.quiz_id = %d",
                $quiz_id
            ),
            ARRAY_A
        );

        $map = [];
        foreach ( $rows as $row ) {
            $map[ (int) $row['id'] ] = [
                'component' => $row['component'],
                'score'     => (int) $row['score'],
            ];
        }

        self::$options_cache[ $quiz_id ] = $map;
        return $map;
    }
}
