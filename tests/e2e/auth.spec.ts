import { expect, test, type Page } from '@playwright/test';
import { login } from './support/login';

const guest = { cookies: [], origins: [] };

test.describe('guest', () => {
    test.use({ storageState: guest });

    test('redirects a guest from the dashboard to the login page', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('rejects a wrong password with an error message', async ({ page }) => {
        await login(page, 'nobody@example.com', 'wrong-password');
        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByText(/do not match/i)).toBeVisible();
    });
});

// Login and logout share one real login: Fortify allows 5 login POSTs/min, and setup uses one.
test.describe.serial('real session', () => {
    test.use({ storageState: guest });
    let page: Page;

    test.beforeAll(async ({ browser, baseURL }) => {
        page = await (await browser.newContext({ baseURL, ignoreHTTPSErrors: true, storageState: guest })).newPage();
    });
    test.afterAll(() => page.context().close());

    test('logs in with the seeded user and lands on the dashboard', async () => {
        await login(page);
        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page).toHaveTitle(/Dashboard/);
    });

    test('logs out from the user menu and returns to a guest page', async () => {
        await page.getByTestId('sidebar-menu-button').click();
        await page.getByTestId('logout-button').filter({ visible: true }).click();
        await expect(page).not.toHaveURL(/\/dashboard/);
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });
});

test('shows the dashboard heading after login', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('[data-household-name]')).toBeVisible();
});
