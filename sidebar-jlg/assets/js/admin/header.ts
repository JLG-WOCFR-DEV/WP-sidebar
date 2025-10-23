/* eslint-disable */
// @ts-nocheck

export function initHeaderSection($) {
    const logoTypeRadios = $('input[name="sidebar_jlg_settings[header_logo_type]"]');
    logoTypeRadios.on('change', function() {
        if ($(this).val() === 'image') {
            $('.header-image-options').show();
            $('.header-text-options').hide();
        } else {
            $('.header-image-options').hide();
            $('.header-text-options').show();
        }
    }).trigger('change');

    // --- Sliders en temps réel ---
    $('input[type="range"]').each(function() {
        const $input = $(this);
        const $valueDisplay = $input.siblings('span');
        
        if ($valueDisplay.length) {
            $input.on('input', function() {
                $valueDisplay.text($(this).val() + ($valueDisplay.text().includes('px') ? 'px' : 
                                                     $valueDisplay.text().includes('%') ? '%' : 
                                                     $valueDisplay.text().includes('ms') ? 'ms' : ''));
            });
        }
    });

    // --- Upload Media ---
    let mediaFrame;
    $('.upload-logo-button').on('click', function(e) {
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title: 'Choisir un logo',
            button: { text: 'Utiliser ce logo' },
            multiple: false
        });
        mediaFrame.on('select', function() {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $('.header-logo-image-url').val(attachment.url);
            $('.logo-preview img').attr('src', attachment.url).show();
        });
        mediaFrame.open();
    });

}
