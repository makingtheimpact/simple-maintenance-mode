<?php
/**
 * Plugin Name:         Simple Maintenance Mode
 * Plugin URI:          https://makingtheimpact.com/wordpress-plugins
 * Description:         A simple maintenance mode plugin with bypass link.
 * Version:             1.0.2
 * Requires at least:   6.4.2
 * Requires PHP:        7.4
 * Author:              Making The Impact LLC
 * Author URI:          https://makingtheimpact.com
 * License:             GPL-2.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         simple-maintenance-mode
 * Domain Path:         /languages
 */


$simple_maintenance_mode_version = "1.0.2";

/* TOKEN TO BYPASS COMING SOON/MAINTENANCE MODE */
// Generate a random token
function simple_maintenance_mode_generate_random_token() {
    return bin2hex(openssl_random_pseudo_bytes(16)); // 32 characters
}

// Save the token to the WordPress options table
function simple_maintenance_mode_save_bypass_token() {
    $new_token = simple_maintenance_mode_generate_random_token();
    update_option('simple_maintenance_mode_bypass_token', $new_token);
    return $new_token;
}

// Retrieve the token from the WordPress options table
function simple_maintenance_mode_get_bypass_token() {
    return get_option('simple_maintenance_mode_bypass_token', '');
}

// Check if the current request has the bypass token and set a secure cookie
function simple_maintenance_mode_check_bypass_token() {
    $token = simple_maintenance_mode_get_bypass_token();
    $secure = is_ssl(); // Check if the connection is over HTTPS
    
    // Validate the token from the URL and set a secure cookie
    if (isset($_GET['mct_token']) && $_GET['mct_token'] === $token) {
        $url = home_url(); // Retrieves the home URL of the site.
        $parsed_url = parse_url($url); // Parse the URL to get its components.
        $domain = $parsed_url['host']; // Get the host component which is your domain.

        // Prepend a dot to make cookie valid across all subdomains
        if (substr_count($domain, '.') == 1) {
            $domain = '.' . $domain;
        }

        // Secure cookie flags
        $cookie_options = array(
            'expires' => time() + 43200, // 12 hour validity
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure, // Only set to true if your site uses HTTPS
            'httponly' => true, // Cookie is accessible only through the HTTP protocol
            'samesite' => 'Lax' // Helps against CSRF
        );
        
        // Set the cookie
        setcookie('maintenance_bypass', $token, $cookie_options);
        $_COOKIE['maintenance_bypass'] = $token; // Set for immediate availability
        return true;
    }

    // Check if the bypass cookie is present and matches the stored token
    if (isset($_COOKIE['maintenance_bypass']) && $_COOKIE['maintenance_bypass'] === $token) {
        return true;
    }

    return false;
}

// Modify the maintenance page display function
function simple_maintenance_mode_show_maintenance_page() {
    // Early exit for AJAX, admin, cron, and customizer
    if (wp_doing_ajax() || wp_doing_cron() || is_customize_preview()) {
        return;
    }

    // Allow admin access
    if (is_admin()) {
        return;
    }

    // Check if user should bypass
    if (simple_maintenance_mode_check_bypass_token() || current_user_can('manage_options') || 
        strpos($_SERVER['REQUEST_URI'], 'wp-login') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'wp-login.php?action=lostpassword') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'login') !== false) {
        return;
    }

    $status = get_option('simple_maintenance_mode_status', 'online');
    
    if ($status !== 'online') {
        // Set security headers
        header('HTTP/1.1 503 Service Unavailable');
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 3600');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');

        $page_id = get_option('simple_maintenance_mode_page', '');
        $current_page_id = get_queried_object_id();
        
        // If using a custom page and we're not on that page, redirect to it
        if (!empty($page_id) && get_post($page_id)) {
            if ($current_page_id != $page_id) {
                wp_redirect(get_permalink($page_id));
                exit();
            }
            // If we are on the custom page, show it
            return;
        }

        // Using plugin template - get all necessary settings
        $mode = ($status === 'maintenance') ? 'maintenance' : 'coming_soon';
        $settings = array(
            'mode' => $mode,
            'background_type' => get_option('smm_background_type', 'image'),
            'background_image' => get_option('smm_background_image', ''),
            'background_video' => get_option('smm_background_video', ''),
            'background_alignment' => get_option('smm_background_alignment', 'center center'),
            'background_size' => get_option('smm_background_size', 'cover'),
            'background_color' => get_option('smm_background_color', '#ffffff'),
            'overlay_color' => get_option('smm_overlay_color', '#000000'),
            'overlay_opacity' => get_option('smm_overlay_opacity', '50'),
            'text_color' => get_option('smm_text_color', '#ffffff'),
            'custom_content' => get_option('smm_custom_content', ''),
            'logo_image' => get_option('smm_logo_image', ''),
            'show_countdown' => get_option('smm_show_countdown', false),
            'countdown_date' => get_option('smm_countdown_date', ''),
            'display_mode' => get_option('smm_display_mode', 'fullscreen'),
            'content_bg_color' => get_option('smm_content_bg_color', '#ffffff'),
            'content_bg_opacity' => get_option('smm_content_bg_opacity', 0),
            'box_padding' => get_option('smm_box_padding', 0),
            'box_shadow_color' => get_option('smm_box_shadow_color', '#000000'),
            'box_shadow_opacity' => get_option('smm_box_shadow_opacity', 0)
        );

        // If no custom content is set, use default content
        if (empty($settings['custom_content'])) {
            $settings['custom_content'] = simple_maintenance_mode_get_default_content($mode);
        }

        // Extract settings to make them available as variables in the template
        extract($settings);
        
        // Ensure this is done before the HTML output
        $display_mode = get_option('smm_display_mode', 'fullscreen');
        
        // Include the template
        include plugin_dir_path(__FILE__) . 'maintenance-fullscreen-template.php';
        exit();
    }
}

