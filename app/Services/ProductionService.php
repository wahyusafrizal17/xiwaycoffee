<?php

namespace App\Services;

use App\Enums\ProductionStatus;
use App\Enums\StockMovementType;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function __construct(
        protected InventoryService $inventory,
        protected NumberGenerator $numbers,
        protected AuditService $audit,
    ) {}

    /**
     * Expand BOM recursively up to 4 levels into a flat list of raw/semi requirements.
     *
     * @return array<int, array{product_id:int, quantity:float, level:int}>
     */
    public function explode(Bom $bom, float $outputQty, int $maxLevel = 4): array
    {
        $result = [];
        $this->walk($bom, $outputQty, 1, $maxLevel, $result);

        return $result;
    }

    public function create(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->findOrFail($data['product_id']);
            $bom = ! empty($data['bom_id'])
                ? Bom::query()->with('items')->findOrFail($data['bom_id'])
                : $product->activeBom();

            if ($bom) {
                $bom->load('items');
            }

            $order = ProductionOrder::query()->create([
                'number' => $this->numbers->next(config('pos.production_prefix', 'PROD'), 'production_orders'),
                'outlet_id' => $data['outlet_id'],
                'product_id' => $product->id,
                'bom_id' => $bom?->id,
                'user_id' => Auth::id(),
                'quantity_planned' => $data['quantity_planned'],
                'production_date' => $data['production_date'] ?? now()->toDateString(),
                'status' => ProductionStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($bom) {
                $bom->loadMissing('items.component.unit', 'items.unit');
                foreach ($bom->items as $item) {
                    $qty = $item->requiredQuantity((float) $data['quantity_planned']);
                    $fromUnit = $item->unit;
                    $stockUnit = $item->component?->unit;
                    if ($fromUnit && $stockUnit) {
                        $qty = $fromUnit->convertTo($stockUnit, $qty);
                    }

                    $order->items()->create([
                        'product_id' => $item->component_id,
                        'unit_id' => $stockUnit?->id ?? $item->unit_id ?? $item->component?->unit_id,
                        'quantity_required' => $qty,
                        'quantity_used' => 0,
                    ]);
                }
            }

            return $order->fresh(['items.product', 'product']);
        });
    }

    public function start(ProductionOrder $order): ProductionOrder
    {
        $order->update([
            'status' => ProductionStatus::InProduction,
            'started_at' => now(),
        ]);

        return $order->fresh();
    }

    public function complete(ProductionOrder $order, float $producedQty): ProductionOrder
    {
        return DB::transaction(function () use ($order, $producedQty) {
            $order = $order->fresh(['items.product', 'product', 'bom']);

            if (in_array($order->status, [ProductionStatus::Completed, ProductionStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'Production order sudah ditutup.']);
            }

            $planned = (float) $order->quantity_planned;
            $yield = $planned > 0 ? round(($producedQty / $planned) * 100, 2) : 0;
            $batchNumber = $this->numbers->next('PROD', 'production_batches', 'batch_number');

            $scale = $planned > 0 ? ($producedQty / $planned) : 1;

            foreach ($order->items as $item) {
                $used = (float) $item->quantity_required * $scale;
                $item->update(['quantity_used' => $used]);
                $this->inventory->decrease(
                    $order->outlet_id,
                    $item->product_id,
                    $used,
                    StockMovementType::Production,
                    'Pemakaian produksi '.$order->number,
                    $order,
                    $order->number,
                );
            }

            $this->inventory->increase(
                $order->outlet_id,
                $order->product_id,
                $producedQty,
                StockMovementType::Production,
                'Hasil produksi '.$order->number,
                $order,
                $order->number,
            );

            $order->update([
                'quantity_produced' => $producedQty,
                'yield_percentage' => $yield,
                'batch_number' => $batchNumber,
                'status' => ProductionStatus::Completed,
                'completed_at' => now(),
            ]);

            ProductionBatch::query()->create([
                'batch_number' => $batchNumber,
                'production_order_id' => $order->id,
                'product_id' => $order->product_id,
                'outlet_id' => $order->outlet_id,
                'quantity' => $planned,
                'yield_quantity' => $producedQty,
                'remaining_quantity' => $producedQty,
                'produced_at' => $order->production_date,
                'expires_at' => now()->addDays(7)->toDateString(),
            ]);

            $this->audit->log('completed', 'production', $order, null, [
                'produced' => $producedQty,
                'yield' => $yield,
                'batch' => $batchNumber,
            ]);

            return $order->fresh(['items.product', 'batch', 'product']);
        });
    }

    public function cancel(ProductionOrder $order, string $reason): ProductionOrder
    {
        if ($order->status === ProductionStatus::Completed) {
            throw ValidationException::withMessages(['status' => 'Produksi selesai tidak dapat dibatalkan.']);
        }

        $order->update([
            'status' => ProductionStatus::Cancelled,
            'notes' => trim(($order->notes ? $order->notes."\n" : '').$reason),
        ]);

        return $order;
    }

    protected function walk(Bom $bom, float $outputQty, int $level, int $maxLevel, array &$result): void
    {
        if ($level > $maxLevel) {
            return;
        }

        $bom->loadMissing('items.component');

        foreach ($bom->items as $item) {
            /** @var BomItem $item */
            $qty = $item->requiredQuantity($outputQty);
            $result[] = [
                'product_id' => $item->component_id,
                'name' => $item->component?->name,
                'quantity' => $qty,
                'unit' => $item->unit?->code ?? $item->component?->unit?->code,
                'level' => $level,
            ];

            $childBom = $item->component?->activeBom();
            if ($childBom && $level < $maxLevel) {
                $this->walk($childBom, $qty, $level + 1, $maxLevel, $result);
            }
        }
    }
}
