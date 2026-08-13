import { Head, router, useForm } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import {
    FormSection,
    ListField,
    SelectField,
    TextArea,
    TextField,
    Toggle,
} from '@/components/admin/form';
import { AdminPage } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type PlanForm = {
    name: string;
    tagline: string;
    description: string;
    type: 'unlimited' | 'session_pack';
    session_count: number | string;
    price: number | string;
    validity_days: number;
    included_service_ids: number[];
    perks: string[];
    is_popular: boolean;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    services: { id: number; name: string }[];
    plan: (PlanForm & { slug: string }) | null;
};

export default function PlanForm({ services, plan }: Props) {
    const editing = plan !== null;

    const form = useForm<PlanForm>({
        name: plan?.name ?? '',
        tagline: plan?.tagline ?? '',
        description: plan?.description ?? '',
        type: plan?.type ?? 'session_pack',
        session_count: plan?.session_count ?? 4,
        price: plan?.price ?? '',
        validity_days: plan?.validity_days ?? 30,
        included_service_ids: plan?.included_service_ids ?? [],
        perks: plan?.perks ?? [],
        is_popular: plan?.is_popular ?? false,
        is_active: plan?.is_active ?? true,
        sort_order: plan?.sort_order ?? 0,
    });

    function toggleService(id: number) {
        form.setData(
            'included_service_ids',
            form.data.included_service_ids.includes(id)
                ? form.data.included_service_ids.filter((value) => value !== id)
                : [...form.data.included_service_ids, id],
        );
    }

    function submit() {
        if (editing) {
            form.put(`/admin/plans/${plan.slug}`);
        } else {
            form.post('/admin/plans');
        }
    }

    return (
        <>
            <Head title={editing ? `Edit ${plan.name}` : 'New plan'} />

            <AdminPage
                title={editing ? `Edit ${plan.name}` : 'New plan'}
                lede="Paid up front, redeemed over time. Predictable income for the shop."
                action={
                    editing && (
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (window.confirm(`Remove ${plan.name}?`)) {
                                    router.delete(`/admin/plans/${plan.slug}`);
                                }
                            }}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            Remove
                        </Button>
                    )
                }
            >
                <div className="grid gap-4 lg:grid-cols-2">
                    <FormSection title="The offer">
                        <TextField
                            label="Name"
                            required
                            value={form.data.name}
                            error={form.errors.name}
                            onChange={(value) => form.setData('name', value)}
                            placeholder="Always Sharp"
                        />
                        <TextField
                            label="Tagline"
                            value={form.data.tagline}
                            error={form.errors.tagline}
                            onChange={(value) => form.setData('tagline', value)}
                            placeholder="Unlimited cuts and line ups."
                        />
                        <TextArea
                            label="Description"
                            value={form.data.description}
                            error={form.errors.description}
                            onChange={(value) => form.setData('description', value)}
                        />
                    </FormSection>

                    <FormSection title="What it costs">
                        <SelectField
                            label="Type"
                            required
                            value={form.data.type}
                            error={form.errors.type}
                            onChange={(value) =>
                                form.setData(
                                    'type',
                                    value as 'unlimited' | 'session_pack',
                                )
                            }
                            options={[
                                { value: 'session_pack', label: 'Session pack' },
                                { value: 'unlimited', label: 'Unlimited' },
                            ]}
                        />

                        <div className="flex flex-wrap gap-4">
                            <TextField
                                label="Price"
                                required
                                type="number"
                                min="0"
                                step="0.5"
                                value={form.data.price}
                                error={form.errors.price}
                                onChange={(value) => form.setData('price', value)}
                                className="w-32"
                            />
                            <TextField
                                label="Valid for (days)"
                                required
                                type="number"
                                min="1"
                                value={form.data.validity_days}
                                error={form.errors.validity_days}
                                onChange={(value) =>
                                    form.setData('validity_days', Number(value))
                                }
                                className="w-40"
                            />
                            {form.data.type === 'session_pack' && (
                                <TextField
                                    label="Sessions"
                                    required
                                    type="number"
                                    min="1"
                                    value={form.data.session_count}
                                    error={form.errors.session_count}
                                    onChange={(value) =>
                                        form.setData(
                                            'session_count',
                                            Number(value),
                                        )
                                    }
                                    className="w-32"
                                />
                            )}
                        </div>
                    </FormSection>

                    <FormSection
                        title="What it includes"
                        description="Which services a session can be spent on."
                    >
                        <div className="grid gap-2 sm:grid-cols-2">
                            {services.map((service) => (
                                <label
                                    key={service.id}
                                    className="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.included_service_ids.includes(
                                            service.id,
                                        )}
                                        onChange={() => toggleService(service.id)}
                                        className="accent-primary size-4"
                                    />
                                    {service.name}
                                </label>
                            ))}
                        </div>
                    </FormSection>

                    <FormSection title="How it shows">
                        <ListField
                            label="Perks"
                            values={form.data.perks}
                            onChange={(values) => form.setData('perks', values)}
                            placeholder="Priority over the walk in queue"
                            hint="Listed on the plan card"
                        />
                        <Toggle
                            label="On sale"
                            checked={form.data.is_active}
                            onChange={(value) => form.setData('is_active', value)}
                        />
                        <Toggle
                            label="Highlight as most taken"
                            checked={form.data.is_popular}
                            onChange={(value) => form.setData('is_popular', value)}
                        />
                        <TextField
                            label="Sort order"
                            type="number"
                            min="0"
                            value={form.data.sort_order}
                            error={form.errors.sort_order}
                            onChange={(value) =>
                                form.setData('sort_order', Number(value))
                            }
                            className="w-32"
                        />
                    </FormSection>
                </div>

                <div className="flex gap-3">
                    <Button onClick={submit} disabled={form.processing}>
                        {form.processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {editing ? 'Save changes' : 'Add plan'}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => router.get('/admin/plans')}
                    >
                        Cancel
                    </Button>
                </div>
            </AdminPage>
        </>
    );
}

PlanForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Plans', href: '/admin/plans' },
    ],
};
