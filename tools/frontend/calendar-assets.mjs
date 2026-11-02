import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const checking = process.argv.includes('--check');
const files = {
    'all/global.js': 'fullcalendar.js',
    'locales/fr/global.js': 'fr.js',
    'themes/classic/global.js': 'theme.js',
    'skeleton.css': 'skeleton.css',
    'themes/classic/theme.css': 'theme.css',
    'themes/classic/palette.css': 'palette.css',
    'LICENSE.md': 'LICENSE.md',
};

for (const [source, destination] of Object.entries(files)) {
    const contents = await readFile(resolve(root, 'node_modules/fullcalendar', source));
    const target = resolve(root, 'public/vendor/fullcalendar', destination);
    if (checking) {
        const existing = await readFile(target).catch(() => null);
        if (!existing || !existing.equals(contents)) {
            throw new Error(`Régénérer ${destination} avec npm run calendar:assets.`);
        }
    } else {
        await mkdir(dirname(target), { recursive: true });
        await writeFile(target, contents);
    }
}

console.log(
    checking ? 'Fichiers FullCalendar conformes au verrou npm.' : 'Fichiers FullCalendar copiés.',
);
