@extends('admin.layouts.app')
@section('title', 'Database Manager')
@section('content')
<div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-100"><i class="fas fa-database mr-2"></i>Database Manager</h1>
            <p class="text-gray-400 mt-1">Interrogez et explorez votre base de données</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg p-6 text-white">
            <p class="text-blue-100 text-sm">Base de données</p>
            <p class="text-2xl font-bold mt-2">{{ $stats['database_name'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-600 to-purple-700 rounded-lg p-6 text-white">
            <p class="text-purple-100 text-sm">Tables</p>
            <p class="text-3xl font-bold mt-2">{{ $stats['total_tables'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-lg p-6 text-white">
            <p class="text-green-100 text-sm">Taille</p>
            <p class="text-3xl font-bold mt-2">{{ $stats['database_size'] }} MB</p>
        </div>
    </div>
    <div class="bg-dark-100 border border-dark-200 rounded-lg p-6">
        <h2 class="text-xl font-bold text-white mb-4"><i class="fas fa-terminal mr-2 text-primary-500"></i>Éditeur SQL</h2>
        <form id="queryForm">
            @csrf
            <textarea id="queryEditor" name="query_text" rows="8" class="w-full px-4 py-3 bg-dark-50 border border-dark-300 rounded-lg text-white font-mono text-sm" placeholder="SELECT * FROM users LIMIT 10;"></textarea>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg"><i class="fas fa-play mr-2"></i>Exécuter</button>
                <button type="button" id="exportBtn" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg" disabled><i class="fas fa-file-csv mr-2"></i>Exporter CSV</button>
            </div>
        </form>
        <div id="queryResults" class="mt-6 hidden">
            <h3 class="text-lg font-semibold text-white mb-4">Résultats <span id="resultCount"></span> <span id="executionTime"></span></h3>
            <div class="overflow-x-auto"><table id="resultsTable" class="w-full text-sm"><thead id="resultsHeader" class="bg-dark-50 text-gray-300"></thead><tbody id="resultsBody" class="text-gray-200"></tbody></table></div>
        </div>
        <div id="queryError" class="mt-6 hidden"><div class="bg-red-500/10 border border-red-500/50 rounded-lg p-4"><p id="errorMessage" class="text-red-300"></p></div></div>
    </div>
    <div class="bg-dark-100 border border-dark-200 rounded-lg overflow-hidden">
        <div class="bg-dark-50 px-6 py-4"><h2 class="text-xl font-bold text-white">Tables</h2></div>
        <table class="w-full"><thead class="bg-dark-50"><tr><th class="px-6 py-3 text-left text-gray-300">Table</th><th class="px-6 py-3 text-left text-gray-300">Lignes</th><th class="px-6 py-3 text-left text-gray-300">Actions</th></tr></thead><tbody>
            @foreach($tables as $table)
            <tr class="hover:bg-dark-50 border-b border-dark-200"><td class="px-6 py-4 text-white">{{ $table['name'] }}</td><td class="px-6 py-4 text-gray-300">{{ number_format($table['rows']) }}</td><td class="px-6 py-4"><button onclick="quickQuery('{{ $table['name'] }}')" class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-sm rounded">Interroger</button></td></tr>
            @endforeach
        </tbody></table>
    </div>
</div>
<script>
let lastQuery = '';
document.getElementById('queryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const query = document.getElementById('queryEditor').value.trim();
    if (!query) return;
    lastQuery = query;
    document.getElementById('queryResults').classList.add('hidden');
    document.getElementById('queryError').classList.add('hidden');
    try {
        const response = await fetch('{{ route('admin.database.query') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ query_text: query })
        });
        const data = await response.json();
        if (data.success) {
            displayResults(data.results, data.count, data.execution_time);
            document.getElementById('exportBtn').disabled = false;
        } else {
            document.getElementById('errorMessage').textContent = data.message;
            document.getElementById('queryError').classList.remove('hidden');
        }
    } catch (error) {
        document.getElementById('errorMessage').textContent = 'Erreur réseau';
        document.getElementById('queryError').classList.remove('hidden');
    }
});
function displayResults(results, count, time) {
    document.getElementById('resultCount').textContent = `(${count} lignes)`;
    document.getElementById('executionTime').textContent = `- ${time}ms`;
    if (results.length === 0) {
        document.getElementById('resultsHeader').innerHTML = '';
        document.getElementById('resultsBody').innerHTML = '<tr><td class="px-6 py-8 text-center text-gray-400">Aucun résultat</td></tr>';
        document.getElementById('queryResults').classList.remove('hidden');
        return;
    }
    const columns = Object.keys(results[0]);
    document.getElementById('resultsHeader').innerHTML = '<tr>' + columns.map(col => '<th class="px-4 py-3 text-left">' + col + '</th>').join('') + '</tr>';
    document.getElementById('resultsBody').innerHTML = results.map(row => '<tr class="hover:bg-dark-50">' + columns.map(col => '<td class="px-4 py-3">' + (row[col] !== null ? row[col] : '<span class="text-gray-500">NULL</span>') + '</td>').join('') + '</tr>').join('');
    document.getElementById('queryResults').classList.remove('hidden');
}
function quickQuery(tableName) {
    document.getElementById('queryEditor').value = 'SELECT * FROM ' + tableName + ' LIMIT 100;';
}
document.getElementById('exportBtn').addEventListener('click', () => {
    if (!lastQuery) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('admin.database.export') }}';
    form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="query" value="' + lastQuery + '">';
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
});
</script>
@endsection
