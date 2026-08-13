import { Head } from '@inertiajs/react';
import {
    CalendarPlus,
    Car,
    Check,
    Clock,
    MapPin,
    Scissors,
    User,
} from 'lucide-react';
import { SiteLink } from '@/components/site/button';
import { Container } from '@/components/site/section';
import type { Appointment } from '@/types/booking';
import type { SiteShared } from '@/types/catalog';

/**
 * The confirmation. The reference is the loudest thing on the page because it
 * is what a client reads out at the desk or quotes over WhatsApp.
 */
export default function Booked({
    site,
    appointment,
}: {
    site: SiteShared;
    appointment: Appointment;
}) {
    const whatsappConfirm = site.whatsapp_link
        ? `${site.whatsapp_link}?text=${encodeURIComponent(
              `Hi Magnetic, this is about booking ${appointment.reference} on ${appointment.when_label}.`,
          )}`
        : null;

    return (
        <>
            <Head title={`Booked · ${appointment.reference}`} />

            <section className="site-glow">
                <Container className="py-16 text-center sm:py-24">
                    <span className="bg-go/15 text-go animate-in zoom-in mx-auto mb-7 flex size-16 items-center justify-center rounded-full duration-500">
                        <Check className="size-8" aria-hidden="true" />
                    </span>

                    <h1 className="site-display text-4xl sm:text-5xl">
                        You are in the book.
                    </h1>
                    <p className="text-smoke mx-auto mt-4 max-w-md leading-relaxed">
                        {appointment.when_label}
                        {appointment.staff?.name
                            ? ` with ${appointment.staff.name}`
                            : ''}
                        . Save your reference, it is all we need at the desk.
                    </p>

                    <p className="site-panel bg-panel-alt mx-auto mt-8 inline-flex flex-col items-center px-8 py-5">
                        <span className="site-eyebrow">Your reference</span>
                        <span className="site-display text-gold mt-1 text-3xl tracking-wider">
                            {appointment.reference}
                        </span>
                    </p>
                </Container>
            </section>

            <Container className="pb-20">
                <div className="site-panel mx-auto max-w-lg divide-y">
                    <div className="flex gap-4 p-5">
                        <Clock
                            className="text-gold mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p className="font-semibold">
                                {appointment.when_label}
                            </p>
                            <p className="text-smoke mt-0.5 text-sm">
                                About {appointment.duration_minutes} minutes in
                                the chair
                            </p>
                        </div>
                    </div>

                    {appointment.staff && (
                        <div className="flex gap-4 p-5">
                            <User
                                className="text-gold mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="font-semibold">
                                    {appointment.staff.name}
                                </p>
                                {appointment.staff.title && (
                                    <p className="text-smoke mt-0.5 text-sm">
                                        {appointment.staff.title}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {appointment.services && appointment.services.length > 0 && (
                        <div className="flex gap-4 p-5">
                            <Scissors
                                className="text-gold mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <div className="min-w-0 flex-1">
                                <ul className="space-y-1.5">
                                    {appointment.services.map((line) => (
                                        <li
                                            key={line.name}
                                            className="flex justify-between gap-3 text-sm"
                                        >
                                            <span>{line.name}</span>
                                            <span className="text-smoke tabular-nums">
                                                {line.price.formatted}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                {appointment.travel_fee &&
                                    appointment.travel_fee.cents > 0 && (
                                        <p className="text-smoke mt-1.5 flex justify-between gap-3 text-sm">
                                            <span>Travel fee</span>
                                            <span className="tabular-nums">
                                                {
                                                    appointment.travel_fee
                                                        .formatted
                                                }
                                            </span>
                                        </p>
                                    )}
                                <p className="border-bone/8 mt-3 flex justify-between border-t pt-3 font-semibold">
                                    <span>
                                        {appointment.house_call
                                            ? 'Total to pay your barber'
                                            : 'Total to pay at the counter'}
                                    </span>
                                    <span className="text-gold tabular-nums">
                                        {appointment.total.formatted}
                                    </span>
                                </p>
                            </div>
                        </div>
                    )}

                    {appointment.house_call && (
                        <div className="flex gap-4 p-5">
                            <Car
                                className="text-gold mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="font-semibold">
                                    We are coming to you
                                </p>
                                <p className="text-smoke mt-0.5 text-sm">
                                    {appointment.house_call.address}
                                </p>
                                {appointment.house_call.directions_note && (
                                    <p className="text-smoke mt-1 text-sm italic">
                                        {appointment.house_call.directions_note}
                                    </p>
                                )}
                                <p className="text-smoke mt-2 text-sm">
                                    Travel fee{' '}
                                    <span className="text-gold font-semibold">
                                        {
                                            appointment.house_call.travel_fee
                                                .formatted
                                        }
                                    </span>
                                    , included in the total below.
                                </p>
                            </div>
                        </div>
                    )}

                    {!appointment.house_call && appointment.branch && (
                        <div className="flex gap-4 p-5">
                            <MapPin
                                className="text-gold mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="font-semibold">
                                    {appointment.branch.name}
                                </p>
                                <p className="text-smoke mt-0.5 text-sm">
                                    {appointment.branch.address_line}
                                    <br />
                                    {appointment.branch.area}
                                </p>
                                {appointment.branch.map_url && (
                                    <a
                                        href={appointment.branch.map_url}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="text-gold hover:text-gold-lite mt-2 inline-block text-sm underline underline-offset-4"
                                    >
                                        Get directions
                                    </a>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                <div className="mx-auto mt-6 flex max-w-lg flex-wrap justify-center gap-3">
                    {whatsappConfirm && (
                        <SiteLink href={whatsappConfirm} external>
                            <CalendarPlus className="size-4" aria-hidden="true" />
                            Send it to WhatsApp
                        </SiteLink>
                    )}
                    <SiteLink href="/" variant="outline">
                        Back to the site
                    </SiteLink>
                </div>

                <p className="text-smoke mx-auto mt-8 max-w-lg text-center text-xs leading-relaxed">
                    Need to change or cancel? Message us on WhatsApp with your
                    reference. Automatic reminders arrive once messaging is
                    switched on.
                </p>
            </Container>
        </>
    );
}
