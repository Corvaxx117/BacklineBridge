import { test, expect } from '@playwright/test';
import { login } from './helpers/session.mjs';

test('Orfeo affiche l’attente, évite un double envoi et se réactive au retour', async ({
    page,
}) => {
    const data = await login(page);
    const path = `/groupes/${data.bandId}/concerts/import-orfeo`;
    let release;
    let submissions = 0;
    const pending = new Promise((resolve) => (release = resolve));
    await page.route('**' + path, async (route) => {
        if (route.request().method() === 'POST') {
            submissions++;
            await pending;
        }
        await route.continue();
    });
    await page.goto(path);
    const button = page.locator('[data-import-submit]');
    const form = page.locator('[data-import-preview]');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    // Une navigation POST suspend les lectures Playwright du document courant.
    // Observer l’état dans l’événement submit, avant de laisser partir la requête.
    const feedback = page.waitForEvent('console', (message) =>
        message.text().startsWith('BB_PREVIEW_STATE:'),
    );
    await form.evaluate((element) => {
        let observed = false;
        element.addEventListener('submit', () => {
            if (observed) return;
            observed = true;
            const duplicate = new Event('submit', { bubbles: true, cancelable: true });
            element.dispatchEvent(duplicate);
            const indicator = element.querySelector('.import-loading-indicator');
            console.debug(
                'BB_PREVIEW_STATE:' +
                    JSON.stringify({
                        disabled: element.querySelector('[data-import-submit]').disabled,
                        label: element.querySelector('[data-import-label]').textContent,
                        busy: element.getAttribute('aria-busy'),
                        statusHidden: element.querySelector('[data-import-status]').hidden,
                        indicatorDisplay: getComputedStyle(indicator).display,
                        animation: getComputedStyle(indicator).animationName,
                        duplicatePrevented: duplicate.defaultPrevented,
                    }),
            );
        });
    });
    const click = button.click();
    try {
        const state = JSON.parse((await feedback).text().slice('BB_PREVIEW_STATE:'.length));
        expect(state.disabled).toBe(true);
        expect(state.label).toBe('Chargement en cours…');
        expect(state.busy).toBe('true');
        expect(state.statusHidden).toBe(false);
        expect(state.indicatorDisplay).not.toBe('none');
        expect(state.animation).toBe('none');
        expect(state.duplicatePrevented).toBe(true);
        await expect.poll(() => submissions).toBe(1);
    } finally {
        release();
    }
    await click;
    await expect(page).toHaveURL(new RegExp(path + '/[a-f0-9]{32}$'));
    await page.goBack();
    await expect(button).toBeEnabled();
    await expect(button).toContainText('Charger l’aperçu Orfeo');
    await expect(form).not.toHaveAttribute('aria-busy');
});

test('le bouton reste utilisable après une erreur pendant le chargement Orfeo', async ({
    page,
}) => {
    const data = await login(page);
    const path = `/groupes/${data.bandId}/concerts/import-orfeo`;
    await page.goto(path);
    const html = await page.content();
    await page.route('**' + path, async (route) => {
        if (route.request().method() === 'POST') {
            await route.fulfill({
                status: 503,
                contentType: 'text/html; charset=utf-8',
                body: html,
            });
        } else await route.continue();
    });
    await page.getByRole('button', { name: 'Charger l’aperçu Orfeo', exact: true }).click();
    await expect(page.locator('[data-import-submit]')).toBeEnabled();
    await expect(page.locator('[data-import-status]')).toBeHidden();
    await expect(page.locator('[data-import-preview]')).not.toHaveAttribute('aria-busy');
});
