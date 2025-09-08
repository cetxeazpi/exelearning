/**
 * eXeLearning
 *
 * Loading the package nav structure in the menu
 */

export default class MenuStructureCompose {
    constructor(structureEngine) {
        this.structureEngine = structureEngine;
        this.menuNav = document.querySelector('#main #menu_nav');
        this.menuNavList = this.menuNav.querySelector('#main #nav_list');
        // Add object to engine
        this.structureEngine.menuStructureCompose = this;

        /* TO-DO: revert to previous version id no hyerarchy tree used. */
        // Inicialise items counter per level
        this.levelItemCounters = {};
    }

    compose() {
        this.data = this.structureEngine.data ? this.structureEngine.data : {};
        this.menuNavList.innerHTML = '';

        this.levelItemCounters = {};
        this.levelStructure = {};
        this.onlyChildMap = {};
        const childCount = {};
        for (let [id, element] of Object.entries(this.data)) {
            if (element.parent) {
                if (!childCount[element.parent]) {
                    childCount[element.parent] = 0;
                }
                childCount[element.parent]++;
            }
        }
        for (let [id, element] of Object.entries(this.data)) {
            if (element.parent && childCount[element.parent] === 1) {
                this.onlyChildMap[element.id] = true;
            }
        }
        for (let [id, element] of Object.entries(this.data)) {
            if (!element.parent) {
                this.buildTreeRecursive(element, this.menuNavList, 0);
            }
        }
    }

    /**
     *
     * @param {*} data
     * @returns
     */
    navElementsById(data) {
        let orderData = {};
        data.forEach((element) => {
            orderData[element.id] = element;
        });

        return orderData;
    }

    /* TO-DO: revert to previous version id no hyerarchy tree used. */

    /**
     * Generate node structure content element
     *
     * @param {Element} parent
     * @param {Object} node
     */
    makeNodeStructureContentNode(
        parent,
        node,
        level = 0,
        itemIndex = 1,
        isOnlyItem = false
    ) {
        let nodeDivElementNav = document.createElement('div');
        nodeDivElementNav.classList.add('nav-element');
        nodeDivElementNav.classList.add(`level${level}`);
        nodeDivElementNav.classList.add(`item${itemIndex}`);
        if (isOnlyItem) {
            nodeDivElementNav.classList.add('onlyitem');
        }
        // Atributes
        nodeDivElementNav.setAttribute('is-parent', false);
        nodeDivElementNav.setAttribute('nav-id', node.id);
        nodeDivElementNav.setAttribute('page-id', node.pageId);
        nodeDivElementNav.setAttribute('nav-parent', node.parent);
        nodeDivElementNav.setAttribute('order', node.order);
        // Classes
        if (node.open) {
            nodeDivElementNav.classList.add('toggle-on');
        } else {
            nodeDivElementNav.classList.add('toggle-off');
        }
        // Properties attributes/classes
        this.setPropertiesClassesToElement(nodeDivElementNav, node);
        // Icon
        let iconElement = this.makeNodeIconElement(node);
        nodeDivElementNav.appendChild(iconElement);
        // Text
        let textElement = this.makeNodeTextElement(node);
        nodeDivElementNav.appendChild(textElement);
        // Children container
        let childrenElement = document.createElement('div');
        childrenElement.classList.add('nav-element-children-container');
        nodeDivElementNav.appendChild(childrenElement);

        parent.appendChild(nodeDivElementNav);
        console.log(
            `Node ${node.id} -> level${level} item${itemIndex} ${isOnlyItem ? '(onlyitem)' : ''}`
        );
    }

    /**
     *
     * @param {Object} node
     * @returns {Element}
     */
    makeNodeIconElement(node) {
        let iconElement = document.createElement('span');
        iconElement.classList.add('exe-icon');
        iconElement.classList.add('nav-element-toggle');
        if (node.open) {
            iconElement.innerHTML = 'keyboard_arrow_down';
        } else {
            iconElement.innerHTML = 'keyboard_arrow_right';
        }
        return iconElement;
    }

