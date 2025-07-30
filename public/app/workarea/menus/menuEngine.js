/**
 * eXeLearning
 *
 * Responsible for the resize and drag and drop operation of the menu elements
 */

export default class MenuEngine {
    constructor() {
        this.menuWidthBaseSide = 250;
        this.menuHeightBaseTop = 250;
        this.separationHeight = 10;
        this.separationWidth = 5;
        this.zIndexMenuAnchored = 997;
        this.zIndexMenu = 998;
        this.zIndexMenuDrag = 999;
        this.menuClassLong = 'long';
        this.menuClassThick = 'thick';
        this.main = document.querySelector('body#main');
        this.head = document.querySelector('#main #head');
        this.headTop = document.querySelector('#main #head .top');
        this.headBottom = document.querySelector('#main #head .bottom');
        this.workarea = document.querySelector('#main #workarea');
        this.nodeContainer = document.querySelector(
            '#main #workarea #node-content-container'
        );
        this.menus = document.querySelectorAll('#main #workarea .menu');
        this.menuLabels = document.querySelectorAll(
            '#main #workarea .menu .menu_content .label'
        );
        this.menuNav = document.querySelector('#main #workarea #menu_nav');
        this.menuIdevices = document.querySelector(
            '#main #workarea #menu_idevices'
        );
        this.buttonMenuHeadNav = document.querySelector('#button_menu_nav');
        this.buttonMenuHeadIdevices = document.querySelector(
            '#button_menu_idevices'
        );
        this.sideMenusIds = [this.menuNav.id, this.menuIdevices.id];
        this.relationSizeMenus = {};
        this.relationSizeMenus[this.menuNav.id] = 50;
        this.relationSizeMenus[this.menuIdevices.id] = 50;
    }

    /**
     * Main behaviour
     *
     */
    behaviour() {
        this.addButtonsToMenuLabels();
        this.setDefaultZindex();
    }

    /**
     * Set default z-index of menus
     *
     */
    setDefaultZindex() {
        this.menuNav.style.zIndex = this.zIndexMenu;
        this.menuIdevices.style.zIndex = this.zIndexMenu;
    }

    /**
     * Get #main height
     *
     * @returns float
     */
    getMainHeight() {
        return this.main.clientHeight;
    }

    /**
     * Get #head height
     *
     * @returns float
     */
    getHeadHeight() {
        return this.headTop.clientHeight + this.headBottom.clientHeight;
    }

    /**
     * Get #workarea height
     *
     * @returns float
     */
    getWorkareaHeight() {
        return this.getMainHeight() - this.getHeadHeight();
    }

    /**
     * Get #workarea width
     *
     * @returns float
     */
    getWorkareaWidth() {
        return this.workarea.clientWidth;
    }

    /**
     * Get the remaining menu
     *
     * @param nodeElement
     * @returns nodeElement
     */
    getOtherSideMenu(menu) {
        let listIdsMenus = [...this.sideMenusIds];
        let otherMenuId = listIdsMenus.splice(listIdsMenus.indexOf(menu.id), 1);
        otherMenuId = listIdsMenus[0];
        let otherMenu = document.getElementById(otherMenuId);
        return otherMenu;
    }

    /**
     * Interact drag and drop move listener
     *
     * @param {*} event
     */
    dragMoveMenusListener(event) {
        var target = event.target;
        var parent = target.parentNode.parentNode;
        // Add class dragging
        parent.classList.add('dragging');
        // Keep the dragged position in the data-x/data-y attributes
        var x = (parseFloat(parent.getAttribute('data-x')) || 0) + event.dx;
        var y = (parseFloat(parent.getAttribute('data-y')) || 0) + event.dy;
        // Correct data-y of idevices menu
        let positionsMenu = this.getPositionsMenus();
        if (
            parent.id == 'menu_idevices' &&
            parent.style.top &&
            ((parent.getAttribute('pos') == 'left' &&
                positionsMenu.left.length > 1) ||
                (parent.getAttribute('pos') == 'right' &&
                    positionsMenu.right.length > 1)) &&
            parent.classList.contains('dragging') &&
            parent.getAttribute('data-y') == 0
        ) {
            y += parent.style.top.slice(0, -2) / 1.3;
            parent.style.top = '';
        }
        // Set max/min sizes of menu
        let actualHeight = parent.style.height.slice(0, -2);
        let actualWidth = parent.style.width.slice(0, -2);
        let maxHeight = Math.max(
            this.getWorkareaHeight() / 2,
            this.menuHeightBaseTop
        );
        let maxWidth = Math.max(
            this.getWorkareaWidth() / 2,
            this.menuWidthBaseSide
        );
        parent.style.maxHeight = maxHeight + 'px';
        parent.style.maxWidth = maxWidth + 'px';
        if (actualHeight > maxHeight) {
            parent.style.minHeight = maxHeight + 'px';
            parent.style.height = maxHeight + 'px';
        }
        if (actualWidth > maxWidth) {
            parent.style.minWidth = maxWidth + 'px';
            parent.style.width = maxWidth + 'px';
        }
        // Translate the element
        parent.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
        // Update the position attributes
        parent.setAttribute('data-x', x);
        parent.setAttribute('data-y', y);
        // Set positions
        this.setMenuPositionAttribute(parent);
        // Set z-index
        this.menus.forEach((menu) => {
            if (menu.getAttribute('pos') == 'free') {
                menu.style.zIndex = this.zIndexMenu;
            } else {
                menu.style.zIndex = this.zIndexMenuAnchored;
            }
        });
        if (parent.getAttribute('pos') == 'free') {
            parent.style.zIndex = this.zIndexMenuDrag;
        }
    }

