# Toggletip copy for „Истакнат“ and „Спонзорирано“

For the WP2 toggletip on the two directory badges. Use the sentences
verbatim. They match the terms of use (`/terms#istaknato`) and
`docs/legal/research-memo.md` §5 (health-advertising and consumer law). This is
a draft until a Macedonian lawyer has reviewed it.

## „Истакнат“

> Профилот го избра тимот на Zdravje360 без никакво плаќање и затоа е прикажан прв; тоа не е оцена за квалитетот на лекувањето.

## „Спонзорирано“

> Овој лекар плаќа за соработка со Zdravje360; плаќањето не влијае на рецензиите, оценката ниту на редоследот во листите и пребарувањето.

## Why these words (for implementers)

- **Say who chose the entry and whether anyone paid.** Under consumer law
  (Закон за заштита на потрошувачите, Сл. весник 236/2022, чл. 71(1)
  т. 11–12), undisclosed paid placement and paid higher ranking always count
  as misleading. Featured entries come first in every list and search, so the
  featured sentence also says why the entry is first (ranking transparency,
  чл. 75(5)).
- **Make no claim about quality.** Закон за здравствената заштита чл. 277
  forbids misleading and comparative advertising of health services. The
  Лекарска комора code (чл. 18, 85, 87) forbids doctors' self-promotion. Never
  write „најдобар“, „препорачан“ or „проверен квалитет“, and never tie a badge
  to ratings.
- **Replace the current `doctors.sponsoredHoverExplanation` string** with the
  sentence above. It says sponsorship is offered only to well-rated profiles
  and withdrawn when reviews are weak. That links payment to reviews and
  quality, the opposite of the neutrality the memo recommends. It also mixes
  up „Спонзорирано“ and „истакнат“.
- **Keep the claims true in code.** Today `is_sponsored` changes no ordering.
  Only `is_featured` does: `DoctorController` and `UnifiedSearch` sort
  featured entries first. If sponsorship ever affects order or prominence,
  change this sentence and the terms first.
- The text uses only Macedonian Cyrillic (no й щ ъ ы ь э ю я ё).
