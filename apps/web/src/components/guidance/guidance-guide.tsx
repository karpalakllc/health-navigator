"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { EmergencyCard } from "@/components/guidance/emergency-card";
import { GuidanceResult } from "@/components/guidance/guidance-result";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import {
  DemographicsStep,
  EMPTY_DEMOGRAPHICS,
  ProgressHeader,
  QuestionStep,
  ScreenStep,
  SelectedSymptoms,
  SymptomsStep,
  demographicsFromForm,
  numberAnswer,
  type DemographicsForm,
  type NumberDraft,
} from "@/components/guidance/guidance-steps";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/field";
import { FormError } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import {
  GuidanceApiError,
  isStaleGuidanceSession,
  parseStoredGuidanceSession,
  type GuidanceSessionHandle,
} from "@/lib/api/guidance";
import {
  answerQuestion,
  answerScreen,
  chooseSymptoms,
  emergencyShortcut,
  fetchState,
  noMatch,
  saveDemographics,
  startSession,
  type BodyArea,
  type Demographics,
  type GuidanceCatalog,
  type GuidanceNode,
  type GuidanceState,
} from "@/lib/api/guidance-v2";
import { cn } from "@/lib/cn";
import { recordFunnelStep } from "@/lib/feedback";
import { funnelStepsFor } from "@/lib/guidance/next-steps";
import {
  answerLabel,
  buildSummary,
  type AnsweredQuestion,
} from "@/lib/guidance/summary";
import { t } from "@/i18n/t";

// Holds {id, token}; the token is what lets this tab continue the session.
const SESSION_KEY = "guidance_session_v2";
// Earlier builds' keys: they can no longer continue anything.
const LEGACY_KEYS = ["guidance_session", "guidance_session_id"];

/** The global screen's self-harm red flag (it routes to the crisis outcome). */
const SELF_HARM_FLAG = "global.self_harm";

type Phase = "intro" | "guide" | "emergency";

type Props = {
  catalog: GuidanceCatalog;
  /** Whether the pharmacy directory is on, so results may link to it. */
  pharmaciesOn?: boolean;
};

type QuestionState = Extract<GuidanceState, { stage: "question" }>;

/**
 * Symptom guidance v2: intro → who → symptoms (search / body map, up to 3) →
 * one red-flag screen → questions, most urgent symptom first → one result.
 *
 * The urgent-help button and the compact 194/112 line are on every step; any
 * path to an emergency shows the tap-to-call links at once, before and
 * regardless of the API (docs/triage-safety.md).
 */
