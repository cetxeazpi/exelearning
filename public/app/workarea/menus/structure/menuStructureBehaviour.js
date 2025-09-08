/**
 * eXeLearning
 *
 * Set the events in the nav menu
 */

export default class MenuStructureBehaviour {
    constructor(structureEngine) {
        this.structureEngine = structureEngine;
        this.menuNav = document.querySelector('#main #menu_nav');
        this.menuNavList = this.menuNav.querySelector('#main #nav_list');
        this.nodeSelected = null;
        this.nodeDrag = null;
        this.enterDragMenuStructureCount = 0;
        this.dbclickNode = false;
        // Add object to engine
        this.structureEngine.menuStructureBehaviour = this;
    }

    /**
     *
     */
    behaviour(firtsTime) {
        // Button related events are only loaded once
        if (firtsTime) {
            this.addEventNavNewNodeOnclick();
            this.addEventNavPropertiesNodeOnclick();
            this.addEventNavRemoveNodeOnclick();
            this.addEventNavCloneNodeOnclick();
            this.addEventNavImportIdevicesOnclick();
            //this.addEventNavCheckOdePageBrokenLinksOnclick();
            this.addEventNavMovPrevOnClick();
            this.addEventNavMovNextOnClick();
            this.addEventNavMovUpOnClick();
            this.addEventNavMovDownOnClick();
        }
        // Nav elements drag&drop events
        this.addEventNavElementOnclick();
        this.addEventNavElementOnDbclick();
        this.addEventNavElementIconOnclick();
        this.addEventNavElementOnMenuIconClic();
        this.addDragAndDropFunctionalityToNavElements();
    }

    /*******************************************************************************
     * EVENTS
     *******************************************************************************/

    /**
     *
     */
    addEventNavElementOnclick() {
        var navLabelElements = this.menuNav.querySelectorAll(
            `.nav-element > .nav-element-text`
        );
        navLabelElements.forEach((element) => {
            element.addEventListener('click', (event) => {
                event.stopPropagation();
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                this.selectNode(element.parentElement).then((nodeElement) => {
                    if (eXeLearning.app.project.checkOpenIdevice()) return;
                    // Check dbclick
                    if (nodeElement && this.dbclickNode) {
                        this.showModalPropertiesNode();
                        this.dbclickNode = false;
                    }
                });
            });
        });
    }

