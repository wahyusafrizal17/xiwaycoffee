<?php

namespace App\Http\Controllers;

use App\Enums\BankMovementType;
use App\Enums\ExpenseCategory;
use App\Enums\ExpensePaymentMethod;
use App\Models\OperatingExpense;
use App\Models\Outlet;
use App\Services\BankAccountService;
use App\Services\DashboardService;
use App\Services\ProfitShareService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfitShareController extends Controller
{
    public function __construct(
        protected ProfitShareService $shares,
        protected ReportService $reports,
    ) {}

    public function expenses(Request $request): View
    {
        abort_unless($this->canManageBop($request), 403);

        $outletId = current_outlet_id();
        $date = $request->string('date')->toString() ?: null;
        $category = $request->string('category')->toString() ?: null;
        $paymentMethod = $request->string('payment_method')->toString() ?: null;
        $search = $request->string('q')->toString() ?: null;
        $user = $request->string('user')->toString() ?: null;

        // Keep legacy from/to query params working if bookmarked.
        $from = $date ?: ($request->string('from')->toString() ?: null);
        $to = $date ? $date : ($request->string('to')->toString() ?: null);

        $query = OperatingExpense::query()
            ->with('user')
            ->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id))
            ->when(
                ! $request->user()->hasPermission('reports.view'),
                fn ($q) => $q->where('user_id', $request->user()->id),
            )
            ->when($date, fn ($q) => $q->whereDate('spent_on', $date))
            ->when(! $date && $from, fn ($q) => $q->whereDate('spent_on', '>=', $from))
            ->when(! $date && $to, fn ($q) => $q->whereDate('spent_on', '<=', $to))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($paymentMethod, fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($search, fn ($q) => $q->where('notes', 'like', '%'.$search.'%'))
            ->when($user, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$user.'%')));

        $filteredTotal = (float) (clone $query)->sum('amount');
        $byCategory = (clone $query)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $category = $row->category instanceof ExpenseCategory
                    ? $row->category
                    : ExpenseCategory::tryFrom((string) $row->category);

                return [
                    'category' => $category,
                    'total' => (float) $row->total,
                ];
            });

        $filters = [
            'date' => $date,
            'category' => $category,
            'payment_method' => $paymentMethod,
            'q' => $search,
            'user' => $user,
        ];

        return view('reports.expenses', [
            'expenses' => $query->orderByDesc('spent_on')->orderByDesc('id')->paginate(20)->withQueryString(),
            'categories' => ExpenseCategory::selectable(),
            'filterCategories' => ExpenseCategory::cases(),
            'paymentMethods' => ExpensePaymentMethod::cases(),
            'total' => $filteredTotal,
            'byCategory' => $byCategory,
            'filters' => $filters,
            'hasFilters' => collect($filters)->contains(fn ($v) => filled($v)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManageBop($request), 403);

        if ($request->input('payment_method') === '') {
            $request->merge(['payment_method' => null]);
        }

        $data = $request->validate([
            'spent_on' => ['required', 'date'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['nullable', Rule::enum(ExpensePaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'evidence.required' => 'Bukti wajib diunggah.',
            'evidence.mimes' => 'Bukti harus berupa foto atau PDF.',
            'evidence.max' => 'Bukti maksimal 5 MB.',
        ]);

        $file = $request->file('evidence');
        $directory = public_path('evidence/bop');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $name = $file->hashName();
        $file->move($directory, $name);
        unset($data['evidence']);

        $expense = OperatingExpense::query()->create([
            ...$data,
            'evidence' => 'evidence/bop/'.$name,
            'payment_method' => $data['payment_method'] ?? null,
            'outlet_id' => current_outlet_id(),
            'user_id' => $request->user()->id,
        ]);

        app(BankAccountService::class)->debit(
            (int) $expense->outlet_id,
            (float) $expense->amount,
            BankMovementType::Bop,
            $expense,
            $expense->notes ?: 'BOP '.$expense->category?->label(),
            $expense->spent_on?->toDateString(),
        );

        return redirect()->route('reports.expenses')->with('success', 'BOP tercatat.');
    }

    public function profit(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $filters['outlet_id'] = $filters['outlet_id'] ?? current_outlet_id();

        return view('reports.profit', [
            'filters' => $filters,
            'outlets' => Outlet::query()->orderBy('name')->get(),
            ...$this->shares->summarize($filters),
        ]);
    }

    public function setoran(Request $request): View
    {
        abort_unless($this->canViewFood($request), 403);

        [$filters, $range] = app(DashboardService::class)->periodFilters($request);
        $filters['outlet_id'] = $filters['outlet_id'] ?? current_outlet_id();

        return view('reports.setoran', [
            'filters' => $filters,
            'range' => $range,
            'period' => $range['period'],
            'outlets' => Outlet::query()->orderBy('name')->get(),
            ...$this->reports->foodSetoran($filters),
            ...$this->reports->foodSetoranBalance($filters['outlet_id'] ? (int) $filters['outlet_id'] : null),
        ]);
    }

    public function storeSetoran(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'settled_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->reports->settleFoodSetoran(
            (int) current_outlet_id(),
            (int) $request->user()->id,
            $data['settled_on'],
            $data['from'],
            $data['to'],
            $data['notes'] ?? null,
        );

        return redirect()->route('reports.setoran', [
            'from' => $data['from'],
            'to' => $data['to'],
        ])->with('success', 'Setoran tercatat.');
    }

    protected function canManageBop(Request $request): bool
    {
        $user = $request->user();

        return $user->hasPermission('bop.manage') || $user->hasPermission('reports.view');
    }

    protected function canViewFood(Request $request): bool
    {
        $user = $request->user();

        return $user->hasPermission('reports.view') || $user->hasPermission('reports.food');
    }
}
