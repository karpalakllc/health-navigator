/**
 * Structured data for search engines.
 *
 * Next's own guidance is a plain inline <script type="application/ld+json">
 * rather than next/script. The `<` escape is mandatory, not decorative: this
 * payload carries admin-entered doctor and facility names, so an unescaped
 * `</script>` in a name would break out of the tag.
 */
export function JsonLd({ data }: { data: Record<string, unknown> }) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{
        __html: JSON.stringify(data).replace(/</g, "\\u003c"),
      }}
    />
  );
}
