import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import {
    applicationKey,
    deviceSupport,
    sameKey,
} from '../../public/scripts/notifications/push-utils.mjs';

const supported = {
    secure: true,
    worker: true,
    push: true,
    notification: true,
    ios: false,
    standalone: false,
};

test('iPhone requires home-screen installation while desktop uses feature detection', () => {
    assert.equal(deviceSupport(supported), 'supported');
    assert.equal(deviceSupport({ ...supported, ios: true }), 'install');
    assert.equal(deviceSupport({ ...supported, ios: true, standalone: true }), 'supported');
    assert.equal(deviceSupport({ ...supported, secure: false }), 'insecure');
    assert.equal(deviceSupport({ ...supported, push: false }), 'unsupported');
});

test('VAPID application key detects obsolete browser subscriptions', () => {
    const encoded = 'BAAB_w';
    const bytes = applicationKey(encoded);
    assert.deepEqual([...bytes], [4, 0, 1, 255]);
    assert.equal(sameKey({ options: { applicationServerKey: bytes.buffer } }, encoded), true);
    assert.equal(sameKey({ options: { applicationServerKey: bytes.buffer } }, 'BAAC_w'), false);
    assert.equal(sameKey(null, encoded), false);
});

function worker() {
    const handlers = new Map();
    const notices = [];
    const opened = [];
    const self = {
        addEventListener: (name, handler) => handlers.set(name, handler),
        location: { origin: 'https://bb.example.test' },
        skipWaiting: () => Promise.resolve(),
        registration: {
            showNotification: async (title, options) => notices.push({ title, options }),
            getNotifications: async () => [],
        },
        clients: {
            claim: async () => {},
            matchAll: async () => [],
            openWindow: async (url) => opened.push(url),
        },
    };
    runInNewContext(
        readFileSync(new URL('../../public/service-worker.js', import.meta.url), 'utf8'),
        { self, URL },
    );
    const dispatch = async (name, details) => {
        let promise;
        handlers.get(name)({
            ...details,
            waitUntil: (value) => {
                promise = value;
            },
        });
        await promise;
    };
    return { handlers, notices, opened, self, dispatch };
}

test('every push shows a visible notification and never installs a page cache', async () => {
    const app = worker();
    await app.dispatch('push', {
        data: {
            json: () => ({
                title: 'Avee Mana',
                body: 'Julien a ajouté un document.',
                tag: 'bb-42',
            }),
        },
    });
    assert.equal(app.notices[0].options.tag, 'bb-42');
    assert.equal(app.notices[0].title, 'Avee Mana');
    assert.equal(app.handlers.has('fetch'), false);
    await app.dispatch('push', {
        data: {
            json: () => {
                throw new Error('Malformed payload');
            },
        },
    });
    assert.equal(app.notices[1].title, 'BacklineBridge');
});

test('notification clicks open only the authenticated inbox and never a payload URL', async () => {
    const app = worker();
    let closed = false;
    await app.dispatch('notificationclick', {
        notification: {
            data: { url: 'https://evil.example.test' },
            close: () => {
                closed = true;
            },
        },
    });
    assert.equal(closed, true);
    assert.deepEqual(app.opened, ['https://bb.example.test/notifications']);
});

test('logout closes notifications already displayed on this device', async () => {
    const app = worker();
    let closed = 0;
    app.self.registration.getNotifications = async () => [
        {
            close: () => {
                closed++;
            },
        },
    ];
    await app.dispatch('message', { data: { type: 'BB_LOGOUT' } });
    assert.equal(closed, 1);
});