// Create a menu item for the plugin in the admin dashboard
function simple_maintenance_mode_admin_menu() {
    add_options_page(
        'Maintenance Mode Settings',
        'Maintenance Mode',
        'manage_options',
        'maintenance-mode-settings',
        'simple_maintenance_mode_settings_page'
    );
}
add_action('admin_menu', 'simple_maintenance_mode_admin_menu');

// Admin notice for settings update
function show_simple_maintenance_mode_admin_notice() {
    if (get_transient('simple_maintenance_mode_settings_saved')) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'text-domain') . '</p></div>';
        delete_transient('simple_maintenance_mode_settings_saved');
    }
}
add_action('admin_notices', 'show_simple_maintenance_mode_admin_notice');

// Add this function to handle default content
function simple_maintenance_mode_get_default_content($mode) {
    if ($mode === 'maintenance') {
        return wp_kses_post('
<div style="text-align: center;">
    <h1>Under Maintenance</h1>
    <p>We are currently performing scheduled maintenance on our website to bring you an even better experience.</p>
    <p>We will be back online shortly. Thank you for your patience!</p>
</div>');
    } else { // coming soon
        return wp_kses_post('
<div style="text-align: center;">
    <h1>Coming Soon</h1>
    <p>We are working hard to bring you something amazing!</p>
    <p>Our new website is under construction and will be launching soon.</p>
    <p>Stay tuned for updates!</p>
</div>');
    }
}

// Add this JavaScript to handle mode changes
add_action('admin_footer', 'simple_maintenance_mode_admin_footer_script');
function simple_maintenance_mode_admin_footer_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Only run on our settings page
        if ($('#simple_maintenance_mode_status').length) {
            var editor = tinymce.get('smm_custom_content');
            var currentContent = editor ? editor.getContent() : $('#smm_custom_content').val();
            
            $('#simple_maintenance_mode_status').on('change', function() {
                var mode = $(this).val();
                if (mode !== 'online') {
                    // Only update if the editor is empty
                    if (!currentContent || currentContent.trim() === '') {
                        $.post(ajaxurl, {
                            action: 'get_default_content',
                            mode: mode,
                            nonce: '<?php echo wp_create_nonce('smm_get_default_content'); ?>'
                        }, function(response) {
                            if (response.success && editor) {
                                editor.setContent(response.data);
                                currentContent = response.data;
                            }
                        });
                    }
                }
            });

            // Update currentContent when the editor changes
            if (editor) {
                editor.on('change', function() {
                    currentContent = editor.getContent();
                });
            }
        }
    });
    </script>
    <?php
}

// Add AJAX handler for getting default content
add_action('wp_ajax_get_default_content', 'simple_maintenance_mode_get_default_content_ajax');
function simple_maintenance_mode_get_default_content_ajax() {
    check_ajax_referer('smm_get_default_content', 'nonce');
    
    $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'coming_soon';
    wp_send_json_success(simple_maintenance_mode_get_default_content($mode));
}

