export default class ModalPublishToGithub {
    constructor(modals) {
        this.modals = modals;
        this.app = modals.app;
        this.id = 'modalPublishToGithub';
        this.modal = null;
        this._cancelled = false;
        this._deviceFlowActive = false;
        this._devicePollTimeoutId = null;
        this._xhrPublish = null;
        this.mode = 'publish';
        this.viewSiteUrl = null;
        this.state = {
            authed: false,
            repos: [],
            filtered: [],
            selected: null,
            repoUrl: null,
        };
    }

    init() {
        const el = document.getElementById(this.id);
        if (!el) return;
        this.bindElements(el);
    }

    behaviour() {
        this.bindEvents();
    }

    bindElements(root) {
        // Auth
        this.btnAuth = root.querySelector('#gh-auth-btn');
        this.devicePanel = root.querySelector('#gh-device');
        this.deviceCode = root.querySelector('#gh-user-code');
        this.deviceUrl = root.querySelector('#gh-verify-url');
        this.deviceMsg = root.querySelector('#gh-device-msg');
        // UI refs
        this.stepSelect = root.querySelector('#stepSelect');
        this.stepProgress = root.querySelector('#stepProgress');
        this.publishDone = root.querySelector('#publishDone');
        this.btnPublish = root.querySelector('#btnPublish');
        this.btnRepo = root.querySelector('#btnRepo');
        this.btnRepoText = root.querySelector('#btnRepoText');
        this.repoMenu = root.querySelector('#repoMenu');
        this.repoFilter = root.querySelector('#repoFilter');
        this.btnReloadRepos = root.querySelector('#btnReloadRepos');
        this.branchInput = root.querySelector('#branchInput');
        this.branchList = root.querySelector('#branchList');
        this.branchWarn = root.querySelector('#branchWarn');
        this.overwriteWrap = root.querySelector('#overwriteWrap');
        this.overwriteCheckbox = root.querySelector('#overwriteCheckbox');
        this.progressBar = root.querySelector('#progressBar');
        this.progressMsg = root.querySelector('#progressMsg');
        this.doneRepoUrl = root.querySelector('#doneRepoUrl');
        this.doneText = root.querySelector('#doneText');
        this.btnViewSite = root.querySelector('#btnViewSite');
        // overwrite via checkbox in UI
        this.overwrite = null;
    }

    bindEvents() {
        // OAuth popup
        if (this.btnAuth) {
            this.btnAuth.addEventListener('click', (e) => {
                const url = this.btnAuth.getAttribute('href');
                const w = 720,
                    h = 820;
                const left = window.screen.width / 2 - w / 2;
                const top = window.screen.height / 2 - h / 2;
                const popup = window.open(
                    url,
                    'github_oauth',
                    `width=${w},height=${h},top=${top},left=${left}`
                );
                const onMsg = async (evt) => {
                    try {
                        if (
                            !evt ||
                            !evt.data ||
                            evt.data.type !== 'github_auth'
                        )
                            return;
                        window.removeEventListener('message', onMsg);
                        if (popup && !popup.closed) popup.close();
                        try {
                            console.debug('GitHub auth message:', evt.data);
                        } catch (_x) {}
                        if (evt.data && evt.data.ok === false) {
                            const msg =
                                (evt.data && evt.data.error) ||
                                _('GitHub authorization failed.');
                            this.errorToast({ message: msg });
                            return;
                        }
                        await this.checkAuthAndLoad();
                    } catch (_e) {}
                };
                window.addEventListener('message', onMsg);
                if (popup) e.preventDefault();
            });
        }

        if (this.btnReloadRepos)
            this.btnReloadRepos.addEventListener('click', () =>
                this.checkAuthAndLoad()
            );
        if (this.repoFilter)
            this.repoFilter.addEventListener('input', () =>
                this.renderRepoMenu()
            );
        if (this.branchInput)
            this.branchInput.addEventListener('input', () => {
                this.updateBranchState();
                this.updatePublishEnabled();
            });
        if (this.btnPublish)
            this.btnPublish.addEventListener('click', async () => {
                if (this.mode === 'view' && this.viewSiteUrl) {
                    try {
                        window.open(this.viewSiteUrl, '_blank');
                    } catch (_e) {}
                    return;
                }
                if (this.mode === 'publishing') {
                    const ok = window.confirm(
                        _('Publishing in progress. Do you want to cancel?')
                    );
                    if (!ok) return;
                    this._cancelled = true;
                    try {
                        if (this._xhrPublish && this._xhrPublish.abort)
                            this._xhrPublish.abort();
                    } catch (_e) {}
                    // Restore UI
                    if (this.stepProgress)
                        this.stepProgress.classList.add('d-none');
                    if (this.stepSelect)
                        this.stepSelect.classList.remove('d-none');
                    this.mode = 'publish';
                    if (this.btnPublish) {
                        try {
                            this.btnPublish.textContent = _('Publish');
                        } catch (_e) {}
                        this.updatePublishEnabled();
                    }
                    // Notify user
                    try {
                        this.app.toasts.createToast({
                            title: _('Cancelled'),
                            body: _('Publishing cancelled.'),
                            icon: 'info',
                            remove: 3000,
                        });
                    } catch (_e) {}
                    return;
                }
                await this.publish();
            });
        if (this.overwriteCheckbox)
            this.overwriteCheckbox.addEventListener('change', () =>
                this.updatePublishEnabled()
            );

        // Confirm cancel if publishing in progress
        const el = document.getElementById(this.id);
        if (el) {
            el.addEventListener('hide.bs.modal', (evt) => {
                const inProgress =
                    this.stepProgress &&
                    !this.stepProgress.classList.contains('d-none');
                if (inProgress) {
                    const ok = window.confirm(
                        _('Publishing in progress. Do you want to cancel?')
                    );
                    if (!ok) {
                        evt.preventDefault();
                        return;
                    }
                }
                // mark cancelled to ignore any pending async completions
                this._cancelled = true;
                // abort in-flight publish request if any
                try {
                    if (this._xhrPublish && this._xhrPublish.abort) {
                        this._xhrPublish.abort();
                    }
                } catch (_e) {}
            });
            el.addEventListener('hidden.bs.modal', () => {
                // fully reset so next open starts fresh
                this.reset();
            });
        }
    }

