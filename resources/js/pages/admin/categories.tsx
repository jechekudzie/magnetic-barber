import { Head, router, useForm } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FormSection, TextArea, TextField, Toggle } from '@/components/admin/form';
import { AdminPage, Panel, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type Category = {
    id: number;
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    icon: string | null;
    sort_order: number;
    is_active: boolean;
    services_count: number;
};

export default function Categories({
    categories,
}: {
    branchContext: { current: Branch | null; available: Branch[] };
    categories: Category[];
}) {
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <>
            <Head title="Categories" />

            <AdminPage
                title="Categories"
                lede="How the price list is grouped. A category with no services never renders on the site."
                action={
                    <Button onClick={() => setAdding((value) => !value)}>
                        <Plus className="size-4" aria-hidden="true" />
                        New category
                    </Button>
                }
            >
                {adding && (
                    <CategoryForm
                        category={null}
                        onDone={() => setAdding(false)}
                    />
                )}

                <Panel>
                    <ul className="divide-y">
                        {categories.map((category) => (
                            <li key={category.id} className="p-4">
                                {editing === category.id ? (
                                    <CategoryForm
                                        category={category}
                                        onDone={() => setEditing(null)}
                                    />
                                ) : (
                                    <div className="flex flex-wrap items-center gap-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {category.name}
                                                </span>
                                                {!category.is_active && (
                                                    <Pill tone="warn">
                                                        Hidden
                                                    </Pill>
                                                )}
                                                <Pill>
                                                    {category.services_count}{' '}
                                                    {category.services_count === 1
                                                        ? 'service'
                                                        : 'services'}
                                                </Pill>
                                            </div>
                                            {category.tagline && (
                                                <p className="text-muted-foreground mt-0.5 text-sm">
                                                    {category.tagline}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditing(category.id)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            `Remove ${category.name}?`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            `/admin/categories/${category.slug}`,
                                                            { preserveScroll: true },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Trash2
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>
            </AdminPage>
        </>
    );
}

function CategoryForm({
    category,
    onDone,
}: {
    category: Category | null;
    onDone: () => void;
}) {
    const form = useForm({
        name: category?.name ?? '',
        tagline: category?.tagline ?? '',
        description: category?.description ?? '',
        icon: category?.icon ?? '',
        sort_order: category?.sort_order ?? 0,
        is_active: category?.is_active ?? true,
    });

    function submit() {
        const options = { preserveScroll: true, onSuccess: onDone };

        if (category) {
            form.put(`/admin/categories/${category.slug}`, options);
        } else {
            form.post('/admin/categories', options);
        }
    }

    return (
        <FormSection title={category ? `Edit ${category.name}` : 'New category'}>
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    label="Name"
                    required
                    value={form.data.name}
                    error={form.errors.name}
                    onChange={(value) => form.setData('name', value)}
                    placeholder="Cuts and Beards"
                />
                <TextField
                    label="Icon"
                    hint="A lucide icon name, so web and mobile match"
                    value={form.data.icon}
                    error={form.errors.icon}
                    onChange={(value) => form.setData('icon', value)}
                    placeholder="scissors"
                />
            </div>

            <TextField
                label="Tagline"
                value={form.data.tagline}
                error={form.errors.tagline}
                onChange={(value) => form.setData('tagline', value)}
                placeholder="Fades, shape ups, beard trims."
            />

            <TextArea
                label="Description"
                value={form.data.description}
                error={form.errors.description}
                onChange={(value) => form.setData('description', value)}
            />

            <div className="flex flex-wrap items-end gap-4">
                <TextField
                    label="Sort order"
                    type="number"
                    min="0"
                    value={form.data.sort_order}
                    error={form.errors.sort_order}
                    onChange={(value) => form.setData('sort_order', Number(value))}
                    className="w-32"
                />
                <div className="pb-2">
                    <Toggle
                        label="Visible"
                        checked={form.data.is_active}
                        onChange={(value) => form.setData('is_active', value)}
                    />
                </div>
            </div>

            <div className="flex gap-3">
                <Button onClick={submit} disabled={form.processing}>
                    {form.processing && (
                        <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                    )}
                    {category ? 'Save' : 'Add category'}
                </Button>
                <Button variant="outline" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </FormSection>
    );
}

Categories.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Categories', href: '/admin/categories' },
    ],
};
