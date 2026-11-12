<?php
declare(strict_types=1);

/**
 * WordPress-free integration smoke test.
 * It checks option-key compatibility, hook registration, and updater isolation.
 * Run full WordPress functional tests separately before publishing.
 */
define('ABSPATH', __DIR__);
$GLOBALS['hooks'] = [];
$GLOBALS['options'] = [
    'simple_maintenance_mode_status' => 'maintenance',
    'simple_maintenance_mode_page' => 42,
    'simple_maintenance_mode_bypass_token' => 'test-token-do-not-rotate',
    'smm_bypass_duration' => 24,
    'smm_layout' => 'split-left',
];
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/simple-maintenance-mode/'; }
function plugin_basename($file) { return 'simple-maintenance-mode/' . basename($file); }
function add_action($name, $callback, $priority = 10, $accepted = 1) { $GLOBALS['hooks'][$name][] = $callback; }
function add_filter($name, $callback, $priority = 10, $accepted = 1) { $GLOBALS['hooks'][$name][] = $callback; }
function register_activation_hook($file, $callback) { $GLOBALS['activation_hook'] = $callback; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }

require dirname(__DIR__) . '/simple-maintenance-mode.php';
check(SMM_VERSION === '2.0.0', 'unreleased integration stays version 2.0.0');
check(smm_get('status') === 'maintenance', 'status key retained');
check(smm_get('page_id') === 42, 'page key retained');
check(smm_get('bypass_duration') === 24, 'smm_ key retained');
check(smm_get('layout') === 'split-left', 'layout retained');
check(smm_get_bypass_token() === 'test-token-do-not-rotate', 'bypass token retained');
check($GLOBALS['activation_hook'] === 'smm_activate', 'activation preserved');
check(isset($GLOBALS['hooks']['plugins_loaded']), 'framework scheduled for bootstrap');
foreach ($GLOBALS['hooks']['plugins_loaded'] as $callback) { $callback(); }
check(!isset($GLOBALS['hooks']['update_plugins_plugins.makingtheimpact.com']), 'legacy update hook removed');
check(!isset($GLOBALS['hooks']['update_plugins_api.nightmarketplugins.com']), 'no public key means no update checks');
check(!isset($GLOBALS['hooks']['plugins_api']), 'unsigned starter cannot advertise updates');
check(class_exists('MTI\\Plugins\\SimpleMaintenanceMode\\Framework\\Update\\SignedEnvelope'), 'signed client bundled');
check($GLOBALS['options']['simple_maintenance_mode_status'] === 'maintenance', 'no settings modified during bootstrap');
check($GLOBALS['options']['simple_maintenance_mode_bypass_token'] === 'test-token-do-not-rotate', 'no token rotation on bootstrap');
echo "PASS: WordPress-free framework integration smoke checks\n";
