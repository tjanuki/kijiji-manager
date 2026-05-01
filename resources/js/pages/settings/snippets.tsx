import { Form, Head } from '@inertiajs/react';
import SnippetsController from '@/actions/App/Http/Controllers/Settings/SnippetsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit as editProfile } from '@/routes/profile';
import { edit } from '@/routes/snippets';

type Snippets = { pickup: string; payment: string };

export default function SettingsSnippets({ snippets }: { snippets: Snippets }) {
    return (
        <>
            <Head title="Listing snippets" />

            <h1 className="sr-only">Listing snippets</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Listing snippets"
                    description="Reusable text blocks injected into every listing draft. Update once, every draft re-renders."
                />

                <Form
                    {...SnippetsController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="pickup">Pickup</Label>

                                <textarea
                                    id="pickup"
                                    name="pickup"
                                    rows={3}
                                    defaultValue={snippets.pickup}
                                    placeholder="e.g. Pickup at front porch, Liberty Village. Saturdays 10–4."
                                    className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive mt-1 block w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.pickup}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="payment">Payment</Label>

                                <textarea
                                    id="payment"
                                    name="payment"
                                    rows={2}
                                    defaultValue={snippets.payment}
                                    placeholder="e.g. Cash or e-transfer on pickup."
                                    className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive mt-1 block w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.payment}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-snippets-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SettingsSnippets.layout = {
    breadcrumbs: [
        { title: 'Settings', href: editProfile() },
        { title: 'Snippets', href: edit() },
    ],
};
