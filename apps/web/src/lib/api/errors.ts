/*
 * Client-safe home of the API error type. lib/api/server.ts re-exports it;
 * it lives here so modules shared with the browser (lib/api/path.ts) can throw
 * it without pulling server.ts — and the session cookie code — into a client
 * bundle.
 */

export type ApiErrorBody = {
  message: string;
  errors?: Record<string, string[]>;
};

export class ApiRequestError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly body?: ApiErrorBody,
  ) {
    super(message);
    this.name = "ApiRequestError";
  }
}
