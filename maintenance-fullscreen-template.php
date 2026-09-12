<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$gradients = array(
    'ocean'    => 'linear-gradient(135deg,#0f172a 0%,#1d4ed8 52%,#38bdf8 100%)',
    'sunset'   => 'linear-gradient(135deg,#7c2d12 0%,#ea580c 45%,#fbbf24 100%)',
    'aurora'   => 'linear-gradient(135deg,#052e16 0%,#047857 45%,#22d3ee 100%)',
    'midnight' => 'linear-gradient(135deg,#020617 0%,#111827 55%,#312e81 100%)',
    'purple'   => 'linear-gradient(135deg,#2e1065 0%,#7e22ce 50%,#c084fc 100%)',
    'warm'     => 'linear-gradient(135deg,#451a03 0%,#c2410c 48%,#fb7185 100%)',
    'sky'      => 'linear-gradient(135deg,#e0f2fe 0%,#60a5fa 55%,#2563eb 100%)',
    'forest'   => 'linear-gradient(135deg,#052e16 0%,#166534 55%,#65a30d 100%)',
    'slate'    => 'linear-gradient(135deg,#0f172a 0%,#334155 55%,#64748b 100%)',
    'light'    => 'linear-gradient(135deg,#ffffff 0%,#f1f5f9 55%,#dbeafe 100%)',
    'dark'     => 'linear-gradient(135deg,#09090b 0%,#18181b 55%,#27272a 100%)',
);
$gradient = isset( $gradients[ $gradient_preset ] ) ? $gradients[ $gradient_preset ] : sprintf( 'linear-gradient(%1$ddeg,%2$s 0%%,%3$s 100%%)', (int) $gradient_angle, $gradient_color_1, $gradient_color_2 );
if ( 'custom' === $gradient_preset ) {
    $gradient = sprintf( 'linear-gradient(%1$ddeg,%2$s 0%%,%3$s 100%%)', (int) $gradient_angle, $gradient_color_1, $gradient_color_2 );
}
$font_map = array(
    'system' => '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif',
    'arial' => 'Arial,sans-serif',
    'helvetica' => 'Helvetica,Arial,sans-serif',
    'georgia' => 'Georgia,serif',
    'times' => '"Times New Roman",Times,serif',
    'verdana' => 'Verdana,sans-serif',
    'trebuchet' => '"Trebuchet MS",sans-serif',
    'monospace' => 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace',
);
$font_stack = isset( $font_map[ $font_family ] ) ? $font_map[ $font_family ] : $font_map['system'];
$panel_alpha = max( 0, min( 100, (int) $content_bg_opacity ) ) / 100;
$shadow_alpha = max( 0, min( 100, (int) $box_shadow_opacity ) ) / 100;
$overlay_alpha = max( 0, min( 100, (int) $overlay_opacity ) ) / 100;
$rgb = static function( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( 6 !== strlen( $hex ) ) {
        return '15,23,42';
    }
    return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — <?php echo esc_html( 'maintenance' === $mode ? __( 'Maintenance', 'simple-maintenance-mode' ) : __( 'Coming Soon', 'simple-maintenance-mode' ) ); ?></title>
    <style>
        :root{color-scheme:light dark}
        *{box-sizing:border-box}
        html,body{margin:0;min-height:100%;font-family:<?php echo esc_html( $font_stack ); ?>}
        body{min-height:100vh;color:<?php echo esc_attr( $text_color ); ?>;background:<?php echo esc_attr( $background_color ); ?>;overflow-x:hidden}
        .smm-page{position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:clamp(24px,5vw,72px);isolation:isolate;
        <?php if ( 'gradient' === $background_type ) : ?>background:<?php echo esc_html( $gradient ); ?>;<?php elseif ( 'image' === $background_type && $background_image ) : ?>background-image:url('<?php echo esc_url( $background_image ); ?>');background-position:<?php echo esc_attr( $background_alignment ); ?>;background-size:<?php echo esc_attr( $background_size ); ?>;background-repeat:no-repeat;<?php else : ?>background:<?php echo esc_attr( $background_color ); ?>;<?php endif; ?>}
        .smm-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-3}
        .smm-overlay{position:absolute;inset:0;background:<?php echo esc_attr( $overlay_color ); ?>;opacity:<?php echo esc_attr( $overlay_alpha ); ?>;z-index:-2}
        .smm-content{width:min(100%,<?php echo (int) $content_width; ?>px);text-align:<?php echo esc_attr( $text_align ); ?>;position:relative;z-index:1;padding:<?php echo (int) $box_padding; ?>px;border-radius:<?php echo (int) $border_radius; ?>px}
        .smm-layout-centered-card .smm-content,.smm-layout-bottom-panel .smm-content{background:rgba(<?php echo esc_attr( $rgb( $content_bg_color ) ); ?>,<?php echo esc_attr( $panel_alpha ); ?>);box-shadow:0 22px 60px rgba(0,0,0,<?php echo esc_attr( $shadow_alpha ); ?>);backdrop-filter:blur(8px)}
        .smm-layout-minimal .smm-content{max-width:620px;padding:24px}
        .smm-layout-split-left,.smm-layout-split-right{justify-content:flex-start}
        .smm-layout-split-left .smm-content,.smm-layout-split-right .smm-content{width:min(48vw,720px);background:rgba(<?php echo esc_attr( $rgb( $content_bg_color ) ); ?>,<?php echo esc_attr( min( 1, $panel_alpha + .10 ) ); ?>);box-shadow:0 20px 55px rgba(0,0,0,<?php echo esc_attr( $shadow_alpha ); ?>)}
        .smm-layout-split-right{justify-content:flex-end}
        .smm-layout-bottom-panel{align-items:flex-end}
        .smm-layout-bottom-panel .smm-content{width:min(100%,900px)}
        .smm-logo{display:block;max-width:min(100%,<?php echo (int) $logo_width; ?>px);height:auto;margin:0 auto 28px}
        .smm-content h1{margin:0 0 16px;font-size:clamp(30px,6vw,<?php echo (int) $heading_size; ?>px);line-height:1.08;font-weight:<?php echo esc_attr( $font_weight ); ?>;letter-spacing:-.02em;color:inherit}
        .smm-message{margin:0 auto;font-size:<?php echo (int) $body_size; ?>px;line-height:1.65;max-width:52ch}
        .smm-content[style*="text-align:left"] .smm-message{margin-left:0}.smm-content[style*="text-align:right"] .smm-message{margin-right:0}
        .smm-extra{margin-top:18px;font-size:<?php echo (int) $body_size; ?>px;line-height:1.6}.smm-extra>*:first-child{margin-top:0}.smm-extra>*:last-child{margin-bottom:0}
        .smm-countdown{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:30px}.smm-countdown-item{padding:14px 10px;border:1px solid rgba(255,255,255,.22);border-radius:14px;background:rgba(255,255,255,.08)}.smm-countdown-number{display:block;font-size:clamp(24px,5vw,40px);font-weight:800;line-height:1}.smm-countdown-label{display:block;margin-top:6px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.82}
        .smm-button{display:inline-block;margin-top:28px;padding:13px 22px;border-radius:999px;background:<?php echo esc_attr( $button_bg_color ); ?>;color:<?php echo esc_attr( $button_text_color ); ?>;font-weight:700;text-decoration:none;transition:transform .18s ease,opacity .18s ease}.smm-button:hover{transform:translateY(-1px);opacity:.92}
        @media(max-width:760px){.smm-page{padding:20px}.smm-layout-split-left,.smm-layout-split-right{justify-content:center}.smm-layout-split-left .smm-content,.smm-layout-split-right .smm-content{width:100%}.smm-content{padding:min(9vw,36px)}.smm-countdown{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(prefers-reduced-motion:reduce){.smm-video{display:none}.smm-button{transition:none}}
    </style>
</head>
<body>
    <main class="smm-page smm-layout-<?php echo esc_attr( $layout ); ?>">
        <?php if ( 'video' === $background_type && $background_video ) : ?><video class="smm-video" autoplay muted loop playsinline aria-hidden="true"><source src="<?php echo esc_url( $background_video ); ?>" type="video/mp4"></video><?php endif; ?>
        <div class="smm-overlay" aria-hidden="true"></div>
        <section class="smm-content" style="text-align:<?php echo esc_attr( $text_align ); ?>">
            <?php if ( $logo_image ) : ?><img class="smm-logo" src="<?php echo esc_url( $logo_image ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><?php endif; ?>
            <h1><?php echo esc_html( $heading ); ?></h1>
            <p class="smm-message"><?php echo nl2br( esc_html( $message ) ); ?></p>
            <?php if ( $custom_content ) : ?><div class="smm-extra"><?php echo wp_kses_post( $custom_content ); ?></div><?php endif; ?>

            <?php if ( $show_countdown && $countdown_date ) : ?>
                <div class="smm-countdown" data-target="<?php echo esc_attr( $countdown_date ); ?>" aria-label="<?php esc_attr_e( 'Countdown', 'simple-maintenance-mode' ); ?>">
                    <?php foreach ( array( 'days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds' ) as $class => $label ) : ?>
                    <div class="smm-countdown-item"><span class="smm-countdown-number <?php echo esc_attr( $class ); ?>">00</span><span class="smm-countdown-label"><?php echo esc_html( $label ); ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( $show_button && $button_label && $button_url ) : ?><a class="smm-button" href="<?php echo esc_url( $button_url ); ?>"<?php echo $button_new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $button_label ); ?></a><?php endif; ?>
        </section>
    </main>
    <?php if ( $show_countdown && $countdown_date ) : ?>
    <script>
    (function(){const el=document.querySelector('.smm-countdown');if(!el)return;const target=new Date(el.dataset.target).getTime();function tick(){const diff=target-Date.now();if(!Number.isFinite(target)||diff<=0){el.innerHTML='<div style="grid-column:1/-1;font-weight:700">'+<?php echo wp_json_encode( __( "We're launching!", 'simple-maintenance-mode' ) ); ?>+'</div>';return;}const d=Math.floor(diff/86400000),h=Math.floor(diff%86400000/3600000),m=Math.floor(diff%3600000/60000),s=Math.floor(diff%60000/1000);[['days',d],['hours',h],['minutes',m],['seconds',s]].forEach(([c,v])=>{const n=el.querySelector('.'+c);if(n)n.textContent=String(v).padStart(2,'0')});}tick();setInterval(tick,1000)})();
    </script>
    <?php endif; ?>
</body>
</html>