    async show() {
        const el = document.getElementById(this.id);
        if (!el) return;
        if (!this.modal) {
            const bs = window.bootstrap || bootstrap;
            this.modal = new bs.Modal(el, {});
        }
        this.reset();
        this.modal.show();
        await this.decideFlow();
    }

    reset() {
        // stop device polling if any
        this._deviceFlowActive = false;
        if (this._devicePollTimeoutId) {
            try {
                clearTimeout(this._devicePollTimeoutId);
            } catch (_e) {}
            this._devicePollTimeoutId = null;
        }
        this._cancelled = false;

        this.state = {
            authed: false,
            repos: [],
            filtered: [],
            selected: null,
            repoUrl: null,
        };
        if (this.btnRepoText)
            this.btnRepoText.textContent = _('Select repository…');
        if (this.branchInput) {
            this.branchInput.value = '';
            this.branchInput.disabled = true;
        }
        if (this.repoMenu) this.repoMenu.innerHTML = '';
        if (this.branchList) this.branchList.innerHTML = '';
        if (this.branchWarn) this.branchWarn.classList.add('d-none');
        if (this.overwriteWrap) this.overwriteWrap.classList.add('d-none');
        this.mode = 'publish';
        this.viewSiteUrl = null;
        if (this.btnPublish) {
            this.btnPublish.disabled = true;
            try {
                this.btnPublish.textContent = _('Publish');
            } catch (_e) {}
        }
        if (this.stepProgress) this.stepProgress.classList.add('d-none');
        if (this.publishDone) this.publishDone.classList.add('d-none');
        if (this.btnPublish) this.btnPublish.classList.remove('d-none');
        if (this.overwriteCheckbox) this.overwriteCheckbox.checked = false;
        if (this.doneRepoUrl) this.doneRepoUrl.href = '#';
        if (this.btnViewSite) {
            this.btnViewSite.href = '#';
            this.btnViewSite.classList.add('d-none');
        }
        if (this.doneText) this.doneText.textContent = '';
        // Show auth area by default; hide selection/progress
        const auth = document.getElementById('gh-step-auth');
        if (auth) auth.classList.remove('d-none');
        if (this.btnAuth) this.btnAuth.classList.remove('d-none');
        if (this.devicePanel) this.devicePanel.classList.add('d-none');
        if (this.stepSelect) this.stepSelect.classList.add('d-none');
    }

    async checkAuthAndLoad() {
        try {
            const st = await this.app.api.getGithubStatus();
            if (st && st.connected) {
                this.state.authed = true;
                await this.renderRepoMenuData();
                if (this.btnAuth) this.btnAuth.classList.add('d-none');
                const auth = document.getElementById('gh-step-auth');
                if (auth) auth.classList.add('d-none');
                if (this.stepSelect) this.stepSelect.classList.remove('d-none');
                return true;
            }
        } catch (_e) {}
        const stEl = document.getElementById('gh-auth-status');
        if (stEl)
            stEl.textContent = _(
                'Not connected. Authenticate, then click Refresh.'
            );
        return false;
    }

