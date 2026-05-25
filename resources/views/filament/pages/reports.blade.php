<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php($stats = $this->getStats())
        @php($chart = $this->getChartData())

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Total Revenue', 'value' => rupiah($stats['revenue']), 'color' => 'text-emerald-500'],
                ['label' => 'Jumlah Transaksi', 'value' => number_format($stats['transactions'], 0, ',', '.'), 'color' => 'text-sky-500'],
                ['label' => 'Rata-rata Transaksi', 'value' => rupiah($stats['average']), 'color' => 'text-amber-500'],
                ['label' => 'Total Item Terjual', 'value' => number_format($stats['itemsSold'], 0, ',', '.'), 'color' => 'text-violet-500'],
            ] as $card)
                <div class="rounded-xl bg-white p-5 shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $card['color'] }} tabular-nums">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div
            x-data="{
                init() {
                    const ctx = this.$refs.canvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: @js($chart['labels']),
                            datasets: [{
                                label: 'Revenue',
                                data: @js($chart['data']),
                                fill: 'start',
                                backgroundColor: 'rgba(217,119,6,0.15)',
                                borderColor: 'rgb(217,119,6)',
                                tension: 0.3,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true } },
                            plugins: { legend: { display: false } },
                        },
                    });
                },
            }"
            class="rounded-xl bg-white p-5 shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Revenue per Hari</h2>
            <div class="mt-3" style="position: relative; height: 280px;">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        {{ $this->table }}
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
