import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterEach, vi } from "vitest";
import { altchaControl } from "./altcha";
import { resetNavigation } from "./next-navigation";

// App Router hooks need a Next.js render tree; see next-navigation.ts.
vi.mock(
  "next/navigation",
  async () => (await import("./next-navigation")).navigationMock,
);

// The ALTCHA widget needs Web Workers; forms get an instant fake instead
// (test/altcha.ts). use-altcha.test.tsx exercises the hook against it.
vi.mock(
  "@/lib/altcha/load",
  async () => (await import("./altcha")).altchaLoadMock,
);

// Vitest runs without globals, so Testing Library cannot register its own
// afterEach cleanup — unmount explicitly, and reset shared mocks with it.
afterEach(() => {
  cleanup();
  altchaControl.reset();
  resetNavigation();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});
