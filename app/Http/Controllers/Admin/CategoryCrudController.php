<?php

namespace App\Http\Controllers\Admin;

use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Response;

class CategoryCrudController extends AdminController
{
    public function index(Request $request): Response
    {
        return inertia('admin/categories', [
            'branchContext' => $this->branchContext($request),
            'categories' => ServiceCategory::query()
                ->withCount('services')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ServiceCategory $category): array => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'tagline' => $category->tagline,
                    'description' => $category->description,
                    'icon' => $category->icon,
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->is_active,
                    'services_count' => $category->services_count,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        ServiceCategory::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return back()->with('success', "{$validated['name']} added.");
    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('success', "{$category->name} updated.");
    }

    /**
     * A category holding services cannot be deleted: those services would
     * disappear from the menu with no way to find them again.
     */
    public function destroy(ServiceCategory $category): RedirectResponse
    {
        if ($category->services()->exists()) {
            return back()->withErrors([
                'category' => "{$category->name} still holds services. Move or remove them first.",
            ]);
        }

        $category->delete();

        return back()->with('success', "{$category->name} removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ServiceCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:400'],
            // A lucide icon name, so web and mobile can render the same one.
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
            'slug' => [
                'nullable', 'string', 'max:60',
                Rule::unique('service_categories', 'slug')->ignore($category?->id),
            ],
        ]);
    }
}
