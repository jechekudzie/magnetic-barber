---
paths:
  - 'database/migrations/**'
---

# Migrations

## Permission teams are branch-scoped and already migrated
config/permission.php runs with `teams => true` and `team_foreign_key => branch_id`, set before the first migration. Roles are global rows; the branch travels on the assignment:

    setPermissionsTeamId($branch->id);
    $user->assignRole('barber');

Do not flip these settings later — it means rewriting model_has_roles, role_has_permissions and every gate call. Seeders must call `setPermissionsTeamId(null)` when finished so they do not leak branch context into whatever runs next.
