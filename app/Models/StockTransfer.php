<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'number', 'source_outlet_id', 'destination_outlet_id', 'requested_by', 'approved_by',
    'received_by', 'status', 'transfer_date', 'notes', 'shipped_at', 'received_at',
])]
class StockTransfer extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'transfer_date' => 'date',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function sourceOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'source_outlet_id');
    }

    public function destinationOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'destination_outlet_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function toModalArray(): array
    {
        $this->loadMissing([
            'sourceOutlet', 'destinationOutlet', 'requester', 'approver', 'receiver', 'items.product.unit',
        ]);

        $items = $this->items
            ->sortBy(fn (StockTransferItem $item) => $item->product?->name ?? '')
            ->values()
            ->map(function (StockTransferItem $item) {
                $unit = $item->product?->unit?->code ?? '';
                $qty = (float) $item->quantity;
                $received = (float) ($item->received_quantity ?? 0);

                return [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? '—',
                    'sku' => $item->product?->sku ?? '—',
                    'unit' => $unit,
                    'quantity' => $qty,
                    'quantity_label' => $this->formatQty($qty, $unit),
                    'received_qty' => $received,
                    'received_label' => $this->formatQty($received, $unit),
                    'received_input' => (string) $qty,
                ];
            })
            ->all();

        $status = $this->status ?? TransferStatus::Draft;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'source_name' => $this->sourceOutlet?->name ?? '—',
            'destination_name' => $this->destinationOutlet?->name ?? '—',
            'route_label' => ($this->sourceOutlet?->name ?? '—').' → '.($this->destinationOutlet?->name ?? '—'),
            'notes' => $this->notes ?: '—',
            'requester_name' => $this->requester?->name ?? '—',
            'approver_name' => $this->approver?->name ?? '—',
            'receiver_name' => $this->receiver?->name ?? '—',
            'transfer_date_label' => $this->transfer_date?->format('d/m/Y') ?? $this->created_at?->format('d/m/Y') ?? '—',
            'shipped_label' => $this->shipped_at?->format('d/m/Y H:i') ?? '—',
            'received_label' => $this->received_at?->format('d/m/Y H:i') ?? '—',
            'items_count' => count($items),
            'items' => $items,
            'request_url' => route('transfers.request', $this),
            'approve_url' => route('transfers.approve', $this),
            'ship_url' => route('transfers.ship', $this),
            'receive_url' => route('transfers.receive', $this),
        ];
    }

    protected function formatQty(float $value, string $unit): string
    {
        $label = qty($value);

        return $unit !== '' ? $label.' '.$unit : $label;
    }
}
