import { t, tFormat } from "@/i18n/t";

/**
 * Our own field validation, in Macedonian.
 *
 * The forms set `noValidate`: the browser's own bubbles are in the browser's
 * language (English on most machines), appear once and vanish, and are not
 * tied to the field the way our FieldError (aria-describedby) is. `required`
 * and `type="email"` stay on the inputs for the semantics and the keyboard.
 */

/** A deliberately loose shape check; the API makes the final call. */
const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function requiredError(value: string): string | null {
  return value.trim() === "" ? t("ui.fieldRequired") : null;
}

/** Required, and at least `min` characters once trimmed (the API's min:N). */
export function lengthError(value: string, min: number): string | null {
  const length = value.trim().length;

  if (length === 0) {
    return t("ui.fieldRequired");
  }

  return length < min ? tFormat("ui.tooShort", { min }) : null;
}

export function emailError(value: string): string | null {
  const trimmed = value.trim();

  if (trimmed === "") {
    return t("ui.emailRequired");
  }

  return EMAIL_SHAPE.test(trimmed) ? null : t("ui.emailInvalid");
}

export function passwordConfirmationError(
  password: string,
  confirmation: string,
): string | null {
  if (confirmation === "") {
    return t("ui.fieldRequired");
  }

  return password === confirmation ? null : t("auth.passwordMismatch");
}

/** Only the entries that hold a message. */
export function compactErrors<F extends string>(
  errors: Partial<Record<F, string | null>>,
): Partial<Record<F, string>> {
  const out: Partial<Record<F, string>> = {};

  for (const [field, message] of Object.entries(errors) as Array<
    [F, string | null | undefined]
  >) {
    if (message) {
      out[field] = message;
    }
  }

  return out;
}

/*
 * The API answers in Macedonian (lang/mk/validation.php), but in Laravel's
 * generic wording („Полето лозинка мора да има најмалку 10 знаци.“) and with a
 * `password` „confirmed“ error filed under `password`. These patterns name the
 * rule behind a message (mk first, the English fallback second) so the form
 * can say it in its own words and under the right field.
 */
const CONFIRMED = /Потврдата .*не се совпаѓа|confirmation does not match/i;
const REQUIRED = /е задолжително|field is required/i;
const EMAIL = /валидна е-адреса|мора да биде валидна|valid email/i;
const PASSWORD_RULES =
  /најмалку \d+ знаци|барем една (буква|бројка|голема)|at least (\d+ characters|one (letter|number))|uppercase and one lowercase/i;

type MapOptions<F extends string> = {
  /** Fields that hold a password: their rule messages become the rules hint. */
  passwordField?: F;
  /** Where a `confirmed` error on `passwordField` belongs. */
  confirmationField?: F;
};

/**
 * Every field's errors from a Laravel 422 `errors` bag, in Macedonian, each
 * under the field it is about. Several messages for one field are joined.
 * Unknown keys are ignored (the form has no field to show them under).
 */
export function mapApiFieldErrors<F extends string>(
  errors: unknown,
  fields: readonly F[],
  options: MapOptions<F> = {},
): Partial<Record<F, string>> {
  const collected = new Map<F, string[]>();

  if (!errors || typeof errors !== "object") {
    return {};
  }

  const add = (field: F, message: string) => {
    const list = collected.get(field) ?? [];

    if (!list.includes(message)) {
      list.push(message);
    }

    collected.set(field, list);
  };

  for (const field of fields) {
    const messages = (errors as Record<string, unknown>)[field];

    if (!Array.isArray(messages)) {
      continue;
    }

    for (const raw of messages) {
      if (typeof raw !== "string" || raw.trim() === "") {
        continue;
      }

      if (
        field === options.passwordField &&
        options.confirmationField &&
        CONFIRMED.test(raw)
      ) {
        add(options.confirmationField, t("auth.passwordMismatch"));
      } else if (REQUIRED.test(raw)) {
        add(field, t("ui.fieldRequired"));
      } else if (field.endsWith("email") && EMAIL.test(raw)) {
        add(field, t("ui.emailInvalid"));
      } else if (field === options.passwordField && PASSWORD_RULES.test(raw)) {
        add(field, t("auth.passwordTooWeak"));
      } else {
        add(field, raw);
      }
    }
  }

  const out: Partial<Record<F, string>> = {};

  for (const field of fields) {
    const list = collected.get(field);

    if (list) {
      out[field] = list.join(" ");
    }
  }

  return out;
}

/** Moves focus to a field by id (the error summary's links). */
export function focusField(id: string): void {
  const element = document.getElementById(id);
  element?.scrollIntoView?.({ block: "center" });
  element?.focus({ preventScroll: true });
}
