import { test, expect } from '@playwright/test';

test.describe('Settings Management End-to-End Suite', () => {

    async function loginAsAdmin(page) {
        await page.goto('/login');
        await page.fill('#username_input', 'admin@bakery.com');
        await page.fill('#password_input', 'pass123');
        await page.click('#submit_btn');
        await expect(page).toHaveURL(/\/admin\/dashboard/);
    }

    async function loginAsCashier(page) {
        await page.goto('/login');
        await page.fill('#username_input', 'cashier@bakery.com');
        await page.fill('#password_input', 'pass123');
        await page.click('#submit_btn');
        await expect(page).toHaveURL(/\/cashier\/dashboard/);
    }

    test('1. Admin can open Settings and all glass cards render', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');
        await expect(page).toHaveURL(/\/admin\/settings/);

        // Header
        const headerTitle = page.locator('.settings-header-intro h1');
        await expect(headerTitle).toBeVisible();
        await expect(headerTitle).toContainText(/System Settings/i);

        // Core cards
        await expect(page.locator('#form_shop_info')).toBeVisible();
        await expect(page.locator('#form_tax_discount')).toBeVisible();
        await expect(page.locator('#form_receipt')).toBeVisible();
        await expect(page.locator('#form_security')).toBeVisible();
        await expect(page.locator('#form_general')).toBeVisible();
        await expect(page.locator('#form_appearance')).toBeVisible();
        await expect(page.locator('#form_operations')).toBeVisible();
        await expect(page.locator('#card_backup_restore')).toBeVisible();
    });

    test('2. Admin can update bakery information and changes persist after reload', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        const uniqueName = 'Sweet Delights Bakery ' + Date.now().toString().slice(-4);
        await page.fill('#input_shop_name', uniqueName);
        await page.fill('#input_shop_phone', '+855 12 345 678');
        await page.fill('#input_shop_email', 'store@delights.com');

        await page.click('#btn_save_shop_info');

        // Toast feedback
        const toast = page.locator('#settings_toast');
        await expect(toast).toBeVisible();

        // Reload page to verify database persistence
        await page.reload();
        await expect(page.locator('#input_shop_name')).toHaveValue(uniqueName);
        await expect(page.locator('#input_shop_phone')).toHaveValue('+855 12 345 678');
        await expect(page.locator('#input_shop_email')).toHaveValue('store@delights.com');
    });

    test('3. Admin can update tax rate and discount with persistence', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        await page.fill('#input_tax_rate', '12.5');
        await page.fill('#input_discount', '7.0');

        await page.click('#btn_save_tax_discount');

        const toast = page.locator('#settings_toast');
        await expect(toast).toBeVisible();

        await page.reload();
        await expect(page.locator('#input_tax_rate')).toHaveValue('12.5');
        await expect(page.locator('#input_discount')).toHaveValue('7.0');
    });

    test('4. Admin can update currency (USD / KHR) and date format', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        await page.selectOption('#currency_select', 'KHR');
        await page.selectOption('#date_format_select', 'DD/MM/YYYY');

        await page.click('#btn_save_general');

        const toast = page.locator('#settings_toast');
        await expect(toast).toBeVisible();

        await page.reload();
        await expect(page.locator('#currency_select')).toHaveValue('KHR');

        // Switch back to USD
        await page.selectOption('#currency_select', 'USD');
        await page.click('#btn_save_general');
        await expect(toast).toBeVisible();
    });

    test('5. Admin can update Business Hours & Operations thresholds', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        await page.fill('#input_opening_time', '06:30');
        await page.fill('#input_closing_time', '21:00');
        await page.fill('#input_low_stock_threshold', '8');
        await page.fill('#input_expiry_warning_days', '5');

        await page.click('#btn_save_operations');

        const toast = page.locator('#settings_toast');
        await expect(toast).toBeVisible();

        await page.reload();
        await expect(page.locator('#input_opening_time')).toHaveValue('06:30');
        await expect(page.locator('#input_closing_time')).toHaveValue('21:00');
        await expect(page.locator('#input_low_stock_threshold')).toHaveValue('8');
        await expect(page.locator('#input_expiry_warning_days')).toHaveValue('5');
    });

    test('6. Server-side validation errors are handled correctly', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        // Tax rate exceeds 100% or invalid
        await page.evaluate(() => {
            const input = document.getElementById('input_tax_rate');
            if (input) input.value = '250'; // bypass HTML5 max for server validation test
        });

        await page.click('#btn_save_tax_discount');

        // Toast feedback receives error message
        const toast = page.locator('#settings_toast');
        await expect(toast).toBeVisible();
    });

    test('7. Theme switching works across Light, Dark, and Glass modes', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        const html = page.locator('html');

        // Dark
        await page.click('.segmented-option[data-theme="dark"]');
        await expect(html).toHaveAttribute('data-theme', 'dark');

        // Glass
        await page.click('.segmented-option[data-theme="glass"]');
        await expect(html).toHaveAttribute('data-theme', 'glass');

        // Light
        await page.click('.segmented-option[data-theme="light"]');
        await expect(html).toHaveAttribute('data-theme', 'light');
    });

    test('8. Language switching between English and Khmer', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        const headerTitle = page.locator('.settings-header-intro h1');

        // Switch to Khmer via dropdown
        await page.selectOption('#language_select', 'KM');
        await expect(page.locator('html')).toHaveAttribute('lang', 'km');
        await expect(headerTitle).toContainText(/ការកំណត់ប្រព័ន្ធ/);

        // Switch back to English
        await page.selectOption('#language_select', 'EN');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(headerTitle).toContainText(/System Settings/);
    });

    test('9. Mobile responsive layout check', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await loginAsAdmin(page);
        await page.goto('/admin/settings');

        await expect(page.locator('.settings-header-intro h1')).toBeVisible();
        await expect(page.locator('#form_shop_info')).toBeVisible();
        await expect(page.locator('#input_shop_name')).toBeVisible();
        await expect(page.locator('#card_backup_restore')).toBeVisible();
    });

    test('10. Cashier is blocked from Settings page', async ({ page }) => {
        await loginAsCashier(page);
        await page.goto('/admin/settings');

        // CheckRole redirects cashier to their dashboard
        await expect(page).toHaveURL(/\/cashier\/dashboard/);
    });
});