// Modify the settings page function to handle default content
function simple_maintenance_mode_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings
    if (isset($_POST['simple_maintenance_mode_nonce']) && wp_verify_nonce($_POST['simple_maintenance_mode_nonce'], 'simple_maintenance_mode_save_settings')) {
        // Save existing settings
        if (isset($_POST['simple_maintenance_mode_status'])) {
            update_option('simple_maintenance_mode_status', sanitize_text_field($_POST['simple_maintenance_mode_status']));
        }

        // Save custom page selection
        if (isset($_POST['simple_maintenance_mode_page'])) {
            update_option('simple_maintenance_mode_page', absint($_POST['simple_maintenance_mode_page']));
        }

        // Generate new bypass token if requested
        if (isset($_POST['simple_maintenance_mode_new_token'])) {
            simple_maintenance_mode_save_bypass_token();
        }

        // Save the image and logo
        if (isset($_POST['smm_background_image'])) {
            update_option('smm_background_image', sanitize_text_field($_POST['smm_background_image']));
        }
        if (isset($_POST['smm_logo_image'])) {
            update_option('smm_logo_image', sanitize_text_field($_POST['smm_logo_image']));
        }

        // Save new settings
        $fields = array(
            'smm_background_type' => 'sanitize_text_field',
            'smm_background_alignment' => 'sanitize_text_field',
            'smm_background_size' => 'sanitize_text_field',
            'smm_background_color' => 'sanitize_hex_color',
            'smm_overlay_color' => 'sanitize_hex_color',
            'smm_overlay_opacity' => 'absint',
            'smm_text_color' => 'sanitize_hex_color',
            'smm_show_countdown' => 'rest_sanitize_boolean',
            'smm_countdown_date' => 'sanitize_text_field'
        );

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                update_option($field, $sanitize_callback($_POST[$field]));
            }
        }

        if (isset($_POST['smm_display_mode'])) {
            update_option('smm_display_mode', sanitize_text_field($_POST['smm_display_mode']));
        }
        if (isset($_POST['smm_box_padding'])) {
            update_option('smm_box_padding', intval($_POST['smm_box_padding']));
        }
        if (isset($_POST['smm_box_shadow_color'])) {
            update_option('smm_box_shadow_color', sanitize_hex_color($_POST['smm_box_shadow_color']));
        }
        if (isset($_POST['smm_box_shadow_opacity'])) {
            update_option('smm_box_shadow_opacity', intval($_POST['smm_box_shadow_opacity']));
        }

        if (isset($_POST['smm_content_bg_color'])) {
            update_option('smm_content_bg_color', sanitize_hex_color($_POST['smm_content_bg_color']));
        }
        if (isset($_POST['smm_content_bg_opacity'])) {
            update_option('smm_content_bg_opacity', intval($_POST['smm_content_bg_opacity']));
        }

        if (isset($_POST['smm_custom_content'])) {
            $custom_content = wp_unslash($_POST['smm_custom_content']);
            update_option('smm_custom_content', wp_kses_post($custom_content));
        }

        set_transient('simple_maintenance_mode_settings_saved', true, 10);
    }

    // Get current settings
    $status = get_option('simple_maintenance_mode_status', 'online');
    $page = get_option('simple_maintenance_mode_page', '');
    $token = simple_maintenance_mode_get_bypass_token();
    $background_type = get_option('smm_background_type', 'image');
    $background_image = get_option('smm_background_image', '');
    $background_video = get_option('smm_background_video', '');
    $background_alignment = get_option('smm_background_alignment', 'center center');
    $background_size = get_option('smm_background_size', 'cover');
    $background_color = get_option('smm_background_color', '#ffffff');
    $overlay_color = get_option('smm_overlay_color', 'rgba(0,0,0,0.5)');
    $display_mode = get_option('smm_display_mode', 'fullscreen');
    $content_bg_color = get_option('smm_content_bg_color', '#ffffff');
    $content_bg_opacity = get_option('smm_content_bg_opacity', 0);
    $box_padding = get_option('smm_box_padding', 0);
    $box_shadow_color = get_option('smm_box_shadow_color', '#000000');
    $box_shadow_opacity = get_option('smm_box_shadow_opacity', 0);
    $custom_content = htmlspecialchars_decode(get_option('smm_custom_content', ''));
    if ($custom_content !== '') { 
        $custom_content = wp_unslash($custom_content); // remove slashes
    }
    if (empty($custom_content)) {
        $custom_content = simple_maintenance_mode_get_default_content($status);
    }
    $logo_image = get_option('smm_logo_image', '');
    $show_countdown = get_option('smm_show_countdown', false);
    $countdown_date = get_option('smm_countdown_date', '');

    // Get list of background images from assets folder
    $backgrounds_dir = plugin_dir_path(__FILE__) . 'assets/backgrounds/';
    $backgrounds_url = plugin_dir_url(__FILE__) . 'assets/backgrounds/';
    $background_images = glob($backgrounds_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    $background_images = array_map('basename', $background_images);

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('simple_maintenance_mode_save_settings', 'simple_maintenance_mode_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="simple_maintenance_mode_status">Website Status</label></th>
                    <td>
                        <select name="simple_maintenance_mode_status" id="simple_maintenance_mode_status">
                            <option value="online" <?php selected($status, 'online'); ?>>Online</option>
                            <option value="maintenance" <?php selected($status, 'maintenance'); ?>>Maintenance Mode</option>
                            <option value="coming_soon" <?php selected($status, 'coming_soon'); ?>>Coming Soon</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="simple_maintenance_mode_page">Custom Page</label></th>
                    <td>
                        <select name="simple_maintenance_mode_page" id="simple_maintenance_mode_page">
                            <option value="">Use Plugin Template</option>
                            <?php
                            $pages = get_pages();
                            foreach ($pages as $p) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($p->ID),
                                    selected($page, $p->ID, false),
                                    esc_html($p->post_title)
                                );
                            }
                            ?>
                        </select>
                        <p class="description">Select a custom page or use the plugin's template below.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Bypass Token</th>
                    <td>
                        <p>Current token: <code><?php echo esc_html($token); ?></code></p>
                        <p>Bypass URL: <input type="text" id="bypass_url" value="<?php echo esc_url(add_query_arg('mct_token', $token, home_url())); ?>" style="width: 80%;" readonly>
                        <button type="button" id="copyBypassUrl" class="button">Copy URL</button></p>
                        <label style="margin-top: 5px; display: block;">
                            <input type="checkbox" name="simple_maintenance_mode_new_token" value="1">
                            Generate new bypass token
                        </label>
                        <p class="description">Share this URL to grant temporary access to your site while in maintenance mode.</p>
                    </td>
                </tr>

                <tr><th scope="row"><h3>Background Settings</h3></th></tr>

                <tr>
                    <th scope="row"><label for="smm_background_type">Background Type</label></th>
                    <td>
                        <select name="smm_background_type" id="smm_background_type">
                            <option value="image" <?php selected($background_type, 'image'); ?>>Image</option>
                            <option value="video" <?php selected($background_type, 'video'); ?>>Video</option>
                        </select>
                    </td>
                </tr>

                <tr class="background-image-section" <?php echo $background_type === 'video' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label>Background Image</label></th>
                    <td>
                        <div class="image-preview-wrapper-background">
                            <img id="background_image_preview" src="<?php echo esc_url($background_image); ?>" style="max-width: 200px; display: <?php echo empty($background_image) ? 'none' : 'block'; ?>">
                        </div>
                        <input type="hidden" name="smm_background_image" id="smm_background_image" value="<?php echo esc_attr($background_image); ?>">
                        <button type="button" class="button" id="upload_background_image">Upload Custom Image</button>
                        <button type="button" class="button" id="remove_background_image" style="display: <?php echo empty($background_image) ? 'none' : 'inline-block'; ?>">Remove Image</button>
                        
                        <div class="predefined-backgrounds" style="margin-top: 20px;">
                            <h4>Pre-installed Backgrounds</h4>
                            <div class="background-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                                <?php foreach ($background_images as $bg_image): ?>
                                <div class="background-option" style="text-align: center;">
                                    <img src="<?php echo esc_url($backgrounds_url . $bg_image); ?>" 
                                         style="max-width: 150px; cursor: pointer; border: 2px solid transparent;"
                                         data-url="<?php echo esc_url($backgrounds_url . $bg_image); ?>"
                                         onclick="selectPredefinedBackground(this)">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                </tr>

                <tr class="background-video-section" <?php echo $background_type === 'image' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label>Background Video</label></th>
                    <td>
                        <input type="hidden" name="smm_background_video" id="smm_background_video" value="<?php echo esc_attr($background_video); ?>">
                        <button type="button" class="button" id="upload_background_video">Choose Video</button>
                        <button type="button" class="button" id="remove_background_video" style="display: <?php echo empty($background_video) ? 'none' : 'inline-block'; ?>">Remove Video</button>
                        <p class="description">Upload MP4 video file (recommended max size: 10MB)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_background_alignment">Background Alignment</label></th>
                    <td>
                        <select name="smm_background_alignment" id="smm_background_alignment">
                            <option value="center center" <?php selected($background_alignment, 'center center'); ?>>Center</option>
                            <option value="top left" <?php selected($background_alignment, 'top left'); ?>>Top Left</option>
                            <option value="top right" <?php selected($background_alignment, 'top right'); ?>>Top Right</option>
                            <option value="bottom left" <?php selected($background_alignment, 'bottom left'); ?>>Bottom Left</option>
                            <option value="bottom right" <?php selected($background_alignment, 'bottom right'); ?>>Bottom Right</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_background_size">Background Size</label></th>
                    <td>
                        <select name="smm_background_size" id="smm_background_size">
                            <option value="cover" <?php selected($background_size, 'cover'); ?>>Cover</option>
                            <option value="contain" <?php selected($background_size, 'contain'); ?>>Contain</option>
                            <option value="auto" <?php selected($background_size, 'auto'); ?>>Auto</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_background_color">Background Color</label></th>
                    <td>
                        <input type="text" name="smm_background_color" id="smm_background_color" value="<?php echo esc_attr($background_color); ?>" class="color-picker">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_overlay_color">Overlay Color</label></th>
                    <td>
                        <input type="text" 
                               name="smm_overlay_color" 
                               id="smm_overlay_color" 
                               value="<?php echo esc_attr(get_option('smm_overlay_color', '#000000')); ?>" 
                               class="color-picker">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_overlay_opacity">Overlay Opacity</label></th>
                    <td>
                        <input type="range" 
                               name="smm_overlay_opacity" 
                               id="smm_overlay_opacity" 
                               value="<?php echo esc_attr(get_option('smm_overlay_opacity', '50')); ?>" 
                               min="0" 
                               max="100" 
                               step="1">
                        <span id="opacity_value"><?php echo esc_html(get_option('smm_overlay_opacity', '50')); ?>%</span>
                        <p class="description">Adjust the transparency of the overlay. 0% is fully transparent, 100% is fully opaque.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_display_mode">Content Display Mode</label></th>
                    <td>
                        <select name="smm_display_mode" id="smm_display_mode">
                            <option value="fullscreen" <?php selected($display_mode, 'fullscreen'); ?>>Fullscreen</option>
                            <option value="boxed" <?php selected($display_mode, 'boxed'); ?>>Boxed</option>
                        </select>
                    </td>
                </tr>
                <tr class="boxed-settings" <?php echo $display_mode === 'fullscreen' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label for="smm_content_bg_color">Content Background Color</label></th>
                    <td>
                        <input type="text" name="smm_content_bg_color" id="smm_content_bg_color" value="<?php echo esc_attr($content_bg_color); ?>" class="color-picker">
                    </td>
                </tr>
                <tr class="boxed-settings" <?php echo $display_mode === 'fullscreen' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label for="smm_content_bg_opacity">Content Background Opacity</label></th>
                    <td>
                        <input type="range" name="smm_content_bg_opacity" id="smm_content_bg_opacity" value="<?php echo esc_attr($content_bg_opacity); ?>" min="0" max="100" step="1">
                        <span id="content_bg_opacity_value"><?php echo esc_html($content_bg_opacity); ?>%</span>
                    </td>
                </tr>
                <tr class="boxed-settings" <?php echo $display_mode === 'fullscreen' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label for="smm_box_padding">Box Padding</label></th>
                    <td>
                        <input type="number" name="smm_box_padding" id="smm_box_padding" value="<?php echo esc_attr($box_padding); ?>" min="0" max="100"> px
                    </td>
                </tr>
                <tr class="boxed-settings" <?php echo $display_mode === 'fullscreen' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label for="smm_box_shadow_color">Box Shadow Color</label></th>
                    <td>
                        <input type="text" name="smm_box_shadow_color" id="smm_box_shadow_color" value="<?php echo esc_attr($box_shadow_color); ?>" class="color-picker">
                    </td>
                </tr>
                <tr class="boxed-settings" <?php echo $display_mode === 'fullscreen' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label for="smm_box_shadow_opacity">Box Shadow Opacity</label></th>
                    <td>
                        <input type="range" name="smm_box_shadow_opacity" id="smm_box_shadow_opacity" value="<?php echo esc_attr($box_shadow_opacity); ?>" min="0" max="100" step="1">
                        <span id="box_shadow_opacity_value"><?php echo esc_html($box_shadow_opacity); ?>%</span>
                    </td>
                </tr>

                <tr><th scope="row"><h3>Content Settings</h3></th></tr>

                <tr>
                    <th scope="row"><label>Logo Image</label></th>
                    <td>
                        <div class="image-preview-wrapper-logo">
                            <img id="logo_image_preview" src="<?php echo esc_url($logo_image); ?>" style="max-width: 200px; display: <?php echo empty($logo_image) ? 'none' : 'block'; ?>">
                        </div>
                        <input type="hidden" name="smm_logo_image" id="smm_logo_image" value="<?php echo esc_attr($logo_image); ?>">
                        <button type="button" class="button" id="upload_logo_image">Choose Logo</button>
                        <button type="button" class="button" id="remove_logo_image" style="display: <?php echo empty($logo_image) ? 'none' : 'inline-block'; ?>">Remove Logo</button>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_custom_content">Custom Content</label></th>
                    <td>
                        <?php
                        wp_editor($custom_content, 'smm_custom_content', array(
                            'textarea_name' => 'smm_custom_content',
                            'media_buttons' => true,
                            'textarea_rows' => 10,
                            'editor_height' => 200
                        ));
                        ?>
                        <p class="description">Customize the content that appears on your maintenance/coming soon page. The default content will only be used if this field is empty.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_show_countdown">Show Countdown</label></th>
                    <td>
                        <input type="checkbox" name="smm_show_countdown" id="smm_show_countdown" value="1" <?php checked($show_countdown, true); ?>>
                    </td>
                </tr>

                <tr class="countdown-date-section" <?php echo !$show_countdown ? 'style="display:none;"' : ''; ?>>
                    <th scope="row"><label for="smm_countdown_date">Launch Date</label></th>
                    <td>
                        <input type="datetime-local" name="smm_countdown_date" id="smm_countdown_date" value="<?php echo esc_attr($countdown_date); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="smm_text_color">Text Color</label></th>
                    <td>
                        <input type="text" 
                               name="smm_text_color" 
                               id="smm_text_color" 
                               value="<?php echo esc_attr(get_option('smm_text_color', '#000000')); ?>" 
                               class="color-picker">
                        <p class="description">Choose the color for your text content.</p>
                    </td>
                </tr>                
            </table>

            <button type="button" class="button" id="preview_maintenance_mode">Preview Page</button>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>

    <script>
    function selectPredefinedBackground(img) {
        var url = img.getAttribute('data-url');
        jQuery('#smm_background_image').val(url);
        jQuery('.image-preview-wrapper-background img').attr('src', url).show();
        jQuery('#remove_background_image').show();
        
        // Update visual selection
        jQuery('.background-option img').css('border-color', 'transparent');
        jQuery(img).css('border-color', '#007cba');
    }

    jQuery(document).ready(function($) {
        $('#smm_display_mode').change(function() {
            if ($(this).val() === 'boxed') {
                $('.boxed-settings').show();
            } else {
                $('.boxed-settings').hide();
            }
        });

        $('#smm_box_shadow_opacity').on('input change', function() {
            $('#box_shadow_opacity_value').text($(this).val() + '%');
        });
        $('#smm_content_bg_opacity').on('input change', function() {
            $('#content_bg_opacity_value').text($(this).val() + '%');
        });

        $('#preview_maintenance_mode').click(function() {
            var previewUrl = '<?php echo esc_url(home_url('?preview_maintenance_mode=1')); ?>';
            window.open(previewUrl, '_blank');
        });
    });
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                alert("Copied to clipboard: " + text);
            }).catch(function(error) {
                alert("Copy failed! " + error);
            });
        } else {
            // Fallback for unsupported browsers
            var tempInput = document.createElement('textarea');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            try {
                document.execCommand('copy');
                alert("Copied to clipboard: " + text);
            } catch (error) {
                alert("Copy failed! " + error);
            }
            document.body.removeChild(tempInput);
        }
    }

    // Add click event listener to the copy button
    document.addEventListener('DOMContentLoaded', function() {
        var copyButton = document.getElementById('copyBypassUrl');
        if (copyButton) {
            copyButton.addEventListener('click', function() {
                var bypassUrl = document.getElementById('bypass_url').value;
                copyToClipboard(bypassUrl);
            });
        }
    });

    </script>
    <?php
}

