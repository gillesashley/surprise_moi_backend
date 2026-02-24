<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index()
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->paginate(15);

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'canCreate' => Auth::user()->isSuperAdmin(),
            'canDelete' => Auth::user()->isSuperAdmin(),
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create()
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create categories.');
        }

        return Inertia::render('categories/create');
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create categories.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'type' => ['required', 'string', 'in:product,service'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
            $validated['image'] = $imagePath;
        }

        Category::create($validated);

        return redirect()->route('content-management', ['tab' => 'categories'])->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        $category->loadCount('products');

        return Inertia::render('categories/show', [
            'category' => $category,
        ]);
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category)
    {
        return Inertia::render('categories/edit', [
            'category' => $category,
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,'.$category->id],
            'type' => ['required', 'string', 'in:product,service'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Handle is_active checkbox - if not present, set to false
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $imagePath = $request->file('image')->store('categories', 'public');
            $validated['image'] = $imagePath;
        }

        $category->update($validated);

        return redirect()->route('content-management', ['tab' => 'categories'])->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete categories.');
        }

        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete category with existing products.');
        }

        // Delete image if exists
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return redirect()->route('content-management', ['tab' => 'categories'])->with('success', 'Category deleted successfully.');
    }
}
