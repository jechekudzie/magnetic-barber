<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StyleRequest;
use App\Models\Service;
use App\Models\Style;
use App\Support\UploadedImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Response;

class StyleCrudController extends AdminController
{
    public function create(Request $request): Response
    {
        return inertia('admin/style-form', [
            'branchContext' => $this->branchContext($request),
            'services' => $this->serviceOptions(),
            'style' => null,
            'nextCode' => $this->nextCode(),
        ]);
    }

    public function store(StyleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $style = Style::create([
            ...$this->attributes($validated),
            'slug' => Str::slug($validated['name']),
            'image_path' => $request->hasFile('photo')
                ? UploadedImage::store($request->file('photo'), 'styles')
                : null,
        ]);

        return to_route('admin.styles')->with('success', "{$style->name} added to the gallery.");
    }

    public function edit(Request $request, Style $style): Response
    {
        return inertia('admin/style-form', [
            'branchContext' => $this->branchContext($request),
            'services' => $this->serviceOptions(),
            'style' => [
                'slug' => $style->slug,
                'code' => $style->code,
                'name' => $style->name,
                'description' => $style->description,
                'service_id' => $style->service_id,
                'gender_tag' => $style->gender_tag?->value,
                'hair_type_tag' => $style->hair_type_tag ?? [],
                'typical_duration_minutes' => $style->typical_duration_minutes,
                'is_featured' => $style->is_featured,
                'is_active' => $style->is_active,
                'sort_order' => $style->sort_order,
                'image_url' => $style->image_path
                    ? Storage::url($style->image_path)
                    : null,
            ],
            'nextCode' => $style->code,
        ]);
    }

    public function update(StyleRequest $request, Style $style): RedirectResponse
    {
        $validated = $request->validated();

        $attributes = $this->attributes($validated);

        if ($request->hasFile('photo')) {
            $attributes['image_path'] = UploadedImage::replace(
                $style->image_path,
                $request->file('photo'),
                'styles',
            );
        } elseif ($request->boolean('remove_photo')) {
            UploadedImage::forget($style->image_path);
            $attributes['image_path'] = null;
        }

        $style->update($attributes);

        return to_route('admin.styles')->with('success', "{$style->name} updated.");
    }

    public function destroy(Style $style): RedirectResponse
    {
        // Soft deleted: past appointments still point at the style booked.
        $style->delete();

        return to_route('admin.styles')->with('success', "{$style->name} removed from the gallery.");
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
            'gender_tag' => $validated['gender_tag'] ?? null,
            'hair_type_tag' => array_values(array_filter(
                $validated['hair_type_tag'],
                fn (?string $tag): bool => $tag !== null && trim($tag) !== '',
            )),
            'typical_duration_minutes' => $validated['typical_duration_minutes'] ?? null,
            'is_featured' => $validated['is_featured'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
        ];
    }

    /**
     * Styles are numbered so a client can ask for one aloud, so a new one is
     * suggested the next free number rather than left blank.
     */
    private function nextCode(): string
    {
        $highest = Style::withTrashed()
            ->get()
            ->map(fn (Style $style): int => (int) $style->code)
            ->max();

        return str_pad((string) (((int) $highest) + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function serviceOptions(): array
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
            ])
            ->all();
    }
}
