import { cn } from '@/lib/utils';

type LogoProps = {
    className?: string;
    /** Renders the wordmark beside the mark. Off for tight spaces. */
    withWordmark?: boolean;
};

/**
 * The supplied artwork is a JPG on solid black, so it is blended with screen
 * to drop the background out rather than showing a black square on a panel.
 */
export function Logo({ className, withWordmark = true }: LogoProps) {
    return (
        <span className={cn('flex items-center gap-2.5', className)}>
            <img
                src="/images/magnetic-logo.jpg"
                alt=""
                aria-hidden="true"
                className="site-logo-blend h-11 w-11 shrink-0 object-cover object-top"
            />
            {withWordmark && (
                <span className="flex flex-col leading-none">
                    <span className="site-display text-bone text-lg tracking-wide">
                        MAGNETIC
                    </span>
                    <span className="text-gold text-[0.5rem] font-semibold tracking-[0.35em]">
                        BARBERSHOP
                    </span>
                </span>
            )}
        </span>
    );
}
