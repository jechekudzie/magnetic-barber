import { Instagram, User } from 'lucide-react';
import type { StaffMember } from '@/types/catalog';

export function StaffCard({ member }: { member: StaffMember }) {
    return (
        <article className="site-panel site-panel-hover overflow-hidden">
            <div className="bg-panel-alt aspect-square overflow-hidden">
                {member.photo_url ? (
                    <img
                        src={member.photo_url}
                        alt={member.name}
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <div
                        className="site-glow flex size-full items-center justify-center"
                        aria-hidden="true"
                    >
                        <User className="text-gold/25 size-14" />
                    </div>
                )}
            </div>

            <div className="p-5">
                <h3 className="site-display text-xl">{member.name}</h3>
                {member.title && (
                    <p className="text-gold mt-0.5 text-sm">{member.title}</p>
                )}
                {member.bio && (
                    <p className="text-smoke mt-3 text-sm leading-relaxed">
                        {member.bio}
                    </p>
                )}

                {member.specialities.length > 0 && (
                    <ul className="mt-4 flex flex-wrap gap-1.5">
                        {member.specialities.map((speciality) => (
                            <li
                                key={speciality}
                                className="border-bone/12 text-bone/75 rounded-full border px-2.5 py-1 text-xs"
                            >
                                {speciality}
                            </li>
                        ))}
                    </ul>
                )}

                {member.instagram_handle && (
                    <a
                        href={`https://instagram.com/${member.instagram_handle}`}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="text-smoke hover:text-gold mt-4 inline-flex items-center gap-1.5 text-xs transition-colors"
                    >
                        <Instagram className="size-3.5" aria-hidden="true" />@
                        {member.instagram_handle}
                    </a>
                )}
            </div>
        </article>
    );
}
