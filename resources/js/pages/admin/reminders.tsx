import { Head, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    Check,
    Clock,
    Loader2,
    MessageCircle,
} from 'lucide-react';
import { useState } from 'react';
import { FormSection, TextField, Toggle } from '@/components/admin/form';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type DueClient = {
    id: string;
    name: string;
    phone: string | null;
    whatsapp: string | null;
    branch: string | null;
    account_number: string | null;
    visit_count: number;
    last_visit: string;
    days_since: number;
    threshold: number;
    days_over: number;
    days_until: number;
    preferred_cycle_days: number | null;
    average_cycle_days: number | null;
    marketing_opt_in: boolean;
};

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    settings: { threshold: number; warn: number };
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

export default function Reminders({ settings, due, soon, queued }: Props) {
    const [editing, setEditing] = useState<DueClient | null>(null);

    const rules = useForm({
        threshold: settings.threshold,
        warn: settings.warn,
    });

    return (
        <>
            <Head title="Reminders" />

            <AdminPage
                title="Reminders"
                lede="Who has stopped coming, and who is about to. Message them before they find another barber."
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
                        value={soon.length}
                        hint={`Inside ${settings.warn} days of their next cut`}
                    />
                    <StatCard label="Reminders queued" value={queued.length} />
                </div>

                {/* ------------------------------------------ the rule */}
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
                            hint="Fills the almost due list"
                            value={rules.data.warn}
                            error={rules.errors.warn}
                            onChange={(value) =>
                                rules.setData('warn', Number(value))
                            }
                            className="w-48"
                        />
                        <Button
                            onClick={() => rules.put('/admin/reminders/settings')}
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

                {/* ---------------------------------------------- overdue */}
                <Panel
                    title="Overdue"
                    description="Longest gap first. The WhatsApp link opens a message already written."
                >
                    {due.length === 0 ? (
                        <p className="text-muted-foreground p-6 text-center text-sm">
                            Nobody is overdue. Everyone has been in, or is
                            already booked.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {due.map((client) => (
                                <ClientRow
                                    key={client.id}
                                    client={client}
                                    tone="due"
                                    onEdit={() => setEditing(client)}
                                />
                            ))}
                        </ul>
                    )}
                </Panel>

                {/* ------------------------------------------ almost due */}
                <Panel
                    title="Almost due"
                    description={`Within ${settings.warn} days of their next cut. Worth a nudge before they lapse.`}
                >
                    {soon.length === 0 ? (
                        <p className="text-muted-foreground p-6 text-center text-sm">
                            Nobody is close yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {soon.map((client) => (
                                <ClientRow
                                    key={client.id}
                                    client={client}
                                    tone="soon"
                                    onEdit={() => setEditing(client)}
                                />
                            ))}
                        </ul>
                    )}
                </Panel>

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
                                        <Check className="size-4" aria-hidden="true" />
                                        Messaged
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                )}

                <div className="text-muted-foreground flex items-start gap-3 rounded-xl border p-4 text-sm">
                    <BellRing className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        Reminders are queued nightly and messaged by hand from
                        here. Automatic WhatsApp sending needs the Cloud API
                        connected and its templates approved, which takes days,
                        so it is worth starting that early.
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
        </>
    );
}

function ClientRow({
    client,
    tone,
    onEdit,
}: {
    client: DueClient;
    tone: 'due' | 'soon';
    onEdit: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center gap-3 p-4">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{client.name}</span>
                    {tone === 'due' ? (
                        <Pill tone="warn">
                            {client.days_over === 0
                                ? 'Due today'
                                : `${client.days_over} days over`}
                        </Pill>
                    ) : (
                        <Pill tone="gold">
                            {client.days_until === 0
                                ? 'Due today'
                                : `Due in ${client.days_until}`}
                        </Pill>
                    )}
                    {client.preferred_cycle_days !== null && (
                        <Pill tone="good">
                            Asked for every {client.preferred_cycle_days} days
                        </Pill>
                    )}
                </div>
                <p className="text-muted-foreground mt-0.5 text-sm">
                    Last in {client.last_visit} · {client.days_since} days ago ·{' '}
                    {client.visit_count} visits
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
                {client.whatsapp && (
                    <Button size="sm" asChild>
                        <a
                            href={client.whatsapp}
                            target="_blank"
                            rel="noreferrer noopener"
                        >
                            <MessageCircle className="size-4" aria-hidden="true" />
                            Message
                        </a>
                    </Button>
                )}
            </div>
        </li>
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
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <button
                type="button"
                aria-label="Close"
                onClick={onClose}
                className="absolute inset-0 bg-black/40"
            />

            <div className="bg-card relative w-full max-w-md rounded-xl border p-5 shadow-xl">
                <h2 className="font-semibold">{client.name}</h2>
                <p className="text-muted-foreground mt-0.5 mb-4 text-sm">
                    How often do they want a cut? Leave blank to use the shop
                    rule of {shopThreshold} days.
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
                    <span className="text-sm font-medium">
                        Or a number of days
                    </span>
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
                    onChange={(value) =>
                        form.setData('reminders_enabled', value)
                    }
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
