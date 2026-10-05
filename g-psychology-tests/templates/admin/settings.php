<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap gpt-admin-wrap" dir="rtl" lang="fa">
    <h1 class="gpt-admin-title">
        <?php GPT_Icons::render( 'settings', 20, 'gpt-title-icon' ); ?>
        تنظیمات
    </h1>

    <?php settings_errors( 'gpt_settings' ); ?>

    <div class="gpt-editor-wrap" style="max-width:600px;margin-top:16px;">
        <form method="post">
            <?php wp_nonce_field( 'gpt_settings', 'gpt_settings_nonce' ); ?>

            <div class="gpt-editor-section">
                <label class="gpt-editor-label" for="gpt_doctor_name">نام پزشک / روانشناس</label>
                <input class="gpt-editor-input" id="gpt_doctor_name" name="gpt_doctor_name" type="text"
                    value="<?php echo esc_attr( get_option( 'gpt_doctor_name', 'دکتر راجیه دوزنده' ) ); ?>">
            </div>

            <div class="gpt-editor-section">
                <label class="gpt-editor-label" for="gpt_doctor_phone">شماره تماس (برای Floating CTA)</label>
                <input class="gpt-editor-input" id="gpt_doctor_phone" name="gpt_doctor_phone" type="tel"
                    value="<?php echo esc_attr( get_option( 'gpt_doctor_phone', '09030429138' ) ); ?>"
                    style="direction:ltr;text-align:right;">
            </div>

            <div class="gpt-editor-section">
                <label class="gpt-editor-label" for="gpt_cta_text">متن دکمه CTA</label>
                <input class="gpt-editor-input" id="gpt_cta_text" name="gpt_cta_text" type="text"
                    value="<?php echo esc_attr( get_option( 'gpt_cta_text', 'مشاوره با دکتر دوزنده' ) ); ?>">
            </div>

            <button class="gpt-admin-btn gpt-admin-btn-primary" type="submit">
                <?php GPT_Icons::render( 'save', 15 ); ?>
                ذخیره تنظیمات
            </button>
        </form>
    </div>
</div>
