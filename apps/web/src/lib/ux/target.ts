/**
 * Describes a clicked element without reading anything a person wrote or read.
 *
 * A target key is `context/element`:
 *  - context: the nearest `data-track` name (set on key components, e.g.
 *    `doctor-card`), else the nearest landmark (`header`, `nav`, `main`,
 *    `footer`, `aside`, `search`, `dialog`, `form`), else `page`;
 *  - element: what kind of thing was clicked — `link`, `button`, `input-…`,
 *    or for something that does nothing, `heading`, `text`, `img`, `icon`, …
 *
 * Only tag names, a fixed list of ARIA roles and input types, and our own
 * data-track attributes are read: never textContent, value, aria-label, alt,
 * title, href, ids or classes. The admin describes keys in words
 * (apps/api/app/Support/Ux/UxTargetDescriber.php).
 */

const TRACK_NAME = /^[a-z][a-z0-9-]{0,47}$/;

const INTERACTIVE_ROLES = new Set([
  "button",
  "link",
  "checkbox",
  "radio",
  "switch",
  "tab",
  "menuitem",
  "menuitemcheckbox",
  "menuitemradio",
  "option",
  "combobox",
  "slider",
  "spinbutton",
  "textbox",
  "searchbox",
  "treeitem",
]);

const INPUT_TYPES = new Set([
  "text",
  "search",
  "email",
  "password",
  "tel",
  "url",
  "number",
  "checkbox",
  "radio",
  "range",
  "date",
  "time",
  "file",
  "submit",
  "button",
  "reset",
]);

const LANDMARK_TAGS: Record<string, string> = {
  HEADER: "header",
  NAV: "nav",
  MAIN: "main",
  FOOTER: "footer",
  ASIDE: "aside",
  DIALOG: "dialog",
  FORM: "form",
  SEARCH: "search",
};

const LANDMARK_ROLES: Record<string, string> = {
  banner: "header",
  navigation: "nav",
  main: "main",
  contentinfo: "footer",
  complementary: "aside",
  search: "search",
  dialog: "dialog",
  alertdialog: "dialog",
  form: "form",
};

/** The element a click event landed on (text nodes resolve to their parent). */
export function eventElement(target: EventTarget | null): Element | null {
  if (!target || typeof (target as Node).nodeType !== "number") return null;
  const node = target as Node;
  return node.nodeType === 1 ? (node as Element) : node.parentElement;
}

function role(el: Element): string | null {
  const value = el.getAttribute("role");
  return value ? value.trim().toLowerCase() : null;
}

function isInteractiveSelf(el: Element): string | null {
  const tag = el.tagName;

  if (tag === "A" && el.hasAttribute("href")) return "link";
  if (tag === "BUTTON") return "button";
  if (tag === "SELECT") return "select";
  if (tag === "TEXTAREA") return "textarea";
  if (tag === "SUMMARY") return "summary";
  if (tag === "INPUT") {
    const type = (el.getAttribute("type") ?? "text").toLowerCase();
    return `input-${INPUT_TYPES.has(type) ? type : "other"}`;
  }
  // A label works only when it is tied to a control.
  if (tag === "LABEL" && (el as HTMLLabelElement).control) return "label";

  const r = role(el);
  if (r && INTERACTIVE_ROLES.has(r)) {
    return r === "button" || r === "link" ? r : `role-${r}`;
  }

  if (el.getAttribute("contenteditable") === "true") return "role-textbox";

  const tabindex = el.getAttribute("tabindex");
  if (tabindex !== null && Number(tabindex) >= 0) {
    return "focusable";
  }

  return null;
}

/**
 * The nearest interactive element at or above `el` and its kind, or null for
 * a dead click. `pointerCursor` lets the caller treat a `cursor: pointer`
 * element (a click handler without semantics) as interactive.
 */
export function findInteractive(
  el: Element,
  pointerCursor?: (el: Element) => boolean,
): { element: Element; kind: string } | null {
  let pointerHit: { element: Element; kind: string } | null = null;
  let depth = 0;

  for (
    let node: Element | null = el;
    node && node !== node.ownerDocument.documentElement;
    node = node.parentElement
  ) {
    // A disabled control does nothing: a click there is dead.
    if (node.hasAttribute("disabled")) return null;

    const kind = isInteractiveSelf(node);
    if (kind) return { element: node, kind };

    // Only the first few levels: a pointer cursor far up the tree is usually
    // a whole card, which the semantic check above already covers.
    if (!pointerHit && depth < 3 && pointerCursor?.(node)) {
      pointerHit = { element: node, kind: "pointer" };
    }
    depth += 1;
  }

  return pointerHit;
}

function context(el: Element): string {
  for (let node: Element | null = el; node; node = node.parentElement) {
    const track = node.getAttribute("data-track");
    if (track && TRACK_NAME.test(track)) return track;

    const r = role(node);
    if (r && LANDMARK_ROLES[r]) return LANDMARK_ROLES[r];
    if (LANDMARK_TAGS[node.tagName]) return LANDMARK_TAGS[node.tagName];
  }
  return "page";
}

function passiveKind(el: Element): string {
  const tag = el.tagName;

  if (/^H[1-6]$/.test(tag) || role(el) === "heading") return "heading";
  if (tag === "IMG" || tag === "PICTURE" || role(el) === "img") return "img";
  if (el.closest("svg")) return "icon";
  if (["VIDEO", "AUDIO", "IFRAME", "CANVAS"].includes(tag)) return "media";
  if (["TABLE", "THEAD", "TBODY", "TR", "TD", "TH"].includes(tag)) {
    return "table";
  }
  if (
    [
      "P",
      "SPAN",
      "STRONG",
      "EM",
      "B",
      "I",
      "SMALL",
      "LI",
      "DT",
      "DD",
      "TIME",
      "BLOCKQUOTE",
      "MARK",
      "ABBR",
      "CODE",
    ].includes(tag)
  ) {
    return "text";
  }
  return "area";
}

export type UxTarget = { key: string; interactive: boolean };

/** The target key of a click and whether it landed on something that works. */
export function describeTarget(
  el: Element,
  pointerCursor?: (el: Element) => boolean,
): UxTarget {
  const interactive = findInteractive(el, pointerCursor);
  const disabled = !interactive && el.closest("[disabled]") !== null;
  const element = interactive
    ? interactive.kind
    : disabled
      ? "disabled"
      : passiveKind(el);
  const anchor = interactive ? interactive.element : el;

  return {
    key: `${context(anchor)}/${element}`,
    interactive: interactive !== null,
  };
}

/** Inside a fixed or sticky box (header, tab bar): no stable page position. */
export function inFixedBox(
  el: Element,
  positionOf: (el: Element) => string,
): boolean {
  for (let node: Element | null = el; node; node = node.parentElement) {
    if (node === node.ownerDocument.body) return false;
    const position = positionOf(node);
    if (position === "fixed" || position === "sticky") return true;
  }
  return false;
}
