import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Buyer = { id: number; display_name: string };
type PickupItem = {
    id: number;
    title: string;
    status: string;
    pivot: { agreed_price_cents: number };
};
type Pickup = {
    id: number;
    status: 'scheduled' | 'completed' | 'no_show' | 'cancelled';
    notes: string | null;
    payment_method: string | null;
    payment_status: 'pending' | 'received';
    completed_at: string | null;
    cancelled_at: string | null;
    buyer: Buyer;
    items: PickupItem[];
};
type PaymentMethodOption = { value: string; label: string };

function totalCents(items: PickupItem[]): number {
    return items.reduce((sum, i) => sum + i.pivot.agreed_price_cents, 0);
}

export default function PickupsShow({
    pickup,
    payment_methods,
}: {
    pickup: Pickup;
    payment_methods: PaymentMethodOption[];
}) {
    const editForm = useForm({
        notes: pickup.notes ?? '',
        payment_method: pickup.payment_method ?? '',
    });
    const [completePayment, setCompletePayment] = useState(payment_methods[0]?.value ?? 'cash');

    const isOpen = pickup.status === 'scheduled';

    return (
        <>
            <Head title={`Pickup with ${pickup.buyer.display_name}`} />
            <div className="p-6 max-w-3xl space-y-6">
                <header className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            <Link href={`/buyers/${pickup.buyer.id}`} className="underline">
                                {pickup.buyer.display_name}
                            </Link>
                        </h1>
                        <p className="text-sm text-zinc-600">
                            Total ${(totalCents(pickup.items) / 100).toFixed(2)}
                        </p>
                    </div>
                    <span className="text-xs uppercase tracking-wide text-zinc-500">{pickup.status}</span>
                </header>

                <section>
                    <h2 className="font-medium mb-2">Items</h2>
                    <ul className="space-y-1 text-sm">
                        {pickup.items.map((i) => (
                            <li key={i.id} className="flex justify-between border-b py-1">
                                <Link href={`/items/${i.id}`} className="underline">{i.title}</Link>
                                <span>${(i.pivot.agreed_price_cents / 100).toFixed(2)}</span>
                            </li>
                        ))}
                    </ul>
                </section>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        editForm.patch(`/pickups/${pickup.id}`, { preserveScroll: true });
                    }}
                    className="border rounded-lg p-4 space-y-2"
                >
                    <h2 className="font-medium text-sm">Notes & payment method</h2>
                    <textarea
                        rows={3}
                        value={editForm.data.notes}
                        onChange={(e) => editForm.setData('notes', e.target.value)}
                        placeholder="Pickup time, location, anything else"
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                    <select
                        value={editForm.data.payment_method}
                        onChange={(e) => editForm.setData('payment_method', e.target.value)}
                        className="w-full border rounded px-2 py-1 text-sm"
                    >
                        <option value="">Select payment method…</option>
                        {payment_methods.map((m) => (
                            <option key={m.value} value={m.value}>{m.label}</option>
                        ))}
                    </select>
                    <button type="submit" className="bg-black text-white px-3 py-1.5 rounded text-sm">
                        Save
                    </button>
                </form>

                {isOpen && (
                    <section className="border rounded-lg p-4 space-y-3">
                        <h2 className="font-medium">Complete pickup</h2>
                        <p className="text-sm text-zinc-600">
                            Marks all items as sold and records payment as received.
                        </p>
                        <div className="flex items-center gap-2">
                            <select
                                value={completePayment}
                                onChange={(e) => setCompletePayment(e.target.value)}
                                className="border rounded px-2 py-1 text-sm"
                            >
                                {payment_methods.map((m) => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={() => {
                                    router.post(
                                        `/pickups/${pickup.id}/complete`,
                                        { payment_method: completePayment },
                                        { preserveScroll: true },
                                    );
                                }}
                                className="bg-emerald-700 text-white px-3 py-1.5 rounded text-sm"
                            >
                                Complete & mark sold
                            </button>
                        </div>
                    </section>
                )}

                {isOpen && (
                    <section className="border rounded-lg p-4 space-y-2">
                        <h2 className="font-medium text-sm">Cancel or no-show</h2>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(
                                        `/pickups/${pickup.id}/cancel`,
                                        { to: 'cancelled' },
                                        { preserveScroll: true },
                                    )
                                }
                                className="text-sm border rounded px-3 py-1.5"
                            >
                                Cancel pickup
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(
                                        `/pickups/${pickup.id}/cancel`,
                                        { to: 'no_show' },
                                        { preserveScroll: true },
                                    )
                                }
                                className="text-sm border rounded px-3 py-1.5"
                            >
                                Mark no-show
                            </button>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

PickupsShow.layout = {
    breadcrumbs: [{ title: 'Pickups', href: '/pickups' }],
};
