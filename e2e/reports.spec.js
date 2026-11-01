import { test, expect } from '@playwright/test';

test.describe('Report Management Module', () => {

    test.describe('Admin Access & Core Navigation', () => {
        test.beforeEach(async ({ page }) => {
            await page.goto('/login');
            await page.fill('#username_input', 'admin@bakery.com');
            await page.fill('#password_input', 'pass123');
            await page.click('#submit_btn');
            await expect(page).toHaveURL(/\/admin\/dashboard/);
        });

        test('should navigate to Reports page and render primary layout', async ({ page }) => {
            await page.goto('/admin/reports');
            await expect(page).toHaveURL(/\/admin\/reports/);

            // Verify page heading
            const heading = page.locator('h1');
            await expect(heading).toContainText(/Reports/i);

            // Verify all 5 primary report tabs are present
            const tabContainer = page.locator('.report-nav-tabs');
            await expect(tabContainer).toBeVisible();
            const tabLinks = tabContainer.locator('a.report-tab-btn');
            await expect(tabLinks).toHaveCount(5);

            // Verify filter bar
            const filterBar = page.locator('.dash-filter-bar');
            await expect(filterBar).toBeVisible();

            // Verify date preset pills
            const periodPills = filterBar.locator('a.period-pill');
            await expect(periodPills).toHaveCount(6);

            // Verify export buttons
            await expect(page.locator('#btn_print_report')).toBeVisible();
            await expect(page.locator('#btn_export_excel')).toBeVisible();
            await expect(page.locator('#btn_export_pdf')).toBeVisible();

            // Verify 4 summary KPI cards
            const statCards = page.locator('.reports-summary-grid .report-stat-card');
            await expect(statCards).toHaveCount(4);
        });

        test('should switch through report tabs and display distinct metrics', async ({ page }) => {
            await page.goto('/admin/reports');

            // 1. Sales group & Revenue Report
            await page.click('#nav_sales');
            await expect(page).toHaveURL(/type=sales/);
            await page.click('#tab_revenue');
            await expect(page).toHaveURL(/type=revenue/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);
            await expect(page.locator('.report-table-wrapper')).toBeVisible();

            // 2. Orders Report
            await page.click('#tab_orders');
            await expect(page).toHaveURL(/type=orders/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 3. Inventory group & Products Report
            await page.click('#nav_inventory');
            await expect(page).toHaveURL(/type=products/);
            await page.click('#tab_products');
            await expect(page).toHaveURL(/type=products/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 4. Inventory Report
            await page.click('#tab_inventory');
            await expect(page).toHaveURL(/type=inventory/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 5. Low Stock Report
            await page.click('#tab_low_stock');
            await expect(page).toHaveURL(/type=low-stock/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 6. Purchases Report
            await page.click('#tab_purchases');
            await expect(page).toHaveURL(/type=purchases/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 7. Production Report
            await page.click('#tab_production');
            await expect(page).toHaveURL(/type=production/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 8. Customers Report
            await page.click('#tab_customers');
            await expect(page).toHaveURL(/type=customers/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 9. Profit & Loss Report
            await page.goto('/admin/reports?type=profit-loss');
            await expect(page).toHaveURL(/type=profit-loss/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // 10. Sales Report
            await page.click('#nav_sales');
            await expect(page).toHaveURL(/type=sales/);
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);
        });

        test('should filter by preset periods and custom date range', async ({ page }) => {
            await page.goto('/admin/reports?type=sales');

            // Click "This Month" preset
            const thisMonthPill = page.locator('a.period-pill:has-text("This Month")');
            await thisMonthPill.click();
            await expect(page).toHaveURL(/period=this_month/);

            // Test custom date query parameter filter
            await page.goto('/admin/reports?type=sales&start_date=2026-09-01&end_date=2026-09-09');
            await expect(page).toHaveURL(/start_date=2026-09-01/);
            await expect(page).toHaveURL(/end_date=2026-09-09/);
            await expect(page.locator('.reports-summary-grid')).toBeVisible();
        });

        test('should render responsive KPI summary cards without visual chart breakdown cards', async ({ page }) => {
            await page.goto('/admin/reports?type=sales');
            await expect(page.locator('.reports-summary-grid')).toBeVisible();
            await expect(page.locator('.reports-summary-grid .report-stat-card')).toHaveCount(4);

            // Verify visual breakdown cards (e.g. payment distribution) are not present
            await expect(page.locator('#sales_payment_distribution_card')).toHaveCount(0);

            // Verify Products tab does not render Top 5 breakdown card
            await page.goto('/admin/reports?type=products');
            await expect(page.locator('#products_top5_card')).toHaveCount(0);
        });

        test('should not display Data Audit search card and should render report table directly', async ({ page }) => {
            await page.goto('/admin/reports?type=sales');
            await expect(page.locator('.report-controls-card')).toHaveCount(0);
            await expect(page.locator('#report_search_input')).toHaveCount(0);
            await expect(page.locator('.report-table-wrapper')).toBeVisible();

            // Check Customers tab as well
            await page.goto('/admin/reports?type=customers');
            await expect(page.locator('.report-controls-card')).toHaveCount(0);
            await expect(page.locator('.report-table-wrapper')).toBeVisible();
        });

        test('should provide valid export links for Excel and PDF', async ({ page }) => {
            await page.goto('/admin/reports?type=sales&period=this_month');

            const excelLink = page.locator('#btn_export_excel');
            await expect(excelLink).toBeVisible();
            const excelHref = await excelLink.getAttribute('href');
            expect(excelHref).toContain('/admin/reports/export/excel');
            expect(excelHref).toContain('type=sales');

            const pdfLink = page.locator('#btn_export_pdf');
            await expect(pdfLink).toBeVisible();
            const pdfHref = await pdfLink.getAttribute('href');
            expect(pdfHref).toContain('/admin/reports/export/pdf');
            expect(pdfHref).toContain('type=sales');

            const printBtn = page.locator('#btn_print_report');
            await expect(printBtn).not.toBeVisible();
        });

        test('should maintain layout integrity under language switches', async ({ page }) => {
            await page.goto('/admin/reports?type=sales');

            // Switch to Khmer language
            const kmLink = page.locator('a[href*="/lang/km"]');
            if (await kmLink.count() > 0) {
                await kmLink.click();
                await page.waitForLoadState('domcontentloaded');
                await expect(page.locator('.reports-summary-grid')).toBeVisible();
            }

            // Switch back to English
            const enLink = page.locator('a[href*="/lang/en"]');
            if (await enLink.count() > 0) {
                await enLink.click();
                await page.waitForLoadState('domcontentloaded');
                await expect(page.locator('.reports-summary-grid')).toBeVisible();
            }
        });
    });

    test.describe('Role Based Access Control (RBAC)', () => {
        test('Manager role can access reports dashboard', async ({ page }) => {
            await page.goto('/login');
            await page.fill('#username_input', 'manager@bakery.com');
            await page.fill('#password_input', 'pass123');
            await page.click('#submit_btn');
            await expect(page).toHaveURL(/\/manager\/dashboard/);

            await page.goto('/admin/reports');
            await expect(page).toHaveURL(/\/admin\/reports/);
            await expect(page.locator('.reports-summary-grid')).toBeVisible();
        });

        test('Cashier role CANNOT access reports dashboard and is redirected', async ({ page }) => {
            await page.goto('/login');
            await page.fill('#username_input', 'cashier@bakery.com');
            await page.fill('#password_input', 'pass123');
            await page.click('#submit_btn');
            await expect(page).toHaveURL(/\/cashier\/dashboard/);

            // Attempt to directly navigate to reports
            await page.goto('/admin/reports');
            await expect(page).not.toHaveURL(/\/admin\/reports/);
            await expect(page).toHaveURL(/\/cashier\/dashboard/);
        });
    });
});
