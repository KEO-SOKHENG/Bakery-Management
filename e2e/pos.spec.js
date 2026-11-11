import { test, expect } from '@playwright/test';

test.describe('POS Terminal Full End-to-End Workflow', () => {
    test('Cashier can view POS, search, add items, adjust qty, apply discount, select KHQR, and checkout', async ({ page }) => {
        // 1. Login as Cashier
        await page.goto('/login');
        await page.fill('#username_input', 'cashier@bakery.com');
        await page.fill('#password_input', 'pass123');
        await page.click('#submit_btn');

        // 2. Arrives at Cashier Dashboard / POS Terminal
        await expect(page).toHaveURL(/\/cashier\/dashboard/);
        await expect(page.locator('.pos-screen-layout')).toBeVisible();

        // 3. Check Catalog Components
        await expect(page.locator('#pos_search_input')).toBeVisible();
        await expect(page.locator('#pos_category_tabs')).toBeVisible();
        await expect(page.locator('#pos_product_grid')).toBeVisible();

        // 4. Test Search Filtering
        const searchInput = page.locator('#pos_search_input');
        await searchInput.fill('Croissant');
        await page.waitForTimeout(300);
        await searchInput.fill('');
        await page.waitForTimeout(300);

        // 5. Test Adding Product with at least 2 in stock (to test '+' quantity increment)
        let cardToClick = page.locator('.pos-item-card:not(.is-out-of-stock)').first();
        const cardsCount = await page.locator('.pos-item-card:not(.is-out-of-stock)').count();
        for (let i = 0; i < cardsCount; i++) {
            const card = page.locator('.pos-item-card:not(.is-out-of-stock)').nth(i);
            const stockVal = await card.getAttribute('data-stock');
            if (parseInt(stockVal || '0', 10) >= 2) {
                cardToClick = card;
                break;
            }
        }
        await expect(cardToClick).toBeVisible();
        await cardToClick.click();

        // 6. Verify Item in Cart
        const cartItem = page.locator('.pos-cart-item').first();
        await expect(cartItem).toBeVisible();
        await expect(page.locator('#pos_cart_count_pill')).toHaveText('1');

        // 7. Increase quantity with '+'
        const plusBtn = cartItem.locator('.btn-plus');
        await plusBtn.click();
        await expect(cartItem.locator('.pos-qty-value')).toHaveText('2');
        await expect(page.locator('#pos_cart_count_pill')).toHaveText('2');

        // 8. Apply Discount
        const discountInput = page.locator('#pos_discount_input');
        await discountInput.fill('1.00');
        await discountInput.dispatchEvent('input');

        // 9. Select KHQR Payment Method
        const qrBtn = page.locator('.pos-pay-btn[data-method="qr_code"]');
        await qrBtn.click();
        await expect(qrBtn).toHaveClass(/active/);

        // 10. Click Complete Sale
        const completeSaleBtn = page.locator('#btn_complete_sale');
        await expect(completeSaleBtn).toBeEnabled();
        await completeSaleBtn.click();

        // 10.1 Confirm KHQR payment with a transaction ID
        await expect(page.locator('#pos_payment_modal')).toHaveClass(/is-active/);
        await page.fill('#pay_khqr_ref', `E2E-${Date.now()}`);
        await page.click('#btn_confirm_pay');

        // 11. Verify Receipt Modal Appears
        const modal = page.locator('#pos_receipt_modal');
        await expect(modal).toHaveClass(/is-active/);
        await expect(page.locator('#slip_order_num')).not.toHaveText('ORD-0000');
        await expect(page.locator('#slip_method')).toHaveText('KHQR');

        // 12. Dismiss Modal via 'New Sale' button
        const newSaleBtn = page.locator('#btn_receipt_close');
        await newSaleBtn.click();
        await expect(modal).not.toHaveClass(/is-active/);

        // 13. Verify Cart is Reset
        await expect(page.locator('#pos_cart_empty_state')).toBeVisible();
        await expect(page.locator('#pos_cart_count_pill')).toHaveText('0');
    });

    test('Manager and Admin can also access POS via /pos route', async ({ page }) => {
        // Login as Manager
        await page.goto('/login');
        await page.fill('#username_input', 'manager@bakery.com');
        await page.fill('#password_input', 'pass123');
        await page.click('#submit_btn');
        await expect(page).toHaveURL(/\/manager\/dashboard/);

        // Navigate to /pos
        await page.goto('/pos');
        await expect(page).toHaveURL(/\/pos/);
        await expect(page.locator('.pos-screen-layout')).toBeVisible();
    });
});
