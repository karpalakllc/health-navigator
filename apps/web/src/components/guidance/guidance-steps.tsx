"use client";

import { useId, useMemo, useState, type Ref } from "react";
import { BodyMap } from "@/components/guidance/body-map";
import { ChoiceCard } from "@/components/guidance/choice-card";
import { FilterChip } from "@/components/ui/chip";
import { Fieldset, Input, Select } from "@/components/ui/field";
import { Icon } from "@/components/ui/icons";
import type {
  AgeBand,
  BodyArea,
  CatalogFlow,
  Demographics,
  GuidanceNode,
  ScreenItem,
  FlowRef,
  TimeUnit,
} from "@/lib/api/guidance-v2";
import { pregnancyIsAsked } from "@/lib/api/guidance-v2";
import { cn } from "@/lib/cn";
import {
  areasWithFlows,
  flowsForAge,
  flowsInArea,
  searchFlows,
} from "@/lib/guidance/search";
import { UNIT_KEYS } from "@/lib/guidance/summary";
import {
  convertToUnit,
  formatAnswerNumber,
  isTimeUnit,
  parseLocaleNumber,
} from "@/lib/guidance/units";
import { t, tFormat, type MessageKey } from "@/i18n/t";

export const stepHeadingClass = "type-h2 text-ink";

/** The step's heading: takes focus when the step appears (see GuidanceGuide). */
export function StepHeading({
  headingRef,
  children,
}: {
  headingRef: Ref<HTMLHeadingElement>;
  children: React.ReactNode;
}) {
  return (
    <h2
      id="guidance-step-heading"
      ref={headingRef}
      tabIndex={-1}
      className={stepHeadingClass}
    >
      {children}
    </h2>
  );
}

// ------------------------------------------------------------ demographics

export type DemographicsForm = {
  ageValue: string;
  ageUnit: Demographics["age_unit"];
  sex: Demographics["sex"] | "";
  pregnancy: NonNullable<Demographics["pregnancy"]> | "";
  conditions: string[];
};

export const EMPTY_DEMOGRAPHICS: DemographicsForm = {
  ageValue: "",
  ageUnit: "years",
  sex: "",
  pregnancy: "",
  conditions: [],
};

const CONDITIONS: Array<{ value: string; label: MessageKey }> = [
  { value: "immunosuppressed", label: "guidance.conditionImmunosuppressed" },
  { value: "diabetes", label: "guidance.conditionDiabetes" },
  { value: "heart_disease", label: "guidance.conditionHeart" },
  { value: "lung_disease", label: "guidance.conditionLung" },
  { value: "kidney_disease", label: "guidance.conditionKidney" },
  {
    value: "pregnancy_complication_history",
    label: "guidance.conditionPregnancyHistory",
  },
];

/** The form as the API expects it, or the first problem to show. */
export function demographicsFromForm(
  form: DemographicsForm,
): { demo: Demographics } | { error: string } {
  const age = parseLocaleNumber(form.ageValue);
  const years =
    form.ageUnit === "years"
      ? age
      : form.ageUnit === "months"
        ? age / 12
        : age / 52;

  if (!Number.isFinite(age) || age < 0 || years > 120) {
    return { error: t("guidance.ageInvalid") };
  }

  if (form.sex === "") {
    return { error: t("guidance.selectOption") };
  }

  const asks = pregnancyIsAsked({
    age_value: age,
    age_unit: form.ageUnit,
    sex: form.sex,
  });

  if (asks && form.pregnancy === "") {
    return { error: t("guidance.pregnancyRequired") };
  }

  return {
    demo: {
      age_value: age,
      age_unit: form.ageUnit,
      sex: form.sex,
      pregnancy: asks && form.pregnancy !== "" ? form.pregnancy : null,
      conditions: form.conditions,
    },
  };
}

