import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Container({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('mx-auto w-full max-w-6xl px-5 sm:px-8', className)}>
            {children}
        </div>
    );
}

type SectionProps = {
    id?: string;
    eyebrow?: string;
    title?: ReactNode;
    lede?: ReactNode;
    /** Sits to the right of the heading on desktop, under it on mobile. */
    action?: ReactNode;
    className?: string;
    children: ReactNode;
};

export function Section({
    id,
    eyebrow,
    title,
    lede,
    action,
    className,
    children,
}: SectionProps) {
    return (
        <section id={id} className={cn('py-16 sm:py-24', className)}>
            <Container>
                {(eyebrow || title || lede || action) && (
                    <div className="mb-10 flex flex-col gap-6 sm:mb-14 sm:flex-row sm:items-end sm:justify-between">
                        <div className="max-w-2xl">
                            {eyebrow && (
                                <p className="site-eyebrow mb-3">{eyebrow}</p>
                            )}
                            {title && (
                                <h2 className="site-display text-3xl sm:text-4xl lg:text-5xl">
                                    {title}
                                </h2>
                            )}
                            {lede && (
                                <p className="text-smoke mt-4 text-base leading-relaxed sm:text-lg">
                                    {lede}
                                </p>
                            )}
                        </div>
                        {action && <div className="shrink-0">{action}</div>}
                    </div>
                )}
                {children}
            </Container>
        </section>
    );
}
