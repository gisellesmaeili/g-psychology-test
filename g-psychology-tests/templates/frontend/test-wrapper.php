<?php
/**
 * Frontend template: quiz shortcode wrapper.
 *
 * Available vars:
 *   $quiz         array  — quiz row + components_data
 *   $questions    array  — questions with options
 *   $components   array  — component definitions
 *   $doctor_name  string — escaped
 *   $doctor_phone string — escaped
 *   $cta_text     string — escaped
 */
defined( 'ABSPATH' ) || exit;

$quiz_id   = (int) $quiz['id'];
$quiz_slug = esc_attr( $quiz['slug'] );
$nonce     = wp_create_nonce( 'wp_rest' );
?>
<div class="gpt-wrap"
     id="gpt-quiz-<?php echo $quiz_id; ?>"
     data-gpt-quiz="<?php echo $quiz_id; ?>"
     dir="rtl"
     lang="fa">

    <?php /* Accessible: sr-only quiz title for SEO and screen readers */ ?>
    <h1 class="screen-reader-text"><?php echo esc_html( $quiz['title'] ); ?> — آزمون آنلاین</h1>

    <?php /* JSON-LD for SEO */ ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Quiz",
        "name": "<?php echo esc_js( $quiz['title'] ); ?>",
        "description": "<?php echo esc_js( wp_strip_all_tags( $quiz['description'] ?? '' ) ); ?>",
        "educationalAlignment": {
            "@type": "AlignmentObject",
            "alignmentType": "educationalSubject",
            "targetName": "روانشناسی"
        },
        "author": {
            "@type": "Person",
            "name": "<?php echo esc_js( $doctor_name ); ?>"
        }
    }
    </script>
</div>

<?php /* Inject localized data for app.js */ ?>
<script>
window.GPT = window.GPT || {};
window.GPT.doctor_name  = <?php echo wp_json_encode( $doctor_name ); ?>;
window.GPT.doctor_phone = <?php echo wp_json_encode( $doctor_phone ); ?>;
window.GPT.cta_text     = <?php echo wp_json_encode( $cta_text ); ?>;
</script>
