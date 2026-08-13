import { Head, useForm } from '@inertiajs/react';
import { Gem, Loader2 } from 'lucide-react';
import { FormSection, SelectField, TextField } from '@/components/admin/form';
import { AdminPage, Panel, Pill, StatCard } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch, Money } from '@/types/catalog';

type Client = {
    id: string;
    name: string;
    account_number: string | null;
    visit_count: number;
    points: number;
    redeemable: boolean;
    worth: Money;
};

type Props = {
    branchContext: { current: Branch | null; available: Branch[] };
    rule: {
        name: string;
        points_per_visit: number;
        points_per_currency_unit: number;
        redemption_threshold: number;
        redemption_value: number;
        points_expiry_months: number | null;
    };
    clients: Client[];
    recent: {
        id: number;
        client: string | null;
        type: string;
        points: number;
        balance_after: number;
        description: string | null;
        at: string | null;
    }[];
    totals: { issued: number; redeemed: number; outstanding: number };
};

export default function Loyalty({ rule, clients, recent, totals }: Props) {
    const rules = useForm({ ...rule });
    const adjust = useForm({ client: '', points: 5, reason: '' });

    return (
        <>
            <Head title="Loyalty" />

            <AdminPage
                title="Loyalty"
                lede="Points are earned when a booking is marked complete. The balance is the sum of the ledger, never a stored number."
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Points outstanding"
                        value={totals.outstanding}
                        hint="What clients are holding"
                        accent
                    />
                    <StatCard label="Issued all time" value={totals.issued} />
                    <StatCard label="Redeemed" value={totals.redeemed} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <FormSection
                        title="Earn and redeem rules"
                        description="One rule is in force at a time. Saving replaces it."
                    >
                        <TextField
                            label="Rule name"
                            required
                            value={rules.data.name}
                            error={rules.errors.name}
                            onChange={(value) => rules.setData('name', value)}
                            placeholder="Standard"
                        />

                        <div className="flex flex-wrap gap-4">
                            <TextField
                                label="Points per visit"
                                required
                                type="number"
                                min="0"
                                hint="Awarded on every completed cut"
                                value={rules.data.points_per_visit}
                                error={rules.errors.points_per_visit}
                                onChange={(value) =>
                                    rules.setData(
                                        'points_per_visit',
                                        Number(value),
                                    )
                                }
                                className="w-40"
                            />
                            <TextField
                                label="Points per $1 spent"
                                type="number"
                                min="0"
                                step="0.5"
                                hint="On top of the per visit points"
                                value={rules.data.points_per_currency_unit}
                                error={rules.errors.points_per_currency_unit}
                                onChange={(value) =>
                                    rules.setData(
                                        'points_per_currency_unit',
                                        Number(value),
                                    )
                                }
                                className="w-44"
                            />
                        </div>

                        <div className="flex flex-wrap gap-4">
                            <TextField
                                label="Redeem at"
                                required
                                type="number"
                                min="1"
                                hint="Points needed to redeem"
                                value={rules.data.redemption_threshold}
                                error={rules.errors.redemption_threshold}
                                onChange={(value) =>
                                    rules.setData(
                                        'redemption_threshold',
                                        Number(value),
                                    )
                                }
                                className="w-40"
                            />
                            <TextField
                                label="Worth"
                                required
                                type="number"
                                min="0"
                                step="0.5"
                                hint="What that threshold buys"
                                value={rules.data.redemption_value}
                                error={rules.errors.redemption_value}
                                onChange={(value) =>
                                    rules.setData(
                                        'redemption_value',
                                        Number(value),
                                    )
                                }
                                className="w-36"
                            />
                            <TextField
                                label="Expire after (months)"
                                type="number"
                                min="1"
                                hint="Blank means never"
                                value={rules.data.points_expiry_months ?? ''}
                                error={rules.errors.points_expiry_months}
                                onChange={(value) =>
                                    rules.setData(
                                        'points_expiry_months',
                                        value === '' ? null : Number(value),
                                    )
                                }
                                className="w-44"
                            />
                        </div>

                        <p className="text-muted-foreground bg-muted/50 rounded-lg p-3 text-sm">
                            As set: a client earns{' '}
                            <strong>{rules.data.points_per_visit} points</strong>{' '}
                            per cut, and can redeem{' '}
                            <strong>${rules.data.redemption_value}</strong> once
                            they reach{' '}
                            <strong>
                                {rules.data.redemption_threshold} points
                            </strong>{' '}
                            — about{' '}
                            {Math.ceil(
                                rules.data.redemption_threshold /
                                    Math.max(rules.data.points_per_visit, 1),
                            )}{' '}
                            visits.
                        </p>

                        <Button
                            onClick={() => rules.put('/admin/loyalty')}
                            disabled={rules.processing}
                        >
                            {rules.processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            Save rules
                        </Button>
                    </FormSection>

                    <FormSection
                        title="Manual adjustment"
                        description="For the times the shop owes somebody points the system cannot know about. Every adjustment is logged against you."
                    >
                        <SelectField
                            label="Client"
                            required
                            placeholder="Choose a client"
                            value={adjust.data.client}
                            error={adjust.errors.client}
                            onChange={(value) => adjust.setData('client', value)}
                            options={clients.map((client) => ({
                                value: client.id,
                                label: `${client.name} (${client.points} pts)`,
                            }))}
                        />
                        <TextField
                            label="Points"
                            required
                            type="number"
                            hint="Negative to deduct"
                            value={adjust.data.points}
                            error={adjust.errors.points}
                            onChange={(value) =>
                                adjust.setData('points', Number(value))
                            }
                            className="w-40"
                        />
                        <TextField
                            label="Reason"
                            required
                            value={adjust.data.reason}
                            error={adjust.errors.reason}
                            onChange={(value) => adjust.setData('reason', value)}
                            placeholder="Goodwill after a late start"
                        />
                        <Button
                            onClick={() =>
                                adjust.post('/admin/loyalty/adjust', {
                                    preserveScroll: true,
                                    onSuccess: () => adjust.reset(),
                                })
                            }
                            disabled={adjust.processing}
                        >
                            Apply adjustment
                        </Button>
                    </FormSection>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Top balances">
                        {clients.length === 0 ? (
                            <p className="text-muted-foreground p-5 text-sm">
                                No points issued yet. Complete a booking and the
                                client earns their first.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {clients.map((client) => (
                                    <li
                                        key={client.id}
                                        className="flex items-center gap-3 p-4"
                                    >
                                        <Gem
                                            className="text-primary size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {client.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {client.account_number} ·{' '}
                                                {client.visit_count} visits
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-semibold tabular-nums">
                                                {client.points} pts
                                            </p>
                                            {client.redeemable && (
                                                <Pill tone="good">
                                                    Worth {client.worth.formatted}
                                                </Pill>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel title="Recent ledger entries" description="Append only">
                        {recent.length === 0 ? (
                            <p className="text-muted-foreground p-5 text-sm">
                                Nothing yet.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {recent.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex items-center gap-3 px-5 py-3"
                                    >
                                        <span
                                            className={
                                                row.points >= 0
                                                    ? 'w-12 shrink-0 font-semibold tabular-nums text-emerald-600'
                                                    : 'text-destructive w-12 shrink-0 font-semibold tabular-nums'
                                            }
                                        >
                                            {row.points > 0 ? '+' : ''}
                                            {row.points}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate">
                                                {row.client}
                                            </p>
                                            <p className="text-muted-foreground truncate text-xs">
                                                {row.description} · {row.at}
                                            </p>
                                        </div>
                                        <span className="text-muted-foreground shrink-0 tabular-nums">
                                            {row.balance_after}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </AdminPage>
        </>
    );
}

Loyalty.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Loyalty', href: '/admin/loyalty' },
    ],
};
