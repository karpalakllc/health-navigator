import { useId, type ReactNode } from "react";
import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/*
 * One input style: label above (16/600), optional hint, the control (56px,
 * white, 1.5px #857369 border, radius 14), then the error. Errors are ink
 * text with a warning icon and the „Грешка:“ prefix, plus a 2px ink border —
 * never red, so an error can never be mistaken for the emergency colour.
 *
 * Wiring: the control gets aria-describedby = "<hint id> <error id>" (only
 * those present) and aria-invalid when there is an error. Pass `id` to fix the
 * control's id (e.g. for an external <label>); otherwise one is generated.
 */

type FieldMeta = {
  label: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  /** Visually hide the label (it stays the accessible name). */
  hideLabel?: boolean;
  /** Extra classes for the wrapper. */
  className?: string;
};

export function describedBy(
  ...ids: Array<string | false | null | undefined>
): string | undefined {
  const joined = ids.filter(Boolean).join(" ");
  return joined === "" ? undefined : joined;
}

function useFieldIds(id: string | undefined) {
  const generated = useId();
  const controlId = id ?? `f${generated.replace(/[^a-zA-Z0-9_-]/g, "")}`;
  return {
    controlId,
    hintId: `${controlId}-hint`,
    errorId: `${controlId}-error`,
  };
}

/** The label row used above a control (or as a <legend> style). */
export function FieldLabel({
  htmlFor,
  children,
  hidden,
  required,
}: {
  htmlFor: string;
  children: ReactNode;
  hidden?: boolean;
  required?: boolean;
}) {
  return (
    <label
      htmlFor={htmlFor}
      className={cn("type-label block text-ink", hidden && "sr-only")}
    >
      {children}
      {required ? (
        <>
          {" "}
          <span className="sr-only">({t("ui.required")})</span>
        </>
      ) : null}
    </label>
  );
}

export function FieldHint({
  id,
  children,
}: {
  id: string;
  children: ReactNode;
}) {
  return (
    <p id={id} className="type-meta text-ink-2">
      {children}
    </p>
  );
}

/** Per-field error: ink text + warning icon + „Грешка:“ prefix. */
export function FieldError({
  id,
  children,
}: {
  id: string;
  children: ReactNode;
}) {
  return (
    <p
      id={id}
      className="flex items-start gap-2 type-body font-semibold text-ink"
    >
      <Icon name="alert-triangle" size={20} className="mt-0.5" />
      <span>
        {`${t("ui.errorPrefix")} `}
        {children}
      </span>
    </p>
  );
}

function FieldShell({
  meta,
  ids,
  required,
  children,
}: {
  meta: FieldMeta;
  ids: ReturnType<typeof useFieldIds>;
  required?: boolean;
  children: ReactNode;
}) {
  return (
    <div className={cn("flex flex-col gap-2", meta.className)}>
      <FieldLabel
        htmlFor={ids.controlId}
        hidden={meta.hideLabel}
        required={required}
      >
        {meta.label}
      </FieldLabel>
      {meta.hint ? <FieldHint id={ids.hintId}>{meta.hint}</FieldHint> : null}
      {children}
      {meta.error ? (
        <FieldError id={ids.errorId}>{meta.error}</FieldError>
      ) : null}
    </div>
  );
}

function controlA11y(
  meta: FieldMeta,
  ids: ReturnType<typeof useFieldIds>,
  extraDescribedBy?: string,
) {
  return {
    id: ids.controlId,
    "aria-describedby": describedBy(
      meta.hint ? ids.hintId : null,
      meta.error ? ids.errorId : null,
      extraDescribedBy,
    ),
    "aria-invalid": meta.error ? (true as const) : undefined,
  };
}

type InputProps = FieldMeta &
  Omit<React.InputHTMLAttributes<HTMLInputElement>, "className"> & {
    inputClassName?: string;
  };

export function Input({
  label,
  hint,
  error,
  hideLabel,
  className,
  inputClassName,
  id,
  "aria-describedby": extra,
  ...inputProps
}: InputProps) {
  const ids = useFieldIds(id);
  const meta = { label, hint, error, hideLabel, className };

  return (
    <FieldShell meta={meta} ids={ids} required={inputProps.required}>
      <input
        {...inputProps}
        {...controlA11y(meta, ids, extra)}
        className={cn("field-control", inputClassName)}
      />
    </FieldShell>
  );
}

type TextareaProps = FieldMeta &
  Omit<React.TextareaHTMLAttributes<HTMLTextAreaElement>, "className"> & {
    textareaClassName?: string;
    /** e.g. „0 / 5.000“ — rendered under the control, linked by describedby. */
    counter?: ReactNode;
  };

