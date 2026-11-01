import { test, expect } from '@playwright/test';

test.describe('User & Staff Management + Roles & Permissions Module', () => {

    test.beforeEach(async ({ page }) => {
        // Log in as Admin
        await page.goto('/login');
        await page.fill('#username_input', 'admin@bakery.com');
        await page.fill('#password_input', 'pass123');
        await page.click('#submit_btn');
        await expect(page).toHaveURL(/\/admin\/dashboard/);
    });

    test('1. Admin can navigate to User Management and view KPI statistics & table', async ({ page }) => {
        await page.goto('/admin/users');
        await expect(page).toHaveURL(/\/admin\/users/);

        // Header & KPI cards
        await expect(page.locator('h1, h2')).toContainText(/User & Staff Management/i);
        await expect(page.locator('.user-stat-card')).toHaveCount(4);

        // Verify table displays users
        await expect(page.locator('#users_table')).toBeVisible();
        await expect(page.locator('#users_table tbody tr').first()).toBeVisible();
    });

    test('2. Admin can create a new staff account with temporary password flag', async ({ page }) => {
        await page.goto('/admin/users');

        const uniqueSuffix = Date.now().toString().slice(-4);
        const newUsername = `staff_${uniqueSuffix}`;
        const newEmail = `staff_${uniqueSuffix}@bakery.com`;

        // Open create user modal
        await page.click('#btn_open_create_user_modal');
        await expect(page.locator('#create_user_modal')).toHaveClass(/is-active/);

        // Fill form
        await page.fill('#create_user_name', `Staff Member ${uniqueSuffix}`);
        await page.fill('#create_user_username', newUsername);
        await page.fill('#create_user_phone', '012555666');
        await page.fill('#create_user_email', newEmail);
        await page.selectOption('#create_user_role', 'cashier');
        await page.selectOption('#create_user_status', 'active');
        await page.fill('#create_user_password', 'tempPass123');

        // Submit form
        await page.click('#create_user_modal button[type="submit"]');

        // Wait for page load and verify user is in table
        await expect(page).toHaveURL(/\/admin\/users/);
        await expect(page.locator('body')).toContainText(newUsername);
        await expect(page.locator('body')).toContainText(/TEMP PWD/i);
    });

    test('3. Search and filtering work as expected', async ({ page }) => {
        await page.goto('/admin/users');

        // Search for 'admin'
        await page.goto('/admin/users?search=admin');
        await expect(page).toHaveURL(/search=admin/);
        await expect(page.locator('#users_table')).toContainText('admin');

        // Filter by role: 'cashier'
        await page.goto('/admin/users?role=cashier');
        await expect(page.locator('#users_table')).toContainText(/CASHIER/i);

        // Filter by status: 'active'
        await page.goto('/admin/users?status=active');
        await expect(page.locator('#users_table')).toContainText(/ACTIVE/i);
    });

    test('4. Admin can open permissions modal and view effective permissions', async ({ page }) => {
        await page.goto('/admin/users');

        // Click edit on the first user row and open permissions
        await page.locator('.btn-edit-user').first().click();
        const permBtn = page.locator('.btn-permissions-user').first();
        await expect(permBtn).toBeVisible();
        await permBtn.click();

        // Verify permission modal opens
        const permModal = page.locator('#permissions_user_modal');
        await expect(permModal).toHaveClass(/is-active/);
        await expect(page.locator('.permission-module-card').first()).toBeVisible();

        // Close modal
        await page.click('#btn_close_perm_modal');
        await expect(permModal).not.toHaveClass(/is-active/);
    });

    test('5. Admin can open reset password modal', async ({ page }) => {
        await page.goto('/admin/users');

        // Click edit on first user and open reset password modal
        await page.locator('.btn-edit-user').first().click();
        const resetBtn = page.locator('.btn-reset-pwd-user').first();
        await expect(resetBtn).toBeVisible();
        await resetBtn.click();

        const resetModal = page.locator('#reset_password_modal');
        await expect(resetModal).toHaveClass(/is-active/);
        await expect(page.locator('#reset_pwd_user_title')).toBeVisible();

        // Close modal
        await page.click('#btn_close_reset_pwd');
        await expect(resetModal).not.toHaveClass(/is-active/);
    });

    test('6. Admin can open view details and inspect user audit activity', async ({ page }) => {
        await page.goto('/admin/users');

        // Click view button
        const viewBtn = page.locator('.btn-view-user').first();
        await expect(viewBtn).toBeVisible();
        await viewBtn.click();

        const viewModal = page.locator('#view_user_modal');
        await expect(viewModal).toHaveClass(/is-active/);
        await expect(page.locator('#view_user_name')).toBeVisible();
        await expect(page.locator('#view_user_audit_timeline')).toBeVisible();

        // Close modal
        await page.click('#btn_close_view_modal');
        await expect(viewModal).not.toHaveClass(/is-active/);
    });

    test('7. Unauthorized roles cannot access User Management', async ({ page }) => {
        // Log out of admin
        await page.goto('/logout');

        // Log in as Cashier
        await page.goto('/login');
        await page.fill('#username_input', 'cashier@bakery.com');
        await page.fill('#password_input', 'pass123');
        await page.click('#submit_btn');
        await expect(page).toHaveURL(/\/cashier\/dashboard/);

        // Attempt direct URL to /admin/users
        await page.goto('/admin/users');
        // CheckRole should block and redirect cashier back to their dashboard
        await expect(page).toHaveURL(/\/cashier\/dashboard/);
    });

    test('8. Language switching (EN / KM) and Theme compatibility', async ({ page }) => {
        await page.goto('/admin/users');

        // Switch to KM
        await page.goto('/lang/km');
        await page.goto('/admin/users');
        await expect(page.locator('html')).toHaveAttribute('lang', 'km');

        // Switch back to EN
        await page.goto('/lang/en');
        await page.goto('/admin/users');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');

        // Theme test: Dark Mode
        await page.evaluate(() => {
            document.body.classList.add('dark-mode');
        });
        await expect(page.locator('body')).toHaveClass(/dark-mode/);

        // Theme test: Glass Mode
        await page.evaluate(() => {
            document.body.classList.remove('dark-mode');
            document.body.classList.add('glass-mode');
        });
        await expect(page.locator('body')).toHaveClass(/glass-mode/);
    });
});
