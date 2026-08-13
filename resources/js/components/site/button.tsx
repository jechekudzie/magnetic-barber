import { Link } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Variant = 'gold' | 'outline' | 'ghost';
type Size = 'sm' | 'md' | 'lg';

const variants: Record<Variant, string> = {
    gold: 'bg-gold text-ink hover:bg-gold-lite active:bg-gold-lite',
    outline:
        'border border-bone/25 text-bone hover:border-gold hover:text-gold',
    ghost: 'text-bone/80 hover:text-gold',
};

const sizes: Record<Size, string> = {
    sm: 'h-9 px-4 text-sm',
    md: 'h-11 px-6 text-sm',
    lg: 'h-13 px-8 text-base',
};

const base =
    'inline-flex items-center justify-center gap-2 rounded-full font-semibold tracking-wide transition-colors duration-200 disabled:pointer-events-none disabled:opacity-50';

function classes(variant: Variant, size: Size, className?: string) {
    return cn(base, variants[variant], sizes[size], className);
}

type SiteLinkProps = {
    href: string;
    variant?: Variant;
    size?: Size;
    className?: string;
    children: ReactNode;
    /** Set for links leaving the app, such as WhatsApp or Google Maps. */
    external?: boolean;
};

export function SiteLink({
    href,
    variant = 'gold',
    size = 'md',
    className,
    children,
    external = false,
}: SiteLinkProps) {
    if (external) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noreferrer noopener"
                className={classes(variant, size, className)}
            >
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={classes(variant, size, className)}>
            {children}
        </Link>
    );
}

type SiteButtonProps = ComponentProps<'button'> & {
    variant?: Variant;
    size?: Size;
};

export function SiteButton({
    variant = 'gold',
    size = 'md',
    className,
    ...props
}: SiteButtonProps) {
    return (
        <button type="button" className={classes(variant, size, className)} {...props} />
    );
}
