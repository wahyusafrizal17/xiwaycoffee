@extends('layouts.app')
@section('title', 'Tables')
@section('breadcrumb', 'Front of house')
@section('content')
<div
    class="tables-page"
    x-data="{
        createOpen: false,
        editOpen: false,
        reserveOpen: false,
        transferOpen: false,
        mergeOpen: false,
        splitOpen: false,
        splitSource: '',
        splitItems: [],
        editing: { id: null, code: '', name: '', capacity: 2, shape: 'square', zone: '' },
        reserveTableId: '',
        openEdit(table) {
            this.editing = table;
            this.editOpen = true;
        },
        openReserve(id) {
            this.reserveTableId = id;
            this.reserveOpen = true;
        },
        async loadSplitItems() {
            if (!this.splitSource) { this.splitItems = []; return; }
            const res = await fetch('{{ route('tables.live') }}', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            const table = (data.tables || []).find(t => String(t.id) === String(this.splitSource));
            this.splitItems = table?.items || [];
        }
    }"
>
    @php
        $availableCount = $tables->where('status', \App\Enums\TableStatus::Available)->count();
        $occupiedCount = $tables->where('status', \App\Enums\TableStatus::Occupied)->count();
        $reservedCount = $tables->where('status', \App\Enums\TableStatus::Reserved)->count();
    @endphp

    <section class="card overflow-hidden">
        <div class="tables-toolbar">
            <div>
                <p class="stat-kicker">Floor plan</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-heading">Atur posisi meja</h1>
                <p class="mt-1 text-sm text-muted">Geser kartu untuk mengubah layout. Status terbarui otomatis.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-ghost !py-2" @click="reserveOpen = true">Reservasi</button>
                <button type="button" class="btn-ghost !py-2" @click="transferOpen = true">Transfer</button>
                <button type="button" class="btn-ghost !py-2" @click="mergeOpen = true">Merge</button>
                <button type="button" class="btn-ghost !py-2" @click="splitOpen = true">Split</button>
                <button type="button" class="btn-add !py-2" @click="createOpen = true">Tambah meja</button>
            </div>
        </div>
        <div class="tables-stats">
            <div class="tables-stat tables-stat-available">
                <span>Available</span>
                <strong id="count-available">{{ $availableCount }}</strong>
            </div>
            <div class="tables-stat tables-stat-occupied">
                <span>Occupied</span>
                <strong id="count-occupied">{{ $occupiedCount }}</strong>
            </div>
            <div class="tables-stat tables-stat-reserved">
                <span>Reserved</span>
                <strong id="count-reserved">{{ $reservedCount }}</strong>
            </div>
        </div>
        <div class="floor-canvas">
        @forelse ($tables as $index => $table)
            @php
                $statusClass = match ($table->status) {
                    \App\Enums\TableStatus::Available => 'table-tile-available',
                    \App\Enums\TableStatus::Occupied => 'table-tile-occupied',
                    \App\Enums\TableStatus::Reserved => 'table-tile-reserved',
                    default => '',
                };
                $badge = match ($table->status) {
                    \App\Enums\TableStatus::Available => 'tables-tile-badge-available',
                    \App\Enums\TableStatus::Occupied => 'tables-tile-badge-occupied',
                    \App\Enums\TableStatus::Reserved => 'tables-tile-badge-reserved',
                    default => 'tables-tile-badge-available',
                };
                $guest = $table->reservations->first();
                $order = $table->orders->first();
                $shapeClass = match ($table->shape) {
                    'round' => '!rounded-[36px]',
                    'rect' => '!rounded-2xl',
                    default => '',
                };
            @endphp
            <div
                class="absolute z-10"
                x-data="tableDrag({{ $table->id }}, {{ (int) ($table->pos_x ?? (32 + ($index % 5) * 196)) }}, {{ (int) ($table->pos_y ?? (32 + intdiv($index, 5) * 180)) }})"
                :style="`left:${x}px;top:${y}px`"
                @mousedown.prevent="start($event)"
                @mousemove.window="move($event)"
                @mouseup.window="end()"
            >
                <div class="table-tile {{ $statusClass }} {{ $shapeClass }}" data-table-id="{{ $table->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <a href="{{ route('tables.show', $table) }}" class="text-[17px] font-semibold leading-none tracking-tight text-heading" @mousedown.stop>{{ $table->code }}</a>
                        <span class="tables-tile-badge {{ $badge }}" data-table-status>{{ $table->status->label() }}</span>
                    </div>
                    <p class="mt-2 text-xs text-muted">{{ $table->name }} · {{ $table->capacity }} pax</p>
                    @if ($table->zone)
                        <p class="mt-0.5 text-[11px] text-muted">{{ $table->zone }}</p>
                    @endif
                    @if ($guest)
                        <p class="tables-tile-guest">{{ $guest->guest_name }}</p>
                    @elseif ($order)
                        <p class="tables-tile-guest">{{ (int) $order->items_count }} item{{ $table->open_minutes ? ' · '.$table->open_minutes.' menit' : '' }}</p>
                        @if ($order->notes)
                            <p class="mt-0.5 text-[11px] text-muted">{{ $order->notes }}</p>
                        @endif
                    @elseif ($table->open_minutes)
                        <p class="tables-tile-guest">{{ $table->open_minutes }} menit</p>
                    @endif
                    <div class="tables-tile-actions" @mousedown.stop>
                        <button type="button" @click="openEdit(@js(['id' => $table->id, 'code' => $table->code, 'name' => $table->name, 'capacity' => $table->capacity, 'shape' => $table->shape, 'zone' => $table->zone]))">Edit</button>
                        <button type="button" @click="openReserve({{ $table->id }})">Reserve</button>
                        <form method="POST" action="{{ route('tables.destroy', $table) }}" class="ml-auto" onsubmit="return confirm('Hapus meja ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="is-danger">Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <p class="text-sm font-medium text-heading">Belum ada meja</p>
                <p class="mt-1 text-xs text-muted">Tambahkan meja untuk mulai mengatur floor plan.</p>
            </div>
        @endforelse
        </div>
    </section>

    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h5 class="card-header-title">Daftar reservasi</h5>
                <p class="card-header-subtitle">Tamu yang sudah booking di outlet ini.</p>
            </div>
            <span class="badge-soft">{{ $reservations->count() }} aktif</span>
        </div>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Tamu</th>
                        <th>Meja</th>
                        <th>Waktu</th>
                        <th>Pax</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr class="tables-reserve-row">
                            <td>
                                <div class="tables-guest">
                                    <span class="tables-guest-mark">{{ strtoupper(mb_substr($reservation->guest_name, 0, 1)) }}</span>
                                    <div>
                                        <strong>{{ $reservation->guest_name }}</strong>
                                        <span>{{ $reservation->guest_phone ?: 'Tanpa telepon' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('tables.show', $reservation->table_id) }}" class="font-semibold text-heading">{{ $reservation->table?->code ?? '—' }}</a>
                            </td>
                            <td>{{ $reservation->reserved_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $reservation->guest_count }} orang</td>
                            <td class="text-muted">{{ $reservation->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-sm text-slate-400">Belum ada reservasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="createOpen" x-cloak>
        <div class="card w-full max-w-lg p-6" @click.outside="createOpen = false">
            <h3 class="text-lg font-semibold text-heading">Tambah meja</h3>
            <form method="POST" action="{{ route('tables.store') }}" class="mt-5 space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Kode</label>
                        <input class="input" name="code" required maxlength="20" placeholder="T-01">
                    </div>
                    <div>
                        <label class="label">Nama</label>
                        <input class="input" name="name" required maxlength="50" placeholder="Meja jendela">
                    </div>
                    <div>
                        <label class="label">Kapasitas</label>
                        <input class="input" type="number" name="capacity" min="1" value="2" required>
                    </div>
                    <div>
                        <label class="label">Bentuk</label>
                        <select name="shape" class="input">
                            <option value="square">Square</option>
                            <option value="round">Round</option>
                            <option value="rect">Rect</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Zona</label>
                        <input class="input" name="zone" maxlength="50" placeholder="Indoor / Terrace">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost flex-1" @click="createOpen = false">Batal</button>
                    <button class="btn-primary flex-1" type="submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="editOpen" x-cloak>
        <div class="card w-full max-w-lg p-6" @click.outside="editOpen = false">
            <h3 class="text-lg font-semibold">Edit meja</h3>
            <form method="POST" :action="`{{ url('/tables') }}/${editing.id}`" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Kode</label>
                        <input class="input" name="code" required maxlength="20" x-model="editing.code">
                    </div>
                    <div>
                        <label class="label">Nama</label>
                        <input class="input" name="name" required maxlength="50" x-model="editing.name">
                    </div>
                    <div>
                        <label class="label">Kapasitas</label>
                        <input class="input" type="number" name="capacity" min="1" required x-model="editing.capacity">
                    </div>
                    <div>
                        <label class="label">Bentuk</label>
                        <select name="shape" class="input" x-model="editing.shape">
                            <option value="square">Square</option>
                            <option value="round">Round</option>
                            <option value="rect">Rect</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Zona</label>
                        <input class="input" name="zone" maxlength="50" x-model="editing.zone">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost flex-1" @click="editOpen = false">Batal</button>
                    <button class="btn-primary flex-1" type="submit">Update</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="reserveOpen" x-cloak>
        <div class="card w-full max-w-lg p-6" @click.outside="reserveOpen = false">
            <h3 class="text-lg font-semibold">Reservasi meja</h3>
            <form method="POST" action="{{ route('tables.reserve') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="label">Meja</label>
                    <select name="table_id" class="input" required x-model="reserveTableId">
                        <option value="">Pilih meja</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table->id }}">{{ $table->code }} · {{ $table->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Nama tamu</label>
                        <input class="input" name="guest_name" required maxlength="100">
                    </div>
                    <div>
                        <label class="label">Telepon</label>
                        <input class="input" name="guest_phone" maxlength="30">
                    </div>
                    <div>
                        <label class="label">Jumlah tamu</label>
                        <input class="input" type="number" name="guest_count" min="1" value="2" required>
                    </div>
                    <div>
                        <label class="label">Waktu</label>
                        <input class="input" type="datetime-local" name="reserved_at" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Pelanggan (opsional)</label>
                        <select name="customer_id" class="input">
                            <option value="">Tidak terkait</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Catatan</label>
                        <input class="input" name="notes" placeholder="Permintaan khusus">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost flex-1" @click="reserveOpen = false">Batal</button>
                    <button class="btn-primary flex-1" type="submit">Simpan reservasi</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="transferOpen" x-cloak>
        <div class="card w-full max-w-md p-6" @click.outside="transferOpen = false">
            <h3 class="text-lg font-semibold">Transfer meja</h3>
            <form method="POST" action="{{ route('tables.transfer') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="label">Dari</label>
                    <select name="from_id" class="input" required>
                        <option value="">Pilih meja sumber</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table->id }}">{{ $table->code }} · {{ $table->status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Ke</label>
                    <select name="to_id" class="input" required>
                        <option value="">Pilih meja tujuan</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table->id }}">{{ $table->code }} · {{ $table->status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost flex-1" @click="transferOpen = false">Batal</button>
                    <button class="btn-primary flex-1" type="submit">Pindahkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="splitOpen" x-cloak>
        <div class="card w-full max-w-md p-6" @click.outside="splitOpen = false">
            <h3 class="text-lg font-semibold">Split meja</h3>
            <form method="POST" action="{{ route('tables.split') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="label">Sumber</label>
                    <select name="source_id" class="input" required x-model="splitSource" @change="loadSplitItems()">
                        <option value="">Pilih meja sumber</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table->id }}">{{ $table->code }} · {{ $table->status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Item yang dipindah</label>
                    <div class="max-h-40 space-y-2 overflow-y-auto rounded-xl border border-line p-3">
                        <template x-for="item in splitItems" :key="item.id">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="item_ids[]" :value="item.id">
                                <span x-text="`${item.quantity} × ${item.name}`"></span>
                            </label>
                        </template>
                        <p class="text-xs text-muted" x-show="!splitItems.length">Pilih meja sumber yang sedang terisi.</p>
                    </div>
                </div>
                <div>
                    <label class="label">Tujuan (meja kosong)</label>
                    <select name="target_id" class="input" required>
                        <option value="">Pilih meja tujuan</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table->id }}">{{ $table->code }} · {{ $table->status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost flex-1" @click="splitOpen = false">Batal</button>
                    <button class="btn-primary flex-1" type="submit">Pisahkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" x-show="mergeOpen" x-cloak>
        <div class="card w-full max-w-md p-6" @click.outside="mergeOpen = false">
            <h3 class="text-lg font-semibold">Merge meja</h3>
            <p class="mt-1 text-sm text-muted">Order meja sumber pindah ke meja target. Meja sumber jadi Available — kartunya tidak disatukan.</p>
            <form method="POST" action="{{ route('tables.merge') }}" class="mt-5 space-y-4">
                @csrf
                @php $mergeable = $tables->filter(fn ($table) => $table->orders->isNotEmpty()); @endphp
                <div>
                    <label class="label">Sumber (dikosongkan)</label>
                    <select name="source_id" class="input" required>
                        <option value="">Pilih meja sumber</option>
                        @forelse ($mergeable as $table)
                            <option value="{{ $table->id }}">{{ $table->code }} · {{ (int) $table->orders->first()?->items_count }} item</option>
                        @empty
                            <option value="" disabled>Tidak ada meja berorder</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="label">Target (terima order)</label>
                    <select name="target_id" class="input" required>
                        <option value="">Pilih meja target</option>
                        @forelse ($mergeable as $table)
                            <option value="{{ $table->id }}">{{ $table->code }} · {{ (int) $table->orders->first()?->items_count }} item</option>
                        @empty
                            <option value="" disabled>Tidak ada meja berorder</option>
                        @endforelse
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost flex-1" @click="mergeOpen = false">Batal</button>
                    <button class="btn-primary flex-1" type="submit">Gabungkan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.tableDrag = function (id, startX, startY) {
        return {
            x: startX,
            y: startY,
            dragging: false,
            startClientX: 0,
            startClientY: 0,
            originX: 0,
            originY: 0,
            start(event) {
                this.dragging = true;
                this.startClientX = event.clientX;
                this.startClientY = event.clientY;
                this.originX = this.x;
                this.originY = this.y;
            },
            move(event) {
                if (! this.dragging) return;
                this.x = Math.max(0, this.originX + (event.clientX - this.startClientX));
                this.y = Math.max(0, this.originY + (event.clientY - this.startClientY));
            },
            async end() {
                if (! this.dragging) return;
                this.dragging = false;
                await fetch(@json(url('/tables')) + '/' + id + '/move', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ pos_x: Math.round(this.x), pos_y: Math.round(this.y) }),
                });
            },
        };
    };

    async function pollTables() {
        try {
            const res = await fetch('{{ route('tables.live') }}', { headers: { 'Accept': 'application/json' } });
            if (! res.ok) return;
            const data = await res.json();
            (data.tables || []).forEach((table) => {
                const tile = document.querySelector(`[data-table-id="${table.id}"]`);
                if (! tile) return;
                tile.classList.remove('table-tile-available', 'table-tile-occupied', 'table-tile-reserved');
                tile.classList.add('table-tile-' + (table.status || 'available'));
                const badge = tile.querySelector('[data-table-status]');
                if (badge) {
                    badge.className = 'tables-tile-badge tables-tile-badge-' + (table.status || 'available');
                    badge.textContent = table.label;
                }
            });
            if (data.counts) {
                const available = document.getElementById('count-available');
                const occupied = document.getElementById('count-occupied');
                const reserved = document.getElementById('count-reserved');
                if (available) available.textContent = data.counts.available;
                if (occupied) occupied.textContent = data.counts.occupied;
                if (reserved) reserved.textContent = data.counts.reserved;
            }
        } catch (e) {}
        setTimeout(pollTables, 8000);
    }
    pollTables();
</script>
@endpush
