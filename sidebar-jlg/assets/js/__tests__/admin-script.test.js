describe('renderSvgUrlPreview', () => {
  let renderSvgUrlPreview;
  let $;

  beforeEach(() => {
    jest.resetModules();
    document.body.innerHTML = '';

    $ = require('jquery');
    $.fn.ready = function() { return this; };
    global.jQuery = $;
    global.$ = $;

    global.sidebarJLG = {
      svg_url_restrictions: {
        host: 'example.com',
        allowed_path: '/wp-content/uploads/sidebar-jlg/'
      },
      options: {},
      ajax_url: '',
      nonce: '',
      reset_nonce: '',
      icons_manifest: [],
      icon_fetch_action: ''
    };

    if (typeof window !== 'undefined') {
      window.sidebarJLG = global.sidebarJLG;
    }

    ({ renderSvgUrlPreview } = require('../admin/admin-legacy.ts'));
  });

  afterEach(() => {
    delete global.jQuery;
    delete global.$;
    delete global.sidebarJLG;
    if (typeof window !== 'undefined' && window.sidebarJLG) {
      delete window.sidebarJLG;
    }
    document.body.innerHTML = '';
  });

  it('escapes attributes when icon URL contains quotes', () => {
    document.body.innerHTML = `
      <span class="icon-preview"></span>
    `;

    const $preview = $('.icon-preview');
    const iconValue = 'https://example.com/wp-content/uploads/sidebar-jlg/icon.svg" onload="alert(1)';

    const didRender = renderSvgUrlPreview(iconValue, $preview);

    expect(didRender).toBe(true);
    const img = document.querySelector('.icon-preview img');
    expect(img).not.toBeNull();
    expect(img.getAttribute('src')).toBe('https://example.com/wp-content/uploads/sidebar-jlg/icon.svg%22%20onload=%22alert(1)');
    expect(img.getAttribute('alt')).toBe('preview');
    expect(img.getAttribute('onload')).toBeNull();
  });

  it('rejects URLs outside of the allowed upload directory', () => {
    document.body.innerHTML = `
      <input type="text" class="icon-input" />
      <span class="icon-preview"></span>
      <span class="icon-preview-status"></span>
    `;

    const $preview = $('.icon-preview');
    const iconValue = 'https://example.com/wp-content/uploads/other-folder/icon.svg';

    const didRender = renderSvgUrlPreview(iconValue, $preview);

    expect(didRender).toBe(false);
    expect(document.querySelector('.icon-preview img')).toBeNull();
    expect($('.icon-input').hasClass('icon-input-invalid')).toBe(true);
    expect($('.icon-preview-status').text()).toMatch(/ne sera pas enregistrée/i);
  });
});
