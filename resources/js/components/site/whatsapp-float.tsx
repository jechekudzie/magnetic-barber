import { WhatsAppIcon } from '@/components/whatsapp-icon';
import { cn } from '@/lib/utils';

/**
 * WhatsApp is the channel this shop actually runs on, so it gets a permanent
 * handle rather than living only in the footer. It sits above the mobile
 * action bar so the two never overlap.
 */
export function WhatsAppFloat({ href }: { href: string | null }) {
    if (!href) {
        return null;
    }

    return (
        <a
            href={href}
            target="_blank"
            rel="noreferrer noopener"
            aria-label="Message Magnetic Barbershop on WhatsApp"
            className={cn(
                'group fixed right-4 bottom-24 z-40 flex size-14 items-center justify-center rounded-full',
                'bg-[#25D366] text-white shadow-lg shadow-black/40',
                'transition-transform duration-200 hover:scale-105 active:scale-95',
                'sm:right-6 sm:bottom-6',
            )}
        >
            <WhatsAppIcon className="size-7" />
        </a>
    );
}
