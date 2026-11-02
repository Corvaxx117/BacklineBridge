import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('var/browser/state.json', 'utf8'));

test('le plan conserve pivoter, annuler, rétablir et enregistrer', async ({ page }) => {
    const data = fixture();
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/concerts$/);
    const url = `/fiches/${data.sheetId}/plan-de-scene`;
    await page.goto(url);
    await expect(page.locator('[data-stage-save]')).toBeEnabled();
    await page.locator('[data-stage-element-list]').selectOption('2');
    const angle = page.locator('[data-stage-angle]');
    const initial = Number(await angle.inputValue());
    await page.locator('[data-stage-action="rotate-right"]').click();
    const expected = String((initial + 15) % 360);
    await expect(angle).toHaveValue(expected);
    await page.locator('[data-stage-action="undo"]').click();
    await expect(angle).toHaveValue(String(initial));
    await page.locator('[data-stage-action="redo"]').click();
    await expect(angle).toHaveValue(expected);
    await page.locator('[data-stage-save]').click();
    await expect(page.locator('.flash-success')).toContainText('enregistré');
    await page.goto(url);
    await expect(page.locator('[data-stage-save]')).toBeEnabled();
    await page.locator('[data-stage-element-list]').selectOption('2');
    await expect(page.locator('[data-stage-angle]')).toHaveValue(expected);
});

test('la salle renseigne le matériel par groupe et retrouve sa réponse', async ({ page }) => {
    await page.goto(fixture().invitation);
    await page.locator('[name="venue_reply[respondent]"]').fill('Régisseur fictif');
    await page.getByRole('button', { name: 'Toutes les demandes', exact: true }).click();
    const groups = page.locator('[data-reply-group]');
    await expect(groups.first()).toBeVisible();
    for (const group of await groups.all()) {
        if (!(await group.evaluate((element) => element.open)))
            await group.locator(':scope > summary').click();
        await group.locator('[data-bulk-scope]').selectOption('all');
        await group.locator('[data-bulk-status="available"]').click();
    }
    await page.locator('[data-reply-save-button]').click();
    await expect(page.locator('.flash-success')).toContainText('enregistr');
    await page.reload();
    await expect(page.locator('[name="venue_reply[respondent]"]')).toHaveValue('Régisseur fictif');
    await expect(page.locator('[data-answer-row][data-answer-state="unknown"]')).toHaveCount(0);
});
