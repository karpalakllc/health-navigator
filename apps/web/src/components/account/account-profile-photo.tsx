"use client";

import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { UserAvatar } from "@/components/ui/user-avatar";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import type { AuthUser } from "@/lib/api/me";
import { t, tFormat } from "@/i18n/t";
import { FormError } from "@/components/ui/form-message";

export function AccountProfilePhoto({ user }: { user: AuthUser }) {
  const router = useRouter();
  const inputRef = useRef<HTMLInputElement>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { can_change, min_messages, message_count } = user.profile_avatar;

  async function onFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];

    if (!file) {
      return;
    }

    setPending(true);
    setError(null);

    try {
      const body = new FormData();
      body.append("avatar", file);

      const response = await fetch("/api/session/avatar", {
        method: "POST",
        body,
      });

      if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as {
          message?: string;
        } | null;
        setError(payload?.message ?? t("account.profilePhotoUploadError"));
        return;
      }

      router.refresh();
    } catch {
      setError(t("account.profilePhotoUploadError"));
    } finally {
      setPending(false);
      if (inputRef.current) {
        inputRef.current.value = "";
      }
    }
  }

  // Eligibility as a meter: the text says it all, the bar only shows it.
  const progress =
    min_messages > 0 ? Math.min(1, message_count / min_messages) : 1;

  return (
    <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
      <UserAvatar
        name={user.name}
        avatarUrl={user.avatar_url}
        initials={user.avatar_initials}
        size="lg"
      />
      <div className="flex min-w-0 flex-1 flex-col gap-3">
        {can_change ? (
          <>
            <p className="type-body text-ink-2">
              {t("account.profilePhotoUnlocked")}
            </p>
            <input
              ref={inputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              className="sr-only"
              id="profile-photo-input"
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
                ? t("account.profilePhotoUploading")
                : t("account.profilePhotoChoose")}
            </Button>
          </>
        ) : (
          <>
            <p className="flex items-start gap-2 type-body text-ink">
              <Icon name="lock" size={20} className="mt-0.5" />
              <span>
                {tFormat("account.profilePhotoLocked", {
                  count: message_count,
                  required: min_messages,
                })}
              </span>
            </p>
            <div className="flex flex-col gap-2" aria-hidden>
              <div className="h-2 overflow-hidden rounded-pill bg-sand">
                <div
                  className="h-full rounded-pill bg-ink"
                  style={{ width: `${Math.round(progress * 100)}%` }}
                />
              </div>
              <p className="type-meta text-ink-2">
                {tFormat("account.profilePhotoProgress", {
                  count: message_count,
                  required: min_messages,
                })}
              </p>
            </div>
          </>
        )}
        {error ? <FormError>{error}</FormError> : null}
      </div>
    </div>
  );
}
