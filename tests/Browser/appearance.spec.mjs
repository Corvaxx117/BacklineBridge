import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = () => JSON.parse(readFileSync('var/browser/state.json', 'utf8'));
const isMoving = (background) =>
    background.evaluate((element) =>
        element
            .getAnimations({ subtree: true })
            .some((animation) => animation.playState === 'running'),
    );

test('le fond bouge dès l’ouverture et respecte la pause et les animations réduites', async ({
    page,
}) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/connexion');
    const background = page.locator('[data-ambient-background]');
    const toggle = page.getByRole('button', { name: 'Animation de fond', exact: true });
    await expect.poll(() => isMoving(background)).toBe(true);
    const light = background.locator('.ambient-light-warm');
    const start = await light.boundingBox();
    // Vérifier un déplacement visible, pas seulement un moteur déclaré « running ».
    await expect
        .poll(
            async () => {
                const current = await light.boundingBox();
                return Math.hypot(current.x - start.x, current.y - start.y);
            },
            { timeout: 2500, intervals: [200] },
        )
        .toBeGreaterThan(12);
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expect.poll(() => isMoving(background)).toBe(false);
    await page.reload();
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expect.poll(() => isMoving(background)).toBe(false);
    await toggle.click();
    await expect.poll(() => isMoving(background)).toBe(true);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(toggle).toBeHidden();
    await expect.poll(() => isMoving(background)).toBe(false);
    await expect(page.locator('#login_email')).toBeVisible();
});

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
