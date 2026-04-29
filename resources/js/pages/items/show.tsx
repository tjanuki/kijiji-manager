import { Head, Link } from '@inertiajs/react';

type Item = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    asking_price_cents: number;
    kijiji_url: string | null;
};

export default function ItemsShow({ item }: { item: Item }) {
    return (
        <>
            <Head title={item.title} />
            <div className="p-6 max-w-3xl space-y-4">
                <h1 className="text-2xl font-semibold">{item.title}</h1>
                <p className="text-sm">Status: {item.status}</p>
                <p className="text-sm">Asking: ${(item.asking_price_cents / 100).toFixed(2)}</p>
                {item.description && <p>{item.description}</p>}
                {item.kijiji_url && (
                    <a className="text-blue-600 underline" href={item.kijiji_url} target="_blank" rel="noreferrer">
                        Live on Kijiji
                    </a>
                )}
                <Link href={`/items/${item.id}/edit`} className="inline-block bg-black text-white px-4 py-2 rounded">
                    Edit
                </Link>
            </div>
        </>
    );
}

ItemsShow.layout = {
    breadcrumbs: [
        { title: 'Items', href: '/items' },
    ],
};