export function DemographicsStep({
  form,
  onChange,
  headingRef,
}: {
  form: DemographicsForm;
  onChange: (form: DemographicsForm) => void;
  headingRef: Ref<HTMLHeadingElement>;
}) {
  const id = useId();
  const age = parseLocaleNumber(form.ageValue);
  const asksPregnancy =
    form.sex !== "" &&
    Number.isFinite(age) &&
    pregnancyIsAsked({ age_value: age, age_unit: form.ageUnit, sex: form.sex });

  const set = (patch: Partial<DemographicsForm>) =>
    onChange({ ...form, ...patch });

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <StepHeading headingRef={headingRef}>
          {t("guidance.forWhomTitle")}
        </StepHeading>
        <p className="type-reading text-ink-2">{t("guidance.forWhomLead")}</p>
      </div>

      <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:max-w-md">
        <Input
          label={t("guidance.ageLabel")}
          inputMode="decimal"
          autoComplete="off"
          value={form.ageValue}
          onChange={(e) => set({ ageValue: e.target.value })}
          required
        />
        <Select
          label={t("guidance.ageUnitLabel")}
          value={form.ageUnit}
          onChange={(e) =>
            set({ ageUnit: e.target.value as DemographicsForm["ageUnit"] })
          }
        >
          <option value="years">{t("guidance.ageUnitYears")}</option>
          <option value="months">{t("guidance.ageUnitMonths")}</option>
          <option value="weeks">{t("guidance.ageUnitWeeks")}</option>
        </Select>
      </div>

      <Fieldset legend={t("guidance.sexLabel")}>
        <ul className="flex flex-col gap-3 pt-2">
          {(
            [
              ["female", "guidance.sexFemale"],
              ["male", "guidance.sexMale"],
              ["unspecified", "guidance.sexUnspecified"],
            ] as const
          ).map(([value, label]) => (
            <li key={value}>
              <ChoiceCard
                type="radio"
                name={`${id}-sex`}
                id={`${id}-sex-${value}`}
                value={value}
                checked={form.sex === value}
                onChange={() => set({ sex: value })}
                label={t(label)}
              />
            </li>
          ))}
        </ul>
      </Fieldset>

      {asksPregnancy ? (
        <Fieldset legend={t("guidance.pregnancyLabel")}>
          <ul className="flex flex-col gap-3 pt-2">
            {(
              [
                ["pregnant", "guidance.pregnancyPregnant"],
                ["postpartum", "guidance.pregnancyPostpartum"],
                ["not_pregnant", "guidance.pregnancyNo"],
                ["unsure", "guidance.pregnancyUnsure"],
              ] as const
            ).map(([value, label]) => (
              <li key={value}>
                <ChoiceCard
                  type="radio"
                  name={`${id}-pregnancy`}
                  id={`${id}-pregnancy-${value}`}
                  value={value}
                  checked={form.pregnancy === value}
                  onChange={() => set({ pregnancy: value })}
                  label={t(label)}
                />
              </li>
            ))}
          </ul>
        </Fieldset>
      ) : null}

      <Fieldset
        legend={t("guidance.conditionsLabel")}
        hint={t("guidance.conditionsHint")}
      >
        <ul className="flex flex-col gap-3 pt-2">
          {CONDITIONS.map((condition) => (
            <li key={condition.value}>
              <ChoiceCard
                type="checkbox"
                id={`${id}-condition-${condition.value}`}
                value={condition.value}
                checked={form.conditions.includes(condition.value)}
                onChange={() =>
                  set({
                    conditions: form.conditions.includes(condition.value)
                      ? form.conditions.filter((c) => c !== condition.value)
                      : [...form.conditions, condition.value],
                  })
                }
                label={t(condition.label)}
              />
            </li>
          ))}
        </ul>
      </Fieldset>
    </div>
  );
}

// ----------------------------------------------------------------- symptoms

const AREA_KEYS: Record<BodyArea, MessageKey> = {
  head: "guidance.areaHead",
  eyes: "guidance.areaEyes",
  ears: "guidance.areaEars",
  mouth: "guidance.areaMouth",
  throat: "guidance.areaThroat",
  chest: "guidance.areaChest",
  abdomen: "guidance.areaAbdomen",
  pelvis: "guidance.areaPelvis",
  back: "guidance.areaBack",
  arms: "guidance.areaArms",
  legs: "guidance.areaLegs",
  skin: "guidance.areaSkin",
  general: "guidance.areaGeneral",
  mind: "guidance.areaMind",
};

export function areaLabel(area: BodyArea): string {
  return t(AREA_KEYS[area]);
}

