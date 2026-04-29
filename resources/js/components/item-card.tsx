import { Link } from '@inertiajs/react';
import { StatusPill } from '@/components/status-pill';

type Photo = { id: number; thumbnail_path: string | null; path: string };
type Item = {
    id: number;
    title: string;
    status: 'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn';
    asking_price_cents: number;
    photos?: Photo[];
};

export function ItemCard({ item }: { item: Item }) {
    const primary = item.photos?.[0];
    const thumb = primary?.thumbnail_path ?? primary?.path;

    return (
        <Link href={`/items/${item.id}`} className="block border rounded-lg overflow-hidden hover:shadow">
            <div className="aspect-square bg-zinc-100">
                {thumb && <img src={`/storage/${thumb}`} alt="" className="w-full h-full object-cover" />}
            </div>
            <div className="p-3 space-y-1">
                <div className="flex items-start justify-between gap-2">
                    <p className="font-medium leading-tight line-clamp-2">{item.title}</p>
                    <StatusPill status={item.status} />
                </div>
                <p className="text-sm text-zinc-600">${(item.asking_price_cents / 100).toFixed(2)}</p>
            </div>
        </Link>
    );
}
