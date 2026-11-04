import {
    applicationKey,
    sameKey,
    deviceSupport,
} from './notifications/push-utils.mjs?v=8e565fb89d17fc15';

const panel = document.querySelector('[data-push-settings]');
if (panel) {
    const status = panel.querySelector('[data-push-status]');
    const enable = panel.querySelector('[data-push-enable]');
    const disable = panel.querySelector('[data-push-disable]');
    const test = panel.querySelector('[data-push-test]');
    const help = panel.querySelector('[data-push-install]');
    let configuration;
    let registration;
    let busy = false;
    const ios =
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const supported = deviceSupport({
        secure: window.isSecureContext,
        ios,
        standalone:
            navigator.standalone === true || matchMedia('(display-mode: standalone)').matches,
        worker: 'serviceWorker' in navigator,
        push: 'PushManager' in window,
        notification: 'Notification' in window,
    });

    function message(text) {
        status.textContent = text;
    }

    function controls(active) {
        enable.hidden = active;
        disable.hidden = !active;
        test.hidden = !active;
    }

    function lock(value) {
        busy = value;
        for (const button of [enable, disable, test]) button.disabled = value;
        panel.setAttribute('aria-busy', String(value));
    }

    async function request(method, url, data) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': configuration?.token || '',
            },
            ...(data ? { body: JSON.stringify(data) } : {}),
        });
        if (response.redirected || response.status === 401 || response.status === 403) {
            throw new Error('Rechargez la page et reconnectez-vous si nécessaire.');
        }
        if (!response.ok) {
            const error = new Error(
                response.status === 429
                    ? 'Attendez une minute avant de réessayer.'
                    : 'L’opération a échoué. Rechargez la page puis réessayez.',
            );
            error.status = response.status;
            throw error;
        }
        if (!response.headers.get('content-type')?.includes('application/json')) {
            throw new Error('Réponse inattendue. Rechargez la page puis réessayez.');
        }
        return response.json();
    }

    async function saveSubscription() {
        let subscription = await registration.pushManager.getSubscription();
        if (subscription && !sameKey(subscription, configuration.publicKey)) {
            await subscription.unsubscribe();
            subscription = null;
        }
        subscription ||= await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationKey(configuration.publicKey),
        });
        try {
            await request('POST', panel.dataset.pushUrl, subscription.toJSON());
        } catch (error) {
            if (error.status !== 409) throw error;
            // Un navigateur partagé ne doit pas conserver l’abonnement d’un autre compte.
            await subscription.unsubscribe();
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationKey(configuration.publicKey),
            });
            await request('POST', panel.dataset.pushUrl, subscription.toJSON());
        }
    }

    enable.addEventListener('click', async () => {
        if (busy || !configuration?.available) return;
        // La demande reste directement liée au clic, condition nécessaire sur iPhone.
        lock(true);
        try {
            const permission = Notification.requestPermission();
            if ((await permission) !== 'granted') {
                message(
                    'Autorisation refusée. Vous pouvez la modifier dans les réglages de votre appareil.',
                );
                return;
            }
            await saveSubscription();
            controls(true);
            message('Notifications activées sur cet appareil. Vous pouvez envoyer un test.');
        } catch (error) {
            message(error instanceof Error ? error.message : 'L’activation a échoué.');
        } finally {
            lock(false);
        }
    });

    disable.addEventListener('click', async () => {
        if (busy) return;
        lock(true);
        try {
            // Retirer d’abord le destinataire côté serveur, même si le navigateur refuse la suite.
            await request('DELETE', panel.dataset.pushUrl);
            const subscription = await registration?.pushManager.getSubscription();
            await subscription?.unsubscribe();
            controls(false);
            message('Notifications désactivées sur cet appareil. L’historique reste disponible.');
        } catch (error) {
            message(error.message);
        } finally {
            lock(false);
        }
    });

    test.addEventListener('click', async () => {
        if (busy) return;
        lock(true);
        try {
            await request('POST', panel.dataset.pushTestUrl);
            message('Test programmé pour cet appareil. Il devrait arriver d’ici une minute.');
        } catch (error) {
            message(error.message);
        } finally {
            lock(false);
        }
    });

    async function initialize() {
        controls(false);
        enable.hidden = true;
        if (supported !== 'supported') {
            help.hidden = supported !== 'install';
            message(
                {
                    install:
                        'Sur iPhone, ouvrez BacklineBridge depuis son icône sur l’écran d’accueil.',
                    insecure: 'Les notifications nécessitent une connexion HTTPS.',
                    unsupported:
                        'Ce navigateur ne prend pas en charge les notifications push. L’historique reste disponible.',
                }[supported],
            );
            return;
        }
        try {
            configuration = await request('GET', panel.dataset.pushUrl);
            if (!configuration.available) {
                message(
                    'Les notifications sur téléphone ne sont pas encore activées sur cette installation.',
                );
                return;
            }
            registration = await navigator.serviceWorker.register('/service-worker.js', {
                updateViaCache: 'none',
            });
            await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            const active =
                configuration.enabled &&
                Notification.permission === 'granted' &&
                sameKey(subscription, configuration.publicKey);
            controls(active);
            message(
                active
                    ? 'Notifications activées sur cet appareil.'
                    : Notification.permission === 'denied'
                      ? 'Notifications bloquées. Autorisez-les dans les réglages de votre appareil, puis revenez ici.'
                      : 'Recevez les nouveaux concerts et documents, même lorsque BacklineBridge est fermé.',
            );
        } catch {
            message('Impossible de vérifier cet appareil. Rechargez la page pour réessayer.');
        }
    }
    initialize();
}
