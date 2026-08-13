import { Check } from 'lucide-react';
import { SiteLink } from '@/components/site/button';
import { cn } from '@/lib/utils';
import type { Plan } from '@/types/catalog';

export function PlanCard({ plan }: { plan: Plan }) {
    return (
        <article
            className={cn(
                'site-panel relative flex flex-col p-6 sm:p-7',
                plan.is_popular && 'border-gold/50 bg-panel-alt',
            )}
        >
            {plan.is_popular && (
                <span className="bg-gold text-ink absolute -top-3 left-6 rounded-full px-3 py-1 text-xs font-bold tracking-wide uppercase">
                    Most taken
                </span>
            )}

            <h3 className="site-display text-2xl">{plan.name}</h3>
            {plan.tagline && (
                <p className="text-gold mt-1 text-sm">{plan.tagline}</p>
            )}

            <p className="mt-5 flex items-baseline gap-1.5">
                <span className="site-display text-bone text-4xl tabular-nums">
                    {plan.price.formatted}
                </span>
                <span className="text-smoke text-sm">
                    / {plan.validity_days} days
                </span>
            </p>

            <p className="text-smoke mt-1 text-sm">
                {plan.type === 'unlimited'
                    ? 'Unlimited visits'
                    : `${plan.session_count} sessions`}
            </p>

            {plan.description && (
                <p className="text-smoke mt-5 text-sm leading-relaxed">
                    {plan.description}
                </p>
            )}

            {plan.perks.length > 0 && (
                <ul className="mt-6 space-y-2.5">
                    {plan.perks.map((perk) => (
                        <li
                            key={perk}
                            className="text-bone/85 flex gap-2.5 text-sm"
                        >
                            <Check
                                className="text-gold mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {perk}
                        </li>
                    ))}
                </ul>
            )}

            <SiteLink
                href="/book"
                variant={plan.is_popular ? 'gold' : 'outline'}
                className="mt-7 w-full"
            >
                Ask about this plan
            </SiteLink>
        </article>
    );
}
