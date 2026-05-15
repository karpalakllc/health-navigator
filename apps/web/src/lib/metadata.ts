import type { Metadata } from "next";
import { mk } from "@/i18n/mk";

export function pageMetadata(
  title: string,
  description?: string,
): Metadata {
  return {
    title: `${title} · ${mk.meta.title}`,
    description: description ?? mk.meta.description,
  };
}