export function SymptomsStep({
  flows,
  ageBand,
  max,
  selected,
  onToggle,
  area,
  onArea,
  onNoMatch,
  noMatchPending,
  headingRef,
}: {
  flows: CatalogFlow[];
  ageBand: AgeBand | null;
  max: number;
  selected: string[];
  onToggle: (key: string) => void;
  area: BodyArea | null;
  onArea: (area: BodyArea | null) => void;
  onNoMatch: () => void;
  noMatchPending: boolean;
  headingRef: Ref<HTMLHeadingElement>;
}) {
  const id = useId();
  const [query, setQuery] = useState("");
  const forAge = useMemo(() => flowsForAge(flows, ageBand), [flows, ageBand]);
  const areas = useMemo(() => areasWithFlows(forAge), [forAge]);
  const shown = useMemo(
    () =>
      query.trim() !== ""
        ? searchFlows(forAge, query)
        : flowsInArea(forAge, area),
    [forAge, query, area],
  );
  const full = selected.length >= max;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <StepHeading headingRef={headingRef}>
          {t("guidance.symptomsTitle")}
        </StepHeading>
        <p className="type-reading text-ink-2">
          {tFormat("guidance.symptomsLead", { max })}
        </p>
      </div>

      <search>
        <Input
          type="search"
          label={t("guidance.searchLabel")}
          placeholder={t("guidance.searchPlaceholder")}
          autoComplete="off"
          enterKeyHint="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
        />
      </search>

      {query.trim() === "" ? (
        <section
          aria-labelledby={`${id}-areas`}
          className="flex flex-col gap-4"
        >
          <h3 id={`${id}-areas`} className="type-h3 text-ink">
            {t("guidance.bodyMapTitle")}
          </h3>
          <div className="grid gap-4 lg:grid-cols-[auto_minmax(0,1fr)] lg:items-start">
            <BodyMap
              selected={area}
              available={areas}
              onSelect={(a) => onArea(area === a ? null : a)}
            />
            <ul
              aria-label={t("guidance.bodyMapLabel")}
              className="flex flex-wrap gap-2"
            >
              <li>
                <FilterChip
                  selected={area === null}
                  onClick={() => onArea(null)}
                >
                  {t("guidance.areaAll")}
                </FilterChip>
              </li>
              {areas.map((a) => (
                <li key={a}>
                  <FilterChip
                    selected={area === a}
                    onClick={() => onArea(area === a ? null : a)}
                  >
                    {areaLabel(a)}
                  </FilterChip>
                </li>
              ))}
            </ul>
          </div>
        </section>
      ) : null}

      <fieldset aria-labelledby={`${id}-list`} className="min-w-0">
        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
          <h3 id={`${id}-list`} className="type-h3 text-ink">
            {t("guidance.symptomListTitle")}
          </h3>
          <p className="type-meta text-ink-2" aria-live="polite">
            {tFormat("guidance.selectedCount", {
              count: selected.length,
              max,
            })}
          </p>
        </div>
        {shown.length === 0 ? (
          <p className="type-reading text-ink-2" role="status">
            {tFormat("guidance.searchNoResults", { query: query.trim() })}
          </p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2">
            {shown.map((flow) => {
              const checked = selected.includes(flow.key);

              return (
                <li key={flow.key}>
                  <ChoiceCard
                    type="checkbox"
                    id={`${id}-flow-${flow.key}`}
                    value={flow.key}
                    checked={checked}
                    disabled={full && !checked}
                    onChange={() => onToggle(flow.key)}
                    label={flow.title}
                  />
                </li>
              );
            })}
          </ul>
        )}
        {full ? (
          <p className="mt-3 type-meta text-ink-2">
            {tFormat("guidance.maxReached", { max })}
          </p>
        ) : null}
      </fieldset>

      <div className="flex flex-col gap-1">
        <button
          type="button"
          onClick={onNoMatch}
          disabled={noMatchPending}
          className="self-start type-body font-semibold text-ink underline decoration-coral decoration-2 underline-offset-4 disabled:opacity-60"
        >
          {t("guidance.noMatch")}
        </button>
        <p className="type-meta text-ink-2">{t("guidance.noMatchHint")}</p>
      </div>
    </div>
  );
}

// ------------------------------------------------------------------- screen

export function ScreenStep({
  items,
  flows,
  ticked,
  onToggle,
  headingRef,
}: {
  items: ScreenItem[];
  flows: FlowRef[];
  ticked: string[];
  onToggle: (code: string) => void;
  headingRef: Ref<HTMLHeadingElement>;
}) {
  const id = useId();
  const groups: Array<{
    key: string | null;
    title: string;
    items: ScreenItem[];
  }> = [];

  for (const item of items) {
    let group = groups.find((g) => g.key === item.group);

    if (!group) {
      const flow = flows.find((f) => f.key === item.group);
      group = {
        key: item.group,
        title:
          item.group === null
            ? t("guidance.screenGroupGeneral")
            : tFormat("guidance.screenGroupFor", {
                title: flow?.title ?? item.group,
              }),
        items: [],
      };
      groups.push(group);
    }

    group.items.push(item);
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <StepHeading headingRef={headingRef}>
          {t("guidance.safetyCheck")}
        </StepHeading>
        <p className="type-reading text-ink-2">{t("guidance.redFlagIntro")}</p>
      </div>
      {groups.map((group, index) => (
        <fieldset
          key={group.key ?? "global"}
          aria-labelledby={`${id}-group-${index}`}
          className="min-w-0"
        >
          <h3 id={`${id}-group-${index}`} className="mb-3 type-h3 text-ink">
            {group.title}
          </h3>
          <ul className="flex flex-col gap-3">
            {group.items.map((item) => (
              <li key={item.code}>
                <ChoiceCard
                  type="checkbox"
                  id={`${id}-flag-${item.code}`}
                  value={item.code}
                  checked={ticked.includes(item.code)}
                  onChange={() => onToggle(item.code)}
                  label={item.label}
                  hint={item.help}
                />
              </li>
            ))}
          </ul>
        </fieldset>
      ))}
    </div>
  );
}

