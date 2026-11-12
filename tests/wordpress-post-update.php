<?php
if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress required.');
}
function postUpgradeCheck(bool $value, string $reason): void
{
    if (!$value) {
        throw new RuntimeException('Post-upgrade test failed: '.$reason);
    }
}
$path = WP_PLUGIN_DIR.'/simple-maintenance-mode/simple-maintenance-mode.php';
postUpgradeCheck(is_file($path), 'main plugin file retained');
$plugin = get_plugin_data($path);
postUpgradeCheck($plugin['Version'] === '2.0.1', 'actual installed version is 2.0.1');
postUpgradeCheck(SMM_VERSION === '2.0.1', 'new code loaded in a separate WordPress request');
postUpgradeCheck(is_plugin_active('simple-maintenance-mode/simple-maintenance-mode.php'),
    'plugin stays active');
postUpgradeCheck(smm_get('status') === 'maintenance', 'maintenance status preserved');
postUpgradeCheck((int) smm_get('page_id') === 42, 'page selection preserved');
postUpgradeCheck(smm_get('layout') === 'split-left', 'layout preserved');
postUpgradeCheck((int) smm_get('bypass_duration') === 24, 'bypass duration preserved');
postUpgradeCheck(smm_get_bypass_token() === 'ci-token-keep-unchanged', 'bypass token preserved');
postUpgradeCheck(is_file(SMM_DIR.'css/admin-style.css'), 'admin stylesheet retained');
postUpgradeCheck(is_file(SMM_DIR.'js/admin.js'), 'admin script retained');
postUpgradeCheck(is_file(SMM_DIR.'maintenance-fullscreen-template.php'), 'front-end template retained');
postUpgradeCheck(has_filter('update_plugins_api.nightmarketplugins.com') !== false,
    'signed updater still registered on new plugin');
echo "PASS: New signed release installed and original WordPress settings retained.\n";
