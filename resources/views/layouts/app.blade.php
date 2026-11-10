<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bakery Management System')</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Instant Theme Loader -->
    <script>
        (function() {
            let savedTheme = localStorage.getItem('bakery_theme');
            if (!savedTheme) {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    savedTheme = 'dark';
                } else {
                    savedTheme = 'light';
                }
            } else if (savedTheme === 'glass') {
                savedTheme = 'dark';
                try { localStorage.setItem('bakery_theme', 'dark'); } catch(e) {}
            }
            document.documentElement.setAttribute('data-theme', savedTheme);
            const syncBody = () => {
                if (document.body) {
                    document.body.classList.toggle('dark-mode', savedTheme === 'dark');
                    document.body.classList.toggle('theme-dark', savedTheme === 'dark');
                }
            };
            if (document.body) {
                syncBody();
            } else {
                document.addEventListener('DOMContentLoaded', syncBody);
            }

            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                    if (!localStorage.getItem('bakery_theme')) {
                        const newTheme = e.matches ? 'dark' : 'light';
                        document.documentElement.setAttribute('data-theme', newTheme);
                        if (document.body) {
                            document.body.classList.toggle('dark-mode', newTheme === 'dark');
                            document.body.classList.toggle('theme-dark', newTheme === 'dark');
                        }
                    }
                });
            }
        })();
    </script>

    <!-- Instant Sidebar State Loader (Prevents flash / jump when navigating with collapsed sidebar) -->
    <script>
        (function() {
            try {
                if (window.innerWidth >= 1200 && localStorage.getItem('bakery_sidebar_state') === 'collapsed') {
                    document.documentElement.classList.add('sidebar-initially-collapsed');
                }
            } catch(e) {}
            document.addEventListener('DOMContentLoaded', function() {
                document.documentElement.classList.remove('sidebar-initially-collapsed');
            });
        })();
    </script>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="{{ asset('css/common.css') }}?v={{ @filemtime(public_path('css/common.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}?v={{ @filemtime(public_path('css/sidebar.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/theme-scenery.css') }}?v={{ @filemtime(public_path('css/theme-scenery.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/animations.css') }}?v={{ @filemtime(public_path('css/animations.css')) ?: '1.0' }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ @filemtime(public_path('css/responsive.css')) ?: '1.0' }}">
