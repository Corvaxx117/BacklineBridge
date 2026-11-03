import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = () => JSON.parse(readFileSync('var/browser/state.json', 'utf8'));
const bitmap = (canvas) =>
    canvas.evaluate((element) => {
        const pixels = element
            .getContext('2d')
            .getImageData(0, 0, element.width, element.height).data;
        let hash = 0;
        for (let index = 0; index < pixels.length; index += 16) {
            hash = (Math.imul(hash, 31) + pixels[index + 3]) >>> 0;
        }
        return hash;
    });

async function expectFrozen(page, canvas) {
    const before = await bitmap(canvas);
    await page.waitForTimeout(250);
    expect(await bitmap(canvas)).toBe(before);
}

async function expectMovement(canvas) {
    const before = await bitmap(canvas);
    await expect.poll(() => bitmap(canvas), { timeout: 2500, intervals: [200] }).not.toBe(before);
}

test('le fond bouge dès l’ouverture et respecte la pause et les animations réduites', async ({
    page,
}) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/connexion');
    const background = page.locator('[data-ambient-background]');
    const toggle = page.getByRole('button', { name: 'Animation de fond', exact: true });
    const canvas = background.locator('.ambient-canvas');
    await expect(canvas).toBeVisible();
    const size = await canvas.evaluate((element) => ({
        width: element.width,
        height: element.height,
    }));
    expect(size.width).toBeLessThanOrEqual(800);
    expect(size.height).toBeLessThanOrEqual(600);
    await expectMovement(canvas);
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expectFrozen(page, canvas);
    await page.reload();
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expectFrozen(page, canvas);
    await toggle.click();
    await expectMovement(canvas);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(toggle).toBeHidden();
    await expectFrozen(page, canvas);
    await expect(page.locator('#login_email')).toBeVisible();
});

test('Chrome affiche des images de fond différentes sans interaction', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/connexion');
    await page.addStyleTag({
        content:
            '.form-heading, .login-panel { animation: none !important; } input { caret-color: transparent !important; }',
    });
    const client = await page.context().newCDPSession(page);
    let first = null;
    let last = null;
    client.on('Page.screencastFrame', ({ data, sessionId }) => {
        void client.send('Page.screencastFrameAck', { sessionId });
        if (first === null) first = data;
        last = data;
    });
    try {
        await client.send('Page.startScreencast', { format: 'png', everyNthFrame: 6 });
        await expect.poll(() => first !== null).toBe(true);
        await page.waitForTimeout(2200);
        await client.send('Page.stopScreencast');
        const changedPixels = await page.evaluate(
            async ({ first, last }) => {
                async function pixels(data) {
                    const image = new Image();
                    image.src = `data:image/png;base64,${data}`;
                    await image.decode();
                    const canvas = document.createElement('canvas');
                    canvas.width = image.naturalWidth;
                    canvas.height = image.naturalHeight;
                    const context = canvas.getContext('2d');
                    context.drawImage(image, 0, 0);
                    return context.getImageData(0, 0, canvas.width, canvas.height).data;
                }
                const [before, after] = await Promise.all([pixels(first), pixels(last)]);
                let changed = 0;
                for (let index = 0; index < before.length; index += 4) {
                    const difference =
                        Math.abs(before[index] - after[index]) +
                        Math.abs(before[index + 1] - after[index + 1]) +
                        Math.abs(before[index + 2] - after[index + 2]);
                    if (difference > 3) changed++;
                }
                return changed;
            },
            { first, last },
        );
        expect(changedPixels).toBeGreaterThan(500);
    } finally {
        await client.detach();
    }
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
