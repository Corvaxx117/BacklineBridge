import { build } from 'esbuild';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const checking = process.argv.includes('--check');
const files = {
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

const bundle = await build({
    entryPoints: [resolve(root, 'tools/frontend/calendar-entry.mjs')],
    bundle: true,
    minify: true,
    format: 'iife',
    globalName: 'FullCalendar',
    target: ['es2020'],
    write: false,
    legalComments: 'inline',
});
const target = resolve(root, 'public/vendor/fullcalendar/fullcalendar.js');
const output = bundle.outputFiles[0].contents;
if (checking) {
    if (!(await readFile(target)).equals(Buffer.from(output))) {
        throw new Error('Régénérer le calendrier avec npm run calendar:assets.');
    }
} else {
    await writeFile(target, output);
}
