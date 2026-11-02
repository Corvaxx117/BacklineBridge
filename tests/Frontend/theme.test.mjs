import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/scripts/theme.js', import.meta.url), 'utf8');
const storageKey = 'backlinebridge.theme';

function browser({ dark = false, saved = null, blocked = false, storage = new Map() } = {}) {
    const documentListeners = new Map();
    const windowListeners = new Map();
    const systemListeners = new Map();
    const events = [];
    const root = { dataset: {} };
    const control = { hidden: true };
    const meta = {};
    if (saved !== null) storage.set(storageKey, saved);

    class Element {
        attributes = {};
        constructor(kind) {
            this.kind = kind;
        }
        matches(selector) {
            return selector === `[data-theme-${this.kind}]`;
        }
        closest(selector) {
            return selector.includes(`[data-theme-${this.kind}]`) ? this : null;
        }
        setAttribute(name, value) {
            this.attributes[name] = value;
        }
    }
    class CustomEvent {
        constructor(type, options) {
            this.type = type;
            this.detail = options.detail;
        }
    }
    const toggle = new Element('toggle');
    const auto = new Element('auto');
    const system = {
        matches: dark,
        addEventListener: (name, callback) => systemListeners.set(name, callback),
    };
    const document = {
        documentElement: root,
        readyState: 'loading',
        addEventListener: (name, callback) => documentListeners.set(name, callback),
        querySelectorAll: (selector) =>
            ({
                '[data-theme-toggle]': [toggle],
                '[data-theme-auto]': [auto],
                '[data-theme-control]': [control],
            })[selector] ?? [],
        querySelector: () => ({
            setAttribute: (name, value) => {
                meta[name] = value;
            },
        }),
    };
    const window = {
        matchMedia: () => system,
        getComputedStyle: () => ({
            getPropertyValue: () => (root.dataset.theme === 'dark' ? '#0d171e' : '#152b38'),
        }),
        addEventListener: (name, callback) => windowListeners.set(name, callback),
        dispatchEvent: (event) => {
            events.push(event);
            windowListeners.get(event.type)?.(event);
        },
    };
    Object.defineProperty(window, 'localStorage', {
        get() {
            if (blocked) throw new Error('Storage disabled');
            return {
                getItem: (key) => storage.get(key) ?? null,
                setItem: (key, value) => storage.set(key, value),
            };
        },
    });
    vm.runInNewContext(source, { window, document, Element, CustomEvent });

    return {
        root,
        control,
        toggle,
        auto,
        storage,
        meta,
        events,
        ready() {
            document.readyState = 'complete';
            documentListeners.get('DOMContentLoaded')();
        },
        clickToggle() {
            // Un clic sur une icône enfant doit aussi actionner le switch.
            const icon = new Element('icon');
            icon.closest = () => toggle;
            documentListeners.get('click')({ target: icon });
        },
        automatic() {
            documentListeners.get('click')({ target: auto });
        },
        system(value) {
            system.matches = value;
            systemListeners.get('change')({ matches: value });
        },
        fromOtherTab(key, newValue) {
            windowListeners.get('storage')({ key, newValue });
        },
    };
}

test('le mode automatique est appliqué avant le chargement du contenu', () => {
    const page = browser({ dark: true });
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.root.dataset.themePreference, 'auto');
    assert.equal(page.control.hidden, true);
    page.ready();
    assert.equal(page.control.hidden, false);
    assert.equal(
        page.toggle.attributes['aria-checked'],
        String(page.root.dataset.theme === 'dark'),
    );
    assert.equal(page.auto.attributes['aria-pressed'], 'true');
    assert.equal(page.meta.content, '#0d171e');
});

test('un choix explicite est conservé après rechargement et prime sur le système', () => {
    const page = browser({ dark: true });
    page.ready();
    page.clickToggle();
    assert.equal(page.storage.get(storageKey), 'light');
    page.system(false);
    page.system(true);
    assert.equal(page.root.dataset.theme, 'light');
    const reloaded = browser({ dark: true, storage: page.storage });
    assert.equal(reloaded.root.dataset.theme, 'light');
    reloaded.ready();
    assert.equal(reloaded.toggle.attributes['aria-checked'], 'false');
    assert.equal(reloaded.auto.attributes['aria-pressed'], 'false');
});

test('le retour au mode automatique suit les changements de préférence système', () => {
    const page = browser({ dark: false, saved: 'dark' });
    page.ready();
    page.automatic();
    assert.equal(page.auto.attributes['aria-pressed'], 'true');
    assert.equal(page.root.dataset.theme, 'light');
    page.system(true);
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.events.at(-1).detail.theme, 'dark');
    assert.equal(page.events.at(-1).type, 'backlinebridge:themechange');
});

test('un navigateur sans stockage permet quand même de changer le thème', () => {
    const page = browser({ blocked: true });
    page.ready();
    page.clickToggle();
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.toggle.attributes['aria-checked'], 'true');
    assert.equal(page.auto.attributes['aria-pressed'], 'false');
});

test('une valeur corrompue ne devient pas un attribut de thème', () => {
    const page = browser({ dark: true, saved: 'unknown' });
    page.ready();
    assert.equal(page.root.dataset.themePreference, 'auto');
    assert.equal(page.root.dataset.theme, 'dark');
});

test('les onglets ouverts suivent le changement de choix ou son effacement', () => {
    const page = browser();
    page.ready();
    page.fromOtherTab(storageKey, 'dark');
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.toggle.attributes['aria-checked'], 'true');
    assert.equal(page.auto.attributes['aria-pressed'], 'false');
    page.fromOtherTab('other-key', 'light');
    assert.equal(page.root.dataset.theme, 'dark');
    page.fromOtherTab(null, null);
    assert.equal(page.root.dataset.theme, 'light');
    assert.equal(
        page.toggle.attributes['aria-checked'],
        String(page.root.dataset.theme === 'dark'),
    );
    assert.equal(page.auto.attributes['aria-pressed'], 'true');
});
