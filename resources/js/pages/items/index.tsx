import { Head, Link, router } from '@inertiajs/react';
import { ItemCard } from '@/components/item-card';

type Photo = { id: number; thumbnail_path: string | null; path: string };
type Item = {
    id: number;
    title: string;
    status: 'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn';
    asking_price_cents: number;
    is_stale?: boolean;
    photos?: Photo[];
};

type Props = {
    items: Item[];
    filters: { stale: boolean };
    stale_count: number;
};

export default function ItemsIndex({ items, filters, stale_count }: Props) {
    const toggleStale = (checked: boolean) => {
        router.get(
            '/items',
            checked ? { stale: 1 } : {},
            { preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Items" />
            <div className="p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Items</h1>
                    <Link href="/items/create" className="bg-black text-white px-3 py-1.5 rounded text-sm">
                        New item
                    </Link>
                </div>
                <label className="inline-flex items-center gap-2 text-sm text-zinc-700">
                    <input
                        type="checkbox"
                        checked={filters.stale}
                        onChange={(e) => toggleStale(e.target.checked)}
                        className="rounded"
                    />
                    <span>Show only stale items ({stale_count} stale)</span>
                </label>
                {items.length === 0 && filters.stale ? (
                    <p className="text-sm text-zinc-500 py-12 text-center">Nothing stale right now.</p>
                ) : (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {items.map((item) => (
                            <ItemCard key={item.id} item={item} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ItemsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Items',
            href: '/items',
        },
    ],
};
