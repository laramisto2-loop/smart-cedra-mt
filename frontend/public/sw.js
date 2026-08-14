const VERSION = "electoflow-v2";
const SHELL_CACHE = `${VERSION}-shell`;
const ASSET_CACHE = `${VERSION}-assets`;

const SHELL_FILES = [
  "/manifest.webmanifest",
  "/electoflow-pwa.svg",
];

async function cacheApplicationShell() {
  const cache = await caches.open(SHELL_CACHE);
  const indexResponse = await fetch("/", {
    cache: "reload",
  });

  if (!indexResponse.ok) {
    throw new Error(
      "The application shell could not be downloaded.",
    );
  }

  await cache.put("/", indexResponse.clone());

  const indexHtml = await indexResponse.text();
  const assetPaths = Array.from(
    indexHtml.matchAll(
      /(?:src|href)=["']([^"']+)["']/g,
    ),
    (match) => match[1],
  ).filter((path) => path.startsWith("/assets/"));

  const filesToCache = [
    ...SHELL_FILES,
    ...new Set(assetPaths),
  ];

  await cache.addAll(filesToCache);
}

self.addEventListener("install", (event) => {
  event.waitUntil(
    cacheApplicationShell().then(() =>
      self.skipWaiting(),
    ),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) =>
        Promise.all(
          cacheNames
            .filter(
              (cacheName) =>
                cacheName !== SHELL_CACHE &&
                cacheName !== ASSET_CACHE,
            )
            .map((cacheName) =>
              caches.delete(cacheName),
            ),
        ),
      )
      .then(() => self.clients.claim()),
  );
});

function isPrivateRequest(url) {
  return (
    url.pathname.startsWith("/api/") ||
    url.pathname.startsWith("/login") ||
    url.pathname.startsWith("/logout") ||
    url.pathname.startsWith("/sanctum/")
  );
}

function isDevelopmentRequest(url) {
  return (
    url.pathname.startsWith("/src/") ||
    url.pathname.startsWith("/@vite/") ||
    url.pathname.includes("/node_modules/")
  );
}

function isStaticAsset(request, url) {
  return (
    url.pathname.startsWith("/assets/") ||
    ["style", "script", "font", "image"].includes(
      request.destination,
    )
  );
}

async function networkFirst(request) {
  try {
    const response = await fetch(request);

    if (response.ok) {
      const cache = await caches.open(SHELL_CACHE);

      await cache.put(request, response.clone());
    }

    return response;
  } catch {
    return (
      (await caches.match(request)) ||
      (await caches.match("/"))
    );
  }
}

async function cacheFirst(request) {
  const cachedResponse = await caches.match(request);

  if (cachedResponse) {
    return cachedResponse;
  }

  const response = await fetch(request);

  if (response.ok) {
    const cache = await caches.open(ASSET_CACHE);

    await cache.put(request, response.clone());
  }

  return response;
}

self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (
    request.method !== "GET" ||
    url.origin !== self.location.origin ||
    isPrivateRequest(url) ||
    isDevelopmentRequest(url)
  ) {
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(networkFirst(request));
    return;
  }

  if (isStaticAsset(request, url)) {
    event.respondWith(cacheFirst(request));
  }
});

//This file caches only the frontend application shell. It deliberately avoids caching API responses, authentication, tenant data, or private attachments
//the service worker reads the production HTML during installation and stores its exact hashed CSS and JavaScript files.