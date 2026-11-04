export function applicationKey(value) {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
}

export function sameKey(subscription, expected) {
    const actual = subscription?.options?.applicationServerKey;
    if (!actual) return false;
    const bytes = new Uint8Array(actual);
    const target = applicationKey(expected);
    return bytes.length === target.length && bytes.every((byte, index) => byte === target[index]);
}

export function deviceSupport(environment) {
    if (!environment.secure) return 'insecure';
    if (environment.ios && !environment.standalone) return 'install';
    if (!environment.worker || !environment.push || !environment.notification) return 'unsupported';
    return 'supported';
}

export function permissionMessage(permission, ios = false) {
    if (permission === 'denied') {
        return ios
            ? 'Notifications bloquées. Dans les réglages de votre iPhone ou iPad, autorisez BacklineBridge dans Notifications, puis revenez ici.'
            : 'Notifications bloquées pour ce site. Ouvrez les paramètres du site dans votre navigateur, autorisez les notifications, puis rechargez cette page.';
    }
    return 'La demande n’a pas été validée. Réessayez puis choisissez « Autoriser » dans votre navigateur.';
}