    /**
     *
     */
    addEventNavElementOnDbclick() {
        var navLabelElements = this.menuNav.querySelectorAll(
            `.nav-element:not([nav-id="root"]) > .nav-element-text`
        );
        navLabelElements.forEach((element) => {
            element.addEventListener('dblclick', (event) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                event.stopPropagation();
                this.dbclickNode = true;
            });
        });
    }

    addEventNavElementOnMenuIconClic() {
        var navLabelMenuElements = this.menuNav.querySelectorAll(
            `.nav-element:not([nav-id="root"]) > .nav-element-text .node-menu-button`
        );
        navLabelMenuElements.forEach((element) => {
            element.addEventListener('click', (event) => {
                event.stopPropagation();
                let node = this.structureEngine.getNode(
                    element.getAttribute('data-menunavid')
                );
                node.showModalProperties();
                this.mutationForModalProperties();
            });
        });
    }

    /**
     *
     */
    addEventNavElementIconOnclick() {
        var navIconsElements = this.menuNav.querySelectorAll(
            `.nav-element > .exe-icon`
        );
        navIconsElements.forEach((element) => {
            element.addEventListener('click', (event) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                event.stopPropagation();
                let navElement = element.parentElement;
                let node = this.structureEngine.getNode(
                    navElement.getAttribute('nav-id')
                );
                if (navElement.classList.contains('toggle-on')) {
                    navElement.classList.remove('toggle-on');
                    navElement.classList.add('toggle-off');
                    element.innerHTML = 'keyboard_arrow_right';
                    node.open = false;
                } else {
                    navElement.classList.remove('toggle-off');
                    navElement.classList.add('toggle-on');
                    element.innerHTML = 'keyboard_arrow_down';
                    node.open = true;
                }
            });
        });
    }

    /**
     *
     */
    addEventNavNewNodeOnclick() {
        this.menuNav
            .querySelector('.button_nav_action.action_add')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                this.showModalNewNode();
            });
    }

    /**
     *
     */
    addEventNavPropertiesNodeOnclick() {
        this.menuNav
            .querySelector('.button_nav_action.action_properties')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    this.showModalPropertiesNode();
                }
            });
    }

    /**
     *
     */
    addEventNavRemoveNodeOnclick() {
        let params = { odeSessionId: eXeLearning.app.project.odeSession };
        this.menuNav
            .querySelector('.button_nav_action.action_delete')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    eXeLearning.app.api
                        .postCheckUsersOdePage(params)
                        .then((response) => {
                            if (response.isAvailable == true) {
                                this.showModalRemoveNode();
                            } else {
                                eXeLearning.app.modals.alert.show({
                                    title: _('multiple users on page'),
                                    body: _(response.responseMessage),
                                    contentId: 'error',
                                });
                            }
                        });
                }
            });
    }

    /**
     *
     */
    addEventNavCloneNodeOnclick() {
        this.menuNav
            .querySelector('.button_nav_action.action_clone')
            .addEventListener('click', async (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    await this.structureEngine.cloneNodeAndReload(
                        this.nodeSelected.getAttribute('nav-id')
                    );
                    this.showModalRenameNode();
                }
            });
    }

    /**
     *
     * @returns
     */
    createIdevicesUploadInput() {
        let inputUpload = document.createElement('input');
        inputUpload.classList.add('local-ode-file-upload-input', 'd-none');
        inputUpload.setAttribute('type', 'file');
        inputUpload.setAttribute('name', 'local-ode-file-upload');
        inputUpload.setAttribute('accept', '.block,.idevice');
        inputUpload.id = 'local-ode-file-upload';
        let label = document.createElement('label');
        label.setAttribute('for', inputUpload.id);
        label.classList.add('visually-hidden');
        label.textContent = _('Upload iDevice file');

        inputUpload.addEventListener('change', (e) => {
            let uploadOdeFile = document.querySelector(
                '.local-ode-file-upload-input'
            );
            let odeFile = uploadOdeFile.files[0];
            let newUploadInput = this.createIdevicesUploadInput();
            inputUpload.remove();
            this.menuNav.append(newUploadInput);

            eXeLearning.app.modals.openuserodefiles.largeFilesUpload(
                odeFile,
                true
            );
        });

        this.menuNav.append(label);
        this.menuNav.append(inputUpload);
        return inputUpload;
    }

    /**
     *
     */
    addEventNavImportIdevicesOnclick() {
        this.createIdevicesUploadInput();
        this.menuNav
            .querySelector('.button_nav_action.action_import_idevices')
            .addEventListener('click', async (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    this.menuNav
                        .querySelector('input.local-ode-file-upload-input')
                        .click();
                }
            });
    }

    /**
     * Get broken links in all ode on page
     * @returns
     */
    async getOdePageBrokenLinksEvent(pageId) {
        let odePageBrokenLinks =
            await eXeLearning.app.api.getOdePageBrokenLinks(pageId);
        return odePageBrokenLinks;
    }

    /**
     *
     */
    addEventNavCheckOdePageBrokenLinksOnclick() {
        this.menuNav
            .querySelector('.button_nav_action.action_check_broken_links')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    let selectedNav = this.menuNav.querySelector(
                        '#main .toggle-on .selected'
                    );
                    let pageId = selectedNav.getAttribute('page-id');
                    this.getOdePageBrokenLinksEvent(pageId).then((response) => {
                        if (!response.responseMessage) {
                            // Show eXe OdeBrokenList modal
                            eXeLearning.app.modals.odebrokenlinks.show(
                                response
                            );
                        } else {
                            // Open eXe alert modal
                            eXeLearning.app.modals.alert.show({
                                title: _('Broken links'),
                                body: 'No broken links found.',
                            });
                        }
                    });
                }
            });
    }

    /**
     *
     */
    addEventNavMovPrevOnClick() {
        this.menuNav
            .querySelector('.button_nav_action.action_move_prev')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    this.structureEngine.moveNodePrev(
                        this.nodeSelected.getAttribute('nav-id')
                    );
                }
            });
    }

    /**
     *
     */
    addEventNavMovNextOnClick() {
        this.menuNav
            .querySelector('.button_nav_action.action_move_next')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    this.structureEngine.moveNodeNext(
                        this.nodeSelected.getAttribute('nav-id')
                    );
                }
            });
    }

    /**
     *
     */
    addEventNavMovUpOnClick() {
        this.menuNav
            .querySelector('.button_nav_action.action_move_up')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    this.structureEngine.moveNodeUp(
                        this.nodeSelected.getAttribute('nav-id')
                    );
                }
            });
    }

    /**
     *
     */
    addEventNavMovDownOnClick() {
        this.menuNav
            .querySelector('.button_nav_action.action_move_down')
            .addEventListener('click', (e) => {
                if (eXeLearning.app.project.checkOpenIdevice()) return;
                if (this.nodeSelected) {
                    this.structureEngine.moveNodeDown(
                        this.nodeSelected.getAttribute('nav-id')
                    );
                }
            });
    }

    /*******************************************************************************
     * MODALS
     *******************************************************************************/

    /**
     *
     */
    showModalNewNode() {
        let parentNodeId = this.nodeSelected
            ? this.nodeSelected.getAttribute('nav-id')
            : null;
        let bodyText = _('Name');
        let bodyInput = `<input id="input-new-node" class="exe-input" type='text' value='' >`;
        let body = `<p>${bodyText}:</p><p>${bodyInput}</p>`;
        let modalConfirm = eXeLearning.app.modals.confirm;
        modalConfirm.show({
            title: _('New page'),
            contentId: 'new-node-modal',
            body: body,
            confirmButtonText: _('Save'),
            cancelButtonText: _('Cancel'),
            focusFirstInputText: true,
            confirmExec: () => {
                let title =
                    modalConfirm.modalElement.querySelector(
                        '#input-new-node'
                    ).value;
                if (!title || !title.replaceAll(' ', '')) title = _('New page');
                this.structureEngine.createNodeAndReload(parentNodeId, title);
            },
            behaviour: () => {
                let inputElement =
                    modalConfirm.modalElementBody.querySelector('input');
                this.addBehaviourToInputTextModal(inputElement, () => {
                    modalConfirm.confirm();
                });
            },
        });
    }

    /**
     *
     */
    showModalRenameNode() {
        let node = this.structureEngine.getNode(
            this.nodeSelected.getAttribute('nav-id')
        );
        let bodyText = _('New name');
        let bodyInput = `<input id="input-rename-node" class="exe-input" type='text' value='${node.pageName}' >`;
        let body = `<p>${bodyText}:</p><p>${bodyInput}</p>`;
        let modalConfirm = eXeLearning.app.modals.confirm;
        modalConfirm.show({
            title: _('Rename page'),
            contentId: 'rename-node-modal',
            body: body,
            confirmButtonText: _('Save'),
            cancelButtonText: _('Cancel'),
            confirmExec: () => {
                let newTitle =
                    eXeLearning.app.modals.confirm.modalElement.querySelector(
                        '#input-rename-node'
                    ).value;
                this.structureEngine.renameNodeAndReload(node.id, newTitle);
            },
            behaviour: () => {
                let inputElement =
                    modalConfirm.modalElementBody.querySelector('input');
                this.addBehaviourToInputTextModal(inputElement, () => {
                    modalConfirm.confirm();
                });
            },
        });
    }

    /**
     *
     */
    showModalPropertiesNode() {
        let node = this.structureEngine.getNode(
            this.nodeSelected.getAttribute('nav-id')
        );
        node.showModalProperties();
        this.mutationForModalProperties();
    }

    mutationForModalProperties() {
        const observer = new MutationObserver((mutations, obs) => {
            const checkbox = document.querySelector(
                '.property-value[property="editableInPage"]'
            );
            const input = document.querySelector(
                '.property-value[property="titlePage"]'
            );
            const titleInput = document.querySelector(
                '.property-value[property="titleNode"]'
            );
            const titlePageWrapper = document.querySelector('#titlePage');
            if (checkbox && input && titleInput && titlePageWrapper) {
                const syncInputState = () => {
                    const isChecked = checkbox.checked;
                    input.disabled = !isChecked;
                    if (!isChecked) {
                        // Menu item and page have the same title
                        input.value = titleInput.value || '';
                        titlePageWrapper.style.display = 'none';
                    } else {
                        // Different title in page
                        titlePageWrapper.style.display = 'block';
                    }
                };

                syncInputState();

                checkbox.addEventListener('change', syncInputState);
                titleInput.addEventListener('input', syncInputState);

                obs.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    /**
     *
     */
    showModalRemoveNode() {
        let modalConfirm = eXeLearning.app.modals.confirm;
        modalConfirm.show({
            title: _('Delete page'),
            contentId: 'delete-node-modal',
            body: _('Do you want to delete the page? This cannot be undone.'),
            confirmButtonText: _('Yes'),
            confirmExec: () => {
                this.structureEngine.removeNodeCompleteAndReload(
                    this.nodeSelected.getAttribute('nav-id')
                );
            },
        });
    }

    /**
     *
     */
    showModalCloneNode() {
        let modalConfirm = eXeLearning.app.modals.confirm;
        modalConfirm.show({
            title: _('Clone page'),
            contentId: 'clone-node-modal',
            body: _('Do you want to clone the page?'),
            confirmButtonText: _('Yes'),
            confirmExec: () => {
                this.structureEngine.cloneNodeAndReload(
                    this.nodeSelected.getAttribute('nav-id')
                );
            },
        });
    }

    /*******************************************************************************
     * DRAG & DROP
     *******************************************************************************/

    /**
     *
     */
    addDragAndDropFunctionalityToNavElements() {
        var navLabelElements = this.menuNav.querySelectorAll(
            `.nav-element:not([nav-id="root"]) > .nav-element-text`
        );
        navLabelElements.forEach((element) => {
            this.addDragAndDropFunctionalityToNode(element);
        });
    }

    /**
     *
     * @param {*} node
     */
    addDragAndDropFunctionalityToNode(node) {
        this.addEventDragOver(node);
        this.addEventDragStart(node);
        this.addEventDragEnd(node);
    }

    /**
     *
     * @param {*} node
     */
    addEventDragOver(node) {
        node.addEventListener('dragover', (event) => {
            event.stopPropagation();
            // Clear elements
            this.clearMenuNavDragOverClasses();
            // Drag node page
            if (this.nodeDrag) {
                event.preventDefault();
                if (this.nodeDrag != node.parentElement) {
                    node.classList.add('drag-over');
                }
            }
            // Drag idevice/block component
            else if (eXeLearning.app.project.idevices.draggedElement) {
                let componentDragged =
                    eXeLearning.app.project.idevices.draggedElement;
                // Idevice of content
                if (
                    componentDragged &&
                    componentDragged.classList.contains('idevice_actions')
                ) {
                    event.preventDefault();
                    node.classList.add('drag-over');
                    node.classList.add('idevice-content-over');
                }
                // Block of content
                else if (
                    componentDragged &&
                    componentDragged.classList.contains('box-head')
                ) {
                    event.preventDefault();
                    node.classList.add('drag-over');
                    node.classList.add('block-content-over');
                }
            }
        });
    }

    /**
     *
     * @param {*} node
     */
    addEventDragStart(node) {
        node.addEventListener('dragstart', async (event) => {
            if (eXeLearning.app.project.checkOpenIdevice()) {
                event.preventDefault();
                return;
            }
            event.stopPropagation();
            node.classList.add('dragging');
            let parent = node.parentElement;
            this.nodeDrag = parent;
            await this.selectNode(parent);
        });
    }

    /**
     *
     * @param {*} node
     */
    addEventDragEnd(node) {
        node.addEventListener('dragend', (event) => {
            event.stopPropagation();
            if (this.nodeDrag) {
                let nodeBase = this.menuNav.querySelector(
                    '.nav-element > .nav-element-text.drag-over'
                );
                if (nodeBase) {
                    let nodeBaseId =
                        nodeBase.parentElement.getAttribute('nav-id');
                    let nodeMovId = this.nodeDrag.getAttribute('nav-id');
                    this.structureEngine.moveNodeToNode(nodeMovId, nodeBaseId);
                }
                // Reset
                this.clearMenuNavDragOverClasses();
                node.classList.remove('dragging');
                this.nodeDrag = null;
            }
        });
    }

    /*******************************************************************************
     * TOOLTIPS
     *******************************************************************************/

    /**
     *
     */
    addTooltips() {
        $('#nav_list .nav-element-text', this.menuNav)
            .eq(0)
            .attr('title', _('Content properties'))
            .addClass('exe-app-tooltip');
        $('button.button_nav_action', this.menuNav).addClass('exe-app-tooltip');
        eXeLearning.app.common.initTooltips(this.menuNav);
    }

    /*******************************************************************************
     * NODE SELECTION
     *******************************************************************************/

    /**
     * Remove class "selected" in node elements
     *
     */
    deselectNodes() {
        let navElements = this.menuNav.querySelectorAll('.nav-element');
        navElements.forEach((e) => {
            e.classList.remove('selected');
        });
    }

    /**
     * Select first node
     *
     */
    async selectFirst() {
        let navElements = this.menuNav.querySelectorAll('.nav-element');
        if (navElements.length >= 1) {
            return await this.selectNode(navElements[0]);
        }
    }

    /**
     * Select node
     *
     * @param {Element} element
     * @returns {Promise<Element>}
     */
    async selectNode(element) {
        return new Promise(async (resolve, reject) => {
            let response = false;
            let time = 50;
            // We do not reload the page in case the node is already selected
            if (
                this.nodeSelected &&
                element.getAttribute('nav-id') ==
                    this.nodeSelected.getAttribute('nav-id')
            ) {
                this.setNodeSelected(element);
                response = element;
            } else {
                // Load the page components from the api
                let loadPageProcessOk =
                    await eXeLearning.app.project.idevices.loadApiIdevicesInPage(
                        true,
                        element
                    );
                if (loadPageProcessOk) {
                    this.deselectNodes();
                    this.setNodeSelected(element);
                    time = 100;
                    response = element;
                }
                this.checkIfEmptyNode();
            }
            setTimeout(() => {
                // Add the Properties tooltip
                this.addTooltips();
                resolve(response);
            }, time);
        });
    }

    checkIfEmptyNode() {
        this.nodeContent = document.getElementById('node-content');
        const validArticles = this.nodeContent.querySelectorAll(
            'article:not(#empty_articles), #properties-node-content-form'
        );
        const emptyArticles = this.nodeContent.querySelector('#empty_articles');
        if (validArticles.length === 0) {
            if (!emptyArticles) {
                const emptyContainer = document.createElement('article');
                emptyContainer.id = 'empty_articles';
                emptyContainer.classList.add('empty-node-message');
                emptyContainer.classList.add('box');

                const messageBox = document.createElement('div');
                messageBox.classList.add('empty-block-message-box');

                const icon = document.createElement('div');
                icon.classList.add('empty-block-message-icon');
                icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="39" viewBox="0 0 40 39" fill="none">
  <g clip-path="url(#clip0_2090_10905)">
    <path d="M24.6923 37.4744C27.4724 37.4744 30.1384 36.3567 32.104 34.3674L35.81 30.8172C38.1829 28.4161 39.1386 25.1594 39.1386 21.7634C39.1386 18.3675 37.8054 15.1108 35.4326 12.7097L32.2663 9.50544L25.0066 1.95903C23.5593 0.494532 21.2129 0.494532 19.7656 1.95903C18.3184 3.42353 18.047 5.54142 19.4942 7.00592L19.9995 7.51769L17.1451 4.6109C15.6979 3.1464 13.3515 3.1464 11.9042 4.6109C10.4569 6.07539 10.4569 8.44975 11.9042 9.91425L14.1748 12.5102L10.3318 8.32305C8.88448 6.85855 6.53807 6.85855 5.09079 8.32305C3.64352 9.78755 3.64352 12.1623 5.09079 13.6268L9.18189 17.5031L7.18725 15.7485C5.73998 14.284 3.39357 14.284 1.94629 15.7485C0.499022 17.213 0.499022 19.5877 1.94629 21.0522L10.014 29.1529L5.85328 24.9921V29.1529C5.85328 33.8392 9.72773 37.4741 14.3589 37.4741H24.6916L24.6923 37.4744Z" stroke="#333333" stroke-width="1.72249" stroke-linecap="round" stroke-linejoin="round"/>
  </g>
  <defs>
    <clipPath id="clip0_2090_10905">
      <rect width="40" height="38.3357" fill="white"/>
    </clipPath>
  </defs>
</svg>`;

                const title = document.createElement('h2');
                title.classList.add('empty-block-message-title');
                title.textContent = _('Drag an iDevice in and start building');

                const description = document.createElement('p');
                description.classList.add('empty-block-message-text');
                description.innerHTML = _(
                    'Just drag an iDevice onto this page to start designing your content.'
                );

                messageBox.appendChild(icon);
                messageBox.appendChild(title);
                messageBox.appendChild(description);

                const arrow = document.createElement('div');
                arrow.classList.add('empty-block-arrow-icon');
                arrow.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="86" height="122" viewBox="0 0 86 122" fill="none">
  <path d="M70.3061 102.996C75.9909 101.837 78.889 95.0305 86 97.213C85.0265 98.5016 84.5587 99.4256 83.832 100.036C75.8142 106.771 67.8041 113.517 59.6611 120.096C58.3274 121.173 56.0909 122.332 54.7452 121.912C52.2702 121.138 52.7247 118.611 53.2375 116.398C54.8303 109.523 56.3374 102.627 57.8882 95.6935C60.4315 96.8999 61.3962 101.474 60.7825 109.416C75.834 93.5762 73.4304 69.6162 55.4042 58.2551C54.3846 60.1229 53.4286 62.0111 52.3498 63.8247C49.173 69.1647 44.8572 73.0994 38.8351 74.9702C34.3992 76.3482 30.2971 75.8814 27.0434 72.4251C23.6946 68.8678 24.0177 64.6271 25.6583 60.3581C29.2123 51.1102 37.319 46.6195 47.0685 48.4533C48.2714 48.6795 49.5096 48.7149 51.1614 48.8822C52.1111 36.3589 49.6987 24.9638 42.2643 15.2091C30.8018 0.168879 14.9272 3.72427 4.37721 11.6965C3.07291 12.6821 1.95115 13.9135 1.66183e-06 15.7208C0.415293 11.5425 2.5421 9.4654 4.61421 7.46681C12.7967 -0.424933 26.6817 -2.31837 36.7267 3.02276C43.1553 6.441 47.963 11.4952 51.1404 18.0579C55.8143 27.712 58.0252 37.8684 56.8856 48.6514C56.7736 49.7119 56.7288 50.7795 56.6281 52.1922C64.5894 57.9884 71.613 64.6448 74.3685 74.7866C77.059 84.6894 73.2303 93.6543 70.3061 102.996ZM50.0348 55.1578C40.8202 50.9038 32.5625 53.8968 29.9971 62.2881C29.3922 64.267 29.621 67.124 30.6578 68.8471C32.1344 71.301 34.9845 70.9511 37.5423 69.9437C44.1741 67.3322 47.8501 62.2249 50.0348 55.1578Z" fill="#333333"/>
</svg>`;

                emptyContainer.appendChild(messageBox);
                emptyContainer.appendChild(arrow);

                this.nodeContent.appendChild(emptyContainer);
                this.initSimulatedOver(emptyContainer);
            }
        } else {
            if (emptyArticles) {
                emptyArticles.remove();
            }
        }
    }

    initSimulatedOver(target) {
        const state = { dragging: false, html5: false, raf: false };
        const check = (x, y) => {
            const r = target.getBoundingClientRect();
            const over =
                x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
            target.classList.toggle('is-over', over);
        };
        const schedule = (x, y) => {
            if (state.raf) return;
            state.raf = true;
            requestAnimationFrame(() => {
                state.raf = false;
                check(x, y);
            });
        };
        document.addEventListener(
            'dragstart',
            () => {
                state.dragging = true;
                state.html5 = true;
            },
            true
        );
        document.addEventListener(
            'dragend',
            () => {
                state.dragging = false;
                target.classList.remove('is-over');
            },
            true
        );
        document.addEventListener(
            'dragover',
            (e) => {
                if (!state.dragging || !state.html5) return;
                e.preventDefault();
                schedule(e.clientX, e.clientY);
            },
            { passive: false, capture: true }
        );
        const startSelectors = [
            '[draggable="true"]',
            '.draggable',
            '[drag]',
            '[data-drag]',
            '.idevice-element-in-content',
        ];
        document.addEventListener(
            'pointerdown',
            (e) => {
                if (e.button !== 0) return;
                if (e.target.closest(startSelectors.join(','))) {
                    state.dragging = true;
                    state.html5 = false;
                }
            },
            true
        );
        const end = () => {
            if (!state.dragging || state.html5) return;
            state.dragging = false;
            target.classList.remove('is-over');
        };
        document.addEventListener(
            'pointermove',
            (e) => {
                if (!state.dragging || state.html5) return;
                schedule(e.clientX, e.clientY);
            },
            true
        );
        document.addEventListener('pointerup', end, true);
        document.addEventListener('pointercancel', end, true);
    }

    /**
     * Set node selected
     *
     * @param {Node} element
     */
    setNodeSelected(element) {
        this.nodeSelected = element;
        this.nodeSelected.classList.add('selected');
        this.structureEngine.nodeSelected = this.nodeSelected;
        this.setNodeIdToNodeContentElement();
        this.createAddTextBtn();
        this.enabledActionButtons();
    }

    /**
     * Set attribute node id to node content
     *
     */
    setNodeIdToNodeContentElement() {
        document
            .querySelector('#node-content')
            .removeAttribute('node-selected');
        if (this.nodeSelected) {
            let node = this.structureEngine.getNode(
                this.nodeSelected.getAttribute('nav-id')
            );
            document
                .querySelector('#node-content')
                .setAttribute('node-selected', node.pageId);
        }
    }

    /**
     * Create a button to add a Text iDevice
     *
     */
    createAddTextBtn() {
        // Hide any visible tooltips
        $('body > .tooltip').hide();
        // Remove the button
        $('#eXeAddContentBtnWrapper').remove();
        if ($('#properties-node-content-form').is(':visible')) {
            return;
        }
        // Create the button in the right place
        let txt = _('Add Text');
        let bgImage = $('#list_menu_idevices #text .idevice_icon').css(
            'background-image'
        );

        // Click the button to add a Text iDevice
        $('#eXeAddContentBtnWrapper button')
            .off('click')
            .on('click', function (event) {
                if ($('#properties-node-content-form').is(':visible')) {
                    return;
                }
                $('#list_menu_idevices #text').trigger('click');
                $('#eXeAddContentBtnWrapper').remove();
            })
            .css('background-image', bgImage);
    }

    /**
     * Enable action buttons by node selected
     *
     */
    enabledActionButtons() {
        this.disableActionButtons();
        if (this.nodeSelected) {
            let node = this.structureEngine.getNode(
                this.nodeSelected.getAttribute('nav-id')
            );
            if (node.id == 'root') {
                // Enabled only "New node" button
                this.menuNav.querySelector(
                    '.button_nav_action.action_add'
                ).disabled = false;
            } else {
                // Enabled all buttons
                this.menuNav.querySelector(
                    '.button_nav_action.action_add'
                ).disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_properties'
                ).disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_delete'
                ).disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_clone'
                ).disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_import_idevices'
                ).disabled = false;
                //this.menuNav.querySelector(".button_nav_action.action_check_broken_links").disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_move_prev'
                ).disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_move_next'
                ).disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_move_up'
                ).disabled = false;
                this.menuNav.querySelector(
                    '.button_nav_action.action_move_down'
                ).disabled = false;
            }
        }
    }

    /**
     * Disable all action buttons
     *
     */
    disableActionButtons() {
        this.menuNav
            .querySelectorAll('#nav_actions .button_nav_action')
            .forEach((button) => {
                button.disabled = true;
            });
    }

    /*******************************************************************************
     * AUX
     *******************************************************************************/

    /**
     *
     */
    clearMenuNavDragOverClasses() {
        this.menuNav
            .querySelectorAll('.nav-element > .nav-element-text')
            .forEach((element) => {
                element.classList.remove('drag-over');
                element.classList.remove('idevice-content-over');
                element.classList.remove('block-content-over');
            });
    }

    /**
     *
     * @param {*} inputElement
     * @param {*} callback
     */
    addBehaviourToInputTextModal(inputElement, callback) {
        // Focus input title
        setTimeout(() => {
            this.focusTextInput(inputElement);
        }, 500);
    }

    /**
     * Focus element
     *
     * @param {*} input
     */
    focusTextInput(input) {
        input.focus();
        let inputElementValue = input.value;
        input.value = '';
        input.value = inputElementValue;
    }
}
