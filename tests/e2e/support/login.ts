import { expect, type Page } from '@playwright/test';

export const email = process.env.E2E_EMAIL ?? 'test@example.com';
export const password = process.env.E2E_PASSWORD ?? 'password';

export async function login(page: Page, user = email, pass = password) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(user);
    await page.locator('input[name="password"]').fill(pass);
    await page.getByTestId('login-button').click();
}

export async function loginAndWait(page: Page) {
    await login(page);
    await expect(page).toHaveURL(/\/dashboard/);
}
