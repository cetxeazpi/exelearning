export default class ModalPublishToNetlify {
    constructor(modals) {
        this.modals = modals;
        this.app = modals.app;
        this.id = 'modalPublishToNetlify';
        this.modal = null;
        this.state = { connected: false, sites: [], selected: null };
    }

    init() {
        const el = document.getElementById(this.id);
        if (!el) return;
        this.bindElements(el);
    }

    behaviour() {
        this.bindEvents();
        const btn = document.getElementById('navbar-button-publish-netlify');
        if (btn) btn.addEventListener('click', () => this.show());
    }

    bindElements(root) {
        this.tokenInput = root.querySelector('#nl-token');
        this.saveTokenBtn = root.querySelector('#nl-save-token');
        this.authPanel = root.querySelector('#nl-auth');
        this.selectPanel = root.querySelector('#nl-select');

        this.btnSite = root.querySelector('#nl-btn-site');
        this.btnSiteText = root.querySelector('#nl-btn-site-text');
        this.siteMenu = root.querySelector('#nl-site-menu');
        this.siteList = root.querySelector('#nl-site-list');
        this.siteInput = root.querySelector('#nl-site-input');
        this.createInline = root.querySelector('#nl-create-inline');
        this.createNameSpan = root.querySelector('#nl-create-name');
        this.inputFeedback = root.querySelector('#nl-site-input-feedback');
        this.alert = root.querySelector('#nl-alert');

        this.btnRefresh = root.querySelector('#nl-btn-refresh');
        this.btnPublish = root.querySelector('#nl-btn-publish');
        this.btnVisitFooter = root.querySelector('#nl-btn-visit-footer');

        this.progress = root.querySelector('#nl-progress');
        this.progressBar = root.querySelector('#nl-progress-bar');
        this.progressMsg = root.querySelector('#nl-progress-msg');
        this.done = root.querySelector('#nl-done');
        this.doneText = root.querySelector('#nl-done-text');
        this.siteUrl = root.querySelector('#nl-site-url');
    }

    bindEvents() {
        if (this.saveTokenBtn)
            this.saveTokenBtn.addEventListener('click', async () => {
                const token = (this.tokenInput && this.tokenInput.value) || '';
                if (!token.trim()) return;
                try {
                    await this.app.api.postNetlifySaveToken({ access_token: token });
                    await this.checkAndLoad();
                } catch (e) {
                    this.showError((e && e.message) || _('Connect failed'));
                }
            });

        if (this.btnPublish) this.btnPublish.addEventListener('click', async () => { await this.publish(); });
        if (this.btnRefresh) this.btnRefresh.addEventListener('click', async () => { try { this.btnRefresh.disabled = true; await this.loadSites(); } finally { this.btnRefresh.disabled = false; } });
        if (this.createInline) this.createInline.addEventListener('click', async () => { await this.createFromInput(); });
        if (this.btnSite) this.btnSite.addEventListener('shown.bs.dropdown', () => {
            if (this.siteInput) {
                this.siteInput.value='';
                this.siteInput.classList.remove('is-invalid');
                this.renderList('');
                setTimeout(()=>this.siteInput.focus(),40);
            }
        });
        if (this.siteInput) {
            this.siteInput.addEventListener('input', (e) => { this.siteInput.classList.remove('is-invalid'); this.renderList(e.target.value || ''); });
            this.siteInput.addEventListener('keydown', async (e) => { if (e.key === 'Enter') { e.preventDefault(); await this.createFromInput(); } });
        }
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
        this.state = { connected: false, sites: [], selected: null };
        if (this.btnSiteText) { this.btnSiteText.textContent = _('Choose or create…'); this.btnSiteText.classList.add('text-muted'); }
        if (this.siteMenu) this.siteMenu.innerHTML = '';
        if (this.siteList) this.siteList.innerHTML = '';
        if (this.btnPublish) this.btnPublish.disabled = true;
        if (this.progress) this.progress.classList.add('d-none');
        if (this.done) this.done.classList.add('d-none');
        if (this.siteUrl) { this.siteUrl.href = '#'; this.siteUrl.classList.add('d-none'); }
        if (this.btnVisitFooter) { this.btnVisitFooter.href = '#'; this.btnVisitFooter.classList.add('d-none'); }
        if (this.doneText) this.doneText.textContent = '';
        if (this.alert) { this.alert.textContent = ''; this.alert.classList.add('d-none'); }
    }

    async checkAndLoad() {
        try {
            const st = await this.app.api.getNetlifyStatus();
            if (st && st.connected) {
                this.state.connected = true;
                if (this.authPanel) this.authPanel.classList.add('d-none');
                if (this.selectPanel) this.selectPanel.classList.remove('d-none');
                await this.loadSites();
                return true;
            }
        } catch (e) {}
        if (this.authPanel) this.authPanel.classList.remove('d-none');
        if (this.selectPanel) this.selectPanel.classList.add('d-none');
        return false;
    }

    async loadSites() {
        try {
            const sites = await this.app.api.getNetlifySites();
            this.state.sites = Array.isArray(sites) ? sites : [];
            this.renderList('');
        } catch (e) {
            this.showError((e && e.message) || _('Failed to load sites'));
        }
    }

    renderList(filter) {
        if (!this.siteList) return;
        const q = (filter || '').trim().toLowerCase();
        this.siteList.innerHTML = '';
        const matches = (this.state.sites || []).filter(s => (s.name || '').toLowerCase().includes(q) || (s.url || '').toLowerCase().includes(q));
        if (!matches.length) {
            const empty = document.createElement('div'); empty.className = 'list-group-item text-muted small'; empty.textContent = _('No matches.'); this.siteList.appendChild(empty);
        } else {
            matches.forEach((s) => {
                const row = document.createElement('button'); row.type='button'; row.className='list-group-item list-group-item-action d-flex justify-content-between align-items-start';
                const url = s.url || '';
                const visit = url ? `<a href="${url}" target="_blank" rel="noopener" class="text-decoration-none" title="${_('Visit site')}"><span class="auto-icon" aria-hidden="true">open_in_new</span></a>` : '';
                row.innerHTML = `<div class="me-2"><strong>${s.name || s.id}</strong>${url ? `<small class=\"text-muted ms-2\">${url}</small>` : ''}</div>${visit}`;
                row.addEventListener('click', () => { this.selectSite(s); const dd = bootstrap.Dropdown.getOrCreateInstance(this.btnSite); dd.hide(); });
                this.siteList.appendChild(row);
            });
        }
        if (this.createInline) {
            const exists = (this.state.sites || []).some(s => (s.name || s.id) === (filter || ''));
            if (filter && !exists) { this.createNameSpan.textContent = filter; this.createInline.classList.remove('d-none'); }
            else this.createInline.classList.add('d-none');
        }
    }

    selectSite(site) {
        this.state.selected = site;
        if (this.btnSiteText) { this.btnSiteText.textContent = site.name || site.id; this.btnSiteText.classList.remove('text-muted'); }
        if (this.btnPublish) this.btnPublish.disabled = false;
    }

    async createFromInput() {
        const val = (this.siteInput && this.siteInput.value || '').trim();
        if (!val) return;
        if (!/^[a-z0-9-]{3,63}$/.test(val)) { this.siteInput.classList.add('is-invalid'); if (this.inputFeedback) this.inputFeedback.textContent = _('Use 3–63 chars: lowercase letters, numbers, hyphens.'); return; }
        try {
            const res = await this.app.api.postNetlifyCreateSite({ name: val });
            const site = { id: res.id || res.name, name: res.name || val, url: res.url };
            this.state.sites = [site, ...(this.state.sites || []).filter(s => (s.name || s.id) !== (site.name || site.id))];
            this.renderList(this.siteInput.value);
            this.selectSite(site);
            const dd = bootstrap.Dropdown.getOrCreateInstance(this.btnSite); dd.hide();
        } catch (e) {
            this.siteInput.classList.add('is-invalid'); if (this.inputFeedback) this.inputFeedback.textContent = (e && e.responseJSON && e.responseJSON.error) || _('Site unavailable.');
        }
    }

    async publish() {
        const site = this.state.selected; if (!site) return;
        this.showError(null);
        if (this.btnPublish) this.btnPublish.disabled = true;
        if (this.progress) this.progress.classList.remove('d-none'); if (this.done) this.done.classList.add('d-none');
        const step = (pct, msg) => { if (this.progressBar) { this.progressBar.style.width = pct + '%'; this.progressBar.setAttribute('aria-valuenow', String(pct)); } if (this.progressMsg) this.progressMsg.textContent = msg; };
        try {
            step(15, _('Preparing…'));
            const res = await this.app.api.postNetlifyPublish({ siteId: site.id, odeSessionId: this.app.project.odeSession });
            step(100, _('Finalizing…'));
            if (this.progress) this.progress.classList.add('d-none'); if (this.done) this.done.classList.remove('d-none');
            const url = (res && res.siteUrl) || site.url; if (url && this.siteUrl) { this.siteUrl.href = url; this.siteUrl.classList.remove('d-none'); }
            if (this.doneText) this.doneText.textContent = `${site.name || site.id}`;
            if (this.btnVisitFooter) { this.btnVisitFooter.href = url; this.btnVisitFooter.classList.remove('d-none'); }
            if (this.btnPublish) this.btnPublish.classList.add('d-none');
        } catch (e) {
            const msg = (e && e.responseJSON && e.responseJSON.error) || (e && e.message) || _('Publish failed'); this.showError(msg); if (this.btnPublish) this.btnPublish.disabled = !this.state.selected;
        }
    }

    showError(message) { if (!this.alert) return; if (!message) { this.alert.textContent = ''; this.alert.classList.add('d-none'); return; } this.alert.textContent = message; this.alert.classList.remove('d-none'); }

    errorToast(e) { let body = (e && e.message) || _('Unknown error.'); this.app.toasts.createToast({ title: _('Error'), body, icon: 'error', error: true, remove: 5000 }); }

    showFromMenu() { this.show(); }
}
