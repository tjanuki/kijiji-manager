import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Buyer = { id: number; display_name: string };

export function InquiryForm({ itemId, buyers }: { itemId: number; buyers: Buyer[] }) {
    const [mode, setMode] = useState<'existing' | 'new'>(buyers.length > 0 ? 'existing' : 'new');

    const form = useForm<{
        buyer_id: number | null;
        new_buyer: { display_name: string; phone: string; email: string };
        message_excerpt: string;
        offered_price_cents: number | '';
    }>({
        buyer_id: buyers[0]?.id ?? null,
        new_buyer: { display_name: '', phone: '', email: '' },
        message_excerpt: '',
        offered_price_cents: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...(mode === 'existing'
                ? { buyer_id: form.data.buyer_id }
                : { new_buyer: form.data.new_buyer }),
            message_excerpt: form.data.message_excerpt,
            offered_price_cents: form.data.offered_price_cents === '' ? null : form.data.offered_price_cents,
        };
        form.transform(() => payload);
        form.post(`/items/${itemId}/inquiries`, {
            preserveScroll: true,
            onSuccess: () => form.reset('message_excerpt', 'offered_price_cents'),
        });
    };

    return (
        <form onSubmit={submit} className="border rounded-lg p-4 space-y-3">
            <h3 className="font-medium text-sm">Log an inquiry</h3>

            <div className="flex gap-2 text-xs">
                <button
                    type="button"
                    onClick={() => setMode('existing')}
                    disabled={buyers.length === 0}
                    className={`border rounded px-2 py-1 ${mode === 'existing' ? 'bg-black text-white' : ''}`}
                >
                    Existing buyer
                </button>
                <button
                    type="button"
                    onClick={() => setMode('new')}
                    className={`border rounded px-2 py-1 ${mode === 'new' ? 'bg-black text-white' : ''}`}
                >
                    New buyer
                </button>
            </div>

            {mode === 'existing' ? (
                <div>
                    <select
                        value={form.data.buyer_id ?? ''}
                        onChange={(e) => form.setData('buyer_id', Number(e.target.value))}
                        className="w-full border rounded px-2 py-1 text-sm"
                    >
                        {buyers.map((b) => (
                            <option key={b.id} value={b.id}>{b.display_name}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.buyer_id} className="mt-1" />
                </div>
            ) : (
                <div className="space-y-2">
                    <div>
                        <input
                            type="text"
                            placeholder="Display name"
                            value={form.data.new_buyer.display_name}
                            onChange={(e) =>
                                form.setData('new_buyer', { ...form.data.new_buyer, display_name: e.target.value })
                            }
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors['new_buyer.display_name']} className="mt-1" />
                    </div>
                    <div>
                        <input
                            type="text"
                            placeholder="Phone (optional)"
                            value={form.data.new_buyer.phone}
                            onChange={(e) =>
                                form.setData('new_buyer', { ...form.data.new_buyer, phone: e.target.value })
                            }
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors['new_buyer.phone']} className="mt-1" />
                    </div>
                </div>
            )}

            <div>
                <textarea
                    value={form.data.message_excerpt}
                    onChange={(e) => form.setData('message_excerpt', e.target.value)}
                    placeholder="Paste their message"
                    rows={3}
                    className="w-full border rounded px-2 py-1 text-sm"
                />
                <InputError message={form.errors.message_excerpt} className="mt-1" />
            </div>

            <div>
                <input
                    type="number"
                    value={form.data.offered_price_cents}
                    onChange={(e) => form.setData('offered_price_cents', e.target.value === '' ? '' : Number(e.target.value))}
                    placeholder="Offered (cents)"
                    className="w-full border rounded px-2 py-1 text-sm"
                />
                <InputError message={form.errors.offered_price_cents} className="mt-1" />
            </div>

            <Button type="submit" disabled={form.processing}>
                {form.processing && <Spinner className="mr-2 size-4" />}
                Log inquiry
            </Button>
        </form>
    );
}
