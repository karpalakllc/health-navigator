"use client";

import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { UserAvatar } from "@/components/ui/user-avatar";
import { Button } from "@/components/ui/button";
import type { AuthUser } from "@/lib/api/me";
import { t, tFormat } from "@/i18n/t";

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

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
      <UserAvatar
        name={user.name}
        avatarUrl={user.avatar_url}
        initials={user.avatar_initials}
        size="lg"
      />
      <div className="min-w-0 flex-1 space-y-2">
        <p className="text-sm font-medium text-foreground">
          {t("account.profilePhoto")}
        </p>
        {can_change ? (
          <>
            <p className="text-sm text-muted-foreground">
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
            />
            <Button
              type="button"
              variant="outline"
              className="h-10"
              disabled={pending}
              onClick={() => inputRef.current?.click()}
            >
              {pending
                ? t("account.profilePhotoUploading")
                : t("account.profilePhotoChoose")}
            </Button>
          </>
        ) : (
          <p className="text-sm text-muted-foreground">
            {tFormat("account.profilePhotoLocked", {
              count: message_count,
              required: min_messages,
            })}
          </p>
        )}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
      </div>
    </div>
  );
}
