import { Head, router, useForm } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import {
    FormSection,
    SelectField,
    TextArea,
    TextField,
    Toggle,
} from '@/components/admin/form';
import { AdminPage } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type ServiceForm = {
    name: string;
    service_category_id: number | string;
    description: string;
    default_duration_minutes: number;
    buffer_minutes: number;
    requires_patch_test: boolean;
    patch_test_lead_hours: number | string;
    is_skin_service: boolean;
    is_house_call_eligible: boolean;
    is_featured: boolean;
    is_active: boolean;
    sort_order: number;
    price: number | string;
};

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    categories: { id: number; name: string }[];
    service: (ServiceForm & { slug: string }) | null;
};

export default function ServiceForm({ branchContext, categories, service }: Props) {
    const editing = service !== null;
    const branch = branchContext.current;

    const form = useForm<ServiceForm>({
        name: service?.name ?? '',
        service_category_id: service?.service_category_id ?? (categories[0]?.id ?? ''),
        description: service?.description ?? '',
        default_duration_minutes: service?.default_duration_minutes ?? 30,
        buffer_minutes: service?.buffer_minutes ?? 5,
        requires_patch_test: service?.requires_patch_test ?? false,
        patch_test_lead_hours: service?.patch_test_lead_hours ?? 48,
        is_skin_service: service?.is_skin_service ?? false,
        is_house_call_eligible: service?.is_house_call_eligible ?? true,
        is_featured: service?.is_featured ?? false,
        is_active: service?.is_active ?? true,
        sort_order: service?.sort_order ?? 0,
        price: service?.price ?? '',
    });

    function submit() {
        if (editing) {
            form.put(`/admin/services/${service.slug}`);
        } else {
            form.post('/admin/services');
        }
    }

    return (
        <>
            <Head title={editing ? `Edit ${service.name}` : 'New service'} />

            <AdminPage
                title={editing ? `Edit ${service.name}` : 'New service'}
                lede={
                    branch
                        ? `Price applies to ${branch.name}. Other branches keep their own.`
                        : 'No branch selected, so no price can be set.'
                }
                action={
                    editing && (
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `Remove ${service.name} from the menu? Past bookings keep it.`,
                                    )
                                ) {
                                    router.delete(
                                        `/admin/services/${service.slug}`,
                                    );
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
                    <FormSection title="What it is">
                        <TextField
                            label="Name"
                            required
                            value={form.data.name}
                            error={form.errors.name}
                            onChange={(value) => form.setData('name', value)}
                            placeholder="Signature Cut"
                        />
                        <SelectField
                            label="Category"
                            required
                            value={form.data.service_category_id}
                            error={form.errors.service_category_id}
                            onChange={(value) =>
                                form.setData('service_category_id', Number(value))
                            }
                            options={categories.map((category) => ({
                                value: category.id,
                                label: category.name,
                            }))}
                        />
                        <TextArea
                            label="Description"
                            value={form.data.description}
                            error={form.errors.description}
                            onChange={(value) => form.setData('description', value)}
                            placeholder="Consultation, cut, line up, hot towel finish."
                        />
                    </FormSection>

                    <FormSection
                        title="Time and price"
                        description="Duration drives the booking calendar, so keep it honest."
                    >
                        <div className="flex flex-wrap gap-4">
                            <TextField
                                label="Minutes in the chair"
                                required
                                type="number"
                                min="5"
                                step="5"
                                value={form.data.default_duration_minutes}
                                error={form.errors.default_duration_minutes}
                                onChange={(value) =>
                                    form.setData(
                                        'default_duration_minutes',
                                        Number(value),
                                    )
                                }
                                className="w-40"
                            />
                            <TextField
                                label="Buffer after"
                                type="number"
                                min="0"
                                step="5"
                                hint="Tidy up time"
                                value={form.data.buffer_minutes}
                                error={form.errors.buffer_minutes}
                                onChange={(value) =>
                                    form.setData('buffer_minutes', Number(value))
                                }
                                className="w-36"
                            />
                            <TextField
                                label={`Price at ${branch?.name ?? 'this branch'}`}
                                type="number"
                                min="0"
                                step="0.5"
                                hint="Blank means not sold here"
                                value={form.data.price}
                                error={form.errors.price}
                                onChange={(value) => form.setData('price', value)}
                                className="w-40"
                            />
                        </div>

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

                    <FormSection title="How it behaves">
                        <Toggle
                            label="On the menu"
                            hint="Off hides it from the public price list"
                            checked={form.data.is_active}
                            onChange={(value) => form.setData('is_active', value)}
                        />
                        <Toggle
                            label="Featured on the homepage"
                            checked={form.data.is_featured}
                            onChange={(value) => form.setData('is_featured', value)}
                        />
                        <Toggle
                            label="Can be a house call"
                            hint="Anything needing the skin room cannot travel"
                            checked={form.data.is_house_call_eligible}
                            onChange={(value) =>
                                form.setData('is_house_call_eligible', value)
                            }
                        />
                        <Toggle
                            label="Skin room service"
                            hint="Needs the room, and an aesthetician"
                            checked={form.data.is_skin_service}
                            onChange={(value) =>
                                form.setData('is_skin_service', value)
                            }
                        />
                    </FormSection>

                    <FormSection
                        title="Patch test"
                        description="Colour work needs a test first. Bookings inside the lead time are refused."
                    >
                        <Toggle
                            label="Needs a patch test"
                            checked={form.data.requires_patch_test}
                            onChange={(value) =>
                                form.setData('requires_patch_test', value)
                            }
                        />
                        {form.data.requires_patch_test && (
                            <TextField
                                label="Hours needed before the appointment"
                                required
                                type="number"
                                min="1"
                                value={form.data.patch_test_lead_hours}
                                error={form.errors.patch_test_lead_hours}
                                onChange={(value) =>
                                    form.setData(
                                        'patch_test_lead_hours',
                                        Number(value),
                                    )
                                }
                                className="w-48"
                            />
                        )}
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
                        {editing ? 'Save changes' : 'Add service'}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => router.get('/admin/services')}
                    >
                        Cancel
                    </Button>
                </div>
            </AdminPage>
        </>
    );
}

ServiceForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Services', href: '/admin/services' },
    ],
};
