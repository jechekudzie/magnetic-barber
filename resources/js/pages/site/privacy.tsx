import { Head, Link } from '@inertiajs/react';
import {
    Clause,
    ClauseList,
    LegalBody,
    LegalHeader,
} from '@/components/site/legal';
import type { SiteShared } from '@/types/catalog';

type PrivacyProps = {
    site: SiteShared;
};

export default function Privacy({ site }: PrivacyProps) {
    const branch = site.branch;

    return (
        <>
            <Head title="Privacy">
                <meta
                    name="description"
                    content="What Magnetic Barbershop holds about you, who inside the shop can see it, and how to ask us to change or delete it."
                />
            </Head>

            <LegalHeader
                eyebrow="Privacy"
                title="What we hold, and why."
                lede="We hold a phone number, a cut history and a payment record. That is nearly all of it. This page says what each one is for, who inside the shop can see it, and how to make us delete it."
                updated="12 November 2026"
            />

            <LegalBody>
                <Clause heading="Who this is">
                    <p>
                        Magnetic Barbershop, in Harare, Zimbabwe. We decide what
                        happens to the information described here, which under
                        Zimbabwean law makes us the data controller for it.
                    </p>
                    <p>
                        You can reach us on WhatsApp or on the shop phone. Both
                        are on the{' '}
                        <Link href="/visit" className="text-gold underline">
                            find us
                        </Link>{' '}
                        page.
                    </p>
                </Clause>

                <Clause heading="What you give us">
                    <ClauseList
                        items={[
                            'Your name and mobile number. The number is how we find you: the diary, the front desk and your points all hang off it.',
                            'An email address, if you give one. It is optional and we do not need it to cut your hair.',
                            'A password, if you make an account. We never store it as you typed it.',
                            'Anything else you choose to tell us, such as your birthday, or the barber you would rather have.',
                        ]}
                    />
                </Clause>

                <Clause heading="What booking and cutting creates">
                    <ClauseList
                        items={[
                            'Your bookings: the services, the barber, the branch, the day and time, and the price.',
                            'Whether you came, were late, or did not arrive.',
                            'How often you come. We use the rhythm of your own visits to decide when a reminder is useful, rather than messaging everyone on the same day.',
                            'Notes a barber writes about the cut itself, so the next person with the clippers knows what you asked for last time.',
                        ]}
                    />
                </Clause>

                <Clause heading="Payments, and the screenshot">
                    <p>
                        For each payment we keep the method, the amount, the
                        reference and the date. If you send a confirmation
                        screenshot for an EcoCash, InnBucks or bank payment, we
                        keep that image too.
                    </p>
                    <p>
                        That screenshot carries your name, your number and the
                        amount, so it is not stored anywhere a link alone would
                        open. It sits on private storage and is served only
                        after we check who is asking. You can see it, and so can
                        the people who take payments. A barber cannot open it.
                    </p>
                </Clause>

                <Clause heading="Your number is not shown to every barber">
                    <p>
                        Inside the shop, access follows the job and the branch,
                        not seniority. The owner, the branch manager and the
                        front desk can see your contact details, because they
                        are the people who phone you and run the diary.
                    </p>
                    <p>
                        Barbers and the aesthetician cannot. They see the
                        booking, the time and the notes about your cut, and not
                        your phone number. This is deliberate, and it is checked
                        by the code that builds every screen rather than left to
                        anyone's discretion.
                    </p>
                </Clause>

                <Clause heading="Messages, and the ones you can switch off">
                    <p>
                        Reminders about a booking you made are part of running
                        the appointment: a confirmation, a nudge before the day,
                        a note if something changes.
                    </p>
                    <p>
                        Marketing is separate and off unless you turn it on, and
                        WhatsApp, SMS and app notifications are each their own
                        switch. Turning marketing off does not stop your
                        appointment reminders, and stopping reminders does not
                        cancel your booking. Ask us and we will change either.
                    </p>
                </Clause>

                <Clause heading="Points and reviews">
                    <p>
                        Points are a running ledger tied to your visits, so each
                        entry records which visit earned or spent it.
                    </p>
                    <p>
                        A review you write may be published on this site with
                        the name you put on it and the shop may reply in public.
                        Ask us and we will take yours down.
                    </p>
                </Clause>

                <Clause heading="Cookies, and what we do not run">
                    <p>
                        This site sets a cookie to keep you signed in and to
                        remember which branch you picked. That is the whole of
                        it.
                    </p>
                    <p>
                        There is no advertising network, no analytics product
                        and no third party tracking script on this site or in
                        the app. Nobody is building a profile of you here, and
                        we have nothing to hand over to anyone who wanted one.
                    </p>
                </Clause>

                <Clause heading="Who else sees it">
                    <p>
                        We do not sell your details and we do not share them for
                        anyone else's advertising.
                    </p>
                    <ClauseList
                        items={[
                            'Our hosting provider holds the database and the files, as the shop uses them.',
                            'When you pay by EcoCash, InnBucks, card or bank transfer, that provider sees what you send them under their own terms, not ours.',
                            'If the law requires us to produce records, we comply, and no further.',
                        ]}
                    />
                </Clause>

                <Clause heading="How long we keep it">
                    <p>
                        Your record stays while you are a client of the shop.
                        Payment and sales records are kept longer because
                        accounting and tax rules require it, so a deletion
                        request will not always empty those.
                    </p>
                    <p>
                        Everything else we can delete on request, and we will
                        say plainly what we had to keep and why.
                    </p>
                </Clause>

                <Clause heading="Children">
                    <p>
                        We cut children's hair. A booking for a child is made by
                        their parent or guardian, who consents on their behalf.
                        For a child we hold the first name and the cut, and
                        nothing else. The contact details are the adult's.
                    </p>
                </Clause>

                <Clause heading="What you can ask us to do">
                    <p>
                        Under the Cyber and Data Protection Act [Chapter 12:07]
                        you may:
                    </p>
                    <ClauseList
                        items={[
                            'Ask what we hold about you, and get a copy.',
                            'Have anything wrong corrected.',
                            'Ask us to delete it, subject to the records we must keep.',
                            'Withdraw your consent to marketing at any time.',
                            'Object to what we are doing with it, and get an answer from a person.',
                        ]}
                    />
                    <p>
                        Ask on WhatsApp, on the phone or at the front desk. We
                        would rather sort it out directly, but if we do not, the
                        Postal and Telecommunications Regulatory Authority of
                        Zimbabwe (POTRAZ) is the data protection authority and
                        you are entitled to complain to them.
                    </p>
                </Clause>

                <Clause heading="How we look after it">
                    <ClauseList
                        items={[
                            'Passwords are stored as one way hashes. Nobody at the shop can read yours.',
                            'The site and the app talk to us over HTTPS.',
                            'Payment screenshots sit on private storage behind a permission check, never on a public URL.',
                            'Staff see only their own branch, and only what their job needs.',
                            'Staff accounts can carry two step verification.',
                        ]}
                    />
                </Clause>

                <Clause heading="If this page changes">
                    <p>
                        We will change the date at the top. If a change actually
                        affects what we do with your information, we will tell
                        you rather than rely on you re-reading this.
                    </p>
                </Clause>

                <Clause heading="Asking us something">
                    <p>
                        WhatsApp is the fastest way to reach the shop.
                        {branch?.phone && ' You can also call the branch.'}{' '}
                        Everything is on the{' '}
                        <Link href="/visit" className="text-gold underline">
                            find us
                        </Link>{' '}
                        page, and our{' '}
                        <Link href="/terms" className="text-gold underline">
                            terms
                        </Link>{' '}
                        cover booking, cancelling and paying.
                    </p>
                </Clause>
            </LegalBody>
        </>
    );
}
