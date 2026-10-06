"use client";

import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { Button } from "@/components/ui/button";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { t, tFormat } from "@/i18n/t";

/**
 * The profile photo, changed at once: the file goes through the web tier to
 * the API, which re-encodes it (WebP) like the admin panel's upload.
 */
export function DoctorPhotoUpload({
  name,
  avatarUrl,
}: {
  name: string;
  avatarUrl: string | null;
}) {
  const router = useRouter();
  const inputRef = useRef<HTMLInputElement>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  async function onFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];

    if (!file) {
      return;
    }

    setPending(true);
    setError(null);
    setSaved(false);

    try {
      const body = new FormData();
      body.append("avatar", file);

      const response = await fetch("/api/doctor-dashboard/avatar", {
        method: "POST",
        body,
      });

      if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as {
          message?: string;
        } | null;
        setError(payload?.message ?? t("doctorDashboard.photoError"));

        return;
      }

      setSaved(true);
      router.refresh();
    } catch {
      setError(t("doctorDashboard.photoError"));
    } finally {
      setPending(false);

      if (inputRef.current) {
        inputRef.current.value = "";
      }
    }
  }

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
      <DirectoryAvatar
        kind="doctor"
        avatarUrl={avatarUrl}
        name={name}
        size={80}
        alt={tFormat("doctorDashboard.photoAlt", { name })}
        loading="eager"
      />
      <div className="flex min-w-0 flex-1 flex-col gap-2">
        <p className="type-meta text-ink-2">{t("doctorDashboard.photoHint")}</p>
        <input
          ref={inputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif"
          className="sr-only"
          onChange={onFileChange}
          disabled={pending}
          tabIndex={-1}
          aria-hidden
        />
        <Button
          type="button"
          variant="secondary"
          leadingIcon="camera"
          loading={pending}
          disabled={pending}
          className="w-full sm:w-auto sm:self-start"
          onClick={() => inputRef.current?.click()}
        >
          {pending
            ? t("doctorDashboard.photoUploading")
            : t("doctorDashboard.photoChange")}
        </Button>
        {error ? <FormError>{error}</FormError> : null}
        <FormSuccess>
          {saved ? t("doctorDashboard.photoSaved") : null}
        </FormSuccess>
      </div>
    </div>
  );
}
