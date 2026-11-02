export const clone = (value) => JSON.parse(JSON.stringify(value));
export const serialize = (items) => JSON.stringify(items.map(({ id, ...item }) => item));

export function createHistory(limit = 60) {
    const past = [];
    const future = [];
    function restore(source, destination, current) {
        if (!source.length) return null;
        destination.push(clone(current));
        return clone(source.pop());
    }
    return {
        canUndo: () => past.length > 0,
        canRedo: () => future.length > 0,
        record(before, after) {
            if (serialize(before.items) === serialize(after.items)) return;
            past.push(clone(before));
            if (past.length > limit) past.shift();
            future.length = 0;
        },
        undo: (current) => restore(past, future, current),
        redo: (current) => restore(future, past, current),
    };
}
