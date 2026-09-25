<?php

namespace App\Services;

use App\Models\Order;

class EscPosPrinter
{
    public const WIDTH = 48;

    public function receipt(Order $order): string
    {
        $order->loadMissing(['items', 'payments', 'outlet', 'customer', 'table', 'user']);

        $out = $this->init();
        $out .= $this->align(1);
        $out .= $this->bold(true);
        $out .= $this->line($order->outlet?->name ?: config('app.name'));
        $out .= $this->bold(false);
        $out .= $this->line($order->order_number);
        $out .= $this->line($order->created_at?->format('d/m/Y H:i') ?? '');
        $type = $order->order_type?->label() ?? '';
        if ($order->table?->code) {
            $type .= ' · '.$order->table->code;
        }
        $out .= $this->line($type);
        $out .= $this->line('Kasir: '.($order->user?->name ?? '-'));
        $out .= $this->rule();
        $out .= $this->align(0);

        foreach ($order->items as $item) {
            $out .= $this->cols($this->qty((float) $item->quantity).' x '.$item->name, money($item->total));
            if ($item->notes) {
                $out .= $this->line('  '.$item->notes);
            }
        }

        $out .= $this->rule();
        $out .= $this->cols('Subtotal', money($order->subtotal));
        $out .= $this->cols('Diskon', money($order->discount_amount));
        $out .= $this->cols('Biaya Layanan', money($order->tax_amount));
        $out .= $this->bold(true);
        $out .= $this->cols('Total', money($order->grand_total));
        $out .= $this->bold(false);

        foreach ($order->payments as $payment) {
            $out .= $this->cols($payment->method?->label() ?? 'Bayar', money($payment->amount));
            if ((float) $payment->change_amount > 0) {
                $out .= $this->cols('Kembalian', money($payment->change_amount));
            }
        }

        $out .= $this->rule();
        $out .= $this->align(1);
        $out .= $this->line((string) setting('receipt_footer', 'Terima kasih'));
        $out .= $this->cut();

        return $out;
    }

