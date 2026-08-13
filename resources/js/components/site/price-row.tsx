import { Clock, TriangleAlert } from 'lucide-react';
import type { Service } from '@/types/catalog';

/**
 * One line of the price list. The dotted leader keeps the name and the price
 * tied together on a wide screen without a table.
 */
export function PriceRow({ service }: { service: Service }) {
    return (
        <li className="border-bone/8 flex flex-col gap-1.5 border-b py-4 last:border-b-0 sm:flex-row sm:items-baseline sm:gap-4">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h3 className="text-bone font-semibold">{service.name}</h3>
                    <span className="text-smoke inline-flex items-center gap-1 text-xs">
                        <Clock className="size-3" aria-hidden="true" />
                        {service.duration_minutes} min
                    </span>
                    {service.requires_patch_test && (
                        <span className="text-gold inline-flex items-center gap-1 text-xs">
                            <TriangleAlert className="size-3" aria-hidden="true" />
                            Patch test {service.patch_test_lead_hours}h before
                        </span>
                    )}
                </div>
                {service.description && (
                    <p className="text-smoke mt-1 text-sm leading-relaxed">
                        {service.description}
                    </p>
                )}
            </div>

            <div
                className="border-bone/15 hidden flex-1 border-b border-dotted sm:block"
                aria-hidden="true"
            />

            <span className="text-gold site-display shrink-0 text-lg tabular-nums">
                {service.is_free ? 'Free' : (service.price?.formatted ?? '—')}
            </span>
        </li>
    );
}
