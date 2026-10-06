"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { OptionChecklist } from "@/components/doctor-dashboard/option-checklist";
import { Button } from "@/components/ui/button";
import { Checkbox, Fieldset, Input, Textarea } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import type {
  DoctorDashboard,
  ManagedDoctor,
} from "@/lib/api/doctor-dashboard-types";
import { t, tFormat } from "@/i18n/t";

type PracticeErrors = Partial<Record<string, string[]>>;

const BIO_MAX_LENGTH = 5000;

/**
 * „Податоци за ординацијата“: what the doctor changes without review — about
 * text, contact, fee note, new patients, hours, languages and services. Saved
 * in one PATCH; the public profile shows it at once.
 */
export function DoctorPracticeForm({
  doctor,
  options,
}: {
  doctor: ManagedDoctor;
  options: DoctorDashboard["options"];
}) {
  const router = useRouter();
  const [bio, setBio] = useState(doctor.bio ?? "");
  const [phone, setPhone] = useState(doctor.phone ?? "");
  const [email, setEmail] = useState(doctor.email ?? "");
  const [fee, setFee] = useState(doctor.consultation_fee_note ?? "");
  const [accepts, setAccepts] = useState(doctor.accepts_new_patients);
  const [hours, setHours] = useState<Record<string, string>>(
    doctor.office_hours ?? {},
  );
  const [languages, setLanguages] = useState(doctor.language_ids);
  const [interests, setInterests] = useState(doctor.clinical_interest_ids);
  const [procedures, setProcedures] = useState(doctor.procedure_ids);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<PracticeErrors>({});
  const [saved, setSaved] = useState(false);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setFieldErrors({});
    setSaved(false);
    setPending(true);

    const officeHours = Object.fromEntries(
      Object.entries(hours).filter(([, value]) => value.trim() !== ""),
    );

    try {
      const response = await fetch("/api/doctor-dashboard/profile", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          bio: bio.trim() === "" ? null : bio,
          phone: phone.trim() === "" ? null : phone,
          email: email.trim() === "" ? null : email.trim(),
          consultation_fee_note: fee.trim() === "" ? null : fee,
          accepts_new_patients: accepts,
          office_hours: officeHours,
          language_ids: languages,
          clinical_interest_ids: interests,
          procedure_ids: procedures,
        }),
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: PracticeErrors;
      } | null;

      if (!response.ok) {
        setFieldErrors(payload?.errors ?? {});
        setError(payload?.message ?? t("doctorDashboard.practiceError"));

        return;
      }

      setSaved(true);
      router.refresh();
    } catch {
      setError(t("doctorDashboard.practiceError"));
    } finally {
      setPending(false);
    }
  }

  const fieldError = (key: string) => fieldErrors[key]?.[0];

  return (
    <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-6">
      <Textarea
        label={t("doctorDashboard.bio")}
        hint={t("doctorDashboard.bioHint")}
        name="bio"
        rows={6}
        maxLength={BIO_MAX_LENGTH}
        value={bio}
        error={fieldError("bio")}
        onChange={(event) => setBio(event.target.value)}
      />
      <div className="grid gap-5 sm:grid-cols-2">
        <Input
          label={t("doctorDashboard.phone")}
          type="tel"
          name="phone"
          autoComplete="tel"
          maxLength={50}
          value={phone}
          error={fieldError("phone")}
          onChange={(event) => setPhone(event.target.value)}
        />
        <Input
          label={t("doctorDashboard.email")}
          type="email"
          name="email"
          autoComplete="email"
          maxLength={255}
          value={email}
          error={fieldError("email")}
          onChange={(event) => setEmail(event.target.value)}
        />
        <Input
          label={t("doctorDashboard.feeNote")}
          hint={t("doctorDashboard.feeNoteHint")}
          name="consultation_fee_note"
          maxLength={255}
          value={fee}
          error={fieldError("consultation_fee_note")}
          onChange={(event) => setFee(event.target.value)}
          className="sm:col-span-2"
        />
      </div>
      <Checkbox
        name="accepts_new_patients"
        label={t("doctorDashboard.acceptsNewPatients")}
        checked={accepts}
        onChange={(event) => setAccepts(event.target.checked)}
      />
      <Fieldset
        legend={t("doctorDashboard.officeHours")}
        hint={t("doctorDashboard.officeHoursHint")}
        error={fieldError("office_hours")}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          {options.days.map((day) => (
            <Input
              key={day}
              label={day}
              aria-label={tFormat("doctorDashboard.dayHours", { day })}
              name={`office_hours.${day}`}
              placeholder="08:00–14:00"
              maxLength={100}
              value={hours[day] ?? ""}
              onChange={(event) =>
                setHours((current) => ({
                  ...current,
                  [day]: event.target.value,
                }))
              }
            />
          ))}
        </div>
      </Fieldset>
      <OptionChecklist
        legend={t("doctorDashboard.languages")}
        options={options.languages}
        selected={languages}
        onChange={setLanguages}
      />
      <OptionChecklist
        legend={t("doctorDashboard.clinicalInterests")}
        options={options.clinical_interests}
        selected={interests}
        onChange={setInterests}
      />
      <OptionChecklist
        legend={t("doctorDashboard.procedures")}
        options={options.procedures}
        selected={procedures}
        onChange={setProcedures}
      />
      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>
        {saved ? t("doctorDashboard.practiceSaved") : null}
      </FormSuccess>
      <Button
        type="submit"
        loading={pending}
        disabled={pending}
        className="w-full sm:w-auto sm:self-start"
      >
        {pending
          ? t("doctorDashboard.savingPractice")
          : t("doctorDashboard.savePractice")}
      </Button>
    </form>
  );
}
