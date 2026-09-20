<?php

namespace App\Services;

use App\Enums\PrinterStation;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WaCloudService
{
    public function sendText(string $to, string $message): void
    {
        $this->send($to, [
            'message_type' => 'text',
            'message' => $message,
        ]);
    }

    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null): void
    {
        $payload = [
            'message_type' => 'document',
            'document_url' => $documentUrl,
            'filename' => $filename,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $this->send($to, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function send(string $to, array $payload): void
    {
        $apiKey = (string) config('services.wacloud.api_key');
        $deviceId = (string) config('services.wacloud.device_id');
        $baseUrl = rtrim((string) config('services.wacloud.base_url'), '/');

        if ($apiKey === '' || $deviceId === '') {
            throw new RuntimeException('WACloud belum dikonfigurasi.');
        }

        $to = $this->normalizePhone($to);
        if ($to === '') {
            throw new RuntimeException('Nomor WhatsApp tidak valid.');
        }

        $response = Http::withHeaders([
            'X-Api-Key' => $apiKey,
            'Accept' => 'application/json',
        ])->timeout(30)->post("{$baseUrl}/devices/{$deviceId}/messages", array_merge([
            'device_id' => $deviceId,
            'to' => $to,
        ], $payload));

        if (! $response->successful() || ($response->json('success') === false)) {
            Log::warning('WACloud send failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException(
                $response->json('message')
                    ?: $response->json('error')
                    ?: 'Gagal kirim WhatsApp.'
            );
        }
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return strlen($digits) >= 10 ? $digits : '';
    }

    public function invoiceCaption(Order $order): string
    {
        $order->loadMissing(['outlet']);

        return implode("\n", array_filter([
            '*'.($order->outlet?->name ?: config('app.name')).'*',
            'Invoice '.$order->order_number,
            'Total: '.money($order->grand_total),
            (string) setting('receipt_footer', 'Terima kasih'),
        ]));
    }

    public function invoiceMessage(Order $order): string
    {
        $order->loadMissing(['items', 'payments', 'outlet', 'customer', 'table', 'user']);

        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
        $itemCount = $order->items->count();
        $taxPct = rtrim(rtrim(number_format((float) ($order->tax_rate ?? 0), 2, '.', ''), '0'), '.');

        $status = match ($order->status?->value) {
            'completed' => 'Lunas',
            'cancelled' => 'Dibatalkan',
            'new' => 'Menunggu Konfirmasi',
            'processing', 'preparing' => 'Sedang Diproses',
            'ready' => 'Siap Diambil',
            default => $order->status?->label() ?? '-',
        };

        $lines = [
            '*'.($order->outlet?->name ?: config('app.name')).'*',
            $order->order_number,
            '',
            'Nama: '.($order->customer?->name ?: ($order->user?->name ?: '-')),
            'Meja: '.($order->table?->code ? 'Meja '.$order->table->code : ($order->order_type?->label() ?: '-')),
            'Waktu Pesan: '.($order->created_at?->translatedFormat('d M Y H:i') ?? '-'),
            'Status: '.$status,
            '',
        ];

        foreach ($order->items as $item) {
            $lines[] = $this->qty((float) $item->quantity).'× '.$item->name.' — '.$fmt($item->total);
            if ($item->notes) {
                $lines[] = '  '.$item->notes;
            }
        }

        $lines[] = '';
        $lines[] = 'Subtotal ('.$itemCount.' Item): '.$fmt($order->subtotal);
        if ((float) $order->discount_amount > 0) {
            $lines[] = 'Diskon: -'.$fmt($order->discount_amount);
        }
        $lines[] = 'Charge ('.($taxPct ?: '0').'%): '.$fmt($order->tax_amount);
        $lines[] = '--------------------';
        $lines[] = '*Total: '.$fmt($order->grand_total).'*';
        $lines[] = '';
        $lines[] = (string) setting('receipt_footer', 'Terima kasih');

        return implode("\n", $lines);
    }

    public function kitchenMessage(Order $order, iterable $items): string
    {
        $order->loadMissing(['table', 'outlet', 'user']);

        $lines = [
            '*PESANAN DAPUR*',
            ($order->outlet?->name ?: config('app.name')),
            $order->order_number,
            $order->created_at?->format('d/m/Y H:i') ?? '',
        ];

        if ($order->table?->code) {
            $lines[] = 'Meja: '.$order->table->code;
        }
        $lines[] = 'Kasir: '.($order->user?->name ?? '-');
        $lines[] = '';

        foreach ($items as $item) {
            $lines[] = '• '.$this->qty((float) $item->quantity).' x '.$item->name;
            if ($item->notes) {
                $lines[] = '  Catatan: '.$item->notes;
            }
        }

        return implode("\n", $lines);
    }

    public function sendKitchenOrder(Order $order, PrinterRoutingService $printers): bool
    {
        $kitchenPhone = (string) setting('kitchen_whatsapp', '');
        if ($kitchenPhone === '') {
            return false;
        }

        if ($order->kitchen_printed_at) {
            return false;
        }

        $items = $printers->stationItems($order, PrinterStation::Kitchen->value);
        if ($items->isEmpty()) {
            return false;
        }

        $this->sendText($kitchenPhone, $this->kitchenMessage($order, $items));
        $printers->markPrinted($order, PrinterStation::Kitchen->value);

        return true;
    }

    protected function qty(float $value): string
    {
        return fmod($value, 1.0) < 0.001
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
