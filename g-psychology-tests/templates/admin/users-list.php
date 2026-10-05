<?php
/**
 * Admin template: Users/Respondents list.
 * Layout: flex (no <table>).
 * Search: live AJAX debounced.
 * Export: CSV via admin-post.
 *
 * Available vars: $quizzes, $csv_url, $nonce
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap gpt-admin-wrap" dir="rtl" lang="fa">
    <h1 class="gpt-admin-title">
        <?php GPT_Icons::render( 'users', 20, 'gpt-title-icon' ); ?>
        لیست کاربران آزمون‌ها
    </h1>

    <div class="gpt-admin-toolbar">
        <div class="gpt-toolbar-search">
            <?php GPT_Icons::render( 'search', 16 ); ?>
            <input
                type="search"
                id="gpt-search-input"
                class="gpt-search-input"
                placeholder="جستجو با نام یا شماره تماس..."
                aria-label="جستجو در کاربران"
            >
        </div>

        <div class="gpt-toolbar-filters">
            <select id="gpt-quiz-filter" class="gpt-select-filter" aria-label="فیلتر بر اساس آزمون">
                <option value="">همه آزمون‌ها</option>
                <?php foreach ( $quizzes as $q ) : ?>
                    <option value="<?php echo (int) $q['id']; ?>">
                        <?php echo esc_html( $q['title'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="gpt-toolbar-actions">
            <a href="<?php echo esc_url( $csv_url ); ?>"
               id="gpt-csv-btn"
               class="gpt-admin-btn gpt-admin-btn-secondary"
               aria-label="دانلود فایل CSV">
                <?php GPT_Icons::render( 'download', 16 ); ?>
                خروجی CSV
            </a>
        </div>
    </div>

    <?php /* Column headers */ ?>
    <div class="gpt-list-header gpt-row" role="row" aria-label="سرستون‌ها">
        <span class="gpt-col-name"  role="columnheader">نام</span>
        <span class="gpt-col-phone" role="columnheader">تلفن</span>
        <span class="gpt-col-quiz"  role="columnheader">آزمون</span>
        <span class="gpt-col-score" role="columnheader">امتیاز</span>
        <span class="gpt-col-date"  role="columnheader">تاریخ</span>
        <span class="gpt-col-actions" role="columnheader">عملیات</span>
    </div>

    <?php /* Body — rendered by JS */ ?>
    <div class="gpt-list-body" id="gpt-list-body" role="list" aria-live="polite" aria-label="لیست کاربران">
        <div class="gpt-loading" role="status">
            <span class="gpt-admin-spinner"></span>
            در حال بارگذاری...
        </div>
    </div>

    <div class="gpt-pagination" id="gpt-pagination" aria-label="صفحه‌بندی"></div>
</div>
