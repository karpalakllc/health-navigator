import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterEach, vi } from "vitest";
import { resetNavigation } from "./next-navigation";

// App Router hooks need a Next.js render tree; see next-navigation.ts.
vi.mock(
  "next/navigation",
  async () => (await import("./next-navigation")).navigationMock,
);

// Vitest runs without globals, so Testing Library cannot register its own
// afterEach cleanup — unmount explicitly, and reset shared mocks with it.
afterEach(() => {
  cleanup();
  resetNavigation();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});
