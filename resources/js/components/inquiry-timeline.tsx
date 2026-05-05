import { router } from '@inertiajs/react';
import { useState } from 'react';

type Buyer = { id: number; display_name: string };
type LogEntry = { note: string; at: string };
type Inquiry = {
    id: number;
    buyer: Buyer;
    message_excerpt: string | null;
    status: 'new' | 'replied' | 'negotiating' | 'ghosted' | 'declined';
    offered_price_cents: number | null;
    received_at: string | null;
    last_contact_at: string | null;
    negotiation_log: LogEntry[] | null;
};
type ReplyTemplate = { label: string; body: string };

const STATUSES: Inquiry['status'][] = ['new', 'replied', 'negotiating', 'ghosted', 'declined'];

function CopyButton({ text, label }: { text: string; label: string }) {
    const [state, setState] = useState<'idle' | 'copied' | 'error'>('idle');

    return (
        <button
            type="button"
            onClick={async () => {
                try {
                    if (!navigator.clipboard) {
                        throw new Error('no clipboard');
                    }

                    await navigator.clipboard.writeText(text);
                    setState('copied');
                } catch {
                    setState('error');
                }

                setTimeout(() => setState('idle'), 1500);
            }}
            className="text-xs border rounded px-2 py-1 hover:bg-zinc-50"
        >
            {state === 'copied' ? 'Copied!' : state === 'error' ? 'Failed' : `Copy ${label}`}
        </button>
    );
}

function InquiryRow({ inquiry, replyTemplates }: { inquiry: Inquiry; replyTemplates: ReplyTemplate[] }) {
    const [note, setNote] = useState('');

    const update = (changes: Record<string, string | number | null>) => {
        router.patch(`/inquiries/${inquiry.id}`, changes, { preserveScroll: true });
    };

    return (
        <li className="border rounded-lg p-3 space-y-2">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-medium text-sm">{inquiry.buyer.display_name}</p>
                    {inquiry.received_at && (
                        <p className="text-xs text-zinc-500">
                            {new Date(inquiry.received_at).toLocaleString()}
                        </p>
                    )}
                </div>
                <select
                    value={inquiry.status}
                    onChange={(e) => update({ status: e.target.value })}
                    className="text-xs border rounded px-2 py-1"
                >
                    {STATUSES.map((s) => (
                        <option key={s} value={s}>{s}</option>
                    ))}
                </select>
            </div>

            {inquiry.message_excerpt && (
                <p className="text-sm bg-zinc-50 border rounded p-2 whitespace-pre-wrap">
                    {inquiry.message_excerpt}
                </p>
            )}

            <div className="flex items-center gap-2 text-xs text-zinc-600">
                <span>Offered:</span>
                <input
                    type="number"
                    defaultValue={inquiry.offered_price_cents ?? ''}
                    placeholder="cents"
                    className="border rounded px-2 py-1 w-24"
                    onBlur={(e) => {
                        const v = e.target.value === '' ? null : Number(e.target.value);
                        update({ offered_price_cents: v });
                    }}
                />
            </div>

            {inquiry.negotiation_log && inquiry.negotiation_log.length > 0 && (
                <ul className="text-xs text-zinc-600 space-y-1">
                    {inquiry.negotiation_log.map((entry, i) => (
                        <li key={i}>
                            <span className="text-zinc-400">{new Date(entry.at).toLocaleString()}</span>
                            {' — '}
                            {entry.note}
                        </li>
                    ))}
                </ul>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();

                    if (!note.trim()) {
                        return;
                    }

                    update({ negotiation_note: note });
                    setNote('');
                }}
                className="flex gap-2"
            >
                <input
                    type="text"
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder="Counter-offer note"
                    className="flex-1 border rounded px-2 py-1 text-xs"
                />
                <button type="submit" className="text-xs border rounded px-2 py-1">Log</button>
            </form>

            {replyTemplates.length > 0 && (
                <div className="flex flex-wrap gap-1 pt-1 border-t">
                    {replyTemplates.map((t) => (
                        <CopyButton key={t.label} text={t.body} label={t.label} />
                    ))}
                </div>
            )}
        </li>
    );
}

export function InquiryTimeline({
    inquiries,
    replyTemplates,
}: {
    inquiries: Inquiry[];
    replyTemplates: ReplyTemplate[];
}) {
    if (inquiries.length === 0) {
        return <p className="text-sm text-zinc-500">No inquiries yet.</p>;
    }

    return (
        <ul className="space-y-2">
            {inquiries.map((inq) => (
                <InquiryRow key={inq.id} inquiry={inq} replyTemplates={replyTemplates} />
            ))}
        </ul>
    );
}