    /**
     *
     * @param {*} node
     * @returns {Element}
     */
    makeNodeRootIconElement(node) {
        let iconElement = document.createElement('span');
        iconElement.classList.add('root-icon');
        iconElement.innerHTML = node.icon;
        return iconElement;
    }

    /**
     *
     * @param {Object} node
     * @returns {Element}
     */
    makeNodeTextElement(node) {
        let textElement = document.createElement('span');
        textElement.classList.add('nav-element-text');

        let spanText = document.createElement('span');
        spanText.classList.add('node-text-span');
        spanText.innerText = String(node.pageName);
        textElement.append(spanText);
        if (node.id === 'root') {
            let iconRootElement = this.makeNodeRootIconElement(node);
            textElement.append(iconRootElement);
        } else {
            textElement.setAttribute('draggable', true);
            let menuIcon = document.createElement('span');
            menuIcon.classList.add('small-icon', 'settings-icon-green');
            let menuButton = document.createElement('span');
            menuButton.classList.add(
                'btn',
                'button-tertiary',
                'button-narrow',
                'd-flex',
                'justify-content-center',
                'align-items-center',
                'node-menu-button',
                'page-settings'
            );
            menuButton.setAttribute('data-menunavid', node.id);
            menuButton.style.cursor = 'pointer';
            menuButton.append(menuIcon);
            textElement.append(menuButton);
        }

        // Drag over nav element
        let dragOverElement = document.createElement('span');
        dragOverElement.classList.add('drag-over-border');
        textElement.append(dragOverElement);

        return textElement;
    }

    /**
     * Add atributes and classes to node element element based in properties
     *
     * @param {Element} node
     * @param {Object} node
     */
    setPropertiesClassesToElement(nodeElement, node) {
        // visibility
        if (node.properties.visibility.value != '') {
            nodeElement.setAttribute(
                'export-view',
                node.properties.visibility.value
            );
        }
    }

    /* TO-DO: revert to previous version id no hyerarchy tree used. */
    /**
     * Recalculate levelX classes whan DOM is ready
     */
    recalculateLevelsFromDomTree() {
        const updateNodeLevel = (element, level) => {
            element.classList.add(`level${level}`);

            const childrenContainer = element.querySelector(
                '.nav-element-children-container'
            );
            if (childrenContainer) {
                const children = [...childrenContainer.children].filter(
                    (child) => child.classList.contains('nav-element')
                );
                for (let child of children) {
                    updateNodeLevel(child, level + 1);
                }
            }
        };

        const rootNodes = [...this.menuNavList.children].filter((child) =>
            child.classList.contains('nav-element')
        );
        for (let rootNode of rootNodes) {
            updateNodeLevel(rootNode, 0);
        }
    }

    buildTreeRecursive(node, parentElement, level) {
        if (!this.levelItemCounters[level]) {
            this.levelItemCounters[level] = 1;
        }
        const itemIndex = this.levelItemCounters[level];
        const isOnlyItem = this.onlyChildMap[node.id] === true;

        this.makeNodeStructureContentNode(
            parentElement,
            node,
            level,
            itemIndex,
            isOnlyItem
        );

        this.levelItemCounters[level]++;

        const thisNodeElement = parentElement.querySelector(
            `.nav-element[nav-id="${node.id}"]`
        );
        const childrenContainer = thisNodeElement.querySelector(
            '.nav-element-children-container'
        );

        for (let [id, childNode] of Object.entries(this.data)) {
            if (childNode.parent === node.id) {
                thisNodeElement.setAttribute('is-parent', true);
                this.buildTreeRecursive(
                    childNode,
                    childrenContainer,
                    level + 1
                );
            }
        }
    }

    /**
     * Calculates node jerarchy through their parents
     * @param {Object} node
     * @returns {number}
     */
    getNodeLevel(node) {
        let level = 0;
        while (node.parent && this.data[node.parent]) {
            node = this.data[node.parent];
            level++;
        }
        return level;
    }
}
