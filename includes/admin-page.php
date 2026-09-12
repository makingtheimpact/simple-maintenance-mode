<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { return; }
if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { smm_save_settings(); }
$s = smm_get_template_settings();
$token = smm_get_bypass_token();
$bypass_url = add_query_arg( 'smm_token', $token, home_url( '/' ) );
$pages = get_pages( array( 'post_status' => 'publish' ) );
$select = static function( $name, $value, $options ) {
    echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
    foreach ( $options as $key => $label ) {
        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
};
?>
<div class="wrap smm-wrap">
<div class="smm-header"><div><h1><?php esc_html_e( 'Simple Maintenance Mode', 'simple-maintenance-mode' ); ?></h1><p><?php esc_html_e( 'Lightweight maintenance and coming-soon pages without page-builder bloat.', 'simple-maintenance-mode' ); ?></p></div><span class="smm-version">v<?php echo esc_html( SMM_VERSION ); ?></span></div>
<form method="post"><?php wp_nonce_field( 'smm_save_settings', 'smm_nonce' ); ?>
<div class="smm-grid"><main>
<section class="smm-card"><h2><?php esc_html_e( 'Site Status', 'simple-maintenance-mode' ); ?></h2><div class="smm-status-options">
<?php foreach ( array( 'online'=>'Online','coming_soon'=>'Coming Soon','maintenance'=>'Maintenance' ) as $key=>$label ) : ?><label class="smm-radio-card"><input type="radio" name="status" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['status'], $key ); ?>><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?>
</div><p class="description"><?php esc_html_e( 'Administrators always retain access.', 'simple-maintenance-mode' ); ?></p></section>

<section class="smm-card"><h2><?php esc_html_e( 'Page & Search Engine Response', 'simple-maintenance-mode' ); ?></h2>
<div class="smm-field"><label><?php esc_html_e( 'Page source', 'simple-maintenance-mode' ); ?></label><select name="page_id"><option value="0"><?php esc_html_e( 'Use plugin template', 'simple-maintenance-mode' ); ?></option><?php foreach ( $pages as $page ) : ?><option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $s['page_id'], $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option><?php endforeach; ?></select></div>
<div class="smm-field"><label><?php esc_html_e( 'HTTP response', 'simple-maintenance-mode' ); ?></label><div><?php $select( 'response_code', $s['response_code'], array( 'auto'=>'Automatic: 503 Maintenance / 200 Coming Soon','503'=>'503 Temporarily Unavailable','200'=>'200 OK' ) ); ?><p class="description"><?php esc_html_e( '503 tells crawlers the outage is temporary. 200 is generally best for a new Coming Soon site.', 'simple-maintenance-mode' ); ?></p></div></div>
<div class="smm-field smm-retry-field"><label><?php esc_html_e( 'Retry-After', 'simple-maintenance-mode' ); ?></label><div><input type="number" name="retry_after" min="60" max="604800" value="<?php echo esc_attr( $s['retry_after'] ); ?>"> seconds</div></div>
</section>

<section class="smm-card"><h2><?php esc_html_e( 'Layout', 'simple-maintenance-mode' ); ?></h2><div class="smm-layout-grid">
<?php foreach ( array( 'centered'=>'Centered','centered-card'=>'Centered Card','split-left'=>'Split Left','split-right'=>'Split Right','bottom-panel'=>'Bottom Panel','minimal'=>'Minimal' ) as $key=>$label ) : ?><label class="smm-layout-option"><input type="radio" name="layout" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['layout'], $key ); ?>><span class="smm-layout-demo smm-layout-<?php echo esc_attr( $key ); ?>"></span><strong><?php echo esc_html( $label ); ?></strong></label><?php endforeach; ?>
</div></section>

