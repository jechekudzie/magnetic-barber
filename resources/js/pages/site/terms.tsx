import { Head, Link } from '@inertiajs/react';
import { Clause, LegalBody, LegalHeader } from '@/components/site/legal';
import type { Money, SiteShared } from '@/types/catalog';

type TermsProps = {
    site: SiteShared;
    /** Read from the same config the booking service enforces. */
    cancellation_hours: number;
    loyalty: {
        points_per_visit: number;
        threshold: number;
        reward: Money;
        expiry_months: number | null;
    };
};

function hours(count: number): string {
    return count === 1 ? '1 hour' : `${count} hours`;
}

export default function Terms({ cancellation_hours, loyalty }: TermsProps) {
    const cancelWindow = hours(cancellation_hours);

    return (
        <>
            <Head title="Terms">
                <meta
                    name="description"
                    content="How booking, cancelling, paying, plans and points work at Magnetic Barbershop."
                />
            </Head>

            <LegalHeader
                eyebrow="Terms"
                title="How booking here works."
                lede="No deposit, no small print on the prices, and a cancellation window long enough to be fair to whoever wanted that chair. This is the whole arrangement."
                updated="12 November 2026"
            />

            <LegalBody>
                <Clause heading="What these cover">
                    <p>
                        Booking a chair at Magnetic Barbershop, whether on this
                        site, in the app, over WhatsApp or by walking in. By
                        booking you accept what is on this page. Our{' '}
                        <Link href="/privacy" className="text-gold underline">
                            privacy notice
                        </Link>{' '}
                        covers what we hold about you.
                    </p>
                </Clause>

                <Clause heading="Booking a chair">
                    <p>
                        A booking asks for a name and a mobile number. We take
                        no deposit and no card details: you book for nothing and
                        pay at the shop.
                    </p>
                    <p>
                        You get a booking reference. Bring it, or just give your
                        number at the desk, and we will find you either way.
                        Until we confirm it, a booking is a request for a time,
                        not a promise of one.
                    </p>
                </Clause>

                <Clause heading="Changing your mind">
                    <p>
                        You can cancel from the site or the app up to{' '}
                        {cancelWindow} before your appointment, and that is the
                        end of it. No fee, no explanation needed.
                    </p>
                    <p>
                        Inside the last {cancelWindow}, phone the shop instead
                        of cancelling online. By then nobody else can
                        realistically take the slot, and a barber who turned
                        down another client for it should hear from a person.
                    </p>
                    <p>
                        We record when somebody does not arrive. Do it
                        repeatedly and we may ask you to pay up front before we
                        hold a chair again.
                    </p>
                </Clause>

                <Clause heading="If we have to change it">
                    <p>
                        Barbers get sick and power goes. If we must move or
                        cancel your booking we will contact you on the number
                        you gave, offer the nearest time we have, and you owe us
                        nothing.
                    </p>
                </Clause>

                <Clause heading="Arriving">
                    <p>
                        Come a few minutes early. If you are late we will still
                        cut your hair where the diary allows, but a long cut may
                        have to become a shorter one, or move to the next free
                        slot, because the person booked after you is owed their
                        time too.
                    </p>
                </Clause>

                <Clause heading="Prices">
                    <p>
                        The prices on this site come line for line from the
                        printed cards in the shop, and they are set per branch.
                        What you see when a branch is selected is what that
                        branch charges.
                    </p>
                    <p>
                        A few services are shown as a starting price, marked
                        "from". Colour and tints are the usual ones: the final
                        figure depends on your hair's length and condition, and
                        your barber settles it with you in the chair before
                        starting, never afterwards.
                    </p>
                </Clause>

                <Clause heading="Colour and skin work needs a patch test">
                    <p>
                        Some services, colour especially, need a patch test a
                        set number of hours before the appointment. The lead
                        time is shown on the service when you book. We will not
                        skip it on request: the test is what stops a reaction on
                        your scalp, and the shop carries that risk with you.
                    </p>
                </Clause>

                <Clause heading="Paying">
                    <p>
                        We take EcoCash, InnBucks, cash, card and bank transfer.
                    </p>
                    <p>
                        If you pay by transfer and send a confirmation
                        screenshot, it is logged as pending until somebody at
                        the shop checks it against the money arriving. A
                        screenshot on its own is not a receipt, and we will tell
                        you if one is rejected and why.
                    </p>
                </Clause>

                <Clause heading="Plans">
                    <p>
                        A plan runs at the branch you bought it at. Session
                        packs are valid for their stated window and then stop.
                    </p>
                    <p>
                        Nothing renews silently. An unlimited plan is something
                        you choose to renew each month, and we will never take a
                        further payment because you forgot to cancel. A plan
                        sits on one client record, because it carries your cut
                        history, so it is not shareable.
                    </p>
                </Clause>

                <Clause heading="Points">
                    <p>
                        A completed visit earns {loyalty.points_per_visit}{' '}
                        points. At {loyalty.threshold} points you can take{' '}
                        {loyalty.reward.formatted} off a bill.
                    </p>
                    <p>
                        Points come off a bill; they are not credit and we
                        cannot pay them out in cash. They sit on your record, so
                        they are not transferable.{' '}
                        {loyalty.expiry_months
                            ? `Unused points expire ${loyalty.expiry_months} months after you earn them.`
                            : 'They do not currently expire, and if that ever changes we will tell you before it applies to points you already hold.'}
                    </p>
                    <p>
                        Your live balance is on your{' '}
                        <Link href="/account" className="text-gold underline">
                            account page
                        </Link>
                        , and it is the figure that counts.
                    </p>
                </Clause>

                <Clause heading="House calls">
                    <p>
                        Some services and some barbers travel. Where a house
                        call is offered it is priced separately, depends on
                        where you are, and needs a time we can actually get to
                        and back from. We agree it with you before the day.
                    </p>
                </Clause>

                <Clause heading="Reviews">
                    <p>
                        We may publish a review you leave, with the name you put
                        on it, and reply to it in public. We will take down
                        anything that names another client, or that is abusive,
                        and we will not quietly delete a review simply for being
                        unflattering.
                    </p>
                </Clause>

                <Clause heading="Your account">
                    <p>
                        If you make an account, the password is yours to look
                        after. Tell us if you think somebody else has it and we
                        will close the sessions on it.
                    </p>
                    <p>
                        We can close an account that is being used to abuse
                        staff or to hold chairs nobody intends to use. We will
                        say why.
                    </p>
                </Clause>

                <Clause heading="What we are responsible for">
                    <p>
                        We are responsible for the work we do on your hair and
                        skin. If a cut or a treatment goes wrong, tell us at
                        once and we will put it right, which normally means a
                        correction in the chair at no charge.
                    </p>
                    <p>
                        Tell your barber or the aesthetician about allergies,
                        scalp or skin conditions and medication that affects
                        your skin before they start. We cannot plan around
                        something we were not told.
                    </p>
                    <p>
                        Nothing here removes a right you have under Zimbabwean
                        consumer law.
                    </p>
                </Clause>

                <Clause heading="Which law">
                    <p>
                        These terms are governed by the law of Zimbabwe, and the
                        Zimbabwean courts deal with any dispute. Speak to us
                        first, though: most of this gets sorted at the front
                        desk in five minutes.
                    </p>
                </Clause>

                <Clause heading="If these change">
                    <p>
                        We will change the date at the top. A booking you have
                        already made is held under the terms as they stood when
                        you made it.
                    </p>
                </Clause>
            </LegalBody>
        </>
    );
}
