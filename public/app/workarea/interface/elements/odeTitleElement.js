export default class OdeTitleMenu {
    constructor() {
        this.odeTitleMenuHeadElement = document.querySelector(
            '#exe-title > .exe-title.content'
        );
        this.changeTitle = document.querySelector('#change_title');
    }

    /**
     * Init element
     *
     */
    init() {
        this.setTitle();
        this.setChangeTitle();
    }

    /**
     * Set title text to menu element
     *
     */
    setTitle() {
        let odeTitleProperty =
            eXeLearning.app.project.properties.properties.pp_title;
        let odeTitleText = odeTitleProperty.value
            ? odeTitleProperty.value
            : _('Untitled document');
        this.odeTitleMenuHeadElement.textContent = odeTitleText;
        this.odeTitleMenuHeadElement.setAttribute('title', odeTitleText);
    }

    setChangeTitle() {
        const title = this.odeTitleMenuHeadElement;
        const changeTitle = this.changeTitle;
        changeTitle.addEventListener('click', () => {
            if (eXeLearning.app.project.checkOpenIdevice()) return;
            title.setAttribute('contenteditable', 'true');
            title.focus();
            const range = document.createRange();
            range.selectNodeContents(title);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            changeTitle.style.display = 'none';
            let finished = false;
            const finishEditing = () => {
                if (finished) return;
                finished = true;
                title.removeAttribute('contenteditable');
                changeTitle.style.display = '';
                title.scrollTop = 0;
                this.saveTitle(title.textContent).then((response) => {
                    title.removeEventListener('blur', finishEditing);
                    title.removeEventListener('keydown', onKeydown);
                    if (response.responseMessage === 'OK') {
                        let toastData = {
                            title: _('Project properties'),
                            body: _('Project properties saved.'),
                            icon: 'downloading',
                        };
                        let toast =
                            window.eXeLearning.app.toasts.createToast(toastData);
                        setTimeout(() => {
                            toast.remove();
                        }, 1000);
                    }
                });
            };
            const onKeydown = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    finishEditing();
                }
            };
            title.addEventListener('blur', finishEditing);
            title.addEventListener('keydown', onKeydown);
        });
    }

    async saveTitle(title) {
        const properties = await this.getProjectProperties();
        const output = Object.keys(properties).reduce((acc, key) => {
            acc[key] = (typeof properties[key] === 'object' && 'value' in properties[key])
                ? properties[key].value
                : properties[key];
            return acc;
        }, {});
        output.pp_title = title;
        return eXeLearning.app.project.properties.apiSaveProperties(output, false);
    }


    async getProjectProperties() {
        return eXeLearning.app.project.properties.load().then(() => {
            return eXeLearning.app.project.properties.properties;
        });
    }
}
