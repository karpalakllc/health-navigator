import { describe, expect, it } from "vitest";
import {
  emailError,
  mapApiFieldErrors,
  passwordConfirmationError,
  requiredError,
} from "@/lib/form-validation";
import { t } from "@/i18n/t";

describe("client checks", () => {
  it("requires a non-blank value", () => {
    expect(requiredError("  ")).toBe(t("ui.fieldRequired"));
    expect(requiredError("Ана")).toBeNull();
  });

  it("asks for an e-mail, then for a well-formed one", () => {
    expect(emailError("")).toBe(t("ui.emailRequired"));
    expect(emailError("ana@")).toBe(t("ui.emailInvalid"));
    expect(emailError("ana@example")).toBe(t("ui.emailInvalid"));
    expect(emailError(" ana@example.mk ")).toBeNull();
  });

  it("compares the confirmation with the password", () => {
    expect(passwordConfirmationError("abc", "")).toBe(t("ui.fieldRequired"));
    expect(passwordConfirmationError("abc", "abd")).toBe(
      t("auth.passwordMismatch"),
    );
    expect(passwordConfirmationError("abc", "abc")).toBeNull();
  });
});

describe("mapApiFieldErrors", () => {
  const fields = ["name", "email", "password", "password_confirmation"] as const;
  const options = {
    passwordField: "password",
    confirmationField: "password_confirmation",
  } as const;

  it("maps every field and moves the „confirmed“ error to the confirmation", () => {
    expect(
      mapApiFieldErrors(
        {
          name: ["Полето име е задолжително."],
          email: ["Полето е-адреса мора да биде валидна е-адреса."],
          password: [
            "Полето лозинка мора да има најмалку 10 знаци.",
            "Полето лозинка мора да содржи барем една бројка.",
            "Потврдата на полето лозинка не се совпаѓа.",
          ],
          device_name: ["ignored"],
        },
        fields,
        options,
      ),
    ).toEqual({
      name: t("ui.fieldRequired"),
      email: t("ui.emailInvalid"),
      // Two rule messages collapse into one rules sentence.
      password: t("auth.passwordTooWeak"),
      password_confirmation: t("auth.passwordMismatch"),
    });
  });

  it("understands the English fallback wording too", () => {
    expect(
      mapApiFieldErrors(
        {
          password: [
            "The password field confirmation does not match.",
            "The password field must be at least 10 characters.",
          ],
        },
        fields,
        options,
      ),
    ).toEqual({
      password: t("auth.passwordTooWeak"),
      password_confirmation: t("auth.passwordMismatch"),
    });
  });

  it("keeps a message it does not recognise, word for word", () => {
    const leaked =
      "Оваа лозинка се појавила во протекување на податоци. Изберете друга лозинка.";

    expect(mapApiFieldErrors({ password: [leaked] }, fields, options)).toEqual(
      { password: leaked },
    );
  });

  it("returns nothing for a missing or malformed bag", () => {
    expect(mapApiFieldErrors(undefined, fields)).toEqual({});
    expect(mapApiFieldErrors({ email: "not a list" }, fields)).toEqual({});
  });
});
