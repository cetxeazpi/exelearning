export default class OdeTitleMenu {
    constructor() {
        this.odeTitleMenuHeadElement = document.querySelector(
            '#exe-title > .exe-title.content'
        );
        this.titleButton = document.querySelector('.title-menu-button');
        this.titleContainer = document.querySelector('#exe-title');
    }

    /**
     * Init element
     *
     */
    init() {
        this.setTitle();
        this.setChangeTitle();
        this.checkTitleLineCount();

        const resizeObserver = new ResizeObserver(() => {
            this.checkTitleLineCount();
        });
        resizeObserver.observe(this.odeTitleMenuHeadElement);
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
        //this.odeTitleMenuHeadElement.setAttribute('title', odeTitleText);
    }

    setChangeTitle() {
        const title = this.odeTitleMenuHeadElement;
        this.titleButton.addEventListener('click', (e) => {
            e.stopPropagation();
            title.click();
        });
        title.addEventListener('click', () => {
            if (eXeLearning.app.project.checkOpenIdevice()) return;
            title.setAttribute('contenteditable', 'true');
            this.titleContainer.classList.add('title-editing');
            this.titleContainer.classList.remove('title-not-editing');
            setTimeout(() => {
                this.placeCursorAtEnd(title);
            }, 0);
            const range = document.createRange();
            range.selectNodeContents(title);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            let finished = false;
            const finishEditing = () => {
                if (finished) return;
                finished = true;
                title.removeAttribute('contenteditable');
                title.scrollTop = 0;
                this.titleContainer.classList.remove('title-editing');
                this.titleContainer.classList.add('title-not-editing');
                this.saveTitle(title.textContent).then((response) => {
                    this.checkTitleLineCount();
                    title.removeEventListener('blur', finishEditing);
                    title.removeEventListener('keydown', onKeydown);
                    if (response.responseMessage === 'OK') {
                        let toastData = {
                            title: _('Project properties'),
                            body: _('Project properties saved.'),
                            icon: 'downloading',
                        };
                        let toast =
                            window.eXeLearning.app.toasts.createToast(
                                toastData
                            );
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
        eXeLearning.app.common.initTooltips(this.titleContainer);
    }

    async saveTitle(title) {
        const properties = await this.getProjectProperties();
        const output = Object.keys(properties).reduce((acc, key) => {
            acc[key] =
                typeof properties[key] === 'object' &&
                'value' in properties[key]
                    ? properties[key].value
                    : properties[key];
            return acc;
        }, {});
        output.pp_title = title;
        return eXeLearning.app.project.properties.apiSaveProperties(
            output,
            false
        );
    }

    checkTitleLineCount() {
        const title = this.odeTitleMenuHeadElement;
        const computedStyle = getComputedStyle(title);
        const titleContainer = document.querySelector('#exe-title');

        if (!title || !titleContainer) return;

        let lineHeight = parseFloat(computedStyle.lineHeight);

        if (isNaN(lineHeight)) {
            lineHeight = 19.2;
        }

        const height = title.offsetHeight;

        titleContainer.classList.remove('one-line', 'two-lines');

        if (height > 36) {
            titleContainer.classList.add('two-lines');
        } else {
            titleContainer.classList.add('one-line');
        }
    }

    placeCursorAtEnd(el) {
        el.focus();

        const selection = window.getSelection();
        const range = document.createRange();

        if (el.childNodes.length > 0) {
            const lastNode = el.childNodes[el.childNodes.length - 1];
            if (lastNode.nodeType === Node.TEXT_NODE) {
                range.setStart(lastNode, lastNode.length);
            } else {
                range.selectNodeContents(el);
                range.collapse(false);
            }
        } else {
            range.selectNodeContents(el);
            range.collapse(false);
        }

        selection.removeAllRanges();
        selection.addRange(range);

        el.scrollTop = el.scrollHeight;
    }

    async getProjectProperties() {
        return eXeLearning.app.project.properties.load().then(() => {
            return eXeLearning.app.project.properties.properties;
        });
    }
}
