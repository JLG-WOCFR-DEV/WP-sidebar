describe('toggleAriaVisibility', () => {
  let toggleAriaVisibility;
  let $;

  beforeEach(() => {
    jest.resetModules();
    document.body.innerHTML = '';

    $ = require('jquery');
    $.fn.ready = function() { return this; };
    global.jQuery = $;
    global.$ = $;

    global.sidebarJLG = {
      svg_url_restrictions: { host: '', allowed_path: '' },
      options: {},
      ajax_url: '',
      nonce: '',
      reset_nonce: '',
      icons_manifest: [],
      icon_fetch_action: '',
      i18n: {}
    };

    if (typeof window !== 'undefined') {
      window.sidebarJLG = global.sidebarJLG;
    }

    ({ toggleAriaVisibility } = require('../admin-script.js'));
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

  it('hides and restores tabbable elements without removing focusability permanently', () => {
    const button = document.createElement('button');
    button.textContent = 'Action';
    document.body.appendChild(button);

    toggleAriaVisibility(button, false);

    expect(button.hidden).toBe(true);
    expect(button.getAttribute('aria-hidden')).toBe('true');
    expect(button.getAttribute('aria-disabled')).toBe('true');
    expect(button.tabIndex).toBe(-1);
    expect(button.style.display).toBe('none');

    toggleAriaVisibility(button, true);

    expect(button.hidden).toBe(false);
    expect(button.getAttribute('aria-hidden')).toBe('false');
    expect(button.getAttribute('aria-disabled')).toBe('false');
    expect(button.tabIndex).toBe(0);
    expect(button.hasAttribute('tabindex')).toBe(false);
    expect(button.style.display).toBe('');
  });

  it('works with jQuery collections and preserves explicit tabindex attributes', () => {
    const element = document.createElement('div');
    element.setAttribute('tabindex', '2');
    document.body.appendChild(element);
    const $element = $(element);

    toggleAriaVisibility($element, false);

    expect(element.hidden).toBe(true);
    expect($element.attr('aria-hidden')).toBe('true');
    expect($element.attr('aria-disabled')).toBe('true');
    expect(element.tabIndex).toBe(-1);

    toggleAriaVisibility($element, true, 'flex');

    expect(element.hidden).toBe(false);
    expect($element.attr('aria-hidden')).toBe('false');
    expect($element.attr('aria-disabled')).toBe('false');
    expect(element.tabIndex).toBe(2);
    expect(element.getAttribute('tabindex')).toBe('2');
    expect(element.style.display).toBe('flex');
  });
});
