'use strict';

const CACHE_PREFIX = 'bianconerihub';
const STATIC_CACHE = `${CACHE_PREFIX}-static-v1`;
const RUNTIME_CACHE = `${CACHE_PREFIX}-runtime-v1`;
const OFFLINE_URL = '/offline.html';
const STATIC_ASSETS = [
    OFFLINE_URL,
    '/assets/css/tailwind.css',
    '/assets/js/app.js',
    '/manifest.webmanifest',
    '/uploads/favicon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            try {
                const cache = await caches.open(STATIC_CACHE);
                await cache.addAll(STATIC_ASSETS);
            } catch (error) {
                console.warn('SW install: precache failed', error);
            }
            await self.skipWaiting();
        })()
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const cacheNames = await caches.keys();
            await Promise.all(
                cacheNames
                    .filter((cacheName) => cacheName.startsWith(CACHE_PREFIX) && cacheName !== STATIC_CACHE && cacheName !== RUNTIME_CACHE)
                    .map((cacheName) => caches.delete(cacheName))
            );
            await self.clients.claim();
        })()
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(request.url);

    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                try {
                    const networkResponse = await fetch(request);
                    const runtimeCache = await caches.open(RUNTIME_CACHE);
                    await runtimeCache.put(request, networkResponse.clone());
                    return networkResponse;
                } catch (error) {
                    const cachedResponse = await caches.match(request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    const offlinePage = await caches.match(OFFLINE_URL);
                    if (offlinePage) {
                        return offlinePage;
                    }
                    return new Response('Offline', {
                        status: 503,
                        statusText: 'Offline',
                        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                    });
                }
            })()
        );
        return;
    }

    if (['style', 'script', 'image', 'font'].includes(request.destination)) {
        event.respondWith(
            (async () => {
                const cache = await caches.open(STATIC_CACHE);
                const cachedResponse = await cache.match(request);
                if (cachedResponse) {
                    return cachedResponse;
                }

                try {
                    const networkResponse = await fetch(request);
                    await cache.put(request, networkResponse.clone());
                    return networkResponse;
                } catch (error) {
                    const fallbackResponse = request.destination === 'image' ? await caches.match('/uploads/favicon.png') : null;
                    if (fallbackResponse) {
                        return fallbackResponse;
                    }
                    return new Response('', { status: 504, statusText: 'Gateway Timeout' });
                }
            })()
        );
        return;
    }

    event.respondWith(
        (async () => {
            try {
                const networkResponse = await fetch(request);
                const runtimeCache = await caches.open(RUNTIME_CACHE);
                await runtimeCache.put(request, networkResponse.clone());
                return networkResponse;
            } catch (error) {
                const cachedResponse = await caches.match(request);
                if (cachedResponse) {
                    return cachedResponse;
                }
                return new Response('', { status: 504, statusText: 'Gateway Timeout' });
            }
        })()
    );
});

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
