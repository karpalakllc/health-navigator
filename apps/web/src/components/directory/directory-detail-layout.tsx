import type { ReactNode } from "react";
import {
  Breadcrumbs,
  type BreadcrumbItem,
} from "@/components/directory/breadcrumbs";
import { BackLink } from "@/components/ui/back-link";

/**
 * Profile template (D2a): a back pill on mobile and a breadcrumb on desktop,
 * then an 8-column main column of cards beside a 4-column sticky contact
 * card. On mobile the contact list comes right after the header (pass it
 * as part of `main`), and `footer` holds the sticky call bar.
 */
export function DirectoryDetailLayout({
  back,
  breadcrumbs,
  main,
  sidebar,
  footer,
}: {
  back: { href: string; label: string };
  breadcrumbs: BreadcrumbItem[];
  main: ReactNode;
  sidebar: ReactNode;
  footer?: ReactNode;
}) {
  return (
    <div className="mx-auto w-full min-w-0 max-w-[1240px] px-5 pb-10 lg:px-6 lg:pb-20">
      <div className="flex pb-3 pt-2 lg:hidden">
        <BackLink href={back.href} label={back.label} />
      </div>
      <Breadcrumbs className="mb-6 hidden pt-8 lg:flex" items={breadcrumbs} />
      <div className="grid min-w-0 items-start gap-3 lg:grid-cols-12 lg:gap-6">
        <div className="flex min-w-0 flex-col gap-3 lg:col-span-8 lg:gap-6">
          {main}
        </div>
        <div className="hidden lg:col-span-4 lg:block lg:self-stretch">
          {sidebar}
        </div>
      </div>
      {footer}
    </div>
  );
}
