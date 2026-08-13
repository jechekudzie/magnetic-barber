import { Link } from '@inertiajs/react';
import { Scissors } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Style } from '@/types/catalog';

/**
 * The number is the point: a client says "number 03" over WhatsApp and every
 * barber cuts the same thing. It stays the loudest element on the card.
 */
export function StyleCard({
    style,
    className,
}: {
    style: Style;
    className?: string;
}) {
    return (
        <Link
            href={`/styles/${style.slug}`}
            className={cn(
                'site-panel site-panel-hover group flex flex-col overflow-hidden',
                className,
            )}
        >
            <div className="bg-panel-alt relative aspect-4/5 overflow-hidden">
                {style.image_url ? (
                    <img
                        src={style.image_url}
                        alt={style.name}
                        loading="lazy"
                        className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                ) : (
                    <div
                        className="site-glow flex size-full items-center justify-center"
                        aria-hidden="true"
                    >
                        <Scissors className="text-gold/25 size-14" />
                    </div>
                )}

                <span className="bg-gold text-ink absolute top-3 left-3 rounded-full px-2.5 py-1 text-xs font-bold tabular-nums">
                    {style.code}
                </span>
            </div>

            <div className="flex flex-1 flex-col p-4">
                <h3 className="site-display group-hover:text-gold text-xl transition-colors">
                    {style.name}
                </h3>
                {style.description && (
                    <p className="text-smoke mt-1.5 line-clamp-2 text-sm leading-relaxed">
                        {style.description}
                    </p>
                )}
                <div className="text-smoke mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 pt-3 text-xs">
                    {style.gender_label && <span>{style.gender_label}</span>}
                    {style.typical_duration_minutes && (
                        <span className="before:mr-3 before:content-['·']">
                            {style.typical_duration_minutes} min
                        </span>
                    )}
                </div>
            </div>
        </Link>
    );
}
