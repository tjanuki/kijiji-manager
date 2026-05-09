import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Condition = { value: string; label: string };

export default function ItemsCreate({ conditions }: { conditions: Condition[] }) {
    const form = useForm({
        title: '',
        description: '',
        category: '',
        condition: conditions[0]?.value ?? '',
        asking_price_cents: 0,
        floor_price_cents: '' as number | '',
        location_in_house: '',
        notes: '',
    });

    return (
        <>
            <Head title="New item" />
            <form
                className="max-w-xl p-6 space-y-4"
                onSubmit={(e) => {
 e.preventDefault(); form.post('/items'); 
}}
            >
                <h1 className="text-2xl font-semibold">New item</h1>

                <label className="block">
                    <span className="text-sm">Title</span>
                    <input
                        name="title"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                    <InputError message={form.errors.title} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Description</span>
                    <textarea
                        rows={4}
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                    <InputError message={form.errors.description} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Category</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.category}
                        onChange={(e) => form.setData('category', e.target.value)}
                    />
                    <InputError message={form.errors.category} className="mt-1" />
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
                    <InputError message={form.errors.condition} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Asking price (cents)</span>
                    <input
                        type="number"
                        name="asking_price_cents"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.asking_price_cents || ''}
                        onChange={(e) => form.setData('asking_price_cents', Number(e.target.value))}
                    />
                    <InputError message={form.errors.asking_price_cents} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Floor price (cents)</span>
                    <input
                        type="number"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.floor_price_cents}
                        onChange={(e) => form.setData('floor_price_cents', e.target.value === '' ? '' : Number(e.target.value))}
                    />
                    <InputError message={form.errors.floor_price_cents} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Location in house</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.location_in_house}
                        onChange={(e) => form.setData('location_in_house', e.target.value)}
                    />
                    <InputError message={form.errors.location_in_house} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Private notes</span>
                    <textarea
                        rows={2}
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                    <InputError message={form.errors.notes} className="mt-1" />
                </label>

                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner className="mr-2 size-4" />}
                    Create
                </Button>
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
