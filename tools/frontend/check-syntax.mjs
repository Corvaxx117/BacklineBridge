import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
function check(directory) {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        const file = resolve(directory, entry.name);
        if (entry.isDirectory()) check(file);
        else if (/\.(js|mjs)$/.test(entry.name)) {
            const result = spawnSync(process.execPath, ['--input-type=module', '--check'], {
                input: readFileSync(file),
                stdio: ['pipe', 'inherit', 'inherit'],
            });
            if (result.status !== 0) process.exit(result.status || 1);
        }
    }
}
check('public/scripts');
check('tools/frontend');

const worker = spawnSync(process.execPath, ['--check', 'public/service-worker.js'], {
    stdio: 'inherit',
});
if (worker.status !== 0) process.exit(worker.status || 1);
