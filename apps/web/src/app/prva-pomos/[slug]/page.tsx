import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { viewerCanPreviewFirstAid } from "@/components/first-aid/access";
import { FirstAidGuideArticle } from "@/components/first-aid/guide-article";
import {
  firstAidBreadcrumbJsonLd,
  firstAidGuideJsonLd,
} from "@/components/first-aid/structured-data";
import { JsonLd } from "@/components/seo/json-ld";
import { FIRST_AID_COPY } from "@/content/first-aid/copy";
import {
  FIRST_AID_BASE,
  getFirstAidGuide,
  isFirstAidGuidePublic,
} from "@/content/first-aid";
import { pageMetadata } from "@/lib/metadata";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const guide = getFirstAidGuide(slug);

  if (!guide) {
    return pageMetadata(FIRST_AID_COPY.title, undefined, { noIndex: true });
  }

  return pageMetadata(guide.title, guide.summary, {
    path: `${FIRST_AID_BASE}/${guide.slug}`,
    ogType: "article",
    // A draft is only ever seen in staff preview; never index it.
    noIndex: !isFirstAidGuidePublic(guide),
  });
}

/**
 * One guide. Public once published and clinician-reviewed; until then a
 * plain 404 for everyone but staff, who get the draft with a preview banner.
 */
export default async function FirstAidGuidePage({ params }: Props) {
  const { slug } = await params;
  const guide = getFirstAidGuide(slug);

  if (!guide) {
    notFound();
  }

  const isPublic = isFirstAidGuidePublic(guide);

  if (!isPublic && !(await viewerCanPreviewFirstAid())) {
    notFound();
  }

  const breadcrumb = isPublic ? firstAidBreadcrumbJsonLd(guide) : null;

  return (
    <>
      {isPublic ? <JsonLd data={firstAidGuideJsonLd(guide)} /> : null}
      {breadcrumb ? <JsonLd data={breadcrumb} /> : null}
      <FirstAidGuideArticle guide={guide} preview={!isPublic} />
    </>
  );
}
