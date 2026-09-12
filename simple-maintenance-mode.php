<?php
/**
 * Plugin Name:         Simple Maintenance Mode
 * Plugin URI:          https://makingtheimpact.com/wordpress-plugins
 * Description:         A lightweight maintenance and coming-soon plugin with secure bypass links and customizable layouts.
 * Version:             2.0.0
 * Requires at least:   6.4
 * Requires PHP:        7.4
 * Author:              Making The Impact LLC
 * Author URI:          https://makingtheimpact.com
 * License:             GPL-2.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         simple-maintenance-mode
 * Domain Path:         /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SMM_VERSION', '2.0.0' );
define( 'SMM_FILE', __FILE__ );
define( 'SMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMM_URL', plugin_dir_url( __FILE__ ) );

function smm_defaults() {
    return array(
        'status'                 => 'online',
        'page_id'                => 0,
        'response_code'          => 'auto',
        'retry_after'            => 3600,
        'bypass_enabled'         => 1,
        'bypass_duration'        => 12,
        'layout'                 => 'centered-card',
        'background_type'        => 'gradient',
        'gradient_preset'        => 'ocean',
        'gradient_color_1'       => '#0f172a',
        'gradient_color_2'       => '#2563eb',
        'gradient_angle'         => 135,
        'background_color'       => '#0f172a',
        'background_image'       => '',
        'background_video'       => '',
        'background_alignment'   => 'center center',
        'background_size'        => 'cover',
        'overlay_color'          => '#000000',
        'overlay_opacity'        => 20,
        'logo_image'             => '',
        'logo_width'             => 180,
        'heading'                => '',
        'message'                => '',
        'text_color'             => '#ffffff',
        'heading_size'           => 44,
        'body_size'              => 19,
        'font_family'            => 'system',
        'font_weight'            => '700',
        'text_align'             => 'center',
        'content_width'          => 720,
        'content_bg_color'       => '#0f172a',
        'content_bg_opacity'     => 55,
        'box_padding'            => 48,
        'border_radius'          => 20,
        'box_shadow_opacity'     => 25,
        'show_countdown'         => 0,
        'countdown_date'         => '',
        'show_button'            => 0,
        'button_label'           => '',
        'button_url'             => '',
        'button_new_tab'         => 0,
        'button_bg_color'        => '#ffffff',
        'button_text_color'      => '#0f172a',
        'custom_content'         => '',
        'block_rest_api'         => 1,
        'block_xmlrpc'           => 1,
    );
}

function smm_get( $key ) {
    $defaults = smm_defaults();
    $option_map = array(
        'status' => 'simple_maintenance_mode_status',
        'page_id' => 'simple_maintenance_mode_page',
    );
    $option = isset( $option_map[ $key ] ) ? $option_map[ $key ] : 'smm_' . $key;
    return get_option( $option, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

function smm_is_active() {
    return in_array( smm_get( 'status' ), array( 'maintenance', 'coming_soon' ), true );
}

function smm_generate_bypass_token() {
    try {
        return bin2hex( random_bytes( 24 ) );
    } catch ( Exception $e ) {
        return wp_generate_password( 48, false, false );
    }
}

function smm_get_bypass_token() {
    $token = get_option( 'simple_maintenance_mode_bypass_token', '' );
    if ( empty( $token ) ) {
        $token = smm_generate_bypass_token();
        update_option( 'simple_maintenance_mode_bypass_token', $token, false );
    }
    return $token;
}

function smm_regenerate_bypass_token() {
    $token = smm_generate_bypass_token();
    update_option( 'simple_maintenance_mode_bypass_token', $token, false );
    return $token;
}

function smm_has_valid_bypass_cookie() {
    if ( ! smm_get( 'bypass_enabled' ) || empty( $_COOKIE['smm_bypass'] ) ) {
        return false;
    }
    $token = smm_get_bypass_token();
    $cookie = sanitize_text_field( wp_unslash( $_COOKIE['smm_bypass'] ) );
    return ! empty( $token ) && hash_equals( $token, $cookie );
}

function smm_handle_bypass_request() {
    if ( ! smm_get( 'bypass_enabled' ) || empty( $_GET['smm_token'] ) ) {
        return false;
    }

    $provided = sanitize_text_field( wp_unslash( $_GET['smm_token'] ) );
    $token = smm_get_bypass_token();
    if ( empty( $token ) || ! hash_equals( $token, $provided ) ) {
        return false;
    }

    $hours = max( 1, min( 168, absint( smm_get( 'bypass_duration' ) ) ) );
    setcookie(
        'smm_bypass',
        $token,
        array(
            'expires'  => time() + ( HOUR_IN_SECONDS * $hours ),
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );
    $_COOKIE['smm_bypass'] = $token;

    $clean_url = remove_query_arg( 'smm_token' );
    wp_safe_redirect( $clean_url ? $clean_url : home_url( '/' ) );
    exit;
}

function smm_request_is_allowed() {
    if ( ! smm_is_active() ) {
        return true;
    }
    if ( wp_doing_cron() || wp_doing_ajax() || is_customize_preview() ) {
        return true;
    }
    if ( is_admin() || current_user_can( 'manage_options' ) ) {
        return true;
    }
    if ( smm_has_valid_bypass_cookie() ) {
        return true;
    }

    global $pagenow;
    if ( 'wp-login.php' === $pagenow ) {
        return true;
    }

    return false;
}

function smm_effective_response_code() {
    $setting = (string) smm_get( 'response_code' );
    if ( in_array( $setting, array( '200', '503' ), true ) ) {
        return (int) $setting;
    }
    return 'maintenance' === smm_get( 'status' ) ? 503 : 200;
}

function smm_send_headers() {
    $code = smm_effective_response_code();
    status_header( $code );
    nocache_headers();
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    if ( 503 === $code ) {
        header( 'Retry-After: ' . max( 60, min( 604800, absint( smm_get( 'retry_after' ) ) ) ) );
    }
}

function smm_get_default_heading( $mode ) {
    return 'maintenance' === $mode ? __( "We'll Be Right Back", 'simple-maintenance-mode' ) : __( 'Something New Is Coming', 'simple-maintenance-mode' );
}

function smm_get_default_message( $mode ) {
    return 'maintenance' === $mode
        ? __( "We're making a few improvements to our website. Please check back shortly.", 'simple-maintenance-mode' )
        : __( "We're putting the finishing touches on our new website. Check back soon.", 'simple-maintenance-mode' );
}

function smm_get_template_settings() {
    $mode = 'maintenance' === smm_get( 'status' ) ? 'maintenance' : 'coming_soon';
    $defaults = smm_defaults();
    $settings = array( 'mode' => $mode );
    foreach ( array_keys( $defaults ) as $key ) {
        $settings[ $key ] = smm_get( $key );
    }
    if ( empty( $settings['heading'] ) ) {
        $settings['heading'] = smm_get_default_heading( $mode );
    }
    if ( empty( $settings['message'] ) ) {
        $settings['message'] = smm_get_default_message( $mode );
    }
    return $settings;
}

function smm_render_maintenance_page() {
    if ( ! smm_is_active() ) {
        return;
    }

    if ( ! empty( $_GET['smm_token'] ) ) {
        smm_handle_bypass_request();
    }

    if ( smm_request_is_allowed() ) {
        return;
    }

    $page_id = absint( smm_get( 'page_id' ) );
    if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
        if ( get_queried_object_id() === $page_id ) {
            smm_send_headers();
            return;
        }
        wp_safe_redirect( get_permalink( $page_id ), 302 );
        exit;
    }

    smm_send_headers();
    extract( smm_get_template_settings(), EXTR_SKIP );
    include SMM_DIR . 'maintenance-fullscreen-template.php';
    exit;
}
add_action( 'template_redirect', 'smm_render_maintenance_page', 0 );

function smm_block_rest_api( $result ) {
    if ( $result || ! smm_is_active() || ! smm_get( 'block_rest_api' ) || smm_request_is_allowed() ) {
        return $result;
    }
    return new WP_Error(
        'smm_maintenance',
        __( 'The site is temporarily unavailable.', 'simple-maintenance-mode' ),
        array( 'status' => smm_effective_response_code() )
    );
}
add_filter( 'rest_authentication_errors', 'smm_block_rest_api', 99 );

function smm_filter_xmlrpc_enabled( $enabled ) {
    if ( smm_is_active() && smm_get( 'block_xmlrpc' ) && ! current_user_can( 'manage_options' ) ) {
        return false;
    }
    return $enabled;
}
add_filter( 'xmlrpc_enabled', 'smm_filter_xmlrpc_enabled' );

function smm_sanitize_choice( $value, $allowed, $fallback ) {
    $value = sanitize_text_field( wp_unslash( $value ) );
    return in_array( $value, $allowed, true ) ? $value : $fallback;
}

function smm_clamp( $value, $min, $max ) {
    return max( $min, min( $max, (int) $value ) );
}

function smm_save_settings() {
    if ( ! current_user_can( 'manage_options' ) || empty( $_POST['smm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smm_nonce'] ) ), 'smm_save_settings' ) ) {
        return;
    }

    update_option( 'simple_maintenance_mode_status', smm_sanitize_choice( $_POST['status'] ?? 'online', array( 'online', 'maintenance', 'coming_soon' ), 'online' ) );
    update_option( 'simple_maintenance_mode_page', absint( $_POST['page_id'] ?? 0 ) );
    update_option( 'smm_response_code', smm_sanitize_choice( $_POST['response_code'] ?? 'auto', array( 'auto', '200', '503' ), 'auto' ) );
    update_option( 'smm_retry_after', smm_clamp( $_POST['retry_after'] ?? 3600, 60, 604800 ) );
    update_option( 'smm_bypass_enabled', isset( $_POST['bypass_enabled'] ) ? 1 : 0 );
    update_option( 'smm_bypass_duration', smm_clamp( $_POST['bypass_duration'] ?? 12, 1, 168 ) );

    if ( isset( $_POST['regenerate_token'] ) ) {
        smm_regenerate_bypass_token();
    }

    update_option( 'smm_layout', smm_sanitize_choice( $_POST['layout'] ?? 'centered-card', array( 'centered', 'centered-card', 'split-left', 'split-right', 'bottom-panel', 'minimal' ), 'centered-card' ) );
    update_option( 'smm_background_type', smm_sanitize_choice( $_POST['background_type'] ?? 'gradient', array( 'solid', 'gradient', 'image', 'video' ), 'gradient' ) );
    update_option( 'smm_gradient_preset', smm_sanitize_choice( $_POST['gradient_preset'] ?? 'ocean', array( 'ocean', 'sunset', 'aurora', 'midnight', 'purple', 'warm', 'sky', 'forest', 'slate', 'light', 'dark', 'custom' ), 'ocean' ) );
    update_option( 'smm_gradient_color_1', sanitize_hex_color( $_POST['gradient_color_1'] ?? '#0f172a' ) ?: '#0f172a' );
    update_option( 'smm_gradient_color_2', sanitize_hex_color( $_POST['gradient_color_2'] ?? '#2563eb' ) ?: '#2563eb' );
    update_option( 'smm_gradient_angle', smm_clamp( $_POST['gradient_angle'] ?? 135, 0, 360 ) );
    update_option( 'smm_background_color', sanitize_hex_color( $_POST['background_color'] ?? '#0f172a' ) ?: '#0f172a' );
    update_option( 'smm_background_image', esc_url_raw( wp_unslash( $_POST['background_image'] ?? '' ) ) );
    update_option( 'smm_background_video', esc_url_raw( wp_unslash( $_POST['background_video'] ?? '' ) ) );
    update_option( 'smm_background_alignment', smm_sanitize_choice( $_POST['background_alignment'] ?? 'center center', array( 'center center', 'top left', 'top center', 'top right', 'center left', 'center right', 'bottom left', 'bottom center', 'bottom right' ), 'center center' ) );
    update_option( 'smm_background_size', smm_sanitize_choice( $_POST['background_size'] ?? 'cover', array( 'cover', 'contain', 'auto' ), 'cover' ) );
    update_option( 'smm_overlay_color', sanitize_hex_color( $_POST['overlay_color'] ?? '#000000' ) ?: '#000000' );
    update_option( 'smm_overlay_opacity', smm_clamp( $_POST['overlay_opacity'] ?? 20, 0, 100 ) );

    update_option( 'smm_logo_image', esc_url_raw( wp_unslash( $_POST['logo_image'] ?? '' ) ) );
    update_option( 'smm_logo_width', smm_clamp( $_POST['logo_width'] ?? 180, 40, 600 ) );
    update_option( 'smm_heading', sanitize_text_field( wp_unslash( $_POST['heading'] ?? '' ) ) );
    update_option( 'smm_message', sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) );
    update_option( 'smm_text_color', sanitize_hex_color( $_POST['text_color'] ?? '#ffffff' ) ?: '#ffffff' );
    update_option( 'smm_heading_size', smm_clamp( $_POST['heading_size'] ?? 44, 20, 96 ) );
    update_option( 'smm_body_size', smm_clamp( $_POST['body_size'] ?? 19, 12, 36 ) );
    update_option( 'smm_font_family', smm_sanitize_choice( $_POST['font_family'] ?? 'system', array( 'system', 'arial', 'helvetica', 'georgia', 'times', 'verdana', 'trebuchet', 'monospace' ), 'system' ) );
    update_option( 'smm_font_weight', smm_sanitize_choice( $_POST['font_weight'] ?? '700', array( '400', '500', '600', '700', '800' ), '700' ) );
    update_option( 'smm_text_align', smm_sanitize_choice( $_POST['text_align'] ?? 'center', array( 'left', 'center', 'right' ), 'center' ) );
    update_option( 'smm_content_width', smm_clamp( $_POST['content_width'] ?? 720, 320, 1200 ) );
    update_option( 'smm_content_bg_color', sanitize_hex_color( $_POST['content_bg_color'] ?? '#0f172a' ) ?: '#0f172a' );
    update_option( 'smm_content_bg_opacity', smm_clamp( $_POST['content_bg_opacity'] ?? 55, 0, 100 ) );
    update_option( 'smm_box_padding', smm_clamp( $_POST['box_padding'] ?? 48, 0, 100 ) );
    update_option( 'smm_border_radius', smm_clamp( $_POST['border_radius'] ?? 20, 0, 60 ) );
    update_option( 'smm_box_shadow_opacity', smm_clamp( $_POST['box_shadow_opacity'] ?? 25, 0, 100 ) );

    update_option( 'smm_show_countdown', isset( $_POST['show_countdown'] ) ? 1 : 0 );
    update_option( 'smm_countdown_date', sanitize_text_field( wp_unslash( $_POST['countdown_date'] ?? '' ) ) );
    update_option( 'smm_show_button', isset( $_POST['show_button'] ) ? 1 : 0 );
    update_option( 'smm_button_label', sanitize_text_field( wp_unslash( $_POST['button_label'] ?? '' ) ) );
    update_option( 'smm_button_url', esc_url_raw( wp_unslash( $_POST['button_url'] ?? '' ) ) );
    update_option( 'smm_button_new_tab', isset( $_POST['button_new_tab'] ) ? 1 : 0 );
    update_option( 'smm_button_bg_color', sanitize_hex_color( $_POST['button_bg_color'] ?? '#ffffff' ) ?: '#ffffff' );
    update_option( 'smm_button_text_color', sanitize_hex_color( $_POST['button_text_color'] ?? '#0f172a' ) ?: '#0f172a' );
    update_option( 'smm_custom_content', wp_kses_post( wp_unslash( $_POST['custom_content'] ?? '' ) ) );
    update_option( 'smm_block_rest_api', isset( $_POST['block_rest_api'] ) ? 1 : 0 );
    update_option( 'smm_block_xmlrpc', isset( $_POST['block_xmlrpc'] ) ? 1 : 0 );

    set_transient( 'smm_settings_saved', 1, 30 );
}

function smm_admin_menu() {
    add_menu_page(
        __( 'Maintenance Mode', 'simple-maintenance-mode' ),
        __( 'Maintenance', 'simple-maintenance-mode' ),
        'manage_options',
        'simple-maintenance-mode',
        'smm_settings_page',
        'dashicons-hammer',
        81
    );
}
add_action( 'admin_menu', 'smm_admin_menu' );

function smm_plugin_action_links( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=simple-maintenance-mode' ) ) . '">' . esc_html__( 'Settings', 'simple-maintenance-mode' ) . '</a>' );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'smm_plugin_action_links' );

function smm_admin_notice() {
    if ( get_transient( 'smm_settings_saved' ) ) {
        delete_transient( 'smm_settings_saved' );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Maintenance Mode settings saved.', 'simple-maintenance-mode' ) . '</p></div>';
    }
    if ( smm_is_active() && current_user_can( 'manage_options' ) ) {
        $label = 'maintenance' === smm_get( 'status' ) ? __( 'Maintenance Mode is ON', 'simple-maintenance-mode' ) : __( 'Coming Soon mode is ON', 'simple-maintenance-mode' );
        echo '<div class="notice notice-warning"><p><strong>' . esc_html( $label ) . '.</strong> ' . esc_html__( 'Visitors cannot access the normal website.', 'simple-maintenance-mode' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=simple-maintenance-mode' ) ) . '">' . esc_html__( 'Settings', 'simple-maintenance-mode' ) . '</a></p></div>';
    }
}
add_action( 'admin_notices', 'smm_admin_notice' );

function smm_admin_bar( $bar ) {
    if ( ! current_user_can( 'manage_options' ) || ! smm_is_active() ) {
        return;
    }
    $title = 'maintenance' === smm_get( 'status' ) ? '● ' . __( 'Maintenance ON', 'simple-maintenance-mode' ) : '● ' . __( 'Coming Soon ON', 'simple-maintenance-mode' );
    $bar->add_node( array(
        'id' => 'smm-status',
        'title' => $title,
        'href' => admin_url( 'admin.php?page=simple-maintenance-mode' ),
        'meta' => array( 'class' => 'smm-admin-bar-status' ),
    ) );
}
add_action( 'admin_bar_menu', 'smm_admin_bar', 90 );

function smm_admin_assets( $hook ) {
    if ( 'toplevel_page_simple-maintenance-mode' !== $hook ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_style( 'smm-admin', SMM_URL . 'css/admin-style.css', array(), SMM_VERSION );
    wp_enqueue_script( 'smm-admin', SMM_URL . 'js/admin.js', array( 'jquery', 'wp-color-picker' ), SMM_VERSION, true );
    wp_localize_script( 'smm-admin', 'smmAdmin', array(
        'logoTitle' => __( 'Choose Logo', 'simple-maintenance-mode' ),
        'backgroundTitle' => __( 'Choose Background Image', 'simple-maintenance-mode' ),
        'videoTitle' => __( 'Choose Background Video', 'simple-maintenance-mode' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'smm_admin_assets' );

function smm_settings_page() {
    include SMM_DIR . 'includes/admin-page.php';
}

function smm_preview_page() {
    if ( empty( $_GET['smm_preview'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $nonce = sanitize_text_field( wp_unslash( $_GET['smm_preview'] ) );
    if ( ! wp_verify_nonce( $nonce, 'smm_preview' ) ) {
        return;
    }
    extract( smm_get_template_settings(), EXTR_SKIP );
    include SMM_DIR . 'maintenance-fullscreen-template.php';
    exit;
}
add_action( 'template_redirect', 'smm_preview_page', -1 );

function smm_activate() {
    smm_get_bypass_token();
}
register_activation_hook( __FILE__, 'smm_activate' );
