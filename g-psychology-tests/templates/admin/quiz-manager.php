<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap gpt-admin-wrap" dir="rtl" lang="fa">
    <h1 class="gpt-admin-title">
        <?php GPT_Icons::render( 'file-text', 20, 'gpt-title-icon' ); ?>
        مدیریت آزمون‌ها
    </h1>

    <button class="gpt-admin-btn gpt-admin-btn-primary" id="gpt-new-quiz-btn" style="margin-bottom:20px;">
        <?php GPT_Icons::render( 'plus', 16 ); ?>
        افزودن آزمون جدید
    </button>

    <div id="gpt-quiz-list"></div>
    <div id="gpt-quiz-editor" style="display:none;margin-top:24px;"></div>
</div>