// ----------------------------------------------------------------- question

export type NumberDraft = { text: string; unit: TimeUnit | null };

/**
 * The values to send for a number answer, or an error. The visitor may type
 * in an alternative unit; the API always receives the question's own unit.
 */
export function numberAnswer(
  node: GuidanceNode,
  draft: NumberDraft,
): { values: string[] } | { error: string } {
  const unit = node.unit ?? "count";
  const min = node.min ?? 0;
  const max = node.max ?? 0;
  const error = tFormat("guidance.numberInvalid", {
    min,
    max,
    unit: t(UNIT_KEYS[unit]),
  });
  const typed = parseLocaleNumber(draft.text);

  if (!Number.isFinite(typed)) {
    return { error };
  }

  const value =
    draft.unit && isTimeUnit(unit) && draft.unit !== unit
      ? convertToUnit(typed, draft.unit, unit)
      : typed;

  if (value < min || value > max) {
    return { error };
  }

  return { values: [formatAnswerNumber(value)] };
}

export function QuestionStep({
  node,
  values,
  onValues,
  numberDraft,
  onNumberDraft,
  headingRef,
}: {
  node: GuidanceNode;
  values: string[];
  onValues: (values: string[]) => void;
  numberDraft: NumberDraft;
  onNumberDraft: (draft: NumberDraft) => void;
  headingRef: Ref<HTMLHeadingElement>;
}) {
  const id = useId();

  if (node.type === "info") {
    return (
      <div className="flex flex-col gap-3">
        <StepHeading headingRef={headingRef}>{node.title}</StepHeading>
        <p className="type-reading measure text-ink">{node.text}</p>
      </div>
    );
  }

  const heading = (
    <div className="flex flex-col gap-2">
      <StepHeading headingRef={headingRef}>{node.text}</StepHeading>
      {node.help ? (
        <p className="type-reading text-ink-2">{node.help}</p>
      ) : null}
    </div>
  );

  if (node.kind === "number") {
    const unit = node.unit ?? "count";
    const units = [unit, ...(node.alt_units ?? [])] as TimeUnit[];
    const unknown = values[0] === "unknown";

    return (
      <div className="flex flex-col gap-6">
        {heading}
        <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:max-w-md">
          <Input
            label={t("guidance.numberLabel")}
            inputMode="decimal"
            autoComplete="off"
            value={unknown ? "" : numberDraft.text}
            disabled={unknown}
            onChange={(e) =>
              onNumberDraft({ ...numberDraft, text: e.target.value })
            }
          />
          {units.length > 1 ? (
            <Select
              label={t("guidance.numberUnitLabel")}
              value={numberDraft.unit ?? unit}
              disabled={unknown}
              onChange={(e) =>
                onNumberDraft({
                  ...numberDraft,
                  unit: e.target.value as TimeUnit,
                })
              }
            >
              {units.map((u) => (
                <option key={u} value={u}>
                  {t(UNIT_KEYS[u])}
                </option>
              ))}
            </Select>
          ) : (
            <p className="self-end pb-3 type-body text-ink">
              {t(UNIT_KEYS[unit])}
            </p>
          )}
        </div>
        {node.allow_unknown ? (
          <ChoiceCard
            type="checkbox"
            id={`${id}-unknown`}
            value="unknown"
            checked={unknown}
            onChange={() => onValues(unknown ? [] : ["unknown"])}
            label={t("guidance.unsure")}
          />
        ) : null}
      </div>
    );
  }

  if (node.kind === "scale") {
    return (
      <div className="flex flex-col gap-6">
        {heading}
        <fieldset aria-labelledby="guidance-step-heading" className="min-w-0">
          <legend className="sr-only">{t("guidance.scaleLabel")}</legend>
          <div className="grid grid-cols-6 gap-2 sm:grid-cols-11">
            {Array.from({ length: 11 }, (_, n) => String(n)).map((n) => (
              <label
                key={n}
                htmlFor={`${id}-scale-${n}`}
                className={cn(
                  "relative flex min-h-12 cursor-pointer items-center justify-center rounded-card bg-white text-lg font-semibold text-ink shadow-card",
                  values[0] === n
                    ? "bg-ink text-white"
                    : "hover:ring-[1.5px] hover:ring-inset hover:ring-line-strong",
                )}
              >
                <input
                  type="radio"
                  id={`${id}-scale-${n}`}
                  name={`${id}-scale`}
                  value={n}
                  checked={values[0] === n}
                  onChange={() => onValues([n])}
                  className="absolute inset-0 cursor-pointer appearance-none rounded-card"
                />
                <span aria-hidden="true">{n}</span>
                <span className="sr-only">
                  {n === "0"
                    ? `0 — ${node.min_label ?? t("guidance.scaleMin")}`
                    : n === "10"
                      ? `10 — ${node.max_label ?? t("guidance.scaleMax")}`
                      : n}
                </span>
              </label>
            ))}
          </div>
          <div
            aria-hidden="true"
            className="mt-2 flex justify-between type-meta text-ink-2"
          >
            <span>0 — {node.min_label ?? t("guidance.scaleMin")}</span>
            <span>10 — {node.max_label ?? t("guidance.scaleMax")}</span>
          </div>
        </fieldset>
      </div>
    );
  }

  const options =
    node.kind === "yes_no"
      ? [
          { value: "yes", label: t("guidance.yes") },
          { value: "no", label: t("guidance.no") },
          ...(node.allow_unsure
            ? [{ value: "unsure", label: t("guidance.unsure") }]
            : []),
        ]
      : (node.options ?? []);
  const multi = node.kind === "multi";

  return (
    <div className="flex flex-col gap-6">
      {heading}
      <fieldset aria-labelledby="guidance-step-heading" className="min-w-0">
        <ul className="flex flex-col gap-3">
          {options.map((option) => (
            <li key={option.value}>
              <ChoiceCard
                type={multi ? "checkbox" : "radio"}
                name={`${id}-${node.id}`}
                id={`${id}-${node.id}-${option.value}`}
                value={option.value}
                checked={values.includes(option.value)}
                onChange={() => {
                  if (!multi) {
                    onValues([option.value]);

                    return;
                  }

                  const exclusive =
                    "exclusive" in option && option.exclusive === true;
                  const exclusives = new Set(
                    (node.options ?? [])
                      .filter((o) => o.exclusive)
                      .map((o) => o.value),
                  );

                  if (values.includes(option.value)) {
                    onValues(values.filter((v) => v !== option.value));
                  } else if (exclusive) {
                    onValues([option.value]);
                  } else {
                    onValues([
                      ...values.filter((v) => !exclusives.has(v)),
                      option.value,
                    ]);
                  }
                }}
                label={option.label}
                hint={"help" in option ? option.help : undefined}
              />
            </li>
          ))}
        </ul>
      </fieldset>
    </div>
  );
}

