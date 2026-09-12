jQuery(function($){
    $('.smm-color').wpColorPicker();

    function syncVisibility(){
        const type=$('#background_type').val();
        $('.smm-background-group').hide();
        $('.smm-bg-'+type).show();
        $('.smm-countdown-field').toggle($('input[name="show_countdown"]').is(':checked'));
        $('.smm-button-fields').toggle($('input[name="show_button"]').is(':checked'));
        const response=$('#response_code').val();
        $('.smm-retry-field').toggle(response==='503'||response==='auto');
    }
    syncVisibility();
    $('#background_type,#response_code,input[name="show_countdown"],input[name="show_button"]').on('change',syncVisibility);

    $('input[type="range"]').on('input change',function(){
        const suffix=this.name==='logo_width'?'px':'%';
        $(this).siblings('.smm-range-value').text(this.value+suffix);
    });

    const frames={};
    $('.smm-media-button').on('click',function(e){
        e.preventDefault();
        const target=$(this).data('target');
        const type=$(this).data('type');
        if(frames[target]){frames[target].open();return;}
        const isVideo=type==='video';
        frames[target]=wp.media({
            title:isVideo?smmAdmin.videoTitle:(type==='logo'?smmAdmin.logoTitle:smmAdmin.backgroundTitle),
            library:{type:isVideo?'video':'image'},
            multiple:false
        });
        frames[target].on('select',function(){
            const item=frames[target].state().get('selection').first().toJSON();
            $('#'+target).val(item.url).trigger('change');
        });
        frames[target].open();
    });
    $('.smm-clear-media').on('click',function(){ $('#'+$(this).data('target')).val(''); });

    $('#smm-copy-bypass').on('click',async function(){
        const text=$('#smm-bypass-url').text();
        try{await navigator.clipboard.writeText(text);$(this).text('Copied!');setTimeout(()=>$(this).text('Copy'),1400);}catch(e){window.prompt('Copy this URL:',text);}
    });
});
