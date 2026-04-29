import { Head, useForm } from '@inertiajs/react';
import { PhotoUploader } from '@/components/photo-uploader';

type Condition = { value: string; label: string };
type Photo = { id: number; path: string; thumbnail_path: string | null; position: number; is_primary: boolean };
type Item = {
    id: number;
    title: string;
    description: string | null;
    category: string | null;
    condition: string;
    asking_price_cents: number;
    floor_price_cents: number | null;
    location_in_house: string | null;
    notes: string | null;
    status: string;
    photos: Photo[];
};

export default function ItemsEdit({ item, conditions }: { item: Item; conditions: Condition[] }) {
    const form = useForm({
        title: item.title,
        description: item.description ?? '',
        category: item.category ?? '',
        condition: item.condition,
        asking_price_cents: item.asking_price_cents,
        floor_price_cents: item.floor_price_cents ?? '',
        location_in_house: item.location_in_house ?? '',
        notes: item.notes ?? '',
    });

    return (
        <>
            <Head title={`Edit ${item.title}`} />
            <form
                className="max-w-xl p-6 space-y-4"
                onSubmit={(e) => { e.preventDefault(); form.patch(`/items/${item.id}`); }}
            >
                <h1 className="text-2xl font-semibold">Edit item</h1>

                <label className="block">
                    <span className="text-sm">Title</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                </label>

                <label className="block">
                    <span className="text-sm">Description</span>
                    <textarea
                        className="mt-1 w-full border rounded px-3 py-2"
                        rows={4}
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                </label>

                <label className="block">
                    <span className="text-sm">Condition</span>
                    <select
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.condition}
                        onChange={(e) => form.setData('condition', e.target.value)}
                    >
                        {conditions.map((c) => (
                            <option key={c.value} value={c.value}>{c.label}</option>
                        ))}
                    </select>
                </label>

                <label className="block">
                    <span className="text-sm">Asking price (cents)</span>
                    <input
                        type="number"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.asking_price_cents}
                        onChange={(e) => form.setData('asking_price_cents', Number(e.target.value))}
                    />
                </label>

                <label className="block">
                    <span className="text-sm">Floor price (cents)</span>
                    <input
                        type="number"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.floor_price_cents}
                        onChange={(e) => form.setData('floor_price_cents', Number(e.target.value) || '')}
                    />
                </label>

                <label className="block">
                    <span className="text-sm">Location in house</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.location_in_house}
                        onChange={(e) => form.setData('location_in_house', e.target.value)}
                    />
                </label>

                <label className="block">
                    <span className="text-sm">Private notes</span>
                    <textarea
                        className="mt-1 w-full border rounded px-3 py-2"
                        rows={2}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                </label>

                <section className="border-t pt-4">
                    <h2 className="text-lg font-medium mb-2">Photos</h2>
                    <PhotoUploader itemId={item.id} photos={item.photos} />
                </section>

                <button
                    type="submit"
                    className="bg-black text-white px-4 py-2 rounded"
                    disabled={form.processing}
                >
                    Save
                </button>
            </form>
        </>
    );
}

ItemsEdit.layout = {
    breadcrumbs: [
        { title: 'Items', href: '/items' },
        { title: 'Edit', href: '#' },
    ],
};
