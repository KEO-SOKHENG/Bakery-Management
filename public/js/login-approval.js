(function () {
    'use strict';

    // ── Config ──
    var cfg = window.LOGIN_APPROVAL_CONFIG;
    if (!cfg || !cfg.pendingUrl) return;

    var list = document.getElementById('notification_list');
    if (!list) return;

    var POLL_MS = 10000;
    // Stop polling once the person has been idle this long (matches the server's active window).
    var IDLE_MS = (cfg.idleSeconds || 300) * 1000;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var lastInteraction = Date.now();
    var pageLoadedAt = Date.now();
    var items = {};
    var timer = null;

    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (evt) {
        window.addEventListener(evt, function () {
            var wasIdle = Date.now() - lastInteraction > IDLE_MS;
            lastInteraction = Date.now();
            if (wasIdle) poll();
        }, { passive: true, capture: true });
    });

    // ── Helpers (textContent only: request data is never parsed as HTML) ──

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    function fmt(s) {
        s = Math.max(0, s);
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }

    function setBell(delta) {
        var btn = document.getElementById('notification_bell_btn');
        if (!btn) return;
        var badge = document.getElementById('notification_badge');
        if (!badge) {
            badge = el('span', 'bell-badge', '0');
            badge.id = 'notification_badge';
            btn.appendChild(badge);
        }
        var count = Math.max(0, (parseInt(badge.textContent, 10) || 0) + delta);
        badge.textContent = String(count);
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
        var tag = document.getElementById('notification_count_tag');
        if (tag) tag.textContent = count + ' New';
    }

    function storedRow(id) {
        return list.querySelector('[data-dedup="login_request:' + id + '"]');
    }

    function toggleEmptyState() {
        var empty = list.querySelector('[data-lang-key="no_notifications"]');
        if (empty) empty.style.display = list.querySelector('.notification-item:not([hidden])') ? 'none' : '';
    }

    // ── Actionable bell item ──

    function buildItem(req) {
        var item = el('div', 'notification-item unread lac-item');
        item.setAttribute('role', 'group');
        item.setAttribute('aria-label', 'Login approval request');

        var icon = el('div', 'notification-icon-box warning');
        icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
        item.appendChild(icon);

        var body = el('div', 'notification-content');
        body.appendChild(el('div', 'notification-item-title', 'Login approval needed'));
        body.appendChild(el('div', 'notification-item-desc',
            req.user + ' (' + req.role + ') · ' + req.browser + ' on ' + req.platform + ' · ' + req.device + ' · IP ' + (req.ip || 'unknown')));
        var expiry = el('span', 'notification-time lac-expiry');
        body.appendChild(expiry);

        var actions = el('div', 'lac-actions');
        var reject = el('button', 'lac-reject', 'Reject');
        var approve = el('button', 'lac-approve', 'Approve');
        reject.type = approve.type = 'button';
        actions.appendChild(reject);
        actions.appendChild(approve);
        body.appendChild(actions);
        item.appendChild(body);

        var entry = { item: item, deadline: Date.now() + req.seconds_left * 1000, expiry: expiry, confirming: false };

        // Clicks inside the item must not close the dropdown or follow a link.
        item.addEventListener('click', function (e) { e.stopPropagation(); });
        reject.addEventListener('click', function () { decide(req.id, 'reject', [reject, approve]); });
        approve.addEventListener('click', function () {
            // Two-step approve guards against reflex clicks.
            if (!entry.confirming) {
                entry.confirming = true;
                body.insertBefore(el('div', 'lac-confirm', 'Only approve if you recognise this person and device.'), actions);
                approve.textContent = 'Confirm approve';
                return;
            }
            decide(req.id, 'approve', [reject, approve]);
        });

        return entry;
    }

    function add(req) {
        var entry = buildItem(req);
        items[req.id] = entry;

        // Replace the stored "Login approval needed" row for this request with the actionable one.
        var stored = storedRow(req.id);
        if (stored) stored.hidden = true;
        list.insertBefore(entry.item, list.firstChild);

        // Count it on the bell only if it arrived after this page was rendered (stored rows are already counted).
        entry.counted = !!stored && stored.classList.contains('unread');
        if (!stored && new Date(req.requested_at).getTime() > pageLoadedAt) {
            setBell(1);
            entry.counted = true;
        }
        toggleEmptyState();
    }

    function remove(id) {
        if (!items[id]) return;
        var counted = items[id].counted;
        items[id].item.remove();
        delete items[id];

        // Handled or expired: show the stored row again as read, and take it off the bell count.
        var stored = storedRow(id);
        if (stored) {
            stored.hidden = false;
            if (stored.classList.contains('unread')) {
                stored.classList.remove('unread');
                stored.dataset.read = '1';
                var dot = stored.querySelector('.unread-dot');
                if (dot) dot.remove();
                fetch('/notifications/' + stored.dataset.id + '/read', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                }).catch(function () {});
            }
        }
        if (counted) setBell(-1);
        toggleEmptyState();
    }

    function tick() {
        var now = Date.now();
        Object.keys(items).forEach(function (id) {
            var left = Math.round((items[id].deadline - now) / 1000);
            if (left <= 0) return remove(id);
            items[id].expiry.textContent = 'Expires in ' + fmt(left);
        });
    }

    // ── Server calls (same endpoints and checks as before) ──

    function decide(id, action, buttons) {
        buttons.forEach(function (b) { b.disabled = true; });
        fetch(cfg.decideUrl.replace('__ID__', encodeURIComponent(id)).replace('__ACTION__', action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function () { remove(id); })
            .catch(function () { buttons.forEach(function (b) { b.disabled = false; }); });
    }

    function poll() {
        if (document.visibilityState !== 'visible' || Date.now() - lastInteraction > IDLE_MS) return;
        fetch(cfg.pendingUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Background-Poll': '1' } })
            .then(function (r) {
                if (r.status === 401) { clearInterval(timer); window.location.reload(); return null; }
                return r.ok ? r.json() : null;
            })
            .then(function (data) {
                if (!data || !Array.isArray(data.requests)) return;
                var seen = {};
                data.requests.forEach(function (req) {
                    seen[req.id] = true;
                    if (!items[req.id]) add(req);
                });
                Object.keys(items).forEach(function (id) { if (!seen[id]) remove(id); });
                tick();
            })
            .catch(function () { /* transient network error: retry next poll */ });
    }

    poll();
    timer = setInterval(poll, POLL_MS);
    setInterval(tick, 1000);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') poll(); });
})();
