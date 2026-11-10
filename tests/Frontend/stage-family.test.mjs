import test from 'node:test';
import assert from 'node:assert/strict';
import { stageFamily } from '../../public/scripts/stage/family.mjs';
const rules = {
    defaults: { drums: 'drums', wedge: 'monitors' },
    rules: [
        { kinds: ['amp'], words: ['guit'], family: 'guitar' },
        { kinds: ['amp'], words: ['bass'], family: 'bass' },
    ],
};
test('la famille suit le patch lié avant le nom et le type', () => {
    const sources = new Map([['one', { family: 'bass', available: true }]]);
    assert.equal(stageFamily({ kind: 'di_mono', patchSources: ['one'] }, rules, sources), 'bass');
    assert.equal(stageFamily({ kind: 'amp', label: 'Ampli GUITARE' }, rules, sources), 'guitar');
    assert.equal(stageFamily({ kind: 'drums' }, rules, sources), 'drums');
    assert.equal(
        stageFamily({ kind: 'other', patchSources: ['missing'] }, rules, sources),
        'utility',
    );
});