    async decideFlow() {
        const offline = !!this.app?.eXeLearning?.config?.isOfflineInstallation;
        if (!offline) {
            // If already connected, jump to repo selection; else show auth
            const authed = await this.checkAuthAndLoad();
            if (!authed) {
                if (this.devicePanel) this.devicePanel.classList.add('d-none');
                if (this.btnAuth) this.btnAuth.classList.remove('d-none');
                const stEl = document.getElementById('gh-auth-status');
                if (stEl) stEl.textContent = '';
            }
            return;
        }
        // Offline device flow
        if (this.btnAuth) this.btnAuth.classList.add('d-none');
        if (this.devicePanel) this.devicePanel.classList.remove('d-none');
        await this.startDeviceFlow();
    }

    async startDeviceFlow() {
        try {
            const res = await this.app.api.postGithubDeviceStart(
                'read:user public_repo'
            );
            const userCode = res.user_code;
            const deviceCode = res.device_code;
            const url = res.verification_uri || res.verification_uri_complete;
            const interval = Math.max(5, parseInt(res.interval || 5, 10));
            if (this.deviceCode) this.deviceCode.textContent = userCode || '…';
            if (this.deviceUrl && url) this.deviceUrl.href = url;
            let currentInterval = interval * 1000;
            this._deviceFlowActive = true;
            const pollOnce = async () => {
                if (this._cancelled || !this._deviceFlowActive) return;
                try {
                    const p =
                        await this.app.api.postGithubDevicePoll(deviceCode);
                    if (p && p.access_token) {
                        if (!this._cancelled) await this.checkAuthAndLoad();
                        return;
                    }
                    if (p && p.error === 'slow_down') currentInterval += 5000;
                    else if (
                        p &&
                        (p.error === 'expired_token' ||
                            p.error === 'access_denied')
                    ) {
                        if (this.deviceMsg)
                            this.deviceMsg.textContent = _(
                                'Authorization expired or denied.'
                            );
                        return;
                    }
                } catch (_e) {}
                this._devicePollTimeoutId = setTimeout(
                    pollOnce,
                    currentInterval
                );
            };
            this._devicePollTimeoutId = setTimeout(pollOnce, currentInterval);
        } catch (e) {
            this.errorToast(e);
        }
    }

    async renderRepoMenuData() {
        try {
            const repos = await this.app.api.getGithubRepos();
            this.state.repos = (repos || []).map((r) => ({
                owner: r.owner,
                name: r.name,
                full: `${r.owner}/${r.name}`,
            }));
            this.state.filtered = this.state.repos.slice();
            this.renderRepoMenu();
        } catch (e) {
            this.errorToast(e);
        }
    }

