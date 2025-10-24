'use strict';

self.addEventListener('push', (event) => {
    let payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
        } catch (error) {
            payload = { body: event.data.text() };
        }
    }

    const title = payload.title || 'BianconeriHub';
    const options = {
        body: payload.body || '',
        data: payload.data || {},
        icon: payload.icon || undefined,
        badge: payload.badge || payload.icon || undefined,
        tag: payload.tag || 'bianconerihub-community-post',
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = (event.notification.data && event.notification.data.url) || '/?page=community';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url === targetUrl || client.url.endsWith(targetUrl)) {
                    return client.focus();
                }
            }

            return clients.openWindow(targetUrl);
        })
    );
});
