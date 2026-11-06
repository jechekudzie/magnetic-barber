import { Head, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    CircleAlert,
    ExternalLink,
    Loader2,
    Smartphone,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { SelectField, TextField } from '@/components/admin/form';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import type { Money } from '@/types/catalog';

type Method = { value: string; label: string; needs_proof: boolean };

type Awaiting = {
    id: string;
    method: string;
    method_label: string;
    amount: Money;
    reference: string | null;
    proof_url: string | null;
    note: string | null;
    claimed_at: string | null;
    self_recorded: boolean;
    appointment: {
        id: string;
        reference: string;
        client: string;
        when: string;
        total: Money;
        services: string[];
    };
};

type Owing = {
    id: string;
    reference: string;
    client: string;
    barber: string | null;
    when: string;
    status: string;
    services: string[];
    total: Money;
    paid: Money;
    outstanding: Money;
    is_secured: boolean;
};

/**
 * One row of the queue: money somebody says they sent, and the two answers.
 */
function AwaitingRow({ payment, canVoid }: { payment: Awaiting; canVoid: boolean }) {
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);

    return (
        <div className="border-border/60 flex flex-col gap-4 border-b py-5 last:border-b-0 lg:flex-row lg:items-start">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-semibold">{payment.appointment.client}</span>
                    <Pill>{payment.appointment.reference}</Pill>
                    <Pill>{payment.method_label}</Pill>
                    {payment.self_recorded && <Pill>From the app</Pill>}
                </div>

                <p className="text-muted-foreground mt-1.5 text-sm">
                    {payment.appointment.when} ·{' '}
                    {payment.appointment.services.join(', ') || 'No services listed'}
                </p>

                <p className="mt-2 text-sm">
                    <span className="font-semibold">{payment.amount.formatted}</span>
                    {' claimed against '}
                    {payment.appointment.total.formatted}
                    {payment.reference && (
                        <>
                            {' · ref '}
                            <span className="font-mono text-xs">{payment.reference}</span>
                        </>
                    )}
                </p>

                {payment.note && (
                    <p className="text-muted-foreground mt-1 text-sm italic">{payment.note}</p>
                )}

                {payment.proof_url ? (
                    <a
                        href={payment.proof_url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="mt-3 inline-flex items-center gap-1.5 text-sm underline underline-offset-4"
                    >
                        <ExternalLink className="size-3.5" aria-hidden="true" />
                        Open the screenshot
                    </a>
                ) : (
                    <p className="text-muted-foreground mt-3 inline-flex items-center gap-1.5 text-sm">
                        <CircleAlert className="size-3.5" aria-hidden="true" />
                        No screenshot — check the reference against the statement
                    </p>
                )}
            </div>

            <div className="flex shrink-0 flex-col gap-2 lg:w-64">
                {rejecting ? (
                    <>
                        <TextField
                            label="Why not?"
                            value={reason}
                            onChange={setReason}
                            placeholder="No such transaction"
                        />
                        <div className="flex gap-2">
                            <Button
                                variant="destructive"
                                disabled={reason.trim() === '' || busy}
                                onClick={() => {
                                    setBusy(true);
                                    router.put(
                                        `/admin/till/${payment.id}/reject`,
                                        { reason },
                                        { preserveScroll: true, onFinish: () => setBusy(false) },
                                    );
                                }}
                            >
                                Not received
                            </Button>
                            <Button variant="ghost" onClick={() => setRejecting(false)}>
                                Back
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <Button
                            disabled={busy}
                            onClick={() => {
                                setBusy(true);
                                router.put(
                                    `/admin/till/${payment.id}/confirm`,
                                    {},
                                    { preserveScroll: true, onFinish: () => setBusy(false) },
                                );
                            }}
                        >
                            {busy ? (
                                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                            ) : (
                                <BadgeCheck className="size-4" aria-hidden="true" />
                            )}
                            Money arrived
                        </Button>

                        {canVoid && (
                            <Button variant="outline" onClick={() => setRejecting(true)}>
                                <X className="size-4" aria-hidden="true" />
                                It did not
                            </Button>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

/**
 * Taking money against one booking.
 */
function TakeMoney({ booking, methods }: { booking: Owing; methods: Method[] }) {
    const [open, setOpen] = useState(false);

    const form = useForm({
        method: 'cash',
        amount: (booking.outstanding.cents / 100).toFixed(2),
        reference: '',
        note: '',
    });

    const chosen = methods.find((m) => m.value === form.data.method);

    if (!open) {
        return (
            <Button variant="outline" onClick={() => setOpen(true)}>
                <Banknote className="size-4" aria-hidden="true" />
                Take {booking.outstanding.formatted}
            </Button>
        );
    }

    return (
        <form
            className="flex flex-col gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/admin/till/${booking.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        setOpen(false);
                    },
                });
            }}
        >
            <SelectField
                label="How did they pay?"
                value={form.data.method}
                onChange={(value) => form.setData('method', value)}
                options={methods.map((method) => ({
                    value: method.value,
                    label: method.label,
                }))}
            />

            <TextField
                label="Amount"
                value={form.data.amount}
                onChange={(value) => form.setData('amount', value)}
                error={form.errors.amount}
            />

            {chosen?.needs_proof && (
                <TextField
                    label="Transaction reference"
                    value={form.data.reference}
                    onChange={(value) => form.setData('reference', value)}
                    error={form.errors.reference}
                    hint="Off the client's phone. It stays pending until somebody checks it."
                />
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && (
                        <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                    )}
                    Record it
                </Button>
                <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

/**
 * The till.
 *
 * Two jobs on one screen because they are the same job to whoever is on the
 * desk: take money from the person in front of you, and deal with the money
 * somebody sent from their phone before they arrived.
 *
 * The queue is deliberately not filtered by date. A proof sent on Friday
 * night that nobody looked at must not fall off the screen on Saturday.
 */
export default function Till({
    date,
    awaiting,
    owing,
    takings,
    methods,
    can,
}: {
    date: string;
    awaiting: Awaiting[];
    owing: Owing[];
    takings: {
        total: Money;
        count: number;
        by_method: Array<{ method: string; label: string; count: number; total: Money }>;
    };
    methods: Method[];
    can: { take: boolean; void: boolean };
}) {
    return (
        <AdminPage
            title="Till"
            lede={`Money taken today, and anything still waiting to be checked. ${date}`}
        >
            <Head title="Till" />

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard label="Taken today" value={takings.total.formatted} />
                <StatCard label="Payments" value={String(takings.count)} />
                <StatCard
                    label="Waiting to be checked"
                    value={String(awaiting.length)}
                    accent={awaiting.length > 0}
                    hint={awaiting.length > 0 ? 'Chairs are held on these' : undefined}
                />
            </div>

            {takings.by_method.length > 0 && (
                <Panel title="How it came in">
                    <ul className="flex flex-wrap gap-4">
                        {takings.by_method.map((row) => (
                            <li key={row.method} className="flex items-baseline gap-2">
                                <span className="font-semibold">{row.total.formatted}</span>
                                <span className="text-muted-foreground text-sm">
                                    {row.label} · {row.count}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}

            <Panel
                title="Waiting to be checked"
                description="Somebody says they sent this. Their chair is already held — this is where the shop finds out whether the money arrived."
            >
                {awaiting.length === 0 ? (
                    <p className="text-muted-foreground py-6 text-sm">
                        Nothing waiting. Every payment claimed has been looked at.
                    </p>
                ) : (
                    <div>
                        {awaiting.map((payment) => (
                            <AwaitingRow
                                key={payment.id}
                                payment={payment}
                                canVoid={can.void}
                            />
                        ))}
                    </div>
                )}
            </Panel>

            <Panel
                title="Owing today"
                description="Bookings today that still have something to settle."
            >
                {owing.length === 0 ? (
                    <p className="text-muted-foreground py-6 text-sm">
                        Nothing outstanding today.
                    </p>
                ) : (
                    <ul className="divide-border/60 divide-y">
                        {owing.map((booking) => (
                            <li
                                key={booking.id}
                                className="flex flex-col gap-4 py-5 lg:flex-row lg:items-start"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">{booking.client}</span>
                                        <Pill>{booking.reference}</Pill>
                                        <Pill>{booking.status}</Pill>
                                        {booking.is_secured && (
                                            <Pill>
                                                <Smartphone
                                                    className="mr-1 inline size-3"
                                                    aria-hidden="true"
                                                />
                                                Paid from the app
                                            </Pill>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground mt-1.5 text-sm">
                                        {booking.when}
                                        {booking.barber ? ` · ${booking.barber}` : ''} ·{' '}
                                        {booking.services.join(', ')}
                                    </p>

                                    <p className="mt-2 text-sm">
                                        {booking.total.formatted} total ·{' '}
                                        {booking.paid.formatted} paid ·{' '}
                                        <span className="font-semibold">
                                            {booking.outstanding.formatted} outstanding
                                        </span>
                                    </p>
                                </div>

                                <div className="shrink-0 lg:w-72">
                                    {can.take && (
                                        <TakeMoney booking={booking} methods={methods} />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </AdminPage>
    );
}
