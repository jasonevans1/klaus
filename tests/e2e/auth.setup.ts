import { test as setup } from '@playwright/test';
import { loginAndWait } from './support/login';

setup('log in once and save the session', async ({ page }) => {
    await loginAndWait(page);
    await page.context().storageState({ path: 'tests/e2e/.auth/user.json' });
});
