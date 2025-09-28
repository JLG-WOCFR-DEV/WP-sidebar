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

    ({ renderSvgUrlPreview } = require('../admin-script.js'));
  });

  afterEach(() => {
    delete global.jQuery;
    delete global.$;
    document.body.innerHTML = '';
  });

  it('escapes attributes when icon URL contains quotes', () => {
    document.body.innerHTML = `
      <span class="icon-preview"></span>
    `;

    const $preview = $('.icon-preview');
    const iconValue = 'https://example.com/icon.svg" onload="alert(1)';

    const didRender = renderSvgUrlPreview(iconValue, $preview);

    expect(didRender).toBe(true);
    const img = document.querySelector('.icon-preview img');
    expect(img).not.toBeNull();
    expect(img.getAttribute('src')).toBe('https://example.com/icon.svg%22%20onload=%22alert(1)');
    expect(img.getAttribute('alt')).toBe('preview');
    expect(img.getAttribute('onload')).toBeNull();
  });
});
