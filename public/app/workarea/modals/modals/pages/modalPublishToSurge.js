export default class ModalPublishToSurge {
    constructor(modals) {
        this.modals = modals;
        this.app = modals.app;
        this.id = 'modalPublishToSurge';
        this.modal = null;
    }

    init() {
        const el = document.getElementById(this.id);
        if (!el) return;
        this.bindElements(el);
    }

    behaviour() {
        const btn = document.getElementById('navbar-button-publish-surge');
        if (btn) btn.addEventListener('click', () => this.show());
        this.bindEvents();
    }

    bindElements(root) {
        this.domainInput = root.querySelector('#surge-domain');
        this.exportBtn = root.querySelector('#surge-export');
        this.zipLink = root.querySelector('#surge-zip-link');
        this.instructions = root.querySelector('#surge-instructions');
        this.cmdEl = root.querySelector('#surge-cmd');
    }

    bindEvents() {
        if (this.exportBtn)
            this.exportBtn.addEventListener('click', async () => {
                await this.exportZip();
            });
    }

    async show() {
        const el = document.getElementById(this.id);
        if (!el) return;
        const bs = window.bootstrap || bootstrap;
        if (!this.modal) this.modal = new bs.Modal(el, {});
        this.reset();
        this.modal.show();
    }

    reset() {
        if (this.domainInput) this.domainInput.value = '';
        if (this.zipLink) {
            this.zipLink.href = '#';
            this.zipLink.classList.add('d-none');
        }
        if (this.instructions) this.instructions.classList.add('d-none');
        if (this.cmdEl) this.cmdEl.textContent = 'surge ./folder';
    }

    async exportZip() {
        try {
            const odeSessionId = this.app.project.odeSession;
            const res = await this.app.api.postSurgeExport({ odeSessionId });
            const url = res && res.zipUrl;
            const domain = (this.domainInput && this.domainInput.value) || '';
            if (url && this.zipLink) {
                this.zipLink.href = url;
                this.zipLink.classList.remove('d-none');
            }
            if (this.instructions) this.instructions.classList.remove('d-none');
            if (this.cmdEl)
                this.cmdEl.textContent =
                    `surge ./unzipped-folder ${domain || ''}`.trim();
        } catch (e) {
            this.errorToast(e);
        }
    }

    errorToast(e) {
        let body = (e && e.message) || _('Unknown error.');
        this.app.toasts.createToast({
            title: _('Error'),
            body,
            icon: 'error',
            error: true,
            remove: 5000,
        });
    }
}
