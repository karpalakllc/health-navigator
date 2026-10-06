"use client";

import { useId, useState, type ReactNode } from "react";
import {
  describedBy,
  FieldError,
  FieldHint,
  FieldLabel,
} from "@/components/ui/field";
import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type PasswordFieldProps = {
  label: ReactNode;
  name: string;
  value: string;
  onChange: (value: string) => void;
  /** Fixes the input id (otherwise generated); the error id is `<id>-error`. */
  id?: string;
  hint?: ReactNode;
  error?: ReactNode;
  /** Shown at the right of the label row, outside the <label> (e.g. a link). */
  labelAside?: ReactNode;
  autoComplete?: string;
  required?: boolean;
  className?: string;
};

/**
 * The shared field layout (label above, hint, control, error) around a
 * password input with a show/hide toggle.
 *
 * The toggle sits inside the control's frame but outside its <label>, so the
 * field's accessible name stays the visible label alone.
 */
export function PasswordField({
  label,
  name,
  value,
  onChange,
  id,
  hint,
  error,
  labelAside,
  autoComplete,
  required,
  className,
}: PasswordFieldProps) {
  const generated = useId();
  const inputId = id ?? `pw${generated.replace(/[^a-zA-Z0-9_-]/g, "")}`;
  const hintId = `${inputId}-hint`;
  const errorId = `${inputId}-error`;
  const [visible, setVisible] = useState(false);

  return (
    <div className={cn("flex flex-col gap-2", className)}>
      {labelAside ? (
        <div className="flex flex-wrap items-center justify-between gap-x-4">
          <FieldLabel htmlFor={inputId}>{label}</FieldLabel>
          {labelAside}
        </div>
      ) : (
        <FieldLabel htmlFor={inputId}>{label}</FieldLabel>
      )}
      {hint ? <FieldHint id={hintId}>{hint}</FieldHint> : null}
      <div className="relative">
        <input
          id={inputId}
          type={visible ? "text" : "password"}
          name={name}
          autoComplete={autoComplete}
          required={required}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(
            hint ? hintId : null,
            error ? errorId : null,
          )}
          // w-full is explicit (as well as in .field-control): a missing
          // width once left this input at ~230px with the toggle floating.
          className="field-control w-full pr-16"
        />
        <button
          type="button"
          onClick={() => setVisible((current) => !current)}
          aria-controls={inputId}
          className="absolute right-1 top-1/2 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-full text-ink-2 hover:bg-sand hover:text-ink"
          aria-label={visible ? t("auth.hidePassword") : t("auth.showPassword")}
        >
          <Icon name={visible ? "eye-off" : "eye"} size={24} />
        </button>
      </div>
      {error ? <FieldError id={errorId}>{error}</FieldError> : null}
    </div>
  );
}
