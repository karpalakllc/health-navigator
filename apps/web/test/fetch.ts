import { vi } from "vitest";

type Reply = {
  status: number;
  body?: unknown;
};

/**
 * Stubs global fetch with one queued JSON reply per call (the last reply
 * repeats). Returns the mock so tests can inspect the requests made.
 */
export function mockFetch(...replies: Reply[]) {
  const queue = [...replies];
  const fn = vi.fn<typeof fetch>(async () => {
    const reply = queue.length > 1 ? queue.shift()! : queue[0];

    return new Response(
      reply.body === undefined ? null : JSON.stringify(reply.body),
      {
        status: reply.status,
        headers: { "Content-Type": "application/json" },
      },
    );
  });

  vi.stubGlobal("fetch", fn);

  return fn;
}

/** fetch that never reaches the server (network down, CORS, etc.). */
export function mockFetchNetworkError() {
  const fn = vi.fn<typeof fetch>(async () => {
    throw new TypeError("Failed to fetch");
  });

  vi.stubGlobal("fetch", fn);

  return fn;
}

/** The parsed JSON body of the nth fetch call. */
export function requestBody(
  fn: ReturnType<typeof vi.fn>,
  call = 0,
): Record<string, unknown> {
  const init = fn.mock.calls[call]?.[1] as RequestInit | undefined;

  return JSON.parse(String(init?.body ?? "null"));
}
