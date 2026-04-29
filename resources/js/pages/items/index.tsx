import { Head, Link } from '@inertiajs/react';
import { ItemCard } from '@/components/item-card';

type Photo = { id: number; thumbnail_path: string | null; path: string };
type Item = {
    id: number;
    title: string;
    status: 'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn';
    asking_price_cents: number;
    photos?: Photo[];
};

export default function ItemsIndex({ items }: { items: Item[] }) {
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
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {items.map((item) => (
                        <ItemCard key={item.id} item={item} />
                    ))}
                </div>
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
