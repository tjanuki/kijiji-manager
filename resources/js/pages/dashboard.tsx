import { Head } from '@inertiajs/react';
import { ItemCard } from '@/components/item-card';
import { dashboard } from '@/routes';

type Counts = Record<'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn', number>;
type Photo = { id: number; thumbnail_path: string | null; path: string };
type Item = {
    id: number;
    title: string;
    status: keyof Counts;
    asking_price_cents: number;
    photos?: Photo[];
};

export default function Dashboard({ counts, recentItems }: { counts: Counts; recentItems: Item[] }) {
    const order: (keyof Counts)[] = ['draft', 'ready', 'listed', 'reserved', 'sold', 'withdrawn'];

    return (
        <>
            <Head title="Dashboard" />
            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-semibold">Dashboard</h1>

                <div className="grid grid-cols-3 md:grid-cols-6 gap-3">
                    {order.map((s) => (
                        <div key={s} className="border rounded p-3">
                            <p className="text-xs uppercase text-zinc-500">{s}</p>
                            <p className="text-2xl font-semibold">{counts[s] ?? 0}</p>
                        </div>
                    ))}
                </div>

                <section className="space-y-2">
                    <h2 className="text-lg font-medium">Recent items</h2>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {recentItems.map((item) => (
                            <ItemCard key={item.id} item={item} />
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
