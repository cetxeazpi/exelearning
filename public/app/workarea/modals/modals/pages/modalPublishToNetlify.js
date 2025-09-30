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
        this.root = root;
        this.tokenInput = root.querySelector('#nl-token');
        this.saveTokenBtn = root.querySelector('#nl-save-token');
        this.authPanel = root.querySelector('#nl-auth');
        this.selectPanel = root.querySelector('#nl-select');

        this.btnSite = root.querySelector('#nl-btn-site');
        this.btnSiteText = root.querySelector('#nl-btn-site-text');
        this.siteMenu = root.querySelector('#nl-site-menu');
        this.siteList = root.querySelector('#nl-site-list');
        this.siteInput = root.querySelector('#nl-site-input');
        // Inline "create" UI removed; creation handled on Publish if needed
        this.createInline = null;
        this.createNameSpan = null;
        this.inputFeedback = root.querySelector('#nl-site-input-feedback');
        this.alert = root.querySelector('#nl-alert');

        this.btnRefresh = root.querySelector('#nl-btn-refresh');
        this.btnPublish = root.querySelector('#nl-btn-publish');
        this.btnVisitFooter = root.querySelector('#nl-btn-visit-footer');
        this.overwriteWrap = root.querySelector('#nl-overwrite-wrap');
        this.overwriteCheckbox = root.querySelector('#nl-overwrite');

        // If missing, create footer buttons dynamically
        if (!this.btnPublish || !this.btnVisitFooter) {
            const footer = root.querySelector('.modal-footer');
            if (footer) {
                const holder = document.createElement('div');
                holder.className = 'd-flex gap-2 align-items-center';
                if (!this.btnVisitFooter) {
                    const a = document.createElement('a');
                    a.id = 'nl-btn-visit-footer';
                    a.href = '#';
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.className = 'btn btn-primary d-none';
                    a.textContent = _('Visit site');
                    holder.appendChild(a);
                    this.btnVisitFooter = a;
                }
                if (!this.btnPublish) {
                    const b = document.createElement('button');
                    b.id = 'nl-btn-publish';
                    b.type = 'button';
                    b.className = 'btn btn-primary';
                    b.textContent = _('Publish');
                    holder.appendChild(b);
                    this.btnPublish = b;
                }
                footer.appendChild(holder);
            }
        }

        // If missing, create overwrite checkbox dynamically
        if (!this.overwriteWrap) {
            const siteBlock = root.querySelector('#nl-select .mb-2');
            if (siteBlock) {
                const wrap = document.createElement('div');
                wrap.id = 'nl-overwrite-wrap';
                wrap.className = 'form-check mt-2 d-none';
                const input = document.createElement('input');
                input.className = 'form-check-input';
                input.type = 'checkbox';
                input.id = 'nl-overwrite';
                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.setAttribute('for', 'nl-overwrite');
                label.textContent = _('Overwrite existing content');
                wrap.appendChild(input);
                wrap.appendChild(label);
                siteBlock.appendChild(wrap);
                this.overwriteWrap = wrap;
                this.overwriteCheckbox = input;
            }
        }

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
                    await this.app.api.postNetlifySaveToken({
                        access_token: token,
                    });
                    await this.checkAndLoad();
                } catch (e) {
                    this.showError((e && e.message) || _('Connect failed'));
                }
            });

        if (this.btnPublish)
            this.btnPublish.addEventListener('click', async () => {
                await this.publish();
            });
        if (this.overwriteCheckbox)
            this.overwriteCheckbox.addEventListener('change', () =>
                this.updatePublishEnabled()
            );
        if (this.btnRefresh)
            this.btnRefresh.addEventListener('click', async () => {
                try {
                    this.btnRefresh.disabled = true;
                    await this.loadSites();
                } finally {
                    this.btnRefresh.disabled = false;
                }
            });
        // No inline create; we will use typed name on Enter
        if (this.btnSite)
            this.btnSite.addEventListener('shown.bs.dropdown', () => {
                if (this.siteInput) {
                    this.siteInput.value = '';
                    this.siteInput.classList.remove('is-invalid');
                    this.renderList('');
                    setTimeout(() => this.siteInput.focus(), 40);
                }
            });
        if (this.siteInput) {
            this.siteInput.addEventListener('input', (e) => {
                this.siteInput.classList.remove('is-invalid');
                this.renderList(e.target.value || '');
            });
            this.siteInput.addEventListener('keydown', async (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.selectTypedName();
                    const dd = bootstrap.Dropdown.getOrCreateInstance(
                        this.btnSite
                    );
                    dd.hide();
                }
            });
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
        if (this.btnSiteText) {
            this.btnSiteText.textContent = _('Choose or create…');
            this.btnSiteText.classList.add('text-muted');
        }
        // Do not wipe siteMenu structure; only clear the list items
        if (this.siteList) this.siteList.innerHTML = '';
        if (this.overwriteWrap) this.overwriteWrap.classList.add('d-none');
        if (this.overwriteCheckbox) this.overwriteCheckbox.checked = false;
        if (this.btnPublish) this.btnPublish.disabled = true;
        if (this.progress) this.progress.classList.add('d-none');
        if (this.done) this.done.classList.add('d-none');
        if (this.siteUrl) {
            this.siteUrl.href = '#';
            this.siteUrl.classList.add('d-none');
        }
        if (this.btnVisitFooter) {
            this.btnVisitFooter.href = '#';
            this.btnVisitFooter.classList.add('d-none');
        }
        if (this.doneText) this.doneText.textContent = '';
        if (this.alert) {
            this.alert.textContent = '';
            this.alert.classList.add('d-none');
        }
    }

    async checkAndLoad() {
        try {
            const st = await this.app.api.getNetlifyStatus();
            if (st && st.connected) {
                this.state.connected = true;
                if (this.authPanel) this.authPanel.classList.add('d-none');
                if (this.selectPanel)
                    this.selectPanel.classList.remove('d-none');
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
            const status = (e && (e.status || e.statusCode)) || 0;
            const msg =
                (e && e.responseJSON && e.responseJSON.error) ||
                (e && e.message) ||
                _('Failed to load sites');
            if (
                status === 401 ||
                (e && e.responseJSON && e.responseJSON.reauth)
            ) {
                // Token inválido/expirado: volver a pedirlo
                if (this.authPanel) this.authPanel.classList.remove('d-none');
                if (this.selectPanel) this.selectPanel.classList.add('d-none');
                this.state.connected = false;
                this.showError(
                    _('Invalid or expired Netlify token. Please connect again.')
                );
                return;
            }
            this.showError(msg);
        }
    }

    renderList(filter) {
        if (!this.siteList) {
            // Try to re-bind if structure was recreated or previously cleared
            if (this.root)
                this.siteList = this.root.querySelector('#nl-site-list');
            if (!this.siteList && this.siteMenu) {
                const list = document.createElement('div');
                list.id = 'nl-site-list';
                list.className = 'list-group list-group-flush';
                this.siteMenu.appendChild(list);
                this.siteList = list;
            }
        }
        if (!this.siteList) return;
        const q = (filter || '').trim().toLowerCase();
        this.siteList.innerHTML = '';
        const matches = (this.state.sites || []).filter(
            (s) =>
                (s.name || '').toLowerCase().includes(q) ||
                (s.url || '').toLowerCase().includes(q)
        );
        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-muted small';
            empty.textContent = _('No matches.');
            this.siteList.appendChild(empty);
        } else {
            matches.forEach((s) => {
                const row = document.createElement('button');
                row.type = 'button';
                row.className =
                    'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
                const url = s.url || '';
                const visit = url
                    ? `<a href="${url}" target="_blank" rel="noopener" class="text-decoration-none" title="${_('Visit site')}"><span class="auto-icon" aria-hidden="true">open_in_new</span></a>`
                    : '';
                row.innerHTML = `<div class="me-2"><strong>${s.name || s.id}</strong>${url ? `<small class=\"text-muted ms-2\">${url}</small>` : ''}</div>${visit}`;
                row.addEventListener('click', () => {
                    this.selectSite(s);
                    const dd = bootstrap.Dropdown.getOrCreateInstance(
                        this.btnSite
                    );
                    dd.hide();
                });
                this.siteList.appendChild(row);
            });
        }
        // Inline create disabled; creation happens during Publish if needed
    }

    selectSite(site) {
        this.state.selected = site;
        if (this.btnSiteText) {
            this.btnSiteText.textContent = site.name || site.id;
            this.btnSiteText.classList.remove('text-muted');
        }
        // Existing site requires overwrite consent
        if (site && site.id) {
            if (this.overwriteWrap)
                this.overwriteWrap.classList.remove('d-none');
            if (this.overwriteCheckbox) this.overwriteCheckbox.checked = false;
        } else {
            if (this.overwriteWrap) this.overwriteWrap.classList.add('d-none');
            if (this.overwriteCheckbox) this.overwriteCheckbox.checked = false;
        }
        this.updatePublishEnabled();
    }

    selectTypedName() {
        const val = ((this.siteInput && this.siteInput.value) || '').trim();
        if (!val) return;
        if (!/^[a-z0-9-]{3,63}$/.test(val)) {
            this.siteInput.classList.add('is-invalid');
            if (this.inputFeedback)
                this.inputFeedback.textContent = _(
                    'Use 3–63 chars: lowercase letters, numbers, hyphens.'
                );
            return;
        }
        const existing = (this.state.sites || []).find(
            (s) => (s.name || s.id) === val
        );
        if (existing) this.selectSite(existing);
        else {
            this.state.selected = {
                id: null,
                name: val,
                url: null,
                isNew: true,
            };
            if (this.btnSiteText) {
                this.btnSiteText.textContent = val;
                this.btnSiteText.classList.remove('text-muted');
            }
            if (this.overwriteWrap) this.overwriteWrap.classList.add('d-none');
            this.updatePublishEnabled();
        }
    }

    updatePublishEnabled() {
        const sel = this.state.selected;
        if (!this.btnPublish) return;
        if (!sel) {
            this.btnPublish.disabled = true;
            return;
        }
        const isExisting = !!sel.id;
        if (isExisting) {
            const consent = !!(
                this.overwriteCheckbox && this.overwriteCheckbox.checked
            );
            this.btnPublish.disabled = !consent;
        } else {
            this.btnPublish.disabled = false;
        }
    }

    async publish() {
        const site = this.state.selected;
        if (!site) return;
        this.showError(null);
        if (this.btnPublish) this.btnPublish.disabled = true;
        if (this.progress) this.progress.classList.remove('d-none');
        if (this.done) this.done.classList.add('d-none');
        const step = (pct, msg) => {
            if (this.progressBar) {
                this.progressBar.style.width = pct + '%';
                this.progressBar.setAttribute('aria-valuenow', String(pct));
            }
            if (this.progressMsg) this.progressMsg.textContent = msg;
        };
        try {
            step(15, _('Preparing…'));
            let siteId = site.id;
            // If user typed a new site name, create it now
            if (!siteId && site.name) {
                try {
                    const created = await this.app.api.postNetlifyCreateSite({
                        name: site.name,
                    });
                    siteId = created.id || created.name;
                    const newSite = {
                        id: siteId,
                        name: created.name || site.name,
                        url: created.url,
                    };
                    this.state.sites = [
                        newSite,
                        ...(this.state.sites || []).filter(
                            (s) =>
                                (s.name || s.id) !==
                                (newSite.name || newSite.id)
                        ),
                    ];
                    this.state.selected = newSite;
                } catch (e) {
                    const status = (e && (e.status || e.statusCode)) || 0;
                    const err =
                        (e && e.responseJSON && e.responseJSON.error) ||
                        (e && e.message) ||
                        _('Site unavailable.');
                    if (
                        status === 401 ||
                        (e && e.responseJSON && e.responseJSON.reauth)
                    ) {
                        if (this.progress)
                            this.progress.classList.add('d-none');
                        if (this.authPanel)
                            this.authPanel.classList.remove('d-none');
                        if (this.selectPanel)
                            this.selectPanel.classList.add('d-none');
                        this.state.connected = false;
                        this.showError(
                            _(
                                'Invalid or expired Netlify token. Please connect again.'
                            )
                        );
                        return;
                    }
                    if (
                        String(err).toLowerCase().includes('must be unique') ||
                        String(err).toLowerCase().includes('taken')
                    ) {
                        if (this.progress)
                            this.progress.classList.add('d-none');
                        this.showError(
                            _(
                                'Site name not available. Pick another or choose an existing site.'
                            )
                        );
                        if (this.siteInput)
                            this.siteInput.classList.add('is-invalid');
                        const dd = bootstrap.Dropdown.getOrCreateInstance(
                            this.btnSite
                        );
                        dd.show();
                        return;
                    }
                    throw e;
                }
            }
            const res = await this.app.api.postNetlifyPublish({
                siteId: siteId,
                odeSessionId: this.app.project.odeSession,
            });
            step(100, _('Finalizing…'));
            if (this.progress) this.progress.classList.add('d-none');
            if (this.done) this.done.classList.remove('d-none');
            const url = (res && res.siteUrl) || site.url;
            if (url && this.siteUrl) {
                this.siteUrl.href = url;
                this.siteUrl.classList.remove('d-none');
            }
            if (this.doneText)
                this.doneText.textContent = `${site.name || site.id}`;
            if (this.btnVisitFooter) {
                this.btnVisitFooter.href = url;
                this.btnVisitFooter.classList.remove('d-none');
            }
            if (this.btnPublish) this.btnPublish.classList.add('d-none');
        } catch (e) {
            const status = (e && (e.status || e.statusCode)) || 0;
            if (
                status === 401 ||
                (e && e.responseJSON && e.responseJSON.reauth)
            ) {
                if (this.progress) this.progress.classList.add('d-none');
                if (this.authPanel) this.authPanel.classList.remove('d-none');
                if (this.selectPanel) this.selectPanel.classList.add('d-none');
                this.state.connected = false;
                this.showError(
                    _('Invalid or expired Netlify token. Please connect again.')
                );
            } else {
                const msg =
                    (e && e.responseJSON && e.responseJSON.error) ||
                    (e && e.message) ||
                    _('Publish failed');
                this.showError(msg);
            }
            if (this.btnPublish)
                this.btnPublish.disabled = !this.state.selected;
        }
    }

    showError(message) {
        if (!this.alert) return;
        if (!message) {
            this.alert.textContent = '';
            this.alert.classList.add('d-none');
            return;
        }
        this.alert.textContent = message;
        this.alert.classList.remove('d-none');
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

    showFromMenu() {
        this.show();
    }
}
