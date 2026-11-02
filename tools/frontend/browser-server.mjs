import { mkdirSync, rmSync } from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { resolve } from 'node:path';

const directory = resolve('var/browser');
mkdirSync(directory, { recursive: true, mode: 0o700 });
const database = `${directory}/backlinebridge-browser-${randomBytes(8).toString('hex')}.db`;
const php = process.env.BB_BROWSER_PHP || 'php';
const env = {
    ...process.env,
    APP_ENV: 'browser',
    APP_DEBUG: '1',
    APP_DEMO_MODE: '0',
    APP_SECRET: 'synthetic-browser-tests-only',
    PROFILE_DATA_KEY: 'ab'.repeat(32),
    GOOGLE_CALENDAR_BAND_ID: '0',
    DATABASE_URL: `sqlite:///${database}`,
};
const prepared = spawnSync(php, ['tests/Browser/prepare.php'], { env, stdio: 'inherit' });
if (prepared.status !== 0) process.exit(prepared.status || 1);
const server = spawn(php, ['-S', '127.0.0.1:8877', '-t', 'public', 'tests/Browser/router.php'], {
    env,
    stdio: 'inherit',
});
const stop = () => {
    server.kill('SIGTERM');
};
process.on('SIGTERM', stop);
process.on('SIGINT', stop);
server.on('exit', (code) => {
    for (const suffix of ['', '-shm', '-wal']) rmSync(database + suffix, { force: true });
    process.exit(code || 0);
});
