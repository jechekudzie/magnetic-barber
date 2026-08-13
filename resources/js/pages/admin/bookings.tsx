import { Head, router, useForm } from '@inertiajs/react';
import { Car, MapPin, Phone, Search, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { Branch, Money } from '@/types/catalog';

type Booking = {
    id: string;
    reference: string;
    status: string;
    status_label: string;
    type: string;
    is_house_call: boolean;
    date: string | null;
    day_label: string | null;
    time_label: string | null;
    duration_minutes: number;
    client: {
        name: string | null;
        phone: string | null;
        account_number: string | null;
        visit_count: number;
    };
    staff: string | null;
    services: string[];
    total: Money;
    address: string | null;
    note: string | null;
};

type BookingsProps = {
    branchContext: { current: Branch | null; available: Branch[] };
    filters: { from: string; to: string; status: string; search: string };
    statuses: { value: string; label: string }[];
    bookings: Booking[];
    summary: {
        total: number;
        shown: number;
        truncated: boolean;
        confirmed: number;
        completed: number;
        cancelled: number;
        value: Money;
    };
};

const TONE: Record<string, 'neutral' | 'good' | 'warn' | 'gold'> = {
    pending: 'warn',
    confirmed: 'gold',
    checked_in: 'gold',
    in_progress: 'gold',
    completed: 'good',
    cancelled: 'neutral',
    no_show: 'warn',
};

export default function Bookings({
    filters,
    statuses,
    bookings,
    summary,
}: BookingsProps) {
    const form = useForm({ ...filters });

    // Bookings arrive ordered, so grouping is just a walk down the list.
    const days = bookings.reduce<Record<string, Booking[]>>((groups, booking) => {
        const key = booking.day_label ?? 'No date';
        groups[key] = [...(groups[key] ?? []), booking];

        return groups;
    }, {});

    function apply(overrides: Partial<typeof form.data> = {}) {
        router.get('/admin/bookings', { ...form.data, ...overrides }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Bookings" />

            <AdminPage
                title="Bookings"
                lede="Every appointment in the window, oldest first. Completing one earns the client their points."
            >
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="In this window" value={summary.total} accent />
                    <StatCard label="Confirmed" value={summary.confirmed} />
                    <StatCard label="Completed" value={summary.completed} />
                    <StatCard
                        label="Booked value"
                        value={summary.value.formatted}
                        hint="Quoted, not yet taken"
                    />
                </div>

                <Panel>
                    <div className="flex flex-wrap items-end gap-3 p-4">
                        <label className="flex flex-col gap-1.5">
                            <span className="text-sm font-medium">From</span>
                            <Input
                                type="date"
                                value={form.data.from}
                                onChange={(event) => {
                                    form.setData('from', event.target.value);
                                    apply({ from: event.target.value });
                                }}
                                className="w-40"
                            />
                        </label>
                        <label className="flex flex-col gap-1.5">
                            <span className="text-sm font-medium">To</span>
                            <Input
                                type="date"
                                value={form.data.to}
                                onChange={(event) => {
                                    form.setData('to', event.target.value);
                                    apply({ to: event.target.value });
                                }}
                                className="w-40"
                            />
                        </label>
                        <label className="flex flex-col gap-1.5">
                            <span className="text-sm font-medium">Status</span>
                            <select
                                value={form.data.status}
                                onChange={(event) => {
                                    form.setData('status', event.target.value);
                                    apply({ status: event.target.value });
                                }}
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option value="all">All</option>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex min-w-48 flex-1 flex-col gap-1.5">
                            <span className="text-sm font-medium">
                                Name, number or reference
                            </span>
                            <span className="relative">
                                <Search
                                    className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <Input
                                    value={form.data.search}
                                    placeholder="MB-A7K2Q or 078 187 9820"
                                    onChange={(event) =>
                                        form.setData('search', event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            apply();
                                        }
                                    }}
                                    className="pl-9"
                                />
                            </span>
                        </label>
                        <Button onClick={() => apply()}>Search</Button>
                    </div>
                </Panel>

                {summary.truncated && (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
                        <TriangleAlert
                            className="mt-0.5 size-5 shrink-0 text-amber-600"
                            aria-hidden="true"
                        />
                        <p>
                            Showing the first{' '}
                            <span className="font-medium">{summary.shown}</span>{' '}
                            of{' '}
                            <span className="font-medium">{summary.total}</span>{' '}
                            bookings in this window. Narrow the dates to see the
                            rest.
                        </p>
                    </div>
                )}

                {bookings.length === 0 ? (
                    <Panel>
                        <p className="text-muted-foreground p-8 text-center text-sm">
                            Nothing booked in that window.
                        </p>
                    </Panel>
                ) : (
                    Object.entries(days).map(([day, rows]) => (
                        <Panel key={day} title={day} description={`${rows.length} booking${rows.length === 1 ? '' : 's'}`}>
                            <ul className="divide-y">
                                {rows.map((booking) => (
                                    <BookingRow
                                        key={booking.id}
                                        booking={booking}
                                    />
                                ))}
                            </ul>
                        </Panel>
                    ))
                )}
            </AdminPage>
        </>
    );
}

function BookingRow({ booking }: { booking: Booking }) {
    const [busy, setBusy] = useState(false);

    function setStatus(status: string) {
        setBusy(true);
        router.put(
            `/admin/bookings/${booking.id}/status`,
            { status },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    }

    const done =
        booking.status === 'completed' ||
        booking.status === 'cancelled' ||
        booking.status === 'no_show';

    return (
        <li className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center">
            <div className="w-20 shrink-0">
                <p className="font-semibold tabular-nums">
                    {booking.time_label}
                </p>
                <p className="text-muted-foreground text-xs tabular-nums">
                    {booking.duration_minutes} min
                </p>
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{booking.client.name}</span>
                    <Pill tone={TONE[booking.status] ?? 'neutral'}>
                        {booking.status_label}
                    </Pill>
                    {booking.is_house_call && (
                        <Pill tone="gold">
                            <Car className="mr-1 size-3" aria-hidden="true" />
                            House call
                        </Pill>
                    )}
                    {booking.client.visit_count > 1 && (
                        <Pill tone="good">
                            {booking.client.visit_count} visits
                        </Pill>
                    )}
                </div>

                <p className="text-muted-foreground mt-1 text-sm">
                    {booking.services.join(', ')}
                    {booking.staff && ` · with ${booking.staff}`}
                </p>

                <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    <span className="font-mono">{booking.reference}</span>
                    {booking.client.account_number && (
                        <span>{booking.client.account_number}</span>
                    )}
                    {booking.client.phone && (
                        <a
                            href={`tel:${booking.client.phone}`}
                            className="hover:text-primary inline-flex items-center gap-1"
                        >
                            <Phone className="size-3" aria-hidden="true" />
                            {booking.client.phone}
                        </a>
                    )}
                    {booking.address && (
                        <span className="inline-flex items-center gap-1">
                            <MapPin className="size-3" aria-hidden="true" />
                            {booking.address}
                        </span>
                    )}
                </div>

                {booking.note && (
                    <p className="text-muted-foreground mt-1.5 text-xs italic">
                        “{booking.note}”
                    </p>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-3">
                <span className="font-semibold tabular-nums">
                    {booking.total.formatted}
                </span>

                {!done && (
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            disabled={busy}
                            onClick={() => setStatus('completed')}
                        >
                            Complete
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={busy}
                            onClick={() => setStatus('no_show')}
                        >
                            No show
                        </Button>
                    </div>
                )}
            </div>
        </li>
    );
}

Bookings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Bookings', href: '/admin/bookings' },
    ],
};