// Add a button to the admin bar with dynamic text based on the current mode
function add_simple_maintenance_mode_admin_bar_button( $wp_admin_bar ) {
    $status = get_option('simple_maintenance_mode_status', 'online'); // Default to online

    // Determine the button text based on the current status
    switch ($status) {
        case 'maintenance':
            $button_text = 'Mode: Maintenance';
            break;
        case 'coming_soon':
            $button_text = 'Mode: Coming Soon';
            break;
        default:
            $button_text = 'Mode: Online';
            break;
    }

    if ($status !== 'online') {
        $args = array(
            'id' => 'simple_maintenance_mode_settings',
            'title' => $button_text,
            'href' => admin_url('options-general.php?page=maintenance-mode-settings'),
            'parent' => 'top-secondary', // The area on the right side of the admin bar
            'meta' => array('class' => 'maintenance-mode-admin-bar')
        );
        $wp_admin_bar->add_node($args);
    }
}
add_action('admin_bar_menu', 'add_simple_maintenance_mode_admin_bar_button', 100);

function simple_maintenance_mode_admin_styles() {
    echo '
    <style type="text/css">
        #wpadminbar .maintenance-mode-admin-bar {
            float: right;
        }
    </style>';
}
add_action('admin_head', 'simple_maintenance_mode_admin_styles');

