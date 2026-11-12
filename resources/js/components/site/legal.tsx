import type { ReactNode } from 'react';
import { Container } from '@/components/site/section';

/**
 * Both legal pages share one shape. The prose column is held narrower than
 * the rest of the site because legal text is read line by line rather than
 * scanned, and a 70 character measure is where that stops being work.
 */
export function LegalHeader({
    eyebrow,
    title,
    lede,
    updated,
}: {
    eyebrow: string;
    title: string;
    lede: string;
    updated: string;
}) {
    return (
        <section className="site-glow border-b border-bone/8">
            <Container className="py-16 sm:py-20">
                <p className="site-eyebrow mb-4">{eyebrow}</p>
                <h1 className="site-display max-w-2xl text-4xl sm:text-5xl">
                    {title}
                </h1>
                <p className="mt-5 max-w-xl leading-relaxed text-smoke">
                    {lede}
                </p>
                <p className="mt-6 text-xs text-smoke/70">
                    Last updated {updated}
                </p>
            </Container>
        </section>
    );
}

export function LegalBody({ children }: { children: ReactNode }) {
    return (
        <Container className="py-14 sm:py-20">
            <div className="max-w-2xl space-y-10">{children}</div>
        </Container>
    );
}

export function Clause({
    heading,
    children,
}: {
    heading: string;
    children: ReactNode;
}) {
    return (
        <section>
            <h2 className="site-display text-2xl sm:text-3xl">{heading}</h2>
            <div className="mt-3 space-y-3 leading-relaxed text-smoke">
                {children}
            </div>
        </section>
    );
}

/** A plain list, for the "what we hold" and "your rights" clauses. */
export function ClauseList({ items }: { items: ReactNode[] }) {
    return (
        <ul className="space-y-2">
            {items.map((item, index) => (
                <li key={index} className="flex gap-3">
                    <span
                        className="mt-2 size-1.5 shrink-0 rounded-full bg-gold/60"
                        aria-hidden="true"
                    />
                    <span>{item}</span>
                </li>
            ))}
        </ul>
    );
}
