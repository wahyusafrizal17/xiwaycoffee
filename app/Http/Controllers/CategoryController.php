<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('products.view'), 403);

        $filters = $request->only(['name', 'station', 'status']);
        $query = Category::query()->withCount('products');

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['station'] ?? null)) {
            $query->where('station', $filters['station']);
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $focusCategory = $request->filled('category')
            ? Category::query()->withCount('products')->find($request->integer('category'))
            : null;

        return view('categories.index', [
            'categories' => $query->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Category::query()->count(),
                'active' => Category::query()->where('is_active', true)->count(),
                'inactive' => Category::query()->where('is_active', false)->count(),
            ],
            'focusPayload' => $focusCategory?->toModalArray(),
        ]);
    }

    public function create(): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('products.manage'), 403);

        return redirect()->route('categories.index', ['modal' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('products.manage'), 403);
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        Category::query()->create($data);

        return redirect()->route('categories.index')->with('success', 'Kategori ditambahkan.');
    }

    public function edit(Category $category): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('products.manage'), 403);

        return redirect()->route('categories.index', ['modal' => 'edit', 'category' => $category->id]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('products.manage'), 403);
        $category->update($this->validated($request));

        return redirect()->route('categories.index')->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('products.manage'), 403);
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Kategori dihapus.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'station' => ['nullable', 'in:kitchen,bar,cashier'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama wajib diisi.',
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