// Make sure to hook early enough to intercept the template redirect
add_action('template_redirect', 'simple_maintenance_mode_show_maintenance_page', 9); // priority 9 to override the default template redirect

function simple_maintenance_mode_enqueue_admin_styles($hook) {
    global $simple_maintenance_mode_version;
    if ('settings_page_maintenance-mode-settings' !== $hook) {
        return;
    }
    wp_enqueue_style(
        'simple_maintenance_mode_admin_style',
        plugin_dir_url(__FILE__) . 'css/admin-style.css',
        array(),
        $simple_maintenance_mode_version
    );
}
add_action('admin_enqueue_scripts', 'simple_maintenance_mode_enqueue_admin_styles');

function simple_maintenance_mode_enqueue_styles() {
    global $simple_maintenance_mode_version;
    // This assumes you are correctly determining when to load these styles.
    wp_enqueue_style(
        'simple_maintenance_mode_style', 
        plugin_dir_url(__FILE__) . 'css/style.css', 
        array(), 
        $simple_maintenance_mode_version
    );
}
add_action('wp_enqueue_scripts', 'simple_maintenance_mode_enqueue_styles');


// Enqueue the script for the admin settings page
function simple_maintenance_mode_enqueue_scripts($hook) {
    global $simple_maintenance_mode_version;
    // Only add to the settings page of the plugin
    if ($hook !== 'settings_page_maintenance-mode-settings') {
        return;
    }

    // Enqueue the JavaScript file
    wp_enqueue_script(
        'maintenance-mode-js', // Handle for the script
        plugin_dir_url(__FILE__) . 'js/maintenance-mode.js', // Path to the script file
        array(), // Dependencies (none in this case)
        $simple_maintenance_mode_version, // Version number for the script
        true // Place the script in the footer to avoid issues with DOMContentLoaded
    );
}
add_action('admin_enqueue_scripts', 'simple_maintenance_mode_enqueue_scripts');