export function ProgressHeader({
  flows,
  flow,
  answered,
  remaining,
}: {
  flows: FlowRef[];
  flow: FlowRef & { position: number };
  answered: number;
  remaining: number;
}) {
  const total = answered + Math.max(remaining, 1);
  const progress = Math.min(100, Math.round(((answered + 1) / total) * 100));

  return (
    <div className="flex flex-col gap-3">
      <p className="flex flex-wrap items-center gap-x-3 gap-y-1 type-meta font-semibold text-ink-2">
        {flows.length > 1 ? (
          <span>
            {tFormat("guidance.symptomOf", {
              index: flow.position + 1,
              count: flows.length,
              title: flow.title,
            })}
          </span>
        ) : (
          <span>{flow.title}</span>
        )}
        <span aria-hidden="true">·</span>
        <span>
          {tFormat("guidance.questionNumber", { count: answered + 1 })}
        </span>
      </p>
      <div
        aria-hidden="true"
        className="h-1.5 w-full overflow-hidden rounded-full bg-sand"
      >
        <div
          className="h-full rounded-full bg-ink"
          style={{ width: `${progress}%` }}
        />
      </div>
    </div>
  );
}

export function SelectedSymptoms({ titles }: { titles: string[] }) {
  if (titles.length === 0) {
    return null;
  }

  return (
    <p className="flex flex-wrap items-center gap-2 type-meta text-ink-2">
      <Icon name="check" size={18} />
      {titles.join(" · ")}
    </p>
  );
}
