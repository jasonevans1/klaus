import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['html', { open: 'never' }], ['list']] : 'list',
    use: {
        baseURL: process.env.BASE_URL ?? 'https://klaus.ddev.site',
        ignoreHTTPSErrors: true,
        testIdAttribute: 'data-test',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        {
            name: 'chromium',
            testIgnore: /auth\.setup\.ts/,
            use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/.auth/user.json' },
            dependencies: ['setup'],
        },
    ],
});
