import { Form, Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import SnippetsController from '@/actions/App/Http/Controllers/Settings/SnippetsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit as editProfile } from '@/routes/profile';
import { edit } from '@/routes/snippets';

type ReplyTemplate = { label: string; body: string };

type Snippets = {
    pickup: string;
    payment: string;
    reply_templates: ReplyTemplate[];
};

export default function SettingsSnippets({ snippets }: { snippets: Snippets }) {
    const [replyTemplates, setReplyTemplates] = useState<ReplyTemplate[]>(
        snippets.reply_templates ?? [],
    );

    useEffect(() => {
        // intentional: re-sync local state when prop changes after a successful save
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setReplyTemplates(snippets.reply_templates ?? []);
    }, [snippets.reply_templates]);

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

                            <section className="space-y-2 border-t pt-4">
                                <div className="flex items-center justify-between">
                                    <h2 className="font-medium">Reply templates</h2>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setReplyTemplates([
                                                ...replyTemplates,
                                                { label: '', body: '' },
                                            ])
                                        }
                                        className="text-xs border rounded px-2 py-1"
                                    >
                                        Add template
                                    </button>
                                </div>

                                {replyTemplates.map((tpl, i) => (
                                    <div key={i} className="border rounded-lg p-3 space-y-2">
                                        <input
                                            type="text"
                                            name={`reply_templates[${i}][label]`}
                                            placeholder="Label"
                                            value={tpl.label}
                                            onChange={(e) => {
                                                const next = [...replyTemplates];
                                                next[i] = { ...next[i], label: e.target.value };
                                                setReplyTemplates(next);
                                            }}
                                            className="w-full border rounded px-2 py-1 text-sm"
                                        />
                                        <InputError
                                            message={errors[`reply_templates.${i}.label`]}
                                        />
                                        <textarea
                                            name={`reply_templates[${i}][body]`}
                                            rows={2}
                                            placeholder="Body"
                                            value={tpl.body}
                                            onChange={(e) => {
                                                const next = [...replyTemplates];
                                                next[i] = { ...next[i], body: e.target.value };
                                                setReplyTemplates(next);
                                            }}
                                            className="w-full border rounded px-2 py-1 text-sm"
                                        />
                                        <InputError
                                            message={errors[`reply_templates.${i}.body`]}
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setReplyTemplates(
                                                    replyTemplates.filter((_, j) => j !== i),
                                                )
                                            }
                                            className="text-xs text-rose-700 underline"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                ))}
                            </section>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-snippets-button"
                                >
                                    {processing && <Spinner className="mr-2 size-4" />}
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
