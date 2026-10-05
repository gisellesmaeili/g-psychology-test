<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

class GPT_Deactivator {
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
