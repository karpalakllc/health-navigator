"use client";

import { useEffect, useId, useRef, useState } from "react";
import { OptionChecklist } from "@/components/doctor-dashboard/option-checklist";
import { Input } from "@/components/ui/field";
import type { DoctorDashboard } from "@/lib/api/doctor-dashboard-types";
import { t } from "@/i18n/t";

type FacilityOption = DoctorDashboard["options"]["facilities"][number];

/** Wait this long after the last keystroke before searching. */
const DEBOUNCE_MS = 300;

/**
 * The workplaces of a change request. The dashboard sends only the
 * profile's own workplaces (there are too many facilities to ship them all),
 * so others are found by name and added from the results; ticked ones stay
 * listed whatever the search.
 */
export function FacilityPicker({
  initial,
  selected,
  onChange,
  onNamesChange,
}: {
  initial: FacilityOption[];
  selected: number[];
  onChange: (ids: number[]) => void;
  /** Every facility seen so far, for naming the selected ones elsewhere. */
  onNamesChange?: (known: FacilityOption[]) => void;
}) {
  const statusId = useId();
  const [query, setQuery] = useState("");
  const [known, setKnown] = useState<FacilityOption[]>(initial);
  const [results, setResults] = useState<FacilityOption[]>([]);
  const [status, setStatus] = useState<
    "idle" | "searching" | "empty" | "error"
  >("idle");
  const latest = useRef(0);
  const knownRef = useRef(initial);

  useEffect(() => {
    const needle = query.trim();

    if ([...needle].length < 2) {
      latest.current += 1;
      setResults([]);
      setStatus("idle");

      return;
    }

    const request = ++latest.current;
    const timer = window.setTimeout(async () => {
      setStatus("searching");

      try {
        const response = await fetch(
          `/api/doctor-dashboard/facilities?q=${encodeURIComponent(needle)}`,
        );

        if (!response.ok) {
          throw new Error(String(response.status));
        }

        const payload = (await response.json()) as { data?: FacilityOption[] };
        const found = payload.data ?? [];

        if (request !== latest.current) {
          return;
        }

        setResults(found);
        setStatus(found.length === 0 ? "empty" : "idle");
        const merged = [
          ...knownRef.current,
          ...found.filter((option) =>
            knownRef.current.every((existing) => existing.id !== option.id),
          ),
        ];
        knownRef.current = merged;
        setKnown(merged);
        onNamesChange?.(merged);
      } catch {
        if (request === latest.current) {
          setStatus("error");
        }
      }
    }, DEBOUNCE_MS);

    return () => window.clearTimeout(timer);
  }, [query, onNamesChange]);

  const listed = [
    ...known.filter((option) => selected.includes(option.id)),
    ...results.filter((option) => !selected.includes(option.id)),
  ];

  const statusText =
    status === "searching"
      ? t("doctorDashboard.facilitySearching")
      : status === "empty"
        ? t("doctorDashboard.facilitySearchEmpty")
        : status === "error"
          ? t("doctorDashboard.facilitySearchError")
          : "";

  return (
    <div className="flex flex-col gap-2">
      <Input
        id={`facility-search${statusId.replace(/[^a-zA-Z0-9_-]/g, "")}`}
        label={t("doctorDashboard.facilityFilter")}
        hint={t("doctorDashboard.facilitySearchHint")}
        type="search"
        autoComplete="off"
        value={query}
        onChange={(event) => setQuery(event.target.value)}
      />
      <p role="status" className="text-base text-ink-2">
        {statusText}
      </p>
      <OptionChecklist
        legend={t("doctorDashboard.facilities")}
        options={listed}
        selected={selected}
        onChange={onChange}
        describe={(option) =>
          known.find((facility) => facility.id === option.id)?.city ?? null
        }
      />
    </div>
  );
}
