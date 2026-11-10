import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('les contacts et les notes se modifient séparément sans débordement sur mobile', async ({
    page,
}) => {
    const data = JSON.parse(readFileSync('var/browser/state.json', 'utf8'));
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await page.waitForURL('**/concerts');
    await page.goto('/');
    await page.getByRole('button', { name: 'Tout déplier', exact: true }).click();
    const scale = await page.locator('.stage-drums').evaluate((element) => {
        const matrix = new DOMMatrixReadOnly(getComputedStyle(element).transform);
        return Math.hypot(matrix.a, matrix.b);
    });
    expect(scale).toBeCloseTo(1.12, 2);
    await page.getByRole('link', { name: 'Modifier les contacts', exact: true }).click();
    const name = page.locator('[name="technical_contacts[contacts][0][name]"]');
    const originalName = await name.inputValue();
    await name.fill('Contact modifié depuis le navigateur');
    await expect(page.locator('[name="technical_notes[notices]"]')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
    await page.getByRole('button', { name: 'Enregistrer les contacts', exact: true }).click();
    await expect(page.locator('.rider-contacts')).toContainText(
        'Contact modifié depuis le navigateur',
    );
    await page.getByRole('link', { name: 'Modifier les contacts', exact: true }).click();
    await name.fill(originalName);
    await page.getByRole('button', { name: 'Enregistrer les contacts', exact: true }).click();
    await page.getByRole('link', { name: 'Modifier les notes', exact: true }).click();
    const notes = page.locator('[name="technical_notes[notices]"]');
    const originalNotes = await notes.inputValue();
    await notes.fill('Note technique ajoutée depuis le navigateur.');
    await expect(page.locator('[name="technical_contacts[contacts][0][name]"]')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
    await page.getByRole('button', { name: 'Enregistrer les notes', exact: true }).click();
    await expect(page.locator('.technical-information-notes')).toContainText(
        'Note technique ajoutée depuis le navigateur.',
    );
    await page.getByRole('link', { name: 'Modifier les notes', exact: true }).click();
    await notes.fill(originalNotes);
    await page.getByRole('button', { name: 'Enregistrer les notes', exact: true }).click();
});
