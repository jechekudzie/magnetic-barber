<?php

namespace App\Console\Commands;

use App\Enums\EmploymentType;
use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates a real staff account.
 *
 * This exists because the seeders must never put a known password on a live
 * system. The placeholder team in TeamSeeder is development only; production
 * staff are created here, one at a time, with a password nobody else knows.
 */
class CreateStaffMember extends Command
{
    protected $signature = 'magnetic:staff
                            {--name= : Their full name}
                            {--phone= : Mobile number, any format}
                            {--email= : Required for anyone who signs into the admin}
                            {--role= : owner|branch-manager|receptionist|barber|aesthetician}
                            {--branch= : Branch slug they work at}
                            {--password= : Leave out to have one generated}';

    protected $description = 'Create a staff account and assign their role at a branch';

    public function handle(): int
    {
        // Deploy scripts and SSH one liners have no terminal to prompt at, so
        // everything falls back to an option or a sensible default.
        $interactive = $this->input->isInteractive();

        $branches = Branch::query()->ordered()->get();

        if ($branches->isEmpty()) {
            $this->error('No branches yet. Run php artisan db:seed --force first.');

            return self::FAILURE;
        }

        if (! $interactive && ($this->option('name') === null || $this->option('phone') === null)) {
            $this->error('Running without a terminal needs at least --name and --phone.');
            $this->line('  php artisan magnetic:staff --name="Tapiwa Moyo" --phone=0781879820 \\');
            $this->line('    --email=owner@magneticbarber.co.zw --role=owner --branch=harare-avenues');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text(
            label: 'Full name',
            required: true,
            validate: fn (string $value): ?string => strlen(trim($value)) < 2
                ? 'That is too short to be a name.'
                : null,
        );

        $phone = $this->option('phone') ?: text(
            label: 'Mobile number',
            placeholder: '078 187 9820',
            required: true,
        );

        $normalised = Phone::normalise($phone);

        if ($normalised === null || ! str_starts_with($normalised, '+')) {
            $this->error("Could not read {$phone} as a mobile number.");

            return self::FAILURE;
        }

        $existing = User::query()->where('phone', $normalised)->first();

        if ($existing !== null && $interactive
            && ! confirm("{$normalised} already belongs to {$existing->name}. Update them instead?")) {
            return self::FAILURE;
        }

        $role = $this->option('role') ?: ($interactive ? select(
            label: 'Role at this branch',
            options: [
                'barber' => 'Barber',
                'aesthetician' => 'Aesthetician (barber plus skin)',
                'receptionist' => 'Receptionist (books and takes payment)',
                'branch-manager' => 'Branch manager (runs one branch)',
                'owner' => 'Owner (sees every branch)',
            ],
            default: 'barber',
        ) : 'barber');

        $branchSlug = $this->option('branch') ?: ($interactive ? select(
            label: 'Which branch',
            options: $branches->pluck('name', 'slug')->all(),
        ) : $branches->first()->slug);

        $branch = $branches->firstWhere('slug', $branchSlug);

        if ($branch === null) {
            $this->error("No branch with the slug {$branchSlug}.");

            return self::FAILURE;
        }

        // Anyone who signs in needs an email; a barber who only appears on the
        // team page does not.
        $signsIn = in_array($role, ['owner', 'branch-manager', 'receptionist'], true)
            || $this->option('email') !== null
            || ($interactive && confirm(label: "Does {$name} sign into the admin?", default: true));

        $email = null;
        $secret = null;

        if ($signsIn) {
            $email = $this->option('email') ?: ($interactive ? text(
                label: 'Email for signing in',
                required: true,
                validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                    ? 'That is not an email address.'
                    : null,
            ) : null);

            if ($email === null) {
                $this->error("A {$role} signs into the admin, so --email is required.");

                return self::FAILURE;
            }

            $secret = $this->option('password') ?: ($interactive ? password(
                label: 'Password (leave blank to generate one)',
                validate: function (string $value): ?string {
                    if ($value === '') {
                        return null;
                    }

                    $validator = validator(
                        ['password' => $value],
                        ['password' => ['string', Password::min(12)->letters()->numbers()]],
                    );

                    return $validator->fails()
                        ? 'At least 12 characters, with letters and numbers.'
                        : null;
                },
            ) : '');

            // Blank means "generate one", whether that came from an empty
            // prompt or from there being no terminal to prompt at.
            if ($secret === '') {
                $secret = Str::password(16);
                $generated = true;
            }
        }

        $user = DB::transaction(function () use ($name, $normalised, $email, $secret, $branch, $role): User {
            // Assigned explicitly rather than mass assigned: verification
            // timestamps are not something a request should ever be able to set,
            // so they stay off the model's fillable list.
            $user = User::firstOrNew(['phone' => $normalised]);

            $user->name = $name;
            $user->email = $email;
            $user->email_verified_at = $email !== null ? now() : null;
            $user->phone_verified_at = now();
            $user->is_active = true;

            if ($secret !== null) {
                $user->password = Hash::make($secret);
            }

            $user->save();

            $user->branches()->syncWithoutDetaching([
                $branch->id => [
                    'employment_type' => EmploymentType::Employed->value,
                    'currency' => config('magnetic.default_currency'),
                    'is_primary' => $user->branches()->count() === 0,
                    'starts_on' => now()->toDateString(),
                ],
            ]);

            // Roles are global rows; the branch travels on the assignment.
            setPermissionsTeamId($branch->id);
            $user->assignRole($role);
            setPermissionsTeamId(null);

            StaffProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
                    'display_name' => $name,
                    'is_bookable' => in_array($role, ['barber', 'aesthetician'], true),
                    // New staff stay off the public page until someone has
                    // written their bio and added a photo.
                    'show_on_site' => false,
                ],
            );

            return $user;
        });

        $this->newLine();
        $this->info("{$user->name} created as {$role} at {$branch->name}.");

        if ($email !== null) {
            $this->line("  Sign in with: {$email}");
        }

        if (isset($generated)) {
            $this->newLine();
            $this->warn('  Generated password, shown once:');
            $this->line("  {$secret}");
            $this->newLine();
            $this->line('  Give it to them over a channel you trust, and have them change it.');
        }

        $this->line('  They are hidden from the public team page until you add a bio and photo in the admin.');

        return self::SUCCESS;
    }
}
