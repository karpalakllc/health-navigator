import { parsePostBody } from "@/lib/forum/post-body";

/** A post's plain text, with „>“ lines shown as a quote block. */
export function ForumPostBody({ body }: { body: string }) {
  return (
    <div className="measure flex flex-col gap-3 break-words type-reading text-ink">
      {parsePostBody(body).map((block, index) =>
        block.kind === "quote" ? (
          <blockquote
            key={index}
            className="whitespace-pre-wrap rounded-r-card border-l-4 border-line-strong bg-cream py-2 pr-3 pl-4 text-ink-2"
          >
            {block.text}
          </blockquote>
        ) : (
          <p key={index} className="whitespace-pre-wrap">
            {block.text}
          </p>
        ),
      )}
    </div>
  );
}
