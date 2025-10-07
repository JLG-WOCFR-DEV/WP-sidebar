const FOCUSABLE_SELECTOR = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

describe('public-script.js', () => {
  let sidebar;
  let hamburgerBtn;
  let overlay;
  let mediaQueryList;
  let matchMediaListeners;
  const recordedDocumentListeners = [];
  const recordedWindowListeners = [];
  const dispatchPointerEvent = (target, type, options = {}) => {
    const event = new Event(type, { bubbles: true, cancelable: true });
    Object.entries(options).forEach(([key, value]) => {
      Object.defineProperty(event, key, {
        configurable: true,
        value,
      });
    });
    target.dispatchEvent(event);
    return event;
  };
  const getVisibleFocusable = () => {
    const elements = Array.from(sidebar.querySelectorAll(FOCUSABLE_SELECTOR));
    return elements.filter((element) => {
      if (element.hidden || element.disabled) {
        return false;
      }

      if (element.getAttribute('aria-hidden') === 'true') {
        return false;
      }

      let parent = element.parentElement;
      while (parent && parent !== document.body) {
        if (parent.hidden || parent.getAttribute('aria-hidden') === 'true') {
          return false;
        }
        parent = parent.parentElement;
      }

      return true;
    });
  };
  const setupMatchMedia = (matches = false) => {
    matchMediaListeners = [];
    mediaQueryList = {
      matches,
      media: '(prefers-reduced-motion: reduce)',
      addEventListener: jest.fn((event, handler) => {
        if (event === 'change') {
          matchMediaListeners.push(handler);
        }
      }),
      removeEventListener: jest.fn((event, handler) => {
        if (event === 'change') {
          matchMediaListeners = matchMediaListeners.filter((listener) => listener !== handler);
        }
      }),
      addListener: jest.fn((handler) => {
        matchMediaListeners.push(handler);
      }),
      removeListener: jest.fn((handler) => {
        matchMediaListeners = matchMediaListeners.filter((listener) => listener !== handler);
      }),
      dispatchEvent: jest.fn((event) => {
        matchMediaListeners.forEach((listener) => listener(event));
        return true;
      }),
      onchange: null,
    };

    window.matchMedia = jest.fn(() => mediaQueryList);
  };

  const removeRecordedListeners = () => {
    while (recordedDocumentListeners.length > 0) {
      const { target, type, listener, options } = recordedDocumentListeners.pop();
      target.removeEventListener(type, listener, options);
    }

    while (recordedWindowListeners.length > 0) {
      const { target, type, listener, options } = recordedWindowListeners.pop();
      target.removeEventListener(type, listener, options);
    }
  };

  const loadScript = (settings = {}, options = {}) => {
    removeRecordedListeners();

    jest.resetModules();

    const originalDocumentAddEventListener = document.addEventListener.bind(document);
    const originalWindowAddEventListener = window.addEventListener.bind(window);

    document.addEventListener = (type, listener, options) => {
      recordedDocumentListeners.push({ target: document, type, listener, options });
      return originalDocumentAddEventListener(type, listener, options);
    };

    window.addEventListener = (type, listener, options) => {
      recordedWindowListeners.push({ target: window, type, listener, options });
      return originalWindowAddEventListener(type, listener, options);
    };

    try {
      setupMatchMedia(options.prefersReducedMotion ?? false);

      global.sidebarSettings = {
        animation_type: 'fade',
        close_on_link_click: '0',
        remember_last_state: '0',
        state_storage_key: 'sidebar-jlg-state:test',
        active_profile_id: 'test',
        ...settings,
      };

      require('../public-script.js');
      document.dispatchEvent(new Event('DOMContentLoaded'));
    } finally {
      document.addEventListener = originalDocumentAddEventListener;
      window.addEventListener = originalWindowAddEventListener;
    }

    sidebar = document.getElementById('pro-sidebar');
    hamburgerBtn = document.getElementById('hamburger-btn');
    overlay = document.getElementById('sidebar-overlay');
  };

  beforeEach(() => {
    jest.useFakeTimers();
    window.localStorage.clear();

    document.body.innerHTML = `
      <div id="sidebar-overlay"></div>
      <button
        id="hamburger-btn"
        aria-expanded="false"
        aria-label="Ouvrir"
        data-open-label="Ouvrir"
        data-close-label="Fermer"
      >Menu</button>
      <aside id="pro-sidebar" data-hover-desktop="glow" data-hover-mobile="underline">
        <div class="sidebar-inner" style="max-height: 240px; overflow-y: auto;">
          <div class="sidebar-header">
            <button class="close-sidebar-btn">Fermer</button>
          </div>
          <input type="search" class="sidebar-search" placeholder="Rechercher" />
          <nav class="sidebar-menu">
            <ul class="sidebar-menu">
              <li class="menu-item has-submenu-toggle">
                <a href="#item">Item</a>
                <button
                  class="submenu-toggle"
                  type="button"
                  aria-expanded="false"
                  aria-controls="sidebar-submenu-1"
                  data-label-expand="Open"
                  data-label-collapse="Close"
                >
                  <span class="screen-reader-text">Open</span>
                  <span aria-hidden="true" class="submenu-toggle-indicator"></span>
                </button>
                <ul id="sidebar-submenu-1" class="submenu" aria-hidden="true">
                  <li class="menu-item"><a href="#child">Child</a></li>
                </ul>
              </li>
            </ul>
          </nav>
          <div class="menu-cta" data-cta-id="cta-demo" data-cta-analytics="demo">
            <a class="menu-cta__button" href="#cta">CTA</a>
          </div>
          <div class="social-icons">
            <a href="#social">Social</a>
          </div>
          <button type="button">Action</button>
          <div style="height: 400px"></div>
        </div>
      </aside>
    `;
    document.body.className = '';

    Object.defineProperty(window, 'innerWidth', {
      writable: true,
      configurable: true,
      value: 1200,
    });

    window.PointerEvent = function PointerEvent() {};
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
    delete global.sidebarSettings;
    delete window.matchMedia;
    delete window.PointerEvent;
    removeRecordedListeners();
    document.body.innerHTML = '';
    window.localStorage.clear();
  });

  test('opens and closes the sidebar via UI controls', () => {
    loadScript();

    expect(overlay.getAttribute('aria-hidden')).toBe('true');

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    expect(document.body.classList.contains('sidebar-open')).toBe(true);
    expect(hamburgerBtn.classList.contains('is-active')).toBe(true);
    expect(hamburgerBtn.getAttribute('aria-expanded')).toBe('true');
    expect(hamburgerBtn.getAttribute('aria-label')).toBe('Fermer');
    expect(overlay.classList.contains('is-visible')).toBe(true);
    expect(overlay.getAttribute('aria-hidden')).toBe('false');
    const focusableContent = getVisibleFocusable();
    expect(document.activeElement).toBe(focusableContent[0]);

    overlay.click();

    expect(document.body.classList.contains('sidebar-open')).toBe(false);
    expect(hamburgerBtn.classList.contains('is-active')).toBe(false);
    expect(hamburgerBtn.getAttribute('aria-expanded')).toBe('false');
    expect(hamburgerBtn.getAttribute('aria-label')).toBe('Ouvrir');
    expect(overlay.getAttribute('aria-hidden')).toBe('true');
    expect(document.activeElement).toBe(hamburgerBtn);
  });

  test('traps focus within the sidebar when open', () => {
    loadScript();

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    const focusableContent = getVisibleFocusable();
    const firstFocusable = focusableContent[0];
    const lastFocusable = focusableContent[focusableContent.length - 1];

    lastFocusable.focus();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));
    expect(document.activeElement).toBe(firstFocusable);

    firstFocusable.focus();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true }));
    expect(document.activeElement).toBe(lastFocusable);
  });

  test('maintains focus trap when only the search field is visible', () => {
    loadScript();

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    const closeButton = sidebar.querySelector('.close-sidebar-btn');
    const submenuToggle = sidebar.querySelector('.submenu-toggle');
    const actionButton = sidebar.querySelector('.sidebar-inner > button[type="button"]');
    const navLink = sidebar.querySelector('.sidebar-menu a');
    const socialLink = sidebar.querySelector('.social-icons a');
    const searchField = sidebar.querySelector('input[type="search"]');
    const ctaButton = sidebar.querySelector('.menu-cta__button');

    closeButton.hidden = true;
    submenuToggle.disabled = true;
    if (actionButton) {
      actionButton.disabled = true;
    }
    navLink.setAttribute('aria-hidden', 'true');
    socialLink.hidden = true;
    if (ctaButton) {
      ctaButton.setAttribute('aria-hidden', 'true');
      ctaButton.setAttribute('tabindex', '-1');
    }

    searchField.focus();

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));
    expect(document.activeElement).toBe(searchField);

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true }));
    expect(document.activeElement).toBe(searchField);
  });

  test('Escape closes the sidebar only when it is open', () => {
    loadScript();

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(document.body.classList.contains('sidebar-open')).toBe(false);

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

    expect(document.body.classList.contains('sidebar-open')).toBe(false);
    expect(hamburgerBtn.getAttribute('aria-expanded')).toBe('false');
    expect(overlay.classList.contains('is-visible')).toBe(false);
  });

  test('closes the sidebar when a link is clicked if the setting is enabled', () => {
    loadScript({ close_on_link_click: '1' });

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    expect(document.body.classList.contains('sidebar-open')).toBe(true);

    const link = sidebar.querySelector('.sidebar-menu a');
    link.click();

    jest.runOnlyPendingTimers();

    expect(document.body.classList.contains('sidebar-open')).toBe(false);
    expect(hamburgerBtn.getAttribute('aria-expanded')).toBe('false');
  });

  test('persists submenu state when remember_last_state is enabled', () => {
    const storageKey = 'sidebar-jlg-state:remember-test';
    loadScript({ remember_last_state: '1', state_storage_key: storageKey, active_profile_id: 'remember-test' });

    const toggle = sidebar.querySelector('.submenu-toggle');

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();
    toggle.click();

    const rawState = window.localStorage.getItem(storageKey);
    expect(rawState).toBeTruthy();
    const parsed = JSON.parse(rawState);
    expect(parsed.isOpen).toBe(true);
    expect(parsed.openSubmenus).toContain('sidebar-submenu-1');
  });

  test('restores remembered state on load', () => {
    const storageKey = 'sidebar-jlg-state:restored';
    window.localStorage.setItem(storageKey, JSON.stringify({
      isOpen: true,
      scrollTop: 120,
      openSubmenus: ['sidebar-submenu-1'],
      clickedCtas: ['cta-demo'],
    }));

    loadScript({ remember_last_state: '1', state_storage_key: storageKey, active_profile_id: 'restored' });

    expect(document.body.classList.contains('sidebar-open')).toBe(true);
    const submenu = document.getElementById('sidebar-submenu-1');
    expect(submenu.getAttribute('aria-hidden')).toBe('false');
    expect(submenu.classList.contains('is-open')).toBe(true);
    const inner = sidebar.querySelector('.sidebar-inner');
    expect(inner.scrollTop).toBe(120);
    const cta = sidebar.querySelector('.menu-cta');
    expect(cta.classList.contains('menu-cta--clicked')).toBe(true);
    expect(cta.getAttribute('data-cta-clicked')).toBe('true');
  });

  test('records CTA interactions when remembering state', () => {
    const storageKey = 'sidebar-jlg-state:cta';
    loadScript({ remember_last_state: '1', state_storage_key: storageKey, active_profile_id: 'cta' });

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    const ctaButton = sidebar.querySelector('.menu-cta__button');
    ctaButton.click();

    const rawState = window.localStorage.getItem(storageKey);
    expect(rawState).toBeTruthy();
    const parsed = JSON.parse(rawState);
    expect(parsed.clickedCtas).toContain('cta-demo');
    const cta = sidebar.querySelector('.menu-cta');
    expect(cta.classList.contains('menu-cta--clicked')).toBe(true);
  });

  test('updates spotlight hover variables in response to pointer movement', () => {
    loadScript();
    sidebar.setAttribute('data-hover-desktop', 'spotlight');
    window.dispatchEvent(new Event('resize'));

    const link = sidebar.querySelector('.sidebar-menu a');
    link.getBoundingClientRect = jest.fn(() => ({
      left: 0,
      top: 0,
      width: 200,
      height: 100,
    }));

    dispatchPointerEvent(link, 'pointermove', { clientX: 50, clientY: 25 });

    expect(link.style.getPropertyValue('--mouse-x')).toBe('25.00%');
    expect(link.style.getPropertyValue('--mouse-y')).toBe('25.00%');
    expect(link.style.getPropertyValue('--rotate-x')).toBe('5.00deg');
    expect(link.style.getPropertyValue('--rotate-y')).toBe('-5.00deg');

    dispatchPointerEvent(link, 'pointerleave');
    expect(link.style.getPropertyValue('--mouse-x')).toBe('');
    expect(link.style.getPropertyValue('--mouse-y')).toBe('');
    expect(link.style.getPropertyValue('--rotate-x')).toBe('');
    expect(link.style.getPropertyValue('--rotate-y')).toBe('');
  });

  test('updates glossy tilt hover rotations and resets on touch events', () => {
    loadScript();
    sidebar.setAttribute('data-hover-desktop', 'glossy-tilt');
    window.dispatchEvent(new Event('resize'));

    const link = sidebar.querySelector('.sidebar-menu a');
    link.getBoundingClientRect = jest.fn(() => ({
      left: 0,
      top: 0,
      width: 200,
      height: 120,
    }));

    dispatchPointerEvent(link, 'pointermove', {
      clientX: 150,
      clientY: 90,
      pointerType: 'touch',
    });

    expect(link.style.getPropertyValue('--mouse-x')).toBe('75.00%');
    expect(link.style.getPropertyValue('--mouse-y')).toBe('75.00%');
    expect(link.style.getPropertyValue('--rotate-x')).toBe('-5.00deg');
    expect(link.style.getPropertyValue('--rotate-y')).toBe('5.00deg');

    dispatchPointerEvent(link, 'pointerup', { pointerType: 'touch' });
    expect(link.style.getPropertyValue('--mouse-x')).toBe('');
    expect(link.style.getPropertyValue('--mouse-y')).toBe('');
    expect(link.style.getPropertyValue('--rotate-x')).toBe('');
    expect(link.style.getPropertyValue('--rotate-y')).toBe('');
  });

  test('edge swipe opens the sidebar when enabled', () => {
    loadScript({
      touch_gestures: {
        edge_swipe_enabled: '1',
        close_swipe_enabled: '0',
        edge_size: '80',
        min_distance: '40',
      },
    });

    Object.defineProperty(window, 'innerWidth', {
      writable: true,
      configurable: true,
      value: 480,
    });

    const pointerId = 21;
    dispatchPointerEvent(window, 'pointerdown', {
      pointerId,
      pointerType: 'touch',
      clientX: 12,
      clientY: 180,
      isPrimary: true,
    });

    const moveEvent = dispatchPointerEvent(window, 'pointermove', {
      pointerId,
      pointerType: 'touch',
      clientX: 140,
      clientY: 190,
    });

    expect(moveEvent.defaultPrevented).toBe(true);
    expect(document.body.classList.contains('sidebar-open')).toBe(true);
  });

  test('swipe closes the sidebar without returning focus when enabled', () => {
    loadScript({
      touch_gestures: {
        edge_swipe_enabled: '0',
        close_swipe_enabled: '1',
        edge_size: '60',
        min_distance: '50',
      },
    });

    Object.defineProperty(window, 'innerWidth', {
      writable: true,
      configurable: true,
      value: 480,
    });

    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    const pointerId = 42;
    dispatchPointerEvent(sidebar, 'pointerdown', {
      pointerId,
      pointerType: 'touch',
      clientX: 200,
      clientY: 140,
      isPrimary: true,
    });

    const moveEvent = dispatchPointerEvent(window, 'pointermove', {
      pointerId,
      pointerType: 'touch',
      clientX: 40,
      clientY: 150,
    });

    expect(moveEvent.defaultPrevented).toBe(true);

    dispatchPointerEvent(window, 'pointerup', {
      pointerId,
      pointerType: 'touch',
      clientX: 40,
      clientY: 150,
    });

    expect(document.body.classList.contains('sidebar-open')).toBe(false);
    expect(document.activeElement).not.toBe(hamburgerBtn);
  });

  test('auto opens after the configured timer delay', () => {
    loadScript({ behavior_triggers: { time_delay: 2, scroll_depth: 0 } });

    expect(document.body.classList.contains('sidebar-open')).toBe(false);
    jest.advanceTimersByTime(1900);
    expect(document.body.classList.contains('sidebar-open')).toBe(false);

    jest.advanceTimersByTime(200);
    expect(document.body.classList.contains('sidebar-open')).toBe(true);
  });

  test('auto opens once the scroll threshold is reached', () => {
    const htmlElement = document.documentElement;
    const originalScrollHeight = Object.getOwnPropertyDescriptor(htmlElement, 'scrollHeight');
    const originalClientHeight = Object.getOwnPropertyDescriptor(htmlElement, 'clientHeight');
    const originalPageYOffset = Object.getOwnPropertyDescriptor(window, 'pageYOffset');
    const originalInnerHeight = Object.getOwnPropertyDescriptor(window, 'innerHeight');

    Object.defineProperty(htmlElement, 'scrollHeight', { configurable: true, value: 2000, writable: true });
    Object.defineProperty(htmlElement, 'clientHeight', { configurable: true, value: 1000, writable: true });
    Object.defineProperty(window, 'pageYOffset', { configurable: true, value: 0, writable: true });
    Object.defineProperty(window, 'innerHeight', { configurable: true, value: 1000, writable: true });

    loadScript({ behavior_triggers: { time_delay: 0, scroll_depth: 50 } });

    expect(document.body.classList.contains('sidebar-open')).toBe(false);

    window.dispatchEvent(new Event('scroll'));
    expect(document.body.classList.contains('sidebar-open')).toBe(false);

    window.pageYOffset = 600;
    window.dispatchEvent(new Event('scroll'));
    expect(document.body.classList.contains('sidebar-open')).toBe(true);

    if (originalScrollHeight) {
      Object.defineProperty(htmlElement, 'scrollHeight', originalScrollHeight);
    } else {
      delete htmlElement.scrollHeight;
    }

    if (originalClientHeight) {
      Object.defineProperty(htmlElement, 'clientHeight', originalClientHeight);
    } else {
      delete htmlElement.clientHeight;
    }

    if (originalPageYOffset) {
      Object.defineProperty(window, 'pageYOffset', originalPageYOffset);
    } else {
      delete window.pageYOffset;
    }

    if (originalInnerHeight) {
      Object.defineProperty(window, 'innerHeight', originalInnerHeight);
    } else {
      delete window.innerHeight;
    }
  });

  test('manual close cancels subsequent auto-open triggers', () => {
    loadScript({ behavior_triggers: { time_delay: 1, scroll_depth: 0 } });

    jest.advanceTimersByTime(1000);
    expect(document.body.classList.contains('sidebar-open')).toBe(true);

    overlay.click();
    expect(document.body.classList.contains('sidebar-open')).toBe(false);

    jest.advanceTimersByTime(2000);
    expect(document.body.classList.contains('sidebar-open')).toBe(false);
  });

  test('logs localized missing elements message when provided', () => {
    document.body.innerHTML = '<div id="app"></div>';

    const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

    try {
      loadScript({
        debug_mode: '1',
        messages: {
          missingElements: 'Message localisé personnalisé',
        },
      });

      expect(errorSpy).toHaveBeenCalledWith('Message localisé personnalisé');
    } finally {
      errorSpy.mockRestore();
    }
  });

  test('respects the reduced motion preference', () => {
    loadScript({ close_on_link_click: '1' }, { prefersReducedMotion: true });

    expect(sidebar.classList.contains('animation-fade')).toBe(false);
    expect(sidebar.className).not.toMatch(/hover-effect-/);

    hamburgerBtn.click();

    expect(document.body.classList.contains('sidebar-open')).toBe(true);
    expect(jest.getTimerCount()).toBe(0);
    const focusableContent = getVisibleFocusable();
    expect(document.activeElement).toBe(focusableContent[0]);

    const link = sidebar.querySelector('.sidebar-menu a');
    link.click();

    expect(document.body.classList.contains('sidebar-open')).toBe(false);
    expect(jest.getTimerCount()).toBe(0);

    sidebar.setAttribute('data-hover-desktop', 'spotlight');
    window.dispatchEvent(new Event('resize'));

    link.getBoundingClientRect = jest.fn(() => ({
      left: 0,
      top: 0,
      width: 200,
      height: 100,
    }));

    dispatchPointerEvent(link, 'pointermove', { clientX: 100, clientY: 50 });

    expect(link.style.getPropertyValue('--mouse-x')).toBe('');
    expect(link.style.getPropertyValue('--mouse-y')).toBe('');
    expect(link.style.getPropertyValue('--rotate-x')).toBe('');
    expect(link.style.getPropertyValue('--rotate-y')).toBe('');
  });
});
