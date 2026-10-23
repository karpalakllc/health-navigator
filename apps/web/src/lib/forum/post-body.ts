/*
 * Forum bodies are plain text. The one structure they carry is a quote:
 * lines starting with „>“ (what „Цитирај“ inserts) are shown as a quote
 * block. Nothing is parsed as HTML or Markdown.
 */

export type PostBodyBlock =
  { kind: "text"; text: string } | { kind: "quote"; text: string };

const QUOTE_LINE = /^\s*>\s?/;

export function parsePostBody(body: string): PostBodyBlock[] {
  const blocks: PostBodyBlock[] = [];

  for (const line of body.replace(/\r\n?/g, "\n").split("\n")) {
    const isQuote = QUOTE_LINE.test(line);
    const text = isQuote ? line.replace(QUOTE_LINE, "") : line;
    const kind = isQuote ? "quote" : "text";
    const last = blocks[blocks.length - 1];

    if (last && last.kind === kind) {
      last.text += `\n${text}`;
    } else {
      blocks.push({ kind, text });
    }
  }

  return blocks
    .map((block) =>
      block.kind === "text"
        ? // Blank lines around a quote are spacing, not text.
          { ...block, text: block.text.replace(/^\n+|\n+$/g, "") }
        : block,
    )
    .filter((block) => block.text.trim() !== "");
}

/** Longest stretch of a post a quote carries. */
export const QUOTE_MAX_LENGTH = 600;

/**
 * The text „Цитирај“ inserts: „Име напиша:“ and the post as „> “ lines,
 * without the post's own quotes (no quote pyramids), cut at a word.
 */
export function quoteText(intro: string, body: string): string {
  const own = parsePostBody(body)
    .filter((block) => block.kind === "text")
    .map((block) => block.text)
    .join("\n\n")
    .trim();
  let excerpt = own;

  if (excerpt.length > QUOTE_MAX_LENGTH) {
    const cut = excerpt.slice(0, QUOTE_MAX_LENGTH);
    const space = cut.lastIndexOf(" ");
    excerpt = `${(space > QUOTE_MAX_LENGTH / 2 ? cut.slice(0, space) : cut).trimEnd()} …`;
  }

  const lines = [intro, ...excerpt.split("\n")].map((line) =>
    line.trim() === "" ? ">" : `> ${line}`,
  );

  return `${lines.join("\n")}\n\n`;
}
