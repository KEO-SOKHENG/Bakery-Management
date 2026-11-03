        document.addEventListener('DOMContentLoaded', function() {
            // Clean up initial anti-flash class once DOM is parsed and script takes over
            document.documentElement.classList.remove('sidebar-initially-collapsed');
            document.documentElement.classList.remove('sidebar-initially-closed');

            // Sidebar synchronization helper (waits for CSS transition to complete smoothly)
            function smoothPillSync(delay = 320) {
                setTimeout(() => {
                    if (typeof window.sidebarPillRefresh === 'function') {
                        window.sidebarPillRefresh(false);
                    } else {
                        window.dispatchEvent(new Event('resize'));
                    }
                }, delay);
            }
            window.smoothPillSync = smoothPillSync;

            // =========================================================
            // Sidebar Open, Close & Toggle Controllers (Desktop, Tablet, Mobile)
            // =========================================================
            const headerToggleBtn = document.getElementById('sidebar_toggle_btn') || document.getElementById('header_sidebar_toggle_btn');
            const sidebar = document.querySelector('.bakery-sidebar');
            const mobileBackdrop = document.getElementById('sidebar_mobile_backdrop');

            function isDesktop() {
                return window.innerWidth >= 1200;
            }

            function isTablet() {
                return window.innerWidth >= 768 && window.innerWidth < 1200;
            }

            function isMobile() {
                return window.innerWidth < 768;
            }

            function openMobileDrawer() {
                if (sidebar) sidebar.classList.add('sidebar-expanded');
                if (mobileBackdrop) mobileBackdrop.classList.add('is-active');
                if (headerToggleBtn) headerToggleBtn.classList.add('is-closed-state');
            }

            function closeMobileDrawer() {
                if (sidebar) sidebar.classList.remove('sidebar-expanded');
                if (mobileBackdrop) mobileBackdrop.classList.remove('is-active');
                if (headerToggleBtn) headerToggleBtn.classList.remove('is-closed-state');
            }

            const sidebarHeaderToggleBtn = document.getElementById('sidebar_brand_toggle') || document.querySelector('.bakery-logo-area') || document.querySelector('.sidebar-header-toggle-btn') || document.getElementById('sidebar_close_btn');

            function updateSidebarHeaderToggleBtn() {
                const btn = document.getElementById('sidebar_brand_toggle') || document.querySelector('.bakery-logo-area') || document.querySelector('.sidebar-header-toggle-btn') || document.getElementById('sidebar_close_btn');
                if (!btn) return;
                const isCollapsed = isDesktop() 
                    ? (sidebar && (sidebar.classList.contains('sidebar-collapsed') || document.body.classList.contains('sidebar-is-closed')))
                    : (sidebar && !sidebar.classList.contains('sidebar-expanded'));

                if (isCollapsed) {
                    btn.setAttribute('title', 'Expand Sidebar (Ctrl+B)');
                    btn.setAttribute('aria-label', 'Expand Sidebar');
                    btn.classList.add('is-collapsed-state');
                } else {
                    btn.setAttribute('title', 'Collapse Sidebar (Ctrl+B)');
                    btn.setAttribute('aria-label', 'Collapse Sidebar');
                    btn.classList.remove('is-collapsed-state');
                }
            }

            function openSidebar() {
                document.documentElement.classList.remove('sidebar-initially-collapsed');
                document.documentElement.classList.remove('sidebar-initially-closed');
                if (isDesktop()) {
                    if (sidebar) sidebar.classList.remove('sidebar-collapsed');
                    document.body.classList.remove('sidebar-is-closed');
                    localStorage.setItem('bakery_sidebar_state', 'expanded');
                    if (headerToggleBtn) {
                        headerToggleBtn.classList.remove('is-closed-state');
                        headerToggleBtn.setAttribute('title', 'Collapse Sidebar (Ctrl+B)');
                    }
                } else {
                    openMobileDrawer();
                }
                updateSidebarHeaderToggleBtn();
                smoothPillSync(10);
                smoothPillSync(320);
            }

            function closeSidebar() {
                document.documentElement.classList.remove('sidebar-initially-collapsed');
                document.documentElement.classList.remove('sidebar-initially-closed');
                if (isDesktop()) {
                    if (sidebar) sidebar.classList.add('sidebar-collapsed');
                    document.body.classList.add('sidebar-is-closed');
                    localStorage.setItem('bakery_sidebar_state', 'collapsed');
                    if (headerToggleBtn) {
                        headerToggleBtn.classList.add('is-closed-state');
                        headerToggleBtn.setAttribute('title', 'Expand Sidebar (Ctrl+B)');
                    }
                } else {
                    closeMobileDrawer();
                }
                updateSidebarHeaderToggleBtn();
                smoothPillSync(10);
                smoothPillSync(320);
            }

            function toggleSidebar() {
                if (!sidebar) return;
                if (isDesktop()) {
                    const isCurrentlyCollapsed = sidebar.classList.contains('sidebar-collapsed') || document.body.classList.contains('sidebar-is-closed');
                    if (isCurrentlyCollapsed) {
                        openSidebar();
                    } else {
                        closeSidebar();
                    }
                } else {
                    const isCurrentlyOpen = sidebar.classList.contains('sidebar-expanded');
                    if (isCurrentlyOpen) {
                        closeMobileDrawer();
                    } else {
                        openMobileDrawer();
                    }
                }
                updateSidebarHeaderToggleBtn();
                smoothPillSync();
            }

            // Expose globally
            window.openSidebar = openSidebar;
            window.closeSidebar = closeSidebar;
            window.toggleSidebar = toggleSidebar;
            window.updateSidebarHeaderToggleBtn = updateSidebarHeaderToggleBtn;

            if (headerToggleBtn) {
                headerToggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            const brandToggle = document.getElementById('sidebar_brand_toggle') || document.querySelector('.bakery-logo-area');
            if (brandToggle) {
                brandToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (sidebarHeaderToggleBtn && sidebarHeaderToggleBtn !== brandToggle) {
                sidebarHeaderToggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (mobileBackdrop) {
                mobileBackdrop.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
                mobileBackdrop.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            document.addEventListener('click', function(e) {
                if (!isDesktop() && sidebar && sidebar.classList.contains('sidebar-expanded')) {
                    if (!sidebar.contains(e.target) && (!headerToggleBtn || !headerToggleBtn.contains(e.target))) {
                        closeMobileDrawer();
                    }
                }
            });

            // Keyboard shortcut support: Ctrl+B or Cmd+B to toggle sidebar, Escape to close drawer
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
                    e.preventDefault();
                    toggleSidebar();
                } else if (e.key === 'Escape') {
                    if (!isDesktop() && sidebar && sidebar.classList.contains('sidebar-expanded')) {
                        closeMobileDrawer();
                    }
                }
            });

            // Initialize sidebar state on page load
            if (isDesktop()) {
                const savedState = localStorage.getItem('bakery_sidebar_state');
                if (savedState === 'collapsed') {
                    if (sidebar) sidebar.classList.add('sidebar-collapsed');
                    document.body.classList.add('sidebar-is-closed');
                    if (headerToggleBtn) {
                        headerToggleBtn.classList.add('is-closed-state');
                        headerToggleBtn.setAttribute('title', 'Expand Sidebar (Ctrl+B)');
                    }
                } else {
                    if (sidebar) sidebar.classList.remove('sidebar-collapsed');
                    document.body.classList.remove('sidebar-is-closed');
                    if (headerToggleBtn) {
                        headerToggleBtn.classList.remove('is-closed-state');
                        headerToggleBtn.setAttribute('title', 'Collapse Sidebar (Ctrl+B)');
                    }
                }
                if (mobileBackdrop) mobileBackdrop.classList.remove('is-active');
            } else {
                closeMobileDrawer();
            }
            updateSidebarHeaderToggleBtn();

            // Adjust on resize between desktop, tablet, and mobile
            window.addEventListener('resize', function() {
                if (isDesktop()) {
                    closeMobileDrawer();
                    const savedState = localStorage.getItem('bakery_sidebar_state');
                    if (savedState === 'collapsed') {
                        if (sidebar) sidebar.classList.add('sidebar-collapsed');
                        document.body.classList.add('sidebar-is-closed');
                    } else {
                        if (sidebar) sidebar.classList.remove('sidebar-collapsed');
                        document.body.classList.remove('sidebar-is-closed');
                    }
                } else {
                    if (sidebar) {
                        sidebar.classList.remove('sidebar-collapsed');
                        document.body.classList.remove('sidebar-is-closed');
                    }
                }
            });

            // User Profile Pill & Notification Elements
            const userPill = document.getElementById('user_profile_pill');
            const userMenu = document.getElementById('user_dropdown_menu');
            const bellBtn = document.getElementById('notification_bell_btn');
            const notificationDropdown = document.getElementById('notification_dropdown');
            const markAllReadBtn = document.getElementById('btn_mark_all_read');
            const notificationBadge = document.getElementById('notification_badge');
            const notificationCountTag = document.getElementById('notification_count_tag');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (bellBtn && notificationDropdown) {
                bellBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (userMenu) userMenu.classList.remove('active');
                    notificationDropdown.classList.toggle('active');
                });
            }

            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    fetch(window.APP_CONFIG?.notificationsReadAllUrl || '/notifications/read-all', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                            'Accept': 'application/json'
                        }
                    }).then(res => res.json()).then(data => {
                        document.querySelectorAll('.notification-item').forEach(item => {
                            item.classList.remove('unread');
                            item.dataset.read = '1';
                            const dot = item.querySelector('.unread-dot');
                            if (dot) dot.remove();
                        });
                        if (notificationBadge) {
                            notificationBadge.style.display = 'none';
                            notificationBadge.textContent = '0';
                        }
                        if (notificationCountTag) {
                            notificationCountTag.textContent = '0 New';
                            notificationCountTag.style.backgroundColor = '#e4e4e7';
                            notificationCountTag.style.color = '#71717a';
                        }
                    }).catch(err => console.error('Failed to mark all as read:', err));
                });
            }

            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', function() {
                    const notifId = this.dataset.id;
                    const isUnread = this.dataset.read === '0' || this.classList.contains('unread');
                    if (isUnread && notifId) {
                        this.classList.remove('unread');
                        this.dataset.read = '1';
                        const dot = this.querySelector('.unread-dot');
                        if (dot) dot.remove();

                        fetch(`/notifications/${notifId}/read`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                                'Accept': 'application/json'
                            }
                        }).then(res => res.json()).then(data => {
                            if (notificationBadge) {
                                const remaining = data.unread_count ?? document.querySelectorAll('.notification-item.unread').length;
                                if (remaining > 0) {
                                    notificationBadge.textContent = remaining;
                                    notificationBadge.style.display = 'inline-flex';
                                } else {
                                    notificationBadge.style.display = 'none';
                                }
                                if (notificationCountTag) {
                                    notificationCountTag.textContent = `${remaining} New`;
                                }
                            }
                        }).catch(err => console.error('Failed to mark notification read:', err));
                    }
                });
            });

            // User Profile Pill Toggle
            if (userPill && userMenu) {
                userPill.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (notificationDropdown) notificationDropdown.classList.remove('active');
                    userMenu.classList.toggle('active');
                });
            }

            // Close dropdowns on outside click
            document.addEventListener('click', function(e) {
                if (notificationDropdown && !notificationDropdown.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
                    notificationDropdown.classList.remove('active');
                }
                if (userMenu && !userMenu.contains(e.target) && (!userPill || !userPill.contains(e.target))) {
                    userMenu.classList.remove('active');
                }
            });

            // Header Global Search Filter for Page Tables & Lists
            const searchInput = document.getElementById('global_header_search') || document.getElementById('order_search_input') || document.getElementById('category_search_input');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    const tableRows = document.querySelectorAll('table tbody tr');
                    const stockItems = document.querySelectorAll('.stock-alert-item');
                    const productCards = document.querySelectorAll('.pos-product-card, .product-card');
                    const categoryCards = document.querySelectorAll('.category-card');

                    tableRows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(query) ? '' : 'none';
                    });

                    stockItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(query) ? '' : 'none';
                    });

                    productCards.forEach(card => {
                        const text = card.textContent.toLowerCase();
                        card.style.display = text.includes(query) ? '' : 'none';
                    });

                    categoryCards.forEach(card => {
                        const text = card.textContent.toLowerCase();
                        card.style.display = text.includes(query) ? '' : 'none';
                    });
                });
            }

            // Dark Mode Firefly Particle Generator (Dense Swarm)
            const fireflyContainer = document.getElementById('fireflies_layer');
            if (fireflyContainer) {
                const count = 120;
                for (let i = 0; i < count; i++) {
                    const particle = document.createElement('div');
                    particle.className = 'firefly-particle';
                    
                    const size = Math.floor(Math.random() * 3) + 2;
                    const left = Math.random() * 100;
                    const top = Math.random() * 100;
                    const duration = (Math.random() * 10 + 10).toFixed(1);
                    const floatDelay = (Math.random() * 8).toFixed(1);
                    const pulseDelay = (Math.random() * 4).toFixed(1);
                    
                    particle.style.width = `${size}px`;
                    particle.style.height = `${size}px`;
                    particle.style.left = `${left}%`;
                    particle.style.top = `${top}%`;
                    particle.style.animationDuration = `${duration}s, 3s`;
                    particle.style.animationDelay = `${floatDelay}s, ${pulseDelay}s`;
                    
                    fireflyContainer.appendChild(particle);
                }
            }

            // Sidebar Submenu Dropdown / Dropup Toggle with Auto-Close
            function closeAllSidebarDropdowns(exceptGroup = null) {
                let changed = false;
                document.querySelectorAll('.sidebar-group.is-open').forEach(grp => {
                    if (grp !== exceptGroup) {
                        grp.classList.remove('is-open');
                        changed = true;
                    }
                });
                if (changed) {
                    smoothPillSync();
                }
            }

            // Chevron Button: Dedicated Submenu Accordion Toggle without page navigation
            document.querySelectorAll('.sidebar-dropdown-btn').forEach(btn => {
                btn.addEventListener('pointerdown', function(e) {
                    e.stopPropagation();
                });

                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const group = this.closest('.sidebar-group');
                    if (!group) return;

                    const sidebar = document.querySelector('.bakery-sidebar');
                    const isCollapsed = sidebar && (sidebar.classList.contains('sidebar-collapsed') || document.body.classList.contains('sidebar-is-closed'));
                    if (isCollapsed) {
                        if (typeof window.openSidebar === 'function') {
                            window.openSidebar();
                        }
                        group.classList.add('is-open');
                        smoothPillSync();
                        return;
                    }

                    const isCurrentlyOpen = group.classList.contains('is-open');
                    closeAllSidebarDropdowns(group);

                    if (isCurrentlyOpen) {
                        group.classList.remove('is-open');
                    } else {
                        group.classList.add('is-open');
                    }

                    smoothPillSync();
                    if (typeof window.sidebarPillRefresh === 'function') {
                        setTimeout(() => window.sidebarPillRefresh(false), 50);
                        setTimeout(() => window.sidebarPillRefresh(false), 150);
                        setTimeout(() => window.sidebarPillRefresh(false), 260);
                        setTimeout(() => window.sidebarPillRefresh(true), 380);
                    }
                });

                btn.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        e.stopPropagation();
                        this.click();
                    }
                });
            });

            // Parent Navigation Links (.sidebar-group > .sidebar-nav-item)
            document.querySelectorAll('.sidebar-group > .sidebar-nav-item').forEach(navItem => {
                navItem.addEventListener('click', function(e) {
                    // If user clicked the chevron button, let the dedicated chevron handler handle it
                    if (e.target.closest('.sidebar-dropdown-btn')) {
                        return;
                    }

                    const group = this.closest('.sidebar-group');
                    const href = this.getAttribute('href');
                    const currentUrl = window.location.href.split('?')[0].replace(/\/+$/, '');
                    const targetUrl = href ? new URL(href, window.location.origin).href.split('?')[0].replace(/\/+$/, '') : '';
                    const isCurrentPage = Boolean(targetUrl && currentUrl === targetUrl);

                    // If already on this page, toggle the accordion open/close instead of redundant reload
                    if (isCurrentPage) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (group) {
                            const isCurrentlyOpen = group.classList.contains('is-open');
                            closeAllSidebarDropdowns(group);
                            if (isCurrentlyOpen) {
                                group.classList.remove('is-open');
                            } else {
                                group.classList.add('is-open');
                            }
                            smoothPillSync();
                            if (typeof window.sidebarPillRefresh === 'function') {
                                setTimeout(() => window.sidebarPillRefresh(false), 50);
                                setTimeout(() => window.sidebarPillRefresh(false), 150);
                                setTimeout(() => window.sidebarPillRefresh(true), 350);
                            }
                        }
                    } else {
                        // User is on another page (e.g. Dashboard): allow standard browser navigation to href!
                        if (group) {
                            group.classList.add('is-open');
                        }
                    }
                });
            });

            // Auto-close inactive dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                const clickedGroup = e.target.closest('.sidebar-group');
                document.querySelectorAll('.sidebar-group.is-open').forEach(grp => {
                    if (grp !== clickedGroup && !grp.querySelector('.sidebar-submenu-item.active') && !grp.querySelector('.sidebar-nav-item.active')) {
                        grp.classList.remove('is-open');
                        smoothPillSync();
                    }
                });
            });

            // =========================================================
            // Logout Confirmation Card Interceptor
            // =========================================================
            const logoutModal = document.getElementById('logout_confirm_modal');
            const cancelLogoutBtn = document.getElementById('btn_cancel_logout');
            let logoutTriggerEl = null;

            function openLogoutModal(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    logoutTriggerEl = e.currentTarget || document.activeElement;
                } else {
                    logoutTriggerEl = document.activeElement;
                }
                if (logoutModal) {
                    logoutModal.classList.add('is-active');
                    logoutModal.removeAttribute('aria-hidden');
                    logoutModal.removeAttribute('inert');
                    if (cancelLogoutBtn) {
                        cancelLogoutBtn.focus();
                    }
                }
            }

            function closeLogoutModal() {
                if (logoutModal) {
                    logoutModal.classList.remove('is-active');
                    logoutModal.setAttribute('aria-hidden', 'true');
                    logoutModal.setAttribute('inert', '');
                    if (logoutTriggerEl && typeof logoutTriggerEl.focus === 'function') {
                        try {
                            logoutTriggerEl.focus();
                        } catch (err) {
                            // Ignore focus errors
                        }
                        logoutTriggerEl = null;
                    }
                }
            }

            // Intercept both sidebar logout button and user dropdown sign out link
            document.querySelectorAll('.logout-btn, a[href*="/logout"]').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (this.id === 'btn_confirm_logout') return; // Allow confirmed action to proceed
                    openLogoutModal(e);
                });
            });

            if (cancelLogoutBtn) {
                cancelLogoutBtn.addEventListener('click', closeLogoutModal);
            }

            if (logoutModal) {
                logoutModal.addEventListener('click', function(e) {
                    if (e.target === logoutModal) {
                        closeLogoutModal();
                    }
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && logoutModal && logoutModal.classList.contains('is-active')) {
                    closeLogoutModal();
                }
            });

            // =========================================================
            // Dynamic Sticky Header Height Synchronizer
            // =========================================================
            const updateAppHeaderHeight = () => {
                const header = document.querySelector('.top-header-bar');
                if (header) {
                    const height = header.offsetHeight;
                    if (height > 0) {
                        document.documentElement.style.setProperty('--app-header-height', `${height}px`);
                    }
                }
            };
            updateAppHeaderHeight();
            window.addEventListener('resize', updateAppHeaderHeight, { passive: true });
            const topHeaderEl = document.querySelector('.top-header-bar');
            if (topHeaderEl && window.ResizeObserver) {
                new ResizeObserver(updateAppHeaderHeight).observe(topHeaderEl);
            }
        });
