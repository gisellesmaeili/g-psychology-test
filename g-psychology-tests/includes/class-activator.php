<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

class GPT_Activator {

    public static function activate(): void {
        self::create_tables();
        self::set_defaults();
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix;

        $sql = "
        CREATE TABLE IF NOT EXISTS {$p}gpt_quizzes (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(500)    NOT NULL,
            description LONGTEXT,
            slug        VARCHAR(200)    NOT NULL,
            components  LONGTEXT        COMMENT 'JSON: [{id,label,color,max_score}]',
            instructions LONGTEXT,
            status      ENUM('active','draft') NOT NULL DEFAULT 'active',
            sort_order  INT             NOT NULL DEFAULT 0,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slug (slug),
            KEY idx_status (status),
            KEY idx_sort (sort_order)
        ) ENGINE=InnoDB $charset;

        CREATE TABLE IF NOT EXISTS {$p}gpt_questions (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_id     BIGINT UNSIGNED NOT NULL,
            question    LONGTEXT        NOT NULL,
            sort_order  INT             NOT NULL DEFAULT 0,
            KEY idx_quiz (quiz_id),
            KEY idx_sort (quiz_id, sort_order)
        ) ENGINE=InnoDB $charset;

        CREATE TABLE IF NOT EXISTS {$p}gpt_options (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question_id BIGINT UNSIGNED NOT NULL,
            label       TEXT            NOT NULL,
            component   VARCHAR(100)    NOT NULL DEFAULT '',
            score       TINYINT         NOT NULL DEFAULT 1,
            sort_order  INT             NOT NULL DEFAULT 0,
            KEY idx_question (question_id)
        ) ENGINE=InnoDB $charset;

        CREATE TABLE IF NOT EXISTS {$p}gpt_respondents (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(300)    NOT NULL DEFAULT '',
            phone       VARCHAR(30)     NOT NULL,
            email       VARCHAR(300)    NOT NULL DEFAULT '',
            age         TINYINT UNSIGNED,
            gender      ENUM('male','female','other','') NOT NULL DEFAULT '',
            quiz_id     BIGINT UNSIGNED NOT NULL,
            ip_address  VARCHAR(45)     NOT NULL DEFAULT '',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_phone  (phone),
            KEY idx_quiz   (quiz_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB $charset;

        CREATE TABLE IF NOT EXISTS {$p}gpt_results (
            id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            respondent_id  BIGINT UNSIGNED NOT NULL,
            quiz_id        BIGINT UNSIGNED NOT NULL,
            answers        LONGTEXT        COMMENT 'JSON: {question_id: option_id}',
            scores         LONGTEXT        COMMENT 'JSON: {component: score, total: N, max: M}',
            interpretation LONGTEXT,
            token          VARCHAR(64)     NOT NULL,
            completed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_token  (token),
            UNIQUE KEY uq_resp   (respondent_id, quiz_id),
            KEY idx_quiz_date (quiz_id, completed_at)
        ) ENGINE=InnoDB $charset;
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( array_filter( array_map( 'trim', explode( ';', $sql ) ) ) as $statement ) {
            dbDelta( $statement . ';' );
        }

        update_option( 'gpt_db_version', GPT_DB_VER );
    }

    private static function set_defaults(): void {
        $defaults = [
            'gpt_doctor_name'  => 'دکتر راجیه دوزنده',
            'gpt_doctor_phone' => '09030429138',
            'gpt_cta_text'     => 'مشاوره با دکتر دوزنده',
            'gpt_per_page'     => 20,
        ];
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }
}
