import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = () => JSON.parse(readFileSync('var/browser/state.json', 'utf8'));
test('la référence se replie au clavier et retrouve la section de travail', async ({ page }) => {
    const data = fixture();
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL('**/concerts');
    await page.goto('/');
    const sections = page.locator('[data-sheet-sections] > [data-sheet-section]');
    await expect
        .poll(() => sections.evaluateAll((elements) => elements.every((element) => !element.open)))
        .toBe(true);
    const patch = page.locator('#sheet-patch');
    await patch.locator(':scope > summary').focus();
    await page.keyboard.press('Enter');
    await expect(patch).toHaveAttribute('open', '');
    await expect(page.locator('.patch-table')).toBeVisible();
    await page.goto('/concerts');
    await page.goto('/');
    await expect(patch).toHaveAttribute('open', '');
    await page.getByRole('button', { name: 'Tout déplier', exact: true }).click();
    await expect
        .poll(() => sections.evaluateAll((elements) => elements.every((element) => element.open)))
        .toBe(true);
    await expect(page.locator('[data-stage-zoom-value]')).toBeVisible();
    await page.getByRole('button', { name: 'Tout replier', exact: true }).click();
    await expect
        .poll(() => sections.evaluateAll((elements) => elements.every((element) => !element.open)))
        .toBe(true);
    await page.goto('/#equipment-title');
    await expect(page.locator('#sheet-equipment')).toHaveAttribute('open', '');
    await expect(page.locator('.equipment-items').first()).toBeVisible();
});
