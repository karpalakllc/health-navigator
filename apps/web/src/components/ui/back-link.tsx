import Link from "next/link";

type BackLinkProps = {
  href: string;
  label: string;
};

export function BackLink({ href, label }: BackLinkProps) {
  return (
    <Link
      href={href}
      className="inline-flex text-sm text-zinc-500 hover:text-zinc-900"
    >
      <span aria-hidden="true">← </span>
      {label}
    </Link>
  );
}
