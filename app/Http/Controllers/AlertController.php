<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function lowStock(Request $request, InventoryService $inventory): JsonResponse
    {
        abort_unless($request->user()->hasPermission('inventory.view') || $request->user()->hasPermission('pos.access'), 403);

        $items = $inventory->lowStock(current_outlet_id())->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'quantity' => (float) ($product->inventories->first()?->quantity ?? 0),
            'reorder_level' => (float) $product->reorder_level,
            'unit' => $product->unit?->code,
        ])->values();

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
        ]);
    }
}
