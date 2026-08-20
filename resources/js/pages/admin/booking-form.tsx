import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    Gem,
    Clock,
    Loader2,
    Scissors,
    TriangleAlert,
    UserPlus,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    FormSection,
    SelectField,
    TextArea,
    TextField,
} from '@/components/admin/form';
import { AdminPage, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Branch, ServiceCategory, Style } from '@/types/catalog';

type Barber = { id: string; name: string; title: string | null };

type FoundClient = {
    id: string;
    name: string;
    phone: string | null;
    account_number: string | null;
    visit_count: number;
    last_visit: string | null;
    loyalty: {
        points: number;
        redeemable: boolean;
        threshold: number;
        value: { cents: number; formatted: string };
        reward: { cents: number; formatted: string };
        to_next: number;
    };
};

type Slot = { start: string; label: string };

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    categories: ServiceCategory[];
    styles: Style[];
    barbers: Barber[];
    prefill: { date: string; time: string | null; staff: string | null };
};

const QUICK_DATES = [
    { label: 'Today', days: 0 },
    { label: 'Tomorrow', days: 1 },
    { label: 'In a week', days: 7 },
];

/** Plain date maths on a Y-m-d string, in the branch's day, not the browser's. */
function shiftDate(from: string, days: number): string {
    const date = new Date(`${from}T12:00:00`);
    date.setDate(date.getDate() + days);

    return date.toISOString().slice(0, 10);
}

