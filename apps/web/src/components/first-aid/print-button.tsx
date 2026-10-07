"use client";

import { Icon } from "@/components/ui/icons";
import { FIRST_AID_COPY } from "@/content/first-aid/copy";

/** The page's only script of its own: window.print(). Hidden when printed. */
export function PrintButton() {
  return (
    <button
      type="button"
      onClick={() => window.print()}
      className="btn btn-secondary btn-md self-start print:hidden"
    >
      <Icon name="file-text" size={20} />
      <span>{FIRST_AID_COPY.print}</span>
    </button>
  );
}
