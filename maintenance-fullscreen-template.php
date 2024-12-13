<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - <?php echo esc_html($mode === 'maintenance' ? 'Maintenance Mode' : 'Coming Soon'); ?></title>
    <?php wp_head(); ?>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        .maintenance-fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: <?php echo esc_attr($background_color); ?>;
            <?php if ($background_type === 'image' && !empty($background_image)): ?>
            background-image: url(<?php echo esc_url($background_image); ?>);
            background-position: <?php echo esc_attr($background_alignment); ?>;
            background-size: <?php echo esc_attr($background_size); ?>;
            background-repeat: no-repeat;
            <?php endif; ?>
        }
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: <?php echo esc_attr($overlay_color); ?>;
            opacity: <?php echo esc_attr($overlay_opacity / 100); ?>;
            z-index: 1;
        }
        .content-wrapper {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: <?php echo esc_attr($box_padding); ?>px;
            max-width: 800px;
            width: 90%;
            color: <?php echo esc_attr($text_color); ?>;
            <?php if ($display_mode === 'boxed'): ?>
            background-color: rgba(<?php echo esc_attr(hexdec(substr($content_bg_color, 1, 2))); ?>, <?php echo esc_attr(hexdec(substr($content_bg_color, 3, 2))); ?>, <?php echo esc_attr(hexdec(substr($content_bg_color, 5, 2))); ?>, <?php echo esc_attr($content_bg_opacity / 100); ?>);
            box-shadow: 0 0 10px rgba(<?php echo esc_attr(hexdec(substr($box_shadow_color, 1, 2))); ?>, <?php echo esc_attr(hexdec(substr($box_shadow_color, 3, 2))); ?>, <?php echo esc_attr(hexdec(substr($box_shadow_color, 5, 2))); ?>, <?php echo esc_attr($box_shadow_opacity / 100); ?>);
            <?php endif; ?>
        }
        .logo {
            max-width: 200px;
            height: auto;
            margin: 0 auto 2rem;
            display: block;
        }
        .maintenance-content {
            margin-bottom: 2rem;
            text-align: center;
        }
        .maintenance-content h1 {
            font-size: 2.5em;
            margin-bottom: 1rem;
            color: <?php echo esc_attr($text_color); ?>;
            text-align: center;
        }
        .maintenance-content p {
            font-size: 1.2em;
            line-height: 1.6;
            color: <?php echo esc_attr($text_color); ?>;
            text-align: center;
        }
        .countdown {
            font-size: 2rem;
            margin: 2rem 0;
            display: flex;
            justify-content: center;
            gap: 2rem;
            color: <?php echo esc_attr($text_color); ?>;
        }
        .countdown-item {
            text-align: center;
        }
        .countdown-number {
            font-size: 2.5em;
            font-weight: bold;
            display: block;
            color: <?php echo esc_attr($text_color); ?>;
        }
        .countdown-label {
            font-size: 0.8em;
            text-transform: uppercase;
            color: <?php echo esc_attr($text_color); ?>;
        } 
        .background-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            z-index: 0;
            object-fit: cover;
        }
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1rem;
            }
            .countdown {
                font-size: 1.5rem;
                gap: 1rem;
            }
            .maintenance-content h1 {
                font-size: 2em;
            }
            .maintenance-content p {
                font-size: 1em;
            }
        }
    </style>
</head>
<body class="maintenance-mode">
    <div class="maintenance-fullscreen">
        <?php if ($background_type === 'video' && !empty($background_video)): ?>
        <video class="background-video" autoplay muted loop playsinline>
            <source src="<?php echo esc_url($background_video); ?>" type="video/mp4">
        </video>
        <?php endif; ?>
        
        <div class="overlay"></div>
        
        <div class="content-wrapper">
            <?php if (!empty($logo_image)): ?>
            <img src="<?php echo esc_url($logo_image); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="logo">
            <?php endif; ?>

            <div class="maintenance-content">
                <?php echo wp_kses_post($custom_content); ?>
            </div>

            <?php if ($show_countdown && !empty($countdown_date)): ?>
            <div class="countdown" data-countdown="<?php echo esc_attr($countdown_date); ?>">
                <div class="countdown-item">
                    <span class="countdown-number days">00</span>
                    <span class="countdown-label">Days</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number hours">00</span>
                    <span class="countdown-label">Hours</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number minutes">00</span>
                    <span class="countdown-label">Minutes</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number seconds">00</span>
                    <span class="countdown-label">Seconds</span>
                </div>
            </div>

            <script>
            function updateCountdown() {
                const countdownElement = document.querySelector('.countdown');
                const targetDate = new Date(countdownElement.dataset.countdown).getTime();
                
                function update() {
                    const now = new Date().getTime();
                    const distance = targetDate - now;
                    
                    if (distance < 0) {
                        countdownElement.innerHTML = "We're launching!";
                        return;
                    }
                    
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    document.querySelector('.days').textContent = days.toString().padStart(2, '0');
                    document.querySelector('.hours').textContent = hours.toString().padStart(2, '0');
                    document.querySelector('.minutes').textContent = minutes.toString().padStart(2, '0');
                    document.querySelector('.seconds').textContent = seconds.toString().padStart(2, '0');
                }
                
                update();
                setInterval(update, 1000);
            }
            
            document.addEventListener('DOMContentLoaded', updateCountdown);
            </script>
            <?php endif; ?>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html> 