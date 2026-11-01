<?php

use App\Services\BankAccountService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bank:close-qris', function (BankAccountService $bank) {
    $result = $bank->closeQris();
    $this->info($result['count'].' pembayaran QRIS masuk rekening, total '.number_format($result['total'], 0, ',', '.'));
})->purpose('Masukkan QRIS yang belum tercatat ke saldo rekening');

Schedule::command('bank:close-qris')->dailyAt('00:00')->timezone('Asia/Jakarta');
