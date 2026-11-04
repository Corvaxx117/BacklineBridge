import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(
    new URL('../../public/scripts/notifications.js', import.meta.url),
    'utf8',
);
const flush = () => new Promise((resolve) => setImmediate(resolve));

function browser() {
    const documentEvents = new Map();
    const windowEvents = new Map();
    const timers = new Map();
    const badge = { hidden: true, textContent: '0' };
    const bell = {
        dataset: { countUrl: '/notifications/compteur', unread: '0' },
        attributes: {},
        querySelector: () => badge,
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
    const document = {
        visibilityState: 'visible',
        querySelector: () => bell,
        addEventListener: (name, handler) => documentEvents.set(name, handler),
    };
    let id = 0;
    let calls = 0;
    let response = { ok: true, status: 200, json: async () => ({ unread: 3 }) };
    const window = {
        addEventListener: (name, handler) => windowEvents.set(name, handler),
        setTimeout: (callback, delay) => {
            timers.set(++id, { callback, delay });
            return id;
        },
        clearTimeout: (value) => timers.delete(value),
    };
    vm.runInNewContext(source, {
        document,
        window,
        fetch: async () => {
            calls++;
            return response;
        },
    });
    return {
        document,
        documentEvents,
        windowEvents,
        timers,
        badge,
        bell,
        calls: () => calls,
        respond: (value) => {
            response = value;
        },
    };
}

test('le compteur attend une minute puis actualise son libellé accessible', async () => {
    const b = browser();
    assert.equal(b.calls(), 0);
    const timer = [...b.timers.values()][0];
    assert.equal(timer.delay, 60000);
    await timer.callback();
    assert.equal(b.bell.dataset.unread, '3');
    assert.equal(b.badge.textContent, '3');
    assert.equal(b.badge.hidden, false);
    assert.equal(b.bell.attributes['aria-label'], 'Notifications, 3 non lues');
});

test('aucune requête pendant que l’onglet est masqué ; reprise à son retour', async () => {
    const b = browser();
    b.document.visibilityState = 'hidden';
    b.documentEvents.get('visibilitychange')();
    assert.equal(b.timers.size, 0);
    await b.windowEvents.get('focus')();
    assert.equal(b.calls(), 0);
    b.document.visibilityState = 'visible';
    b.documentEvents.get('visibilitychange')();
    await flush();
    assert.equal(b.calls(), 1);
    assert.equal(b.timers.size, 1);
});

test('une session expirée arrête les interrogations au lieu de lire la page de connexion', async () => {
    const b = browser();
    b.respond({ redirected: true, status: 200 });
    await b.windowEvents.get('focus')();
    assert.equal(b.timers.size, 0);
    await b.windowEvents.get('focus')();
    assert.equal(b.calls(), 1);
});

test('une réponse invalide conserve le dernier compteur et zéro masque le badge', async () => {
    const b = browser();
    await b.windowEvents.get('focus')();
    b.respond({ ok: true, status: 200, json: async () => ({ unread: -1 }) });
    await b.windowEvents.get('focus')();
    assert.equal(b.badge.textContent, '3');
    b.respond({ ok: true, status: 200, json: async () => ({ unread: 0 }) });
    await b.windowEvents.get('focus')();
    assert.equal(b.badge.hidden, true);
});
