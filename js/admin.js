jQuery(document).ready(function($) {
    const { __ } = wp.i18n;

    // Initialize color pickers with different settings
    const colorPickerOptions = {
        defaultColor: false,
        change: function(event, ui) {
            // Modern event handling
            $(event.target).trigger('color-change', ui.color.toString());
        },
        clear: function() {},
        hide: true,
        palettes: true,
        strings: {
            pick: smmAdmin.colorPicker.pick,
            current: smmAdmin.colorPicker.current
        }
    };

    // Initialize color pickers
    $('#smm_background_color, #smm_text_color, #smm_overlay_color').each(function() {
        $(this).wpColorPicker(colorPickerOptions);
    });
    
    // Handle opacity slider
    $('#smm_overlay_opacity').on('input change', function() {
        $('#opacity_value').text($(this).val() + '%');
    });

    // Background type toggle
    $('#smm_background_type').on('change', function() {
        if ($(this).val() === 'video') {
            $('.background-image-section').hide();
            $('.background-video-section').show();
        } else {
            $('.background-image-section').show();
            $('.background-video-section').hide();
        }
    });

    // Countdown toggle
    $('#smm_show_countdown').on('change', function() {
        if ($(this).is(':checked')) {
            $('.countdown-date-section').show();
        } else {
            $('.countdown-date-section').hide();
        }
    });

    // Media uploader instances
    const mediaUploader = {};

    // Generic function to handle media uploads
    function handleMediaUpload(type, callback) {
        if (mediaUploader[type]) {
            mediaUploader[type].open();
            return;
        }

        mediaUploader[type] = wp.media({
            title: smmAdmin.frame_title[type],
            multiple: false,
            library: {
                type: type === 'video' ? 'video' : 'image'
            }
        });

        mediaUploader[type].on('select', function() {
            const attachment = mediaUploader[type].state().get('selection').first().toJSON();
            callback(attachment);
        });

        mediaUploader[type].open();
    }

    // Background image upload
    $('#upload_background_image').on('click', function(e) {
        e.preventDefault();
        handleMediaUpload('background', function(attachment) {
            $('#smm_background_image').val(attachment.url);
            $('#background_image_preview').attr('src', attachment.url).show();
            $('#remove_background_image').show();
        });
    });

    // Background video upload
    $('#upload_background_video').on('click', function(e) {
        e.preventDefault();
        handleMediaUpload('video', function(attachment) {
            $('#smm_background_video').val(attachment.url);
            $('#remove_background_video').show();
        });
    });

    // Logo image upload
    $('#upload_logo_image').on('click', function(e) {
        e.preventDefault();
        handleMediaUpload('logo', function(attachment) {
            $('#smm_logo_image').val(attachment.url);
            $('#logo_image_preview').attr('src', attachment.url).show();
            $('#remove_logo_image').show();
        });
    });

    // Remove background image
    $('#remove_background_image').on('click', function() {
        $('#smm_background_image').val('');
        $('#background_image_preview').attr('src', '').hide();
        $(this).hide();
    });

    // Remove background video
    $('#remove_background_video').on('click', function() {
        $('#smm_background_video').val('');
        $(this).hide();
    });

    // Remove logo image
    $('#remove_logo_image').on('click', function() {
        $('#smm_logo_image').val('');
        $('#logo_image_preview').attr('src', '').hide();
        $(this).hide();
    });
}); 