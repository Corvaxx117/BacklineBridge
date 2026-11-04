import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('la cloche reste accessible et consulter une nouveauté la marque comme lue', async ({
    page,
}) => {
    const data = JSON.parse(readFileSync('var/browser/state.json', 'utf8'));
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL('**/concerts');
    const bell = page.locator('[data-notification-bell]');
    await expect(bell).toBeVisible();
    await bell.click();
    await expect(page).toHaveURL(/\/notifications$/);
    await expect(page.locator('.notification-action').first()).toContainText('Membre fictif');
    const card = page.locator('.notification-card').first();
    if (!(await card.getAttribute('class')).includes('notification-card--unread')) {
        await card.getByRole('button', { name: 'Marquer comme non lu', exact: true }).click();
    }
    await expect(bell).toHaveAttribute('data-unread', '1');
    await page
        .locator('.notification-card')
        .first()
        .getByRole('button', { name: 'Consulter' })
        .click();
    await expect(page).toHaveURL(new RegExp(`/concerts/${data.concertId}$`));
    await expect(bell).toHaveAttribute('data-unread', '0');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
});
