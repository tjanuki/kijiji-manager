import { Head, useForm } from '@inertiajs/react';

type Condition = { value: string; label: string };

export default function ItemsCreate({ conditions }: { conditions: Condition[] }) {
    const form = useForm({
        title: '',
        description: '',
        category: '',
        condition: 'good',
        asking_price_cents: 0,
        floor_price_cents: '',
        location_in_house: '',
        notes: '',
    });

    return (
        <>
            <Head title="New item" />
            <form
                className="max-w-xl p-6 space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/items');
                }}
            >
                <h1 className="text-2xl font-semibold">New item</h1>

                <label className="block">
                    <span className="text-sm">Title</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                    {form.errors.title && <p className="text-red-600 text-sm">{form.errors.title}</p>}
                </label>

                <label className="block">
                    <span className="text-sm">Condition</span>
                    <select
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.condition}
                        onChange={(e) => form.setData('condition', e.target.value)}
                    >
                        {conditions.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="block">
                    <span className="text-sm">Asking price (cents)</span>
                    <input
                        type="number"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.asking_price_cents || ''}
                        onChange={(e) => form.setData('asking_price_cents', Number(e.target.value))}
                    />
                    {form.errors.asking_price_cents && (
                        <p className="text-red-600 text-sm">{form.errors.asking_price_cents}</p>
                    )}
                </label>

                <button
                    type="submit"
                    className="bg-black text-white px-4 py-2 rounded"
                    disabled={form.processing}
                >
                    Create
                </button>
            </form>
        </>
    );
}

ItemsCreate.layout = {
    breadcrumbs: [
        { title: 'Items', href: '/items' },
        { title: 'New', href: '/items/create' },
    ],
};