export function Textarea({
  label,
  hint,
  error,
  hideLabel,
  className,
  textareaClassName,
  counter,
  id,
  "aria-describedby": extra,
  ...textareaProps
}: TextareaProps) {
  const ids = useFieldIds(id);
  const meta = { label, hint, error, hideLabel, className };
  const counterId = `${ids.controlId}-counter`;

  return (
    <FieldShell meta={meta} ids={ids} required={textareaProps.required}>
      <textarea
        {...textareaProps}
        {...controlA11y(
          meta,
          ids,
          describedBy(extra, counter != null ? counterId : null),
        )}
        className={cn("field-control", textareaClassName)}
      />
      {counter != null ? (
        <p id={counterId} className="type-meta text-right text-ink-2">
          {counter}
        </p>
      ) : null}
    </FieldShell>
  );
}

type SelectProps = FieldMeta &
  Omit<React.SelectHTMLAttributes<HTMLSelectElement>, "className"> & {
    selectClassName?: string;
  };

/** Native select with a visible chevron (so it never reads as a text field). */
export function Select({
  label,
  hint,
  error,
  hideLabel,
  className,
  selectClassName,
  id,
  children,
  "aria-describedby": extra,
  ...selectProps
}: SelectProps) {
  const ids = useFieldIds(id);
  const meta = { label, hint, error, hideLabel, className };

  return (
    <FieldShell meta={meta} ids={ids} required={selectProps.required}>
      <div className="relative">
        <select
          {...selectProps}
          {...controlA11y(meta, ids, extra)}
          className={cn("field-control", selectClassName)}
        >
          {children}
        </select>
        <Icon
          name="chevron-down"
          size={20}
          className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-ink"
        />
      </div>
    </FieldShell>
  );
}

type ChoiceProps = Omit<
  React.InputHTMLAttributes<HTMLInputElement>,
  "type" | "className"
> & {
  label: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  className?: string;
};

/**
 * Checkbox / radio row: a 24px box inside a ≥48px clickable row; the whole
 * row is the <label>. Hint and error are linked by aria-describedby.
 */
function Choice({
  type,
  label,
  hint,
  error,
  className,
  id,
  "aria-describedby": extra,
  ...inputProps
}: ChoiceProps & { type: "checkbox" | "radio" }) {
  const ids = useFieldIds(id);
  const round = type === "radio";

  return (
    <div className={cn("flex flex-col gap-1", className)}>
      <label
        htmlFor={ids.controlId}
        className={cn(
          "flex min-h-12 cursor-pointer items-start gap-3 py-3 type-body text-ink",
          inputProps.disabled && "cursor-not-allowed text-ink-2",
        )}
      >
        <span className="relative mt-px inline-flex size-6 shrink-0">
          <input
            {...inputProps}
            type={type}
            id={ids.controlId}
            aria-describedby={describedBy(
              hint ? ids.hintId : null,
              error ? ids.errorId : null,
              extra,
            )}
            aria-invalid={error ? true : undefined}
            className={cn(
              "peer size-6 cursor-[inherit] appearance-none border-[1.5px] border-line-strong bg-white",
              "checked:border-ink checked:bg-ink disabled:bg-sand",
              "aria-[invalid=true]:border-2 aria-[invalid=true]:border-ink",
              round ? "rounded-full" : "rounded-[6px]",
            )}
          />
          {round ? (
            <span
              aria-hidden="true"
              className="pointer-events-none absolute inset-0 m-auto size-2.5 rounded-full bg-white opacity-0 peer-checked:opacity-100"
            />
          ) : (
            <Icon
              name="check"
              size={16}
              className="pointer-events-none absolute inset-0 m-auto text-white opacity-0 peer-checked:opacity-100"
            />
          )}
        </span>
        <span>{label}</span>
      </label>
      {hint ? (
        <div className="pl-9">
          <FieldHint id={ids.hintId}>{hint}</FieldHint>
        </div>
      ) : null}
      {error ? (
        <div className="pl-9">
          <FieldError id={ids.errorId}>{error}</FieldError>
        </div>
      ) : null}
    </div>
  );
}

export function Checkbox(props: ChoiceProps) {
  return <Choice type="checkbox" {...props} />;
}

export function Radio(props: ChoiceProps) {
  return <Choice type="radio" {...props} />;
}

/** Groups radios/checkboxes under a legend, with a group hint and error. */
export function Fieldset({
  legend,
  hint,
  error,
  hideLegend,
  className,
  children,
}: {
  legend: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  hideLegend?: boolean;
  className?: string;
  children: ReactNode;
}) {
  const ids = useFieldIds(undefined);

  return (
    <fieldset
      className={cn("flex min-w-0 flex-col gap-1", className)}
      aria-describedby={describedBy(
        hint ? ids.hintId : null,
        error ? ids.errorId : null,
      )}
    >
      <legend
        className={cn("type-label mb-1 text-ink", hideLegend && "sr-only")}
      >
        {legend}
      </legend>
      {hint ? <FieldHint id={ids.hintId}>{hint}</FieldHint> : null}
      {children}
      {error ? <FieldError id={ids.errorId}>{error}</FieldError> : null}
    </fieldset>
  );
}