// Generate a bypass token on activation 
function simple_maintenance_mode_activate() {
    // Check if the bypass token already exists
    if (!get_option('simple_maintenance_mode_bypass_token')) {
        // Generate and save a new token
        simple_maintenance_mode_save_bypass_token();
    }
}
register_activation_hook(__FILE__, 'simple_maintenance_mode_activate');

// Add new settings fields
function simple_maintenance_mode_register_settings() {
    register_setting('simple_maintenance_mode_options', 'smm_background_type');
    register_setting('simple_maintenance_mode_options', 'smm_background_image');
    register_setting('simple_maintenance_mode_options', 'smm_background_video');
    register_setting('simple_maintenance_mode_options', 'smm_background_alignment');
    register_setting('simple_maintenance_mode_options', 'smm_background_size');
    register_setting('simple_maintenance_mode_options', 'smm_background_color');
    register_setting('simple_maintenance_mode_options', 'smm_overlay_color');
    register_setting('simple_maintenance_mode_options', 'smm_custom_content');
    register_setting('simple_maintenance_mode_options', 'smm_logo_image');
    register_setting('simple_maintenance_mode_options', 'smm_show_countdown');
    register_setting('simple_maintenance_mode_options', 'smm_countdown_date');
}
add_action('admin_init', 'simple_maintenance_mode_register_settings');

