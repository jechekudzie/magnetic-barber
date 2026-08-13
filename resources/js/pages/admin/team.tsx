import { Head, router } from '@inertiajs/react';
import { User } from 'lucide-react';
import { AdminPage, Panel, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch, StaffMember } from '@/types/catalog';

type TeamMember = StaffMember & {
    show_on_site: boolean;
    roles: string[];
};

export default function Team({ branchContext, team }: {
    branchContext: { current: Branch | null; available: Branch[] };
    team: TeamMember[];
}) {
    return (
        <>
            <Head title="Team" />

            <AdminPage
                title="Team"
                lede={`Who works at ${branchContext.current?.name ?? 'this branch'}. Roles are assigned per branch.`}
            >
                <Panel>
                    <ul className="divide-y">
                        {team.map((member) => (
                            <li
                                key={member.slug}
                                className="flex items-center gap-4 p-4"
                            >
                                <span className="bg-muted flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full">
                                    {member.photo_url ? (
                                        <img
                                            src={member.photo_url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <User
                                            className="text-muted-foreground size-5"
                                            aria-hidden="true"
                                        />
                                    )}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {member.name}
                                        </span>
                                        {member.roles.map((role) => (
                                            <Pill key={role} tone="gold">
                                                {role}
                                            </Pill>
                                        ))}
                                        {!member.show_on_site && (
                                            <Pill>Hidden from site</Pill>
                                        )}
                                        {member.accepts_house_calls && (
                                            <Pill tone="good">House calls</Pill>
                                        )}
                                    </div>
                                    {member.title && (
                                        <p className="text-muted-foreground mt-0.5 text-sm">
                                            {member.title}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.get(
                                            `/admin/team/${member.slug}/edit`,
                                        )
                                    }
                                >
                                    Edit
                                </Button>
                            </li>
                        ))}
                    </ul>
                </Panel>
            </AdminPage>
        </>
    );
}

Team.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Team', href: '/admin/team' },
    ],
};
