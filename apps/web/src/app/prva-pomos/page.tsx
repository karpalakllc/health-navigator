import type { Metadata } from "next";
import { viewerCanPreviewFirstAid } from "@/components/first-aid/access";
import { FirstAidIndex } from "@/components/first-aid/guide-index";
import { FIRST_AID_COPY } from "@/content/first-aid/copy";
import {
  FIRST_AID_BASE,
  FIRST_AID_GUIDES,
  hasPublishedFirstAidGuides,
  publishedFirstAidGuides,
} from "@/content/first-aid";
import { pageMetadata } from "@/lib/metadata";

export function generateMetadata(): Metadata {
  // Until a guide is published the index is only a holding note: keep it
  // out of search results (it is not in the sitemap either).
  return pageMetadata(FIRST_AID_COPY.title, FIRST_AID_COPY.metaDescription, {
    path: FIRST_AID_BASE,
    noIndex: !hasPublishedFirstAidGuides(),
  });
}

/** „Прва помош“: published guides by situation; staff also see drafts. */
export default async function FirstAidIndexPage() {
  const preview = await viewerCanPreviewFirstAid();

  return (
    <FirstAidIndex
      guides={preview ? FIRST_AID_GUIDES : publishedFirstAidGuides()}
      preview={preview}
    />
  );
}