    renderRepoMenu() {
        if (!this.repoMenu) return;
        const q = (
            (this.repoFilter && this.repoFilter.value) ||
            ''
        ).toLowerCase();
        const items =
            !q || !q.trim()
                ? this.state.repos
                : this.state.repos.filter((x) =>
                      x.full.toLowerCase().includes(q)
                  );
        this.repoMenu.innerHTML = '';
        if (!items || !items.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-muted';
            empty.textContent = _('No repositories');
            this.repoMenu.appendChild(empty);
            return;
        }
        items.forEach((it) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className =
                'list-group-item list-group-item-action repo-item text-start';
            btn.innerHTML = `<div class="d-flex justify-content-between"><strong>${it.full}</strong><small>${it.owner}</small></div>`;
            btn.addEventListener('click', () => this.selectRepo(it));
            this.repoMenu.appendChild(btn);
        });
    }

    async selectRepo(it) {
        if (!it) return;
        if (this.btnRepoText) this.btnRepoText.textContent = it.full;
        this.state.selected = { owner: it.owner, repo: it.name };
        // Load branches
        try {
            const list = await this.app.api.getGithubBranches(
                it.owner,
                it.name
            );
            this.fillBranchDatalist(Array.isArray(list) ? list : []);
        } catch (_e) {
            this.fillBranchDatalist([]);
        }
        // Suggest branch from server
        try {
            const res = await this.app.api.postGithubCheck({
                owner: it.owner,
                repo: it.name,
            });
            const def =
                res.branch ||
                this.app.eXeLearning.symfony.githubPagesBranch ||
                'gh-pages';
            if (this.branchInput && !this.branchInput.value)
                this.branchInput.value = def;
        } catch (_e) {}
        if (this.branchInput) this.branchInput.disabled = false;
        this.updateBranchState();
        this.updatePublishEnabled();
    }

    fillBranchDatalist(arr) {
        if (!this.branchList) return;
        this.branchList.innerHTML = '';
        (arr || []).forEach((b) => {
            const opt = document.createElement('option');
            opt.value = b;
            this.branchList.appendChild(opt);
        });
    }

    updateBranchState() {
        if (!this.branchInput || !this.branchWarn) return;
        const val = (this.branchInput.value || '').trim();
        const exists = Array.from(this.branchList?.children || []).some(
            (o) => o.value === val
        );
        this.branchWarn.classList.toggle('d-none', !val || exists);
        // Show overwrite UI only when branch exists
        if (this.overwriteWrap)
            this.overwriteWrap.classList.toggle('d-none', !exists);
        // Reset overwrite checkbox when branch changes
        if (this.overwriteCheckbox && exists === true)
            this.overwriteCheckbox.checked = false;
        this.branchExists = exists;
    }

    updatePublishEnabled() {
        if (!this.btnPublish) return;
        const hasRepo = !!this.state.selected;
        const branch = (
            (this.branchInput && this.branchInput.value) ||
            ''
        ).trim();
        const valid = this.isValidBranchName(branch);
        const requiresOverwriteConsent = !!this.branchExists;
        const consentGiven =
            !requiresOverwriteConsent ||
            (this.overwriteCheckbox && this.overwriteCheckbox.checked);
        this.btnPublish.disabled = !(
            hasRepo &&
            valid &&
            branch.length > 0 &&
            consentGiven
        );
    }

    isValidBranchName(name) {
        if (!name) return false;
        if (name.startsWith('/') || name.endsWith('/')) return false;
        if (name.includes('..') || name.includes('@{')) return false;
        if (/[^\w\.\-\/]/.test(name)) return false;
        return true;
    }

    async publish() {
        const sel = this.state.selected;
        if (!sel) return;
        if (this.btnPublish) this.btnPublish.disabled = true;
        // Show progress
        if (this.stepSelect) this.stepSelect.classList.add('d-none');
        if (this.publishDone) this.publishDone.classList.add('d-none');
        if (this.stepProgress) this.stepProgress.classList.remove('d-none');
        if (this.progressBar) this.progressBar.style.width = '10%';
        if (this.progressMsg) this.progressMsg.textContent = _('Preparing…');
        try {
            const payload = {
                owner: sel.owner,
                repo: sel.repo,
                overwrite: !!(
                    this.overwriteCheckbox && this.overwriteCheckbox.checked
                ),
                odeSessionId: this.app.project.odeSession,
                branch:
                    (this.branchInput && this.branchInput.value) || undefined,
            };
            this._xhrPublish = this.app.api.postGithubPublish(payload);
            // While publishing, allow cancel
            this.mode = 'publishing';
            if (this.btnPublish) {
                try {
                    this.btnPublish.textContent = _('Cancel');
                } catch (_e) {}
                this.btnPublish.disabled = false;
            }
            const res = await this._xhrPublish;
            if (this._cancelled) return;
            if (this.progressBar) this.progressBar.style.width = '100%';
            this.state.repoUrl =
                res && res.repoUrl
                    ? res.repoUrl
                    : `https://github.com/${sel.owner}/${sel.repo}`;
            if (this.stepProgress) this.stepProgress.classList.add('d-none');
            if (this.publishDone) this.publishDone.classList.remove('d-none');
            const b = (this.branchInput && this.branchInput.value) || '';
            if (this.doneRepoUrl && this.state.repoUrl) {
                // Link directly to the pushed branch in the repo
                this.doneRepoUrl.href = `${this.state.repoUrl}/tree/${b}`;
            }
            const manual = res && res.manual;
            const pagesUrl = res && res.pagesUrl;
            // Footer primary button becomes "View site"
            const href =
                pagesUrl && !manual
                    ? pagesUrl
                    : `${this.state.repoUrl}/tree/${b}`;
            if (this.btnViewSite) {
                this.btnViewSite.href = href;
                this.btnViewSite.classList.remove('d-none');
            }
            this.mode = 'view';
            this.viewSiteUrl = href;
            if (this.btnPublish) {
                try {
                    this.btnPublish.textContent = _('View site');
                } catch (_e) {}
                this.btnPublish.disabled = false;
            }
            if (this.doneText) {
                let msg = `${sel.owner}/${sel.repo} → ${b}`;
                if (manual) {
                    msg += ` · ${_('Enable GitHub Pages manually in Settings → Pages (Source: branch')} ${b} / /)`;
                }
                this.doneText.textContent = msg;
            }
        } catch (e) {
            this.errorToast(e);
            if (this.btnPublish) {
                this.mode = 'publish';
                try {
                    this.btnPublish.textContent = _('Publish');
                } catch (_e) {}
                this.btnPublish.disabled = false;
            }
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

    showFromMenu() {
        this.show();
    }
}
