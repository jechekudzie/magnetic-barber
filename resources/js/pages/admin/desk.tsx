import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    CircleCheck,
    Clock,
    Loader2,
    Play,
    Scissors,
    Search,
    SquareCheck,
    UserRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';

import type { Money } from '@/types/catalog';

type Barber = {
    id: string;
    name: string;
    title: string | null;
    /** The booking reference they are mid-cut on, if any. */
    in_chair: string | null;
};

type Booking = {
    id: string;
    reference: string;
    status: string;
    status_label: string;
    is_house_call: boolean;
    branch_name: string | null;
    date: string | null;
    day_label: string | null;
    time_label: string | null;
    duration_minutes: number;
    staff_id: string | null;
    staff: string | null;
    client: {
        name: string | null;
        phone: string | null;
        account_number: string | null;
        visit_count: number;
    };
    services: string[];
    total: Money;
    paid: Money;
    outstanding: Money;
    is_secured: boolean;
    is_paid_in_full: boolean;
    started_at: string | null;
    minutes_so_far: number | null;
    note: string | null;
    can_assign: boolean;
    assigned_by: string | null;
};

/**
 * One person at the desk, and everything the receptionist does next.
 *
 * Laid out as one row per visit rather than a table, because the three
 * actions are not the same width as a cell: choosing a chair is a dropdown,
 * starting is one tap, and the money is a link to the till.
 */
