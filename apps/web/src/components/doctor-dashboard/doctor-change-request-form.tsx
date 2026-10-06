"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { useChangeRequestAnnounce } from "@/components/doctor-dashboard/change-request-area";
import { FacilityPicker } from "@/components/doctor-dashboard/facility-picker";
import { OptionChecklist } from "@/components/doctor-dashboard/option-checklist";
import { Button } from "@/components/ui/button";
import { Input, Select, Textarea } from "@/components/ui/field";
import { FormError } from "@/components/ui/form-message";
import type {
  DoctorDashboard,
  DoctorLink,
  ManagedDoctor,
} from "@/lib/api/doctor-dashboard-types";
import { t } from "@/i18n/t";

type RequestErrors = Partial<Record<string, string[]>>;

function primaryOf(links: DoctorLink[]): number | null {
  return links.find((link) => link.is_primary)?.id ?? null;
}

/**
 * „Податоци што ги проверува тимот“: name, title, qualifications, city,
 * specialties and workplaces. Submitting sends everything; the API keeps
 * only what differs, as a change request staff approve or reject. The
 * profile keeps the current values meanwhile.
 */
export function DoctorChangeRequestForm({
  doctor,
  options,
}: {
  doctor: ManagedDoctor;
  options: DoctorDashboard["options"];
}) {
  const router = useRouter();
  const announce = useChangeRequestAnnounce();
  const [fullName, setFullName] = useState(doctor.full_name);
  const [title, setTitle] = useState(doctor.title ?? "");
  const [subspecialty, setSubspecialty] = useState(doctor.subspecialty ?? "");
  const [education, setEducation] = useState(doctor.education ?? "");
  const [years, setYears] = useState(
    doctor.years_experience === null ? "" : String(doctor.years_experience),
  );
  const [city, setCity] = useState(doctor.city ?? "");
  const [specialties, setSpecialties] = useState(
    doctor.specialties.map((link) => link.id),
  );
  const [primarySpecialty, setPrimarySpecialty] = useState(
    primaryOf(doctor.specialties),
  );
  const [facilities, setFacilities] = useState(
    doctor.facilities.map((link) => link.id),
  );
  const [primaryFacility, setPrimaryFacility] = useState(
    primaryOf(doctor.facilities),
  );
  const [facilityNames, setFacilityNames] = useState(options.facilities);
  const [message, setMessage] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<RequestErrors>({});

  const nameOf = (list: { id: number; name: string }[], id: number) =>
    list.find((option) => option.id === id)?.name ?? String(id);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setFieldErrors({});
    setPending(true);

    try {
      const response = await fetch("/api/doctor-dashboard/change-requests", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          full_name: fullName,
          title: title.trim() === "" ? null : title,
          subspecialty: subspecialty.trim() === "" ? null : subspecialty,
          education: education.trim() === "" ? null : education,
          years_experience: years.trim() === "" ? null : Number(years),
          city: city.trim() === "" ? null : city,
          specialty_ids: specialties,
          primary_specialty_id:
            primarySpecialty !== null && specialties.includes(primarySpecialty)
              ? primarySpecialty
              : null,
          facility_ids: facilities,
          primary_facility_id:
            primaryFacility !== null && facilities.includes(primaryFacility)
              ? primaryFacility
              : null,
          message: message.trim() === "" ? null : message,
        }),
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        code?: string;
        errors?: RequestErrors;
      } | null;

      if (!response.ok) {
        setFieldErrors(payload?.errors ?? {});
        setError(
          payload?.code === "doctor_account.no_changes"
            ? t("doctorDashboard.noChanges")
            : (payload?.message ?? t("doctorDashboard.requestError")),
        );

        return;
      }

      setMessage("");
      // The refresh replaces this form with the pending request; the
      // surrounding ChangeRequestArea keeps the confirmation and focus.
      announce(t("doctorDashboard.requestSent"));
      router.refresh();
    } catch {
      setError(t("doctorDashboard.requestError"));
    } finally {
      setPending(false);
    }
  }

  const fieldError = (key: string) => fieldErrors[key]?.[0];

  return (
    <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-6">
      <div className="grid gap-5 sm:grid-cols-2">
        <Input
          label={t("doctorDashboard.fullName")}
          name="full_name"
          required
          maxLength={255}
          value={fullName}
          error={fieldError("full_name")}
          onChange={(event) => setFullName(event.target.value)}
          className="sm:col-span-2"
        />
        <Input
          label={t("doctorDashboard.title")}
          name="title"
          maxLength={255}
          value={title}
          error={fieldError("title")}
          onChange={(event) => setTitle(event.target.value)}
        />
        <Input
          label={t("doctorDashboard.subspecialty")}
          name="subspecialty"
          maxLength={255}
          value={subspecialty}
          error={fieldError("subspecialty")}
          onChange={(event) => setSubspecialty(event.target.value)}
        />
        <Input
          label={t("doctorDashboard.yearsExperience")}
          name="years_experience"
          type="number"
          inputMode="numeric"
          min={0}
          max={70}
          value={years}
          error={fieldError("years_experience")}
          onChange={(event) => setYears(event.target.value)}
        />
        <Input
          label={t("doctorDashboard.city")}
          name="city"
          maxLength={255}
          value={city}
          error={fieldError("city")}
          onChange={(event) => setCity(event.target.value)}
        />
      </div>
      <Textarea
        label={t("doctorDashboard.education")}
        name="education"
        rows={3}
        maxLength={2000}
        value={education}
        error={fieldError("education")}
        onChange={(event) => setEducation(event.target.value)}
      />
      <OptionChecklist
        legend={t("doctorDashboard.specialties")}
        options={options.specialties}
        selected={specialties}
        onChange={setSpecialties}
      />
      {specialties.length > 1 ? (
        <Select
          label={t("doctorDashboard.primarySpecialty")}
          name="primary_specialty_id"
          value={primarySpecialty ?? ""}
          onChange={(event) =>
            setPrimarySpecialty(
              event.target.value === "" ? null : Number(event.target.value),
            )
          }
        >
          <option value="">{t("doctorDashboard.noPrimary")}</option>
          {specialties.map((id) => (
            <option key={id} value={id}>
              {nameOf(options.specialties, id)}
            </option>
          ))}
        </Select>
      ) : null}
      <FacilityPicker
        initial={options.facilities}
        selected={facilities}
        onChange={setFacilities}
        onNamesChange={setFacilityNames}
      />
      {facilities.length > 1 ? (
        <Select
          label={t("doctorDashboard.primaryFacility")}
          name="primary_facility_id"
          value={primaryFacility ?? ""}
          onChange={(event) =>
            setPrimaryFacility(
              event.target.value === "" ? null : Number(event.target.value),
            )
          }
        >
          <option value="">{t("doctorDashboard.noPrimary")}</option>
          {facilities.map((id) => (
            <option key={id} value={id}>
              {nameOf(facilityNames, id)}
            </option>
          ))}
        </Select>
      ) : null}
      <Textarea
        label={t("doctorDashboard.requestMessage")}
        name="message"
        rows={3}
        maxLength={1000}
        value={message}
        error={fieldError("message")}
        onChange={(event) => setMessage(event.target.value)}
      />
      {error ? <FormError>{error}</FormError> : null}
      <Button
        type="submit"
        variant="secondary"
        loading={pending}
        disabled={pending}
        className="w-full sm:w-auto sm:self-start"
      >
        {pending
          ? t("doctorDashboard.submittingRequest")
          : t("doctorDashboard.submitRequest")}
      </Button>
    </form>
  );
}
