/**
 * Haanpaa Martial Arts — PWA Service Worker
 *
 * Strategy:
 * - Precache: shell HTML, Google Fonts (Barlow Condensed + Inter), brand CSS, logo.
 * - Runtime: gym/v1 GET responses — cache-first, 24 h TTL, max 50 entries.
 * - Offline fallback: serve /offline for navigation requests that miss every cache.
 *
 * @version hma-pwa-v1
 */

const CACHE_VERSION = 'hma-pwa-v1';

const PRECACHE_ASSETS = [
  '/my-account/',
  '/offline',
  'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap',
  '/wp-content/themes/haanpaa/style.css',
  '/wp-content/themes/haanpaa/assets/globals.css',
];

/** Maximum age for runtime-cached API responses (24 hours in seconds). */
const API_CACHE_TTL_SECONDS = 86400;

/** Maximum number of API responses kept in the runtime cache. */
const API_CACHE_MAX_ENTRIES = 50;

const API_CACHE_NAME = `${CACHE_VERSION}-api`;
const STATIC_CACHE_NAME = `${CACHE_VERSION}-static`;

// ─── Install ──────────────────────────────────────────────────────────────────

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE_NAME).then((cache) =>
      // Use individual adds so one missing asset doesn't block the whole precache.
      Promise.allSettled(PRECACHE_ASSETS.map((url) => cache.add(url)))
    ).then(() => self.skipWaiting())
  );
});

// ─── Activate ─────────────────────────────────────────────────────────────────

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== STATIC_CACHE_NAME && key !== API_CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

// ─── Fetch ────────────────────────────────────────────────────────────────────

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Only intercept GET requests.
  if (request.method !== 'GET') return;

  // gym/v1 API — cache-first with TTL and max-entries eviction.
  if (url.pathname.startsWith('/wp-json/gym/v1/')) {
    event.respondWith(handleApiRequest(request));
    return;
  }

  // Navigation requests — network-first with offline fallback.
  if (request.mode === 'navigate') {
    event.respondWith(handleNavigationRequest(request));
    return;
  }

  // Static assets — cache-first (precached assets land here).
  event.respondWith(handleStaticRequest(request));
});

// ─── Strategy helpers ─────────────────────────────────────────────────────────

/**
 * Cache-first with 24 h TTL and max-50-entry eviction for gym/v1 responses.
 *
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function handleApiRequest(request) {
  const cache = await caches.open(API_CACHE_NAME);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    const cachedAt = cachedResponse.headers.get('x-sw-cached-at');
    if (cachedAt) {
      const age = (Date.now() - parseInt(cachedAt, 10)) / 1000;
      if (age < API_CACHE_TTL_SECONDS) {
        return cachedResponse;
      }
    }
  }

  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const responseToCache = addCachedAtHeader(networkResponse.clone());
      await cache.put(request, await responseToCache);
      await evictOldEntries(cache, API_CACHE_MAX_ENTRIES);
    }
    return networkResponse;
  } catch {
    if (cachedResponse) return cachedResponse;
    return new Response(JSON.stringify({ error: 'offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

/**
 * Network-first for navigation; falls back to /offline on failure.
 *
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function handleNavigationRequest(request) {
  try {
    return await fetch(request);
  } catch {
    const cache = await caches.open(STATIC_CACHE_NAME);
    const offline = await cache.match('/offline');
    return offline || new Response('Offline', { status: 503 });
  }
}

/**
 * Cache-first for static assets; falls back to network.
 *
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function handleStaticRequest(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    return await fetch(request);
  } catch {
    return new Response('Asset unavailable offline', { status: 503 });
  }
}

// ─── Utilities ────────────────────────────────────────────────────────────────

/**
 * Clones a Response and injects an x-sw-cached-at timestamp header.
 *
 * @param {Response} response
 * @returns {Promise<Response>}
 */
async function addCachedAtHeader(response) {
  const body = await response.arrayBuffer();
  const headers = new Headers(response.headers);
  headers.set('x-sw-cached-at', String(Date.now()));
  return new Response(body, { status: response.status, headers });
}

/**
 * Keeps the cache under maxEntries by deleting the oldest keys.
 *
 * @param {Cache}  cache
 * @param {number} maxEntries
 * @returns {Promise<void>}
 */
async function evictOldEntries(cache, maxEntries) {
  const keys = await cache.keys();
  if (keys.length > maxEntries) {
    const toDelete = keys.slice(0, keys.length - maxEntries);
    await Promise.all(toDelete.map((key) => cache.delete(key)));
  }
}
