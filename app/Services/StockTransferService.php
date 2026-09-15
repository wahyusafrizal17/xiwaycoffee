<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        protected InventoryService $inventory,
        protected NumberGenerator $numbers,
        protected AuditService $audit,
    ) {}

    public function create(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            if ($data['source_outlet_id'] === $data['destination_outlet_id']) {
                throw ValidationException::withMessages([
                    'destination_outlet_id' => 'Outlet tujuan harus berbeda.',
                ]);
            }

            $transfer = StockTransfer::query()->create([
                'number' => $this->numbers->next(config('pos.transfer_prefix', 'TRF'), 'stock_transfers'),
                'source_outlet_id' => $data['source_outlet_id'],
                'destination_outlet_id' => $data['destination_outlet_id'],
                'requested_by' => Auth::id(),
                'status' => TransferStatus::Draft,
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $transfer->fresh(['items.product', 'sourceOutlet', 'destinationOutlet']);
        });
    }

    public function request(StockTransfer $transfer): StockTransfer
    {
        $this->assertStatus($transfer, [TransferStatus::Draft]);
        $transfer->update(['status' => TransferStatus::Requested]);

        return $transfer;
    }

    public function approve(StockTransfer $transfer): StockTransfer
    {
        $this->assertStatus($transfer, [TransferStatus::Requested, TransferStatus::Draft]);
        $transfer->update([
            'status' => TransferStatus::Approved,
            'approved_by' => Auth::id(),
        ]);
        $this->audit->log('approved', 'transfers', $transfer);

        return $transfer;
    }

    public function ship(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $this->assertStatus($transfer, [TransferStatus::Approved]);
            $transfer->load('items.product');

            foreach ($transfer->items as $item) {
                $this->inventory->decrease(
                    $transfer->source_outlet_id,
                    $item->product_id,
                    (float) $item->quantity,
                    StockMovementType::Transfer,
                    'Transfer keluar '.$transfer->number,
                    $transfer,
                    $transfer->number,
                );
            }

            $transfer->update([
                'status' => TransferStatus::Shipped,
                'shipped_at' => now(),
            ]);

            return $transfer->fresh(['items']);
        });
    }

    public function receive(StockTransfer $transfer, array $received = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $received) {
            $this->assertStatus($transfer, [TransferStatus::Shipped]);
            $transfer->load('items.product');

            foreach ($transfer->items as $item) {
                $qty = isset($received[$item->id])
                    ? (float) $received[$item->id]
                    : (float) $item->quantity;
                $item->update(['received_quantity' => $qty]);

                $this->inventory->increase(
                    $transfer->destination_outlet_id,
                    $item->product_id,
                    $qty,
                    StockMovementType::Transfer,
                    'Transfer masuk '.$transfer->number,
                    $transfer,
                    $transfer->number,
                );
            }

            $transfer->update([
                'status' => TransferStatus::Completed,
                'received_by' => Auth::id(),
                'received_at' => now(),
            ]);

            $this->audit->log('received', 'transfers', $transfer);

            return $transfer->fresh(['items.product']);
        });
    }

    protected function assertStatus(StockTransfer $transfer, array $allowed): void
    {
        if (! in_array($transfer->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Status transfer tidak valid untuk aksi ini.',
            ]);
        }
    }
}
