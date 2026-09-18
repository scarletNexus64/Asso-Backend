{{-- Graphique d'évolution. Attend $series, $granularity, $chartId. --}}
<div class="bg-dark-100 border border-dark-200 rounded-xl p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">Évolution {{ $granularity === 'month' ? 'mensuelle' : 'journalière' }}</h3>
    </div>
    <div class="h-72"><canvas id="{{ $chartId }}"></canvas></div>
</div>
<script>
(function () {
    const series = @json($series);
    const monthly = @json($granularity === 'month');
    const labels = series.map(p => {
        const [y, m, d] = p.date.split('-');
        return monthly ? `${m}/${y}` : `${d}/${m}`;
    });
    const draw = () => new Chart(document.getElementById(@json($chartId)), {
        data: {
            labels,
            datasets: [
                { type: 'line', label: 'Visites', data: series.map(p => p.visits), borderColor: 'rgba(59,130,246,1)', backgroundColor: 'rgba(59,130,246,.1)', tension: .35, fill: true, yAxisID: 'y' },
                { type: 'line', label: 'Produits consultés', data: series.map(p => p.product_views), borderColor: 'rgba(168,85,247,1)', backgroundColor: 'rgba(168,85,247,.05)', tension: .35, yAxisID: 'y' },
                { type: 'line', label: 'Commandes', data: series.map(p => p.orders), borderColor: 'rgba(245,158,11,1)', tension: .35, yAxisID: 'y' },
                { type: 'bar', label: 'CA vendeur (FCFA)', data: series.map(p => p.revenue), backgroundColor: 'rgba(16,185,129,.35)', yAxisID: 'y1' },
            ],
        },
        options: {
            animation: { duration: 0 },
            maintainAspectRatio: false,
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: '#e5e7eb' } } },
            scales: {
                x: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(75,85,99,.2)' } },
                y: { beginAtZero: true, ticks: { color: '#9ca3af', precision: 0 }, grid: { color: 'rgba(75,85,99,.2)' } },
                y1: { beginAtZero: true, position: 'right', ticks: { color: '#6ee7b7' }, grid: { drawOnChartArea: false } },
            },
        },
    });
    if (window.Chart) { draw(); } else { window.addEventListener('load', draw); }
})();
</script>
