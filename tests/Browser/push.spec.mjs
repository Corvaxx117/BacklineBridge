import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

async function login(page) {
    const data = JSON.parse(readFileSync('var/browser/state.json', 'utf8'));
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL('**/concerts');
    await page.goto('/notifications');
    await page.locator('[data-push-settings] summary').click();
}

test('activation volontaire, test personnel et désactivation sans solliciter un vrai relais', async ({
    page,
}) => {
    await page.addInitScript(() => {
        let subscription = null;
        window.pushPermissionsRequested = 0;
        window.pushSubscriptionsCreated = 0;
        Object.defineProperty(window, 'Notification', {
            value: {
                permission: 'default',
                requestPermission() {
                    window.pushPermissionsRequested++;
                    this.permission = 'granted';
                    return Promise.resolve('granted');
                },
            },
        });
        const registration = {
            pushManager: {
                getSubscription: async () => subscription,
                subscribe: async ({ applicationServerKey }) => {
                    window.pushSubscriptionsCreated++;
                    subscription = {
                        options: { applicationServerKey: applicationServerKey.buffer },
                        unsubscribe: async () => {
                            subscription = null;
                            return true;
                        },
                        toJSON: () => ({
                            endpoint: 'https://fcm.googleapis.com/wp/fake',
                            keys: { p256dh: 'fake', auth: 'fake' },
                        }),
                    };
                    return subscription;
                },
            },
        };
        Object.defineProperty(navigator, 'serviceWorker', {
            value: {
                register: async () => registration,
                getRegistration: async () => registration,
                ready: Promise.resolve(registration),
            },
        });
    });
    let enabled = false;
    let tests = 0;
    let subscriptions = 0;
    await page.route('**/notifications/appareil', async (route) => {
        const method = route.request().method();
        if (method === 'POST' && ++subscriptions === 1) {
            await route.fulfill({
                status: 409,
                contentType: 'text/html',
                body: '<p>Another account</p>',
            });
            return;
        }
        if (method === 'POST') enabled = true;
        if (method === 'DELETE') enabled = false;
        await route.fulfill({
            json:
                method === 'GET'
                    ? { available: true, enabled, publicKey: 'BAAB_w', token: 'synthetic-token' }
                    : { enabled },
        });
    });
    await page.route('**/notifications/appareil/test', async (route) => {
        tests++;
        await route.fulfill({ status: 202, json: { queued: true } });
    });
    await login(page);
    const panel = page.locator('[data-push-settings]');
    await expect(
        panel.getByRole('button', { name: 'Activer sur cet appareil', exact: true }),
    ).toBeVisible();
    expect(await page.evaluate(() => window.pushPermissionsRequested)).toBe(0);
    await panel.getByRole('button', { name: 'Activer sur cet appareil', exact: true }).click();
    await expect(panel.locator('[data-push-status]')).toContainText('Notifications activées');
    expect(await page.evaluate(() => window.pushPermissionsRequested)).toBe(1);
    expect(await page.evaluate(() => window.pushSubscriptionsCreated)).toBe(2);
    await panel.getByRole('button', { name: 'Envoyer un test' }).click();
    await expect(panel.locator('[data-push-status]')).toContainText('Test programmé');
    expect(tests).toBe(1);
    await panel.getByRole('button', { name: 'Désactiver', exact: true }).click();
    await expect(panel.locator('[data-push-status]')).toContainText('Notifications désactivées');
    expect(enabled).toBe(false);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
});

test('iPhone affiche les étapes d’installation avant toute demande de permission', async ({
    page,
}) => {
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'userAgent', { value: 'iPhone Safari' });
        Object.defineProperty(navigator, 'standalone', { value: false });
    });
    await login(page);
    const panel = page.locator('[data-push-settings]');
    await expect(panel.locator('[data-push-install]')).toBeVisible();
    await expect(panel.locator('[data-push-install]')).toContainText('Sur l’écran d’accueil');
    await expect(panel.locator('[data-push-enable]')).toBeHidden();
});
