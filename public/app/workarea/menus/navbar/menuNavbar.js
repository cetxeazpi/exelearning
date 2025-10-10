import NavbarFile from './items/navbarFile.js';
import NavbarUtilities from './items/navbarUtilities.js';
import NavbarStyles from './items/navbarStyles.js';
import NavbarHelp from './items/navbarHelp.js';

export default class MenuNavbar {
    constructor() {
        this.navbar = document.querySelector('#main #head #eXeLearningNavbar');
    }

    /**
     * Load navbar menu
     *
     */
    load() {
        this.disableLinks();
        this.loadJsNavbarClasses();
        this.addNavbarEvents();
        this.setupMobileShortcuts();
    }

    /**
     * Disable all navbar links
     *
     */
    disableLinks() {
        this.navbar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
            });
        });
    }

    /**
     * Load navbar menu classes
     *
     */
    loadJsNavbarClasses() {
        this.file = new NavbarFile(this);
        this.utilities = new NavbarUtilities(this);
        this.styles = new NavbarStyles(this);
        this.help = new NavbarHelp(this);
    }

    /**
     * Add navbar events
     *
     */
    addNavbarEvents() {
        this.file.setEvents();
        this.utilities.setEvents();
        this.styles.setStyleManagerEvent();
        this.help.setEvents();
    }

    setupMobileShortcuts() {
        if (!this.navbar) {
            return;
        }

        const closeParentDropdown = (trigger) => {
            const menu = trigger.closest('.dropdown-menu');
            if (!menu) {
                return;
            }
            const toggleId = menu.getAttribute('aria-labelledby');
            if (!toggleId) {
                return;
            }
            const toggle = document.getElementById(toggleId);
            if (!toggle) {
                return;
            }
            const Dropdown = window.bootstrap?.Dropdown;
            if (Dropdown) {
                Dropdown.getOrCreateInstance(toggle).hide();
            } else {
                menu.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
        };

        this.navbar
            .querySelectorAll('[data-mobile-forward]')
            .forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const selector = trigger.getAttribute(
                        'data-mobile-forward'
                    );
                    if (!selector) {
                        closeParentDropdown(trigger);
                        return;
                    }
                    const target = document.querySelector(selector);
                    if (!target) {
                        closeParentDropdown(trigger);
                        return;
                    }
                    target.click();
                    closeParentDropdown(trigger);
                });
            });

        this.navbar
            .querySelectorAll('[data-mobile-collapse]')
            .forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const selector = trigger.getAttribute(
                        'data-mobile-collapse'
                    );
                    if (!selector) {
                        closeParentDropdown(trigger);
                        return;
                    }
                    const target = document.querySelector(selector);
                    if (!target) {
                        closeParentDropdown(trigger);
                        return;
                    }
                    const Collapse = window.bootstrap?.Collapse;
                    const action =
                        trigger.getAttribute('data-mobile-action') || 'toggle';
                    if (Collapse) {
                        const instance = Collapse.getOrCreateInstance(target, {
                            toggle: false,
                        });
                        if (action === 'show') {
                            instance.show();
                        } else if (action === 'hide') {
                            instance.hide();
                        } else {
                            instance.toggle();
                        }
                    } else {
                        const shouldShow =
                            action === 'show'
                                ? true
                                : action === 'hide'
                                  ? false
                                  : !target.classList.contains('show');
                        target.classList.toggle('show', shouldShow);
                    }

                    const focusSelector =
                        trigger.getAttribute('data-mobile-focus');
                    if (focusSelector) {
                        window.setTimeout(() => {
                            const focusTarget =
                                document.querySelector(focusSelector);
                            focusTarget?.focus();
                        }, 150);
                    }

                    closeParentDropdown(trigger);
                });
            });
    }
}
