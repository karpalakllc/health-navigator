/*
 * ALTCHA's PBKDF2 proof-of-work worker, bundled as a same-origin file.
 *
 * The widget's default build creates its workers from blob: URLs, which a
 * strict CSP would have to allow (worker-src blob:). Loading this file with
 * `new Worker(new URL(…, import.meta.url))` lets the bundler emit it under
 * /_next/static, so the CSP only needs `worker-src 'self'`. The package
 * script registers its own message handler; nothing else is needed here.
 */
import "altcha/workers/pbkdf2";
