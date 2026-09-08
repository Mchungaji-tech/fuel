// Sarura Fuel Operations Cloud - Service Worker (Offline Support)
const CACHE_NAME = 'sarura-fuel-v1';
const OFFLINE_URL = '/fuel/fleet';

const CORE_ASSETS = [
  '/fuel/',
  '/fuel/dashboard',
  '/fuel/fleet',
  '/fuel/trucks',
  '/fuel/expenses',
  '/fuel/drivers',
  '/fuel/trips',
  '/fuel/reports',
  '/fuel/manifest.json'
];

// Install: Cache essential app shell and pages
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Best-effort pre-caching without failing install if a route isn't ready
      return Promise.allSettled(
        CORE_ASSETS.map((url) =>
          cache.add(url).catch((err) => {
            console.warn('[SW] Pre-cache skipped for ' + url, err);
          })
        )
      );
    })
  );
  self.skipWaiting();
});

// Activate: Clean up older caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch: Network First with Cache Fallback for navigation and pages
self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);

  // Never intercept or cache spreadsheet exports, templates, or file downloads
  if (url.pathname.includes('/export') || url.pathname.includes('/template') || url.pathname.includes('/bol/') || url.search.includes('format=')) {
    return;
  }

  // Skip chrome-extension or external analytics
  if (!url.origin.includes(self.location.origin) && !url.origin.includes('fonts.googleapis')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // Cache successful GET responses
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(async () => {
        // Network failed (offline) -> Try to serve from cache
        const cachedResponse = await caches.match(event.request);
        if (cachedResponse) {
          return cachedResponse;
        }

        // For navigation HTML requests, serve the main fleet/dashboard view from cache
        if (event.request.mode === 'navigate') {
          const fallback = await caches.match('/fuel/fleet') || await caches.match('/fuel/');
          if (fallback) {
            return fallback;
          }
        }

        // Return empty or offline fallback response
        return new Response(
          `<!DOCTYPE html>
          <html>
          <head>
            <meta charset="utf-8">
            <title>Offline - Sarura Fuel</title>
            <style>
              body{font-family:system-ui,-apple-system,sans-serif;background:#0F172A;color:#F8FAFC;display:grid;place-items:center;height:100vh;margin:0;padding:20px;text-align:center;}
              .box{background:#1E293B;border:1px solid #334155;border-radius:16px;padding:32px;max-width:440px;}
              .btn{display:inline-block;margin-top:16px;padding:10px 20px;background:#4F46E5;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;}
            </style>
          </head>
          <body>
            <div class="box">
              <div style="font-size:48px;">📶</div>
              <h2>Working in Offline Mode</h2>
              <p style="color:#94A3B8;">The internet connection is currently unavailable. Your previously opened pages and local data remain accessible.</p>
              <a href="/fuel/fleet" class="btn">Open Fleet Management</a>
            </div>
          </body>
          </html>`,
          {
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
          }
        );
      })
  );
});
