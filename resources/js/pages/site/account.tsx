import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CircleCheck,
    Clock,
    Gift,
    KeyRound,
    LogOut,
    MapPin,
    Scissors,
    TriangleAlert,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { SiteButton, SiteLink } from '@/components/site/button';
import { Container } from '@/components/site/section';
import { cn } from '@/lib/utils';
import type { Money, SiteShared } from '@/types/catalog';

type Visit = {
    id: string;
    reference: string;
    status: string;
    status_label: string;
    is_house_call: boolean;
    day_label: string | null;
    time_label: string | null;
    duration_minutes: number;
    branch: string | null;
    address: string | null;
    barber: string | null;
    services: string[];
    total: Money;
    paid: Money;
    outstanding: Money;
    is_secured: boolean;
    is_paid_in_full: boolean;
    may_cancel: boolean;
    note: string | null;
};

type Loyalty = {
    points: number;
    redeemable: boolean;
    threshold: number;
    value: Money;
    reward: Money;
    to_next: number;
};

/**
 * One visit, booked or been to.
 *
 * The reference is loud because it is what the desk asks for, and what the
 * client reads off their phone when they walk in.
 */
function VisitCard({ visit, past }: { visit: Visit; past: boolean }) {
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);

    function cancel() {
        setBusy(true);
        router.put(
            `/account/bookings/${visit.id}/cancel`,
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    }

    return (
        <div className="site-panel p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="site-display text-xl sm:text-2xl">
                        {visit.day_label}
                        {visit.time_label ? ` at ${visit.time_label}` : ''}
                    </p>
                    <p className="text-smoke mt-1 text-sm">
                        {visit.reference} · {visit.status_label}
                    </p>
                </div>

                <p className="site-display text-gold text-xl tabular-nums">
                    {visit.total.formatted}
                </p>
            </div>

            <dl className="mt-5 space-y-2 text-sm">
                <div className="flex gap-3">
                    <dt className="text-smoke w-20 shrink-0">
                        <Scissors className="mr-1.5 inline size-3.5" aria-hidden="true" />
                        What
                    </dt>
                    <dd>
                        {visit.services.join(', ') || '—'}
                        <span className="text-smoke"> · {visit.duration_minutes} min</span>
                    </dd>
                </div>

                <div className="flex gap-3">
                    <dt className="text-smoke w-20 shrink-0">
                        <User className="mr-1.5 inline size-3.5" aria-hidden="true" />
                        Who
                    </dt>
                    <dd>{visit.barber ?? 'Any barber'}</dd>
                </div>

                <div className="flex gap-3">
                    <dt className="text-smoke w-20 shrink-0">
                        <MapPin className="mr-1.5 inline size-3.5" aria-hidden="true" />
                        Where
                    </dt>
                    <dd>
                        {visit.is_house_call ? 'House call to ' : ''}
                        {visit.address ?? visit.branch}
                    </dd>
                </div>
            </dl>

            {visit.note && (
                <p className="text-smoke mt-4 text-sm italic">“{visit.note}”</p>
            )}

            {!past && (
                <div className="border-bone/8 mt-5 flex flex-wrap items-center gap-3 border-t pt-5">
                    {visit.is_paid_in_full ? (
                        <span className="text-go inline-flex items-center gap-1.5 text-sm font-medium">
                            <CircleCheck className="size-4" aria-hidden="true" />
                            Paid
                        </span>
                    ) : visit.is_secured ? (
                        <span className="text-gold inline-flex items-center gap-1.5 text-sm font-medium">
                            <Clock className="size-4" aria-hidden="true" />
                            Chair held, {visit.outstanding.formatted} to settle
                        </span>
                    ) : (
                        <span className="text-smoke text-sm">
                            {visit.outstanding.formatted} to pay at the shop
                        </span>
                    )}

                    <span className="flex-1" />

                    {visit.may_cancel &&
                        (confirming ? (
                            <>
                                <span className="text-smoke text-sm">Sure?</span>
                                <SiteButton
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setConfirming(false)}
                                >
                                    Keep it
                                </SiteButton>
                                <SiteButton size="sm" disabled={busy} onClick={cancel}>
                                    Cancel it
                                </SiteButton>
                            </>
                        ) : (
                            <SiteButton
                                variant="outline"
                                size="sm"
                                onClick={() => setConfirming(true)}
                            >
                                Cancel
                            </SiteButton>
                        ))}
                </div>
            )}
        </div>
    );
}

/**
 * Changing a password without leaving the page.
 *
 * The settings screens live in the staff shell, so sending a client there
 * would put them straight back in the admin chrome this page exists to keep
 * them out of. The endpoint is the same one those screens post to.
 */
function PasswordPanel() {
    const [open, setOpen] = useState(false);

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    if (!open) {
        return (
            <SiteButton
                variant="outline"
                className="w-full"
                onClick={() => setOpen(true)}
            >
                <KeyRound className="size-4" aria-hidden="true" />
                Change password
            </SiteButton>
        );
    }

    return (
        <form onSubmit={submit} className="site-panel space-y-3 p-5">
            <h2 className="site-eyebrow">Change password</h2>

            {(
                [
                    ['current_password', 'Current password'],
                    ['password', 'New password'],
                    ['password_confirmation', 'Repeat it'],
                ] as Array<[keyof typeof form.data, string]>
            ).map(([field, label]) => (
                <div key={field}>
                    <label
                        htmlFor={field}
                        className="mb-1.5 block text-sm font-medium"
                    >
                        {label}
                    </label>
                    <input
                        id={field}
                        type="password"
                        autoComplete={
                            field === 'current_password'
                                ? 'current-password'
                                : 'new-password'
                        }
                        value={form.data[field]}
                        onChange={(event) =>
                            form.setData(field, event.target.value)
                        }
                        className="border-bone/15 bg-panel-alt text-bone focus:border-gold h-11 w-full rounded-lg border px-3 text-sm outline-none"
                    />
                    {form.errors[field] && (
                        <p className="text-stop mt-1.5 text-xs">
                            {form.errors[field]}
                        </p>
                    )}
                </div>
            ))}

            <div className="flex gap-2 pt-1">
                <SiteButton
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={() => {
                        form.reset();
                        form.clearErrors();
                        setOpen(false);
                    }}
                >
                    Never mind
                </SiteButton>
                <SiteButton
                    type="submit"
                    size="sm"
                    className="flex-1"
                    disabled={form.processing}
                >
                    Save
                </SiteButton>
            </div>
        </form>
    );
}

