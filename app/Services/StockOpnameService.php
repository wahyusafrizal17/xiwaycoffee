<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockOpname;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function __construct(
        protected InventoryService $inventory,
        protected NumberGenerator $numbers,
        protected AuditService $audit,
    ) {}

    public function create(array $data): StockOpname
    {
        return DB::transaction(function () use ($data) {
            $opname = StockOpname::query()->create([
                'number' => $this->numbers->next(config('pos.opname_prefix', 'OPN'), 'stock_opnames'),
                'outlet_id' => $data['outlet_id'],
                'category_id' => $data['category_id'] ?? null,
                'created_by' => Auth::id(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            $products = Product::query()
                ->where('is_stockable', true)
                ->when($data['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
                ->orderBy('name')
                ->get();

            foreach ($products as $product) {
                $system = $this->inventory->quantity($opname->outlet_id, $product->id);
                $opname->items()->create([
                    'product_id' => $product->id,
                    'system_qty' => $system,
                    'physical_qty' => $system,
                    'difference' => 0,
                ]);
            }

            return $opname->fresh(['items.product.unit']);
        });
    }

    public function updateItems(StockOpname $opname, array $items): StockOpname
    {
        if ($opname->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Opname sudah dikunci.']);
        }

        foreach ($items as $row) {
            $item = $opname->items()->whereKey($row['id'])->first();
            if (! $item) {
                continue;
            }
            $physical = (float) $row['physical_qty'];
            $item->update([
                'physical_qty' => $physical,
                'difference' => $physical - (float) $item->system_qty,
                'reason' => $row['reason'] ?? null,
            ]);
        }

        return $opname->fresh(['items.product']);
    }

    public function finalize(StockOpname $opname): StockOpname
    {
        return DB::transaction(function () use ($opname) {
            if ($opname->status === 'finalized') {
                throw ValidationException::withMessages(['status' => 'Opname sudah difinalisasi.']);
            }

            $opname->load('items.product');

            foreach ($opname->items as $item) {
                $diff = (float) $item->difference;
                if (abs($diff) < 0.0001) {
                    continue;
                }

                $this->inventory->adjust(
                    $opname->outlet_id,
                    $item->product_id,
                    $diff,
                    StockMovementType::Adjustment,
                    'Stock opname '.$opname->number.($item->reason ? ' — '.$item->reason : ''),
                    $opname,
                    $opname->number,
                    true,
                );
            }

            $opname->update([
                'status' => 'finalized',
                'approved_by' => Auth::id(),
                'finalized_at' => now(),
            ]);

            $this->audit->log('finalized', 'stock_opname', $opname);

            return $opname->fresh(['items.product']);
        });
    }
}
