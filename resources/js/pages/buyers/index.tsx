import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Buyer = {
    id: number;
    display_name: string;
    phone: string | null;
    email: string | null;
    kijiji_handle: string | null;
    inquiries_count: number;
    updated_at: string;
};

export default function BuyersIndex({ buyers }: { buyers: Buyer[] }) {
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ display_name: '', phone: '', email: '', kijiji_handle: '' });

    return (
        <>
            <Head title="Buyers" />
            <div className="p-6 space-y-4 max-w-3xl">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Buyers</h1>
                    <button
                        type="button"
                        onClick={() => setShowForm((v) => !v)}
                        className="bg-black text-white px-3 py-1.5 rounded text-sm"
                    >
                        {showForm ? 'Cancel' : 'New buyer'}
                    </button>
                </div>

                {showForm && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/buyers', {
                                onSuccess: () => {
                                    form.reset();
                                    setShowForm(false);
                                },
                            });
                        }}
                        className="border rounded-lg p-4 space-y-2"
                    >
                        <input
                            type="text"
                            placeholder="Display name"
                            value={form.data.display_name}
                            onChange={(e) => form.setData('display_name', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <input
                            type="email"
                            placeholder="Email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Kijiji handle"
                            value={form.data.kijiji_handle}
                            onChange={(e) => form.setData('kijiji_handle', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <button type="submit" className="bg-black text-white px-3 py-1.5 rounded text-sm">
                            Save
                        </button>
                    </form>
                )}

                <ul className="space-y-2">
                    {buyers.map((b) => (
                        <li key={b.id} className="border rounded-lg p-3 flex items-center justify-between">
                            <div>
                                <Link href={`/buyers/${b.id}`} className="font-medium underline">
                                    {b.display_name}
                                </Link>
                                <p className="text-xs text-zinc-500">
                                    {b.kijiji_handle ?? b.email ?? b.phone ?? '—'}
                                </p>
                            </div>
                            <span className="text-xs text-zinc-600">
                                {b.inquiries_count} inquir{b.inquiries_count === 1 ? 'y' : 'ies'}
                            </span>
                        </li>
                    ))}
                </ul>

                {buyers.length === 0 && <p className="text-sm text-zinc-500">No buyers yet.</p>}
            </div>
        </>
    );
}

BuyersIndex.layout = {
    breadcrumbs: [{ title: 'Buyers', href: '/buyers' }],
};
