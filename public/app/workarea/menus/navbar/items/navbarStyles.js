export default class NavbarFile {
    constructor(menu) {
        this.menu = menu;
        this.button = this.menu.navbar.querySelector('#dropdownStyles');
        this.readers = [];
        this.paramsInfo = JSON.parse(
            JSON.stringify(eXeLearning.app.api.parameters.themeInfoFieldsConfig)
        );
        this.paramsEdit = JSON.parse(
            JSON.stringify(eXeLearning.app.api.parameters.themeEditionFieldsConfig)
        );
        this.updateThemes();
        document.querySelector('#exestylescontent-tab').addEventListener('click', () => {
            this.buildBaseListThemes();
        });
        document.querySelector('#importedstylescontent-tab').addEventListener('click', () => {
            this.buildUserListThemes();
        });
    }

    updateThemes() {
        this.themes = eXeLearning.app.themes.list.installed;
        this.userThemes = [];
        this.baseThemes = [];
        for (const key in this.themes) {
            const theme = this.themes[key];
            if (theme.type === 'user') {
                this.userThemes.push(theme);
            } else if (theme.type === 'base') {
                this.baseThemes.push(theme);
            }
        }
    }

    /**
     * Style Manager
     * Styles
     *
     */
    setStyleManagerEvent() {
        this.button.addEventListener('click', () => {
            if (eXeLearning.app.project.checkOpenIdevice()) return;
            this.styleManagerEvent();
        });
    }

    /**
     * Show Style Manager modal
     *
     */
    styleManagerEvent() {
        this.buildBaseListThemes();
        this.buildUserListThemes();

        this.toggleSidenav();
        document.getElementById('sidenav-overlay').addEventListener('click', this.toggleSidenav);
        document.getElementById('stylessidenavclose').addEventListener('click', this.toggleSidenav);
        //eXeLearning.app.modals.stylemanager.show(eXeLearning.app.themes.list);
    }

    buildBaseListThemes() {
        let contentBaseThemes = document.querySelector('#styleslistContent #exestylescontent');
        contentBaseThemes.innerHTML = '';
        for (const key in this.baseThemes) {
            const theme = this.baseThemes[key];
            const themeCard = document.createElement('div');
            themeCard.classList.add('theme-card');
            if (theme.manager.selected.name === theme.name) {
                themeCard.classList.add('selected');
            }
            const header = document.createElement('div');
            header.classList.add('theme-card-header');
            const title = document.createElement('h4');
            title.classList.add('theme-card-title');
            title.textContent = theme.title;
            const button = document.createElement('div');
            button.classList.add('theme-menu-btn');
            button.innerHTML = `<span class="exe-icon notranslate">more_vert</span>`;
            const menu = document.createElement('div');
            menu.classList.add('theme-menu', 'hidden');
            const ul = document.createElement('ul');
            const liDownload = document.createElement('li');
            liDownload.classList.add('disabled');
            const iconDownload = document.createElement('span');
            iconDownload.classList.add('menu-icon', 'exe-icon', 'notranslate');
            iconDownload.textContent = 'download';
            liDownload.appendChild(iconDownload);
            liDownload.appendChild(document.createTextNode(` ${_('Download')}`));
            const liInfo = document.createElement('li');
            liInfo.classList.add('theme-action-info');
            const iconInfo = document.createElement('span');
            iconInfo.classList.add('menu-icon', 'exe-icon', 'notranslate');
            iconInfo.textContent = 'info';
            liInfo.appendChild(iconInfo);
            liInfo.appendChild(document.createTextNode(` ${_('Properties')}`));
            liInfo.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                let leftBody = document.getElementById('exestylescontent');
                leftBody.innerHTML = '';
                leftBody.append(
                    this.makeElementInfoTheme(theme, 'base')
                );
            });

            ul.appendChild(liDownload);
            ul.appendChild(liInfo);

            menu.appendChild(ul);
            header.appendChild(title);
            header.appendChild(button);
            header.appendChild(menu);

            const preview = document.createElement('img');
            preview.classList.add('theme-card-preview');
            preview.src = `${theme.path}preview.png`;
            const description = document.createElement('p');
            description.classList.add('theme-description');
            description.innerHTML = theme.description;
            themeCard.appendChild(header);
            themeCard.appendChild(preview);
            themeCard.appendChild(description);
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.theme-menu').forEach(m => {
                    if (m !== menu) m.classList.add('hidden');
                });
                menu.classList.toggle('hidden');
            });

            contentBaseThemes.appendChild(themeCard);

            themeCard.addEventListener('click', () => {
                eXeLearning.app.themes.selectTheme(theme.id, true, false).then(() => {
                    document.querySelectorAll('.theme-card.selected').forEach(t => {
                        if (t !== menu) t.classList.remove('selected');
                    });
                    themeCard.classList.add('selected');
                });
            });
        }
        document.addEventListener('click', () => {
            document.querySelectorAll('.theme-menu').forEach(m => m.classList.add('hidden'));
        });
    }

    buildUserListThemes() {
        const contentUserThemes = document.querySelector('#styleslistContent #importedstylescontent');
        contentUserThemes.innerHTML = '';
        const infoText = document.createElement('div');
        infoText.classList.add('user-theme-empty-info');

        // No imported style
        if (Object.keys(this.userThemes).length === 0) {
            infoText.innerHTML = `
        <p class="empty-title">${_('There are no imported themes yet')}...</p>
        <p class="info-description">
            ${_('You can check the')} <a href="#" target="_blank">${_('guide')}</a> ${_('to create a style or add it from a')} <a href="#" target="_blank">${_('link')}</a>.
        </p>`;
            contentUserThemes.appendChild(infoText);
            contentUserThemes.appendChild(this.createEmptyBox());
            return;
        }

        infoText.innerHTML = `<p class="info-description">
            ${_('You can check the')} <a href="#">${_('guide')}</a> ${_('to create a style or add it from a')} <a href="#">${_('link')}</a>.
        </p>`;
        contentUserThemes.appendChild(infoText);

        for (const key in this.userThemes) {
            const theme = this.userThemes[key];

            const item = document.createElement('div');
            item.classList.add('user-theme-item');
            if (theme.manager.selected.name === theme.name) {
                item.classList.add('selected');
            }

            const icon = document.createElement('span');
            icon.classList.add('user-theme-icon', 'exe-icon', 'notranslate');
            icon.textContent = 'palette';

            const title = document.createElement('span');
            title.classList.add('user-theme-title');
            title.textContent = theme.name;

            const button = document.createElement('div');
            button.classList.add('user-theme-menu-btn');
            button.innerHTML = `<span class="exe-icon notranslate">more_vert</span>`;

            const menu = document.createElement('div');
            menu.classList.add('theme-menu', 'hidden');

            const ul = document.createElement('ul');

            ul.appendChild(this.makeMenuThemeEdit());
            ul.appendChild(this.makeMenuThemeDownload(theme));
            ul.appendChild(this.makeMenuThemeInfo(theme));
            ul.appendChild(this.makeMenuThemeDelete(theme.id));

            menu.appendChild(ul);

            button.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.theme-menu').forEach(m => {
                    if (m !== menu) m.classList.add('hidden');
                });
                menu.classList.toggle('hidden');
            });

            item.appendChild(icon);
            item.appendChild(title);
            item.appendChild(button);
            item.appendChild(menu);

            item.addEventListener('click', () => {
                eXeLearning.app.themes.selectTheme(theme.id, true, false).then(() => {
                    document.querySelectorAll('.user-theme-item.selected').forEach(t => {
                        if (t !== menu) t.classList.remove('selected');
                    });
                    item.classList.add('selected');
                });
            });

            contentUserThemes.appendChild(item);
        }

        contentUserThemes.appendChild(this.createEmptyBox());

        document.addEventListener('click', () => {
            document.querySelectorAll('.theme-menu').forEach(m => m.classList.add('hidden'));
        });
    }

    createEmptyBox() {
        const emptyBox = document.createElement('div');
        emptyBox.classList.add('user-theme-empty-upload');

        const iconContainer = document.createElement('div');
        iconContainer.classList.add('upload-box-icon');

        const icon = document.createElement('span');
        icon.classList.add('exe-icon', 'notranslate');
        icon.textContent = 'cloud_upload';
        iconContainer.appendChild(icon);

        const textContainer = document.createElement('div');
        textContainer.classList.add('upload-box-text');

        const pStrong = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = _('Click to upload the file');
        pStrong.appendChild(strong);

        const pMuted = document.createElement('p');
        pMuted.classList.add('text-muted');
        pMuted.textContent = _('or drag it here');

        textContainer.appendChild(pStrong);
        textContainer.appendChild(pMuted);

        emptyBox.appendChild(iconContainer);
        emptyBox.appendChild(textContainer);

        const inputFile = this.makeElementInputFileImportTheme();
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
            Array.from(files).forEach(file => this.addNewReader(file));
        });

        return emptyBox;
    }

    makeMenuThemeEdit() {
        const li = document.createElement('li');

        const icon = document.createElement('span');
        icon.classList.add('theme-action', 'theme-action-edit', 'menu-icon', 'exe-icon', 'notranslate');
        icon.textContent = 'edit';

        li.appendChild(icon);
        li.appendChild(document.createTextNode(` ${_('Edit')}`));
        li.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            // TODO add edition.
        })
        return li;
    }

    makeMenuThemeDownload(theme) {
        const li = document.createElement('li');

        const icon = document.createElement('span');
        icon.classList.add('theme-action', 'theme-action-export', 'menu-icon', 'exe-icon', 'notranslate');
        icon.textContent = 'download';

        li.appendChild(icon);
        li.appendChild(document.createTextNode(` ${_('Download')}`));

        li.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.downloadThemeZip(theme);
        })
        return li;
    }

    makeMenuThemeInfo(theme) {
        const li = document.createElement('li');

        const icon = document.createElement('span');
        icon.classList.add('theme-action', 'theme-action-info', 'menu-icon', 'exe-icon', 'notranslate');
        icon.textContent = 'info';

        li.appendChild(icon);
        li.appendChild(document.createTextNode(` ${_('Properties')}`));

        li.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            let leftBody = document.getElementById('importedstylescontent');
            leftBody.innerHTML = '';
            leftBody.append(
                this.makeElementInfoTheme(theme, 'users')
            );
        });

        return li;
    }

    makeMenuThemeDelete(themeId) {
        const li = document.createElement('li');

        const icon = document.createElement('span');
        icon.classList.add('theme-action', 'theme-action-remove', 'menu-icon', 'exe-icon', 'notranslate');
        icon.textContent = 'delete';

        li.appendChild(icon);
        li.appendChild(document.createTextNode(` ${_('Delete')}`));
        li.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            eXeLearning.app.modals.confirm.show({
                title: _('Delete theme'),
                body: _(`Are you sure to delete the theme: ${themeId}?`),
                confirmButtonText: _('Delete'),
                cancelButtonText: _('Cancel'),
                confirmExec: () => {
                    this.removeTheme(themeId);
                },
            });
        });
        return li;
    }

    async removeTheme(id) {
        let params = {};
        params.id = id;
        let response = await eXeLearning.app.api.deleteTheme(params);
        if (
            response &&
            response.responseMessage === 'OK' &&
            response.deleted &&
            response.deleted.name
        ) {
            await eXeLearning.app.themes.list.removeTheme(response.deleted.name);
            this.updateThemes();
            this.buildUserListThemes();
        } else {
            // Show modal
            setTimeout(() => {
                this.showElementAlert(_('Failed to remove theme'), response);
            }, 1000);
        }
    }

    makeElementInfoTheme(theme, tab) {
        const container = document.createElement('div');
        container.classList.add('info-theme-container');

        const closeBtn = document.createElement('div');
        closeBtn.classList.add('btn', 'btn-close');
        closeBtn.innerHTML = `<span class="exe-icon notranslate">close</span>`;
        closeBtn.addEventListener('click', () => {
            if (tab === 'base') {
                this.buildBaseListThemes();
            }
            if (tab === 'users') {
                this.buildUserListThemes();
            }
        });

        container.appendChild(closeBtn);

        for (let [param, config] of Object.entries(this.paramsInfo)) {
            if (theme[param]) {
                container.appendChild(
                    this.makeElementInfoThemeItem(param, theme[param], config)
                );
            }
        }

        return container;
    }

    showElementAlert(txt, response) {
        let defErrorText = txt;
        let resErrorText = response && response.error ? response.error : '';
        let errorText = resErrorText
            ? `<p>${defErrorText}:</p><p>&nbsp;${resErrorText}</p>`
            : `<p>${defErrorText}</p>`;
        // TODO show error.
        alert(resErrorText);
    }

    makeElementInputFileImportTheme() {
        let inputFile = document.createElement('input');
        inputFile.setAttribute('type', 'file');
        inputFile.setAttribute('accept', '.zip');
        inputFile.classList.add('hidden');
        inputFile.classList.add('theme-file-import');
        // Add event
        inputFile.addEventListener('change', (event) => {
            Array.from(inputFile.files).forEach((theme) => {
                this.addNewReader(theme);
            });
            inputFile.value = null;
        });

        return inputFile;
    }

    addNewReader(file) {
        let reader = new FileReader();
        this.readers.push(reader);
        reader.onload = (event) => {
            this.uploadTheme(file.name, event.target.result);
        };
        reader.readAsDataURL(file);
    }

    uploadTheme(fileName, fileData) {
        let params = {};
        params.filename = fileName;
        params.file = fileData;
        eXeLearning.app.api.postUploadTheme(params).then((response) => {
            if (response && response.responseMessage === 'OK') {
                eXeLearning.app.themes.list.loadTheme(response.theme);
                eXeLearning.app.themes.list.orderThemesInstalled();
                this.updateThemes();
                this.buildUserListThemes();
            } else {
                // Show alert
                this.showElementAlert(
                    _('Failed to install the new style'),
                    response
                );
            }
        });
    }

    downloadThemeZip(theme) {
        eXeLearning.app.api
            .getThemeZip(eXeLearning.app.project.odeSession, theme.dirName)
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


    toggleSidenav() {
        const sidenav = document.getElementById('stylessidenav');
        const overlay = document.getElementById('sidenav-overlay');
        const isOpen = sidenav.classList.contains('active');

        if (isOpen) {
            sidenav.classList.remove('active');
            overlay.classList.remove('active');
        } else {
            sidenav.classList.add('active');
            overlay.classList.add('active');
        }
    }

    makeElementInfoThemeItem(key, value, config) {
        const group = document.createElement('div');
        group.classList.add('mb-3');

        const label = document.createElement('label');
        label.classList.add('form-label', 'theme-info-key');
        label.textContent = config.title + ':';
        group.appendChild(label);

        switch (config.tag) {
            case 'text': {
                const input = document.createElement('input');
                input.type = 'text';
                input.classList.add('form-control', 'theme-info-value-text');
                input.disabled = true;
                input.value = value;
                group.appendChild(input);
                break;
            }
            case 'textarea': {
                const textarea = document.createElement('textarea');
                textarea.classList.add('form-control', 'theme-info-value-text');
                textarea.disabled = true;
                textarea.value = value;
                group.appendChild(textarea);
                break;
            }
        }

        return group;
    }
}
