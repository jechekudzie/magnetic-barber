import { Head, router, useForm } from '@inertiajs/react';
import { ImageUp, Loader2, User } from 'lucide-react';
import { useState } from 'react';
import {
    FormSection,
    ListField,
    TextArea,
    TextField,
    Toggle,
} from '@/components/admin/form';
import { AdminPage } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type MemberForm = {
    display_name: string;
    title: string;
    bio: string;
    specialities: string[];
    instagram_handle: string;
    accepts_house_calls: boolean;
    is_bookable: boolean;
    show_on_site: boolean;
    sort_order: number;
    photo: File | null;
    remove_photo: boolean;
};

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    member: Omit<MemberForm, 'photo' | 'remove_photo'> & {
        slug: string;
        photo_url: string | null;
    };
};

export default function TeamForm({ member }: Props) {
    const [preview, setPreview] = useState<string | null>(member.photo_url);

    const form = useForm<MemberForm>({
        display_name: member.display_name ?? '',
        title: member.title ?? '',
        bio: member.bio ?? '',
        specialities: member.specialities ?? [],
        instagram_handle: member.instagram_handle ?? '',
        accepts_house_calls: member.accepts_house_calls,
        is_bookable: member.is_bookable,
        show_on_site: member.show_on_site,
        sort_order: member.sort_order,
        photo: null,
        remove_photo: false,
    });

    return (
        <>
            <Head title={`Edit ${member.display_name}`} />

            <AdminPage
                title={member.display_name}
                lede="How this barber appears on the site, and whether clients can book them."
            >
                <div className="grid gap-4 lg:grid-cols-2">
                    <FormSection title="Profile">
                        <TextField
                            label="Display name"
                            required
                            value={form.data.display_name}
                            error={form.errors.display_name}
                            onChange={(value) =>
                                form.setData('display_name', value)
                            }
                        />
                        <TextField
                            label="Title"
                            value={form.data.title}
                            error={form.errors.title}
                            onChange={(value) => form.setData('title', value)}
                            placeholder="Senior Barber"
                        />
                        <TextArea
                            label="Bio"
                            value={form.data.bio}
                            error={form.errors.bio}
                            onChange={(value) => form.setData('bio', value)}
                            placeholder="Fades and waves. Books out first every Saturday."
                        />
                        <TextField
                            label="Instagram"
                            value={form.data.instagram_handle}
                            error={form.errors.instagram_handle}
                            onChange={(value) =>
                                form.setData('instagram_handle', value)
                            }
                            placeholder="magnetic_barbershop"
                        />
                        <ListField
                            label="Specialities"
                            values={form.data.specialities}
                            onChange={(values) =>
                                form.setData('specialities', values)
                            }
                            placeholder="Skin fades"
                        />
                    </FormSection>

                    <div className="space-y-4">
                        <FormSection
                            title="Photo"
                            description="A real photo beats an initial. Up to 6MB."
                        >
                            <div className="flex items-start gap-4">
                                <div className="bg-muted flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-full">
                                    {preview ? (
                                        <img
                                            src={preview}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <User
                                            className="text-muted-foreground/40 size-8"
                                            aria-hidden="true"
                                        />
                                    )}
                                </div>

                                <div className="flex-1 space-y-3">
                                    <label className="border-input hover:border-primary flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed p-4 text-center transition-colors">
                                        <ImageUp
                                            className="text-muted-foreground size-5"
                                            aria-hidden="true"
                                        />
                                        <span className="text-sm font-medium">
                                            Choose a photo
                                        </span>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            className="sr-only"
                                            onChange={(event) => {
                                                const file =
                                                    event.target.files?.[0] ??
                                                    null;
                                                form.setData('photo', file);
                                                form.setData(
                                                    'remove_photo',
                                                    false,
                                                );
                                                setPreview(
                                                    file
                                                        ? URL.createObjectURL(
                                                              file,
                                                          )
                                                        : member.photo_url,
                                                );
                                            }}
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
                                                form.setData(
                                                    'remove_photo',
                                                    true,
                                                );
                                                setPreview(null);
                                            }}
                                        >
                                            Remove photo
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </FormSection>

                        <FormSection title="Booking and visibility">
                            <Toggle
                                label="Clients can book them"
                                hint="Off keeps them off the barber picker"
                                checked={form.data.is_bookable}
                                onChange={(value) =>
                                    form.setData('is_bookable', value)
                                }
                            />
                            <Toggle
                                label="Show on the public team page"
                                checked={form.data.show_on_site}
                                onChange={(value) =>
                                    form.setData('show_on_site', value)
                                }
                            />
                            <Toggle
                                label="Takes house calls"
                                hint="Only these barbers are offered for a house call"
                                checked={form.data.accepts_house_calls}
                                onChange={(value) =>
                                    form.setData('accepts_house_calls', value)
                                }
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
                </div>

                <div className="flex gap-3">
                    <Button
                        onClick={() =>
                            form.post(`/admin/team/${member.slug}`, {
                                forceFormData: true,
                            })
                        }
                        disabled={form.processing}
                    >
                        {form.processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        Save changes
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => router.get('/admin/team')}
                    >
                        Cancel
                    </Button>
                </div>
            </AdminPage>
        </>
    );
}

TeamForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Team', href: '/admin/team' },
    ],
};