<section class="smm-card"><h2><?php esc_html_e( 'Background', 'simple-maintenance-mode' ); ?></h2>
<div class="smm-field"><label><?php esc_html_e( 'Type', 'simple-maintenance-mode' ); ?></label><?php $select( 'background_type', $s['background_type'], array( 'solid'=>'Solid Color','gradient'=>'Gradient','image'=>'Image','video'=>'Video' ) ); ?></div>
<div class="smm-background-group smm-bg-solid smm-field"><label><?php esc_html_e( 'Color', 'simple-maintenance-mode' ); ?></label><input class="smm-color" name="background_color" value="<?php echo esc_attr( $s['background_color'] ); ?>"></div>
<div class="smm-background-group smm-bg-gradient">
<div class="smm-field"><label><?php esc_html_e( 'Preset', 'simple-maintenance-mode' ); ?></label><?php $select( 'gradient_preset', $s['gradient_preset'], array( 'ocean'=>'Ocean','sunset'=>'Sunset','aurora'=>'Aurora','midnight'=>'Midnight','purple'=>'Purple Haze','warm'=>'Warm Glow','sky'=>'Sky','forest'=>'Forest','slate'=>'Slate','light'=>'Clean Light','dark'=>'Clean Dark','custom'=>'Custom' ) ); ?></div>
<div class="smm-inline-fields"><div><label>Color 1</label><input class="smm-color" name="gradient_color_1" value="<?php echo esc_attr( $s['gradient_color_1'] ); ?>"></div><div><label>Color 2</label><input class="smm-color" name="gradient_color_2" value="<?php echo esc_attr( $s['gradient_color_2'] ); ?>"></div><div><label>Angle</label><input type="number" name="gradient_angle" min="0" max="360" value="<?php echo esc_attr( $s['gradient_angle'] ); ?>">°</div></div></div>
<div class="smm-background-group smm-bg-image smm-field"><label><?php esc_html_e( 'Background image', 'simple-maintenance-mode' ); ?></label><div><input type="hidden" id="background_image" name="background_image" value="<?php echo esc_attr( $s['background_image'] ); ?>"><button type="button" class="button smm-media-button" data-target="background_image" data-type="image">Choose Image</button> <button type="button" class="button smm-clear-media" data-target="background_image">Remove</button></div></div>
<div class="smm-background-group smm-bg-video smm-field"><label><?php esc_html_e( 'Background video', 'simple-maintenance-mode' ); ?></label><div><input type="hidden" id="background_video" name="background_video" value="<?php echo esc_attr( $s['background_video'] ); ?>"><button type="button" class="button smm-media-button" data-target="background_video" data-type="video">Choose Video</button> <button type="button" class="button smm-clear-media" data-target="background_video">Remove</button></div></div>
<div class="smm-inline-fields"><div><label>Overlay</label><input class="smm-color" name="overlay_color" value="<?php echo esc_attr( $s['overlay_color'] ); ?>"></div><div><label>Overlay opacity</label><input type="range" name="overlay_opacity" min="0" max="100" value="<?php echo esc_attr( $s['overlay_opacity'] ); ?>"><span class="smm-range-value"><?php echo esc_html( $s['overlay_opacity'] ); ?>%</span></div></div>
</section>

<section class="smm-card"><h2><?php esc_html_e( 'Content & Branding', 'simple-maintenance-mode' ); ?></h2>
<div class="smm-field"><label>Logo</label><div><input type="hidden" id="logo_image" name="logo_image" value="<?php echo esc_attr( $s['logo_image'] ); ?>"><button type="button" class="button smm-media-button" data-target="logo_image" data-type="logo">Choose Logo</button> <button type="button" class="button smm-clear-media" data-target="logo_image">Remove</button></div></div>
<div class="smm-field"><label>Logo width</label><div><input type="range" name="logo_width" min="40" max="600" value="<?php echo esc_attr( $s['logo_width'] ); ?>"><span class="smm-range-value"><?php echo esc_html( $s['logo_width'] ); ?>px</span></div></div>
<div class="smm-field"><label>Heading</label><input class="regular-text" name="heading" value="<?php echo esc_attr( $s['heading'] ); ?>" placeholder="<?php echo esc_attr( smm_get_default_heading( $s['mode'] ) ); ?>"></div>
<div class="smm-field"><label>Message</label><textarea class="large-text" name="message" rows="4" placeholder="<?php echo esc_attr( smm_get_default_message( $s['mode'] ) ); ?>"><?php echo esc_textarea( $s['message'] ); ?></textarea></div>
<div class="smm-inline-fields"><div><label>Text color</label><input class="smm-color" name="text_color" value="<?php echo esc_attr( $s['text_color'] ); ?>"></div><div><label>Heading size</label><input type="number" name="heading_size" min="20" max="96" value="<?php echo esc_attr( $s['heading_size'] ); ?>"> px</div><div><label>Body size</label><input type="number" name="body_size" min="12" max="36" value="<?php echo esc_attr( $s['body_size'] ); ?>"> px</div></div>
<div class="smm-inline-fields"><div><label>Font</label><?php $select( 'font_family', $s['font_family'], array( 'system'=>'System','arial'=>'Arial','helvetica'=>'Helvetica','georgia'=>'Georgia','times'=>'Times','verdana'=>'Verdana','trebuchet'=>'Trebuchet','monospace'=>'Monospace' ) ); ?></div><div><label>Heading weight</label><?php $select( 'font_weight', $s['font_weight'], array( '400'=>'400','500'=>'500','600'=>'600','700'=>'700','800'=>'800' ) ); ?></div><div><label>Alignment</label><?php $select( 'text_align', $s['text_align'], array( 'left'=>'Left','center'=>'Center','right'=>'Right' ) ); ?></div></div>
<details class="smm-advanced-content"><summary>Advanced custom content</summary><p class="description">Optional safe HTML shown beneath the message.</p><textarea class="large-text code" name="custom_content" rows="6"><?php echo esc_textarea( $s['custom_content'] ); ?></textarea></details>
</section>