    public function receiptHtml(Order $order): string
    {
        $order->loadMissing(['items', 'payments', 'outlet', 'customer', 'table', 'user']);

        $rows = '';
        foreach ($order->items as $item) {
            $rows .= $this->htmlRow($this->qty((float) $item->quantity).' x '.$item->name, money($item->total));
            if ($item->notes) {
                $rows .= '<div class="n">'.$this->e($item->notes).'</div>';
            }
        }

        $pays = '';
        foreach ($order->payments as $payment) {
            $pays .= $this->htmlRow($payment->method?->label() ?? 'Bayar', money($payment->amount));
            if ((float) $payment->change_amount > 0) {
                $pays .= $this->htmlRow('Kembalian', money($payment->change_amount));
            }
        }

        $type = $order->order_type?->label() ?? '';
        if ($order->table?->code) {
            $type .= ' · '.$order->table->code;
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'html,body{margin:0;padding:0;width:72mm;font:12px/1.35 monospace;color:#000;background:#fff}'
            .'.c{text-align:center}.b{font-weight:700}.r{display:flex;justify-content:space-between;gap:6px}'
            .'.n{margin:0 0 2px 8px}.hr{border:0;border-top:1px dashed #000;margin:6px 0}'
            .'</style></head><body>'
            .'<div class="c b">'.$this->e($order->outlet?->name ?: (string) config('app.name')).'</div>'
            .'<div class="c">'.$this->e($order->order_number).'</div>'
            .'<div class="c">'.$this->e($order->created_at?->format('d/m/Y H:i') ?? '').'</div>'
            .'<div class="c">'.$this->e($type).'</div>'
            .'<div class="c">Kasir: '.$this->e($order->user?->name ?? '-').'</div>'
            .'<hr class="hr">'
            .$rows
            .'<hr class="hr">'
            .$this->htmlRow('Subtotal', money($order->subtotal))
            .$this->htmlRow('Diskon', money($order->discount_amount))
            .$this->htmlRow('Biaya Layanan', money($order->tax_amount))
            .'<div class="b">'.$this->htmlRow('Total', money($order->grand_total)).'</div>'
            .$pays
            .'<hr class="hr">'
            .'<div class="c">'.$this->e((string) setting('receipt_footer', 'Terima kasih')).'</div>'
            .'</body></html>';
    }

    public function receiptHeightMm(Order $order): int
    {
        $order->loadMissing(['items', 'payments']);
        $lines = 10 + $order->items->count() + $order->payments->count();

        return min(180, max(60, 28 + ($lines * 6)));
    }

    public function ticket(Order $order, string $station, iterable $items): string
    {
        $order->loadMissing(['table', 'outlet']);

        $title = $this->ticketTitle($station);

        $out = $this->init();
        $out .= $this->align(1);
        $out .= $this->bold(true);
        $out .= $this->line($title);
        $out .= $this->bold(false);
        $out .= $this->line($order->order_number);
        if ($order->table?->code) {
            $out .= $this->line('Meja '.$order->table->code);
        }
        $out .= $this->rule();
        $out .= $this->align(0);

        foreach ($items as $item) {
            $notes = is_array($item) ? ($item['notes'] ?? '') : $item->notes;
            $out .= $this->line($this->itemLine($item, $station));
            if ($notes) {
                $out .= $this->line('  '.$notes);
            }
        }

        $out .= $this->cut();

        return $out;
    }

    public function ticketHtml(Order $order, string $station, iterable $items): string
    {
        $order->loadMissing(['table']);
        $title = $this->ticketTitle($station);

        $rows = '';
        $count = 0;
        foreach ($items as $item) {
            $count++;
            $notes = is_array($item) ? ($item['notes'] ?? '') : $item->notes;
            $rows .= '<div>'.$this->e($this->itemLine($item, $station)).'</div>';
            if ($notes) {
                $rows .= '<div class="n">'.$this->e((string) $notes).'</div>';
            }
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'html,body{margin:0;padding:0;width:72mm;font:12px/1.35 monospace;color:#000}'
            .'.c{text-align:center}.b{font-weight:700}.n{margin:0 0 2px 8px}.hr{border:0;border-top:1px dashed #000;margin:6px 0}'
            .'</style></head><body>'
            .'<div class="c b">'.$this->e($title).'</div>'
            .'<div class="c">'.$this->e($order->order_number).'</div>'
            .($order->table?->code ? '<div class="c">Meja '.$this->e($order->table->code).'</div>' : '')
            .'<hr class="hr">'.$rows
            .'</body></html>';
    }

    public function send(string $ip, int $port, string $bytes): bool
    {
        if ($ip === '' || $bytes === '') {
            return false;
        }

        $socket = @stream_socket_client('tcp://'.$ip.':'.$port, $errno, $errstr, 0.4);
        if (! $socket) {
            return false;
        }

        stream_set_timeout($socket, 1);
        $ok = fwrite($socket, $bytes) !== false;
        fclose($socket);

        return $ok;
    }

    public function ticketHeightMm(iterable $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            $count++;
        }

        return min(120, max(40, 24 + ($count * 8)));
    }

    protected function ticketTitle(string $station): string
    {
        return match ($station) {
            'kitchen' => 'KITCHEN',
            'bar' => 'BAR',
            'prep' => 'DAPUR / BAR',
            default => 'TICKET',
        };
    }

    protected function itemLine(mixed $item, string $station): string
    {
        $name = is_array($item) ? ($item['name'] ?? '') : $item->name;
        $qty = is_array($item) ? ($item['qty'] ?? $item['quantity'] ?? 1) : $item->quantity;
        $line = $this->qty((float) $qty).' x '.$name;
        if ($station !== 'prep') {
            return $line;
        }

        $tag = match (is_array($item) ? ($item['station'] ?? '') : ($item->station ?? $item->product?->station ?? $item->product?->category?->station ?? '')) {
            'kitchen' => 'DAPUR',
            'bar' => 'BAR',
            default => '',
        };

        return $tag === '' ? $line : '['.$tag.'] '.$line;
    }

    protected function init(): string
    {
        return "\x1B\x40";
    }

    protected function align(int $mode): string
    {
        return "\x1B\x61".chr($mode);
    }

    protected function bold(bool $on): string
    {
        return "\x1B\x45".($on ? "\x01" : "\x00");
    }

    protected function line(string $text): string
    {
        return $this->ascii($text)."\n";
    }

    protected function rule(): string
    {
        return str_repeat('-', self::WIDTH)."\n";
    }

    protected function cols(string $left, string $right): string
    {
        $left = $this->ascii($left);
        $right = $this->ascii($right);
        $space = self::WIDTH - strlen($left) - strlen($right);
        if ($space < 1) {
            $left = substr($left, 0, max(0, self::WIDTH - strlen($right) - 1));
            $space = 1;
        }

        return $left.str_repeat(' ', $space).$right."\n";
    }

    protected function qty(float $qty): string
    {
        return fmod($qty, 1.0) === 0.0
            ? (string) (int) $qty
            : rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
    }

    protected function ascii(string $text): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);

        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    }

    protected function cut(): string
    {
        return "\n\n\x1D\x56\x00";
    }

    protected function htmlRow(string $left, string $right): string
    {
        return '<div class="r"><span>'.$this->e($left).'</span><span>'.$this->e($right).'</span></div>';
    }

    protected function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
