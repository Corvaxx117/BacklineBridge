import { createHash } from 'node:crypto';
import { format, resolveConfig } from 'prettier';
import { readFile, readdir, writeFile } from 'node:fs/promises';
import { dirname, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const checking = process.argv.includes('--check');
const hash = (data) => createHash('sha256').update(data).digest('hex').slice(0, 16);
async function files(directory) {
    const entries = await readdir(resolve(root, directory), { withFileTypes: true });
    const result = [];
    for (const entry of entries.sort((a, b) => a.name.localeCompare(b.name))) {
        const name = `${directory}/${entry.name}`;
        if (entry.isDirectory()) result.push(...(await files(name)));
        else if (entry.isFile()) result.push(name);
    }
    return result;
}
// Les dépendances des modules changent aussi d'URL lors de leur mise à jour.
for (const file of await files('public/scripts')) {
    if (!/\.(js|mjs)$/.test(file)) continue;
    const source = await readFile(resolve(root, file), 'utf8');
    const pattern = /from(\s*['"])(\.[^'"?]+\.mjs)(?:\?v=[a-f0-9]{16})?(['"])/g;
    const replacements = [];
    for (const match of source.matchAll(pattern)) {
        const dependency = resolve(root, dirname(file), match[2]);
        replacements.push([
            match[0],
            `from${match[1]}${match[2]}?v=${hash(await readFile(dependency))}${match[3]}`,
        ]);
    }
    let updated = source;
    for (const [before, after] of replacements) updated = updated.replace(before, after);
    if (updated !== source) {
        updated = await format(updated, {
            ...(await resolveConfig(resolve(root, file))),
            filepath: resolve(root, file),
        });
        if (checking) throw new Error(`Régénérer les imports de ${file} avec npm run assets.`);
        await writeFile(resolve(root, file), updated);
    }
}
const manifest = {};
for (const directory of ['public/styles', 'public/scripts', 'public/vendor', 'public/icons']) {
    for (const file of await files(directory)) {
        if (!/\.(css|js|mjs|svg|png|ico|woff2?)$/.test(file)) continue;
        const url = `/${relative('public', file)}`;
        manifest[url] = `${url}?v=${hash(await readFile(resolve(root, file)))}`;
    }
}
for (const name of ['favicon.svg', 'favicon.ico', 'apple-touch-icon.png']) {
    manifest[`/${name}`] = `/${name}?v=${hash(await readFile(resolve(root, 'public', name)))}`;
}
const renderFiles = [
    ...(await files('templates/pdf')),
    ...(await files('src/Service/Pdf')),
    'templates/stage/_drum-kit.svg.twig',
    'templates/stage/_audio-symbol.svg.twig',
];
manifest.__pdf = hash(
    Buffer.concat(await Promise.all(renderFiles.map((name) => readFile(resolve(root, name))))),
);
const target = resolve(root, 'public/asset-manifest.json');
const output = await format(JSON.stringify(manifest), {
    ...(await resolveConfig(target)),
    filepath: target,
});
if (checking) {
    if ((await readFile(target, 'utf8').catch(() => '')) !== output) {
        throw new Error('Régénérer les versions avec npm run assets.');
    }
} else {
    await writeFile(target, output);
}
console.log(checking ? 'Versions des fichiers conformes.' : 'Versions des fichiers générées.');
