<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\BankMovementType;
use App\Models\OperatingExpense;
use App\Models\Outlet;
use App\Services\BankAccountService;
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

        return view('reports.expenses', [
            'expenses' => OperatingExpense::query()
                ->with('user')
                ->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id))
                ->orderByDesc('spent_on')
                ->orderByDesc('id')
                ->paginate(20),
            'categories' => ExpenseCategory::cases(),
            'total' => (float) OperatingExpense::query()
                ->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id))
                ->sum('amount'),
            'bopMonthly' => monthly_bop(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManageBop($request), 403);

        $data = $request->validate([
            'spent_on' => ['required', 'date'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $expense = OperatingExpense::query()->create([
            ...$data,
            'outlet_id' => current_outlet_id(),
            'user_id' => $request->user()->id,
        ]);

        app(BankAccountService::class)->debit(
            (int) $expense->outlet_id,
            (float) $expense->amount,
            BankMovementType::Bop,
            $expense,
            $expense->notes ?: 'BOP '.$expense->category?->value,
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
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $filters['outlet_id'] = $filters['outlet_id'] ?? current_outlet_id();

        return view('reports.setoran', [
            'filters' => $filters,
            'outlets' => Outlet::query()->orderBy('name')->get(),
            ...$this->reports->foodSetoran($filters),
            ...$this->reports->foodSetoranBalance($filters['outlet_id'] ? (int) $filters['outlet_id'] : null),
        ]);
    }

    public function storeSetoran(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'settled_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->reports->settleFoodSetoran(
            (int) current_outlet_id(),
            (int) $request->user()->id,
            $data['settled_on'],
            $data['notes'] ?? null,
        );

        return redirect()->route('reports.setoran')->with('success', 'Setoran tercatat.');
    }

    protected function canManageBop(Request $request): bool
    {
        $user = $request->user();

        return $user->hasPermission('bop.manage') || $user->hasPermission('reports.view');
    }
}