// Enqueue necessary scripts and styles for the admin
function simple_maintenance_mode_admin_enqueue($hook) {
    if ('settings_page_maintenance-mode-settings' !== $hook) {
        return;
    }

    // Enqueue WordPress media uploader
    wp_enqueue_media();

    // Enqueue WordPress scripts
    wp_enqueue_script('wp-i18n');
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_style('wp-color-picker');

    // Enqueue color picker alpha
    wp_enqueue_script(
        'wp-color-picker-alpha',
        plugin_dir_url(__FILE__) . 'js/wp-color-picker-alpha.min.js',
        array('wp-color-picker', 'wp-i18n'),
        '3.0.0',
        true
    );

    // Enqueue admin script
    wp_enqueue_script(
        'simple-maintenance-mode-admin',
        plugin_dir_url(__FILE__) . 'js/admin.js',
        array('jquery', 'wp-color-picker', 'wp-color-picker-alpha', 'wp-i18n'),
        '1.0.0',
        true
    );

    // Localize the script with new data
    wp_localize_script('simple-maintenance-mode-admin', 'smmAdmin', array(
        'frame_title' => array(
            'logo' => __('Select or Upload Logo Image', 'simple-maintenance-mode'),
            'background' => __('Select or Upload Background Image', 'simple-maintenance-mode'),
            'video' => __('Select or Upload Background Video', 'simple-maintenance-mode')
        ),
        'colorPicker' => array(
            'pick' => __('Select Color', 'simple-maintenance-mode'),
            'current' => __('Current Color', 'simple-maintenance-mode')
        )
    ));

    // Set translation for color picker
    wp_set_script_translations('simple-maintenance-mode-admin', 'simple-maintenance-mode');

    // Enqueue editor if needed
    wp_enqueue_editor();
}
add_action('admin_enqueue_scripts', 'simple_maintenance_mode_admin_enqueue');

