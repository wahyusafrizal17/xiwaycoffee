<?php

namespace App\Http\Controllers;

use App\Enums\BankMovementType;
use App\Models\BankMovement;
use App\Services\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function __construct(protected BankAccountService $bank) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $outletId = (int) current_outlet_id();
        $this->bank->accountFor($outletId);

        return view('bank.index', [
            'balance' => $this->bank->balance($outletId),
            'movements' => BankMovement::query()
                ->with('user')
                ->where('outlet_id', $outletId)
                ->orderByDesc('id')
                ->paginate(30),
        ]);
    }

    public function storeDeposit(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->hasPermission('bop.manage') || $request->user()->hasPermission('reports.view'),
            403
        );

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->bank->credit(
            (int) current_outlet_id(),
            (float) $data['amount'],
            BankMovementType::CashDeposit,
            null,
            $data['notes'] ?? 'Setoran kas ke rekening',
            $data['occurred_on'],
        );

        $redirect = $request->user()->hasPermission('reports.view')
            ? route('bank.index')
            : route('bank.deposits.create');

        return redirect($redirect)->with('success', 'Setoran kas tercatat.');
    }

    public function createDeposit(Request $request): View
    {
        abort_unless(
            $request->user()->hasPermission('bop.manage') || $request->user()->hasPermission('reports.view'),
            403
        );

        return view('bank.deposit', [
            'balance' => $this->bank->balance((int) current_outlet_id()),
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'notes' => ['required', 'string', 'max:255'],
        ]);

        $this->bank->adjust(
            (int) current_outlet_id(),
            (float) $data['amount'],
            $data['notes'],
            $data['occurred_on'],
        );

        return redirect()->route('bank.index')->with('success', 'Koreksi saldo tercatat.');
    }
}
