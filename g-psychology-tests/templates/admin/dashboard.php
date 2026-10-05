<?php defined( 'ABSPATH' ) || exit;
global $wpdb;
$total_respondents = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gpt_respondents" );
$total_quizzes     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gpt_quizzes WHERE status='active'" );
$today             = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}gpt_respondents WHERE DATE(created_at)=%s", date('Y-m-d') ) );
?>
<div class="wrap gpt-admin-wrap" dir="rtl" lang="fa">
    <h1 class="gpt-admin-title">
        <?php GPT_Icons::render( 'layout-dashboard', 20, 'gpt-title-icon' ); ?>
        داشبورد آزمون‌های روانشناسی
    </h1>
    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:16px;">
        <?php foreach ( [
            [ 'icon' => 'users',     'label' => 'کل کاربران',       'value' => number_format( $total_respondents ) ],
            [ 'icon' => 'file-text', 'label' => 'آزمون‌های فعال',   'value' => $total_quizzes ],
            [ 'icon' => 'clipboard-list', 'label' => 'امروز', 'value' => $today ],
        ] as $stat ) : ?>
        <div style="background:#fff;border:1px solid var(--gpt-border,#e2d9d9);border-radius:10px;padding:24px 28px;display:flex;align-items:center;gap:16px;flex:1;min-width:180px;">
            <span style="color:#910019;"><?php GPT_Icons::render( $stat['icon'], 28 ); ?></span>
            <div>
                <div style="font-size:28px;font-weight:800;color:#1a1c1b;"><?php echo esc_html( $stat['value'] ); ?></div>
                <div style="font-size:13px;color:#5a403f;"><?php echo esc_html( $stat['label'] ); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
