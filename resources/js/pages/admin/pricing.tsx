import { Head, useForm } from '@inertiajs/react';
import { Check, Clock, Loader2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, Panel, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type PriceRow = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    is_priced: boolean;
    price: { formatted: string } | null;
    price_amount: number;
    duration_minutes: number;
    default_duration_minutes: number;
    is_active: boolean;
    requires_patch_test: boolean;
    is_skin_service: boolean;
};

type PricingProps = {
    branchContext: { current: Branch | null; available: Branch[] };
    categories: { id: number; name: string; slug: string; services: PriceRow[] }[];
    currency: string;
};

export default function Pricing({ branchContext, categories }: PricingProps) {
    const branch = branchContext.current;
    const unpriced = categories
        .flatMap((category) => category.services)
        .filter((service) => !service.is_priced).length;

    return (
        <>
            <Head title="Pricing" />

            <AdminPage
                title="Pricing"
                lede={
                    branch
                        ? `What ${branch.name} charges. Prices are per branch, so this grid only changes this one.`
                        : 'No branch selected.'
                }
            >
                {unpriced > 0 && (
                    <div className="flex items-center gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
                        <TriangleAlert
                            className="size-5 shrink-0 text-amber-600"
                            aria-hidden="true"
                        />
                        <p>
                            <span className="font-medium">
                                {unpriced} service{unpriced === 1 ? '' : 's'}
                            </span>{' '}
                            {unpriced === 1 ? 'has' : 'have'} no price here yet
                            and {unpriced === 1 ? 'is' : 'are'} hidden from the
                            public list.
                        </p>
                    </div>
                )}

                {categories.map((category) => (
                    <Panel key={category.slug} title={category.name}>
                        <ul className="divide-y">
                            {category.services.map((service) => (
                                <PriceEditor
                                    key={service.id}
                                    service={service}
                                />
                            ))}
                        </ul>
                    </Panel>
                ))}
            </AdminPage>
        </>
    );
}

function PriceEditor({ service }: { service: PriceRow }) {
    const [justSaved, setJustSaved] = useState(false);

    const form = useForm({
        price: service.price_amount,
        duration_minutes: service.duration_minutes,
        is_active: service.is_active,
    });

    function submit(overrides: Partial<typeof form.data> = {}) {
        form.transform((data) => ({ ...data, ...overrides }));

        form.put(`/admin/pricing/${service.slug}`, {
            preserveScroll: true,
            onSuccess: () => {
                setJustSaved(true);
                window.setTimeout(() => setJustSaved(false), 2000);
            },
        });
    }

    const dirty =
        form.data.price !== service.price_amount ||
        form.data.duration_minutes !== service.duration_minutes;

    return (
        <li className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-medium">{service.name}</h3>
                    {!service.is_priced && <Pill tone="warn">Not priced</Pill>}
                    {service.requires_patch_test && (
                        <Pill tone="neutral">Patch test</Pill>
                    )}
                    {service.is_skin_service && (
                        <Pill tone="neutral">Skin room</Pill>
                    )}
                </div>
                {service.description && (
                    <p className="text-muted-foreground mt-0.5 line-clamp-1 text-sm">
                        {service.description}
                    </p>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <label className="flex items-center gap-2">
                    <span className="text-muted-foreground text-sm">$</span>
                    <Input
                        type="number"
                        step="0.5"
                        min="0"
                        value={form.data.price}
                        onChange={(event) =>
                            form.setData('price', Number(event.target.value))
                        }
                        className="w-24 tabular-nums"
                        aria-label={`Price for ${service.name}`}
                    />
                </label>

                <label className="flex items-center gap-2">
                    <Clock
                        className="text-muted-foreground size-4"
                        aria-hidden="true"
                    />
                    <Input
                        type="number"
                        step="5"
                        min="5"
                        value={form.data.duration_minutes}
                        onChange={(event) =>
                            form.setData(
                                'duration_minutes',
                                Number(event.target.value),
                            )
                        }
                        className="w-20 tabular-nums"
                        aria-label={`Duration for ${service.name}`}
                    />
                    <span className="text-muted-foreground text-sm">min</span>
                </label>

                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) => {
                            form.setData('is_active', event.target.checked);
                            submit({ is_active: event.target.checked });
                        }}
                        className="accent-primary size-4"
                    />
                    On menu
                </label>

                <Button
                    size="sm"
                    onClick={() => submit()}
                    disabled={!dirty || form.processing}
                    className="min-w-20"
                >
                    {form.processing ? (
                        <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                    ) : justSaved ? (
                        <>
                            <Check className="size-4" aria-hidden="true" />
                            Saved
                        </>
                    ) : (
                        'Save'
                    )}
                </Button>
            </div>
        </li>
    );
}

Pricing.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pricing', href: '/admin/pricing' },
    ],
};
