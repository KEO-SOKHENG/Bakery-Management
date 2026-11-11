import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// ── Setup ──
// Opt-in suite: needs a server on a disposable database seeded with the e2e_* accounts.
// E2E_BASE (default http://127.0.0.1:8010), E2E_PSQL + E2E_PG_URL to reset the two feature tables,
// E2E_FEATURE_OFF=1 when the server runs with LOGIN_APPROVAL_ENABLED=false.

const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8010';
const PASSWORD = 'E2e-Pass-123';
const FEATURE_OFF = process.env.E2E_FEATURE_OFF === '1';

test.skip(!process.env.E2E_PG_URL, 'Login approval e2e runs only against a disposable database');
test.describe.configure({ mode: 'serial' });

function resetFeatureTables() {
    execFileSync(process.env.E2E_PSQL || 'psql', [process.env.E2E_PG_URL, '-qc',
        'TRUNCATE login_approval_requests, user_devices'], { stdio: 'pipe' });
}

async function login(page, username) {
    await page.goto(BASE + '/login');
    await page.fill('#username_input', username);
    await page.fill('#password_input', PASSWORD);
    await page.click('#submit_btn');
    await page.waitForLoadState('domcontentloaded');
}

async function twoBrowsers(browser) {
    const a = await browser.newContext();
    const b = await browser.newContext();
    return { a, b, pa: await a.newPage(), pb: await b.newPage() };
}

test.beforeEach(() => resetFeatureTables());

// ── Feature on ──

