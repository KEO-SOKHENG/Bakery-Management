// ── Full Backup & Restore page ──
// Talks to the admin backup endpoints. Shows real data only; no mock records.

(function () {
    'use strict';

    const root = document.getElementById('full_backup_root');
    if (!root) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const urls = {
        index: root.dataset.urlIndex,
        store: root.dataset.urlStore,
        verify: root.dataset.urlVerify,
        gate: root.dataset.urlGate,
        status: id => root.dataset.urlStatus.replace('__ID__', id),
        sandbox: id => root.dataset.urlSandbox.replace('__ID__', id),
    };

    const $ = id => document.getElementById(id);
    const el = {
        btnCreate: $('btn_create_full_backup'),
        components: $('bk_components'),
        createMsg: $('bk_create_message'),
        history: $('bk_history_list'),
        destination: $('bk_history_destination'),
        select: $('bk_select_existing'),
        upload: $('bk_upload_file'),
        btnVerify: $('btn_bk_verify'),
        verifySteps: $('bk_verify_steps'),
        previewEmpty: $('bk_preview_empty'),
        preview: $('bk_preview'),
        btnSandbox: $('btn_bk_sandbox'),
        sandboxSteps: $('bk_sandbox_steps'),
        gateList: $('bk_gate_list'),
    };

    let verifiedBackupId = null;
    const HISTORY_PREVIEW = 3;
    const viewAll = document.getElementById('bkx_view_all');
    let showAll = false;
    let allRows = [];

    // ── Helpers ──
    function esc(text) {
        return String(text ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    function bytes(n) {
        n = Number(n) || 0;
        if (n <= 0) return '0 B';
        const u = ['B', 'KB', 'MB', 'GB', 'TB'];
        const p = Math.min(Math.floor(Math.log(n) / Math.log(1024)), u.length - 1);
        return (Math.round((n / Math.pow(1024, p)) * 10) / 10) + ' ' + u[p];
    }

    function when(iso) {
        if (!iso) return 'Data unavailable';
        const d = new Date(iso);
        return isNaN(d) ? 'Data unavailable' : d.toLocaleString([], { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    // Readable backup name from the creation time, e.g. Nov_10_2026_01_46_PM.bkp
    function niceName(r) {
        const d = new Date(r.created_at);
        if (isNaN(d)) return r.filename || 'Backup';
        const p = n => String(n).padStart(2, '0');
        const mon = d.toLocaleString('en-US', { month: 'short' });
        const h12 = d.getHours() % 12 || 12;
        const ext = (r.filename && r.filename.includes('.')) ? r.filename.split('.').pop() : 'bkp';
        return mon + '_' + p(d.getDate()) + '_' + d.getFullYear() + '_' + p(h12) + '_' + p(d.getMinutes()) + '_' + (d.getHours() >= 12 ? 'PM' : 'AM') + '.' + ext;
    }

    async function api(url, options = {}) {
        const headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, options.headers || {});
        const res = await fetch(url, Object.assign({}, options, { headers }));
        let data = {};
        try { data = await res.json(); } catch (e) { /* non-JSON */ }
        if (!res.ok && !data.conditions) {
            throw new Error(data.message || ('Request failed (' + res.status + ')'));
        }
        return data;
    }

    function toast(msg) {
        if (typeof window.showToast === 'function') window.showToast(msg);
    }

    const pillText = { pending: 'Waiting', running: 'Working...', completed: 'Done', failed: 'Failed' };

    function setComponent(name, status, detail) {
        const li = el.components.querySelector('[data-component="' + name + '"]');
        if (!li) return;
        const pill = li.querySelector('.bk-pill');
        const cls = status === 'completed' ? 'is-ok' : status === 'running' ? 'is-run' : status === 'failed' ? 'is-fail' : 'is-pending';
        pill.className = 'bk-pill ' + cls;
        pill.textContent = (pillText[status] || status) + (detail ? ' · ' + detail : '');
    }

    function renderComponents(components) {
        Object.keys(components || {}).forEach(name => {
            const c = components[name] || {};
            let detail = '';
            if (c.status === 'completed') {
                if (name === 'database' && c.size) detail = bytes(c.size);
                if (name === 'media') detail = (c.count ?? 0) + ' files, ' + bytes(c.bytes);
                if (name === 'settings') detail = (c.count ?? 0) + ' items';
                if (name === 'package' && c.size) detail = bytes(c.size);
            }
            setComponent(name, c.status, detail);
        });
    }

    function statusPill(r) {
        if (r.status === 'running') return '<em class="bk-pill is-run">Running</em>';
        if (r.status === 'failed') return '<em class="bk-pill is-fail">Failed</em>';
        if (r.verified && r.warnings && r.warnings.length) return '<em class="bk-pill is-warn" title="' + esc(r.warnings.join(' ')) + '">Verified · warnings</em>';
        if (r.verified) return '<em class="bk-pill is-ok">Verified</em>';
        return '<em class="bk-pill is-pending">Not verified</em>';
    }

    // ── History ──
    async function loadHistory() {
        try {
            const data = await api(urls.index);
            const list = data.backups || [];
            if (viewAll) viewAll.title = data.destination || '';
            allRows = list;

            el.select.innerHTML = '<option value="">Choose a saved backup</option>' + list
                .filter(r => r.status === 'completed' && r.verified)
                .map(r => '<option value="' + esc(r.id) + '">' + esc(when(r.created_at)) + ' · ' + esc(bytes(r.package_size)) + '</option>').join('');

            renderOverview(list);

            if (!list.length) {
                el.history.innerHTML = '<div class="storage-empty">No backups yet. Create your first full backup above.</div>';
                return;
            }

            const dbIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg>';
            const dlIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';

            if (viewAll) {
                viewAll.hidden = list.length <= HISTORY_PREVIEW;
                el.destination.textContent = showAll ? 'Show Less' : 'View All Backups';
            }
            const rows = showAll ? list : list.slice(0, HISTORY_PREVIEW);

            el.history.innerHTML = rows.map(r => {
                const type = r.type === 'imported' ? 'Imported' : 'Full';
                const name = niceName(r);
                const action = r.download_url
                    ? '<a class="bkx-dl" href="' + esc(r.download_url) + '">' + dlIcon + '<span>Download</span></a>'
                    : '<span class="bkx-dl is-off">' + (r.status === 'running' ? 'In progress' : 'Unavailable') + '</span>';
                const rehearsal = r.rehearsal && !r.rehearsal.ok ? '<span class="bk-tag is-fail">Sandbox failed</span>' : '';
                const usable = r.status === 'completed' && r.verified;
                const more = usable ? '<button type="button" class="bkx-more" data-use-backup="' + esc(r.id) + '" title="Use this backup for restore" aria-label="Use this backup for restore">&middot;&middot;&middot;</button>' : '';
                return '<div class="bkx-row">' +
                    '<div class="bkx-name" title="' + esc(name) + '">' + dbIcon + '<span>' + esc(name) + '</span></div>' +
                    '<span class="bkx-chip">' + type + '</span>' +
                    '<span>' + esc(bytes(r.package_size)) + '</span>' +
                    '<span>' + esc(when(r.created_at)) + '</span>' +
                    '<div class="bkx-status">' + statusPill(r) + rehearsal + '</div>' +
                    '<div class="bkx-row-actions">' + action + more + '</div></div>';
            }).join('');
        } catch (err) {
            el.history.innerHTML = '<div class="storage-empty">Data unavailable</div>';
        }
    }

    // ── Overview (status banner + stat cards), derived from real history ──
    function renderOverview(list) {
        const done = list.filter(r => r.status === 'completed');
        const latest = done[0] || null;
        const banner = $('bkx_banner');
        const set = (id, text) => { const n = $(id); if (n) n.textContent = text; };

        set('bkx_stat_total', String(list.length));
        set('bkx_stat_size', latest && latest.database_size ? bytes(latest.database_size) : '—');
        set('bkx_stat_last', latest ? when(latest.created_at) : '—');

        if (!banner) return;
        const first = list[0];
        let state = 'is-warn', title = 'No backup yet', sub = 'Create your first full backup to protect your data.', badge = 'Action needed';
        if (first && first.status === 'failed') {
            state = 'is-fail'; title = 'Last backup failed'; sub = 'Create a new backup and check the history below.'; badge = 'Attention';
        } else if (latest && latest.verified) {
            state = 'is-ok'; title = 'Backup System Ready'; sub = 'Your backup and restore system is working properly.'; badge = 'Active';
        } else if (latest) {
            state = 'is-warn'; title = 'Latest backup not verified'; sub = 'Verify the latest package in Advanced Details.'; badge = 'Review';
        }
        banner.className = 'bkx-banner ' + (state === 'is-ok' ? '' : state);
        set('bkx_banner_title', title);
        set('bkx_banner_sub', sub);
        set('bkx_banner_badge', badge);
    }

    function openRestore(backupId) {
        const details = $('bkx_advanced');
        if (details) details.open = true;
        if (backupId) {
            el.select.value = backupId;
            if (el.select.value === backupId) el.upload.value = '';
        }
        const target = $('bk_restore_section');
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── Create full backup ──
    async function createBackup() {
        el.btnCreate.disabled = true;
        el.components.hidden = false;
        el.createMsg.hidden = true;
        ['database', 'media', 'settings', 'package', 'verification'].forEach(n => setComponent(n, 'pending'));

        try {
            const start = await api(urls.store, { method: 'POST' });
            const id = start.backup_id;
            let done = false;
            let guard = 0;
            while (!done && guard < 1800) {
                await new Promise(r => setTimeout(r, 1500));
                guard++;
                let s;
                try { s = await api(urls.status(id)); } catch (e) { continue; }
                const p = s.progress || (s.record || {});
                renderComponents(p.components || (s.record && s.record.components));
                const status = (s.record && s.record.status) || p.status;
                if (status === 'completed' || status === 'failed') {
                    done = true;
                    const ok = status === 'completed' && ((s.record && s.record.verified) || p.verified);
                    el.createMsg.hidden = false;
                    el.createMsg.className = 'bk-message ' + (ok ? 'is-ok' : 'is-fail');
                    el.createMsg.textContent = ok ? 'Backup created and verified.' : ('Backup failed: ' + ((s.record && s.record.error) || p.error || 'unknown error'));
                    toast(ok ? 'Full backup created and verified' : 'Full backup failed');
                }
            }
        } catch (err) {
            el.createMsg.hidden = false;
            el.createMsg.className = 'bk-message is-fail';
            el.createMsg.textContent = err.message || 'Could not start the backup.';
        } finally {
            el.btnCreate.disabled = false;
            loadHistory();
        }
    }

    // ── Restore workflow ──
    function renderChecks(ul, steps) {
        ul.hidden = false;
        ul.innerHTML = steps.map(s => {
            const cls = s.status === 'pass' ? 'is-ok' : (s.status === 'warn' ? 'is-warn' : 'is-fail');
            const icon = s.status === 'pass' ? '✓' : (s.status === 'warn' ? '!' : '✕');
            return '<li class="' + cls + '"><span class="bk-check-icon">' + icon + '</span><div><strong>' + esc(s.name) + '</strong><small>' + esc(s.message) + '</small></div></li>';
        }).join('');
    }

    function renderPreview(p) {
        const rows = [
            ['Backup created', when(p.created_at)],
            ['Database', (p.database_engine || '').trim() + ' · ' + bytes(p.database_size)],
            ['Media files', (p.media_count ?? 0) + ' files · ' + bytes(p.media_bytes)],
            ['Settings', p.settings_included ? (p.settings_count + ' items') : 'Not included'],
            ['Verification', 'Passed'],
        ];
        el.preview.innerHTML = rows.map(r => '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>').join('');
        el.preview.hidden = false;
        el.previewEmpty.hidden = true;
    }

    async function verifyPackage() {
        verifiedBackupId = null;
        el.btnSandbox.disabled = true;
        el.preview.hidden = true;
        el.previewEmpty.hidden = false;
        el.sandboxSteps.hidden = true;

        const form = new FormData();
        if (el.upload.files.length) {
            form.append('package', el.upload.files[0]);
        } else if (el.select.value) {
            form.append('backup_id', el.select.value);
        } else {
            toast('Choose a backup or upload a package first');
            return;
        }

        el.btnVerify.disabled = true;
        el.btnVerify.textContent = 'Verifying...';
        try {
            const data = await api(urls.verify, { method: 'POST', body: form });
            renderChecks(el.verifySteps, data.steps || []);
            if (data.ok && data.preview) {
                renderPreview(data.preview);
                verifiedBackupId = data.backup_id;
                el.btnSandbox.disabled = false;
                toast('Package verified');
                loadHistory();
            } else {
                toast('Package did not pass verification');
            }
        } catch (err) {
            renderChecks(el.verifySteps, [{ name: 'Verification', status: 'fail', message: err.message }]);
        } finally {
            el.btnVerify.disabled = false;
            el.btnVerify.textContent = 'Verify Package';
        }
    }

    async function sandboxRestore() {
        if (!verifiedBackupId) return;
        el.btnSandbox.disabled = true;
        el.btnSandbox.textContent = 'Rehearsing...';
        try {
            const data = await api(urls.sandbox(verifiedBackupId), { method: 'POST' });
            renderChecks(el.sandboxSteps, (data.report && data.report.steps) || []);
            toast(data.ok ? 'Sandbox restore passed' : 'Sandbox restore failed');
            loadHistory();
        } catch (err) {
            renderChecks(el.sandboxSteps, [{ name: 'Sandbox restore', status: 'fail', message: err.message }]);
        } finally {
            el.btnSandbox.disabled = false;
            el.btnSandbox.textContent = 'Restore to Sandbox';
        }
    }

    async function loadGate() {
        try {
            const data = await api(urls.gate);
            el.gateList.innerHTML = (data.conditions || []).map(c => '<li class="' + (c.met ? 'is-ok' : 'is-fail') + '"><span class="bk-check-icon">' + (c.met ? '✓' : '✕') + '</span><div><strong>' + esc(c.label) + '</strong></div></li>').join('');
        } catch (e) { /* leave empty */ }
    }

    el.btnCreate.addEventListener('click', createBackup);
    el.btnVerify.addEventListener('click', verifyPackage);
    el.btnSandbox.addEventListener('click', sandboxRestore);
    el.upload.addEventListener('change', () => { if (el.upload.files.length) el.select.value = ''; });
    el.select.addEventListener('change', () => { if (el.select.value) el.upload.value = ''; });

    if (viewAll) viewAll.addEventListener('click', () => { showAll = !showAll; loadHistory(); });
    const btnOpenRestore = $('bkx_open_restore');
    if (btnOpenRestore) btnOpenRestore.addEventListener('click', () => openRestore());
    el.history.addEventListener('click', e => {
        const b = e.target.closest('[data-use-backup]');
        if (b) openRestore(b.getAttribute('data-use-backup'));
    });

    loadHistory();
    loadGate();
})();