    /**
     * Interact drag and drop end listener
     *
     * @param {*} event
     */
    dragendMoveMenusListener(event) {
        var target = event.target;
        var parent = target.parentNode.parentNode;
        // Remove class dragging
        parent.classList.remove('dragging');
        // Remove attribute to node container
        this.nodeContainer.setAttribute('dragging-pos', '');
        if (parent.getAttribute('pos') == 'free') {
            // Adapt size of menus and node container
            parent.style.minHeight = this.menuHeightBaseTop + 'px';
            parent.style.minWidth = this.menuWidthBaseSide + 'px';
            parent.style.maxHeight = '';
            parent.style.maxWidth = '';
        }
        // Set class based on the relationship between the width and height
        this.setClassRelationMenu();
    }

    /**
     * Set menu position attribute
     *
     * @param {*} element
     */
    setMenuPositionAttribute(element) {
        let vw = Math.max(
            document.documentElement.clientWidth || 0,
            window.innerWidth || 0
        );
        let vh = Math.max(
            document.documentElement.clientHeight || 0,
            window.innerHeight || 0
        );
        let rect = element.getBoundingClientRect();
        let newPosAttribute = 'free';
        if (rect.left <= vw * 0.05) newPosAttribute = 'left';
        if (rect.right >= vw) newPosAttribute = 'right';
        if (rect.top <= document.querySelector('#head').offsetHeight)
            newPosAttribute = 'top';
        // Add attribute to node container
        this.nodeContainer.setAttribute('dragging-pos', newPosAttribute);
        // Add attribute to menu
        element.setAttribute('pos', newPosAttribute);
    }

    /**
     * Add class based on the relationship between the width and height of the menu
     *
     */
    setClassRelationMenu() {
        this.menus.forEach((menu) => {
            let width = menu.offsetWidth;
            let height = menu.offsetHeight;
            if (
                width >= height * 1.8 &&
                height < this.getWorkareaHeight() / 2
            ) {
                menu.classList.remove(this.menuClassLong);
                menu.classList.add(this.menuClassThick);
                if (menu.getAttribute('size') == this.menuClassLong) {
                    menu.setAttribute('size', this.menuClassThick);
                }
            } else {
                menu.classList.remove(this.menuClassThick);
                menu.classList.add(this.menuClassLong);
                if (menu.getAttribute('size') == this.menuClassThick) {
                    menu.setAttribute('size', this.menuClassLong);
                }
            }
        });
    }

    /**
     * Get positions of menus
     *
     */
    getPositionsMenus() {
        let positions = { top: [], bottom: [], left: [], right: [], free: [] };
        // Get positions of menus
        this.menus.forEach((menu) => {
            positions[menu.getAttribute('pos')].push(menu.id);
        });
        return positions;
    }

    /**
     * Add button - to menu labels
     *
     * TODO: Move to menu class file
     */
    addButtonsMinToMenuLabels() {
        var p = this;
        this.menuLabels.forEach((label) => {
            let button = document.createElement('div');
            button.title = _('Minimize');
            button.classList.add('button-minimize');
            button.classList.add('showmin');
            button.classList.add('exe-app-tooltip');
            button.addEventListener('click', function () {
                p.minimizeMenu(label.parentElement.parentElement);
            });
            label.appendChild(button);
        });
        eXeLearning.app.common.initTooltips(this.menuLabels);
    }

    /**
     * Add buttons to menus label
     *
     * TODO: Move to menu class file
     */
    addButtonsToMenuLabels() {
        this.addButtonsMinToMenuLabels();
    }

    /**
     * Minimize menu
     *
     * TODO
     */
    minimizeMenu(menu) {
        if (menu.classList.contains('menu')) {
            if (menu.classList.contains('minimized')) {
                menu.classList.remove('minimized');
            } else {
                menu.classList.add('minimized');
            }
            // Get the other menu
            let otherMenu = this.getOtherSideMenu(menu);
            // If menu is anchored addapt the other menu
            if (menu.getAttribute('pos') != 'free') {
                // Addapt the other menu
                // - Left / Right
                if (
                    menu.getAttribute('pos') == 'left' ||
                    menu.getAttribute('pos') == 'right'
                ) {
                    if (
                        menu.getAttribute('pos') ==
                        otherMenu.getAttribute('pos')
                    ) {
                        otherMenu.style.height =
                            this.getWorkareaHeight() -
                            (menu.clientHeight + this.separationHeight * 3) +
                            'px';
                    }
                }
                // - Top
                if (menu.getAttribute('pos') == 'top') {
                    if (otherMenu.getAttribute('pos') == 'top') {
                        otherMenu.style.height = menu.clientHeight + 'px';
                    }
                }
            }
            // Remove max height of other menu
            otherMenu.style.maxHeight = '';
            menu.style.maxHeight = '';
        }
    }
}
