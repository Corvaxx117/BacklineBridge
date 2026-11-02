export const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
export const round = (value, precision = 1) =>
    Math.round(value * 10 ** precision) / 10 ** precision;
export const rotation = (value) => round(((value % 360) + 360) % 360);

export function createTransform(start, rect, origin) {
    const center = {
        x: rect.left + (start.x / 100) * rect.width,
        y: rect.top + (start.y / 100) * rect.height,
    };
    const angle = Math.atan2(origin.y - center.y, origin.x - center.x);
    const distance = Math.max(1, Math.hypot(origin.x - center.x, origin.y - center.y));
    return (point, mode) => {
        if (mode === 'move') {
            return {
                x: round(clamp(start.x + ((point.x - origin.x) / rect.width) * 100, 2, 98)),
                y: round(clamp(start.y + ((point.y - origin.y) / rect.height) * 100, 2, 98)),
            };
        }
        if (mode === 'rotate') {
            let value =
                start.rotation +
                ((Math.atan2(point.y - center.y, point.x - center.x) - angle) * 180) / Math.PI;
            if (point.shiftKey) value = Math.round(value / 15) * 15;
            return { rotation: rotation(value) };
        }
        return {
            scale: round(
                clamp(
                    (start.scale * Math.hypot(point.x - center.x, point.y - center.y)) / distance,
                    0.5,
                    2.5,
                ),
                2,
            ),
        };
    };
}

export function frameUpdate(paint, request = requestAnimationFrame, cancel = cancelAnimationFrame) {
    let pending = null;
    const flush = () => {
        if (pending === null) return;
        cancel(pending);
        pending = null;
        paint();
    };
    return {
        request() {
            if (pending === null)
                pending = request(() => {
                    pending = null;
                    paint();
                });
        },
        flush,
        cancel() {
            if (pending !== null) cancel(pending);
            pending = null;
        },
    };
}
