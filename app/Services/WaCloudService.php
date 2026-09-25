<?php

namespace App\Services;

use App\Enums\OrderType;
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
            '☕ *'.mb_strtoupper((string) ($order->outlet?->name ?: config('app.name'))).'*',
            '🧾 *'.$order->order_number.'*',
            '*TOTAL '.number_format((float) $order->grand_total, 0, ',', '.').'*',
        ]));
    }

    public function invoiceMessage(Order $order): string
    {
        $order->loadMissing(['items', 'payments', 'outlet', 'customer', 'table', 'user']);

        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
        $isDineIn = $order->order_type === OrderType::DineIn;
        $tableLabel = $this->kitchenTableLabel($order->table?->code);

        $status = match (true) {
            $order->status?->value === 'cancelled' => 'Dibatalkan',
            $order->payment_status?->value === 'paid' => 'Lunas',
            default => 'Menunggu Konfirmasi',
        };

        $lines = [
            '☕ *'.mb_strtoupper((string) ($order->outlet?->name ?: config('app.name'))).'*',
            '━━━━━━━━━━━━━━━━━━━━',
            '🧾 *'.$order->order_number.'*',
            '',
            '👤 Kasir: '.($order->user?->name ?? '-'),
            '🕐 '.($order->created_at?->translatedFormat('d M Y H:i') ?? '-'),
            '',
        ];

        if ($isDineIn) {
            $lines[] = '📍 *DINE IN*';
            if ($tableLabel) {
                $lines[] = '🪑 *'.$tableLabel.'*';
            }
        } else {
            $lines[] = '🥡 *TAKE AWAY*';
            if ($tableLabel) {
                $lines[] = '🪑 *'.$tableLabel.'*';
            }
        }

        $lines[] = '━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '*PESANAN*';
        $lines[] = '';

        foreach ($order->items as $item) {
            $left = $this->qty((float) $item->quantity).' × '.$item->name;
            $lines[] = $this->invoicePad($left, $fmt($item->total));
            if (filled($item->notes)) {
                $lines[] = '  └ Catatan: *'.$item->notes.'*';
            }
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━';
        $lines[] = $this->invoicePad('Subtotal', $fmt($order->subtotal));
        if ((float) $order->discount_amount > 0) {
            $lines[] = $this->invoicePad('Diskon', '-'.$fmt($order->discount_amount));
        }
        if ((float) $order->tax_amount > 0) {
            $lines[] = $this->invoicePad('Biaya Layanan', $fmt($order->tax_amount));
        }
        $lines[] = '━━━━━━━━━━━━━━━━━━━━';
        $lines[] = $this->invoicePad('*TOTAL', $fmt($order->grand_total).'*');
        $lines[] = '';
        $lines[] = '💳 *Status: '.$status.'*';
        $lines[] = '';
        $lines[] = 'Terima kasih sudah memilih';
        $lines[] = '*XIWAY COFFEE* 🤎';
        $lines[] = '';
        $lines[] = '_Good Coffee. Better People._';

        return implode("\n", $lines);
    }

    protected function invoicePad(string $left, string $right, int $width = 34): string
    {
        $gap = $width - mb_strlen(strip_tags(str_replace(['*', '_'], '', $left))) - mb_strlen(strip_tags(str_replace(['*', '_'], '', $right)));

        return $left.str_repeat(' ', max(2, $gap)).$right;
    }

    public function kitchenMessage(Order $order, iterable $items): string
    {
        $order->loadMissing(['table', 'outlet', 'user']);

        $isDineIn = $order->order_type === OrderType::DineIn;
        $tableLabel = $this->kitchenTableLabel($order->table?->code);

        $lines = [
            '🍽️ *PESANAN DAPUR*',
            '━━━━━━━━━━━━━━━━━━',
            '*'.mb_strtoupper((string) ($order->outlet?->name ?: config('app.name'))).'*',
            '',
            '🧾 *'.$order->order_number.'*',
            '🕐 '.($order->created_at?->format('d/m/Y H:i') ?? ''),
            '👤 Kasir: '.($order->user?->name ?? '-'),
            '',
            '━━━━━━━━━━━━━━━━━━',
        ];

        if ($isDineIn) {
            $lines[] = '📍 *DINE IN*';
            if ($tableLabel) {
                $lines[] = '🪑 *'.$tableLabel.'*';
            }
        } else {
            $lines[] = '🥡 *TAKE AWAY*';
            if ($tableLabel) {
                $lines[] = '🪑 *'.$tableLabel.'*';
            }
        }

        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '*PESANAN:*';
        $lines[] = '';

        foreach ($items as $item) {
            $lines[] = '• '.$this->qty((float) $item->quantity).' × '.$item->name;
            if (filled($item->notes)) {
                $lines[] = '  └ Catatan: *'.$item->notes.'*';
            }
            $lines[] = '';
        }

        $lines[] = '━━━━━━━━━━━━━━━━━━';
        if ($isDineIn && $tableLabel) {
            $lines[] = '📌 *ANTAR KE: '.$tableLabel.'*';
        } elseif ($tableLabel) {
            $lines[] = '📌 *PACKING — ANTAR KE: '.$tableLabel.'*';
        } else {
            $lines[] = '📌 *PACKING — TAKE AWAY*';
        }
        $lines[] = '━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = '_Terima Kasih_';

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

    protected function kitchenTableLabel(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        if (ctype_digit($code)) {
            return 'MEJA '.str_pad($code, 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/(\d+)/', $code, $matches)) {
            return 'MEJA '.str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        return 'MEJA '.$code;
    }

    protected function qty(float $value): string
    {
        return fmod($value, 1.0) < 0.001
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
