import { readFileSync } from 'node:fs';

export const fixture = () => JSON.parse(readFileSync('var/browser/state.json', 'utf8'));

export async function login(page) {
    const data = fixture();
    await page.goto('/connexion');
    await page.locator('[name="_username"]').fill(data.email);
    await page.locator('[name="_password"]').fill(data.password);
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await page.waitForURL('**/concerts');
    return data;
}
