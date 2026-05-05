import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { InquiryForm } from '@/components/inquiry-form';
import { InquiryTimeline } from '@/components/inquiry-timeline';
import { StatusPill } from '@/components/status-pill';

type Photo = { id: number; thumbnail_path: string | null; path: string };
type Item = {
    id: number;
    title: string;
    description: string | null;
    status: 'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn';
    asking_price_cents: number;
    kijiji_url: string | null;
    photos: Photo[];
};
type ListingDraft = { title: string; description: string };
type Buyer = { id: number; display_name: string };
type ReplyTemplate = { label: string; body: string };
type Inquiry = Parameters<typeof InquiryTimeline>[0]['inquiries'][number];

const COPIED_FEEDBACK_MS = 1500;

function CopyButton({ text, label }: { text: string; label: string }) {
    const [state, setState] = useState<'idle' | 'copied' | 'error'>('idle');
    return (
        <button
            type="button"
            onClick={async () => {
                try {
                    if (!navigator.clipboard) {
                        throw new Error('Clipboard API unavailable');
                    }
                    await navigator.clipboard.writeText(text);
                    setState('copied');
                } catch {
                    setState('error');
                }
                setTimeout(() => setState('idle'), COPIED_FEEDBACK_MS);
            }}
            aria-live="polite"
            className="text-xs border rounded px-2 py-1 hover:bg-zinc-50"
        >
            {state === 'copied' ? 'Copied!' : state === 'error' ? 'Copy failed' : `Copy ${label}`}
        </button>
    );
}

function SchedulePickupForm({
    itemId,
    buyers,
    askingPriceCents,
}: {
    itemId: number;
    buyers: Buyer[];
    askingPriceCents: number;
}) {
    const form = useForm<{
        buyer_id: number | null;
        notes: string;
        agreed_price_cents: number | '';
    }>({
        buyer_id: buyers[0]?.id ?? null,
        notes: '',
        agreed_price_cents: askingPriceCents,
    });

    if (buyers.length === 0) {
        return (
            <div className="border rounded-lg p-4 text-sm text-zinc-600">
                Add a buyer first to schedule a pickup.
            </div>
        );
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const price = form.data.agreed_price_cents === '' ? 0 : Number(form.data.agreed_price_cents);
        router.post(
            '/pickups',
            {
                buyer_id: form.data.buyer_id,
                notes: form.data.notes || null,
                items: [{ item_id: itemId, agreed_price_cents: price }],
            },
            { preserveScroll: true },
        );
    };

    return (
        <form onSubmit={submit} className="border rounded-lg p-4 space-y-3">
            <h2 className="font-medium">Schedule pickup</h2>
            <select
                value={form.data.buyer_id ?? ''}
                onChange={(e) => form.setData('buyer_id', Number(e.target.value))}
                className="w-full border rounded px-2 py-1 text-sm"
            >
                {buyers.map((b) => (
                    <option key={b.id} value={b.id}>{b.display_name}</option>
                ))}
            </select>
            <input
                type="number"
                value={form.data.agreed_price_cents}
                onChange={(e) =>
                    form.setData(
                        'agreed_price_cents',
                        e.target.value === '' ? '' : Number(e.target.value),
                    )
                }
                placeholder="Agreed price (cents)"
                className="w-full border rounded px-2 py-1 text-sm"
            />
            <textarea
                value={form.data.notes}
                onChange={(e) => form.setData('notes', e.target.value)}
                placeholder="When/where (e.g. Saturday around 2pm, front porch)"
                rows={2}
                className="w-full border rounded px-2 py-1 text-sm"
            />
            <button type="submit" className="bg-black text-white px-3 py-1.5 rounded text-sm">
                Schedule
            </button>
        </form>
    );
}

