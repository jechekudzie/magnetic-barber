import { Head, useForm } from '@inertiajs/react';
import { Car, Check, Clock, Loader2, MapPin } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, Panel, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dayName } from '@/lib/hours';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type Settings = {
    opens_at: string;
    closes_at: string;
    days_open: number[];
    house_call_enabled: boolean;
    house_call_opens_at: string;
    house_call_closes_at: string;
    house_call_days_open: number[];
    house_call_radius_km: number | null;
    house_call_fee: number;
};

type AdminBranch = Branch & { settings: Settings };

// Monday first: nobody reads a shop rota starting on Sunday.
const WEEK = [1, 2, 3, 4, 5, 6, 0];

export default function Branches({
    branchContext,
    branches,
}: {
    branchContext: { current: Branch | null; available: Branch[] };
    branches: AdminBranch[];
}) {
    return (
        <>
            <Head title="Branches" />

            <AdminPage
                title="Branches"
                lede="Opening hours drive the booking calendar. Whatever you set here is what a client can pick."
            >
                {branches.map((branch) => (
                    <BranchHours
                        key={branch.slug}
                        branch={branch}
                        isCurrent={branchContext.current?.slug === branch.slug}
                    />
                ))}
            </AdminPage>
        </>
    );
}

function BranchHours({
    branch,
    isCurrent,
}: {
    branch: AdminBranch;
    isCurrent: boolean;
}) {
    const [justSaved, setJustSaved] = useState(false);
    const form = useForm<Settings>({ ...branch.settings });

    function toggleDay(field: 'days_open' | 'house_call_days_open', day: number) {
        const current = form.data[field];

        form.setData(
            field,
            current.includes(day)
                ? current.filter((value) => value !== day)
                : [...current, day].sort((a, b) => a - b),
        );
    }

    function submit() {
        form.put(`/admin/branches/${branch.slug}/hours`, {
            preserveScroll: true,
            onSuccess: () => {
                setJustSaved(true);
                window.setTimeout(() => setJustSaved(false), 2000);
            },
        });
    }

    return (
        <Panel
            title={branch.name}
            description={`${branch.address.line ?? ''} · account prefix ${branch.code}`}
        >
            <div className="space-y-8 p-5">
                {isCurrent && <Pill tone="gold">Current branch</Pill>}

                {/* Shop floor -------------------------------------------- */}
                <section>
                    <h3 className="mb-4 flex items-center gap-2 font-semibold">
                        <Clock
                            className="text-primary size-4"
                            aria-hidden="true"
                        />
                        Shop hours
                    </h3>

                    <div className="flex flex-wrap items-end gap-4">
                        <TimeField
                            id={`${branch.slug}-opens`}
                            label="Opens"
                            value={form.data.opens_at}
                            error={form.errors.opens_at}
                            onChange={(value) => form.setData('opens_at', value)}
                        />
                        <TimeField
                            id={`${branch.slug}-closes`}
                            label="Closes"
                            value={form.data.closes_at}
                            error={form.errors.closes_at}
                            onChange={(value) =>
                                form.setData('closes_at', value)
                            }
                        />
                    </div>

                    <DayPicker
                        legend="Trading days"
                        selected={form.data.days_open}
                        onToggle={(day) => toggleDay('days_open', day)}
                    />
                </section>

                {/* House calls ------------------------------------------- */}
                <section className="border-t pt-6">
                    <h3 className="mb-1 flex items-center gap-2 font-semibold">
                        <Car className="text-primary size-4" aria-hidden="true" />
                        House calls
                    </h3>
                    <p className="text-muted-foreground mb-4 text-sm">
                        Usually a narrower window than the shop, because the
                        barber has to travel there and back.
                    </p>

                    <label className="mb-5 flex cursor-pointer items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.house_call_enabled}
                            onChange={(event) =>
                                form.setData(
                                    'house_call_enabled',
                                    event.target.checked,
                                )
                            }
                            className="accent-primary size-4"
                        />
                        This branch does house calls
                    </label>

                    <div
                        className={cn(
                            'space-y-5',
                            !form.data.house_call_enabled &&
                                'pointer-events-none opacity-50',
                        )}
                    >
                        <div className="flex flex-wrap items-end gap-4">
                            <TimeField
                                id={`${branch.slug}-hc-opens`}
                                label="First job"
                                value={form.data.house_call_opens_at}
                                error={form.errors.house_call_opens_at}
                                hint="Blank means same as the shop"
                                onChange={(value) =>
                                    form.setData('house_call_opens_at', value)
                                }
                            />
                            <TimeField
                                id={`${branch.slug}-hc-closes`}
                                label="Last job starts"
                                value={form.data.house_call_closes_at}
                                error={form.errors.house_call_closes_at}
                                hint="Blank means same as the shop"
                                onChange={(value) =>
                                    form.setData('house_call_closes_at', value)
                                }
                            />

                            <label className="flex flex-col gap-1.5">
                                <span className="text-sm font-medium">
                                    Travel up to
                                </span>
                                <span className="flex items-center gap-2">
                                    <Input
                                        type="number"
                                        min="1"
                                        max="200"
                                        value={
                                            form.data.house_call_radius_km ?? ''
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'house_call_radius_km',
                                                event.target.value === ''
                                                    ? null
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                            )
                                        }
                                        className="w-24 tabular-nums"
                                    />
                                    <span className="text-muted-foreground text-sm">
                                        km
                                    </span>
                                </span>
                            </label>

                            <label className="flex flex-col gap-1.5">
                                <span className="text-sm font-medium">
                                    Travel fee
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="text-muted-foreground text-sm">
                                        $
                                    </span>
                                    <Input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        value={form.data.house_call_fee}
                                        onChange={(event) =>
                                            form.setData(
                                                'house_call_fee',
                                                Number(event.target.value),
                                            )
                                        }
                                        className="w-24 tabular-nums"
                                    />
                                </span>
                            </label>
                        </div>

                        <DayPicker
                            legend="House call days"
                            selected={form.data.house_call_days_open}
                            onToggle={(day) =>
                                toggleDay('house_call_days_open', day)
                            }
                        />
                    </div>
                </section>

                <div className="flex items-center gap-3 border-t pt-5">
                    <Button
                        onClick={submit}
                        disabled={!form.isDirty || form.processing}
                    >
                        {form.processing ? (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        ) : justSaved ? (
                            <>
                                <Check className="size-4" aria-hidden="true" />
                                Saved
                            </>
                        ) : (
                            'Save hours'
                        )}
                    </Button>

                    <p className="text-muted-foreground flex items-center gap-1.5 text-sm">
                        <MapPin className="size-3.5" aria-hidden="true" />
                        {branch.address.area}
                    </p>
                </div>
            </div>
        </Panel>
    );
}

