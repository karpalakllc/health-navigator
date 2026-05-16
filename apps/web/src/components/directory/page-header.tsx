type PageHeaderProps = {
  title: string;
  description?: string;
};

export function PageHeader({ title, description }: PageHeaderProps) {
  return (
    <header className="motion-safe:animate-fade-up space-y-3">
      <div className="space-y-2">
        <h1 className="text-3xl font-semibold tracking-tight text-foreground md:text-4xl">{title}</h1>
        <div className="h-1 w-14 rounded-full bg-gradient-to-r from-primary to-accent motion-safe:animate-accent-bar" />
      </div>
      {description ? <p className="max-w-2xl text-muted-foreground">{description}</p> : null}
    </header>
  );
}
