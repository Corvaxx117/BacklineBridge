import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '*.spec.mjs',
    workers: 1,
    timeout: 30000,
    outputDir: 'var/browser/results',
    reporter: [['list'], ['html', { outputFolder: 'var/browser/report', open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8877',
        channel: process.env.CI ? undefined : 'chrome',
        launchOptions: { args: ['--disable-gpu'] },
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'ordinateur', use: { viewport: { width: 1440, height: 1000 } } },
        {
            name: 'mobile',
            use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true },
        },
    ],
    webServer: {
        command: 'node tools/frontend/browser-server.mjs',
        url: 'http://127.0.0.1:8877/connexion',
        timeout: 120000,
        reuseExistingServer: false,
    },
});