export function GuidanceGuide({ catalog, pharmaciesOn = false }: Props) {
  const [phase, setPhase] = useState<Phase>("intro");
  const [accepted, setAccepted] = useState(false);
  const [state, setState] = useState<GuidanceState | null>(null);
  // Earlier steps of this tab, for Back (no server call: re-submitting an
  // earlier step makes the API discard what came after it).
  const [history, setHistory] = useState<GuidanceState[]>([]);
  const [form, setForm] = useState<DemographicsForm>(EMPTY_DEMOGRAPHICS);
  const [demo, setDemo] = useState<Demographics | null>(null);
  const [symptoms, setSymptoms] = useState<string[]>([]);
  const [area, setArea] = useState<BodyArea | null>(null);
  const [ticked, setTicked] = useState<string[]>([]);
  const [values, setValues] = useState<string[]>([]);
  const [numberDraft, setNumberDraft] = useState<NumberDraft>({
    text: "",
    unit: null,
  });
  // What this tab answered, in order: the summary for the doctor.
  const [answered, setAnswered] = useState<
    Array<AnsweredQuestion & { values: string[]; node: GuidanceNode }>
  >([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [emergencySaving, setEmergencySaving] = useState(false);
  const [emergencyFromShortcut, setEmergencyFromShortcut] = useState(false);
  // The self-harm flag was ticked: until the API answers (or if it fails) the
  // interim card uses the crisis wording, not the generic emergency one.
  const [emergencyIsCrisis, setEmergencyIsCrisis] = useState(false);
  const [finishedAt, setFinishedAt] = useState<Date | null>(null);

  /*
   * Bumped whenever the emergency path takes over (and on a restart). A
   * request started before that checks it when it settles and drops its
   * result, so a late ordinary answer can never replace the emergency screen.
   */
  const generation = useRef(0);

  // Each step replaces the panel in place: its heading takes focus and the
  // step is scrolled to, so everyone starts at the new question.
  const headingRef = useRef<HTMLHeadingElement>(null);
  const firstRender = useRef(true);
  const stepKey =
    state === null
      ? phase
      : state.stage === "question"
        ? `${phase}-${state.flow.key}-${state.node.id}`
        : `${phase}-${state.stage}`;

  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false;

      return;
    }

    const heading = headingRef.current;
    const step = heading?.closest("[data-guidance-step]") ?? heading;
    step?.scrollIntoView?.({ block: "start" });
    heading?.focus({ preventScroll: true });
  }, [stepKey]);

  // Anonymous drop-off counters (docs/urgent-care.md § 4): each step once per
  // run, none with GPC/DNT (recordFunnelStep checks). Never the answers.
  const countedSteps = useRef(new Set<string>());

  useEffect(() => {
    if (!state) return;

    const steps = funnelStepsFor(
      state,
      (flowKey) =>
        answered.filter((a) => a.id.startsWith(`${flowKey}.`)).length,
    );

    for (const { funnel, step, depth } of steps) {
      const key = `${funnel}|${step}`;
      if (countedSteps.current.has(key)) continue;
      countedSteps.current.add(key);
      recordFunnelStep(funnel, step, depth);
    }
    // Counted when the step is shown; `answered` is read as of that moment.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  // ------------------------------------------------------------ session

  const session = useRef<GuidanceSessionHandle | null>(null);
  const starting = useRef<Promise<GuidanceSessionHandle> | null>(null);
  const storageEpoch = useRef(0);

  function clearStoredSession() {
    storageEpoch.current++;
    session.current = null;
    starting.current = null;

    try {
      window.sessionStorage.removeItem(SESSION_KEY);
    } catch {
      // Storage unavailable: nothing was stored.
    }
  }

  const ensureSession = useCallback(
    async (fresh = false): Promise<GuidanceSessionHandle> => {
      if (fresh) {
        session.current = null;
        starting.current = null;
      } else {
        if (session.current) return session.current;
        if (starting.current) return starting.current;
      }

      const epoch = storageEpoch.current;
      const request = startSession().then(
        ({ handle }) => {
          if (starting.current === request) starting.current = null;

          if (storageEpoch.current === epoch) {
            session.current = handle;

            try {
              window.sessionStorage.setItem(
                SESSION_KEY,
                JSON.stringify(handle),
              );
            } catch {
              // The session still works for this page view.
            }
          }

          return handle;
        },
        (e: unknown) => {
          if (starting.current === request) starting.current = null;

          throw e;
        },
      );
      starting.current = request;

      return request;
    },
    [],
  );

  // A tab that already started (and accepted the terms) continues where it was.
  useEffect(() => {
    let stored: GuidanceSessionHandle | null = null;

    try {
      for (const key of LEGACY_KEYS) window.sessionStorage.removeItem(key);
      stored = parseStoredGuidanceSession(
        window.sessionStorage.getItem(SESSION_KEY),
      );
    } catch {
      stored = null;
    }

    if (!stored) return;

    const gen = generation.current;
    fetchState(stored).then(
      (resumed) => {
        if (generation.current !== gen || resumed.stage === "result") {
          if (resumed.stage === "result") clearStoredSession();

          return;
        }

        session.current = stored;
        setAccepted(true);
        setState(resumed);
        setPhase("guide");
      },
      () => {
        if (generation.current === gen) clearStoredSession();
      },
    );
    // Mount only.
  }, []);

  // -------------------------------------------------------- emergency path

  async function enterEmergency(
    record: (current: GuidanceSessionHandle) => Promise<GuidanceState>,
  ) {
    const gen = ++generation.current;

    setError(null);
    setPhase("emergency");
    setLoading(false);
    setEmergencySaving(true);

    const attempt = async (fresh: boolean) =>
      record(await ensureSession(fresh));

    try {
      let result: GuidanceState;

      try {
        result = await attempt(false);
      } catch (e) {
        if (!isStaleGuidanceSession(e)) throw e;
        result = await attempt(true);
      }

      if (generation.current !== gen) return;

      if (result.stage === "result") {
        setState(result);
        setFinishedAt(new Date());
        setPhase("guide");
        clearStoredSession();
      }
    } catch (e) {
      if (generation.current === gen) {
        setError(e instanceof Error ? e.message : t("guidance.emergencyError"));
      }
    } finally {
      if (generation.current === gen) setEmergencySaving(false);
    }
  }

  async function handleEmergencyNow() {
    setEmergencyFromShortcut(true);
    setEmergencyIsCrisis(false);
    await enterEmergency(emergencyShortcut);
  }

  // ------------------------------------------------------------- stepping

  /**
   * Runs one ordinary step. The result only applies, and errors only show,
   * while nothing newer (the emergency path, a restart) has taken over.
   */
  async function runStep(
    work: (current: GuidanceSessionHandle) => Promise<GuidanceState>,
    onResult?: (next: GuidanceState) => void,
  ) {
    const gen = generation.current;

    setError(null);
    setLoading(true);

    try {
      const next = await work(await ensureSession());

      if (generation.current !== gen) return;

      if (next.stage === "result" && next.level === "emergency_now") {
        generation.current++;
      }

      onResult?.(next);
      show(next);
    } catch (e) {
      if (generation.current !== gen) return;

      if (
        e instanceof GuidanceApiError &&
        e.status === 404 &&
        session.current !== null
      ) {
        // Expired, purged or already finished elsewhere: only a new check helps.
        clearStoredSession();
        setError(t("guidance.resumeError"));
      } else {
        setError(e instanceof Error ? e.message : t("guidance.saveError"));
      }
    } finally {
      if (generation.current === gen) setLoading(false);
    }
  }

  function show(next: GuidanceState) {
    if (state && state.stage !== "result") {
      setHistory((h) => [...h, state]);
    }

    setState(next);
    setValues([]);
    setNumberDraft({ text: "", unit: null });

    if (next.stage === "question") {
      const previous = answered.find(
        (a) => a.id === `${next.flow.key}.${next.node.id}`,
      );
      if (previous) setValues(previous.values);
    }

    if (next.stage === "result") {
      setFinishedAt(new Date());
      clearStoredSession();
    }
  }

  async function handleStart() {
    if (!accepted) {
      setError(t("guidance.confirmRequired"));

      return;
    }

    const gen = generation.current;
    setError(null);
    setLoading(true);

    try {
      await ensureSession();

      if (generation.current !== gen) return;

      setState({
        session_id: session.current?.id ?? "",
        stage: "demographics",
      });
      setPhase("guide");
    } catch (e) {
      if (generation.current === gen) {
        setError(e instanceof Error ? e.message : t("guidance.sessionError"));
      }
    } finally {
      if (generation.current === gen) setLoading(false);
    }
  }

  async function handleDemographics() {
    const parsed = demographicsFromForm(form);

    if ("error" in parsed) {
      setError(parsed.error);

      return;
    }

    await runStep(
      (current) => saveDemographics(current, parsed.demo),
      () => {
        setDemo(parsed.demo);
        setAnswered([]);
        setTicked([]);
      },
    );
  }

  async function submitSymptoms(keys: string[]) {
    if (keys.length === 0) {
      setError(t("guidance.chooseSymptom"));

      return;
    }

    await runStep(
      (current) => chooseSymptoms(current, keys),
      () => {
        setAnswered([]);
        setTicked([]);
      },
    );
  }

  async function handleNoMatch() {
    const gen = generation.current;
    setError(null);
    setLoading(true);

    try {
      const current = await ensureSession();
      const { suggested } = await noMatch(current, area);

      if (generation.current !== gen) return;

      setSymptoms(suggested);
      setLoading(false);
      await submitSymptoms(suggested);
    } catch (e) {
      if (generation.current === gen) {
        setError(e instanceof Error ? e.message : t("guidance.saveError"));
        setLoading(false);
      }
    }
  }

  async function handleScreen() {
    if (ticked.length > 0) {
      setEmergencyFromShortcut(false);
      setEmergencyIsCrisis(ticked.includes(SELF_HARM_FLAG));
      await enterEmergency((current) => answerScreen(current, ticked));

      return;
    }

    await runStep(
      (current) => answerScreen(current, []),
      () => setAnswered([]),
    );
  }

  async function handleAnswer(question: QuestionState, skip = false) {
    const node = question.node;
    let submitted = skip ? [] : values;

    if (!skip && node.type === "info") {
      submitted = ["seen"];
    } else if (!skip && node.kind === "number" && values[0] !== "unknown") {
      const parsed = numberAnswer(node, numberDraft);

      if ("error" in parsed) {
        setError(parsed.error);

        return;
      }

      submitted = parsed.values;
    } else if (!skip && submitted.length === 0) {
      setError(t("guidance.selectOption"));

      return;
    }

    const id = `${question.flow.key}.${node.id}`;

    await runStep(
      (current) =>
        answerQuestion(current, question.flow.key, node.id, submitted),
      () =>
        setAnswered((log) => {
          const index = log.findIndex((a) => a.id === id);
          const kept = index === -1 ? log : log.slice(0, index);

          return node.type === "info"
            ? kept
            : [
                ...kept,
                {
                  id,
                  flowTitle: question.flow.title,
                  question: node.text ?? "",
                  answer: answerLabel(node, submitted),
                  values: submitted,
                  node,
                },
              ];
        }),
    );
  }

  function handleBack() {
    setError(null);
    const previous = history.at(-1);

    if (!previous) {
      setPhase("intro");

      return;
    }

    setHistory((h) => h.slice(0, -1));
    setState(previous);
    setNumberDraft({ text: "", unit: null });

    if (previous.stage === "question") {
      const before = answered.find(
        (a) => a.id === `${previous.flow.key}.${previous.node.id}`,
      );
      setValues(before?.values ?? []);

      if (
        before &&
        previous.node.kind === "number" &&
        before.values[0] !== "unknown"
      ) {
        setNumberDraft({
          text: before.values[0].replace(".", ","),
          unit: null,
        });
      }
    } else {
      setValues([]);
    }
  }

  function handleRestart() {
    generation.current++;
    countedSteps.current = new Set();
    clearStoredSession();
    setAccepted(false);
    setState(null);
    setHistory([]);
    setForm(EMPTY_DEMOGRAPHICS);
    setDemo(null);
    setSymptoms([]);
    setArea(null);
    setTicked([]);
    setValues([]);
    setAnswered([]);
    setError(null);
    setLoading(false);
    setEmergencySaving(false);
    setEmergencyFromShortcut(false);
    setFinishedAt(null);
    setPhase("intro");
  }

  // ------------------------------------------------------------- render

  const pageTitle = (
    <>
      {catalog.preview ? (
        <p
          role="note"
          className="rounded-card bg-sand px-4 py-3 type-body font-semibold text-ink"
        >
          {t("guidance.previewBanner")}
        </p>
      ) : null}
      {phase === "intro" ? (
        <h1 ref={headingRef} tabIndex={-1} className="type-h1 text-ink">
          {t("guidance.title")}
        </h1>
      ) : (
        <h1 className="sr-only">{t("guidance.title")}</h1>
      )}
    </>
  );

  if (phase === "intro") {
    return (
      <div data-guidance-step className="flex flex-col gap-6 lg:gap-8">
        <section className="flex flex-col gap-3 rounded-sheet bg-apricot px-5 py-6 lg:gap-4 lg:px-10 lg:py-10">
          <div className="flex items-center gap-4">
            <span
              aria-hidden="true"
              className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-white text-ink lg:size-14"
            >
              <Icon name="compass" size={26} />
            </span>
            {pageTitle}
          </div>
          <p className="type-reading measure text-ink">
            {t("guidance.description")}
          </p>
          <p className="flex items-center gap-2 type-meta text-ink-2">
            <Icon name="shield-check" size={20} className="shrink-0" />
            {t("guidance.introAnonymous")}
          </p>
        </section>

        <div className="flex flex-col items-start gap-3">
          <EmergencyShortcutButton
            onEmergency={handleEmergencyNow}
            className="w-full lg:w-auto"
          />
          <GuidanceSafetyNotice compact />
        </div>

        <GuidanceSafetyNotice withoutEmergencyLine />

        <div className="flex flex-col gap-3">
          <Card padding="md">
            <Checkbox
              label={t("guidance.acceptLabel")}
              checked={accepted}
              onChange={(e) => setAccepted(e.target.checked)}
            />
          </Card>
          {error ? <FormError>{error}</FormError> : null}
        </div>

        <div>
          <Button
            size="lg"
            fullWidth
            loading={loading}
            onClick={handleStart}
            trailingIcon={loading ? undefined : "arrow-right"}
            className="lg:w-auto"
          >
            {loading ? t("guidance.starting") : t("guidance.continue")}
          </Button>
        </div>
      </div>
    );
  }

  if (phase === "emergency") {
    return (
      <div data-guidance-step className="flex flex-col gap-6">
        {pageTitle}
        <EmergencyCard
          title={
            emergencyIsCrisis
              ? t("guidance.emergencyCrisisTitle")
              : t("guidance.emergencyInterimTitle")
          }
          body={
            emergencyIsCrisis
              ? t("guidance.emergencyCrisisBody")
              : emergencyFromShortcut
                ? t("guidance.emergencyShortcutBody")
                : t("guidance.emergencyDelay")
          }
          headingRef={headingRef}
        >
          {emergencySaving ? (
            <p className="type-meta text-ink-2" role="status">
              {t("guidance.saving")}
            </p>
          ) : null}
        </EmergencyCard>
        {error ? <FormError>{error}</FormError> : null}
        <Button
          variant="secondary"
          leadingIcon="arrow-left"
          onClick={handleRestart}
          className="self-start"
        >
          {t("guidance.restart")}
        </Button>
      </div>
    );
  }

  if (state === null) {
    return null;
  }

  if (state.stage === "result") {
    const summary = buildSummary({
      date: finishedAt ?? new Date(),
      demo,
      symptoms: state.flows.map((f) => f.title),
      answers: answered,
      outcomes: state.outcomes.map(({ flow, outcome }) => ({
        symptom: flow?.title ?? null,
        outcome,
      })),
      redFlagStop: state.reason === "red_flag",
    });

    return (
      <div data-guidance-step className="flex flex-col gap-6">
        {pageTitle}
        <GuidanceResult
          result={state}
          summary={summary}
          pharmaciesOn={pharmaciesOn}
          headingRef={headingRef}
          onRestart={handleRestart}
        />
      </div>
    );
  }

  let body: React.ReactNode = null;
  let onContinue: () => void = () => {};

  switch (state.stage) {
    case "demographics":
      body = (
        <DemographicsStep
          form={form}
          onChange={setForm}
          headingRef={headingRef}
        />
      );
      onContinue = handleDemographics;
      break;
    case "symptoms":
      body = (
        <SymptomsStep
          flows={catalog.flows}
          ageBand={state.age_band}
          max={catalog.max_symptoms}
          selected={symptoms}
          onToggle={(key) =>
            setSymptoms((s) =>
              s.includes(key)
                ? s.filter((k) => k !== key)
                : s.length >= catalog.max_symptoms
                  ? s
                  : [...s, key],
            )
          }
          area={area}
          onArea={setArea}
          onNoMatch={handleNoMatch}
          noMatchPending={loading}
          headingRef={headingRef}
        />
      );
      onContinue = () => submitSymptoms(symptoms);
      break;
    case "screen":
      body = (
        <>
          <SelectedSymptoms titles={state.flows.map((f) => f.title)} />
          <ScreenStep
            items={state.screen}
            flows={state.flows}
            ticked={ticked}
            onToggle={(code) =>
              setTicked((tk) =>
                tk.includes(code)
                  ? tk.filter((c) => c !== code)
                  : [...tk, code],
              )
            }
            headingRef={headingRef}
          />
        </>
      );
      onContinue = handleScreen;
      break;
    case "question":
      body = (
        <>
          <ProgressHeader
            flows={state.flows}
            flow={state.flow}
            answered={state.progress.answered}
            remaining={state.progress.remaining_max}
          />
          <QuestionStep
            node={state.node}
            values={values}
            onValues={setValues}
            numberDraft={numberDraft}
            onNumberDraft={setNumberDraft}
            headingRef={headingRef}
          />
          {state.node.optional ? (
            <Button
              variant="ghost"
              onClick={() => handleAnswer(state, true)}
              className="self-start"
            >
              {t("guidance.skip")}
            </Button>
          ) : null}
        </>
      );
      onContinue = () => handleAnswer(state);
      break;
  }

  return (
    <div data-guidance-step className="flex flex-col gap-6">
      {pageTitle}
      <StepTopBar
        onBack={handleBack}
        backDisabled={loading}
        onEmergency={handleEmergencyNow}
      />
      {body}
      {error ? <FormError>{error}</FormError> : null}
      <GuidanceSafetyNotice compact />
      <StickyContinue>
        <Button
          size="lg"
          fullWidth
          loading={loading}
          onClick={onContinue}
          trailingIcon={loading ? undefined : "arrow-right"}
          className="lg:w-auto"
        >
          {loading ? t("guidance.saving") : t("guidance.continue")}
        </Button>
      </StickyContinue>
    </div>
  );
}

/**
 * The step's main action. On a phone it stays in the thumb zone, just above
 * the bottom tab bar (--tabbar-space), on a cream strip so the options scroll
 * under it; on desktop it sits in the flow.
 */
function StickyContinue({ children }: { children: React.ReactNode }) {
  return (
    <div
      data-sticky-action-bar
      className="sticky bottom-[calc(var(--tabbar-space)+var(--consent-h,0px))] z-10 -mx-5 bg-cream px-5 py-3 shadow-[0_-1px_0_var(--color-line)] lg:static lg:mx-0 lg:bg-transparent lg:p-0 lg:shadow-none"
    >
      {children}
    </div>
  );
}

/** Back (top left) and the emergency shortcut, on every step. */
function StepTopBar({
  onBack,
  backDisabled,
  onEmergency,
}: {
  onBack: () => void;
  backDisabled: boolean;
  onEmergency: () => void;
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <Button
        variant="soft"
        leadingIcon="chevron-left"
        disabled={backDisabled}
        onClick={onBack}
        className="pl-3"
      >
        {t("common.back")}
      </Button>
      <EmergencyShortcutButton onEmergency={onEmergency} size="md" />
    </div>
  );
}

/**
 * „Потребна ми е итна помош“, in the emergency treatment, on the intro and on
 * every step. Never disabled — not even while an answer is saving; the
 * generation guard keeps that late answer from replacing the emergency screen.
 */
function EmergencyShortcutButton({
  onEmergency,
  size = "lg",
  className,
}: {
  onEmergency: () => void;
  size?: "md" | "lg";
  className?: string;
}) {
  return (
    <Button
      size={size}
      leadingIcon="alert-triangle"
      onClick={onEmergency}
      className={cn(
        "border-2 border-ink bg-emergency text-white hover:bg-emergency-hover",
        size === "md" && "px-4",
        className,
      )}
    >
      {t("guidance.emergencyNow")}
    </Button>
  );
}
