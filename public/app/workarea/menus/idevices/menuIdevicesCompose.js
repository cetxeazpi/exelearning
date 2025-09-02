/**
 * eXeLearning
 *
 * Loading the idevices in the menu
 */

/**
 * MenuIdevicesCompose class
 *
 */
export default class MenuIdevicesCompose {
    constructor(parent, ideviceList) {
        this.parent = parent;
        this.idevicesList = ideviceList;
        this.idevicesInstalled = this.idevicesList.installed;
        this.menuIdevices = document.querySelector(
            '#menu_idevices #list_menu_idevices'
        );
        this.readers = [];
    }
    categoriesTitle = {
        information: _('Information and presentation'),
        evaluation: _('Evaluation and monitoring'),
        games: _('Games'),
        interactive: _('Interactive activities'),
        science: _('Science'),
        imported: _('Imported'),
    };

    categoriesFirst = [
        this.categoriesTitle.information,
        this.categoriesTitle.evaluation,
        this.categoriesTitle.games,
        this.categoriesTitle.interactive,
        this.categoriesTitle.science,
        this.categoriesTitle.imported,
    ];

    /**
     * Generate the HTML in the idevices menu
     *
     */
    compose() {
        // Clean menu
        this.categoriesExtra = [];
        this.categoriesIdevices = {};
        this.categoriesIcons = [];
        this.menuIdevices.innerHTML = '';
        // Set categories
        for (let [key, title] of Object.entries(this.categoriesTitle)) {
            this.categoriesIdevices[title] = [];
            this.categoriesIcons[title] = key;
        }
        this.addIdevicesToCategory();

        // Generate elements
        this.orderedCategories = this.categoriesFirst.concat(
            this.categoriesExtra
        );
        this.orderedCategories.forEach((category) => {
            if (
                this.categoriesIdevices[category]
                //&& this.categoriesIdevices[category].length > 0
            ) {
                this.createDivCategoryIdevices(
                    category,
                    this.categoriesIdevices[category],
                    this.categoriesIcons[category]
                );
            }
        });
    }

    /**
     * Add idevices to categories
     *
     * @return dict
     */
    addIdevicesToCategory() {
        for (let [key, idevice] of Object.entries(this.idevicesInstalled)) {
            // TODO commented only for develop -> if (idevice.visible) {
            if (!this.categoriesIdevices[idevice.category]) {
                this.categoriesIdevices[idevice.category] = [];
                this.categoriesExtra.push(idevice.category);
            }
            this.categoriesIdevices[idevice.category].push(idevice);
            // }
        }
    }

    /**
     * Create node parent category
     *
     * @param {*} categoryTitle
     * @param {*} idevices
     */
    createDivCategoryIdevices(categoryTitle, idevices, icon) {
        let nodeDivCategory = this.elementDivCategory(categoryTitle);
        nodeDivCategory.append(this.elementLabelCategory(categoryTitle, icon));
        nodeDivCategory.append(this.elementDivIdevicesParent(idevices, icon));
        this.menuIdevices.append(nodeDivCategory);
    }

