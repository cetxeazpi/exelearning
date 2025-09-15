export default class ModalPublishToGithub {
    constructor(modals) {
        this.modals = modals;
        this.app = modals.app;
        this.id = 'modalPublishToGithub';
        this.modal = null;
        this.steps = {};
        this.state = {
            authed: false,
            repos: [],
            selected: null,
            pagesUrl: null,
            repoUrl: null,
        };
    }

    init() {
        const el = document.getElementById(this.id);
        if (!el) return;
        this.bindElements(el);
    }

    behaviour() {
        if (!this.btnNext) return;
        this.bindEvents();
    }

    bindElements(root) {
        this.steps.auth = root.querySelector('#gh-step-auth');
        this.steps.repo = root.querySelector('#gh-step-repo');
        this.steps.confirm = root.querySelector('#gh-step-confirm');
        this.steps.progress = root.querySelector('#gh-step-progress');
        this.steps.done = root.querySelector('#gh-step-done');
        this.btnNext = root.querySelector('#gh-next');
        this.btnAuth = root.querySelector('#gh-auth-btn');
        this.repoSelect = root.querySelector('#gh-repo-select');
        this.repoFilter = root.querySelector('#gh-repo-filter');
        this.repoRefresh = root.querySelector('#gh-refresh-repos');
        this.repoCreate = root.querySelector('#gh-create-repo');
        this.repoName = root.querySelector('#gh-new-repo');
        this.repoVisibility = root.querySelector('#gh-new-visibility');
        this.overwrite = root.querySelector('#gh-overwrite');
        this.confirmText = root.querySelector('#gh-confirm-text');
        this.progressBar = root.querySelector('#gh-progress-bar');
        this.progressMsg = root.querySelector('#gh-progress-msg');
        this.viewSite = root.querySelector('#gh-view-site');
        this.viewRepo = root.querySelector('#gh-view-repo');
        this.pagesManual = root.querySelector('#gh-pages-manual');
        // No device flow elements when using popup
    }

    bindEvents() {
        // Auth via popup/new window, then postMessage closes popup and continues
        this.btnAuth.addEventListener('click', (e) => {
            const url = this.btnAuth.getAttribute('href');
            const w = 720, h = 820;
            const left = (window.screen.width/2)-(w/2);
            const top = (window.screen.height/2)-(h/2);
            const popup = window.open(url, 'github_oauth', `width=${w},height=${h},top=${top},left=${left}`);
            const onMsg = async (evt) => {
                try {
                    if (!evt || !evt.data || evt.data.type !== 'github_auth') return;
                    window.removeEventListener('message', onMsg);
                    if (popup && !popup.closed) popup.close();
                    if (evt.data && evt.data.ok === false) {
                        const msg = (evt.data && evt.data.error) || _('GitHub authorization failed.');
                        this.errorToast({ message: msg });
                        return;
                    }
                    await this.checkAuthAndLoad();
                } catch (_e) {}
            };
            window.addEventListener('message', onMsg);
            if (popup) {
                e.preventDefault(); // keep current URL unchanged
            }
        });
        this.btnNext.addEventListener('click', async () => {
            if (this.steps.confirm.classList.contains('d-none')) return;
            await this.publish();
        });
        this.repoRefresh.addEventListener('click', () => this.loadRepos());
        this.repoCreate.addEventListener('click', async () => {
            const name = (this.repoName.value || '').trim();
            if (!name) return;
            const visibility = this.repoVisibility.value || 'public';
            try {
                await this.app.api.postGithubCreateRepo({ name, visibility });
                await this.loadRepos();
            } catch (e) {
                this.errorToast(e);
            }
        });
        this.repoSelect.addEventListener('change', () => this.onRepoSelect());
        if (this.repoFilter) {
            this.repoFilter.addEventListener('input', () => this.renderRepos());
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
        await this.checkAuthAndLoad();
    }

    reset() {
        if (!this.steps || !this.steps.auth) return;
        Object.values(this.steps).forEach((el) => el && el.classList.add('d-none'));
        this.steps.auth.classList.remove('d-none');
        if (this.btnNext) this.btnNext.classList.add('d-none');
        this.state = { authed: false, repos: [], selected: null, pagesUrl: null, repoUrl: null };
    }

    async checkAuthAndLoad() {
        try {
            const repos = await this.app.api.getGithubRepos();
            // Only consider authenticated if backend returned an array of repos
            if (Array.isArray(repos)) {
                this.state.authed = true;
                this.state.repos = repos || [];
                await this.renderRepos();
                this.steps.auth.classList.add('d-none');
                this.steps.repo.classList.remove('d-none');
            } else {
                // Keep auth step visible (likely 401/403 masked by base ajax wrapper)
                this.state.authed = false;
                this.steps.auth.classList.remove('d-none');
                this.steps.repo.classList.add('d-none');
            }
        } catch (e) {
            // On transport or HTTP error, remain in auth step
            this.state.authed = false;
            this.steps.auth.classList.remove('d-none');
            this.steps.repo.classList.add('d-none');
        }
    }

    // Device flow removed: using popup flow only

    async loadRepos() {
        try {
            const repos = await this.app.api.getGithubRepos();
            this.state.repos = repos || [];
            await this.renderRepos();
        } catch (e) {
            this.errorToast(e);
        }
    }

    async renderRepos() {
        this.repoSelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '—';
        this.repoSelect.appendChild(empty);
        const q = (this.repoFilter && this.repoFilter.value || '').toLowerCase();
        (this.state.repos || []).forEach((r) => {
            const opt = document.createElement('option');
            opt.value = `${r.owner}/${r.name}`;
            opt.textContent = `${r.owner}/${r.name}`;
            if (!q || opt.textContent.toLowerCase().includes(q)) {
                this.repoSelect.appendChild(opt);
            }
        });
    }

    async onRepoSelect() {
        const v = this.repoSelect.value;
        if (!v) return;
        const [owner, repo] = v.split('/');
        this.state.selected = { owner, repo };
        try {
            const res = await this.app.api.postGithubCheck({ owner, repo });
            const branch = res.branch || this.app.eXeLearning.symfony.githubPagesBranch || 'gh-pages';
            this.confirmText.textContent = `${owner}/${repo} → ${branch}`;
            this.steps.confirm.classList.remove('d-none');
            this.btnNext.classList.remove('d-none');
        } catch (e) {
            this.errorToast(e);
        }
    }

    async publish() {
        const sel = this.state.selected;
        if (!sel) return;
        this.steps.progress.classList.remove('d-none');
        this.progressBar.style.width = '35%';
        this.progressMsg.textContent = _('Publishing…');
        try {
            const payload = {
                owner: sel.owner,
                repo: sel.repo,
                overwrite: !!this.overwrite.checked,
                odeSessionId: this.app.project.odeSession,
            };
            const res = await this.app.api.postGithubPublish(payload);
            this.progressBar.style.width = '100%';
            this.state.pagesUrl = res.pagesUrl || null;
            this.state.repoUrl = res.repoUrl || null;
            this.steps.done.classList.remove('d-none');
            if (this.state.pagesUrl) {
                this.viewSite.href = this.state.pagesUrl;
            }
            if (this.state.repoUrl) {
                this.viewRepo.href = this.state.repoUrl;
            }
            if (res.manual) {
                this.pagesManual.textContent = res.manual;
                this.pagesManual.classList.remove('d-none');
            }
        } catch (e) {
            this.errorToast(e);
        }
    }

    errorToast(e) {
        let body = (e && e.message) || _('Unknown error.');
        this.app.toasts.createToast({ title: _('Error'), body, icon: 'error', error: true, remove: 5000 });
    }

    showFromMenu() {
        this.show();
    }
}
