import { router } from '@inertiajs/react';
import { Car, Clock, MapPin, Phone, Scissors, User, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import type { Booking } from '@/types/bookings';

const TONE: Record<string, 'neutral' | 'good' | 'warn' | 'gold'> = {
    pending: 'warn',
    confirmed: 'gold',
    checked_in: 'gold',
    in_progress: 'gold',
    completed: 'good',
    cancelled: 'neutral',
    no_show: 'warn',
};

/**
 * One booking, opened from the grid.
 *
 * The calendar shows the shape of the day; everything you would actually do
 * to a booking lives here, so the grid blocks can stay small and readable.
 */
export function BookingDrawer({
    booking,
    onClose,
}: {
    booking: Booking | null;
    onClose: () => void;
}) {
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (booking === null) {
            return;
        }

        function onKey(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [booking, onClose]);

    if (booking === null) {
        return null;
    }

    function setStatus(status: string) {
        setBusy(true);

        router.put(
            `/admin/bookings/${booking!.id}/status`,
            { status },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: onClose,
            },
        );
    }

    const settled = ['completed', 'cancelled', 'no_show'].includes(
        booking.status,
    );

    return (
        <div className="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true">
            <button
                type="button"
                aria-label="Close"
                onClick={onClose}
                className="absolute inset-0 bg-black/40"
            />

            <div className="bg-card animate-in slide-in-from-right relative flex h-full w-full max-w-md flex-col border-l shadow-xl duration-200">
                <header className="flex items-start justify-between gap-3 border-b p-5">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-semibold">
                                {booking.client.name}
                            </h2>
                            <Pill tone={TONE[booking.status] ?? 'neutral'}>
                                {booking.status_label}
                            </Pill>
                        </div>
                        <p className="text-muted-foreground mt-0.5 font-mono text-xs">
                            {booking.reference}
                            {booking.branch_name && ` · ${booking.branch_name}`}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close"
                        className="text-muted-foreground hover:text-foreground -mr-2 p-2"
                    >
                        <X className="size-5" aria-hidden="true" />
                    </button>
                </header>

                <div className="flex-1 space-y-5 overflow-y-auto p-5 text-sm">
                    <Row icon={Clock}>
                        <p className="font-medium">
                            {booking.day_label} at {booking.time_label}
                        </p>
                        <p className="text-muted-foreground">
                            {booking.duration_minutes} minutes in the chair
                        </p>
                    </Row>

                    {booking.staff && (
                        <Row icon={User}>
                            <p className="font-medium">{booking.staff}</p>
                        </Row>
                    )}

                    <Row icon={Scissors}>
                        <p>{booking.services.join(', ')}</p>
                        <p className="text-muted-foreground mt-0.5">
                            {booking.total.formatted}
                        </p>
                    </Row>

                    {booking.client.phone && (
                        <Row icon={Phone}>
                            <a
                                href={`tel:${booking.client.phone}`}
                                className="hover:text-primary font-medium"
                            >
                                {booking.client.phone}
                            </a>
                            <p className="text-muted-foreground">
                                {booking.client.account_number}
                                {booking.client.visit_count > 0 &&
                                    ` · ${booking.client.visit_count} visits`}
                            </p>
                        </Row>
                    )}

                    {booking.address && (
                        <Row icon={booking.is_house_call ? Car : MapPin}>
                            <p className="font-medium">House call</p>
                            <p className="text-muted-foreground">
                                {booking.address}
                            </p>
                        </Row>
                    )}

                    {booking.note && (
                        <div className="bg-muted/50 rounded-lg p-3">
                            <p className="text-muted-foreground mb-1 text-xs font-medium">
                                What they asked for
                            </p>
                            <p className="italic">“{booking.note}”</p>
                        </div>
                    )}
                </div>

                <footer className="space-y-2 border-t p-5">
                    {settled ? (
                        <p className="text-muted-foreground text-center text-sm">
                            This booking is {booking.status_label.toLowerCase()}.
                        </p>
                    ) : (
                        <>
                            <Button
                                className="w-full"
                                disabled={busy}
                                onClick={() => setStatus('completed')}
                            >
                                Complete, and award points
                            </Button>
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => setStatus('checked_in')}
                                >
                                    Checked in
                                </Button>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => setStatus('no_show')}
                                >
                                    No show
                                </Button>
                            </div>
                            <Button
                                variant="outline"
                                className="w-full"
                                disabled={busy}
                                onClick={() => setStatus('cancelled')}
                            >
                                Cancel booking
                            </Button>
                        </>
                    )}
                </footer>
            </div>
        </div>
    );
}

function Row({
    icon: Icon,
    children,
}: {
    icon: typeof Clock;
    children: React.ReactNode;
}) {
    return (
        <div className="flex gap-3">
            <Icon
                className="text-primary mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
