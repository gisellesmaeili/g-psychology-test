<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Renders the results section after test completion.
 * Called from the frontend template via JS dynamic import.
 */
class GPT_Results {

    /**
     * Build chart-ready data structure from raw scores.
     *
     * @param  array $scores      Output of GPT_Test_Engine::calculate_scores()
     * @param  array $components  Quiz component definitions [{id, label, color}]
     * @return list<array{id,label,color,score,max,percentage}>
     */
    public static function build_chart_data( array $scores, array $components ): array {
        $charts = [];
        foreach ( $components as $comp ) {
            $comp_id = $comp['id'];
            $charts[] = [
                'id'         => $comp_id,
                'label'      => $comp['label'],
                'color'      => $comp['color'] ?? '#910019',
                'score'      => $scores['components'][ $comp_id ] ?? 0,
                'max'        => $scores['max_per'][ $comp_id ]    ?? 100,
                'percentage' => $scores['percentages'][ $comp_id ] ?? 0,
            ];
        }
        return $charts;
    }

    /**
     * Generate interpretation text from score percentages.
     * Admins can later extend this with custom ranges per quiz.
     *
     * @param  float $percentage  0-100
     */
    public static function interpret( float $percentage ): string {
        return match ( true ) {
            $percentage >= 75 => 'بالا',
            $percentage >= 50 => 'متوسط',
            $percentage >= 25 => 'پایین',
            default            => 'خیلی پایین',
        };
    }
}
