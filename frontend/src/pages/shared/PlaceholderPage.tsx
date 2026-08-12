import { EmptyState } from '@/components/ui/States';

export function PlaceholderPage({ title, description }: { title: string; description: string }) {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-ink">{title}</h1>
        <p className="text-ink-muted">{description}</p>
      </div>
      <EmptyState
        title="Bu sayfa sonraki batch'te tamamlanacak"
        description="BATCH 1 yalnızca shell, auth ve dashboard iskeletini kapsar."
      />
    </div>
  );
}
