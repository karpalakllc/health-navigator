/**
 * A small structural check of JSON-LD against the required properties in
 * Google's structured-data docs (docs/seo.md). Not a full schema.org
 * validator — it catches what breaks eligibility: a missing required
 * property, a null/undefined value, an ISO date that is not one.
 */
type Json = Record<string, unknown>;

const ISO_DATE =
  /^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})?)?$/;

function isObject(value: unknown): value is Json {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function nonEmptyString(value: unknown): boolean {
  return typeof value === "string" && value.trim() !== "";
}

function checkPosting(node: Json, path: string, problems: string[]) {
  const author = node.author;
  if (!isObject(author) || !nonEmptyString(author.name)) {
    problems.push(`${path}: author.name is required`);
  }
  if (
    !nonEmptyString(node.datePublished) ||
    !ISO_DATE.test(String(node.datePublished))
  ) {
    problems.push(`${path}: datePublished must be an ISO 8601 date`);
  }
  if (
    node.dateModified !== undefined &&
    !ISO_DATE.test(String(node.dateModified))
  ) {
    problems.push(`${path}: dateModified must be an ISO 8601 date`);
  }
  if (!nonEmptyString(node.text) && !node.image && !node.video) {
    problems.push(`${path}: one of text, image or video is required`);
  }
}

const CHECKS: Record<
  string,
  (node: Json, path: string, problems: string[]) => void
> = {
  DiscussionForumPosting: checkPosting,
  Comment: checkPosting,
  BreadcrumbList(node, path, problems) {
    const items = node.itemListElement;
    if (!Array.isArray(items) || items.length < 2) {
      problems.push(`${path}: at least two ListItems are required`);
      return;
    }
    items.forEach((item: unknown, index) => {
      if (
        !isObject(item) ||
        item.position !== index + 1 ||
        !nonEmptyString(item.name)
      ) {
        problems.push(`${path}[${index}]: position and name are required`);
      }
      if (
        index < items.length - 1 &&
        isObject(item) &&
        !nonEmptyString(item.item)
      ) {
        problems.push(
          `${path}[${index}]: item (URL) is required except on the last`,
        );
      }
    });
  },
  AggregateRating(node, path, problems) {
    if (typeof node.ratingValue !== "number") {
      problems.push(`${path}: ratingValue is required`);
    }
    if (
      typeof node.reviewCount !== "number" &&
      typeof node.ratingCount !== "number"
    ) {
      problems.push(`${path}: reviewCount or ratingCount is required`);
    }
  },
  Physician(node, path, problems) {
    if (!nonEmptyString(node.name)) problems.push(`${path}: name is required`);
  },
  InteractionCounter(node, path, problems) {
    if (
      typeof node.userInteractionCount !== "number" ||
      !nonEmptyString(node.interactionType)
    ) {
      problems.push(
        `${path}: interactionType and userInteractionCount are required`,
      );
    }
  },
};

function walk(value: unknown, path: string, problems: string[]) {
  if (value === null || value === undefined) {
    problems.push(`${path}: null/undefined value`);
    return;
  }
  if (Array.isArray(value)) {
    value.forEach((item, index) => walk(item, `${path}[${index}]`, problems));
    return;
  }
  if (!isObject(value)) {
    return;
  }
  const type = value["@type"];
  if (typeof type === "string" && CHECKS[type]) {
    CHECKS[type](value, `${path}<${type}>`, problems);
  }
  for (const [key, child] of Object.entries(value)) {
    walk(child, `${path}.${key}`, problems);
  }
}

/** Problems found (empty when the payload is sane). */
export function schemaProblems(data: Json): string[] {
  const problems: string[] = [];
  if (data["@context"] !== "https://schema.org") {
    problems.push("@context must be https://schema.org");
  }
  // Must survive the JsonLd component's JSON.stringify unchanged.
  if (
    JSON.stringify(JSON.parse(JSON.stringify(data))) !== JSON.stringify(data)
  ) {
    problems.push("not JSON-serialisable");
  }
  walk(data, "$", problems);
  return problems;
}
