import assert from 'node:assert/strict';
import test from 'node:test';
import { createHistory } from '../../public/scripts/stage/history.mjs';
import { createTransform, frameUpdate } from '../../public/scripts/stage/geometry.mjs';

const snapshot = (x) => ({ items: [{ id: '1', x }], selectedId: '1' });
test('le plan conserve l’historique et abandonne le rétablissement après une nouvelle édition', () => {
    const history = createHistory(2);
    history.record(snapshot(10), snapshot(20));
    history.record(snapshot(20), snapshot(30));
    assert.deepEqual(history.undo(snapshot(30)), snapshot(20));
    assert.deepEqual(history.redo(snapshot(20)), snapshot(30));
    history.undo(snapshot(30));
    history.record(snapshot(20), snapshot(40));
    assert.equal(history.canRedo(), false);
    assert.deepEqual(history.undo(snapshot(40)), snapshot(20));
    assert.deepEqual(history.undo(snapshot(20)), snapshot(10));
    assert.equal(history.undo(snapshot(10)), null);
});
test('déplacer, pivoter et redimensionner gardent les limites du plan', () => {
    const transform = createTransform(
        { x: 50, y: 50, rotation: 0, scale: 1 },
        { left: 0, top: 0, width: 1000, height: 600 },
        { x: 600, y: 300 },
    );
    assert.deepEqual(transform({ x: 700, y: 360 }, 'move'), { x: 60, y: 60 });
    assert.deepEqual(transform({ x: 9999, y: -9999 }, 'move'), { x: 98, y: 2 });
    assert.deepEqual(transform({ x: 500, y: 400 }, 'rotate'), { rotation: 90 });
    assert.deepEqual(transform({ x: 500, y: 300 }, 'resize'), { scale: 0.5 });
    assert.deepEqual(transform({ x: 9999, y: 300 }, 'resize'), { scale: 2.5 });
});
test('les mouvements regroupent le dessin par image et finalisent la dernière position', () => {
    let paint = 0;
    let queued;
    const update = frameUpdate(
        () => paint++,
        (callback) => {
            queued = callback;
            return 1;
        },
        () => {
            queued = null;
        },
    );
    update.request();
    update.request();
    assert.equal(paint, 0);
    queued();
    assert.equal(paint, 1);
    update.request();
    update.flush();
    assert.equal(paint, 2);
    update.request();
    update.cancel();
    assert.equal(queued, null);
    assert.equal(paint, 2);
});
