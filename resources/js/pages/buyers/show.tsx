import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Item = { id: number; title: string };
type Inquiry = {
    id: number;
    item: Item;
    message_excerpt: string | null;
    status: string;
    offered_price_cents: number | null;
    received_at: string | null;
};
type Buyer = {
    id: number;
    display_name: string;
    phone: string | null;
    email: string | null;
    kijiji_handle: string | null;
    trust_notes: string | null;
};

export default function BuyersShow({ buyer, inquiries }: { buyer: Buyer; inquiries: Inquiry[] }) {
    const form = useForm({
        display_name: buyer.display_name,
        phone: buyer.phone ?? '',
        email: buyer.email ?? '',
        kijiji_handle: buyer.kijiji_handle ?? '',
        trust_notes: buyer.trust_notes ?? '',
    });

    return (
        <>
            <Head title={buyer.display_name} />
            <div className="p-6 max-w-3xl space-y-6">
                <h1 className="text-2xl font-semibold">{buyer.display_name}</h1>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/buyers/${buyer.id}`, { preserveScroll: true });
                    }}
                    className="border rounded-lg p-4 space-y-2"
                >
                    <h2 className="font-medium text-sm">Edit details</h2>
                    <div>
                        <input
                            type="text"
                            value={form.data.display_name}
                            onChange={(e) => form.setData('display_name', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors.display_name} className="mt-1" />
                    </div>
                    <div>
                        <input
                            type="text"
                            placeholder="Phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors.phone} className="mt-1" />
                    </div>
                    <div>
                        <input
                            type="email"
                            placeholder="Email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors.email} className="mt-1" />
                    </div>
                    <div>
                        <input
                            type="text"
                            placeholder="Kijiji handle"
                            value={form.data.kijiji_handle}
                            onChange={(e) => form.setData('kijiji_handle', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors.kijiji_handle} className="mt-1" />
                    </div>
                    <div>
                        <textarea
                            rows={2}
                            placeholder="Trust notes (private)"
                            value={form.data.trust_notes}
                            onChange={(e) => form.setData('trust_notes', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors.trust_notes} className="mt-1" />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner className="mr-2 size-4" />}
                        Save
                    </Button>
                </form>

                <section>
                    <h2 className="font-medium mb-2">History</h2>
                    {inquiries.length === 0 ? (
                        <p className="text-sm text-zinc-500">No inquiries yet.</p>
                    ) : (
                        <ul className="space-y-2">
                            {inquiries.map((i) => (
                                <li key={i.id} className="border rounded-lg p-3">
                                    <div className="flex justify-between items-baseline">
                                        <Link href={`/items/${i.item.id}`} className="font-medium underline">
                                            {i.item.title}
                                        </Link>
                                        <span className="text-xs uppercase tracking-wide text-zinc-500">
                                            {i.status}
                                        </span>
                                    </div>
                                    {i.message_excerpt && (
                                        <p className="text-sm text-zinc-600 mt-1 whitespace-pre-wrap">
                                            {i.message_excerpt}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

BuyersShow.layout = {
    breadcrumbs: [
        { title: 'Buyers', href: '/buyers' },
    ],
};
