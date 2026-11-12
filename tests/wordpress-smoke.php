<?php
/**
 * Executed by WP-CLI after upgrading original v2 to the framework branch.
 * This is an integration smoke test, not full browser or security testing.
 */
if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress must be loaded.');
}
$assert = static function ($condition, $description) {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $description);
    }
};
$assert(is_plugin_active('simple-maintenance-mode/simple-maintenance-mode.php'), 'same plugin folder active');
$assert(smm_get('status') === 'maintenance', 'status survives update');
$assert((int) smm_get('page_id') === 42, 'selected page survives update');
$assert(smm_get('layout') === 'split-left', 'layout survives update');
$assert((int) smm_get('bypass_duration') === 24, 'bypass duration survives update');
$assert(smm_get_bypass_token() === 'ci-token-keep-unchanged', 'bypass secret not rotated');
$assert(smm_effective_response_code() === 503, 'maintenance HTTP status');
$assert(has_filter('update_plugins_plugins.makingtheimpact.com') === false, 'old provider no longer registered');
$assert(has_filter('update_plugins_api.nightmarketplugins.com') === false, 'unsigned draft cannot check updates');
$assert(is_file(SMM_DIR . 'css/admin-style.css'), 'admin CSS present');
$assert(is_file(SMM_DIR . 'js/admin.js'), 'admin JS present');
$assert(is_file(SMM_DIR . 'maintenance-fullscreen-template.php'), 'maintenance template present');
update_option('simple_maintenance_mode_status', 'coming_soon');
$assert(smm_effective_response_code() === 200, 'coming-soon HTTP status');
update_option('simple_maintenance_mode_status', 'maintenance');
$assert(get_option('simple_maintenance_mode_bypass_token') === 'ci-token-keep-unchanged', 'bypass secret still preserved');
echo "PASS: WordPress upgrade smoke checks\n";
