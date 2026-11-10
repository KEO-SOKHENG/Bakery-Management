// ── Custom searchable dropdown for filter pills ──
// Upgrades native <select> elements inside .supplier-filter-pill-card to the same
// styled dropdown used on the Ingredients page ("All Suppliers"). The native select
// stays in the DOM (hidden) so form submission and onchange handlers keep working.

(function () {
    'use strict';

    const CHECK_SVG = '<svg class="item-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    const SEARCH_SVG = '<svg class="custom-supplier-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
    const CHEVRON_SVG = '<svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>';

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function placeholderFor(select) {
        const first = select.options[0] ? select.options[0].textContent.trim() : '';
        const noun = first.replace(/^all\s+/i, '').toLowerCase();
        return noun ? 'Search ' + noun + '...' : 'Search...';
    }

    function enhance(select) {
        if (select.dataset.customSelect === '1') return;
        // Opt-in field mode: <select data-styled-select> becomes a full-width styled dropdown
        const fieldMode = select.hasAttribute('data-styled-select');
        const card = select.closest('.supplier-filter-pill-card');
        if (!fieldMode && (!card || card.querySelector('.custom-supplier-dropdown:not([data-generated])'))) return;
        if (select.style.display === 'none') return;
        select.dataset.customSelect = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'custom-supplier-dropdown' + (fieldMode ? ' is-field' : '');
        wrapper.setAttribute('data-generated', '1');

        const selected = select.options[select.selectedIndex];
        const options = Array.prototype.slice.call(select.options);

        wrapper.innerHTML =
            '<button type="button" class="custom-supplier-trigger" aria-haspopup="true" aria-expanded="false">' +
                '<span class="custom-supplier-label">' + escapeHtml(selected ? selected.textContent.trim() : '') + '</span>' +
                CHEVRON_SVG +
            '</button>' +
            '<div class="custom-supplier-menu">' +
                (fieldMode ? '' :
                '<div class="custom-supplier-search-wrap">' + SEARCH_SVG +
                    '<input type="text" class="custom-supplier-search-input" placeholder="' + escapeHtml(placeholderFor(select)) + '" autocomplete="off">' +
                '</div>') +
                '<ul class="custom-supplier-list">' +
                    options.map(function (o) {
                        const active = o.selected;
                        return '<li class="custom-supplier-item' + (active ? ' is-active' : '') + '" data-value="' + escapeHtml(o.value) + '">' +
                            '<span>' + escapeHtml(o.textContent.trim()) + '</span>' + (active ? CHECK_SVG : '') + '</li>';
                    }).join('') +
                '</ul>' +
            '</div>';

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.style.display = 'none';

        const trigger = wrapper.querySelector('.custom-supplier-trigger');
        const label = wrapper.querySelector('.custom-supplier-label');
        // Field mode has no search box: use a harmless stand-in so the shared code below keeps working
        const search = wrapper.querySelector('.custom-supplier-search-input') || document.createElement('input');
        const items = Array.prototype.slice.call(wrapper.querySelectorAll('.custom-supplier-item'));

        // Keep the label in step when the value is changed from code (e.g. language applied on load)
        select.addEventListener('bk:sync', function () {
            const opt = select.options[select.selectedIndex];
            label.textContent = opt ? opt.textContent.trim() : '';
            items.forEach(function (it) {
                const on = it.getAttribute('data-value') === select.value;
                it.classList.toggle('is-active', on);
                const c = it.querySelector('.item-check');
                if (c) c.remove();
                if (on) it.insertAdjacentHTML('beforeend', CHECK_SVG);
            });
        });

        function filter(q) {
            q = q.toLowerCase().trim();
            items.forEach(function (it) {
                it.style.display = (!q || it.textContent.toLowerCase().indexOf(q) !== -1) ? 'flex' : 'none';
            });
        }

        function toggle(open) {
            const isOpen = open !== undefined ? open : !wrapper.classList.contains('is-open');
            if (isOpen) {
                document.querySelectorAll('.custom-supplier-dropdown.is-open').forEach(function (d) {
                    if (d !== wrapper) d.classList.remove('is-open');
                });
                wrapper.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                search.value = '';
                filter('');
                setTimeout(function () { search.focus(); }, 50);
            } else {
                wrapper.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        }

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggle();
        });

        search.addEventListener('input', function () { filter(this.value); });
        search.addEventListener('click', function (e) { e.stopPropagation(); });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') toggle(false);
        });

        items.forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.stopPropagation();
                const value = this.getAttribute('data-value');
                label.textContent = this.querySelector('span').textContent.trim();
                items.forEach(function (it) {
                    it.classList.remove('is-active');
                    const c = it.querySelector('.item-check');
                    if (c) c.remove();
                });
                this.classList.add('is-active');
                this.insertAdjacentHTML('beforeend', CHECK_SVG);
                toggle(false);
                if (select.value !== value) {
                    select.value = value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) toggle(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && wrapper.classList.contains('is-open')) toggle(false);
        });
    }

    function init() {
        document.querySelectorAll('.supplier-filter-pill-card select, select[data-styled-select]').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