function TimeField({
    id,
    label,
    value,
    error,
    hint,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    hint?: string;
    onChange: (value: string) => void;
}) {
    return (
        <label htmlFor={id} className="flex flex-col gap-1.5">
            <span className="text-sm font-medium">{label}</span>
            <Input
                id={id}
                type="time"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="w-32"
            />
            {error ? (
                <span className="text-destructive text-xs">{error}</span>
            ) : (
                hint && (
                    <span className="text-muted-foreground text-xs">
                        {hint}
                    </span>
                )
            )}
        </label>
    );
}

function DayPicker({
    legend,
    selected,
    onToggle,
}: {
    legend: string;
    selected: number[];
    onToggle: (day: number) => void;
}) {
    return (
        <fieldset className="mt-5">
            <legend className="mb-2 text-sm font-medium">{legend}</legend>
            <div className="flex flex-wrap gap-2">
                {WEEK.map((day) => {
                    const on = selected.includes(day);

                    return (
                        <button
                            key={day}
                            type="button"
                            onClick={() => onToggle(day)}
                            aria-pressed={on}
                            className={cn(
                                'rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
                                on
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:border-primary/50',
                            )}
                        >
                            {dayName(day).slice(0, 3)}
                        </button>
                    );
                })}
            </div>
        </fieldset>
    );
}

Branches.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Branches', href: '/admin/branches' },
    ],
};
