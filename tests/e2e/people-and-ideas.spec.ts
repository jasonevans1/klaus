import { expect, test } from '@playwright/test';

const stamp = Date.now();
const person = `Smoke Person ${stamp}`;
const idea = `Smoke Idea ${stamp}`;

const personRow = (page: import('@playwright/test').Page) => page.locator(`[data-person="${person}"]`);

test.describe.serial('people and ideas', () => {
    test('adds a person and shows them on the People page', async ({ page }) => {
        await page.goto('/people');
        await page.getByTestId('add-person-button').click();
        const modal = page.getByRole('dialog');
        await modal.getByLabel('Name').fill(person);
        await modal.getByTestId('save-person-button').click();
        await expect(personRow(page)).toBeVisible();
    });

    test('adds a year-round idea for that person on the Ideas page', async ({ page }) => {
        await page.goto('/ideas');
        await personRow(page).getByTestId('add-idea-button').click();
        const modal = page.getByRole('dialog');
        await modal.getByLabel('Title').fill(idea);
        await modal.getByTestId('save-idea-button').click();
        await expect(page.locator(`[data-idea="${idea}"]`)).toBeVisible();
    });

    test('deletes the idea from the Ideas page', async ({ page }) => {
        await page.goto('/ideas');
        const row = page.locator(`[data-idea="${idea}"]`);
        await expect(row).toBeVisible();
        await row.getByTestId('idea-menu-button').click();
        page.once('dialog', (d) => d.accept());
        await page.getByTestId('delete-idea-button').filter({ visible: true }).click();
        await expect(row).toHaveCount(0);
    });

    test('archives the person so they leave the active People list', async ({ page }) => {
        await page.goto('/people');
        await personRow(page).getByTestId('person-menu-button').click();
        await page.getByTestId('archive-person-button').filter({ visible: true }).click();
        await expect(personRow(page)).toHaveCount(0);
    });
});
