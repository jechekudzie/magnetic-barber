<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions are grouped by resource, not by screen. Screens change, resources
 * do not. Roles are global rows; the branch travels on the assignment.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'branch.view', 'branch.create', 'branch.update', 'branch.deactivate',

        'staff.view', 'staff.invite', 'staff.update', 'staff.deactivate',
        'staff.schedule.manage', 'staff.commission.view',

        'client.view', 'client.create', 'client.update', 'client.merge',
        'client.contact.view', 'client.note.view', 'client.note.write', 'client.export',

        'appointment.view.own', 'appointment.view.branch', 'appointment.create',
        'appointment.update', 'appointment.assign', 'appointment.cancel',
        'appointment.checkin', 'appointment.complete', 'appointment.no-show',

        'queue.view', 'queue.manage',

        'visit.record.write', 'visit.photo.upload',
        'skin.profile.view', 'skin.profile.write', 'skin.consent.capture',

        'service.view', 'service.create', 'service.update',
        'price.view', 'price.update',

        'product.view', 'product.create', 'product.update',
        'stock.view', 'stock.adjust', 'stock.transfer',

        'sale.create', 'sale.discount', 'sale.void', 'sale.refund',
        'payment.take', 'drawer.open', 'drawer.close', 'drawer.reconcile',

        'loyalty.view', 'loyalty.adjust',
        'plan.manage', 'subscription.manage',

        'review.view', 'review.respond', 'review.publish',

        'report.view.own', 'report.view.branch', 'report.view.financial', 'report.view.group',

        'message.send', 'message.template.manage',
        'settings.manage', 'audit.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // The registrar caches the permission list, so the roles below would
        // otherwise sync against the set that existed before this loop ran.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // super-admin holds no permissions: it bypasses every gate instead.
        Role::findOrCreate('super-admin', 'web');

        $this->syncRole('owner', $this->permissions);

        $this->syncRole('branch-manager', array_values(array_diff($this->permissions, [
            'branch.create', 'branch.deactivate', 'report.view.group', 'client.export',
        ])));

        $this->syncRole('receptionist', [
            'client.view', 'client.create', 'client.update', 'client.contact.view',
            'appointment.view.branch', 'appointment.create', 'appointment.update',
            'appointment.assign', 'appointment.checkin', 'appointment.cancel',
            'queue.view', 'queue.manage', 'service.view', 'price.view', 'product.view',
            'sale.create', 'payment.take', 'drawer.open', 'drawer.close',
            'loyalty.view', 'message.send', 'report.view.own',
        ]);

        // Barbers deliberately cannot see client phone numbers. This is how a
        // shop keeps its client list when a barber leaves.
        $barber = [
            'appointment.view.own', 'appointment.create', 'appointment.checkin',
            'appointment.complete', 'queue.view', 'visit.record.write',
            'visit.photo.upload', 'service.view', 'price.view', 'client.view',
            'client.note.view', 'client.note.write', 'report.view.own',
        ];

        $this->syncRole('barber', $barber);

        $this->syncRole('aesthetician', [
            ...$barber,
            'skin.profile.view', 'skin.profile.write', 'skin.consent.capture',
        ]);

        // Clients hold no permissions; policies scope them to their own data.
        $this->syncRole('client', []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncRole(string $name, array $permissions): void
    {
        Role::findOrCreate($name, 'web')->syncPermissions($permissions);
    }
}