<section class="smm-card"><h2><?php esc_html_e( 'Container Styling', 'simple-maintenance-mode' ); ?></h2>
<div class="smm-inline-fields"><div><label>Max width</label><input type="number" name="content_width" min="320" max="1200" value="<?php echo esc_attr( $s['content_width'] ); ?>"> px</div><div><label>Padding</label><input type="number" name="box_padding" min="0" max="100" value="<?php echo esc_attr( $s['box_padding'] ); ?>"> px</div><div><label>Corner radius</label><input type="number" name="border_radius" min="0" max="60" value="<?php echo esc_attr( $s['border_radius'] ); ?>"> px</div></div>
<div class="smm-inline-fields"><div><label>Panel color</label><input class="smm-color" name="content_bg_color" value="<?php echo esc_attr( $s['content_bg_color'] ); ?>"></div><div><label>Panel opacity</label><input type="range" name="content_bg_opacity" min="0" max="100" value="<?php echo esc_attr( $s['content_bg_opacity'] ); ?>"><span class="smm-range-value"><?php echo esc_html( $s['content_bg_opacity'] ); ?>%</span></div><div><label>Shadow</label><input type="range" name="box_shadow_opacity" min="0" max="100" value="<?php echo esc_attr( $s['box_shadow_opacity'] ); ?>"><span class="smm-range-value"><?php echo esc_html( $s['box_shadow_opacity'] ); ?>%</span></div></div>
</section>

<section class="smm-card"><h2><?php esc_html_e( 'Optional Elements', 'simple-maintenance-mode' ); ?></h2>
<label class="smm-toggle"><input type="checkbox" name="show_countdown" value="1" <?php checked( $s['show_countdown'], 1 ); ?>><span>Show countdown</span></label><div class="smm-field smm-countdown-field"><label>Launch date</label><input type="datetime-local" name="countdown_date" value="<?php echo esc_attr( $s['countdown_date'] ); ?>"></div><hr>
<label class="smm-toggle"><input type="checkbox" name="show_button" value="1" <?php checked( $s['show_button'], 1 ); ?>><span>Show call-to-action button</span></label><div class="smm-button-fields"><div class="smm-field"><label>Button label</label><input class="regular-text" name="button_label" value="<?php echo esc_attr( $s['button_label'] ); ?>"></div><div class="smm-field"><label>Button URL</label><input class="regular-text" type="url" name="button_url" value="<?php echo esc_attr( $s['button_url'] ); ?>"></div><label><input type="checkbox" name="button_new_tab" value="1" <?php checked( $s['button_new_tab'], 1 ); ?>> Open in a new tab</label><div class="smm-inline-fields"><div><label>Button color</label><input class="smm-color" name="button_bg_color" value="<?php echo esc_attr( $s['button_bg_color'] ); ?>"></div><div><label>Button text</label><input class="smm-color" name="button_text_color" value="<?php echo esc_attr( $s['button_text_color'] ); ?>"></div></div></div>
</section>

<section class="smm-card"><h2><?php esc_html_e( 'Access & Advanced', 'simple-maintenance-mode' ); ?></h2>
<label class="smm-toggle"><input type="checkbox" name="bypass_enabled" value="1" <?php checked( $s['bypass_enabled'], 1 ); ?>><span>Enable secure bypass links</span></label><div class="smm-field"><label>Bypass duration</label><div><input type="number" name="bypass_duration" min="1" max="168" value="<?php echo esc_attr( $s['bypass_duration'] ); ?>"> hours</div></div><div class="smm-bypass-box"><code id="smm-bypass-url"><?php echo esc_html( $bypass_url ); ?></code><button type="button" class="button" id="smm-copy-bypass">Copy</button></div><label><input type="checkbox" name="regenerate_token" value="1"> Generate a new bypass token when saving</label><hr>
<label class="smm-toggle"><input type="checkbox" name="block_rest_api" value="1" <?php checked( $s['block_rest_api'], 1 ); ?>><span>Block public REST API access while active</span></label><label class="smm-toggle"><input type="checkbox" name="block_xmlrpc" value="1" <?php checked( $s['block_xmlrpc'], 1 ); ?>><span>Disable XML-RPC while active</span></label>
</section>
</main><aside><section class="smm-card smm-sticky"><h2>Preview & Save</h2><p>Save your settings, then preview the public maintenance page.</p><?php submit_button( 'Save Settings', 'primary large', 'submit', false ); ?> <a class="button button-secondary button-large" target="_blank" rel="noopener" href="<?php echo esc_url( add_query_arg( 'smm_preview', wp_create_nonce( 'smm_preview' ), home_url( '/' ) ) ); ?>">Preview Page</a><div class="smm-status-summary"><strong>Current status</strong><span class="smm-pill smm-pill-<?php echo esc_attr( $s['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $s['status'] ) ) ); ?></span></div></section></aside></div></form></div>