function DeskRow({
    booking,
    barbers,
    canAssign,
}: {
    booking: Booking;
    barbers: Barber[];
    canAssign: boolean;
}) {
    const [busy, setBusy] = useState<string | null>(null);

    const running = booking.started_at !== null && booking.status !== 'completed';
    const done = booking.status === 'completed';

    function act(url: string, key: string) {
        setBusy(key);
        router.put(
            url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusy(null),
            },
        );
    }

    function assign(staffId: string) {
        if (staffId === '' || staffId === booking.staff_id) {
            return;
        }

        setBusy('assign');
        router.put(
            `/admin/desk/${booking.id}/barber`,
            { staff_id: staffId },
            { preserveScroll: true, onFinish: () => setBusy(null) },
        );
    }

    return (
        <div className="border-border/60 flex flex-col gap-4 border-b px-5 py-5 last:border-b-0 xl:flex-row xl:items-start">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-semibold">
                        {booking.client.name ?? 'Walk-in'}
                    </span>
                    <Pill tone="gold">{booking.reference}</Pill>
                    <Pill tone={done ? 'good' : running ? 'warn' : 'neutral'}>
                        {booking.status_label}
                    </Pill>
                    {booking.is_house_call && <Pill>House call</Pill>}
                    {booking.client.visit_count > 0 && (
                        <Pill>
                            {booking.client.visit_count} visit
                            {booking.client.visit_count === 1 ? '' : 's'}
                        </Pill>
                    )}
                </div>

                <p className="text-muted-foreground mt-1.5 text-sm">
                    {booking.day_label} at {booking.time_label} ·{' '}
                    {booking.services.join(', ') || 'No services'} ·{' '}
                    {booking.duration_minutes} min
                </p>

                <p className="text-muted-foreground mt-1 text-sm">
                    {booking.client.phone ?? 'No number'}
                    {booking.client.account_number
                        ? ` · ${booking.client.account_number}`
                        : ''}
                    {booking.branch_name ? ` · ${booking.branch_name}` : ''}
                </p>

                {booking.note && (
                    <p className="mt-2 text-sm italic">“{booking.note}”</p>
                )}

                {/* The clock, while it is running. A barber mid-cut is the
                    reason the desk cannot give this client to them. */}
                {running && booking.minutes_so_far !== null && (
                    <p className="text-primary mt-2 inline-flex items-center gap-1.5 text-sm font-medium">
                        <Clock className="size-3.5" aria-hidden="true" />
                        In the chair {booking.minutes_so_far} min
                    </p>
                )}

                {booking.assigned_by && (
                    <p className="text-muted-foreground mt-2 text-xs">
                        Chair changed by {booking.assigned_by}
                    </p>
                )}
            </div>

            <div className="flex shrink-0 flex-col gap-3 xl:w-80">
                <div className="flex items-baseline justify-between gap-3">
                    <span className="text-sm font-semibold tabular-nums">
                        {booking.total.formatted}
                    </span>
                    {booking.is_paid_in_full ? (
                        <Pill tone="good">Paid</Pill>
                    ) : (
                        <Pill tone="warn">
                            {booking.outstanding.formatted} owing
                        </Pill>
                    )}
                </div>

                {/* Who is cutting. A barber with a live clock is marked
                    busy whatever the diary says about their next slot. */}
                {canAssign && booking.can_assign && !done ? (
                    <label className="block">
                        <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            Barber
                        </span>
                        <select
                            value={booking.staff_id ?? ''}
                            disabled={busy === 'assign'}
                            onChange={(event) => assign(event.target.value)}
                            className="border-input focus-visible:border-ring focus-visible:ring-ring/50 mt-1 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:opacity-50"
                        >
                            {barbers.map((barber) => (
                                <option key={barber.id} value={barber.id}>
                                    {barber.name}
                                    {barber.in_chair &&
                                    barber.in_chair !== booking.reference
                                        ? ' — mid-cut'
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                ) : (
                    <p className="inline-flex items-center gap-1.5 text-sm">
                        <UserRound
                            className="text-muted-foreground size-3.5"
                            aria-hidden="true"
                        />
                        {booking.staff ?? 'No barber yet'}
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    {!running && !done && (
                        <Button
                            size="sm"
                            disabled={busy !== null}
                            onClick={() =>
                                act(
                                    `/admin/bookings/${booking.id}/start`,
                                    'start',
                                )
                            }
                        >
                            {busy === 'start' ? (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            ) : (
                                <Play className="size-4" aria-hidden="true" />
                            )}
                            Start the cut
                        </Button>
                    )}

                    {running && (
                        <Button
                            size="sm"
                            disabled={busy !== null}
                            onClick={() =>
                                act(
                                    `/admin/bookings/${booking.id}/finish`,
                                    'finish',
                                )
                            }
                        >
                            {busy === 'finish' ? (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            ) : (
                                <SquareCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            )}
                            Finish
                        </Button>
                    )}

                    {!booking.is_paid_in_full && (
                        <Button variant="outline" size="sm" asChild>
                            <a href="/admin/till">
                                <Banknote className="size-4" aria-hidden="true" />
                                Take payment
                            </a>
                        </Button>
                    )}

                    {done && (
                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-sm">
                            <CircleCheck className="size-4" aria-hidden="true" />
                            Done in {booking.duration_minutes} min
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * The front desk.
 *
 * Somebody walks in. The desk finds them by number, name, or the reference
 * off their phone, puts them in a chair, and starts the clock. The diary
 * cannot do this: a client who booked Thursday and turned up Wednesday is
 * invisible on a day grid, so this search is not bound to a date.
 */
export default function Desk({
    search,
    results,
    expected,
    barbers,
    can_assign: canAssign,
}: {
    search: string;
    results: Booking[];
    expected: Booking[];
    barbers: Barber[];
    can_assign: boolean;
}) {
    const [term, setTerm] = useState(search);

    const waiting = expected.filter((row) => row.started_at === null);
    const inChairs = expected.filter((row) => row.started_at !== null);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        router.get(
            '/admin/desk',
            term.trim() === '' ? {} : { search: term.trim() },
            { preserveState: true, preserveScroll: true },
        );
    }

    function clear() {
        setTerm('');
        router.get('/admin/desk', {}, { preserveState: true });
    }

    return (
        <AdminPage
            title="Front desk"
            lede="Find whoever is standing there, put them in a chair, start the clock."
        >
            <Head title="Front desk" />

            <Panel
                title="Who is this?"
                description="A phone number, a name, or the booking reference off their phone. Searches every date, not just today."
            >
                <form onSubmit={submit} className="flex flex-col gap-3 p-5 sm:flex-row">
                    <div className="relative flex-1">
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <input
                            type="search"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            placeholder="0782 000 001, Tapiwa, or MB4K7P"
                            aria-label="Phone number, name or booking reference"
                            autoFocus
                            className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent pr-3 pl-9 text-base shadow-xs outline-none focus-visible:ring-[3px]"
                        />
                    </div>

                    <Button type="submit" size="lg">
                        <Search className="size-4" aria-hidden="true" />
                        Find them
                    </Button>

                    {search !== '' && (
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            onClick={clear}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Clear
                        </Button>
                    )}
                </form>
            </Panel>

            {search !== '' && (
                <Panel
                    title={`${results.length} match${results.length === 1 ? '' : 'es'} for “${search}”`}
                    description="Closest booking to right now first, which is almost always the one they mean."
                >
                    {results.length === 0 ? (
                        <p className="text-muted-foreground px-5 py-10 text-sm">
                            Nothing found. They may never have booked, in which
                            case take them as a walk-in from{' '}
                            <a
                                href="/admin/bookings/create"
                                className="text-primary underline"
                            >
                                New booking
                            </a>
                            .
                        </p>
                    ) : (
                        results.map((row) => (
                            <DeskRow
                                key={row.id}
                                booking={row}
                                barbers={barbers}
                                canAssign={canAssign}
                            />
                        ))
                    )}
                </Panel>
            )}

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Still to come in"
                    value={waiting.length}
                    accent
                    hint="Today, not yet started"
                />
                <StatCard label="In the chairs" value={inChairs.length} />
                <StatCard
                    label="Barbers on"
                    value={barbers.length}
                    hint={`${barbers.filter((barber) => barber.in_chair !== null).length} mid-cut`}
                />
            </div>

            {inChairs.length > 0 && (
                <Panel
                    title="In the chairs"
                    description="Clocks that are running. Finishing is what pays the barber and gives the client their points."
                >
                    {inChairs.map((row) => (
                        <DeskRow
                            key={row.id}
                            booking={row}
                            barbers={barbers}
                            canAssign={canAssign}
                        />
                    ))}
                </Panel>
            )}

            <Panel
                title="Expected today"
                description="Everyone still due in. Searching is for the ones who are not on this list."
            >
                {waiting.length === 0 ? (
                    <p className="text-muted-foreground flex items-center gap-2 px-5 py-10 text-sm">
                        <Scissors className="size-4" aria-hidden="true" />
                        Nobody left to come in today.
                    </p>
                ) : (
                    waiting.map((row) => (
                        <DeskRow
                            key={row.id}
                            booking={row}
                            barbers={barbers}
                            canAssign={canAssign}
                        />
                    ))
                )}
            </Panel>
        </AdminPage>
    );
}
