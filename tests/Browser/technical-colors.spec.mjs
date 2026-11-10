import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('var/browser/state.json', 'utf8'));

async function login(page) {
    const data = fixture();
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await page.waitForURL('**/concerts');
}

test('le tri par famille conserve les canaux et revient à leur ordre numérique', async ({
    page,
}, testInfo) => {
    await login(page);
    await page.goto('/sources/new');
    const channel = testInfo.project.name === 'ordinateur' ? '31' : '32';
    await page.locator('[name="patch_line[channelNumber]"]').fill(channel);
    await page.locator('[name="patch_line[sourceName]"]').fill('DRUM EXTRA ' + channel);
    await page.locator('[name="patch_line[section]"]').selectOption('drums');
    await page.locator('form[name="patch_line"]').evaluate((form) => form.requestSubmit());
    await page.waitForURL('**/');
    const section = page.locator('#sheet-patch');
    if (!(await section.evaluate((element) => element.open)))
        await section.locator(':scope > summary').click();
    const rows = page.locator('.patch-table tbody tr');
    await expect(rows.last()).toHaveAttribute('data-channel', channel);
    const original = await rows.evaluateAll((elements) =>
        elements.map((element) => element.dataset.channel),
    );
    await page.getByRole('button', { name: 'Par famille', exact: true }).click();
    await expect(rows.nth(9)).toHaveAttribute('data-channel', '31');
    const grouped = await rows.evaluateAll((elements) =>
        elements.map((element) => element.dataset.channel),
    );
    expect([...grouped].sort()).toEqual([...original].sort());
    await page.getByRole('button', { name: 'Par canal', exact: true }).click();
    expect(
        await rows.evaluateAll((elements) => elements.map((element) => element.dataset.channel)),
    ).toEqual(original);
});

test('les familles sont colorées dans les deux thèmes et les coordonnées restent consultables', async ({
    page,
}) => {
    await login(page);
    await page.goto('/');
    await page.getByRole('button', { name: 'Tout déplier', exact: true }).click();
    for (const theme of ['light', 'dark']) {
        await page.evaluate((theme) => (document.documentElement.dataset.theme = theme), theme);
        const colors = await page
            .locator('.patch-table tr[data-channel="1"], .patch-table tr[data-channel="12"]')
            .evaluateAll((rows) => rows.map((row) => getComputedStyle(row).backgroundColor));
        expect(colors[0]).not.toBe(colors[1]);
        expect(colors.every((color) => color !== 'rgba(0, 0, 0, 0)')).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
            true,
        );
    }
    await expect(page.locator('.rider-contacts')).toContainText('Martin fictif');
    await expect(page.locator('.stage-drums')).toHaveAttribute('data-technical-family', 'drums');
    await expect(page.locator('.stage-amp[data-technical-family="guitar"]')).toHaveCount(2);
});
