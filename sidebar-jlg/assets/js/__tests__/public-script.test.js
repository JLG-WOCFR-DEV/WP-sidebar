const FOCUSABLE_SELECTOR = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

describe('public-script.js', () => {
  let sidebar;
  let hamburgerBtn;
  let overlay;
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

  const loadScript = (settings = {}) => {
    jest.resetModules();

    global.sidebarSettings = {
      animation_type: 'fade',
      close_on_link_click: '0',
      ...settings,
    };

    require('../public-script.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    sidebar = document.getElementById('pro-sidebar');
    hamburgerBtn = document.getElementById('hamburger-btn');
    overlay = document.getElementById('sidebar-overlay');
  };

  beforeEach(() => {
    jest.useFakeTimers();

    document.body.innerHTML = `
      <div id="sidebar-overlay"></div>
      <button id="hamburger-btn" aria-expanded="false">Menu</button>
      <aside id="pro-sidebar" data-hover-desktop="glow" data-hover-mobile="underline">
        <button class="close-sidebar-btn">Fermer</button>
        <input type="search" class="sidebar-search" placeholder="Rechercher" />
        <nav class="sidebar-menu">
          <a href="#item">Item</a>
        </nav>
        <div class="social-icons">
          <a href="#social">Social</a>
        </div>
        <button type="button">Action</button>
      </aside>
    `;
    document.body.className = '';

    Object.defineProperty(window, 'innerWidth', {
      writable: true,
      configurable: true,
      value: 1200,
    });

    loadScript();
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
    delete global.sidebarSettings;
    document.body.innerHTML = '';
  });

  test('opens and closes the sidebar via UI controls', () => {
    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    expect(document.body.classList.contains('sidebar-open')).toBe(true);
    expect(hamburgerBtn.classList.contains('is-active')).toBe(true);
    expect(hamburgerBtn.getAttribute('aria-expanded')).toBe('true');
    expect(overlay.classList.contains('is-visible')).toBe(true);
    const focusableContent = getVisibleFocusable();
    expect(document.activeElement).toBe(focusableContent[0]);

    overlay.click();

    expect(document.body.classList.contains('sidebar-open')).toBe(false);
    expect(hamburgerBtn.classList.contains('is-active')).toBe(false);
    expect(hamburgerBtn.getAttribute('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(hamburgerBtn);
  });

  test('traps focus within the sidebar when open', () => {
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
    hamburgerBtn.click();
    jest.runOnlyPendingTimers();

    const closeButton = sidebar.querySelector('.close-sidebar-btn');
    const actionButton = sidebar.querySelector('button[type="button"]');
    const navLink = sidebar.querySelector('.sidebar-menu a');
    const socialLink = sidebar.querySelector('.social-icons a');
    const searchField = sidebar.querySelector('input[type="search"]');

    closeButton.hidden = true;
    actionButton.disabled = true;
    navLink.setAttribute('aria-hidden', 'true');
    socialLink.hidden = true;

    searchField.focus();

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));
    expect(document.activeElement).toBe(searchField);

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true }));
    expect(document.activeElement).toBe(searchField);
  });

  test('Escape closes the sidebar only when it is open', () => {
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
});
