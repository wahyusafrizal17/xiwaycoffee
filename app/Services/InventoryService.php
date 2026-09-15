<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        protected NumberGenerator $numbers,
    ) {}

    public function getOrCreate(int $outletId, int $productId): Inventory
    {
        return Inventory::query()->firstOrCreate(
            ['outlet_id' => $outletId, 'product_id' => $productId],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );
    }

    public function quantity(int $outletId, int $productId): float
    {
        return (float) ($this->getOrCreate($outletId, $productId)->quantity);
    }

    public function adjust(
        int $outletId,
        int $productId,
        float $delta,
        StockMovementType $type,
        string $reason,
        ?Model $reference = null,
        ?string $referenceNumber = null,
        bool $allowNegative = false,
        ?int $batchId = null,
    ): InventoryMovement {
        $inventory = $this->getOrCreate($outletId, $productId);
        $product = Product::query()->findOrFail($productId);
        $before = (float) $inventory->quantity;
        $after = $before + $delta;

        if (! $allowNegative && $after < -0.0001) {
            throw ValidationException::withMessages([
                'quantity' => "Stok {$product->name} tidak mencukupi. Tersedia: {$before}.",
            ]);
        }

        $inventory->update(['quantity' => $after]);

        return InventoryMovement::query()->create([
            'reference_number' => $referenceNumber ?? $this->numbers->next('MOV', 'inventory_movements', 'reference_number'),
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'unit_id' => $product->unit_id,
            'batch_id' => $batchId,
            'user_id' => Auth::id(),
            'type' => $type,
            'quantity' => $delta,
            'before_stock' => $before,
            'after_stock' => $after,
            'reason' => $reason,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
        ]);
    }

    public function consumeBatches(int $outletId, int $productId, float $qty): ?int
    {
        $remaining = $qty;
        $firstBatchId = null;

        $batches = \App\Models\ProductionBatch::query()
            ->where('outlet_id', $outletId)
            ->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('produced_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $batch->remaining_quantity, $remaining);
            $batch->update(['remaining_quantity' => (float) $batch->remaining_quantity - $take]);
            $firstBatchId ??= $batch->id;
            $remaining -= $take;
        }

        return $firstBatchId;
    }

    public function increase(int $outletId, int $productId, float $qty, StockMovementType $type, string $reason, ?Model $reference = null, ?string $ref = null): InventoryMovement
    {
        return $this->adjust($outletId, $productId, abs($qty), $type, $reason, $reference, $ref);
    }

    public function decrease(int $outletId, int $productId, float $qty, StockMovementType $type, string $reason, ?Model $reference = null, ?string $ref = null, ?int $batchId = null): InventoryMovement
    {
        return $this->adjust($outletId, $productId, -abs($qty), $type, $reason, $reference, $ref, false, $batchId);
    }

    public function lowStock(?int $outletId = null)
    {
        $outletId = $outletId ?? current_outlet_id();

        return Product::query()
            ->where('is_stockable', true)
            ->where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->with(['unit', 'inventories' => fn ($q) => $q->where('outlet_id', $outletId)])
            ->get()
            ->filter(function (Product $product) use ($outletId) {
                $qty = (float) ($product->inventories->first()?->quantity ?? 0);

                return $qty <= (float) $product->reorder_level;
            })
            ->values();
    }

    public function outOfStock(?int $outletId = null)
    {
        $outletId = $outletId ?? current_outlet_id();

        return Product::query()
            ->where('is_stockable', true)
            ->where('is_active', true)
            ->with(['unit', 'inventories' => fn ($q) => $q->where('outlet_id', $outletId)])
            ->get()
            ->filter(fn (Product $product) => (float) ($product->inventories->first()?->quantity ?? 0) <= 0)
            ->values();
    }
}
