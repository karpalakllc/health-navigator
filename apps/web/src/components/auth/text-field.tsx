import { useId, type ReactNode } from "react";
import {
  describedBy,
  FieldError,
  FieldHint,
  FieldLabel,
} from "@/components/ui/field";
import { cn } from "@/lib/cn";

type TextFieldProps = Omit<
  React.InputHTMLAttributes<HTMLInputElement>,
  "className"
> & {
  label: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  className?: string;
};

/**
 * The shared field layout (label above, hint, control, error) for the auth and
 * account forms, built from the field.tsx blocks.
 *
 * Unlike <Input>, a required field's label carries no hidden „(задолжително)“:
 * the native `required` attribute is already announced, and these forms keep
 * each field's accessible name equal to its visible label (WCAG 2.5.3) — the
 * tests and E2E specs find the fields by exactly that name.
 */
export function TextField({
  label,
  hint,
  error,
  className,
  id,
  "aria-describedby": extra,
  ...inputProps
}: TextFieldProps) {
  const generated = useId();
  const inputId = id ?? `tf${generated.replace(/[^a-zA-Z0-9_-]/g, "")}`;
  const hintId = `${inputId}-hint`;
  const errorId = `${inputId}-error`;

  return (
    <div className={cn("flex flex-col gap-2", className)}>
      <FieldLabel htmlFor={inputId}>{label}</FieldLabel>
      {hint ? <FieldHint id={hintId}>{hint}</FieldHint> : null}
      <input
        {...inputProps}
        id={inputId}
        aria-invalid={error ? true : inputProps["aria-invalid"]}
        aria-describedby={describedBy(
          hint ? hintId : null,
          error ? errorId : null,
          extra,
        )}
        className="field-control w-full read-only:bg-sand read-only:text-ink-2"
      />
      {error ? <FieldError id={errorId}>{error}</FieldError> : null}
    </div>
  );
}
