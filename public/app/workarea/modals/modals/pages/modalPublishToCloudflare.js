export default class ModalPublishToCloudflare {
    constructor(modals) {
        this.modals = modals;
        this.app = modals.app;
        this.id = 'modalPublishToCloudflare';
        this.modal = null;
        this.state = {
            connected: false,
            accounts: [],
            account: null,
            projects: [],
            project: null,
        };
        this.justCreated = false;
    }

    init() {
        const el = document.getElementById(this.id);
        if (!el) return;
        this.bindElements(el);
    }

    behaviour() {
        const btn = document.getElementById('navbar-button-publish-cfpages');
        if (btn) btn.addEventListener('click', () => this.show());
        this.bindEvents();
    }

    bindElements(root) {
        this.tokenInput = root.querySelector('#cf-token');
        this.saveTokenBtn = root.querySelector('#cf-save-token');
        this.authPanel = root.querySelector('#cf-auth');
        this.selectPanel = root.querySelector('#cf-select');
        this.btnAccount = root.querySelector('#cf-btn-account');
        this.btnAccountText = root.querySelector('#cf-btn-account-text');
        this.accountMenu = root.querySelector('#cf-account-menu');
        this.btnProject = root.querySelector('#cf-btn-project');
        this.btnProjectText = root.querySelector('#cf-btn-project-text');
        this.projectMenu = root.querySelector('#cf-project-menu');
        this.btnRefresh = root.querySelector('#cf-btn-refresh');
        this.nameInput = root.querySelector('#cf-project-name');
        this.btnCreate = root.querySelector('#cf-btn-create');
        this.chkProduction = root.querySelector('#cf-deploy-production');
        this.progress = root.querySelector('#cf-progress');
        this.progressBar = root.querySelector('#cf-progress-bar');
        this.progressMsg = root.querySelector('#cf-progress-msg');
        this.done = root.querySelector('#cf-done');
        this.doneText = root.querySelector('#cf-done-text');
        this.siteUrl = root.querySelector('#cf-site-url');
        this.btnPublish = root.querySelector('#cf-btn-publish');
    }

    bindEvents() {
        if (this.saveTokenBtn)
            this.saveTokenBtn.addEventListener('click', async () => {
                const token = (this.tokenInput && this.tokenInput.value) || '';
                if (!token.trim()) return;
                await this.app.api.postCfSaveToken({ access_token: token });
                await this.checkAndLoad();
            });
        if (this.btnPublish)
            this.btnPublish.addEventListener('click', async () => {
                await this.publish();
            });
        if (this.btnCreate)
            this.btnCreate.addEventListener('click', async () => {
                await this.createProject();
            });
        if (this.btnRefresh)
            this.btnRefresh.addEventListener('click', async () => {
                await this.loadProjects();
            });
    }

    async show() {
        const el = document.getElementById(this.id);
        if (!el) return;
        const bs = window.bootstrap || bootstrap;
        if (!this.modal) this.modal = new bs.Modal(el, {});
        this.reset();
        this.modal.show();
        await this.checkAndLoad();
    }

    reset() {
        this.state = {
            connected: false,
            accounts: [],
            account: null,
            projects: [],
            project: null,
        };
        this.justCreated = false;
        if (this.btnAccountText)
            this.btnAccountText.textContent = _('Select account…');
        if (this.btnProjectText)
            this.btnProjectText.textContent = _('Select project…');
        if (this.projectMenu) this.projectMenu.innerHTML = '';
        if (this.accountMenu) this.accountMenu.innerHTML = '';
        if (this.btnPublish) this.btnPublish.disabled = true;
        if (this.progress) this.progress.classList.add('d-none');
        if (this.done) this.done.classList.add('d-none');
        if (this.siteUrl) {
            this.siteUrl.href = '#';
            this.siteUrl.classList.add('d-none');
        }
        if (this.doneText) this.doneText.textContent = '';
    }

    async checkAndLoad() {
        const st = await this.app.api.getCfStatus();
        if (st && st.connected) {
            this.state.connected = true;
            if (this.authPanel) this.authPanel.classList.add('d-none');
            if (this.selectPanel) this.selectPanel.classList.remove('d-none');
            await this.loadAccounts();
            return true;
        }
        if (this.authPanel) this.authPanel.classList.remove('d-none');
        if (this.selectPanel) this.selectPanel.classList.add('d-none');
        return false;
    }

    async loadAccounts() {
        const list = await this.app.api.getCfAccounts();
        this.state.accounts = Array.isArray(list) ? list : [];
        this.renderAccountMenu();
    }

    renderAccountMenu() {
        if (!this.accountMenu) return;
        this.accountMenu.innerHTML = '';
        (this.state.accounts || []).forEach((a) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action text-start';
            btn.innerHTML = `<strong>${a.name || a.id}</strong>`;
            btn.addEventListener('click', () => this.selectAccount(a));
            this.accountMenu.appendChild(btn);
        });
    }

    async selectAccount(acc) {
        this.state.account = acc;
        if (this.btnAccountText)
            this.btnAccountText.textContent = acc.name || acc.id;
        if (this.btnProject) this.btnProject.removeAttribute('disabled');
        await this.loadProjects();
    }

    async loadProjects() {
        const acc = this.state.account;
        if (!acc) return;
        const projs = await this.app.api.getCfProjects(acc.id);
        this.state.projects = Array.isArray(projs) ? projs : [];
        this.renderProjectMenu();
    }

    renderProjectMenu() {
        if (!this.projectMenu) return;
        this.projectMenu.innerHTML = '';
        (this.state.projects || []).forEach((p) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action text-start';
            const sub = p.subdomain
                ? `<small class="text-muted ms-2">${p.subdomain}.pages.dev</small>`
                : '';
            btn.innerHTML = `<strong>${p.name}</strong>${sub}`;
            btn.addEventListener('click', () => this.selectProject(p));
            this.projectMenu.appendChild(btn);
        });
    }

    selectProject(p) {
        this.state.project = p;
        if (this.btnProjectText) this.btnProjectText.textContent = p.name;
        if (this.btnPublish) this.btnPublish.disabled = false;
    }

    async publish() {
        const acc = this.state.account;
        const proj = this.state.project;
        if (!acc || !proj) return;
        if (!this.justCreated) {
            const ok = window.confirm(
                _(
                    'This will overwrite the current live version of the selected project. Continue?'
                )
            );
            if (!ok) return;
        }
        if (this.progress) this.progress.classList.remove('d-none');
        if (this.done) this.done.classList.add('d-none');
        if (this.progressBar) this.progressBar.style.width = '10%';
        if (this.progressMsg) this.progressMsg.textContent = _('Preparing…');
        const res = await this.app.api.postCfPublish({
            account: acc.id,
            project: proj.name,
            odeSessionId: this.app.project.odeSession,
            production: this.chkProduction
                ? !!this.chkProduction.checked
                : true,
            branch: proj.production_branch || 'main',
        });
        if (this.progressBar) this.progressBar.style.width = '100%';
        if (this.progress) this.progress.classList.add('d-none');
        if (this.done) this.done.classList.remove('d-none');
        const url = res && res.url;
        if (url && this.siteUrl) {
            this.siteUrl.href = url;
            this.siteUrl.classList.remove('d-none');
        }
        if (this.doneText)
            this.doneText.textContent = `${acc.name || acc.id} / ${proj.name}`;
    }

    async createProject() {
        const acc = this.state.account;
        if (!acc) return;
        const name = ((this.nameInput && this.nameInput.value) || '').trim();
        if (!name) return;
        try {
            this.btnCreate && (this.btnCreate.disabled = true);
            await this.app.api.postCfCreateProject({ account: acc.id, name });
            await this.loadProjects();
            const created = (this.state.projects || []).find(
                (p) => p && p.name === name
            );
            if (created) {
                this.justCreated = true;
                this.selectProject(created);
            }
        } finally {
            this.btnCreate && (this.btnCreate.disabled = false);
        }
    }
}