test.describe('login approval (feature on)', () => {
    test.skip(FEATURE_OFF, 'feature-on scenarios');

    test('normal login is immediate when nobody is active', async ({ page }) => {
        await login(page, 'e2e_cashier');
        await expect(page).toHaveURL(/\/cashier\/dashboard/);
    });

    test('owner logs in from a new device without approval while an admin is active', async ({ browser }) => {
        const { a, b, pa, pb } = await twoBrowsers(browser);
        await login(pa, 'e2e_admin');
        await expect(pa).toHaveURL(/\/admin\/dashboard/);

        await login(pb, 'e2e_owner');
        await expect(pb).toHaveURL(/\/admin\/dashboard/);
        await expect(pb.locator('body')).not.toContainText('owner@e2e.test');
        await a.close(); await b.close();
    });

    test('admin approves a pending login and the waiting browser signs in', async ({ browser }) => {
        const { a, b, pa, pb } = await twoBrowsers(browser);
        await login(pa, 'e2e_admin');

        await login(pb, 'e2e_cashier');
        await expect(pb).toHaveURL(/\/login\/approval/);
        await expect(pb.locator('.approval-state-pending')).toBeVisible();

        const card = pa.locator('#notification_list .lac-item');
        await pa.click('#notification_bell_btn');
        await expect(card).toBeVisible({ timeout: 15000 }); // inside the opened bell dropdown
        await expect(card).toContainText('Cashier E2E');
        await expect(card).toContainText('Chrome');
        await expect(card).toContainText('127.0.0.1');

        await card.locator('.lac-approve').click();
        await expect(card.locator('.lac-confirm')).toBeVisible(); // two-step approve
        await card.locator('.lac-approve').click();
        await expect(card).toHaveCount(0);

        await expect(pb).toHaveURL(/\/cashier\/dashboard/, { timeout: 10000 });
        await a.close(); await b.close();
    });

    test('admin rejects a pending login', async ({ browser }) => {
        const { a, b, pa, pb } = await twoBrowsers(browser);
        await login(pa, 'e2e_admin');
        await login(pb, 'e2e_cashier');

        const card = pa.locator('#notification_list .lac-item');
        await pa.click('#notification_bell_btn');
        await expect(card).toBeVisible({ timeout: 15000 }); // inside the opened bell dropdown
        await card.locator('.lac-reject').click();

        await expect(pb.locator('.approval-state-rejected')).toBeVisible({ timeout: 10000 });
        await pb.goto(BASE + '/cashier/dashboard');
        await expect(pb).toHaveURL(/\/login/);
        await a.close(); await b.close();
    });

    test('an unanswered request expires and cannot be approved', async ({ browser }) => {
        test.setTimeout(90000);
        const { a, b, pa, pb } = await twoBrowsers(browser);
        await login(pa, 'e2e_admin');
        await login(pb, 'e2e_cashier');
        await expect(pa.locator('#notification_list .lac-item')).toHaveCount(1, { timeout: 15000 });

        // Server runs with LOGIN_APPROVAL_TTL=20 for this suite.
        await expect(pb.locator('.approval-state-expired')).toBeVisible({ timeout: 40000 });
        await expect(pa.locator('#notification_list .lac-item')).toHaveCount(0, { timeout: 15000 });
        await a.close(); await b.close();
    });

    test('roles that are not approvers get no card and are refused by the server', async ({ browser }) => {
        const { a, b, pa, pb } = await twoBrowsers(browser);
        const c = await browser.newContext();
        const pc = await c.newPage();

        // Baker first: once an admin is active, the baker would need approval too.
        await login(pc, 'e2e_baker');
        await expect(pc).toHaveURL(/\/admin\/production/);
        await login(pa, 'e2e_admin');
        await expect(pa).toHaveURL(/\/admin\/dashboard/); // baker can't approve, so admin isn't held
        await login(pb, 'e2e_cashier');
        await expect(pb).toHaveURL(/\/login\/approval/);
        await expect(pa.locator('#notification_list .lac-item')).toHaveCount(1, { timeout: 15000 });

        await expect(pc.locator('script[src*="login-approval.js"]')).toHaveCount(0);
        const result = await pc.evaluate(async () => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const pending = await fetch('/security/login-approvals/pending', { headers: { Accept: 'application/json' } }).then(r => r.json());
            const res = await fetch('/security/login-approvals/00000000-0000-4000-8000-000000000000/approve', {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            return { pendingCount: pending.requests.length, status: res.status };
        });
        expect(result).toEqual({ pendingCount: 0, status: 403 });

        // An approver cannot approve a request ID that does not exist.
        // (Cross-site/no-token CSRF refusal is covered by PHPUnit; Laravel 13 accepts same-origin fetches.)
        const unknown = await pa.evaluate(async () => (await fetch('/security/login-approvals/00000000-0000-4000-8000-000000000000/approve', {
            method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        })).status);
        expect(unknown).toBe(404);

        await expect(pb).toHaveURL(/\/login\/approval/);
        await a.close(); await b.close(); await c.close();
    });

    test('sign-out through the confirm modal uses POST and ends the session', async ({ page }) => {
        await login(page, 'e2e_cashier');
        await page.getByRole('button', { name: 'User Account Menu' }).click();
        await page.locator('.profile-menu-signout').click();
        await expect(page.locator('#btn_confirm_logout')).toBeVisible();
        const [req] = await Promise.all([
            page.waitForRequest(r => r.url().endsWith('/logout')),
            page.click('#btn_confirm_logout'),
        ]);
        expect(req.method()).toBe('POST');
        await expect(page).toHaveURL(/\/login/);
        await page.goto(BASE + '/cashier/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });
});

// ── Feature off ──

test.describe('login approval (feature off)', () => {
    test.skip(!FEATURE_OFF, 'run with E2E_FEATURE_OFF=1 against a server with LOGIN_APPROVAL_ENABLED=false');

    test('existing login behaviour is preserved', async ({ browser }) => {
        const { a, b, pa, pb } = await twoBrowsers(browser);
        await login(pa, 'e2e_admin');
        await login(pb, 'e2e_cashier');
        await expect(pb).toHaveURL(/\/cashier\/dashboard/);
        await pa.waitForTimeout(11000);
        await expect(pa.locator('#notification_list .lac-item')).toHaveCount(0);
        await a.close(); await b.close();
    });
});