    /**
     * Create idevices nodes
     *
     * @return {Node}
     */
    elementDivIdevicesParent(ideviceData, icon) {
        let nodeDivIdevices = document.createElement('div');
        nodeDivIdevices.classList.add('idevices', 'type_' + icon);
        const titleElement = document.createElement('div');
        titleElement.classList.add('idevices-category-title');
        const descriptionElement = document.createElement('p');
        descriptionElement.classList.add('idevices-category-description');
        switch (icon) {
            case 'information':
                titleElement.textContent = _('Information and presentation');
                descriptionElement.textContent = _(
                    'They allow displaying information, structuring resources or enriching the content with accessibility and diverse media.'
                );
                break;
            case 'evaluation':
                titleElement.textContent = _('Evaluation and monitoring');
                descriptionElement.textContent = _(
                    'These iDevices are designed to assess knowledge, understanding, skills, enable self-assessment, facilitate feedback, monitor progress and document performance levels.'
                );
                break;
            case 'games':
                titleElement.textContent = _('Games');
                descriptionElement.textContent = _(
                    'These resources encourage active student participation, play increases motivation, playful reinforcement, promotes the acquisition of skills and knowledge, memorisation and understanding of concepts.'
                );
                break;
            case 'interactive':
                titleElement.textContent = _('Interactive activities');
                descriptionElement.textContent = _(
                    'These iDevices promote active learning, guided discovery and autonomous exploration. They encourage critical thinking, problem solving and practical application of concepts.'
                );
                break;
            case 'science':
                titleElement.textContent = _('Sciencie');
                descriptionElement.textContent = _(
                    'These resources are geared towards teaching scientific concepts and skills through hands-on experience, observation, data analysis and conceptual explanation, providing contextualised learning.'
                );
                break;
            case 'imported':
                titleElement.textContent = _('Imported');
                descriptionElement.textContent = _(
                    'Select or drag to place the iDevice on the page.'
                );
                break;
            default:
                break;
        }
        nodeDivIdevices.append(titleElement);
        nodeDivIdevices.append(descriptionElement);

        if (icon !== 'imported') {
            ideviceData.forEach((ideviceData) => {
                nodeDivIdevices.append(this.elementDivIdevice(ideviceData));
            });
        }

        if (icon === 'imported') {
            const userIdevicesContent = document.createElement('div');
            userIdevicesContent.classList.add('useridevices-content');
            ideviceData.forEach((ideviceData) => {
                userIdevicesContent.append(
                    this.elementDivIdeviceImported(ideviceData)
                );
            });
            nodeDivIdevices.append(userIdevicesContent);
            nodeDivIdevices.append(this.createImportDeviceBox());
        }
        return nodeDivIdevices;
    }

