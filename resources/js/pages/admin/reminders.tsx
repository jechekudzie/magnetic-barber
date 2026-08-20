import { Head, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    Check,
    Clock,
    Copy,
    Loader2,
    Phone as PhoneIcon,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { FormSection, TextArea, TextField, Toggle } from '@/components/admin/form';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { WhatsAppIcon } from '@/components/whatsapp-icon';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type DueClient = {
    id: string;
    name: string;
    phone: string | null;
    phone_display: string | null;
    whatsapp_number: string | null;
    message: string;
    branch: string | null;
    account_number: string | null;
    visit_count: number;
    last_visit: string;
    days_since: number;
    threshold: number;
    days_over: number;
    days_until: number;
    due_on: string;
    last_messaged: string | null;
    preferred_cycle_days: number | null;
    average_cycle_days: number | null;
    marketing_opt_in: boolean;
};

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    can: { see_contact: boolean; send: boolean; manage_settings: boolean };
    settings: {
        threshold: number;
        warn: number;
        horizon: number;
        message: string;
    };
    due: DueClient[];
    soon: DueClient[];
    queued: {
        id: number;
        client: string | null;
        days_since_visit: number | null;
        queued_at: string | null;
    }[];
};

const PRESETS = [
    { days: 7, label: 'Weekly' },
    { days: 14, label: '2 weeks' },
    { days: 21, label: '3 weeks' },
    { days: 28, label: '4 weeks' },
];

/**
 * One timeline, not two lists. Everybody has a date their next cut is due;
 * the filter just decides how far along that line you are looking.
 */
type FilterKey = 'overdue' | 'week' | 'three_weeks' | 'all';

const FILTERS: { key: FilterKey; label: string; within: number | null }[] = [
    { key: 'overdue', label: 'Overdue', within: 0 },
    { key: 'week', label: 'This week', within: 7 },
    { key: 'three_weeks', label: 'Next 3 weeks', within: 21 },
    { key: 'all', label: 'Everyone', within: null },
];

