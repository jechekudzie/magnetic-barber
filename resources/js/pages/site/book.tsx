import { Head, router, useForm, useHttp } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarCheck,
    CalendarDays,
    Car,
    Check,
    Clock,
    DoorOpen,
    Loader2,
    MapPin,
    Phone,
    Scissors,
    Sparkles,
    TriangleAlert,
    User,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    DateModal,
    PickerField,
    TimeModal,
} from '@/components/site/booking/pickers';
import { Stepper } from '@/components/site/booking/stepper';
import { SiteButton, SiteLink } from '@/components/site/button';
import { Container } from '@/components/site/section';
import {
    bookableDays,
    firstOpenDay,
    formatCents,
    formatDuration,
    serviceIndex,
    summarise,
} from '@/lib/booking';
import { cn } from '@/lib/utils';
import type {
    Appointment,
    Availability,
    ClientLookup,
    Slot,
} from '@/types/booking';
import type {
    ServiceCategory,
    SiteShared,
    StaffMember,
    Style,
} from '@/types/catalog';

type BookProps = {
    site: SiteShared;
    categories: ServiceCategory[];
    barbers: StaffMember[];
    styles: Style[];
    /** First day with a genuinely free slot, worked out server side. */
    firstBookableDate: string | null;
    /** Set when the visitor arrived via "Book this cut" in the gallery. */
    preselectedStyle: Style | null;
};

type Mode = 'scheduled' | 'house_call';

/**
 * Day and time come before services on purpose: people want to know what is
 * even available before they start choosing. The grid is drawn against the
 * shortest service the branch sells, then re-checked once the real selection
 * is known.
 */
const STEPS = [
    { key: 'where', label: 'Where' },
    { key: 'when', label: 'Day and time' },
    { key: 'services', label: 'What you want' },
    { key: 'barber', label: 'Who cuts' },
    { key: 'you', label: 'You' },
];

