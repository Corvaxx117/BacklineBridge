import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('les propositions Orfeo restent facultatives et protègent la saisie avant sauvegarde', async ({
    page,
}) => {
    const data = JSON.parse(readFileSync('var/browser/state.json', 'utf8'));
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await page.waitForURL('**/concerts');
    await expect(page.locator('.concert-availability-link')).toHaveCount(0);
    await page.goto(`/concerts/${data.concertId}`);
    await expect(page.locator('#disponibilites')).toHaveCount(0);
    await page.goto(`/concerts/${data.concertId}/conditions#orfeo-conditions`);
    await expect(page.locator('#orfeo-conditions')).toHaveAttribute('open', '');
    const field = (name) => page.locator(`[name="concert_conditions[${name}]"]`);
    const copy = (name) =>
        page.locator(`[data-condition-copy][data-target="concert_conditions_${name}"]`).first();
    await field('accommodationNotes').fill('Mon accord déjà saisi');
    await copy('accommodationNotes').click();
    await expect(field('accommodationNotes')).toHaveValue('Mon accord déjà saisi');
    await expect(copy('accommodationNotes')).toHaveText('Remplacer ma saisie');
    await page.getByRole('button', { name: 'Annuler le remplacement', exact: true }).click();
    await expect(field('accommodationNotes')).toHaveValue('Mon accord déjà saisi');
    await copy('accommodationNotes').click();
    await copy('accommodationNotes').click();
    await expect(field('accommodationNotes')).toHaveValue(/Deux chambres à confirmer/);
    for (const name of ['transportNotes', 'mealNotes']) {
        await field(name).fill('');
        await copy(name).click();
        await expect(field(name)).not.toHaveValue('');
    }
    await field('feeAmount').fill('');
    await page
        .getByRole('button', { name: 'Utiliser le montant TTC du contrat Orfeo', exact: true })
        .click();
    await expect(field('feeAmount')).toHaveValue('2160.00');
    await expect(field('feeBasis')).toHaveValue('ttc');
    await page
        .getByRole('button', { name: 'Utiliser le montant HT du contrat Orfeo', exact: true })
        .click();
    await expect(field('feeAmount')).toHaveValue('2160.00');
    await page
        .getByRole('button', {
            name: 'Remplacer ma saisie par cette proposition Orfeo',
            exact: true,
        })
        .click();
    await expect(field('feeAmount')).toHaveValue('1800.00');
    await expect(field('feeBasis')).toHaveValue('ht');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
    await page.getByRole('button', { name: 'Enregistrer les conditions', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/concerts/${data.concertId}#concert-conditions$`));
    await expect(page.locator('.conditions-overview')).toContainText('1 800,00 € HT');
    await expect(page.locator('.conditions-overview')).toContainText('Repas végétariens');
    await expect(page.locator('#disponibilites')).toHaveCount(0);
});