add_action('template_redirect', function() {
    if (isset($_GET['preview_maintenance_mode']) && current_user_can('manage_options')) {

        $background_type = get_option('smm_background_type', 'image');
        $background_image = get_option('smm_background_image', '');
        $background_video = get_option('smm_background_video', '');
        $background_alignment = get_option('smm_background_alignment', 'center center');
        $background_size = get_option('smm_background_size', 'cover');
        $background_color = get_option('smm_background_color', '#ffffff');
        $overlay_color = get_option('smm_overlay_color', 'rgba(0,0,0,0.5)');
        $overlay_opacity = get_option('smm_overlay_opacity', 0);
        $text_color = get_option('smm_text_color', '#000000');
        $display_mode = get_option('smm_display_mode', 'fullscreen');
        $content_bg_color = get_option('smm_content_bg_color', '#ffffff');
        $content_bg_opacity = get_option('smm_content_bg_opacity', 0);
        $box_padding = get_option('smm_box_padding', 0);
        $box_shadow_color = get_option('smm_box_shadow_color', '#000000');
        $box_shadow_opacity = get_option('smm_box_shadow_opacity', 0);
        $custom_content = htmlspecialchars_decode(get_option('smm_custom_content', ''));
        if ($custom_content !== '') { 
            $custom_content = wp_unslash($custom_content); // remove slashes
        }
        if (empty($custom_content)) {
            $custom_content = simple_maintenance_mode_get_default_content($status);
        }
        $logo_image = get_option('smm_logo_image', '');
        $show_countdown = get_option('smm_show_countdown', false);
        $countdown_date = get_option('smm_countdown_date', '');

        // Get list of background images from assets folder
        $backgrounds_dir = plugin_dir_path(__FILE__) . 'assets/backgrounds/';
        $backgrounds_url = plugin_dir_url(__FILE__) . 'assets/backgrounds/';
        $background_images = glob($backgrounds_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $background_images = array_map('basename', $background_images); 

        
        // Load the maintenance or coming soon template
        include plugin_dir_path(__FILE__) . 'maintenance-fullscreen-template.php';
        exit;
    }
});

?>