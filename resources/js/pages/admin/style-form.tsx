import { Head, router, useForm } from '@inertiajs/react';
import { ImageUp, Loader2, Scissors, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    Field,
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

type StyleForm = {
    code: string;
    name: string;
    description: string;
    service_id: number | string;
    gender_tag: string;
    hair_type_tag: string[];
    typical_duration_minutes: number | string;
    is_featured: boolean;
    is_active: boolean;
    sort_order: number;
    photo: File | null;
    remove_photo: boolean;
};

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    services: { id: number; name: string }[];
    style: (Omit<StyleForm, 'photo' | 'remove_photo'> & {
        slug: string;
        image_url: string | null;
    }) | null;
    nextCode: string;
};

export default function StyleForm({ services, style, nextCode }: Props) {
    const editing = style !== null;
    const [preview, setPreview] = useState<string | null>(style?.image_url ?? null);

    const form = useForm<StyleForm>({
        code: style?.code ?? nextCode,
        name: style?.name ?? '',
        description: style?.description ?? '',
        service_id: style?.service_id ?? '',
        gender_tag: style?.gender_tag ?? 'unisex',
        hair_type_tag: style?.hair_type_tag ?? [],
        typical_duration_minutes: style?.typical_duration_minutes ?? '',
        is_featured: style?.is_featured ?? false,
        is_active: style?.is_active ?? true,
        sort_order: style?.sort_order ?? 0,
        photo: null,
        remove_photo: false,
    });

    function pickPhoto(file: File | null) {
        form.setData('photo', file);
        form.setData('remove_photo', false);
        setPreview(file ? URL.createObjectURL(file) : style?.image_url ?? null);
    }

    function submit() {
        // Multipart, so it always posts: PHP does not parse a PUT body.
        if (editing) {
            form.post(`/admin/styles/${style.slug}`, {
                forceFormData: true,
                headers: { 'X-HTTP-Method-Override': 'POST' },
            });
        } else {
            form.post('/admin/styles', { forceFormData: true });
        }
    }

    return (
        <>
            <Head title={editing ? `Edit ${style.name}` : 'New style'} />

            <AdminPage
                title={editing ? `Edit ${style.name}` : 'New style'}
                lede="The number is what a client says at the desk or over WhatsApp, so keep it short and never reuse one."
                action={
                    editing && (
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `Remove ${style.name} from the gallery?`,
                                    )
                                ) {
                                    router.delete(`/admin/styles/${style.slug}`);
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
                    <FormSection title="The cut">
                        <div className="flex gap-4">
                            <TextField
                                label="Number"
                                required
                                value={form.data.code}
                                error={form.errors.code}
                                onChange={(value) => form.setData('code', value)}
                                className="w-28"
                            />
                            <TextField
                                label="Name"
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) => form.setData('name', value)}
                                placeholder="Low Fade"
                                className="flex-1"
                            />
                        </div>

                        <TextArea
                            label="Description"
                            value={form.data.description}
                            error={form.errors.description}
                            onChange={(value) => form.setData('description', value)}
                            placeholder="Fade starts low above the ear and blends up soft."
                        />

                        <SelectField
                            label="Booked as"
                            placeholder="Let the barber decide"
                            value={form.data.service_id}
                            error={form.errors.service_id}
                            onChange={(value) =>
                                form.setData(
                                    'service_id',
                                    value === '' ? '' : Number(value),
                                )
                            }
                            options={services.map((service) => ({
                                value: service.id,
                                label: service.name,
                            }))}
                        />
                    </FormSection>

                    <FormSection
                        title="Photo"
                        description="Our own photos, not stock. Up to 6MB."
                    >
                        <div className="flex items-start gap-4">
                            <div className="bg-muted flex aspect-4/5 w-32 shrink-0 items-center justify-center overflow-hidden rounded-lg">
                                {preview ? (
                                    <img
                                        src={preview}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Scissors
                                        className="text-muted-foreground/40 size-8"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>

                            <div className="flex-1 space-y-3">
                                <label className="border-input hover:border-primary flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed p-5 text-center transition-colors">
                                    <ImageUp
                                        className="text-muted-foreground size-6"
                                        aria-hidden="true"
                                    />
                                    <span className="text-sm font-medium">
                                        Choose a photo
                                    </span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="sr-only"
                                        onChange={(event) =>
                                            pickPhoto(
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </label>

                                {form.errors.photo && (
                                    <p className="text-destructive text-xs">
                                        {form.errors.photo}
                                    </p>
                                )}

                                {preview && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            form.setData('photo', null);
                                            form.setData('remove_photo', true);
                                            setPreview(null);
                                        }}
                                    >
                                        Remove photo
                                    </Button>
                                )}
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Who it suits">
                        <SelectField
                            label="Suits"
                            value={form.data.gender_tag}
                            error={form.errors.gender_tag}
                            onChange={(value) => form.setData('gender_tag', value)}
                            options={[
                                { value: 'unisex', label: 'Unisex' },
                                { value: 'men', label: 'Men' },
                                { value: 'women', label: 'Women' },
                                { value: 'kids', label: 'Kids' },
                            ]}
                        />
                        <ListField
                            label="Hair types"
                            values={form.data.hair_type_tag}
                            onChange={(values) =>
                                form.setData('hair_type_tag', values)
                            }
                            placeholder="coily"
                            hint="Used by the gallery filters"
                        />
                    </FormSection>

                    <FormSection title="Where it shows">
                        <Field label="Usual time">
                            <TextField
                                label=""
                                type="number"
                                min="5"
                                step="5"
                                value={form.data.typical_duration_minutes}
                                error={form.errors.typical_duration_minutes}
                                onChange={(value) =>
                                    form.setData(
                                        'typical_duration_minutes',
                                        value === '' ? '' : Number(value),
                                    )
                                }
                                className="w-32"
                            />
                        </Field>
                        <Toggle
                            label="In the gallery"
                            checked={form.data.is_active}
                            onChange={(value) => form.setData('is_active', value)}
                        />
                        <Toggle
                            label="Featured on the homepage"
                            checked={form.data.is_featured}
                            onChange={(value) => form.setData('is_featured', value)}
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
                        {editing ? 'Save changes' : 'Add to gallery'}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => router.get('/admin/styles')}
                    >
                        Cancel
                    </Button>
                </div>
            </AdminPage>
        </>
    );
}

StyleForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Style gallery', href: '/admin/styles' },
    ],
};