export default function Book({
    site,
    categories,
    barbers,
    styles,
    firstBookableDate,
    preselectedStyle,
}: BookProps) {
    const branch = site.branch;

    const [step, setStep] = useState(0);
    const [furthest, setFurthest] = useState(0);

    const [mode, setMode] = useState<Mode>('scheduled');

    // Arriving from the gallery preselects the cut and the service it is
    // booked as, so the client is not asked to find it again in the menu.
    const [selected, setSelected] = useState<string[]>(() =>
        preselectedStyle?.service?.id ? [preselectedStyle.service.id] : [],
    );
    const [staff, setStaff] = useState<string>('any');
    const [styleId, setStyleId] = useState<string>(preselectedStyle?.id ?? '');

    const days = useMemo(() => bookableDays(branch), [branch]);
    const [date, setDate] = useState<string>(
        () => firstBookableDate ?? firstOpenDay(days) ?? '',
    );
    const [slot, setSlot] = useState<Slot | null>(null);

    const [dateOpen, setDateOpen] = useState(false);
    const [timeOpen, setTimeOpen] = useState(false);

    const index = useMemo(() => serviceIndex(categories), [categories]);
    const selection = useMemo(
        () => summarise(selected, index),
        [selected, index],
    );

    // A house call can only be for services that can leave the building.
    const visibleCategories = useMemo(
        () =>
            mode === 'scheduled'
                ? categories
                : categories
                      .map((category) => ({
                          ...category,
                          services: (category.services ?? []).filter(
                              (service) => service.is_house_call_eligible,
                          ),
                      }))
                      .filter((category) => (category.services ?? []).length > 0),
        [categories, mode],
    );

    const bookable = barbers.filter(
        (barber) =>
            barber.is_bookable &&
            (mode === 'scheduled' || barber.accepts_house_calls),
    );

    /* ---------------------------------------------------------------- slots */

    // The signature of the request the current answers imply. Comparing it to
    // what has been fetched gives "loading" without setState inside an effect.
    const slotKey = `${mode}|${date}|${staff}|${[...selected].sort().join(',')}`;
    const [fetched, setFetched] = useState<{
        key: string;
        data: Availability;
    } | null>(null);

    useEffect(() => {
        if (step < 1 || date === '') {
            return;
        }

        const controller = new AbortController();
        let cancelled = false;

        async function load() {
            const params = new URLSearchParams({ date, staff, type: mode });
            selection.services.forEach((service) =>
                params.append('service_ids[]', service.id),
            );

            try {
                const response = await fetch(
                    `/book/availability?${params.toString()}`,
                    {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    },
                );

                if (!response.ok) {
                    return;
                }

                const data = (await response.json()) as Availability;

                if (!cancelled) {
                    setFetched({ key: slotKey, data });
                }
            } catch {
                // An aborted request is the expected path when the answers
                // change faster than the network responds.
            }
        }

        load();

        return () => {
            cancelled = true;
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [step, slotKey]);

    const availability = fetched?.key === slotKey ? fetched.data : null;
    const loadingSlots = step >= 1 && availability === null;

    const slots =
        staff === 'any'
            ? (availability?.any_staff ?? [])
            : (availability?.staff.find((row) => row.id === staff)?.slots ?? []);

    // A slot picked against the provisional block may not fit the real one.
    const slotStillFits =
        slot === null ||
        availability === null ||
        slots.some((option) => option.start === slot.start);

    /* --------------------------------------------------------------- lookup */

    const lookup = useHttp<
        { phone: string },
        { data: ClientLookup; upcoming: Appointment[] }
    >({ phone: '' });

    const [known, setKnown] = useState<ClientLookup | null>(null);
    const [upcoming, setUpcoming] = useState<Appointment[]>([]);
    const [nameConfirmed, setNameConfirmed] = useState(false);
    const lookupTimer = useRef<number | null>(null);

    const form = useForm({
        type: 'scheduled' as Mode,
        name: '',
        phone: '',
        service_ids: [] as string[],
        staff: 'any',
        start: '',
        style: '',
        note: '',
        address_line: '',
        area: '',
        directions_note: '',
    });

    function checkPhone(phone: string) {
        lookup.setData('phone', phone);

        lookup.post('/book/lookup', {
            onSuccess: (response) => {
                setKnown(response.data);
                setUpcoming(response.upcoming ?? []);
            },
        });
    }

    /**
     * Runs while the number is being typed rather than on blur, so a returning
     * client is recognised before they reach for the name field. The result is
     * offered, not applied: we ask "is this you" instead of silently filling in
     * a name that might belong to whoever owned the number before.
     */
    function onPhoneChange(value: string) {
        form.setData('phone', value);
        setKnown(null);
        setNameConfirmed(false);

        if (lookupTimer.current !== null) {
            window.clearTimeout(lookupTimer.current);
        }

        if (value.replace(/\D/g, '').length < 9) {
            return;
        }

        // Waits for a pause in typing so one number is not looked up nine times.
        lookupTimer.current = window.setTimeout(() => checkPhone(value), 400);
    }

    function acceptKnownName() {
        if (known?.first_name) {
            form.setData('name', known.first_name);
            setNameConfirmed(true);
        }
    }

    /* --------------------------------------------------------------- submit */

    function submit() {
        if (slot === null) {
            return;
        }

        form.transform((data) => ({
            ...data,
            type: mode,
            service_ids: selection.services.map((service) => service.id),
            staff,
            start: slot.start,
            style: styleId,
        }));

        form.post('/book', { preserveScroll: true });
    }

    /* ---------------------------------------------------------------- steps */

    function goTo(next: number) {
        setStep(next);
        setFurthest((value) => Math.max(value, next));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    const selectedDay = days.find((day) => day.date === date);
    const dayLabel = selectedDay
        ? `${selectedDay.isToday ? 'Today' : selectedDay.weekdayLabel} ${selectedDay.dayNumber} ${selectedDay.monthLabel}`
        : null;

    const canContinue = [
        true,
        slot !== null,
        selected.length > 0 && slotStillFits,
        slotStillFits,
        form.data.name.trim().length > 1 &&
            form.data.phone.trim().length > 5 &&
            (mode === 'scheduled' || form.data.address_line.trim().length > 3),
    ][step];

    if (!branch) {
        return (
            <Container className="py-24">
                <p className="text-smoke">No branch is published yet.</p>
            </Container>
        );
    }

    return (
        <>
            <Head title="Book a chair" />

            <section className="site-glow border-bone/8 border-b">
                <Container className="py-10 sm:py-14">
                    <p className="site-eyebrow mb-3">Book a chair</p>
                    <h1 className="site-display text-3xl sm:text-4xl lg:text-5xl">
                        A few quick questions.
                    </h1>
                    <p className="text-smoke mt-3 text-sm sm:text-base">
                        {branch.name} · {branch.address.line}
                    </p>

                    {preselectedStyle && (
                        <div className="site-panel border-gold/50 bg-panel-alt mt-7 flex items-start gap-3 p-4">
                            <Scissors
                                className="text-gold mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <p className="text-sm">
                                <span className="font-semibold">
                                    Booking number {preselectedStyle.code}, the{' '}
                                    {preselectedStyle.name}.
                                </span>
                                {preselectedStyle.service && (
                                    <span className="text-smoke">
                                        {' '}
                                        We have added{' '}
                                        {preselectedStyle.service.name} for you.
                                    </span>
                                )}
                            </p>
                        </div>
                    )}

                    <div className="mt-8">
                        <Stepper
                            steps={STEPS}
                            current={step}
                            furthest={furthest}
                            onJump={goTo}
                        />
                    </div>
                </Container>
            </section>

            <Container className="py-10 sm:py-14">
                <div className="grid gap-8 lg:grid-cols-[1fr_20rem]">
                    <div
                        key={step}
                        className="animate-in fade-in slide-in-from-bottom-2 duration-300"
                    >
                        {/* 1. Where ----------------------------------------- */}
                        {step === 0 && (
                            <div className="space-y-6">
                                <StepHeading
                                    icon={MapPin}
                                    title="Where do you want the cut?"
                                    lede="Same barbers, same prices either way."
                                />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <ModeOption
                                        active={mode === 'scheduled'}
                                        onClick={() => {
                                            setMode('scheduled');
                                            setSlot(null);
                                        }}
                                        icon={DoorOpen}
                                        title="At the shop"
                                        lede="Book a chair at the branch."
                                        points={[
                                            'Pick your barber and your time',
                                            'The full menu is available',
                                            'Pay at the counter after your cut',
                                        ]}
                                    />

                                    <ModeOption
                                        active={mode === 'house_call'}
                                        disabled={!branch.house_call_enabled}
                                        onClick={() => {
                                            setMode('house_call');
                                            setSlot(null);
                                            setSelected([]);
                                            setStaff('any');
                                        }}
                                        icon={Car}
                                        title="House call"
                                        lede="The chair comes to you."
                                        points={[
                                            branch.house_call_radius_km
                                                ? `We travel up to ${branch.house_call_radius_km} km`
                                                : 'We travel to you',
                                            'Travel fee shown before you confirm',
                                            'Only barbers who travel are offered',
                                        ]}
                                    />
                                </div>

                                {!branch.house_call_enabled && (
                                    <p className="text-smoke text-sm">
                                        House calls are not switched on at this
                                        branch yet.
                                    </p>
                                )}
                            </div>
                        )}

                        {/* 2. When ------------------------------------------ */}
                        {step === 1 && (
                            <div className="space-y-6">
                                <StepHeading
                                    icon={CalendarDays}
                                    title="When suits you?"
                                    lede="Pick the day and time first, then we fit the services around it."
                                />

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <PickerField
                                        label="Day"
                                        value={dayLabel}
                                        placeholder="Choose a day"
                                        icon={CalendarDays}
                                        onClick={() => setDateOpen(true)}
                                    />
                                    <PickerField
                                        label="Time"
                                        value={slot?.label ?? null}
                                        placeholder={
                                            loadingSlots
                                                ? 'Checking the diary'
                                                : 'Choose a time'
                                        }
                                        icon={Clock}
                                        onClick={() => setTimeOpen(true)}
                                        disabled={date === ''}
                                    />
                                </div>

                                {availability?.provisional && (
                                    <p className="text-smoke text-xs leading-relaxed">
                                        Times shown allow{' '}
                                        {formatDuration(
                                            availability.duration_minutes,
                                        )}
                                        , our shortest service. Pick what you
                                        want next and we will confirm the time
                                        still works.
                                    </p>
                                )}

                                {availability?.closed && (
                                    <div className="site-panel flex items-start gap-3 p-4">
                                        <TriangleAlert
                                            className="text-gold mt-0.5 size-5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <p className="text-smoke text-sm">
                                            {availability.reason}
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* 3. Services -------------------------------------- */}
                        {step === 2 && (
                            <div className="space-y-8">
                                <StepHeading
                                    icon={Scissors}
                                    title="What are we doing?"
                                    lede="Pick as many as you like. The time and price add up as you go."
                                />

                                {!slotStillFits && (
                                    <SlotClash
                                        minutes={selection.totalMinutes}
                                        label={slot?.label ?? ''}
                                        onRepick={() => setTimeOpen(true)}
                                    />
                                )}

                                {visibleCategories.map((category) => (
                                    <div key={category.slug}>
                                        <h3 className="site-eyebrow mb-3">
                                            {category.name}
                                        </h3>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {(category.services ?? []).map(
                                                (service) => {
                                                    const on =
                                                        selected.includes(
                                                            service.id,
                                                        );

                                                    return (
                                                        <button
                                                            key={service.id}
                                                            type="button"
                                                            aria-pressed={on}
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
                                                                'site-panel flex items-start gap-3 p-4 text-left transition-all duration-200',
                                                                on
                                                                    ? 'border-gold bg-panel-alt'
                                                                    : 'hover:border-gold/40',
                                                            )}
                                                        >
                                                            <span
                                                                className={cn(
                                                                    'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors',
                                                                    on
                                                                        ? 'border-gold bg-gold text-ink'
                                                                        : 'border-bone/30',
                                                                )}
                                                                aria-hidden="true"
                                                            >
                                                                {on && (
                                                                    <Check className="size-3" />
                                                                )}
                                                            </span>

                                                            <span className="min-w-0 flex-1">
                                                                <span className="flex flex-wrap items-baseline justify-between gap-2">
                                                                    <span className="font-semibold">
                                                                        {
                                                                            service.name
                                                                        }
                                                                    </span>
                                                                    <span className="text-gold text-sm font-semibold tabular-nums">
                                                                        {service
                                                                            .price
                                                                            ?.formatted ??
                                                                            '—'}
                                                                    </span>
                                                                </span>
                                                                <span className="text-smoke mt-1 flex items-center gap-1.5 text-xs">
                                                                    <Clock
                                                                        className="size-3"
                                                                        aria-hidden="true"
                                                                    />
                                                                    {
                                                                        service.duration_minutes
                                                                    }{' '}
                                                                    min
                                                                    {service.requires_patch_test && (
                                                                        <span className="text-gold ml-2">
                                                                            Patch
                                                                            test
                                                                            needed
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </span>
                                                        </button>
                                                    );
                                                },
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* 4. Barber ---------------------------------------- */}
                        {step === 3 && (
                            <div className="space-y-6">
                                <StepHeading
                                    icon={Users}
                                    title="Who is cutting?"
                                    lede={
                                        mode === 'house_call'
                                            ? 'Only barbers who travel are shown.'
                                            : 'Pick a barber, or let us give you the first one free.'
                                    }
                                />

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <BarberOption
                                        active={staff === 'any'}
                                        onClick={() => setStaff('any')}
                                        name="Any barber"
                                        title="Usually the shortest wait"
                                    />

                                    {bookable.map((barber) => (
                                        <BarberOption
                                            key={barber.slug}
                                            active={staff === barber.id}
                                            onClick={() =>
                                                setStaff(barber.id ?? 'any')
                                            }
                                            name={barber.name}
                                            title={barber.title}
                                            photo={barber.photo_url}
                                        />
                                    ))}
                                </div>

                                {!slotStillFits && (
                                    <SlotClash
                                        minutes={selection.totalMinutes}
                                        label={slot?.label ?? ''}
                                        onRepick={() => setTimeOpen(true)}
                                    />
                                )}
                            </div>
                        )}

                        {/* 5. You ------------------------------------------- */}
                        {step === 4 && (
                            <div className="space-y-6">
                                <StepHeading
                                    icon={User}
                                    title="Last bit, who are you?"
                                    lede="Your number is your account. If you have been in before, we already have your details."
                                />

                                <div>
                                    <label
                                        htmlFor="phone"
                                        className="mb-2 block text-sm font-medium"
                                    >
                                        Mobile number
                                    </label>
                                    <div className="relative">
                                        <Phone
                                            className="text-smoke absolute top-1/2 left-4 size-4 -translate-y-1/2"
                                            aria-hidden="true"
                                        />
                                        <input
                                            id="phone"
                                            type="tel"
                                            inputMode="tel"
                                            autoComplete="tel"
                                            placeholder="078 187 9820"
                                            value={form.data.phone}
                                            onChange={(event) =>
                                                onPhoneChange(
                                                    event.target.value,
                                                )
                                            }
                                            className="border-bone/15 bg-panel text-bone placeholder:text-smoke focus:border-gold h-13 w-full rounded-xl border pr-4 pl-11 outline-none"
                                        />
                                        {lookup.processing && (
                                            <Loader2
                                                className="text-gold absolute top-1/2 right-4 size-4 -translate-y-1/2 animate-spin"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </div>
                                    {form.errors.phone && (
                                        <p className="text-stop mt-2 text-sm">
                                            {form.errors.phone}
                                        </p>
                                    )}
                                </div>

                                {known?.found && (
                                    <div className="site-panel border-gold/50 bg-panel-alt animate-in fade-in slide-in-from-top-2 p-4 duration-300">
                                        <div className="flex items-start gap-3">
                                            <Sparkles
                                                className="text-gold mt-0.5 size-5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <div className="min-w-0 flex-1 text-sm">
                                                <p className="font-semibold">
                                                    Is this you,{' '}
                                                    {known.first_name}?
                                                </p>
                                                <p className="text-smoke mt-0.5">
                                                    Account{' '}
                                                    {known.account_number}
                                                    {known.visit_count > 0 &&
                                                        ` · ${known.visit_count} ${known.visit_count === 1 ? 'visit' : 'visits'} with us`}
                                                    {known.last_visit &&
                                                        ` · last in ${known.last_visit}`}
                                                    {known.points > 0 &&
                                                        ` · ${known.points} points banked`}
                                                    .
                                                </p>

                                                {nameConfirmed ? (
                                                    <p className="text-gold mt-3 flex items-center gap-1.5 font-medium">
                                                        <Check
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Welcome back. We will
                                                        use your existing
                                                        details.
                                                    </p>
                                                ) : (
                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                        <SiteButton
                                                            size="sm"
                                                            onClick={
                                                                acceptKnownName
                                                            }
                                                        >
                                                            Yes, that is me
                                                        </SiteButton>
                                                        <SiteButton
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setKnown(null)
                                                            }
                                                        >
                                                            Not me
                                                        </SiteButton>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {known !== null && !known.found && (
                                    <div className="site-panel animate-in fade-in flex items-start gap-3 p-4 duration-300">
                                        <Sparkles
                                            className="text-gold mt-0.5 size-5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <p className="text-smoke text-sm">
                                            First time with us. We will open an
                                            account and issue your account
                                            number.
                                        </p>
                                    </div>
                                )}

                                {upcoming.length > 0 && (
                                    <div className="site-panel border-gold/40 p-4">
                                        <p className="text-sm font-semibold">
                                            You already have a booking
                                        </p>
                                        <ul className="text-smoke mt-2 space-y-1 text-sm">
                                            {upcoming.map((booking) => (
                                                <li key={booking.id}>
                                                    {booking.when_label} ·{' '}
                                                    {booking.reference}
                                                </li>
                                            ))}
                                        </ul>
                                        <p className="text-smoke mt-2 text-xs">
                                            Booking again adds a second visit,
                                            it does not replace that one.
                                        </p>
                                    </div>
                                )}

                                <div>
                                    <label
                                        htmlFor="name"
                                        className="mb-2 block text-sm font-medium"
                                    >
                                        Your name
                                    </label>
                                    <input
                                        id="name"
                                        type="text"
                                        autoComplete="name"
                                        placeholder="Tendai Moyo"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        className="border-bone/15 bg-panel text-bone placeholder:text-smoke focus:border-gold h-13 w-full rounded-xl border px-4 outline-none"
                                    />
                                    {form.errors.name && (
                                        <p className="text-stop mt-2 text-sm">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>

                                {mode === 'house_call' && (
                                    <div className="site-panel animate-in fade-in space-y-4 p-5 duration-300">
                                        <div className="flex items-center gap-2.5">
                                            <Car
                                                className="text-gold size-5"
                                                aria-hidden="true"
                                            />
                                            <h3 className="font-semibold">
                                                Where are we coming to?
                                            </h3>
                                        </div>

                                        <div>
                                            <label
                                                htmlFor="address_line"
                                                className="mb-2 block text-sm font-medium"
                                            >
                                                Street address
                                            </label>
                                            <input
                                                id="address_line"
                                                type="text"
                                                autoComplete="street-address"
                                                placeholder="12 Northolt Drive"
                                                value={form.data.address_line}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'address_line',
                                                        event.target.value,
                                                    )
                                                }
                                                className="border-bone/15 bg-panel-alt text-bone placeholder:text-smoke focus:border-gold h-13 w-full rounded-xl border px-4 outline-none"
                                            />
                                            {form.errors.address_line && (
                                                <p className="text-stop mt-2 text-sm">
                                                    {form.errors.address_line}
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <label
                                                htmlFor="area"
                                                className="mb-2 block text-sm font-medium"
                                            >
                                                Suburb or area
                                            </label>
                                            <input
                                                id="area"
                                                type="text"
                                                placeholder="Mount Pleasant"
                                                value={form.data.area}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'area',
                                                        event.target.value,
                                                    )
                                                }
                                                className="border-bone/15 bg-panel-alt text-bone placeholder:text-smoke focus:border-gold h-13 w-full rounded-xl border px-4 outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                htmlFor="directions_note"
                                                className="mb-2 block text-sm font-medium"
                                            >
                                                How to find you{' '}
                                                <span className="text-smoke font-normal">
                                                    (optional)
                                                </span>
                                            </label>
                                            <textarea
                                                id="directions_note"
                                                rows={2}
                                                placeholder="Green gate, second house after the shops"
                                                value={
                                                    form.data.directions_note
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'directions_note',
                                                        event.target.value,
                                                    )
                                                }
                                                className="border-bone/15 bg-panel-alt text-bone placeholder:text-smoke focus:border-gold w-full rounded-xl border px-4 py-3 outline-none"
                                            />
                                        </div>

                                        <p className="text-smoke text-xs">
                                            A travel fee is added to your bill
                                            and confirmed before your barber
                                            leaves.
                                        </p>
                                    </div>
                                )}

                                {styles.length > 0 && (
                                    <div>
                                        <label
                                            htmlFor="style"
                                            className="mb-2 block text-sm font-medium"
                                        >
                                            Style you want{' '}
                                            <span className="text-smoke font-normal">
                                                (optional)
                                            </span>
                                        </label>
                                        <select
                                            id="style"
                                            value={styleId}
                                            onChange={(event) =>
                                                setStyleId(event.target.value)
                                            }
                                            className="border-bone/15 bg-panel text-bone focus:border-gold h-13 w-full rounded-xl border px-4 outline-none"
                                        >
                                            <option value="">
                                                Let the barber advise
                                            </option>
                                            {styles.map((style) => (
                                                <option
                                                    key={style.id}
                                                    value={style.id}
                                                >
                                                    {style.code} · {style.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                <div>
                                    <label
                                        htmlFor="note"
                                        className="mb-2 block text-sm font-medium"
                                    >
                                        Anything we should know{' '}
                                        <span className="text-smoke font-normal">
                                            (optional)
                                        </span>
                                    </label>
                                    <textarea
                                        id="note"
                                        rows={3}
                                        value={form.data.note}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Grade 1 on the sides, leave the top"
                                        className="border-bone/15 bg-panel text-bone placeholder:text-smoke focus:border-gold w-full rounded-xl border px-4 py-3 outline-none"
                                    />
                                </div>

                                {form.errors.start && (
                                    <div className="site-panel border-stop/50 flex items-start gap-3 p-4">
                                        <TriangleAlert
                                            className="text-stop mt-0.5 size-5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <p className="text-sm">
                                            {form.errors.start}
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Navigation ------------------------------------- */}
                        <div className="mt-10 flex items-center justify-between gap-4">
                            {step > 0 ? (
                                <SiteButton
                                    variant="ghost"
                                    onClick={() => goTo(step - 1)}
                                >
                                    <ArrowLeft
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Back
                                </SiteButton>
                            ) : (
                                <span />
                            )}

                            {step < STEPS.length - 1 ? (
                                <SiteButton
                                    size="lg"
                                    disabled={!canContinue}
                                    onClick={() => goTo(step + 1)}
                                    className={cn(
                                        !canContinue &&
                                            'cursor-not-allowed opacity-40',
                                    )}
                                >
                                    Continue
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </SiteButton>
                            ) : (
                                <SiteButton
                                    size="lg"
                                    disabled={!canContinue || form.processing}
                                    onClick={submit}
                                    className={cn(
                                        (!canContinue || form.processing) &&
                                            'cursor-not-allowed opacity-60',
                                    )}
                                >
                                    {form.processing ? (
                                        <>
                                            <Loader2
                                                className="size-4 animate-spin"
                                                aria-hidden="true"
                                            />
                                            Booking
                                        </>
                                    ) : (
                                        <>
                                            <CalendarCheck
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Confirm booking
                                        </>
                                    )}
                                </SiteButton>
                            )}
                        </div>
                    </div>

                    {/* Running summary --------------------------------- */}
                    <aside className="lg:sticky lg:top-24 lg:self-start">
                        <div className="site-panel p-5">
                            <h2 className="site-eyebrow mb-4">Your booking</h2>

                            {/*
                              Changing branch reloads the wizard, because
                              prices, barbers and free slots are all per branch
                              and none of the answers so far would still hold.
                            */}
                            {mode === 'scheduled' && site.branches.length > 1 && (
                                <label className="mb-4 block">
                                    <span className="site-eyebrow mb-1.5 block">
                                        Branch
                                    </span>
                                    <select
                                        value={branch.slug}
                                        onChange={(event) =>
                                            router.get(
                                                '/book',
                                                { branch: event.target.value },
                                                { preserveState: false },
                                            )
                                        }
                                        className="border-bone/15 bg-panel-alt text-bone focus:border-gold h-11 w-full rounded-lg border px-3 text-sm outline-none"
                                    >
                                        {site.branches.map((option) => (
                                            <option
                                                key={option.slug}
                                                value={option.slug}
                                            >
                                                {option.name}
                                            </option>
                                        ))}
                                    </select>
                                    <span className="text-smoke mt-1.5 block text-xs">
                                        Switching starts the booking again:
                                        prices and free times differ per branch.
                                    </span>
                                </label>
                            )}

                            <dl className="space-y-2 text-sm">
                                {/* Which shop, or which address: "House call"
                                    on its own does not tell you where. */}
                                {mode === 'scheduled' ? (
                                    <SummaryRow
                                        label="At the shop"
                                        value={branch.name}
                                        detail={branch.address.line}
                                    />
                                ) : (
                                    <SummaryRow
                                        label="House call to"
                                        value={
                                            form.data.address_line.trim() === ''
                                                ? null
                                                : form.data.address_line
                                        }
                                        detail={
                                            form.data.area.trim() === ''
                                                ? null
                                                : form.data.area
                                        }
                                        onPick={() => goTo(4)}
                                        pickLabel="Add address"
                                    />
                                )}
                                <SummaryRow
                                    label="Day"
                                    value={slot ? dayLabel : null}
                                    onPick={() => {
                                        goTo(1);
                                        setDateOpen(true);
                                    }}
                                />
                                <SummaryRow
                                    label="Time"
                                    value={slot?.label ?? null}
                                    onPick={() => {
                                        goTo(1);
                                        setTimeOpen(true);
                                    }}
                                />
                                {styleId !== '' && (
                                    <SummaryRow
                                        label="Style"
                                        value={
                                            styles.find(
                                                (style) => style.id === styleId,
                                            )?.name ?? null
                                        }
                                    />
                                )}
                                <SummaryRow
                                    label="Barber"
                                    value={
                                        staff === 'any'
                                            ? 'Any barber'
                                            : (bookable.find(
                                                  (barber) =>
                                                      barber.id === staff,
                                              )?.name ?? 'Any barber')
                                    }
                                />
                            </dl>

                            <div className="border-bone/8 mt-4 border-t pt-4">
                                {selection.services.length === 0 ? (
                                    <p className="text-smoke text-sm">
                                        No services picked yet.
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {selection.services.map((service) => (
                                            <li
                                                key={service.id}
                                                className="flex justify-between gap-3 text-sm"
                                            >
                                                <span className="text-bone/85">
                                                    {service.name}
                                                </span>
                                                <span className="text-smoke shrink-0 tabular-nums">
                                                    {service.price?.formatted}
                                                </span>
                                            </li>
                                        ))}
                                        {mode === 'house_call' && (
                                            <li className="flex justify-between gap-3 text-sm">
                                                <span className="text-bone/85">
                                                    Travel fee
                                                </span>
                                                <span className="text-smoke shrink-0">
                                                    added at confirmation
                                                </span>
                                            </li>
                                        )}
                                    </ul>
                                )}
                            </div>

                            <div className="border-bone/8 mt-4 flex items-baseline justify-between border-t pt-4">
                                <span className="text-sm font-medium">
                                    {mode === 'house_call'
                                        ? 'Services'
                                        : 'Total'}
                                </span>
                                <span className="site-display text-gold text-2xl tabular-nums">
                                    {formatCents(
                                        selection.totalCents,
                                        selection.currency,
                                    )}
                                </span>
                            </div>

                            {selection.totalMinutes > 0 && (
                                <p className="text-smoke mt-2 text-sm">
                                    {formatDuration(selection.totalMinutes)} in
                                    the chair
                                </p>
                            )}

                            <p className="text-smoke mt-4 text-xs leading-relaxed">
                                Nothing is charged now. You pay after your cut.
                            </p>
                        </div>

                        {site.whatsapp_link && (
                            <SiteLink
                                href={site.whatsapp_link}
                                variant="outline"
                                size="sm"
                                external
                                className="mt-3 w-full"
                            >
                                Rather message us
                            </SiteLink>
                        )}
                    </aside>
                </div>
            </Container>

            <DateModal
                open={dateOpen}
                days={days}
                selected={date}
                onSelect={(next) => {
                    setDate(next);
                    setSlot(null);
                }}
                onClose={() => setDateOpen(false)}
            />

            <TimeModal
                open={timeOpen}
                slots={slots}
                selected={slot}
                loading={loadingSlots}
                reason={availability?.reason ?? null}
                onSelect={setSlot}
                onClose={() => setTimeOpen(false)}
            />
        </>
    );
}

function SlotClash({
    minutes,
    label,
    onRepick,
}: {
    minutes: number;
    label: string;
    onRepick: () => void;
}) {
    return (
        <div className="site-panel border-gold/60 flex items-start gap-3 p-4">
            <TriangleAlert
                className="text-gold mt-0.5 size-5 shrink-0"
                aria-hidden="true"
            />
            <div className="text-sm">
                <p className="font-semibold">
                    That no longer fits the time you picked.
                </p>
                <p className="text-smoke mt-0.5">
                    {formatDuration(minutes)} does not fit at {label}. Pick
                    another time, or drop a service.
                </p>
                <button
                    type="button"
                    onClick={onRepick}
                    className="text-gold mt-2 underline underline-offset-4"
                >
                    Pick another time
                </button>
            </div>
        </div>
    );
}

function SummaryRow({
    label,
    value,
    detail,
    onPick,
    pickLabel = 'Pick',
}: {
    label: string;
    value: string | null;
    detail?: string | null;
    onPick?: () => void;
    pickLabel?: string;
}) {
    return (
        <div className="flex justify-between gap-3">
            <dt className="text-smoke shrink-0">{label}</dt>
            <dd className="min-w-0 text-right">
                {value === null ? (
                    onPick ? (
                        <button
                            type="button"
                            onClick={onPick}
                            className="text-gold underline underline-offset-4"
                        >
                            {pickLabel}
                        </button>
                    ) : (
                        '—'
                    )
                ) : (
                    <>
                        <span className="block truncate">{value}</span>
                        {detail && (
                            <span className="text-smoke block truncate text-xs">
                                {detail}
                            </span>
                        )}
                    </>
                )}
            </dd>
        </div>
    );
}

function StepHeading({
    icon: Icon,
    title,
    lede,
}: {
    icon: typeof Scissors;
    title: string;
    lede: string;
}) {
    return (
        <div className="flex gap-4">
            <span className="bg-gold/12 text-gold flex size-11 shrink-0 items-center justify-center rounded-full">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <div>
                <h2 className="site-display text-2xl sm:text-3xl">{title}</h2>
                <p className="text-smoke mt-1 text-sm">{lede}</p>
            </div>
        </div>
    );
}

function ModeOption({
    active,
    onClick,
    icon: Icon,
    title,
    lede,
    points,
    disabled = false,
}: {
    active: boolean;
    onClick: () => void;
    icon: typeof DoorOpen;
    title: string;
    lede: string;
    points: string[];
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-pressed={active}
            className={cn(
                'site-panel flex flex-col p-6 text-left transition-all duration-200',
                disabled && 'cursor-not-allowed opacity-40',
                !disabled && active && 'border-gold bg-panel-alt',
                !disabled && !active && 'hover:border-gold/40',
            )}
        >
            <span
                className={cn(
                    'mb-5 flex size-12 items-center justify-center rounded-full',
                    active ? 'bg-gold text-ink' : 'bg-gold/12 text-gold',
                )}
            >
                <Icon className="size-6" aria-hidden="true" />
            </span>

            <span className="site-display text-2xl">{title}</span>
            <span className="text-gold mt-1 text-sm">{lede}</span>

            <ul className="mt-5 space-y-2">
                {points.map((point) => (
                    <li
                        key={point}
                        className="text-smoke flex gap-2.5 text-sm leading-relaxed"
                    >
                        <span
                            className="bg-gold mt-2 size-1 shrink-0 rounded-full"
                            aria-hidden="true"
                        />
                        {point}
                    </li>
                ))}
            </ul>
        </button>
    );
}

function BarberOption({
    active,
    onClick,
    name,
    title,
    photo,
}: {
    active: boolean;
    onClick: () => void;
    name: string;
    title: string | null;
    photo?: string | null;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'site-panel flex items-center gap-3 p-4 text-left transition-all duration-200',
                active ? 'border-gold bg-panel-alt' : 'hover:border-gold/40',
            )}
        >
            <span className="bg-panel-alt flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full">
                {photo ? (
                    <img src={photo} alt="" className="size-full object-cover" />
                ) : (
                    <Users className="text-gold/50 size-5" aria-hidden="true" />
                )}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-semibold">{name}</span>
                {title && (
                    <span className="text-smoke block truncate text-xs">
                        {title}
                    </span>
                )}
            </span>
            {active && (
                <Check className="text-gold size-5 shrink-0" aria-hidden="true" />
            )}
        </button>
    );
}
