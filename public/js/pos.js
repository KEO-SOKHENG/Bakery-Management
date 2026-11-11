(function() {
    'use strict';

    // Configuration injected via window.POS_CONFIG
    const config = window.POS_CONFIG || {};
    const TAX_PERCENTAGE = typeof config.taxPercentage !== 'undefined' ? Number(config.taxPercentage) : 10.0;
    const CURRENCY = config.currency || '$';
    const CHECKOUT_URL = config.checkoutUrl || '/pos/checkout';
    const CUSTOMER_SEARCH_URL = config.customerSearchUrl || '/pos/customers/search';
    const CUSTOMER_QUICK_STORE_URL = config.customerQuickStoreUrl || '/pos/customers/quick-store';
    const CSRF_TOKEN = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let currentGrandTotal = 0;

    let cart = []; // Array of { id, name, price, stock, qty }
    let selectedPaymentMethod = 'cash';
    let selectedCustomer = null;

    // DOM Elements
    const searchInput = document.getElementById('pos_search_input') || document.getElementById('global_header_search');
    const categoryTabs = document.getElementById('pos_category_tabs');
    const productGrid = document.getElementById('pos_product_grid');
    const cartContainer = document.getElementById('pos_cart_items_container');
    const cartEmptyState = document.getElementById('pos_cart_empty_state');
    const cartCountPill = document.getElementById('pos_cart_count_pill');
    const clearCartBtn = document.getElementById('btn_clear_cart');
    const customerInput = document.getElementById('pos_customer_name');
    const customerIdInput = document.getElementById('pos_customer_id');
    const customerSearchInput = document.getElementById('pos_customer_search');
    const customerDropdown = document.getElementById('pos_cust_dropdown');
    const customerSearchContainer = document.getElementById('pos_cust_search_container');
    const selectedCustCard = document.getElementById('pos_selected_cust_card');
    const custInitials = document.getElementById('pos_cust_initials');
    const custSelectedName = document.getElementById('pos_cust_selected_name');
    const custSelectedPhone = document.getElementById('pos_cust_selected_phone');
    const custSelectedTier = document.getElementById('pos_cust_selected_tier');
    const btnRemoveCust = document.getElementById('btn_remove_selected_cust');

    // Quick Add Modal Elements
    const quickCustModal = document.getElementById('pos_quick_customer_modal');
    const btnOpenQuickCust = document.getElementById('btn_open_quick_cust_modal');
    const btnCloseQuickCust = document.getElementById('btn_close_quick_cust_modal');
    const btnCancelQuickCust = document.getElementById('btn_cancel_quick_cust');
    const formQuickCustomer = document.getElementById('form_quick_customer');
    const quickCustName = document.getElementById('quick_cust_name');
    const quickCustPhone = document.getElementById('quick_cust_phone');
    const quickCustEmail = document.getElementById('quick_cust_email');

    const paymentSelector = document.getElementById('pos_payment_selector');
    const discountInput = document.getElementById('pos_discount_input');
    const subtotalDisplay = document.getElementById('pos_subtotal_display');
    const taxDisplay = document.getElementById('pos_tax_display');
    const grandTotalDisplay = document.getElementById('pos_grand_total_display');
    const completeSaleBtn = document.getElementById('btn_complete_sale');
    const completeSaleText = document.getElementById('btn_complete_sale_text');

    // Receipt Modal Elements
    const receiptModal = document.getElementById('pos_receipt_modal');
    const btnReceiptPrint = document.getElementById('btn_receipt_print');
    const btnReceiptClose = document.getElementById('btn_receipt_close');

    // Thermal Slip Elements
    const slipOrderNum = document.getElementById('slip_order_num');
    const slipPaymentRef = document.getElementById('slip_payment_ref');
    const slipDatetime = document.getElementById('slip_datetime');
    const slipCashier = document.getElementById('slip_cashier');
    const slipCustomer = document.getElementById('slip_customer');
    const slipPhoneRow = document.getElementById('slip_phone_row');
    const slipCustomerPhone = document.getElementById('slip_customer_phone');
    const slipLoyaltyRow = document.getElementById('slip_loyalty_row');
    const slipLoyaltyInfo = document.getElementById('slip_loyalty_info');
    const slipMethod = document.getElementById('slip_method');
    const slipItemsBody = document.getElementById('slip_items_body');
    const slipSubtotal = document.getElementById('slip_subtotal');
    const slipDiscount = document.getElementById('slip_discount');
    const slipTax = document.getElementById('slip_tax');
    const slipGrandTotal = document.getElementById('slip_grand_total');

    // -------------------------------------------------------------------------
    // 1. Live Product Search & Category Filtering
    // -------------------------------------------------------------------------
    let activeCategory = 'all';
    let searchQuery = '';

    function filterProducts() {
        const query = searchQuery.toLowerCase().trim();
        const cards = productGrid.querySelectorAll('.pos-item-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const code = (card.getAttribute('data-code') || '').toLowerCase();
            const catId = card.getAttribute('data-category-id') || '';

            const matchesSearch = !query || name.includes(query) || code.includes(query);
            const matchesCategory = (activeCategory === 'all') || (catId === activeCategory);

            if (matchesSearch && matchesCategory) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
    }

    const searchClearBtn = document.getElementById('pos_search_clear');

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            searchQuery = e.target.value;
            if (searchClearBtn) {
                searchClearBtn.style.display = searchQuery.trim() ? 'flex' : 'none';
            }
            filterProducts();
        });
    }

    if (searchClearBtn && searchInput) {
        searchClearBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchQuery = '';
            searchClearBtn.style.display = 'none';
            searchInput.focus();
            filterProducts();
        });
    }

    if (categoryTabs) {
        categoryTabs.addEventListener('click', function(e) {
            const pill = e.target.closest('.pos-cat-pill');
            if (!pill) return;

            categoryTabs.querySelectorAll('.pos-cat-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');

            activeCategory = pill.getAttribute('data-category-id');
            filterProducts();

            if (typeof window.menuBarsPillRefresh === 'function') {
                window.menuBarsPillRefresh();
            }
        });
    }

    const posDateTabs = document.getElementById('pos_date_tabs');
    if (posDateTabs) {
        posDateTabs.addEventListener('click', function(e) {
            const pill = e.target.closest('.period-pill');
            if (!pill) return;
            e.preventDefault();
            posDateTabs.querySelectorAll('.period-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');

            const href = pill.getAttribute('href');
            if (href && window.history && window.history.pushState) {
                window.history.pushState({}, '', href);
            }
        });
    }

    // -------------------------------------------------------------------------
    // 2. Product Click -> Add to Cart
    // -------------------------------------------------------------------------
    productGrid.addEventListener('click', function(e) {
        const card = e.target.closest('.pos-item-card');
        if (!card) return;

        const id = parseInt(card.getAttribute('data-id'), 10);
        const name = card.getAttribute('data-name');
        const price = parseFloat(card.getAttribute('data-price'));
        const stock = parseInt(card.getAttribute('data-stock'), 10);

        if (stock <= 0) {
            showToast(`"${name}" is out of stock!`, 'warning');
            return;
        }

        const existing = cart.find(item => item.id === id);
        if (existing) {
            if (existing.qty < stock) {
                existing.qty++;
            } else {
                showToast(`Cannot add more. Available stock for "${name}" is ${stock}.`, 'warning');
                return;
            }
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                stock: stock,
                qty: 1
            });
        }

        renderCart();
    });

    // -------------------------------------------------------------------------
    // 3. Cart Render & Calculations
    // -------------------------------------------------------------------------
    const mobileCartCount = document.getElementById('pos_mobile_cart_count');
    const mobileCartTotal = document.getElementById('pos_mobile_cart_total');
    const posCartPanel = document.querySelector('.pos-cart-panel');
    const btnViewCartMobile = document.getElementById('btn_pos_mobile_view_cart');
    const btnCloseCartDrawer = document.getElementById('btn_close_cart_drawer');
    const posCartBackdrop = document.getElementById('pos_cart_backdrop');

    function openMobileCartDrawer() {
        if (posCartPanel) posCartPanel.classList.add('mobile-drawer-open', 'mobile-open');
        if (posCartBackdrop) posCartBackdrop.classList.add('is-active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileCartDrawer() {
        if (posCartPanel) posCartPanel.classList.remove('mobile-drawer-open', 'mobile-open');
        if (posCartBackdrop) posCartBackdrop.classList.remove('is-active');
        document.body.style.overflow = '';
    }

    const mobileCartBar = document.getElementById('pos_mobile_cart_bar');
    if (mobileCartBar) {
        mobileCartBar.addEventListener('click', openMobileCartDrawer);
    }
    if (btnViewCartMobile) {
        btnViewCartMobile.addEventListener('click', function(e) {
            e.stopPropagation();
            openMobileCartDrawer();
        });
    }
    if (btnCloseCartDrawer) {
        btnCloseCartDrawer.addEventListener('click', function(e) {
            e.stopPropagation();
            closeMobileCartDrawer();
        });
    }
    if (posCartBackdrop) {
        posCartBackdrop.addEventListener('click', closeMobileCartDrawer);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && posCartPanel && posCartPanel.classList.contains('mobile-drawer-open')) {
            closeMobileCartDrawer();
        }
    });

    function renderCart() {
        // Clear items container (except empty state placeholder if needed)
        cartContainer.innerHTML = '';

        if (cart.length === 0) {
            cartContainer.appendChild(cartEmptyState);
            cartEmptyState.style.display = 'flex';
            cartCountPill.textContent = '0';
            if (mobileCartCount) mobileCartCount.textContent = '0';
            if (mobileCartTotal) mobileCartTotal.textContent = `${CURRENCY}0.00`;
            subtotalDisplay.textContent = `${CURRENCY}0.00`;
            taxDisplay.textContent = `${CURRENCY}0.00`;
            grandTotalDisplay.textContent = `${CURRENCY}0.00`;
            completeSaleBtn.disabled = true;
            return;
        }

        cartEmptyState.style.display = 'none';

        let subtotal = 0;
        let totalItemsCount = 0;

        cart.forEach((item, index) => {
            const lineTotal = item.price * item.qty;
            subtotal += lineTotal;
            totalItemsCount += item.qty;

            const itemEl = document.createElement('div');
            itemEl.className = 'pos-cart-item';
            itemEl.innerHTML = `
                <div class="pos-item-details">
                    <div class="pos-item-row-title" title="${item.name}">${item.name}</div>
                    <div class="pos-item-row-unit">${CURRENCY}${item.price.toFixed(2)} each</div>
                </div>
                <div class="pos-qty-capsule">
                    <button type="button" class="btn-qty btn-minus" data-index="${index}">-</button>
                    <span class="pos-qty-value">${item.qty}</span>
                    <button type="button" class="btn-qty btn-plus" data-index="${index}">+</button>
                </div>
                <div class="pos-item-row-total">${CURRENCY}${lineTotal.toFixed(2)}</div>
                <button type="button" class="btn-item-remove" data-index="${index}" title="Remove item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            `;

            cartContainer.appendChild(itemEl);
        });

        cartCountPill.textContent = totalItemsCount.toString();

        // Calculations
        const rawDiscount = parseFloat(discountInput.value) || 0;
        const discount = Math.min(Math.max(0, rawDiscount), subtotal);
        const taxable = Math.max(0, subtotal - discount);
        const tax = (taxable * (TAX_PERCENTAGE / 100));
        const grandTotal = taxable + tax;
        currentGrandTotal = grandTotal;

        subtotalDisplay.textContent = `${CURRENCY}${subtotal.toFixed(2)}`;
        taxDisplay.textContent = `${CURRENCY}${tax.toFixed(2)}`;
        grandTotalDisplay.textContent = `${CURRENCY}${grandTotal.toFixed(2)}`;

        if (mobileCartCount) mobileCartCount.textContent = totalItemsCount.toString();
        if (mobileCartTotal) mobileCartTotal.textContent = `${CURRENCY}${grandTotal.toFixed(2)}`;

        completeSaleBtn.disabled = false;
        completeSaleText.textContent = `Charge ${CURRENCY}${grandTotal.toFixed(2)}`;
    }

    // Cart Items Interaction (+, -, remove)
    cartContainer.addEventListener('click', function(e) {
        const plusBtn = e.target.closest('.btn-plus');
        const minusBtn = e.target.closest('.btn-minus');
        const removeBtn = e.target.closest('.btn-item-remove');

        if (plusBtn) {
            const index = parseInt(plusBtn.getAttribute('data-index'), 10);
            const item = cart[index];
            if (item.qty < item.stock) {
                item.qty++;
                renderCart();
            } else {
                showToast(`Max available stock for "${item.name}" is ${item.stock}.`, 'warning');
            }
            return;
        }

        if (minusBtn) {
            const index = parseInt(minusBtn.getAttribute('data-index'), 10);
            const item = cart[index];
            if (item.qty > 1) {
                item.qty--;
            } else {
                cart.splice(index, 1);
            }
            renderCart();
            return;
        }

        if (removeBtn) {
            const index = parseInt(removeBtn.getAttribute('data-index'), 10);
            cart.splice(index, 1);
            renderCart();
            return;
        }
    });

    // Discount change listener
    discountInput.addEventListener('input', function() {
        renderCart();
    });

    // Clear Cart button
    clearCartBtn.addEventListener('click', function() {
        if (cart.length === 0) return;
        window.showConfirmDialog({
            title: 'Clear Shopping Cart',
            message: 'Are you sure you want to clear all items from the current cart?',
            confirmText: 'Clear Cart',
            isDanger: true,
            onConfirm: function() {
                cart = [];
                discountInput.value = '0.00';
                renderCart();
            }
        });
    });

    // -------------------------------------------------------------------------
    // 4. Payment Method Selection
    // -------------------------------------------------------------------------
    paymentSelector.addEventListener('click', function(e) {
        const btn = e.target.closest('.pos-pay-btn');
        if (!btn) return;

        paymentSelector.querySelectorAll('.pos-pay-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedPaymentMethod = btn.getAttribute('data-method');
    });

    // -------------------------------------------------------------------------
    // 4.1 Customer Search, Selection & Quick Registration
    // -------------------------------------------------------------------------
    function selectCustomer(cust) {
        selectedCustomer = cust;
        customerIdInput.value = cust.id;
        customerInput.value = cust.name;

        const initials = (cust.name || 'CU').trim().substring(0, 2).toUpperCase();
        custInitials.textContent = initials;
        custSelectedName.textContent = cust.name;
        custSelectedPhone.textContent = cust.phone || 'No phone number';
        custSelectedTier.textContent = `⭐ ${cust.loyalty_points || 0} pts (${cust.loyalty_tier || 'Standard'})`;

        customerSearchContainer.style.display = 'none';
        selectedCustCard.style.display = 'flex';
        customerDropdown.style.display = 'none';
        customerDropdown.innerHTML = '';
    }

    function clearCustomer() {
        selectedCustomer = null;
        if (customerIdInput) customerIdInput.value = '';
        if (customerInput) customerInput.value = 'Walk-in Customer';
        if (customerSearchInput) customerSearchInput.value = '';

        if (selectedCustCard) selectedCustCard.style.display = 'none';
        if (customerSearchContainer) customerSearchContainer.style.display = 'block';
        if (customerDropdown) {
            customerDropdown.style.display = 'none';
            customerDropdown.innerHTML = '';
        }
    }

    let searchCustTimeout = null;
    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            clearTimeout(searchCustTimeout);

            if (query.length === 0) {
                if (customerDropdown) {
                    customerDropdown.style.display = 'none';
                    customerDropdown.innerHTML = '';
                }
                if (customerInput) customerInput.value = 'Walk-in Customer';
                return;
            }

            if (customerInput) customerInput.value = query;

            searchCustTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`${CUSTOMER_SEARCH_URL}?q=${encodeURIComponent(query)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();

                    if (!customerDropdown) return;
                    customerDropdown.innerHTML = '';
                    if (data.customers && data.customers.length > 0) {
                        data.customers.forEach(cust => {
                            const item = document.createElement('div');
                            item.className = 'pos-cust-dropdown-item';
                            item.style.cssText = 'padding: 0.65rem 0.85rem; border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, #f1f5f9); transition: background 0.15s ease;';
                            item.innerHTML = `
                                <div>
                                    <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-title, #29150d);">${cust.name}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted, #71717a);">${cust.phone || cust.email || 'No phone'}</div>
                                </div>
                                <span style="font-size: 0.75rem; font-weight: 800; color: #d97706; background: rgba(217, 119, 6, 0.1); padding: 2px 7px; border-radius: 9999px;">
                                    ⭐ ${cust.loyalty_points} pts
                                </span>
                            `;
                            item.addEventListener('mouseenter', () => item.style.background = 'rgba(86, 48, 32, 0.06)');
                            item.addEventListener('mouseleave', () => item.style.background = 'transparent');
                            item.addEventListener('click', () => selectCustomer(cust));
                            customerDropdown.appendChild(item);
                        });
                        customerDropdown.style.display = 'block';
                    } else {
                        customerDropdown.innerHTML = `
                            <div style="padding: 0.75rem; text-align: center; color: var(--text-muted, #71717a); font-size: 0.8rem;">
                                No customers found. Click <strong>+ New</strong> to register.
                            </div>
                        `;
                        customerDropdown.style.display = 'block';
                    }
                } catch (err) {
                    console.error('Customer search error:', err);
                }
            }, 200);
        });
    }

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (customerSearchContainer && !customerSearchContainer.contains(e.target)) {
            if (customerDropdown) customerDropdown.style.display = 'none';
        }
    });

    // Remove customer
    if (btnRemoveCust) {
        btnRemoveCust.addEventListener('click', function() {
            clearCustomer();
        });
    }

    // Quick Add Customer modal handlers
    if (btnOpenQuickCust) {
        btnOpenQuickCust.addEventListener('click', () => quickCustModal.classList.add('is-active'));
    }
    if (btnCloseQuickCust) {
        btnCloseQuickCust.addEventListener('click', () => quickCustModal.classList.remove('is-active'));
    }
    if (btnCancelQuickCust) {
        btnCancelQuickCust.addEventListener('click', () => quickCustModal.classList.remove('is-active'));
    }

    formQuickCustomer.addEventListener('submit', async function(e) {
        e.preventDefault();
        const payload = {
            name: quickCustName.value.trim(),
            phone: quickCustPhone.value.trim() || null,
            email: quickCustEmail.value.trim() || null
        };

        try {
            const res = await fetch(CUSTOMER_QUICK_STORE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to register customer');
            }

            selectCustomer(data.customer);
            quickCustModal.classList.remove('is-active');
            formQuickCustomer.reset();
            showToast(`Customer "${data.customer.name}" registered & selected!`, 'success');
        } catch (err) {
            showToast(err.message, 'error');
        }
    });

    // -------------------------------------------------------------------------
    // 5. Checkout / Process Sale
    // -------------------------------------------------------------------------
    // ── KHQR / Card confirmation modal ──
    const payModal = document.getElementById('pos_payment_modal');
    const payTitle = document.getElementById('pay_modal_title');
    const payAmount = document.getElementById('pay_modal_amount');
    const payPanelKhqr = document.getElementById('pay_panel_khqr');
    const payPanelCard = document.getElementById('pay_panel_card');
    const payKhqrCode = document.getElementById('pay_khqr_code');
    const payKhqrRef = document.getElementById('pay_khqr_ref');
    const payCardLast4 = document.getElementById('pay_card_last4');
    const payCardApproval = document.getElementById('pay_card_approval');
    const payError = document.getElementById('pay_modal_error');

    /**
     * Builds the string encoded in the on-screen QR. Without a registered Bakong
     * merchant ID this is a display placeholder, not a scannable KHQR payment.
     */
    function buildKhqrPayload(amount) {
        const merchant = config.khqrMerchantId || 'MERCHANT_NOT_SET';
        return `KHQR|${merchant}|${config.bakeryName || 'Bakery'}|${amount.toFixed(2)}|${CURRENCY}`;
    }

    function openPaymentModal(method) {
        payError.style.display = 'none';
        payAmount.textContent = `${CURRENCY}${currentGrandTotal.toFixed(2)}`;
        const isQr = method === 'qr_code';
        payTitle.textContent = isQr ? 'KHQR Payment' : 'Card Payment';
        payPanelKhqr.style.display = isQr ? 'block' : 'none';
        payPanelCard.style.display = isQr ? 'none' : 'block';

        if (isQr) {
            payKhqrRef.value = '';
            payKhqrCode.innerHTML = '';
            if (window.QRCode) {
                new QRCode(payKhqrCode, { text: buildKhqrPayload(currentGrandTotal), width: 200, height: 200 });
            } else {
                payKhqrCode.textContent = 'QR library failed to load.';
            }
            setTimeout(() => payKhqrRef.focus(), 50);
        } else {
            payCardLast4.value = '';
            payCardApproval.value = '';
            setTimeout(() => payCardLast4.focus(), 50);
        }
        payModal.classList.add('is-active');
    }

    function closePaymentModal() {
        payModal.classList.remove('is-active');
    }

    function showPayError(msg) {
        payError.textContent = msg;
        payError.style.display = 'block';
    }

    document.getElementById('btn_cancel_pay').addEventListener('click', closePaymentModal);
    payCardLast4.addEventListener('input', () => {
        payCardLast4.value = payCardLast4.value.replace(/\D/g, '').slice(0, 4);
    });

    document.getElementById('btn_confirm_pay').addEventListener('click', function() {
        if (selectedPaymentMethod === 'qr_code') {
            const ref = payKhqrRef.value.trim();
            if (!ref) return showPayError('Enter the KHQR transaction ID.');
            closePaymentModal();
            processCheckout({ payment_reference: ref });
        } else {
            const last4 = payCardLast4.value.trim();
            if (!/^\d{4}$/.test(last4)) return showPayError('Enter the last 4 digits of the card.');
            const approval = payCardApproval.value.trim();
            closePaymentModal();
            processCheckout({ card_last4: last4, payment_reference: approval || null });
        }
    });

    completeSaleBtn.addEventListener('click', function() {
        if (cart.length === 0) return;
        if (selectedPaymentMethod === 'qr_code' || selectedPaymentMethod === 'card') {
            openPaymentModal(selectedPaymentMethod);
            return;
        }
        processCheckout({});
    });

    async function processCheckout(paymentDetails) {
        if (cart.length === 0) return;

        const customerName = selectedCustomer ? selectedCustomer.name : (customerInput.value.trim() || 'Walk-in Customer');
        const customerId = selectedCustomer ? selectedCustomer.id : (customerIdInput.value ? parseInt(customerIdInput.value, 10) : null);
        const discountVal = parseFloat(discountInput.value) || 0;

        const payload = {
            customer_id: customerId,
            customer_name: customerName,
            payment_method: selectedPaymentMethod,
            ...paymentDetails,
            discount: discountVal,
            items: cart.map(item => ({
                product_id: item.id,
                quantity: item.qty
            }))
        };

        // Loading state
        completeSaleBtn.disabled = true;
        const originalText = completeSaleText.textContent;
        completeSaleText.textContent = 'Processing Sale...';

        try {
            const response = await fetch(CHECKOUT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Checkout failed. Please try again.');
            }

            // Success!
            showToast(data.message || 'Sale completed successfully!', 'success');

            // 1. Update Product Card Stocks on the DOM
            if (data.receipt && data.receipt.items) {
                data.receipt.items.forEach(soldItem => {
                    const card = productGrid.querySelector(`.pos-item-card[data-name="${soldItem.name}"]`);
                    if (card) {
                        const newStock = soldItem.remaining_stock;
                        card.setAttribute('data-stock', newStock);

                        const badge = card.querySelector('.pos-stock-badge');
                        if (badge) {
                            if (newStock <= 0) {
                                card.classList.add('is-out-of-stock');
                                badge.className = 'pos-stock-badge out-of-stock';
                                badge.innerHTML = '<span data-lang-key="out_of_stock">Out of Stock</span>';
                            } else if (newStock <= 5) {
                                badge.className = 'pos-stock-badge low-stock';
                                badge.innerHTML = `<span class="stock-text">Low stock: ${newStock}</span>`;
                            } else {
                                badge.className = 'pos-stock-badge in-stock';
                                badge.innerHTML = `<span class="stock-text">${newStock} in stock</span>`;
                            }
                        }
                    }
                });
            }

            // 2. Populate and Open Receipt Modal
            closeMobileCartDrawer();
            populateReceiptModal(data.receipt);
            receiptModal.classList.add('is-active');

            // 3. Reset Cart & Customer
            cart = [];
            discountInput.value = '0.00';
            clearCustomer();
            renderCart();

        } catch (error) {
            console.error('POS Checkout Error:', error);
            showToast(error.message || 'An unexpected error occurred.', 'error');
            completeSaleBtn.disabled = false;
            completeSaleText.textContent = originalText;
        }
    }

    // -------------------------------------------------------------------------
    // 6. Receipt Modal & Printing
    // -------------------------------------------------------------------------
    function populateReceiptModal(receipt) {
        slipOrderNum.textContent = receipt.order_number;
        slipPaymentRef.textContent = receipt.payment_ref;
        slipDatetime.textContent = receipt.date_time;
        slipCashier.textContent = receipt.cashier_name;
        slipCustomer.textContent = receipt.customer_name;
        slipMethod.textContent = receipt.payment_method;

        // Populate customer phone & loyalty details if customer present
        if (receipt.customer_phone) {
            slipPhoneRow.style.display = 'block';
            slipCustomerPhone.textContent = receipt.customer_phone;
        } else {
            slipPhoneRow.style.display = 'none';
        }

        if (receipt.customer_id && receipt.points_earned !== undefined) {
            slipLoyaltyRow.style.display = 'block';
            slipLoyaltyInfo.textContent = `+${receipt.points_earned} pts (Bal: ${receipt.loyalty_balance} pts, ${receipt.loyalty_tier})`;
        } else {
            slipLoyaltyRow.style.display = 'none';
        }

        slipSubtotal.textContent = `${receipt.currency}${receipt.subtotal}`;
        slipDiscount.textContent = `${receipt.currency}${receipt.discount}`;
        slipTax.textContent = `${receipt.currency}${receipt.tax}`;
        slipGrandTotal.textContent = `${receipt.currency}${receipt.grand_total}`;

        // Populate Items
        slipItemsBody.innerHTML = '';
        receipt.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.name}</td>
                <td class="align-right">${item.quantity} x ${receipt.currency}${parseFloat(item.price).toFixed(2)}</td>
                <td class="align-right">${receipt.currency}${parseFloat(item.subtotal).toFixed(2)}</td>
            `;
            slipItemsBody.appendChild(tr);
        });
    }

    // Print Receipt button
    btnReceiptPrint.addEventListener('click', function() {
        window.print();
    });

    // New Sale / Close Modal button
    btnReceiptClose.addEventListener('click', function() {
        receiptModal.classList.remove('is-active');
        clearCustomer();
    });

    // Close on backdrop click
    receiptModal.addEventListener('click', function(e) {
        if (e.target === receiptModal) {
            receiptModal.classList.remove('is-active');
            clearCustomer();
        }
    });

    // -------------------------------------------------------------------------
    // 7. Toast Notification Utility
    // -------------------------------------------------------------------------
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `pos-toast pos-toast-${type}`;

        let bg = '#1e293b';
        if (type === 'success') bg = '#16a34a';
        if (type === 'warning') bg = '#d97706';
        if (type === 'error') bg = '#dc2626';

        toast.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: ${bg};
            color: #ffffff;
            padding: 0.85rem 1.35rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            z-index: 100000;
            animation: slideInToast 0.3s ease;
            max-width: 380px;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    }

    // Initialize
    renderCart();

})();