</head>
<body class="{{ (request()->is('pos*') || request()->is('cashier*')) ? 'is-pos-page' : ((request()->is('admin/products*') || request()->is('admin/categories*') || request()->is('admin/reports*') || request()->is('admin/settings*') || request()->is('admin/notifications*') || request()->is('*dashboard*')) ? 'is-catalog-page is-dashboard-page is-settings-page' : '') }} @yield('body_class')">
    <!-- Theme scenery: sun (light) / moon + falling stars (dark) -->
    <div class="theme-scenery" aria-hidden="true"><div class="sky-sun"></div><div class="sky-moon"></div><div class="sky-stars"></div><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span><span class="shooting-star"></span></div>

    <div class="bakery-app-layout">
        <!-- Sidebar Navigation -->
        <aside class="bakery-sidebar" id="sidebar_nav">
            <script>
                (function() {
                    try {
                        if (window.innerWidth >= 1200 && localStorage.getItem('bakery_sidebar_state') === 'collapsed') {
                            var sb = document.getElementById('sidebar_nav');
                            if (sb) sb.classList.add('sidebar-collapsed');
                            if (document.body) document.body.classList.add('sidebar-is-closed');
                        }
                    } catch(e) {}
                })();
            </script>
            <!-- Brand / Logo Area (Interactive toggle button) -->
            <button type="button" class="bakery-logo-area" id="sidebar_brand_toggle" title="Toggle Sidebar (Ctrl+B)" aria-label="Toggle Sidebar">
                <div class="bakery-logo-icon" id="sidebar_brand_icon" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 40 40" fill="none" id="sidebar_brand_logo_svg">
                        <path d="M10 26C6.7 26 4 23.3 4 20C4 17.2 6.1 14.8 8.8 14.2C8.5 13.5 8.3 12.8 8.3 12C8.3 7.6 11.9 4 16.3 4C19.7 4 22.6 6.1 23.8 9.2C24.8 8.5 26 8 27.3 8C31 8 34 11 34 14.7C34 15.2 33.9 15.7 33.7 16.2C35.6 17.3 36.8 19.4 36.8 21.8C36.8 25.2 34 28 30.5 28H10" stroke="#f97316" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 25L25 12" stroke="#f97316" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M16 19C15 17.5 16 16 17.5 16.5C18.5 17.5 18 19 16 19Z" fill="#f97316"/>
                        <path d="M19.5 15.5C18.5 14 19.5 12.5 21 13C22 14 21.5 15.5 19.5 15.5Z" fill="#f97316"/>
                        <path d="M23 12C22 10.5 23 9 24.5 9.5C25.5 10.5 25 12 23 12Z" fill="#f97316"/>
                        <path d="M17.5 22.5C16.5 21 17.5 19.5 19 20C20 21 19.5 22.5 17.5 22.5Z" fill="#f97316"/>
                        <path d="M21 19C20 17.5 21 16 22.5 16.5C23.5 17.5 23 19 21 19Z" fill="#f97316"/>
                    </svg>
                </div>
            </button>

            <!-- Navigation Links -->
            <nav class="sidebar-nav has-liquid-pill">
                <!-- Liquid Moving Pointer Pill -->
                <div class="nav-pointer-pill" id="nav_pointer_pill"></div>
                @php
                    $role = auth()->check() ? strtolower(auth()->user()->role) : 'admin';
                @endphp

                <!-- ==============================================
                     1. MAIN SECTION
                     ============================================== -->
                
                <!-- Dashboard (All Roles) -->
                @if($role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-nav-item {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                        <span data-lang-key="dashboard">Dashboard</span>
                    </a>
                @elseif($role === 'manager')
                    <a href="{{ route('manager.dashboard') }}" class="sidebar-nav-item {{ request()->is('manager/dashboard') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                        <span data-lang-key="dashboard">Dashboard</span>
                    </a>
                @elseif($role === 'baker')
                    <a href="{{ route('admin.production') }}" class="sidebar-nav-item {{ request()->is('admin/production*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" y1="17" x2="18" y2="17"/></svg>
                        <span data-lang-key="production">Production</span>
                    </a>
                @else
                    <a href="{{ route('cashier.dashboard') }}" class="sidebar-nav-item {{ (request()->is('cashier/dashboard') || request()->is('pos*')) ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span data-lang-key="pos">POS Cashier</span>
                    </a>
                @endif

                <!-- POS Terminal (Admin & Manager) -->
                @if(in_array($role, ['admin', 'manager']))
                    <a href="{{ route('pos') }}" class="sidebar-nav-item {{ request()->is('pos*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span data-lang-key="pos_terminal">POS Terminal</span>
                    </a>
                @endif

                <!-- Orders (Admin, Manager, Cashier) -->
                @if(in_array($role, ['admin', 'manager', 'cashier']))
                    <a href="{{ route('admin.orders') }}" class="sidebar-nav-item {{ request()->is('admin/orders*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span data-lang-key="orders">Orders</span>
                    </a>
                @endif

                <!-- ==============================================
                     2. MANAGEMENT SECTION
                     ============================================== -->
                
                <!-- Products Group -->
                @php
                    $productsOpen = request()->is('admin/products*') || request()->is('admin/categories*');
                @endphp
                <div class="sidebar-group {{ $productsOpen ? 'is-open' : '' }}" id="group_products">
                    <a href="{{ route('admin.products') }}" class="sidebar-nav-item {{ $productsOpen ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        <span data-lang-key="products">Products</span>
                        @if(in_array($role, ['admin', 'manager']))
                            <span class="sidebar-dropdown-btn" id="group_products_btn" role="button" tabindex="0" title="Toggle Products" aria-label="Toggle Products Submenu">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </span>
                        @endif
                    </a>
                    @if(in_array($role, ['admin', 'manager']))
                        <div class="sidebar-submenu">
                            <a href="{{ route('admin.products') }}" class="sidebar-submenu-item {{ request()->is('admin/products*') ? 'active' : '' }}">
                                <span class="submenu-dot"></span>
                                <span data-lang-key="products">Products</span>
                            </a>
                            <a href="{{ route('admin.categories') }}" class="sidebar-submenu-item {{ request()->is('admin/categories*') ? 'active' : '' }}">
                                <span class="submenu-dot"></span>
                                <span data-lang-key="categories">Categories</span>
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Inventory Group (Admin & Manager) -->
                @if(in_array($role, ['admin', 'manager']))
                    @php
                        $inventoryOpen = request()->is('admin/ingredients*') || request()->is('admin/recipes*') || request()->is('admin/production*') || request()->is('admin/suppliers*') || request()->is('admin/purchase-orders*');
                    @endphp
                    <div class="sidebar-group {{ $inventoryOpen ? 'is-open' : '' }}" id="group_inventory">
                        <a href="{{ route('admin.ingredients') }}" class="sidebar-nav-item {{ $inventoryOpen ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.97 12.92A2 2 0 0 0 2 14.63v3.24a2 2 0 0 0 .97 1.71l3 1.8a2 2 0 0 0 2.06 0L12 19v-5.5l-5-3-4.03 2.42Z"/><path d="m7 16.5-4.74-2.85"/><path d="m7 16.5 5-3"/><path d="M7 16.5v5.17"/><path d="M12 13.5V19l3.97 2.38a2 2 0 0 0 2.06 0l3-1.8a2 2 0 0 0 .97-1.71v-3.24a2 2 0 0 0-.97-1.71L17 10.5l-5 3Z"/><path d="m17 16.5-5-3"/><path d="m17 16.5 4.74-2.85"/><path d="M17 16.5v5.17"/><path d="M7.97 4.42A2 2 0 0 0 7 6.13v4.37l5 3 5-3V6.13a2 2 0 0 0-.97-1.71l-3-1.8a2 2 0 0 0-2.06 0l-3 1.8Z"/><path d="M12 8 7.26 5.15"/><path d="m12 8 4.74-2.85"/><path d="M12 13.5V8"/></svg>
                            <span data-lang-key="inventory">Inventory</span>
                            <span class="sidebar-dropdown-btn" id="group_inventory_btn" role="button" tabindex="0" title="Toggle Inventory" aria-label="Toggle Inventory Submenu">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </span>
                        </a>
                        <div class="sidebar-submenu">
                            <a href="{{ route('admin.ingredients') }}" class="sidebar-submenu-item {{ request()->is('admin/ingredients*') ? 'active' : '' }}">
                                <span class="submenu-dot"></span>
                                <span data-lang-key="ingredients">Ingredients</span>
                            </a>
                            <a href="{{ route('admin.recipes') }}" class="sidebar-submenu-item {{ request()->is('admin/recipes*') ? 'active' : '' }}">
                                <span class="submenu-dot"></span>
                                <span data-lang-key="recipes">Recipes</span>
                            </a>
                            <a href="{{ route('admin.production') }}" class="sidebar-submenu-item {{ request()->is('admin/production*') ? 'active' : '' }}">
                                <span class="submenu-dot"></span>
                                <span data-lang-key="production">Production</span>
                            </a>
                            <a href="{{ route('admin.suppliers') }}" class="sidebar-submenu-item {{ request()->is('admin/suppliers*') ? 'active' : '' }}">
                                <span class="submenu-dot"></span>
                                <span data-lang-key="suppliers">Suppliers</span>
                            </a>
                            <a href="{{ route('admin.purchase-orders.index') }}" class="sidebar-submenu-item {{ request()->is('admin/purchase-orders*') ? 'active' : '' }}">
                                <span class="submenu-dot"></span>
                                <span data-lang-key="purchase_orders">Purchase Orders</span>
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Customers (Admin & Manager) -->
                @if(in_array($role, ['admin', 'manager']))
                    <a href="{{ route('admin.customers.index') }}" class="sidebar-nav-item {{ request()->is('admin/customers*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span data-lang-key="customers">Customers</span>
                    </a>
                @endif

                <!-- Staff & Accounts (Admin Only) -->
                @if($role === 'admin')
                    <a href="{{ route('admin.users.index') }}" class="sidebar-nav-item {{ request()->is('admin/users*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="15" r="3"/><circle cx="9" cy="7" r="4"/><path d="M10 15H6a4 4 0 0 0-4 4v2"/><path d="m21.7 16.4-.9-.3"/><path d="m15.2 13.9-.9-.3"/><path d="m16.6 18.7.3-.9"/><path d="m19.1 12.2.3-.9"/><path d="m19.6 18.7-.4-1"/><path d="m16.8 12.3-.4-1"/><path d="m14.3 16.6 1-.4"/><path d="m20.7 13.8 1-.4"/></svg>
                        <span data-lang-key="users_staff">Staff & Accounts</span>
                    </a>
                @endif

                <!-- ==============================================
                     3. ANALYTICS SECTION (Admin & Manager)
                     ============================================== -->
                @if(in_array($role, ['admin', 'manager']))
                                        <a href="{{ route('admin.reports') }}" class="sidebar-nav-item {{ request()->is('admin/reports*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span data-lang-key="reports">Sales & Reports</span>
                    </a>
                @endif

                <!-- ==============================================
                     4. SYSTEM SECTION (Admin & Manager)
                     ============================================== -->
                @if(in_array($role, ['admin', 'manager']))
                                        <!-- Settings (Direct Single Item) -->
                    <a href="{{ route('admin.settings') }}" class="sidebar-nav-item {{ (request()->is('admin/settings*') || request()->is('admin/notifications*')) ? 'active' : '' }}" id="nav_settings">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span data-lang-key="settings">Settings</span>
                        @if(($realUnreadCount ?? 0) > 0)
                            <span class="sidebar-badge" style="margin-left: auto; background: #ef4444; color: #fff; font-size: 0.7rem; font-weight: 700; padding: 1px 6px; border-radius: 9999px;">{{ $realUnreadCount }}</span>
                        @endif
                    </a>
                @endif
            </nav>
            <script>
                (function() {
                    try {
                        var nav = document.querySelector('.sidebar-nav');
                        var active = nav ? nav.querySelector('.sidebar-nav-item.active') : null;
                        var pill = document.getElementById('nav_pointer_pill');
                        if (nav && active && pill) {
                            var navRect = nav.getBoundingClientRect();
                            var itemRect = active.getBoundingClientRect();
                            if (itemRect.height > 0) {
                                var isCollapsed = document.querySelector('.bakery-sidebar')?.classList.contains('sidebar-collapsed');
                                var y = itemRect.top - navRect.top + nav.scrollTop;
                                pill.style.setProperty('transition', 'none', 'important');
                                pill.style.transform = 'translate3d(0, ' + y + 'px, 0)';
                                if (isCollapsed) {
                                    pill.style.width = '44px';
                                    pill.style.height = '44px';
                                    pill.style.borderRadius = '50%';
                                    pill.style.left = 'calc(50% - 22px)';
                                } else {
                                    pill.style.width = '100%';
                                    pill.style.height = itemRect.height + 'px';
                                    pill.style.borderRadius = '9999px';
                                    pill.style.left = '0px';
                                }
                                pill.style.opacity = '1';
                            }
                        }
                    } catch(e) {}
                })();
            </script>
        </aside>

        <!-- Mobile & Tablet Drawer Backdrop -->
        <div id="sidebar_mobile_backdrop" class="sidebar-mobile-backdrop"></div>

        <!-- Main Content Area -->
        <div class="bakery-main-content">
            <!-- Header Bar -->
            <header class="top-header-bar">
                <div class="mobile-nav-toggle-wrap">
                    <button type="button" class="mobile-sidebar-toggle-btn" id="sidebar_toggle_btn" title="Toggle Navigation Drawer" aria-label="Toggle Navigation Drawer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <div class="mobile-header-brand" style="display: none;">
                        <span class="mobile-header-title">{{ \App\Models\Setting::get('shop_name', 'Moon Cake') }}</span>
                    </div>
                </div>

                @hasSection('header_toolbar')
                    <div class="header-toolbar-slot">
                        @yield('header_toolbar')
                    </div>
                @endif

                <div class="header-actions">
                    <!-- Language Switcher -->
                   

                    <div class="header-actions-primary">
                        <!-- Live Search Input -->
                        <div class="header-search-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input
                                type="text"
                                class="header-search-input"
                                id="{{ request()->is('pos*') || request()->is('cashier*') ? 'pos_search_input' : (request()->is('admin/orders*') ? 'order_search_input' : (request()->is('admin/categories*') ? 'category_search_input' : 'global_header_search')) }}"
                                placeholder="Search page items..."
                                data-lang-key="search_page_items"
                                autocomplete="off"
                                value="{{ request('search') }}"
                            >
                        </div>

                        <!-- Notification Bell & Popover -->
                        <div class="header-notification-wrapper">
                            <button class="icon-bell-btn" id="notification_bell_btn" type="button" aria-label="Notifications">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                                @if(($realUnreadCount ?? 0) > 0)
                                    <span class="bell-badge" id="notification_badge">{{ $realUnreadCount }}</span>
                                @endif
                            </button>

                            <div class="notification-dropdown" id="notification_dropdown">
                                <div class="notification-header">
                                    <div class="notification-title-group">
                                        <span class="notification-heading">Notifications</span>
                                        <span class="notification-count-badge" id="notification_count_tag">{{ $realUnreadCount ?? 0 }} New</span>
                                    </div>
                                    <button type="button" class="btn-mark-all-read" id="btn_mark_all_read">Mark all as read</button>
                                </div>

                                <div class="notification-list" id="notification_list">
                                    @forelse(($realNotifications ?? []) as $notif)
                                        @php
                                            $isUnread = !$notif->isRead();
                                            $sev = $notif->severity ?? 'info';
                                        @endphp
                                        <a href="{{ $notif->action_url ?: 'javascript:void(0)' }}" class="notification-item {{ $isUnread ? 'unread' : '' }}" data-id="{{ $notif->id }}" data-read="{{ $isUnread ? '0' : '1' }}">
                                            <div class="notification-icon-box {{ $sev }}">
                                                @if($sev === 'danger')
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 1 2 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                                @elseif($sev === 'warning')
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                @elseif($sev === 'success')
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                                @endif
                                            </div>
                                            <div class="notification-content">
                                                <div class="notification-item-title">{{ $notif->title }}</div>
                                                <div class="notification-item-desc">{{ $notif->message }}</div>
                                                <span class="notification-time">{{ $notif->time_ago }}</span>
                                            </div>
                                            @if($isUnread)
                                                <span class="unread-dot"></span>
                                            @endif
                                        </a>
                                    @empty
                                        <div style="text-align: center; color: #a1a1aa; padding: 2rem; font-size: 0.825rem;" data-lang-key="no_notifications">
                                            No new notifications at this time!
                                        </div>
                                    @endforelse
                                </div>

                                <div class="notification-footer">
                                    <a href="{{ route('admin.notifications') }}" class="btn-notification-all" data-lang-key="view_all_notifications">View All Notifications &rarr;</a>
                                </div>
                            </div>
                        </div>

                        <!-- User Profile Pill & Dropdown -->
                        <div class="user-profile-wrapper">
                        @php
                            $headerAvatar = null;
                            if (auth()->check()) {
                                $headerAvatar = \App\Services\SettingsService::getString('user_avatar_' . auth()->id(), '')
                                    ?: \App\Services\SettingsService::getString('admin_avatar', '')
                                    ?: \App\Services\SettingsService::getString('shop_logo', '');
                            }
                        @endphp
                        <div class="user-profile-pill user-profile-circle" id="user_profile_pill" role="button" tabindex="0" title="{{ auth()->check() ? auth()->user()->name . ' (' . ucfirst(auth()->user()->role) . ')' : 'User Account Menu' }}" aria-label="User Account Menu">
                            @if(!empty($headerAvatar))
                                <img src="{{ asset($headerAvatar) }}" alt="{{ auth()->check() ? auth()->user()->name : 'Avatar' }}" class="user-avatar-circle-img" id="header_user_avatar_img">
                            @else
                                <div class="user-avatar-img" id="header_user_avatar_placeholder">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </div>
                            @endif
                        </div>

                        <div class="user-dropdown-menu profile-menu-card" id="user_dropdown_menu">
                            <a href="{{ in_array(auth()->check() ? strtolower(auth()->user()->role) : 'admin', ['admin', 'manager']) ? route('admin.settings') . '#profile_info' : route('profile') }}" class="profile-menu-item" id="nav_my_profile">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z"/></svg>
                                <span>Profile</span>
                            </a>

                            @if(in_array(auth()->check() ? strtolower(auth()->user()->role) : 'admin', ['admin', 'manager']))
                                <a href="{{ route('admin.settings') }}#appearance" class="profile-menu-item">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5l8.25 4.75v9.5L12 21.5l-8.25-4.75v-9.5z"/><circle cx="12" cy="12" r="3.25"/></svg>
                                    <span>Theme &amp; Language</span>
                                </a>
                            @endif

                            <div class="profile-menu-divider"></div>

                            <a href="{{ route('logout') }}" class="profile-menu-item">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"/><path d="M10 12h10"/><path d="M16 8l4 4-4 4"/></svg>
                                <span>Sign out</span>
                            </a>
                        </div>
                    </div>
                </div>

                @hasSection('header_sub_action')
                    <div class="header-sub-action-wrap">
                        @yield('header_sub_action')
                    </div>
                @endif
            </div>
        </header>

            <!-- Page Specific Content -->
            <main id="main_content" class="main-content-body">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Logout Confirmation Modal Card -->
    <div id="logout_confirm_modal" class="logout-modal-backdrop" aria-hidden="true" inert role="dialog" aria-modal="true" aria-labelledby="logout_modal_title">
        <div class="logout-modal-card">
            <div class="logout-modal-icon-chamber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </div>
            
            <h3 id="logout_modal_title" class="logout-modal-title">Ready to Sign Out?</h3>
            <p class="logout-modal-message">
                Are you sure you want to log out of your session? You will need to log in again to access the bakery management system.
            </p>

            <div class="logout-modal-actions">
                <button type="button" id="btn_cancel_logout" class="logout-modal-btn cancel-btn">
                    <span>Stay Logged In</span>
                </button>
                <a href="{{ route('logout') }}" id="btn_confirm_logout" class="logout-modal-btn confirm-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span>Yes, Log Out</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Global Action / Deletion Confirmation Modal Card -->
    <div id="global_confirm_modal" class="confirm-modal-backdrop" aria-hidden="true" inert role="dialog" aria-modal="true" aria-labelledby="global_confirm_title">
        <div class="confirm-modal-card">
            <div class="confirm-modal-icon-chamber danger" id="global_confirm_icon_chamber">
                <svg id="global_confirm_icon_action" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="16 10 11 15 8 12"></polyline>
                </svg>
                <svg id="global_confirm_icon_cancel" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <svg id="global_confirm_icon_trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
                <svg id="global_confirm_icon_warn" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <svg id="global_confirm_icon_info" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            
            <h3 id="global_confirm_title" class="confirm-modal-title">Confirm Deletion</h3>
            <p id="global_confirm_message" class="confirm-modal-message">
                Are you sure you want to delete this item? This action cannot be undone.
            </p>

            <div class="confirm-modal-actions">
                <button type="button" id="btn_cancel_global_confirm" class="confirm-modal-btn cancel-btn">
                    <span id="global_confirm_cancel_text">Cancel</span>
                </button>
                <button type="button" id="btn_proceed_global_confirm" class="confirm-modal-btn confirm-btn danger">
                    <svg id="global_confirm_btn_icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span id="global_confirm_proceed_text">Yes, Delete</span>
                </button>
            </div>
        </div>
    </div>

    @stack('scripts')
    <script src="{{ asset('js/sidebar.js') }}?v={{ @filemtime(public_path('js/sidebar.js')) ?: '1.0' }}"></script>
    <script src="{{ asset('js/custom-select.js') }}?v={{ @filemtime(public_path('js/custom-select.js')) ?: '1.0' }}"></script>
    <script src="{{ asset('js/lang.js') }}?v={{ @filemtime(public_path('js/lang.js')) ?: '1.0' }}"></script>
    <script>
        window.APP_CONFIG = {
            notificationsReadAllUrl: @json(route('notifications.readAll')),
        };
    </script>
    <script src="{{ asset('js/app-layout.js') }}?v={{ @filemtime(public_path('js/app-layout.js')) ?: '1.0' }}"></script>
</body>
</html>
