<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\Product;
use App\Models\Waste;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WasteService
{
    public function __construct(
        protected InventoryService $inventory,
        protected NumberGenerator $numbers,
        protected AuditService $audit,
    ) {}

    public function record(array $data): Waste
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->findOrFail($data['product_id']);

            $waste = Waste::query()->create([
                'number' => $this->numbers->next(config('pos.waste_prefix', 'WST'), 'wastes'),
                'outlet_id' => $data['outlet_id'],
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'user_id' => Auth::id(),
                'quantity' => $data['quantity'],
                'reason' => WasteReason::from($data['reason']),
                'notes' => $data['notes'] ?? null,
                'status' => 'approved',
            ]);

            $this->inventory->decrease(
                $waste->outlet_id,
                $waste->product_id,
                (float) $waste->quantity,
                StockMovementType::Waste,
                'Waste '.$waste->reason->label(),
                $waste,
                $waste->number,
            );

            $this->audit->log('created', 'waste', $waste);

            return $waste->fresh(['product', 'outlet']);
        });
    }
}
