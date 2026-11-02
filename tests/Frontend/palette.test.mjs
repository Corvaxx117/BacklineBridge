import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readFileSync(new URL('../../public/styles/tokens.css', import.meta.url), 'utf8');
const [lightBlock, darkBlock] = css.split(":root[data-theme='dark']");
const colors = (block) =>
    Object.fromEntries(
        [...block.matchAll(/--([\w-]+):\s*(#[a-f\d]{6});/gi)].map((match) => [match[1], match[2]]),
    );
const light = colors(lightBlock);
const dark = { ...light, ...colors(darkBlock) };

function luminance(hex) {
    const rgb = [1, 3, 5].map((index) => Number.parseInt(hex.slice(index, index + 2), 16) / 255);
    const linear = rgb.map((value) =>
        value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4,
    );
    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
}

const pairs = [
    ['text', 'surface'],
    ['text', 'background'],
    ['text-muted', 'surface'],
    ['text-muted', 'background'],
    ['accent-text', 'surface'],
    ['on-strong', 'accent'],
    ['on-strong', 'accent-hover'],
    ['on-strong', 'strong-bg'],
    ['header-text', 'header-background'],
    ['header-muted', 'header-background'],
    ...['success', 'warning', 'danger', 'info', 'violet'].map((kind) => [
        `${kind}-text`,
        `${kind}-bg`,
    ]),
    ...['success', 'warning', 'danger', 'info'].map((kind) => ['on-strong', `${kind}-fill`]),
];

for (const [name, palette] of [
    ['claire', light],
    ['sombre', dark],
]) {
    test(`la palette ${name} garde un contraste de texte d’au moins 4,5:1`, () => {
        for (const [foreground, background] of pairs) {
            assert.ok(palette[foreground], foreground);
            assert.ok(palette[background], background);
            const values = [luminance(palette[foreground]), luminance(palette[background])].sort(
                (a, b) => a - b,
            );
            const ratio = (values[1] + 0.05) / (values[0] + 0.05);
            assert.ok(ratio >= 4.5, `${foreground} sur ${background} : ${ratio.toFixed(2)}:1`);
        }
    });
}
