import { Head, Link } from '@inertiajs/react';

type PickupItem = {
    id: number;
    title: string;
    pivot: { agreed_price_cents: number };
};
type Buyer = { id: number; display_name: string };
type Pickup = {
    id: number;
    status: 'scheduled' | 'completed' | 'no_show' | 'cancelled';
    notes: string | null;
    buyer: Buyer;
    items: PickupItem[];
    created_at: string;
};

function totalCents(items: PickupItem[]): number {
    return items.reduce((sum, i) => sum + i.pivot.agreed_price_cents, 0);
}

export default function PickupsIndex({ pickups }: { pickups: Pickup[] }) {
    return (
        <>
            <Head title="Pickups" />
            <div className="p-6 space-y-4 max-w-3xl">
                <h1 className="text-2xl font-semibold">Pending pickups</h1>

                {pickups.length === 0 && (
                    <p className="text-sm text-zinc-500">
                        No scheduled pickups. Create one from a listed item.
                    </p>
                )}

                <ul className="space-y-2">
                    {pickups.map((p) => (
                        <li key={p.id} className="border rounded-lg p-4 space-y-1">
                            <div className="flex items-center justify-between">
                                <Link href={`/pickups/${p.id}`} className="font-medium underline">
                                    {p.buyer.display_name}
                                </Link>
                                <span className="text-sm text-zinc-600">
                                    ${(totalCents(p.items) / 100).toFixed(2)}
                                </span>
                            </div>
                            {p.notes && (
                                <p className="text-sm text-zinc-700 whitespace-pre-wrap">{p.notes}</p>
                            )}
                            <ul className="text-xs text-zinc-600 list-disc pl-4">
                                {p.items.map((i) => (
                                    <li key={i.id}>
                                        {i.title} — ${(i.pivot.agreed_price_cents / 100).toFixed(2)}
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

PickupsIndex.layout = {
    breadcrumbs: [{ title: 'Pickups', href: '/pickups' }],
};
