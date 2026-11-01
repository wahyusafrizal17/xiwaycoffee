<?php

namespace App\Services;

use App\Enums\BankMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\BankMovement;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankAccountService
{
    /** QRIS sebelum rekening dipakai (20-22 Sep 2026) tidak pernah masuk saldo. */
    public const QRIS_START = '2026-09-23';

    public function accountFor(int $outletId): BankAccount
    {
        return BankAccount::query()->firstOrCreate(
            ['outlet_id' => $outletId],
            ['name' => 'Rekening'],
        );
    }

    public function balance(int $outletId): float
    {
        $account = BankAccount::query()->where('outlet_id', $outletId)->first();

        if (! $account) {
            return 0.0;
        }

        return (float) ($account->movements()->latest('id')->value('balance_after') ?? 0);
    }

    public function credit(
        int $outletId,
        float $amount,
        BankMovementType $type,
        ?Model $reference = null,
        ?string $notes = null,
        ?string $occurredOn = null,
        ?int $userId = null,
    ): ?BankMovement {
        return $this->post($outletId, abs($amount), $type, $reference, $notes, $occurredOn, $userId);
    }

    public function debit(
        int $outletId,
        float $amount,
        BankMovementType $type,
        ?Model $reference = null,
        ?string $notes = null,
        ?string $occurredOn = null,
        ?int $userId = null,
    ): ?BankMovement {
        return $this->post($outletId, -abs($amount), $type, $reference, $notes, $occurredOn, $userId);
    }

    /**
     * Cadangan: QRIS lunas yang belum tercatat di saldo, biasanya masuk langsung saat checkout.
     * Aman diulang.
     *
     * @return array{count: int, total: float}
     */
    public function closeQris(): array
    {
        $payments = Payment::query()
            ->with('order')
            ->where('method', PaymentMethod::Qris->value)
            ->where('status', 'paid')
            ->where('created_at', '>=', Carbon::parse(self::QRIS_START, 'Asia/Jakarta')->utc()->toDateTimeString())
            ->whereHas('order', fn ($query) => $query->where('status', '!=', OrderStatus::Cancelled->value))
            ->whereNotIn('id', BankMovement::query()
                ->where('type', BankMovementType::Qris->value)
                ->where('reference_type', (new Payment)->getMorphClass())
                ->select('reference_id'))
            ->orderBy('id')
            ->get();

        $count = 0;
        $total = 0.0;
        foreach ($payments as $payment) {
            $movement = $this->credit(
                (int) $payment->order->outlet_id,
                (float) $payment->amount,
                BankMovementType::Qris,
                $payment,
                'Pembayaran QRIS '.$payment->order->order_number,
                $payment->created_at->copy()->timezone('Asia/Jakarta')->toDateString(),
                $payment->user_id,
            );
            if ($movement) {
                $count++;
                $total += (float) $payment->amount;
            }
        }

        return ['count' => $count, 'total' => $total];
    }

    public function forget(Model $reference, BankMovementType $type): void
    {
        DB::transaction(function () use ($reference, $type) {
            $movement = BankMovement::query()
                ->where('type', $type->value)
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->lockForUpdate()
                ->first();

            if (! $movement) {
                return;
            }

            BankAccount::query()->whereKey($movement->bank_account_id)->lockForUpdate()->first();

            DB::update(
                'update bank_movements set balance_after = round(balance_after - ?, 2) where bank_account_id = ? and id > ?',
                [$movement->amount, $movement->bank_account_id, $movement->id],
            );

            $movement->delete();
        });
    }

    public function adjust(int $outletId, float $signedAmount, string $notes, ?string $occurredOn = null): BankMovement
    {
        if (round($signedAmount, 2) == 0.0) {
            throw ValidationException::withMessages(['amount' => 'Nominal koreksi harus diisi.']);
        }

        if (trim($notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Keterangan koreksi wajib diisi.']);
        }

        return $this->post(
            $outletId,
            round($signedAmount, 2),
            BankMovementType::Adjustment,
            null,
            $notes,
            $occurredOn,
        );
    }

    public function setBalance(int $outletId, float $target, string $notes, ?string $occurredOn = null): BankMovement
    {
        $difference = round($target - $this->balance($outletId), 2);
        if ($difference == 0.0) {
            throw ValidationException::withMessages(['balance' => 'Saldo sudah sama dengan nominal ini.']);
        }

        return $this->adjust($outletId, $difference, $notes, $occurredOn);
    }

    protected function post(
        int $outletId,
        float $signedAmount,
        BankMovementType $type,
        ?Model $reference,
        ?string $notes,
        ?string $occurredOn,
        ?int $userId = null,
    ): ?BankMovement {
        $signedAmount = round($signedAmount, 2);

        if ($signedAmount == 0.0) {
            return null;
        }

        return DB::transaction(function () use ($outletId, $signedAmount, $type, $reference, $notes, $occurredOn, $userId) {
            if ($reference) {
                $exists = BankMovement::query()
                    ->where('type', $type->value)
                    ->where('reference_type', $reference->getMorphClass())
                    ->where('reference_id', $reference->getKey())
                    ->exists();

                if ($exists) {
                    return null;
                }
            }

            $account = $this->accountFor($outletId);
            $account = BankAccount::query()->whereKey($account->id)->lockForUpdate()->first();
            $current = (float) ($account->movements()->latest('id')->value('balance_after') ?? 0);
            $after = round($current + $signedAmount, 2);

            return BankMovement::query()->create([
                'bank_account_id' => $account->id,
                'outlet_id' => $outletId,
                'user_id' => $userId ?? Auth::id(),
                'type' => $type,
                'amount' => $signedAmount,
                'balance_after' => $after,
                'occurred_on' => $occurredOn ?? now()->toDateString(),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
            ]);
        });
    }
}
