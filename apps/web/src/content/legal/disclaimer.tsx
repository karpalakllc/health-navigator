/** DRAFT — requires legal review before production promotion. */

import { LegalHeading, type LegalSection } from "@/components/legal/legal-page";
import { Notice } from "@/components/ui/notice";

export const disclaimerLastUpdated = "2026-05-15";

/** The h2s, in order (a table of contents, if rendered with LegalPage). */
export const disclaimerSections = [
  { id: "ne-e-sovet", title: "Не е медицински совет" },
  { id: "itna-pomos", title: "Итна помош" },
  { id: "nasoki", title: "Насоки за симптоми" },
  { id: "recenzii", title: "Рецензии и форум" },
  { id: "ceni", title: "Аптеки и цени" },
] as const satisfies readonly LegalSection[];

const [notAdvice, emergency, guidance, reviews, prices] = disclaimerSections;

export function DisclaimerContent() {
  return (
    <>
      <Notice tone="info">Нацрт за правен преглед.</Notice>
      <p>
        Zdravje360 обезбедува општи здравствени информации за Северна
        Македонија. Оваа страница ја опишува границата на она што платформата
        прави — и што не прави.
      </p>
      <LegalHeading section={notAdvice} />
      <p>
        Ништо на оваа страница не е замена за преглед, дијагноза или третман од
        лиценциран здравствен работник. Не користете ја платформата за итни
        одлуки.
      </p>
      <LegalHeading section={emergency} />
      <p>
        При медицинска итност повикајте <strong>194</strong> или{" "}
        <strong>112</strong> веднаш. Насоките за симптоми не испраќаат итна
        помош и не треба да ја одложуваат.
      </p>
      <LegalHeading section={guidance} />
      <p>
        Алатката за насоки дава општи информации врз основа на вашите избори. Не
        дијагностицира состојби и не препишува терапии. Исходите се
        информативни.
      </p>
      <LegalHeading section={reviews} />
      <p>
        Мислењата на корисниците се лични и модерирани, но не се верификувани од
        професионалци.
      </p>
      <LegalHeading section={prices} />
      <p>
        Прикажаните цени се референтни административни податоци, не понуди за
        купување на оваа страница.
      </p>
    </>
  );
}
