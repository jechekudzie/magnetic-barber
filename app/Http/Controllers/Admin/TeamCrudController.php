<?php

namespace App\Http\Controllers\Admin;

use App\Models\StaffProfile;
use App\Support\UploadedImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

/**
 * Editing how a barber appears and whether they can be booked.
 *
 * Creating the staff *account* is a separate, more sensitive job: it issues
 * credentials and assigns branch roles. This screen only edits the profile.
 */
class TeamCrudController extends AdminController
{
    public function edit(Request $request, StaffProfile $staffProfile): Response
    {
        return inertia('admin/team-form', [
            'branchContext' => $this->branchContext($request),
            'member' => [
                'slug' => $staffProfile->slug,
                'display_name' => $staffProfile->display_name,
                'title' => $staffProfile->title,
                'bio' => $staffProfile->bio,
                'specialities' => $staffProfile->specialities ?? [],
                'instagram_handle' => $staffProfile->instagram_handle,
                'accepts_house_calls' => $staffProfile->accepts_house_calls,
                'is_bookable' => $staffProfile->is_bookable,
                'show_on_site' => $staffProfile->show_on_site,
                'sort_order' => $staffProfile->sort_order,
                'photo_url' => $staffProfile->photo_path
                    ? Storage::url($staffProfile->photo_path)
                    : null,
            ],
        ]);
    }

    public function update(Request $request, StaffProfile $staffProfile): RedirectResponse
    {
        abort_unless($request->user()?->can('staff.update'), 403);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:80'],
            'title' => ['nullable', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:400'],
            'specialities' => ['present', 'array'],
            // Blank rows arrive as null via ConvertEmptyStringsToNull.
            'specialities.*' => ['nullable', 'string', 'max:40'],
            'instagram_handle' => ['nullable', 'string', 'max:40'],
            'accepts_house_calls' => ['required', 'boolean'],
            'is_bookable' => ['required', 'boolean'],
            'show_on_site' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:6144'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $attributes = [
            'display_name' => $validated['display_name'],
            'title' => $validated['title'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'specialities' => array_values(array_filter(
                $validated['specialities'],
                fn (?string $item): bool => $item !== null && trim($item) !== '',
            )),
            'instagram_handle' => $validated['instagram_handle']
                ? ltrim($validated['instagram_handle'], '@')
                : null,
            'accepts_house_calls' => $validated['accepts_house_calls'],
            'is_bookable' => $validated['is_bookable'],
            'show_on_site' => $validated['show_on_site'],
            'sort_order' => $validated['sort_order'],
        ];

        if ($request->hasFile('photo')) {
            $attributes['photo_path'] = UploadedImage::replace(
                $staffProfile->photo_path,
                $request->file('photo'),
                'team',
            );
        } elseif ($request->boolean('remove_photo')) {
            UploadedImage::forget($staffProfile->photo_path);
            $attributes['photo_path'] = null;
        }

        $staffProfile->update($attributes);

        return to_route('admin.team')->with('success', "{$staffProfile->name()} updated.");
    }
}