    createImportDeviceBox() {
        const emptyBox = document.createElement('button');
        emptyBox.classList.add('idevice-import-upload', 'btn');

        const iconContainer = document.createElement('div');
        iconContainer.classList.add('upload-box-icon');

        const icon = document.createElement('span');
        icon.classList.add('medium-icon', 'upload-cloud-icon-green');
        iconContainer.appendChild(icon);

        const textContainer = document.createElement('div');
        textContainer.classList.add('upload-box-text');

        const pStrong = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = _('Click to upload the file');
        pStrong.appendChild(strong);

        textContainer.appendChild(pStrong);

        emptyBox.appendChild(iconContainer);
        emptyBox.appendChild(textContainer);

        const inputFile = this.makeElementInputFileImportIdevice();
        emptyBox.appendChild(inputFile);

        emptyBox.addEventListener('click', () => inputFile.click());

        emptyBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            emptyBox.classList.add('dragover');
        });

        emptyBox.addEventListener('dragleave', () => {
            emptyBox.classList.remove('dragover');
        });

        emptyBox.addEventListener('drop', (e) => {
            e.preventDefault();
            emptyBox.classList.remove('dragover');
            const files = e.dataTransfer.files;
            Array.from(files).forEach((file) => this.addNewReader(file));
        });

        return emptyBox;
    }

    makeElementInputFileImportIdevice() {
        let inputFile = document.createElement('input');
        inputFile.setAttribute('type', 'file');
        inputFile.setAttribute('accept', '.zip');
        inputFile.classList.add('hidden');
        inputFile.classList.add('idevice-file-import');
        inputFile.addEventListener('change', (event) => {
            Array.from(inputFile.files).forEach((idevice) => {
                this.addNewReader(idevice);
            });
            inputFile.value = null;
        });

        return inputFile;
    }

    addNewReader(file) {
        let reader = new FileReader();
        this.readers.push(reader);
        reader.onload = (event) => {
            this.uploadIdevice(file.name, event.target.result);
        };
        reader.readAsDataURL(file);
    }

    uploadIdevice(fileName, fileData) {
        let params = {};
        params.filename = fileName;
        params.file = fileData;
        eXeLearning.app.api.postUploadIdevice(params).then((response) => {
            if (response && response.responseMessage === 'OK') {
                response.idevice.id = response.idevice.name;
                this.idevicesList.loadIdevice(response.idevice);
                this.categoriesIdevices[_('Imported')].push(response.idevice);
                this.rebuildImportedIdevices();
            } else {
                // Show alert
                this.showElementAlert(
                    _('Failed to install the new iDevice'),
                    response
                );
            }
        });
    }

    removeIdevice(id) {
        let params = {};
        params.id = id;
        eXeLearning.app.api.deleteIdeviceInstalled(params).then((response) => {
            if (
                response &&
                response.responseMessage === 'OK' &&
                response.deleted &&
                response.deleted.name
            ) {
                this.idevicesList.removeIdevice(params.id);
                document.getElementById(params.id).remove();
                const idx = this.categoriesIdevices[_('Imported')].findIndex(
                    (obj) => obj.id === params.id
                );
                if (idx !== -1) {
                    this.categoriesIdevices[_('Imported')].splice(idx, 1);
                }
                this.rebuildImportedIdevices();
            } else {
                setTimeout(() => {
                    this.showElementAlert(
                        _('Could not remove the iDevice'),
                        response
                    );
                });
            }
        });
    }

    downloadIdeviceZip(idevice) {
        eXeLearning.app.api
            .getIdeviceInstalledZip(
                eXeLearning.app.project.odeSession,
                idevice.dirName
            )
            .then((response) => {
                if (response && response.zipFileName && response.zipBase64) {
                    let link = document.createElement('a');
                    link.setAttribute('type', 'hidden');
                    link.href = 'data:text/plain;base64,' + response.zipBase64;
                    link.download = response.zipFileName;
                    link.click();
                    link.remove();
                }
            });
    }

    rebuildImportedIdevices() {
        this.importedIdevicesContent = document.querySelector(
            '.idevices.type_imported .useridevices-content'
        );
        this.importedIdevicesContent.innerHTML = '';
        this.categoriesIdevices[_('Imported')].forEach((ideviceData) => {
            this.importedIdevicesContent.append(
                this.elementDivIdeviceImported(ideviceData)
            );
        });
        eXeLearning.app.project.idevices.behaviour();
    }

    showElementAlert(txt, response) {
        let defErrorText = txt;
        let resErrorText = response && response.error ? response.error : '';
        let errorText = resErrorText
            ? `<p>${defErrorText}:</p><p>&nbsp;${resErrorText}</p>`
            : `<p>${defErrorText}</p>`;
        // TODO show modal error.
        alert(resErrorText);
    }

    /**
     * Create element div category
     *
     * @return {Node}
     */
    elementDivCategory(categoryTitle) {
        let nodeDivCategory = document.createElement('div');
        nodeDivCategory.classList.add('idevice_category');
        nodeDivCategory.classList.add('off');

        return nodeDivCategory;
    }

    /**
     * Create element label category
     *
     * @param {*} categoryTitle
     *
     * @return {Node}
     */
    elementLabelCategory(categoryTitle, icon) {
        let categoryLabel = document.createElement('div');
        categoryLabel.classList.add('label');
        // Icon
        let iconContent = document.createElement('div');
        iconContent.classList.add('icon-content');
        switch (icon) {
            case 'information':
                iconContent.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
  <path d="M3 5.25C3 4.55109 3 4.20163 3.11418 3.92597C3.26642 3.55843 3.55843 3.26642 3.92597 3.11418C4.20163 3 4.55109 3 5.25 3H12.75C13.4489 3 13.7984 3 14.074 3.11418C14.4416 3.26642 14.7336 3.55843 14.8858 3.92597C15 4.20163 15 4.55109 15 5.25M6.75 15H11.25M9 3V15" stroke="#27a063" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`;
                break;
            case 'evaluation':
                iconContent.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
  <path d="M15.75 13.5012L14.9999 14.3217C14.6021 14.7567 14.0626 15.0012 13.5001 15.0012C12.9376 15.0012 12.3981 14.7567 12.0003 14.3217C11.6019 13.8875 11.0625 13.6437 10.5001 13.6437C9.93778 13.6437 9.39835 13.8875 8.99998 14.3217M2.25 15.0011H3.50591C3.87279 15.0011 4.05624 15.0011 4.22887 14.9597C4.38192 14.923 4.52824 14.8624 4.66245 14.7801C4.81382 14.6873 4.94354 14.5576 5.20296 14.2982L14.625 4.87615C15.2463 4.25483 15.2463 3.24747 14.625 2.62615C14.0037 2.00483 12.9963 2.00483 12.375 2.62615L2.95295 12.0482C2.69352 12.3076 2.5638 12.4373 2.47104 12.5887C2.3888 12.7229 2.32819 12.8692 2.29145 13.0223C2.25 13.1949 2.25 13.3784 2.25 13.7453V15.0011Z" stroke="#27a063" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`;
                break;
            case 'games':
                iconContent.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
  <path d="M4.50035 8.25H7.50035M6.00035 6.75V9.75M11.2503 9H11.2578M13.5003 7.5H13.5078M7.83706 3.75H10.1636C12.1323 3.75 13.1166 3.75 13.8893 4.12307C14.5695 4.45155 15.1347 4.97782 15.5108 5.63294C15.938 6.37702 16.0081 7.35885 16.1484 9.32252L16.3329 11.9059C16.4234 13.1726 15.4202 14.25 14.1503 14.25C13.5008 14.25 12.885 13.9615 12.4692 13.4626L12.1878 13.125C11.9305 12.8162 11.8018 12.6617 11.6553 12.537C11.3481 12.2754 10.9762 12.1012 10.5786 12.0327C10.389 12 10.1879 12 9.78592 12H8.21477C7.81275 12 7.61173 12 7.42207 12.0327C7.02447 12.1012 6.6526 12.2754 6.34543 12.537C6.1989 12.6617 6.07021 12.8162 5.81285 13.125L5.53147 13.4626C5.11573 13.9615 4.49986 14.25 3.85044 14.25C2.58052 14.25 1.57731 13.1726 1.66778 11.9059L1.85231 9.32252C1.99257 7.35885 2.0627 6.37702 2.48987 5.63294C2.86598 4.97782 3.43118 4.45155 4.11144 4.12307C4.88406 3.75 5.8684 3.75 7.83706 3.75Z" stroke="#27a063" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`;
                break;
            case 'interactive':
                iconContent.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
  <path d="M6.75 2.625V1.5M3.7955 3.7955L3 3M3.7955 9.75L3 10.5455M9.75 3.7955L10.5455 3M2.625 6.75H1.5M11.8984 12.1422L10.0296 15.6128C9.81606 16.0093 9.70931 16.2075 9.58088 16.2576C9.46942 16.3012 9.34389 16.2889 9.24298 16.2246C9.12671 16.1505 9.06043 15.9353 8.92786 15.5049L6.33389 7.08394C6.2256 6.73238 6.17145 6.5566 6.21502 6.43788C6.25297 6.33447 6.33447 6.25297 6.43788 6.21502C6.5566 6.17145 6.73238 6.2256 7.08394 6.3339L15.5049 8.92789C15.9353 9.06045 16.1505 9.12674 16.2245 9.24301C16.2888 9.34392 16.3011 9.46945 16.2576 9.58091C16.2075 9.70934 16.0092 9.81609 15.6127 10.0296L12.1422 11.8984C12.0833 11.9301 12.0538 11.9459 12.028 11.9663C12.0051 11.9844 11.9844 12.0051 11.9663 12.028C11.9459 12.0538 11.9301 12.0833 11.8984 12.1422Z" stroke="#27a063" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`;
                break;
            case 'science':
                iconContent.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
  <path d="M2.25 16.5H9M8.25 4.68903C8.72943 4.56563 9.23205 4.5 9.75 4.5C13.0637 4.5 15.75 7.18629 15.75 10.5C15.75 13.018 14.1989 15.1738 12 16.0639M4.125 9.75H7.125C7.47349 9.75 7.64774 9.75 7.79264 9.77882C8.38767 9.89718 8.85282 10.3623 8.97118 10.9574C9 11.1023 9 11.2765 9 11.625C9 11.9735 9 12.1477 8.97118 12.2926C8.85282 12.8877 8.38767 13.3528 7.79264 13.4712C7.64774 13.5 7.47349 13.5 7.125 13.5H4.125C3.77651 13.5 3.60226 13.5 3.45736 13.4712C2.86233 13.3528 2.39718 12.8877 2.27882 12.2926C2.25 12.1477 2.25 11.9735 2.25 11.625C2.25 11.2765 2.25 11.1023 2.27882 10.9574C2.39718 10.3623 2.86233 9.89718 3.45736 9.77882C3.60226 9.75 3.77651 9.75 4.125 9.75ZM3 4.125V9.75H8.25V4.125C8.25 2.67525 7.07475 1.5 5.625 1.5C4.17525 1.5 3 2.67525 3 4.125Z" stroke="#27a063" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`;
                break;
            case 'imported':
                iconContent.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
  <path d="M15.75 11.25V12.15C15.75 13.4101 15.75 14.0402 15.5048 14.5215C15.2891 14.9448 14.9448 15.2891 14.5215 15.5048C14.0402 15.75 13.4101 15.75 12.15 15.75H5.85C4.58988 15.75 3.95982 15.75 3.47852 15.5048C3.05516 15.2891 2.71095 14.9448 2.49524 14.5215C2.25 14.0402 2.25 13.4101 2.25 12.15V11.25M12.75 6L9 2.25M9 2.25L5.25 6M9 2.25V11.25" stroke="#27a063" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`;
                break;
            default:
                iconContent.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" fill="none">
  <rect width="20.4598" height="20.4598" transform="translate(0.540039)"/>
  <path d="M10.77 5.75391V10.2295M10.77 10.2295V14.7051M10.77 10.2295H6.29443M10.77 10.2295H15.2456" stroke="#27a063" stroke-width="1.5" stroke-linecap="round"/>
</svg>`;
                break;
        }
        // tittle
        let categorySpanTitle = document.createElement('h3');
        categorySpanTitle.classList.add('idevice_category_name');
        categorySpanTitle.innerHTML = categoryTitle;
        // add to label parent
        categoryLabel.append(iconContent);
        categoryLabel.append(categorySpanTitle);

        return categoryLabel;
    }

    /**
     * Create element idevice
     *
     * @param {*} ideviceData
     *
     * @return {Node}
     */
    elementDivIdevice(ideviceData) {
        let ideviceDiv = document.createElement('div');
        ideviceDiv.id = ideviceData.id;
        ideviceDiv.classList.add('idevice_item');
        ideviceDiv.classList.add('draggable');
        ideviceDiv.setAttribute('draggable', 'true');
        ideviceDiv.setAttribute('drag', 'idevice');
        ideviceDiv.setAttribute('title', ideviceData.title);
        ideviceDiv.setAttribute('icon-type', ideviceData.icon.type);
        ideviceDiv.setAttribute('icon-name', ideviceData.icon.name);
        ideviceDiv.append(this.elementDivIcon(ideviceData));
        ideviceDiv.append(this.elementDivTitle(ideviceData.title));

        return ideviceDiv;
    }

    elementDivIdeviceImported(ideviceData) {
        let ideviceDiv = document.createElement('div');
        ideviceDiv.id = ideviceData.id;
        ideviceDiv.classList.add('idevice_item', 'draggable');
        ideviceDiv.setAttribute('draggable', 'true');
        ideviceDiv.setAttribute('drag', 'idevice');
        ideviceDiv.setAttribute('title', ideviceData.title);
        ideviceDiv.setAttribute('icon-type', ideviceData.icon.type);
        ideviceDiv.setAttribute('icon-name', ideviceData.icon.name);

        ideviceDiv.append(this.elementDivIcon(ideviceData));
        ideviceDiv.append(this.elementDivTitle(ideviceData.title));

        const dropdownWrapper = document.createElement('div');
        dropdownWrapper.classList.add('dropdown');

        const btnAction = document.createElement('button');
        btnAction.classList.add(
            'btn',
            'button-tertiary',
            'btn-action-menu',
            'button-square',
            'd-flex',
            'justify-content-center',
            'align-items-center',
            'ideviceMenu',
            'exe-app-tooltip'
        );
        btnAction.setAttribute('type', 'button');
        btnAction.setAttribute('data-bs-toggle', 'dropdown');
        btnAction.setAttribute('aria-expanded', 'false');
        btnAction.innerHTML =
            '<span class="small-icon dots-menu-vertical-icon ideviceMenu"></span>';

        const dropdownMenu = document.createElement('ul');
        dropdownMenu.classList.add(
            'dropdown-menu',
            'ideviceMenu',
            'dropdown-menu-with-cols'
        );

        const liExport = document.createElement('li');
        const btnExport = document.createElement('button');
        btnExport.classList.add('dropdown-item', 'userIdeviceExport');
        btnExport.innerHTML =
            '<span class="small-icon download-icon-green"></span> ' +
            _('Export');
        liExport.appendChild(btnExport);
        btnExport.addEventListener('click', (e) => {
            e.preventDefault();
            this.downloadIdeviceZip(ideviceData);
        });

        const liDelete = document.createElement('li');
        const btnDelete = document.createElement('button');
        btnDelete.classList.add('dropdown-item', 'userIdeviceDelete');
        btnDelete.innerHTML =
            '<span class="small-icon delete-icon-red"></span> ' + _('Delete');
        liDelete.appendChild(btnDelete);
        btnDelete.addEventListener('click', (event) => {
            event.preventDefault();
            eXeLearning.app.modals.confirm.show({
                title: _('Delete iDevice'),
                body: _('Delete this iDevice: %s?').replace(
                    '%s',
                    ideviceDiv.id
                ),
                confirmButtonText: _('Delete'),
                cancelButtonText: _('Cancel'),
                confirmExec: () => {
                    this.removeIdevice(ideviceDiv.id);
                },
            });
        });

        dropdownMenu.append(liExport, liDelete);
        dropdownWrapper.append(btnAction, dropdownMenu);
        ideviceDiv.append(dropdownWrapper);

        return ideviceDiv;
    }

    /**
     *
     * @param {Array} ideviceData
     * @returns {Node}
     */
    elementDivIcon(ideviceData) {
        let ideviceIcon = document.createElement('div');
        ideviceIcon.classList.add('idevice_icon');
        if (ideviceData.icon.type === 'exe-icon') {
            ideviceIcon.innerHTML = ideviceData.icon.name;
        } else if (ideviceData.icon.type === 'img') {
            ideviceIcon.classList.add('idevice-img-icon');
            ideviceIcon.style.backgroundImage = `url(${ideviceData.path}/${ideviceData.icon.url})`;
            ideviceIcon.style.backgroundRepeat = 'no-repeat';
            ideviceIcon.style.backgroundPosition = 'center';
            ideviceIcon.style.backgroundSize = 'cover';
        }
        return ideviceIcon;
    }

    /**
     *
     * @param {String} title
     * @returns {Node}
     */
    elementDivTitle(title) {
        let ideviceTitle = document.createElement('div');
        ideviceTitle.classList.add('idevice_title');
        ideviceTitle.innerHTML = title;
        return ideviceTitle;
    }
}