export default function Reminders({ can, settings, due, soon, queued }: Props) {
    const [editing, setEditing] = useState<DueClient | null>(null);
    const [messaging, setMessaging] = useState<DueClient | null>(null);
    const [filter, setFilter] = useState<FilterKey>('overdue');
    const [search, setSearch] = useState('');

    const everyone = useMemo(() => [...due, ...soon], [due, soon]);

    /** Overdue counts as within any window, so it never hides from a filter. */
    const counts = useMemo(
        () =>
            Object.fromEntries(
                FILTERS.map((option) => [
                    option.key,
                    everyone.filter((client) =>
                        option.within === null
                            ? true
                            : client.days_until <= option.within,
                    ).length,
                ]),
            ) as Record<FilterKey, number>,
        [everyone],
    );

    const shown = useMemo(() => {
        const within = FILTERS.find((option) => option.key === filter)?.within;
        const term = search.trim().toLowerCase();

        return everyone
            .filter((client) =>
                within === null || within === undefined
                    ? true
                    : client.days_until <= within,
            )
            .filter(
                (client) =>
                    term === '' ||
                    client.name.toLowerCase().includes(term) ||
                    (client.phone ?? '').includes(term) ||
                    (client.phone_display ?? '').includes(term),
            )
            .sort((a, b) => b.days_over - a.days_over);
    }, [everyone, filter, search]);

    const rules = useForm({
        threshold: settings.threshold,
        warn: settings.warn,
        message: settings.message,
    });

    return (
        <>
            <Head title="Reminders" />

            <AdminPage
                title="Reminders"
                lede="Who has stopped coming, and who is about to. Message them before they find another barber."
                action={
                    <span className="inline-flex items-center gap-2 rounded-full bg-amber-500/15 px-4 py-2 text-sm font-semibold text-amber-700 dark:text-amber-400">
                        <BellRing className="size-4" aria-hidden="true" />
                        {due.length} to chase
                    </span>
                }
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Overdue a cut"
                        value={due.length}
                        hint={`More than ${settings.threshold} days`}
                        accent
                    />
                    <StatCard
                        label="Due within days"
                        value={
                            soon.filter(
                                (client) => client.days_until <= settings.warn,
                            ).length
                        }
                        hint={`Inside ${settings.warn} days of their next cut`}
                    />
                    <StatCard label="Reminders queued" value={queued.length} />
                </div>

                {/* ------------------------------------------ the list */}
                <Panel>
                    <div className="flex flex-wrap items-center gap-3 border-b p-4">
                        <div className="bg-muted flex flex-wrap rounded-md p-0.5">
                            {FILTERS.map((option) => (
                                <button
                                    key={option.key}
                                    type="button"
                                    onClick={() => setFilter(option.key)}
                                    className={cn(
                                        'rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                        filter === option.key
                                            ? 'bg-card shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {option.label}
                                    <span
                                        className={cn(
                                            'ml-1.5 text-xs tabular-nums',
                                            filter === option.key
                                                ? 'text-muted-foreground'
                                                : 'opacity-60',
                                        )}
                                    >
                                        {counts[option.key]}
                                    </span>
                                </button>
                            ))}
                        </div>

                        <span className="relative ml-auto w-full sm:w-64">
                            <Search
                                className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Name or number"
                                className="pl-9"
                            />
                        </span>
                    </div>

                    {shown.length === 0 ? (
                        <p className="text-muted-foreground p-8 text-center text-sm">
                            {filter === 'overdue'
                                ? 'Nobody is overdue. Everyone has been in, or is already booked.'
                                : 'Nobody falls in that window.'}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {shown.map((client) => (
                                <ClientRow
                                    key={client.id}
                                    client={client}
                                    canSend={can.send}
                                    canSeeContact={can.see_contact}
                                    onEdit={() => setEditing(client)}
                                    onMessage={() => setMessaging(client)}
                                />
                            ))}
                        </ul>
                    )}
                </Panel>

                {/* ------------------------------------------ the rule */}
                {can.manage_settings && (
                    <FormSection
                        title="The shop's rule"
                        description="Used for every client who has not told you their own rhythm."
                    >
                        <div className="flex flex-wrap gap-2">
                            {PRESETS.map((preset) => (
                                <button
                                    key={preset.days}
                                    type="button"
                                    onClick={() =>
                                        rules.setData('threshold', preset.days)
                                    }
                                    className={cn(
                                        'rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
                                        rules.data.threshold === preset.days
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:border-primary/50',
                                    )}
                                >
                                    {preset.label}
                                </button>
                            ))}
                        </div>

                        <div className="flex flex-wrap items-end gap-4">
                            <TextField
                                label="Chase after (days)"
                                required
                                type="number"
                                min="1"
                                value={rules.data.threshold}
                                error={rules.errors.threshold}
                                onChange={(value) =>
                                    rules.setData('threshold', Number(value))
                                }
                                className="w-44"
                            />
                            <TextField
                                label="Warn me this far ahead"
                                required
                                type="number"
                                min="0"
                                hint="Fills the almost due count"
                                value={rules.data.warn}
                                error={rules.errors.warn}
                                onChange={(value) =>
                                    rules.setData('warn', Number(value))
                                }
                                className="w-48"
                            />
                        </div>

                        <TextArea
                            label="The message"
                            required
                            rows={3}
                            hint="{name} {days} {shop} {branch} are filled in for each client. Reception can still edit it before it sends."
                            value={rules.data.message}
                            error={rules.errors.message}
                            onChange={(value) => rules.setData('message', value)}
                        />

                        <div>
                            <Button
                                onClick={() =>
                                    rules.put('/admin/reminders/settings', {
                                        preserveScroll: true,
                                    })
                                }
                                disabled={rules.processing || !rules.isDirty}
                            >
                                {rules.processing && (
                                    <Loader2
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                )}
                                Save rule
                            </Button>
                        </div>
                    </FormSection>
                )}

                {queued.length > 0 && (
                    <Panel
                        title="Queued"
                        description="Raised by the nightly job. Mark one off once you have actually messaged them."
                    >
                        <ul className="divide-y">
                            {queued.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex items-center justify-between gap-3 p-3 text-sm"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {row.client}
                                        </span>
                                        <span className="text-muted-foreground ml-2 text-xs">
                                            {row.days_since_visit} days · raised{' '}
                                            {row.queued_at}
                                        </span>
                                    </span>
                                    {can.send && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.put(
                                                    `/admin/reminders/${row.id}/sent`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Check
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Messaged
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Panel>
                )}

                <div className="text-muted-foreground flex items-start gap-3 rounded-xl border p-4 text-sm">
                    <BellRing className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        WhatsApp opens with the message already written, and the
                        client is logged as chased once you send it. Sending
                        automatically, without opening WhatsApp, needs the Cloud
                        API connected and its templates approved.
                    </p>
                </div>
            </AdminPage>

            {editing && (
                <ClientCycleDialog
                    client={editing}
                    shopThreshold={settings.threshold}
                    onClose={() => setEditing(null)}
                />
            )}

            {messaging && (
                <MessageDialog
                    client={messaging}
                    onClose={() => setMessaging(null)}
                />
            )}
        </>
    );
}

function ClientRow({
    client,
    canSend,
    canSeeContact,
    onEdit,
    onMessage,
}: {
    client: DueClient;
    canSend: boolean;
    canSeeContact: boolean;
    onEdit: () => void;
    onMessage: () => void;
}) {
    const [copied, setCopied] = useState(false);
    const overdue = client.days_over >= 0;

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-3 p-4">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{client.name}</span>
                    {overdue ? (
                        <Pill tone="warn">
                            {client.days_over === 0
                                ? 'Due today'
                                : `${client.days_over} days over`}
                        </Pill>
                    ) : (
                        <Pill tone="gold">
                            {client.days_until === 0
                                ? 'Due today'
                                : `Due in ${client.days_until} days`}
                        </Pill>
                    )}
                    {client.preferred_cycle_days !== null && (
                        <Pill tone="good">
                            Asked for every {client.preferred_cycle_days} days
                        </Pill>
                    )}
                    {client.last_messaged && (
                        <Pill tone="neutral">
                            Messaged {client.last_messaged}
                        </Pill>
                    )}
                </div>

                {/* The number, big enough to read across a counter. */}
                <div className="mt-1 flex flex-wrap items-center gap-2">
                    {canSeeContact && client.phone ? (
                        <>
                            <a
                                href={`tel:${client.phone}`}
                                className="hover:text-primary inline-flex items-center gap-1.5 font-medium tabular-nums transition-colors"
                            >
                                <PhoneIcon
                                    className="text-muted-foreground size-3.5"
                                    aria-hidden="true"
                                />
                                {client.phone_display}
                            </a>
                            <button
                                type="button"
                                aria-label={`Copy ${client.name}'s number`}
                                onClick={() => {
                                    void navigator.clipboard.writeText(
                                        client.phone ?? '',
                                    );
                                    setCopied(true);
                                    window.setTimeout(
                                        () => setCopied(false),
                                        1500,
                                    );
                                }}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                {copied ? (
                                    <Check className="size-3.5 text-emerald-600" />
                                ) : (
                                    <Copy className="size-3.5" />
                                )}
                            </button>
                        </>
                    ) : (
                        <span className="text-muted-foreground font-medium tabular-nums">
                            {client.phone_display ?? 'No number'}
                        </span>
                    )}
                </div>

                <p className="text-muted-foreground mt-0.5 text-sm">
                    Last in {client.last_visit} · {client.days_since} days ago ·{' '}
                    {client.visit_count} visits · due {client.due_on}
                    {client.average_cycle_days !== null &&
                        ` · usually every ${client.average_cycle_days}`}
                    {client.branch && ` · ${client.branch}`}
                </p>
            </div>

            <div className="flex shrink-0 gap-2">
                <Button size="sm" variant="outline" onClick={onEdit}>
                    <Clock className="size-4" aria-hidden="true" />
                    How often
                </Button>
                {canSend && client.whatsapp_number && (
                    <Button
                        size="sm"
                        onClick={onMessage}
                        className="bg-[#25D366] text-white hover:bg-[#1da851]"
                    >
                        <WhatsAppIcon />
                        Message
                    </Button>
                )}
            </div>
        </li>
    );
}

/**
 * Read it before it goes. This is a real message to a real client, and the
 * shop's tone matters more than saving a click.
 */
function MessageDialog({
    client,
    onClose,
}: {
    client: DueClient;
    onClose: () => void;
}) {
    const [text, setText] = useState(client.message);

    function send() {
        window.open(
            `https://wa.me/${client.whatsapp_number}?text=${encodeURIComponent(text)}`,
            '_blank',
            'noopener,noreferrer',
        );

        router.put(
            '/admin/reminders/messaged',
            { client: client.id, days_since_visit: client.days_since },
            { preserveScroll: true, onFinish: onClose },
        );
    }

    return (
        <Dialog onClose={onClose}>
            <h2 className="font-semibold">Message {client.name}</h2>
            <p className="text-muted-foreground mt-0.5 mb-4 text-sm tabular-nums">
                {client.phone_display} · last in {client.days_since} days ago
            </p>

            <label className="flex flex-col gap-1.5">
                <span className="text-sm font-medium">What they will get</span>
                <textarea
                    value={text}
                    rows={5}
                    onChange={(event) => setText(event.target.value)}
                    className="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                />
                <span className="text-muted-foreground text-xs">
                    {text.length} characters
                </span>
            </label>

            <div className="mt-5 flex gap-2">
                <Button
                    className="flex-1 bg-[#25D366] text-white hover:bg-[#1da851]"
                    disabled={text.trim() === ''}
                    onClick={send}
                >
                    <WhatsAppIcon />
                    Open WhatsApp
                </Button>
                <Button variant="outline" onClick={onClose}>
                    Cancel
                </Button>
            </div>
        </Dialog>
    );
}

/**
 * What this client asked for. Overrides the shop rule for them alone.
 */
function ClientCycleDialog({
    client,
    shopThreshold,
    onClose,
}: {
    client: DueClient;
    shopThreshold: number;
    onClose: () => void;
}) {
    const form = useForm({
        client: client.id,
        preferred_cycle_days: client.preferred_cycle_days ?? '',
        reminders_enabled: true,
    });

    return (
        <Dialog onClose={onClose}>
            <h2 className="font-semibold">{client.name}</h2>
            <p className="text-muted-foreground mt-0.5 mb-4 text-sm">
                How often do they want a cut? Leave blank to use the shop rule
                of {shopThreshold} days.
            </p>

            <div className="mb-4 flex flex-wrap gap-2">
                {PRESETS.map((preset) => (
                    <button
                        key={preset.days}
                        type="button"
                        onClick={() =>
                            form.setData('preferred_cycle_days', preset.days)
                        }
                        className={cn(
                            'rounded-full border px-3 py-1.5 text-sm transition-colors',
                            form.data.preferred_cycle_days === preset.days
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:border-primary/50',
                        )}
                    >
                        {preset.label}
                    </button>
                ))}
                <button
                    type="button"
                    onClick={() => form.setData('preferred_cycle_days', '')}
                    className={cn(
                        'rounded-full border px-3 py-1.5 text-sm transition-colors',
                        form.data.preferred_cycle_days === ''
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:border-primary/50',
                    )}
                >
                    Shop rule
                </button>
            </div>

            <label className="mb-4 flex flex-col gap-1.5">
                <span className="text-sm font-medium">Or a number of days</span>
                <Input
                    type="number"
                    min="1"
                    value={form.data.preferred_cycle_days}
                    onChange={(event) =>
                        form.setData(
                            'preferred_cycle_days',
                            event.target.value === ''
                                ? ''
                                : Number(event.target.value),
                        )
                    }
                    className="w-32"
                />
                {form.errors.preferred_cycle_days && (
                    <span className="text-destructive text-xs">
                        {form.errors.preferred_cycle_days}
                    </span>
                )}
            </label>

            <Toggle
                label="Chase this client"
                hint="Off means they are never listed as overdue"
                checked={form.data.reminders_enabled}
                onChange={(value) => form.setData('reminders_enabled', value)}
            />

            <div className="mt-5 flex gap-2">
                <Button
                    className="flex-1"
                    disabled={form.processing}
                    onClick={() =>
                        form.put('/admin/reminders/client', {
                            preserveScroll: true,
                            onSuccess: onClose,
                        })
                    }
                >
                    Save
                </Button>
                <Button variant="outline" onClick={onClose}>
                    Cancel
                </Button>
            </div>
        </Dialog>
    );
}

function Dialog({
    children,
    onClose,
}: {
    children: React.ReactNode;
    onClose: () => void;
}) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <button
                type="button"
                aria-label="Close"
                onClick={onClose}
                className="absolute inset-0 bg-black/40"
            />
            <div className="bg-card relative w-full max-w-md rounded-xl border p-5 shadow-xl">
                {children}
            </div>
        </div>
    );
}

Reminders.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reminders', href: '/admin/reminders' },
    ],
};
