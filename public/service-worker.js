// Réception des push uniquement : aucune page ni aucun document privé n’est mis en cache.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data?.json() || {};
    } catch {
        // Safari exige une notification visible, même si le contenu est incomplet.
    }
    const text = (value, fallback, length) =>
        typeof value === 'string' ? value.slice(0, length) : fallback;
    event.waitUntil(
        self.registration.showNotification(text(payload.title, 'BacklineBridge', 100), {
            body: text(payload.body, 'Une nouveauté vous attend dans le groupe.', 400),
            icon: '/icons/app-192.png',
            badge: '/icons/badge-96.png',
            tag: text(payload.tag, 'bb-update', 80),
            data: { url: '/notifications' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        (async () => {
            const url = new URL('/notifications', self.location.origin).href;
            const windows = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });
            const client = windows.find(
                (item) => new URL(item.url).origin === self.location.origin,
            );
            if (client) {
                await client.navigate(url);
                return client.focus();
            }
            return self.clients.openWindow(url);
        })(),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'BB_LOGOUT') {
        event.waitUntil(
            self.registration
                .getNotifications()
                .then((items) => items.forEach((item) => item.close())),
        );
    }
});
