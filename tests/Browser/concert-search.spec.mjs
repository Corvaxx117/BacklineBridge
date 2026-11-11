import { test, expect } from '@playwright/test';
import { login } from './helpers/session.mjs';

test('la recherche conserve les deux tris et se réinitialise sans débordement', async ({
    page,
}) => {
    const data = await login(page);
    const search = page.getByRole('searchbox', { name: 'Rechercher un concert' });
    await expect(page.locator('.concert-level-filters')).toHaveCount(0);
    await expect(
        page.getByRole('navigation', { name: 'Trier les concerts' }).getByRole('link'),
    ).toHaveCount(2);
    for (const theme of ['light', 'dark']) {
        await page.evaluate((theme) => (document.documentElement.dataset.theme = theme), theme);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
            true,
        );
    }
    await search.fill('Ville fictive');
    await search.press('Enter');
    await expect(page.locator('#concert-' + data.concertId)).toBeVisible();
    await page.getByRole('link', { name: 'Enjeu le plus fort', exact: true }).click();
    await expect(search).toHaveValue('Ville fictive');
    expect(new URL(page.url()).searchParams.get('sort')).toBe('importance');
    await search.fill('Aucun résultat 74829');
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Aucun concert ne correspond');
    await expect(page.locator('.empty-state')).toHaveCount(0);
    await page.getByRole('link', { name: 'Effacer la recherche', exact: true }).click();
    await expect(search).toHaveValue('');
    await expect(page.locator('#concert-' + data.concertId)).toBeVisible();
    expect(new URL(page.url()).searchParams.get('sort')).toBe('importance');
});
