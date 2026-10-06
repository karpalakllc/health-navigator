import { vi } from "vitest";

/**
 * A stand-in for the App Router hooks in `next/navigation`. Outside a Next.js
 * render tree the real hooks have no router context, so component tests get
 * this instead (wired up in setup-dom.ts). Tests assert on `router` and steer
 * `useSearchParams` / `usePathname` through `setSearchParams` / `setPathname`.
 */
export const router = {
  push: vi.fn(),
  replace: vi.fn(),
  refresh: vi.fn(),
  back: vi.fn(),
  forward: vi.fn(),
  prefetch: vi.fn(),
};

let searchParams = new URLSearchParams();
let pathname = "/";

export function setSearchParams(init: string | Record<string, string>): void {
  searchParams = new URLSearchParams(init);
}

export function setPathname(next: string): void {
  pathname = next;
}

export function resetNavigation(): void {
  for (const fn of Object.values(router)) {
    fn.mockReset();
  }
  searchParams = new URLSearchParams();
  pathname = "/";
}

export const navigationMock = {
  useRouter: () => router,
  useSearchParams: () => searchParams,
  usePathname: () => pathname,
  useParams: () => ({}),
  redirect: vi.fn(),
  notFound: vi.fn(),
};
