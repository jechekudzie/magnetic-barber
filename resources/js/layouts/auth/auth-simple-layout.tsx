import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Staff sign in. Clients never see this: their number is their account and the
 * booking flow creates it for them.
 *
 * The `dark` class is doing real work. Every control in here is a shadcn
 * component reading --foreground, --input and --border, so painting only the
 * page background would leave near-black text on a near-black panel. Flipping
 * the whole token set keeps inputs, labels, buttons and focus rings correct
 * without restyling any of them one by one.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="dark bg-background text-foreground flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-5">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-3"
                        >
                            <img
                                src="/images/magnetic-logo.jpg"
                                alt=""
                                aria-hidden="true"
                                className="site-logo-blend size-24 object-cover object-top"
                            />
                            <span className="flex flex-col items-center leading-none">
                                <span
                                    className="text-lg font-bold tracking-wide text-white"
                                    style={{ fontFamily: 'var(--font-display)' }}
                                >
                                    MAGNETIC
                                </span>
                                <span className="text-primary mt-1 text-[0.5rem] font-semibold tracking-[0.35em]">
                                    BARBERSHOP
                                </span>
                            </span>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">
                                {description}
                            </p>
                        </div>
                    </div>

                    {children}

                    <p className="text-muted-foreground text-center text-xs">
                        Staff only. Clients book with their phone number, no
                        password needed.
                    </p>
                </div>
            </div>
        </div>
    );
}