/**
 * A client's own page.
 *
 * Signing in on the site used to land a client on the staff dashboard — an
 * admin shell they had no permission for. Everything here already existed in
 * the app; this is the website catching up, because the two are meant to
 * show a client the same things.
 */
export default function Account({
    client,
    upcoming,
    past,
    loyalty,
}: {
    site: SiteShared;
    client: {
        name: string;
        phone: string | null;
        email: string | null;
        account_number: string | null;
        visit_count: number;
        member_since: string | null;
    };
    upcoming: Visit[];
    past: Visit[];
    loyalty: Loyalty;
}) {
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const progress =
        loyalty.threshold > 0
            ? Math.min(1, loyalty.points / loyalty.threshold)
            : 0;

    return (
        <>
            <Head title="My account" />

            <section className="site-glow border-bone/8 border-b">
                <Container className="py-10 sm:py-14">
                    <p className="site-eyebrow mb-3">My account</p>
                    <h1 className="site-display text-3xl sm:text-4xl lg:text-5xl">
                        {client.name}
                    </h1>
                    <p className="text-smoke mt-3 text-sm sm:text-base">
                        {client.phone}
                        {client.account_number ? ` · ${client.account_number}` : ''}
                        {client.member_since ? ` · with us since ${client.member_since}` : ''}
                    </p>
                </Container>
            </section>

            <Container className="py-10 sm:py-14">
                {errors?.booking && (
                    <div className="site-panel border-stop/50 mb-8 flex items-start gap-3 p-4">
                        <TriangleAlert
                            className="text-stop mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <p className="text-sm">{errors.booking}</p>
                    </div>
                )}

                <div className="grid gap-8 lg:grid-cols-[1fr_20rem]">
                    <div className="min-w-0">
                        <h2 className="site-eyebrow mb-4">Coming up</h2>

                        {upcoming.length === 0 ? (
                            <div className="site-panel p-6 sm:p-8">
                                <p className="site-display text-xl">Nothing booked</p>
                                <p className="text-smoke mt-2 text-sm">
                                    Your chair is a minute away.
                                </p>
                                <SiteLink href="/book" className="mt-5">
                                    <CalendarDays className="size-4" aria-hidden="true" />
                                    Book a chair
                                </SiteLink>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {upcoming.map((visit) => (
                                    <VisitCard key={visit.id} visit={visit} past={false} />
                                ))}
                            </div>
                        )}

                        {past.length > 0 && (
                            <>
                                <h2 className="site-eyebrow mt-12 mb-4">
                                    Been in {past.length} time{past.length === 1 ? '' : 's'}
                                </h2>
                                <div className="space-y-4">
                                    {past.slice(0, 10).map((visit) => (
                                        <VisitCard key={visit.id} visit={visit} past />
                                    ))}
                                </div>
                            </>
                        )}
                    </div>

                    <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                        <div className="site-panel p-5">
                            <h2 className="site-eyebrow mb-4">Your points</h2>

                            <p className="site-display text-gold text-4xl tabular-nums">
                                {loyalty.points}
                            </p>
                            <p className="text-smoke mt-1 text-sm">
                                worth {loyalty.value.formatted} off a bill
                            </p>

                            {loyalty.redeemable ? (
                                <div className="border-go/40 bg-panel-alt mt-5 rounded-xl border p-4">
                                    <p className="text-go inline-flex items-center gap-2 text-sm font-semibold">
                                        <Gift className="size-4" aria-hidden="true" />
                                        Ready to spend
                                    </p>
                                    <p className="text-smoke mt-1.5 text-sm leading-relaxed">
                                        Take {loyalty.reward.formatted} off your next cut.
                                        Mention it at the desk.
                                    </p>
                                </div>
                            ) : (
                                <div className="mt-5">
                                    <div className="bg-panel-alt h-1.5 overflow-hidden rounded-full">
                                        <div
                                            className="bg-gold h-full rounded-full"
                                            style={{
                                                width: `${Math.round(progress * 100)}%`,
                                            }}
                                        />
                                    </div>
                                    <p className="text-smoke mt-3 text-sm leading-relaxed">
                                        {loyalty.to_next} more point
                                        {loyalty.to_next === 1 ? '' : 's'} and{' '}
                                        {loyalty.reward.formatted} comes off a bill.
                                    </p>
                                </div>
                            )}

                            <p className="text-smoke mt-4 text-xs leading-relaxed">
                                Points land when the shop marks your visit finished.
                            </p>
                        </div>

                        <SiteLink href="/book" className="w-full">
                            Book a chair
                        </SiteLink>

                        <PasswordPanel />

                        <SiteButton
                            variant="ghost"
                            className={cn('w-full')}
                            onClick={() => router.post('/logout')}
                        >
                            <LogOut className="size-4" aria-hidden="true" />
                            Sign out
                        </SiteButton>
                    </aside>
                </div>
            </Container>
        </>
    );
}
