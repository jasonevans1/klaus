import { expect, test, type Page } from '@playwright/test';

const run = Date.now().toString(36);
const person = `E2E Flow ${run}`;
const gift = `E2E Gift ${run}`;
let occasion = '';

async function openMenuItem(page: Page, row: string, menu: string, item: string) {
    await page.locator(row).getByTestId(menu).click();
    await page.getByTestId(item).filter({ visible: true }).click();
}

test.describe.serial('occasion gift flow', () => {
    let page: Page;

    test.beforeAll(async ({ browser, baseURL }) => {
        const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true, storageState: 'tests/e2e/.auth/user.json' });
        page = await context.newPage();
    });

    test.afterAll(async () => {
        // Cleanup runs even if a step failed: delete the occasion if still listed, archive the person.
        if (occasion) {
            await page.goto('/occasions');
            if (await page.locator(`[data-occasion="${occasion}"]`).count()) {
                page.once('dialog', (d) => d.accept());
                await openMenuItem(page, `[data-occasion="${occasion}"]`, 'occasion-menu-button', 'delete-occasion-button');
                await expect(page.locator(`[data-occasion="${occasion}"]`)).toHaveCount(0);
            }
        }
        await page.goto('/people');
        if (await page.locator(`[data-person="${person}"]`).count()) {
            await openMenuItem(page, `[data-person="${person}"]`, 'person-menu-button', 'archive-person-button');
            await expect(page.locator(`[data-person="${person}"]`)).toHaveCount(0);
        }
        await page.context().close();
    });

    test('creates a Christmas occasion and opens its page', async () => {
        await page.goto('/occasions');
        await page.getByTestId('new-christmas-button').click();
        await page.getByTestId('create-christmas-button').click();
        const heading = page.getByRole('heading', { level: 1 });
        await expect(heading).toHaveText(/^Christmas \d{4}$/);
        occasion = (await heading.textContent())!.trim();
        await expect(page).toHaveURL(/\/occasions\/\d+$/);
    });

    test('adds a person to the occasion', async () => {
        await page.goto('/people');
        await page.getByTestId('add-person-button').click();
        await page.getByLabel('Name', { exact: true }).fill(person);
        await page.getByTestId('save-person-button').click();
        await expect(page.locator(`[data-person="${person}"]`)).toBeVisible();

        await page.goto('/occasions');
        await page.locator(`[data-occasion="${occasion}"]`).getByRole('link', { name: occasion }).click();
        await page.getByTestId('add-people-button').first().click();
        await page.locator(`[data-available="${person}"]`).click();
        await page.getByTestId('add-selected-button').click();
        await expect(page.locator(`[data-person="${person}"]`)).toBeVisible();
    });

    test('adds a gift for that person on the occasion', async () => {
        await page.locator(`[data-person="${person}"] + div`).getByTestId('add-gift-button').click();
        await page.getByLabel('Title').fill(gift);
        await page.getByRole('textbox').and(page.locator('[inputmode="decimal"]')).fill('25');
        await page.getByTestId('save-gift-button').click();
        const row = page.locator(`[data-gift="${gift}"]`);
        await expect(row).toBeVisible();
        await expect(row).toHaveAttribute('data-price', '$25');
        await expect(row).toHaveAttribute('data-status', 'Idea');
    });

    test('marks the gift bought from the shopping list', async () => {
        await page.getByTestId('shopping-list-link').click();
        await expect(page).toHaveURL(/\/shopping$/);
        await page.locator(`[data-idea="${gift}"]`).getByTestId('mark-bought-button').click();
        await expect(page.locator(`[data-idea="${gift}"]`)).toHaveCount(0);
    });

    test('marks the bought gift wrapped on the wrapping checklist', async () => {
        await page.goBack();
        await page.getByTestId('wrapping-checklist-link').click();
        await expect(page).toHaveURL(/\/wrapping$/);
        const row = page.locator(`[data-wrap="${gift}"]`);
        await expect(row).toHaveAttribute('data-wrapped', 'no');
        await row.getByTestId('wrap-checkbox').click();
        await expect(row).toHaveAttribute('data-wrapped', 'yes');
    });

    test('deletes the occasion and removes it from the Occasions list', async () => {
        await page.goto('/occasions');
        page.once('dialog', (d) => d.accept());
        await openMenuItem(page, `[data-occasion="${occasion}"]`, 'occasion-menu-button', 'delete-occasion-button');
        await expect(page.locator(`[data-occasion="${occasion}"]`)).toHaveCount(0);
    });
});