export default function BookingForm({
    branchContext,
    categories,
    styles,
    barbers,
    prefill,
}: Props) {
    const branch = branchContext.current;

    const [selected, setSelected] = useState<string[]>([]);
    const [found, setFound] = useState<FoundClient[]>([]);
    const [picked, setPicked] = useState<FoundClient | null>(null);
    const [searching, setSearching] = useState(false);
    const [slots, setSlots] = useState<Slot[]>([]);
    const searchTimer = useRef<number | null>(null);
    const clientSearch = useRef<AbortController | null>(null);

    const form = useForm({
        client: '',
        name: '',
        phone: '',
        service_ids: [] as string[],
        style: '',
        staff: prefill.staff ?? 'any',
        date: prefill.date,
        time: prefill.time ?? '',
        note: '',
        redeem_points: false as boolean,
    });

    /**
     * Half a number is not "nobody on file", it is half a number. Only say so
     * once there is enough typed to have found somebody.
     */
    const worthJudging =
        form.data.name.trim().length >= 3 ||
        form.data.phone.replace(/\D/g, '').replace(/^0+/, '').length >= 3;

    const chosen = useMemo(
        () =>
            categories
                .flatMap((category) => category.services ?? [])
                .filter((service) => selected.includes(service.id)),
        [categories, selected],
    );

    const totalCents = chosen.reduce(
        (sum, service) => sum + (service.price?.cents ?? 0),
        0,
    );
    const totalMinutes = chosen.reduce(
        (sum, service) => sum + service.duration_minutes,
        0,
    );

    /**
     * What their points take off this particular bill. Mirrors the server,
     * which stays the authority on what is actually spent: whole rewards only,
     * and never more than the bill, because points buy a cut, not credit.
     */
    const discountCents = useMemo(() => {
        const loyalty = picked?.loyalty;

        if (!loyalty || loyalty.reward.cents < 1) {
            return 0;
        }

        const blocks = Math.min(
            Math.floor(loyalty.points / Math.max(1, loyalty.threshold)),
            Math.floor(totalCents / loyalty.reward.cents),
        );

        return Math.max(0, blocks) * loyalty.reward.cents;
    }, [picked, totalCents]);

    const redeeming = form.data.redeem_points && discountCents > 0;
    const dueCents = totalCents - (redeeming ? discountCents : 0);

    /* --------------------------------------------------------- free times */

    const slotKey = `${form.data.date}|${form.data.staff}|${[...selected].sort().join(',')}`;

    useEffect(() => {
        if (form.data.date === '') {
            return;
        }

        const controller = new AbortController();
        let cancelled = false;

        async function load() {
            const params = new URLSearchParams({
                date: form.data.date,
                staff: form.data.staff,
            });
            selected.forEach((id) => params.append('service_ids[]', id));

            try {
                const response = await fetch(
                    `/admin/bookings/availability?${params.toString()}`,
                    {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    },
                );

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                if (!cancelled) {
                    setSlots(
                        form.data.staff === 'any'
                            ? (data.any_staff ?? [])
                            : (data.staff?.find(
                                  (row: { id: string }) =>
                                      row.id === form.data.staff,
                              )?.slots ?? []),
                    );
                }
            } catch {
                // An aborted request is expected when answers change fast.
            }
        }

        load();

        return () => {
            cancelled = true;
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [slotKey]);

    /* ------------------------------------------------------ client lookup */

    function searchClients(value: string) {
        setPicked(null);
        form.setData('client', '');

        if (searchTimer.current !== null) {
            window.clearTimeout(searchTimer.current);
        }

        clientSearch.current?.abort();

        if (value.trim().length < 2) {
            setFound([]);
            setSearching(false);

            return;
        }

        setSearching(true);

        // Waits for a pause in typing rather than a request per keystroke.
        searchTimer.current = window.setTimeout(async () => {
            const controller = new AbortController();
            clientSearch.current = controller;

            try {
                const response = await fetch(
                    `/admin/bookings/clients?q=${encodeURIComponent(value)}`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    setFound((await response.json()).data ?? []);
                }
            } catch {
                // An aborted request is expected when typing continues.
                return;
            } finally {
                if (!controller.signal.aborted) {
                    setSearching(false);
                }
            }
        }, 300);
    }

    function choose(client: FoundClient) {
        setPicked(client);
        setFound([]);
        setSearching(false);
        form.setData('client', client.id);
        form.setData('name', client.name);
        form.setData('phone', client.phone ?? '');
        form.setData('redeem_points', false);
    }

    function forgetClient() {
        setPicked(null);
        setFound([]);
        form.setData('client', '');
        form.setData('name', '');
        form.setData('phone', '');
        form.setData('redeem_points', false);
    }

    function submit() {
        form.transform((data) => ({ ...data, service_ids: selected }));

        form.post('/admin/bookings');
    }

    const ready =
        selected.length > 0 &&
        form.data.time !== '' &&
        (picked !== null ||
            (form.data.name.trim().length > 1 &&
                form.data.phone.trim().length > 5));

    if (!branch) {
        return (
            <AdminPage title="New booking">
                <p className="text-muted-foreground">No branch selected.</p>
            </AdminPage>
        );
    }

    return (
        <>
            <Head title="New booking" />

            <AdminPage
                title="New booking"
                lede={`Taking a booking at the desk for ${branch.name}.`}
            >
                <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
                    <div className="space-y-4">
                        {/* ------------------------------------ when */}
                        <FormSection
                            title="When"
                            description="Set the day and time first. Reception is usually told when before they are told what."
                        >
                            <div className="flex flex-wrap items-end gap-3">
                                <TextField
                                    label="Date"
                                    required
                                    type="date"
                                    value={form.data.date}
                                    error={form.errors.date}
                                    onChange={(value) =>
                                        form.setData('date', value)
                                    }
                                    className="w-44"
                                />
                                <TextField
                                    label="Time"
                                    required
                                    type="time"
                                    value={form.data.time}
                                    error={form.errors.time}
                                    onChange={(value) =>
                                        form.setData('time', value)
                                    }
                                    className="w-36"
                                />
                                <SelectField
                                    label="Barber"
                                    value={form.data.staff}
                                    onChange={(value) =>
                                        form.setData('staff', value)
                                    }
                                    options={[
                                        { value: 'any', label: 'Any barber' },
                                        ...barbers.map((barber) => ({
                                            value: barber.id,
                                            label: barber.name,
                                        })),
                                    ]}
                                />
                                <div className="flex flex-wrap gap-1.5 pb-1">
                                    {QUICK_DATES.map((quick) => (
                                        <button
                                            key={quick.label}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'date',
                                                    shiftDate(
                                                        prefill.date,
                                                        quick.days,
                                                    ),
                                                )
                                            }
                                            className={cn(
                                                'rounded-md border px-2.5 py-1.5 text-sm transition-colors',
                                                form.data.date ===
                                                    shiftDate(
                                                        prefill.date,
                                                        quick.days,
                                                    )
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'hover:border-primary/50',
                                            )}
                                        >
                                            {quick.label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Suggestions, not a gate. A manager may book a
                                time the public grid would never offer. */}
                            {selected.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Type a time above, or pick a cut below and
                                    the free times appear here.
                                </p>
                            ) : slots.length === 0 ? (
                                <div className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm">
                                    <TriangleAlert
                                        className="mt-0.5 size-4 shrink-0 text-amber-600"
                                        aria-hidden="true"
                                    />
                                    <p>
                                        Nothing free that day for that barber.
                                        The time above still stands, and will be
                                        refused only if it truly clashes.
                                    </p>
                                </div>
                            ) : (
                                <div className="flex flex-wrap gap-1.5">
                                    {slots.map((slot) => (
                                        <button
                                            key={slot.start}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'time',
                                                    to24Hour(slot.label),
                                                )
                                            }
                                            className={cn(
                                                'rounded-md border px-2.5 py-1.5 text-sm tabular-nums transition-colors',
                                                form.data.time ===
                                                    to24Hour(slot.label)
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'hover:border-primary/50',
                                            )}
                                        >
                                            {slot.label}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </FormSection>

                        {/* ------------------------------------- who */}
                        <FormSection
                            title="Who is it for"
                            description="Start typing a name or number. Anyone who has been in before comes up as you type."
                        >
                            {picked && (
                                <div className="border-primary/50 bg-primary/5 flex items-start gap-3 rounded-lg border p-3">
                                    <Check
                                        className="text-primary mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <div className="flex-1 text-sm">
                                        <p className="font-medium">
                                            {picked.name}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {picked.phone} ·{' '}
                                            {picked.account_number} ·{' '}
                                            {picked.visit_count} visits
                                            {picked.last_visit &&
                                                ` · last in ${picked.last_visit}`}
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={forgetClient}
                                    >
                                        Change
                                    </Button>
                                </div>
                            )}

                            {/* A regular should not have to know they have a
                                reward waiting. The desk tells them. */}
                            {picked && picked.loyalty.redeemable && (
                                <div className="rounded-lg border border-amber-500/50 bg-amber-500/5 p-3">
                                    <div className="flex items-start gap-3">
                                        <Gem
                                            className="mt-0.5 size-4 shrink-0 text-amber-600"
                                            aria-hidden="true"
                                        />
                                        <div className="flex-1 text-sm">
                                            <p className="font-medium">
                                                {picked.loyalty.points} points
                                                saved up
                                                {discountCents > 0
                                                    ? ` · worth $${(discountCents / 100).toFixed(2)} off this booking`
                                                    : ' · worth a reward on a bigger booking'}
                                            </p>
                                            {discountCents === 0 && (
                                                <p className="text-muted-foreground mt-0.5">
                                                    A reward is{' '}
                                                    {
                                                        picked.loyalty.reward
                                                            .formatted
                                                    }
                                                    , so add a service worth at
                                                    least that to use one.
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {discountCents > 0 && (
                                        <label className="mt-3 flex cursor-pointer items-center gap-2 text-sm font-medium">
                                            <input
                                                type="checkbox"
                                                checked={form.data.redeem_points}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'redeem_points',
                                                        event.target.checked,
                                                    )
                                                }
                                                className="accent-primary size-4"
                                            />
                                            Take $
                                            {(discountCents / 100).toFixed(2)}{' '}
                                            off with their points
                                        </label>
                                    )}
                                </div>
                            )}

                            {picked && !picked.loyalty.redeemable && (
                                <p className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                    <Gem className="size-4" aria-hidden="true" />
                                    {picked.loyalty.points} points ·{' '}
                                    {picked.loyalty.to_next} more for their next
                                    reward.
                                </p>
                            )}

                            {!picked && (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <TextField
                                            label="Name"
                                            required
                                            value={form.data.name}
                                            error={form.errors.name}
                                            onChange={(value) => {
                                                form.setData('name', value);
                                                searchClients(value);
                                            }}
                                            placeholder="Tendai Moyo"
                                        />
                                        <TextField
                                            label="Mobile"
                                            required
                                            value={form.data.phone}
                                            error={form.errors.phone}
                                            onChange={(value) => {
                                                form.setData('phone', value);
                                                searchClients(value);
                                            }}
                                            placeholder="078 187 9820"
                                        />
                                    </div>

                                    {/* Whatever reception types, matches land
                                        here. Silence would read as broken, so
                                        searching and no-match both say so. */}
                                    {searching && (
                                        <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                            <Loader2
                                                className="size-3.5 animate-spin"
                                                aria-hidden="true"
                                            />
                                            Looking them up
                                        </p>
                                    )}

                                    {!searching && found.length > 0 && (
                                        <div className="space-y-1.5">
                                            <p className="text-muted-foreground text-sm">
                                                {found.length === 1
                                                    ? 'One match. Tap to use their details.'
                                                    : `${found.length} matches. Tap to use their details.`}
                                            </p>
                                            <ul className="divide-y rounded-lg border">
                                                {found.map((client) => (
                                                    <li key={client.id}>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                choose(client)
                                                            }
                                                            className="hover:bg-muted/60 flex w-full items-center justify-between gap-3 p-3 text-left text-sm transition-colors"
                                                        >
                                                            <span>
                                                                <span className="block font-medium">
                                                                    {client.name}
                                                                </span>
                                                                <span className="text-muted-foreground block text-xs tabular-nums">
                                                                    {client.phone}{' '}
                                                                    ·{' '}
                                                                    {
                                                                        client.account_number
                                                                    }
                                                                </span>
                                                            </span>
                                                            <span className="text-muted-foreground shrink-0 text-xs">
                                                                {
                                                                    client.visit_count
                                                                }{' '}
                                                                visits
                                                            </span>
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}

                                    {!searching && found.length === 0 && worthJudging && (
                                            <p className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                <UserPlus
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Nobody on file. This books a new
                                                client and opens their account.
                                            </p>
                                        )}
                                </>
                            )}
                        </FormSection>

                        {/* --------------------------------- services */}
                        <FormSection
                            title="What they are having"
                            description="Prices are this branch's."
                        >
                            {categories.map((category) => (
                                <div key={category.slug}>
                                    <p className="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">
                                        {category.name}
                                    </p>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {(category.services ?? []).map(
                                            (service) => {
                                                const on = selected.includes(
                                                    service.id,
                                                );

                                                return (
                                                    <button
                                                        key={service.id}
                                                        type="button"
                                                        onClick={() =>
                                                            setSelected(
                                                                (current) =>
                                                                    on
                                                                        ? current.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  service.id,
                                                                          )
                                                                        : [
                                                                              ...current,
                                                                              service.id,
                                                                          ],
                                                            )
                                                        }
                                                        className={cn(
                                                            'flex items-center justify-between gap-2 rounded-lg border p-2.5 text-left text-sm transition-colors',
                                                            on
                                                                ? 'border-primary bg-primary/10'
                                                                : 'hover:border-primary/40',
                                                        )}
                                                    >
                                                        <span className="min-w-0">
                                                            <span className="block truncate font-medium">
                                                                {service.name}
                                                            </span>
                                                            <span className="text-muted-foreground text-xs">
                                                                {
                                                                    service.duration_minutes
                                                                }{' '}
                                                                min
                                                            </span>
                                                        </span>
                                                        <span className="shrink-0 font-semibold tabular-nums">
                                                            {service.price
                                                                ?.formatted ??
                                                                '—'}
                                                        </span>
                                                    </button>
                                                );
                                            },
                                        )}
                                    </div>
                                </div>
                            ))}
                            {form.errors.service_ids && (
                                <p className="text-destructive text-xs">
                                    {form.errors.service_ids}
                                </p>
                            )}
                        </FormSection>

                        {/* ------------------------------------- cut */}
                        <FormSection
                            title="The cut"
                            description="Pick from the gallery so the barber knows exactly what was asked for."
                        >
                            <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                <button
                                    type="button"
                                    onClick={() => form.setData('style', '')}
                                    className={cn(
                                        'rounded-lg border p-2.5 text-left text-sm transition-colors',
                                        form.data.style === ''
                                            ? 'border-primary bg-primary/10'
                                            : 'hover:border-primary/40',
                                    )}
                                >
                                    <Scissors
                                        className="text-muted-foreground mb-1 size-4"
                                        aria-hidden="true"
                                    />
                                    <span className="block font-medium">
                                        Barber advises
                                    </span>
                                </button>

                                {styles.map((style) => (
                                    <button
                                        key={style.id}
                                        type="button"
                                        onClick={() =>
                                            form.setData('style', style.id)
                                        }
                                        className={cn(
                                            'overflow-hidden rounded-lg border text-left transition-colors',
                                            form.data.style === style.id
                                                ? 'border-primary ring-primary/40 ring-2'
                                                : 'hover:border-primary/40',
                                        )}
                                    >
                                        <span className="bg-muted flex aspect-4/3 items-center justify-center overflow-hidden">
                                            {style.image_url ? (
                                                <img
                                                    src={style.image_url}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <Scissors
                                                    className="text-muted-foreground/40 size-6"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </span>
                                        <span className="block p-2">
                                            <span className="text-muted-foreground block text-xs tabular-nums">
                                                {style.code}
                                            </span>
                                            <span className="block truncate text-sm font-medium">
                                                {style.name}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </FormSection>

                        {/* ------------------------------------ when */}
                        <FormSection title="Anything else">
                            <TextArea
                                label="Note for the barber"
                                value={form.data.note}
                                error={form.errors.note}
                                onChange={(value) => form.setData('note', value)}
                                placeholder="Grade 1 on the sides, leave the top"
                            />
                        </FormSection>
                    </div>

                    {/* ------------------------------------- summary */}
                    <aside className="lg:sticky lg:top-6 lg:self-start">
                        <div className="bg-card rounded-xl border p-5">
                            <h2 className="mb-3 font-semibold">This booking</h2>

                            {chosen.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nothing picked yet.
                                </p>
                            ) : (
                                <ul className="space-y-1.5 text-sm">
                                    {chosen.map((service) => (
                                        <li
                                            key={service.id}
                                            className="flex justify-between gap-2"
                                        >
                                            <span className="truncate">
                                                {service.name}
                                            </span>
                                            <span className="text-muted-foreground shrink-0 tabular-nums">
                                                {service.price?.formatted}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <dl className="mt-4 space-y-1.5 border-t pt-4 text-sm">
                                <Line label="Client">
                                    {picked?.name ??
                                        (form.data.name || 'Not set')}
                                </Line>
                                <Line label="Barber">
                                    {form.data.staff === 'any'
                                        ? 'Any barber'
                                        : (barbers.find(
                                              (b) => b.id === form.data.staff,
                                          )?.name ?? 'Any')}
                                </Line>
                                <Line label="Time">
                                    {form.data.time || 'Not set'}
                                </Line>
                                {form.data.style !== '' && (
                                    <Line label="Cut">
                                        {styles.find(
                                            (s) => s.id === form.data.style,
                                        )?.name ?? ''}
                                    </Line>
                                )}
                            </dl>

                            {redeeming && (
                                <dl className="mt-4 space-y-1.5 border-t pt-4 text-sm">
                                    <Line label="Services">
                                        ${(totalCents / 100).toFixed(2)}
                                    </Line>
                                    <div className="flex justify-between gap-2 text-amber-700 dark:text-amber-400">
                                        <dt>
                                            Points ({picked?.loyalty.threshold}{' '}
                                            used)
                                        </dt>
                                        <dd className="tabular-nums">
                                            −${(discountCents / 100).toFixed(2)}
                                        </dd>
                                    </div>
                                </dl>
                            )}

                            <div className="mt-4 flex items-baseline justify-between border-t pt-4">
                                <span className="text-sm font-medium">
                                    {redeeming ? 'To pay' : 'Total'}
                                </span>
                                <span className="text-xl font-bold tabular-nums">
                                    ${(dueCents / 100).toFixed(2)}
                                </span>
                            </div>
                            {totalMinutes > 0 && (
                                <p className="text-muted-foreground mt-1 flex items-center gap-1.5 text-sm">
                                    <Clock className="size-3.5" aria-hidden="true" />
                                    {totalMinutes} minutes
                                </p>
                            )}

                            <Button
                                className="mt-5 w-full"
                                disabled={!ready || form.processing}
                                onClick={submit}
                            >
                                {form.processing && (
                                    <Loader2
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                )}
                                Take the booking
                            </Button>
                            <Button
                                variant="outline"
                                className="mt-2 w-full"
                                onClick={() => router.get('/admin/bookings')}
                            >
                                Cancel
                            </Button>

                            {picked && (
                                <Pill tone="good">
                                    Returning client, points will apply
                                </Pill>
                            )}
                        </div>
                    </aside>
                </div>
            </AdminPage>
        </>
    );
}

function Line({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="truncate text-right">{children}</dd>
        </div>
    );
}

/** "2:30pm" as the 24 hour value a time input and the server both expect. */
function to24Hour(label: string): string {
    const match = label.match(/^(\d{1,2})(?::(\d{2}))?(am|pm)$/i);

    if (!match) {
        return '';
    }

    let hour = Number(match[1]) % 12;

    if (match[3].toLowerCase() === 'pm') {
        hour += 12;
    }

    return `${String(hour).padStart(2, '0')}:${match[2] ?? '00'}`;
}

BookingForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Bookings', href: '/admin/bookings' },
    ],
};
