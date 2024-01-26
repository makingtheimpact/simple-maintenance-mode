<?php
/**
 * Plugin Name:         Simple Maintenance Mode
 * Plugin URI:          https://makingtheimpact.com/wordpress-plugins
 * Description:         A simple maintenance mode plugin with bypass link.
 * Version:             1.0.0
 * Requires at least:   6.4.2
 * Requires PHP:        7.4
 * Author:              Making The Impact LLC
 * Author URI:          https://makingtheimpact.com
 * License:             GPL-2.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         simple-maintenance-mode
 * Domain Path:         /languages
 */


$simple_maintenance_mode_version = "1.0.0";

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
        // Secure cookie flags
        $cookie_options = array(
            'expires' => time() + 43200, // 12 hour validity
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
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

// Intercept requests and show the maintenance/coming soon page
function simple_maintenance_mode_show_maintenance_page() {
    global $post;
    $requested_page = esc_url($_SERVER['REQUEST_URI']);
    
    // Do not interrupt if bypass token is valid or if it's an admin user
    if (simple_maintenance_mode_check_bypass_token() || current_user_can('manage_options') || strpos($requested_page, 'wp-login') !== false || strpos($requested_page, 'wp-login.php') !== false || strpos($requested_page, 'wp-login.php?action=lostpassword') !== false || strpos($requested_page, 'login') !== false) {
        return;
    }

    // Check if a specific page is set to display
    $status = get_option('simple_maintenance_mode_status', 'online');
    $page_id = get_option('simple_maintenance_mode_page', '');

    // If the bypass token is not set, user is not an admin, and status is not online
    if (!simple_maintenance_mode_check_bypass_token() && !current_user_can('manage_options') && $status !== 'online') {
        if ($status === 'maintenance' || $status === 'coming_soon') {
            // Set security headers
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');

            // If the page is set, set header to 302 (temporary redirect), then redirect user to the page
            if (!empty($page_id) && $page_id !== 'default_content') {
                // Get the ID of the current page
                $current_page_id = isset($post->ID) ? $post->ID : null;
                // If already on the coming soon/maintenance page
                if ($current_page_id == $page_id) {
                    return; // Do nothing 
                } else { // If not on the page already, redirect user
                    $coming_soon_page_url = get_permalink($page_id);
                    // If the coming soon page exists, redirect to it
                    if ($coming_soon_page_url !== false) {
                        wp_redirect(esc_url($coming_soon_page_url), 302);
                        exit();
                    } else {
                        // If page page does not exist send the appropriate headers for a 503 Service Unavailable response.
                        header('HTTP/1.1 503 Service Unavailable');
                        header('Content-Type: text/html; charset=UTF-8');
                        header('Retry-After: 3600'); // Suggests to clients/search engines to retry after 1 hour.

                        // Output your maintenance or coming soon page content.
                        // Set mode based on status
                        $mode = ($status === 'maintenance') ? 'maintenance' : 'coming_soon';

                        // Include the template and pass the mode
                        include plugin_dir_path(__FILE__) . 'maintenance-page-template.php';
                        
                        // Stop script execution.
                        exit();
                    }
                }
            } 

            // If page is set to default content or page does not exist send the appropriate headers for a 503 Service Unavailable response.
            header('HTTP/1.1 503 Service Unavailable');
            header('Content-Type: text/html; charset=UTF-8');
            header('Retry-After: 3600'); // Suggests to clients/search engines to retry after 1 hour.

            // Output your maintenance or coming soon page content.
            // Set mode based on status
            $mode = ($status === 'maintenance') ? 'maintenance' : 'coming_soon';

            // Include the template and pass the mode
            include plugin_dir_path(__FILE__) . 'maintenance-page-template.php';
            
            // Stop script execution.
            exit();
        }
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

// The actual settings page content
// Admin menu callback function
function simple_maintenance_mode_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if form is submitted and nonce is verified
    if (isset($_POST['simple_maintenance_mode_nonce']) && wp_verify_nonce($_POST['simple_maintenance_mode_nonce'], 'simple_maintenance_mode_save_settings')) {
        // The nonce was valid and the user has permission, handle the form submission (update options etc.)
        
        // Sanitize and save the status option
        if (isset($_POST['simple_maintenance_mode_status'])) {
            $sanitized_status = sanitize_text_field($_POST['simple_maintenance_mode_status']);
            update_option('simple_maintenance_mode_status', $sanitized_status);
        }

        // Sanitize and save the page ID option
        if (isset($_POST['simple_maintenance_mode_page'])) {
            $sanitized_page_id = absint($_POST['simple_maintenance_mode_page']); // absint ensures a positive integer
            update_option('simple_maintenance_mode_page', $sanitized_page_id);
        }

        // Generate a new token if requested
        if (isset($_POST['simple_maintenance_mode_new_token'])) {
            simple_maintenance_mode_save_bypass_token();
        }

        // Set a transient to show the admin notice
        set_transient('simple_maintenance_mode_settings_saved', true, 10);

        // Check for the transient and add admin notice
        if (get_transient('simple_maintenance_mode_settings_saved')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'text-domain') . '</p></div>';
                delete_transient('simple_maintenance_mode_settings_saved');
            });
        }
    }

    // Get current settings
    $status = get_option('simple_maintenance_mode_status', 'online'); // Default to online
    $page = get_option('simple_maintenance_mode_page', '');
    $token = simple_maintenance_mode_get_bypass_token();

    // Output the settings form
    ?>
    <div class="simple-maintenance-mode-admin">
        <div class="settings-container">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Use the settings below to change the website status and the page that displays when in maintenance or coming soon mode.</p>
            <form action="" method="post">
                <?php
                // Nonce field for security
                wp_nonce_field('simple_maintenance_mode_save_settings', 'simple_maintenance_mode_nonce');
                ?>

                <table>
                    <tr>
                        <td><label for="simple_maintenance_mode_status">Website Status:</label></td>
                        <td><select name="simple_maintenance_mode_status">
                                <option value="online" <?php selected($status, 'online'); ?>>Online</option>
                                <option value="maintenance" <?php selected($status, 'maintenance'); ?>>Maintenance</option>
                                <option value="coming_soon" <?php selected($status, 'coming_soon'); ?>>Coming Soon</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td><label for="simple_maintenance_mode_page">Display Page:</label></td>
                        <td><?php
                            // Input for selecting the page to display
                            $page_args = array(
                                'post_type' => 'page',
                                'posts_per_page' => -1
                            );
                            $pages = get_posts($page_args);
                            ?>
                            <select name="simple_maintenance_mode_page">
                                <option value="default_content">Default Content</option>
                                <?php foreach ($pages as $p): ?>
                                    <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($page, $p->ID); ?>>
                                        <?php echo esc_html($p->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></td>
                    </tr>
                </table>

                <p><input type="checkbox" name="simple_maintenance_mode_new_token" id="simple_maintenance_mode_new_token" />
                <label for="simple_maintenance_mode_new_token">Generate a new bypass token</label></p>
                <p><input type="submit" value="Save Changes" class="button button-primary"></p>
                <br><br>
                <h3>Bypass Token</h3>
                <p>Anyone who visits the website with the bypass token will be able to view and access the website while it is in the maintenance or coming soon mode.<p>
                <p>When the bypass URL is used, a cookie is saved in the browser that expires after 12 hours. 
                
                <table>
                    <tr>
                        <td><p>Current bypass token: </p></td>
                        <td><p><?php echo esc_html($token); ?></p></td>
                    </tr>
                    <tr>
                        <td><label for="bypass_url">Bypass URL:</label></td>
                        <td><input type="text" id="bypass_url" value="<?php echo esc_url(home_url('?mct_token=' . $token)); ?>" readonly>
                    <button type="button" id="copyBypassUrl">Copy URL</button></td>
                    </tr>
                </table>

            </form>
        </div>
    </div>
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
    if ('settings_page_maintenance-mode-settings' !== $hook) {
        return;
    }
    wp_enqueue_style(
        'simple_maintenance_mode_admin_style',
        plugin_dir_url(__FILE__) . 'path/to/admin-style.css',
        array(),
        $simple_maintenance_mode_version
    );
}
add_action('admin_enqueue_scripts', 'simple_maintenance_mode_enqueue_admin_styles');

function simple_maintenance_mode_enqueue_styles() {
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

?>