import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { AdminPage, Panel, Pill } from '@/components/admin/page';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { Branch } from '@/types/catalog';

type ServiceRow = {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    category: string | null;
    duration_minutes: number;
    price: string | null;
    is_active: boolean;
    is_featured: boolean;
    is_skin_service: boolean;
    requires_patch_test: boolean;
};

export default function Services({
    branchContext,
    services,
}: {
    branchContext: { current: Branch | null; available: Branch[] };
    services: ServiceRow[];
}) {
    return (
        <>
            <Head title="Services" />

            <AdminPage
                title="Services"
                lede={`The menu across every branch. Prices shown are ${branchContext.current?.name ?? 'this branch'}'s.`}
                action={
                    <Button onClick={() => router.get('/admin/services/create')}>
                        <Plus className="size-4" aria-hidden="true" />
                        New service
                    </Button>
                }
            >
                <Panel>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                                <tr>
                                    <th className="px-5 py-3 font-medium">
                                        Service
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        Category
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        Time
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        Price here
                                    </th>
                                    <th className="px-5 py-3 text-right font-medium">
                                        On menu
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {services.map((service) => (
                                    <tr key={service.id}>
                                        <td className="px-5 py-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {service.name}
                                                </span>
                                                {service.is_featured && (
                                                    <Pill tone="gold">
                                                        Featured
                                                    </Pill>
                                                )}
                                                {service.requires_patch_test && (
                                                    <Pill>Patch test</Pill>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-muted-foreground px-5 py-3">
                                            {service.category}
                                        </td>
                                        <td className="text-muted-foreground px-5 py-3 tabular-nums">
                                            {service.duration_minutes} min
                                        </td>
                                        <td className="px-5 py-3 font-medium tabular-nums">
                                            {service.price ?? (
                                                <span className="text-muted-foreground font-normal">
                                                    Not priced
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.get(
                                                            `/admin/services/${service.slug}/edit`,
                                                        )
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant={
                                                        service.is_active
                                                            ? 'outline'
                                                            : 'default'
                                                    }
                                                    onClick={() =>
                                                        router.put(
                                                            `/admin/services/${service.slug}/toggle`,
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    {service.is_active
                                                        ? 'Take off'
                                                        : 'Put on'}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
            </AdminPage>
        </>
    );
}

Services.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Services', href: '/admin/services' },
    ],
};
