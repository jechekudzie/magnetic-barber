import { router } from '@inertiajs/react';
import { Check, ChevronDown, MapPin } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { Branch } from '@/types/catalog';

type BranchSwitcherProps = {
    branches: Branch[];
    current: Branch | null;
    className?: string;
};

/**
 * Prices are per branch, so the visitor's branch choice is a real piece of
 * state. It rides in the query string and the server remembers it for the
 * session, which keeps deep links shareable.
 */
export function BranchSwitcher({
    branches,
    current,
    className,
}: BranchSwitcherProps) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: MouseEvent) {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    // One branch is the normal case today, so there is nothing to switch.
    if (branches.length <= 1) {
        return current ? (
            <span
                className={cn(
                    'text-smoke inline-flex items-center gap-1.5 text-xs',
                    className,
                )}
            >
                <MapPin className="size-3.5" aria-hidden="true" />
                {current.name}
            </span>
        ) : null;
    }

    function choose(slug: string) {
        setOpen(false);
        router.get(
            window.location.pathname,
            { branch: slug },
            { preserveScroll: true, preserveState: false },
        );
    }

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="listbox"
                className="border-bone/15 text-bone/90 hover:border-gold/50 hover:text-gold inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
            >
                <MapPin className="size-3.5" aria-hidden="true" />
                {current?.name ?? 'Choose a branch'}
                <ChevronDown className="size-3.5" aria-hidden="true" />
            </button>

            {open && (
                <ul
                    role="listbox"
                    className="site-panel absolute right-0 z-50 mt-2 w-56 overflow-hidden p-1.5"
                >
                    {branches.map((branch) => (
                        <li key={branch.slug}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={branch.slug === current?.slug}
                                onClick={() => choose(branch.slug)}
                                className="hover:bg-panel-alt hover:text-gold flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors"
                            >
                                <span>
                                    <span className="block">{branch.name}</span>
                                    <span className="text-smoke block text-xs">
                                        {branch.address.area}
                                    </span>
                                </span>
                                {branch.slug === current?.slug && (
                                    <Check
                                        className="text-gold size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
