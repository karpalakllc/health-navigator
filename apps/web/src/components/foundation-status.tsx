import { apiUrl } from "@/lib/config";
import type { ApiHealthData } from "@/lib/api/types";

type FoundationStatusProps = {
  apiBaseUrl: string;
  health: ApiHealthData | null;
  error: string | null;
};

export function FoundationStatus({
  apiBaseUrl,
  health,
  error,
}: FoundationStatusProps) {
  const isHealthy = health?.status === "ok";

  return (
    <div className="w-full max-w-xl space-y-6">
      <header>
        <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Phase 1 · Developer foundation
        </p>
        <h1 className="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
          Zdravje360
        </h1>
        <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
          Next.js → Laravel API (direct). Not a product landing page.
        </p>
      </header>

      <section className="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
        <h2 className="text-sm font-medium text-zinc-900 dark:text-zinc-100">
          Connection
        </h2>
        <div className="mt-3 space-y-2 text-sm">
          <Row label="API base URL" value={apiBaseUrl} />
          <Row label="Health URL" value={apiUrl("/v1/health")} />
        </div>
      </section>

      <section className="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-sm font-medium text-zinc-900 dark:text-zinc-100">
            API health
          </h2>
          <StatusBadge ok={isHealthy} pending={!health && !error} />
        </div>
        {health ? (
          <pre className="mt-3 overflow-x-auto rounded-md bg-zinc-50 p-3 font-mono text-xs text-emerald-800 dark:bg-zinc-900 dark:text-emerald-400">
            {JSON.stringify({ data: health }, null, 2)}
          </pre>
        ) : (
          <p className="mt-3 font-mono text-xs text-red-600 dark:text-red-400">
            {error ?? "Unreachable — start the API on port 8000."}
          </p>
        )}
      </section>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-zinc-500">{label}</dt>
      <dd className="mt-0.5 break-all font-mono text-xs text-zinc-900 dark:text-zinc-100">
        {value}
      </dd>
    </div>
  );
}

function StatusBadge({ ok, pending }: { ok: boolean; pending: boolean }) {
  if (pending) {
    return (
      <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
        checking
      </span>
    );
  }

  return (
    <span
      className={
        ok
          ? "rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400"
          : "rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-400"
      }
    >
      {ok ? "ok" : "error"}
    </span>
  );
}
