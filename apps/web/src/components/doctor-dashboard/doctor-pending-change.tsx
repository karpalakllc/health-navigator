"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ChangeList } from "@/components/doctor-dashboard/change-list";
import { useChangeRequestAnnounce } from "@/components/doctor-dashboard/change-request-area";
import { Button } from "@/components/ui/button";
import { FormError } from "@/components/ui/form-message";
import { Tag } from "@/components/ui/tag";
import type { DoctorChangeRequest } from "@/lib/api/doctor-dashboard-types";
import { formatMkDate } from "@/lib/mk-date";
import { t, tFormat } from "@/i18n/t";

/**
 * The change request waiting for staff: what was asked, since when, and a
 * way to take it back (one request at a time).
 */
export function DoctorPendingChange({
  request,
}: {
  request: DoctorChangeRequest;
}) {
  const router = useRouter();
  const announce = useChangeRequestAnnounce();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const date = formatMkDate(request.created_at);

  async function withdraw() {
    setPending(true);
    setError(null);

    try {
      const response = await fetch(
        `/api/doctor-dashboard/change-requests/${request.id}`,
        { method: "DELETE" },
      );

      if (!response.ok) {
        setError(t("doctorDashboard.withdrawError"));

        return;
      }

      // The refresh brings the form back in place of this request; the
      // surrounding ChangeRequestArea keeps the confirmation and focus.
      announce(t("doctorDashboard.withdrawn"));
      router.refresh();
    } catch {
      setError(t("doctorDashboard.withdrawError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <Tag tone="tint" icon="clock">
          {t("doctorDashboard.pendingTitle")}
        </Tag>
        {date ? (
          <span className="type-meta text-ink-2">
            {tFormat("doctorDashboard.pendingSince", { date })}
          </span>
        ) : null}
      </div>
      <ChangeList changes={request.changes} />
      {request.message ? (
        <p className="whitespace-pre-line type-body text-ink-2">
          {request.message}
        </p>
      ) : null}
      <p className="type-meta text-ink-2">
        {t("doctorDashboard.pendingLocked")}
      </p>
      {error ? <FormError>{error}</FormError> : null}
      <Button
        variant="secondary"
        leadingIcon="rotate-ccw"
        loading={pending}
        disabled={pending}
        onClick={withdraw}
        className="w-full sm:w-auto sm:self-start"
      >
        {pending
          ? t("doctorDashboard.withdrawing")
          : t("doctorDashboard.withdraw")}
      </Button>
    </div>
  );
}