function TransitionControls({ item }: { item: Item }) {
    const [kijijiUrl, setKijijiUrl] = useState('');
    const [error, setError] = useState<string | null>(null);

    const post = (data: Record<string, string>) => {
        setError(null);
        router.post(`/items/${item.id}/transition`, data, {
            preserveScroll: true,
            onError: (errs) => {
                setError(errs.kijiji_url ?? errs.to ?? 'Could not update status.');
            },
        });
    };

    if (item.status === 'draft') {
        return (
            <div className="border rounded-lg p-4 space-y-2">
                <h2 className="font-medium">Next step</h2>
                <p className="text-sm text-zinc-600">
                    Once details and photos are filled in, mark this item ready to copy onto Kijiji.
                </p>
                {error && <p className="text-sm text-rose-700">{error}</p>}
                <button
                    type="button"
                    onClick={() => post({ to: 'ready' })}
                    className="bg-black text-white px-4 py-2 rounded text-sm"
                >
                    Mark as ready
                </button>
            </div>
        );
    }

    if (item.status === 'ready') {
        return (
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post({ to: 'listed', kijiji_url: kijijiUrl });
                }}
                className="border rounded-lg p-4 space-y-3"
            >
                <h2 className="font-medium">Mark as published</h2>
                <p className="text-sm text-zinc-600">
                    Paste the live Kijiji URL after posting the listing.
                </p>
                <label htmlFor={`item-${item.id}-kijiji-url`} className="text-sm font-medium block">
                    Kijiji listing URL
                </label>
                <input
                    id={`item-${item.id}-kijiji-url`}
                    type="url"
                    value={kijijiUrl}
                    onChange={(e) => setKijijiUrl(e.target.value)}
                    placeholder="https://www.kijiji.ca/v-..."
                    className="w-full border rounded px-3 py-2 text-sm"
                />
                {error && <p className="text-sm text-rose-700">{error}</p>}
                <button
                    type="submit"
                    disabled={!kijijiUrl.trim()}
                    className="bg-black text-white px-4 py-2 rounded text-sm disabled:opacity-50"
                >
                    Mark as published
                </button>
            </form>
        );
    }

    if (item.status === 'withdrawn') {
        return (
            <div className="border rounded-lg p-4 space-y-2">
                <h2 className="font-medium">Reactivate</h2>
                {error && <p className="text-sm text-rose-700">{error}</p>}
                <button
                    type="button"
                    onClick={() => post({ to: 'draft' })}
                    className="bg-black text-white px-4 py-2 rounded text-sm"
                >
                    Move back to draft
                </button>
            </div>
        );
    }

    // listed, reserved, sold — only "Withdraw" lives here in Phase 2.
    return (
        <div className="border rounded-lg p-4">
            {error && <p className="text-sm text-rose-700">{error}</p>}
            <button
                type="button"
                onClick={() => post({ to: 'withdrawn' })}
                className="text-sm text-rose-700 underline"
            >
                Withdraw this item
            </button>
        </div>
    );
}

export default function ItemsShow({
    item,
    listing_draft,
    inquiries,
    buyers,
    reply_templates,
}: {
    item: Item;
    listing_draft: ListingDraft;
    inquiries: Inquiry[];
    buyers: Buyer[];
    reply_templates: ReplyTemplate[];
}) {
    return (
        <>
            <Head title={item.title} />
            <div className="p-6 max-w-3xl space-y-6">
                <header className="space-y-2">
                    <div className="flex items-start justify-between gap-3">
                        <h1 className="text-2xl font-semibold">{item.title}</h1>
                        <StatusPill status={item.status} />
                    </div>
                    <p className="text-sm text-zinc-600">
                        Asking ${(item.asking_price_cents / 100).toFixed(2)}
                    </p>
                    {item.kijiji_url && (
                        <a
                            className="text-sm text-blue-600 underline"
                            href={item.kijiji_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            Live on Kijiji ↗
                        </a>
                    )}
                </header>

                <section className="border rounded-lg p-4 space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">Listing draft</h2>
                        <Link href={`/items/${item.id}/edit`} className="text-xs underline">
                            Edit details
                        </Link>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs uppercase text-zinc-500">Title</span>
                            <CopyButton text={listing_draft.title} label="title" />
                        </div>
                        <p className="border rounded bg-zinc-50 p-2 text-sm">
                            {listing_draft.title}
                        </p>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs uppercase text-zinc-500">Description</span>
                            <CopyButton text={listing_draft.description} label="description" />
                        </div>
                        <pre className="border rounded bg-zinc-50 p-2 text-sm whitespace-pre-wrap font-sans">
                            {listing_draft.description}
                        </pre>
                    </div>
                </section>

                {item.photos.length > 0 && (
                    <section className="border rounded-lg p-4 space-y-3">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">Photos ({item.photos.length})</h2>
                            {/* Plain <a>, not <Link> — the endpoint streams a binary zip; Inertia would intercept and try to parse JSON. */}
                            <a
                                href={`/items/${item.id}/photos.zip`}
                                download
                                className="text-xs underline"
                            >
                                Download zip
                            </a>
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                            {item.photos.map((p) => (
                                <img
                                    key={p.id}
                                    src={`/storage/${p.thumbnail_path ?? p.path}`}
                                    alt=""
                                    className="w-full h-32 object-cover rounded border"
                                />
                            ))}
                        </div>
                    </section>
                )}

                {item.status === 'listed' && (
                    <SchedulePickupForm itemId={item.id} buyers={buyers} askingPriceCents={item.asking_price_cents} />
                )}
                <TransitionControls item={item} />

                <section className="space-y-3">
                    <h2 className="font-medium">Inquiries</h2>
                    <InquiryForm itemId={item.id} buyers={buyers} />
                    <InquiryTimeline inquiries={inquiries} replyTemplates={reply_templates} />
                </section>

                <Link
                    href={`/items/${item.id}/edit`}
                    className="inline-block bg-black text-white px-4 py-2 rounded text-sm"
                >
                    Edit item
                </Link>
            </div>
        </>
    );
}

ItemsShow.layout = {
    breadcrumbs: [
        { title: 'Items', href: '/items' },
    ],
};
