<div class="card bg-gray-900 border-gray-700 shadow-xl mb-6">
    <div class="card-header border-b border-gray-700 pb-4">
        <h3 class="text-xl font-bold text-gray-100 flex items-center">
            <span class="mr-2">✏️</span> Live Score Editor (Today)
        </h3>
        <p class="text-gray-400 text-sm mt-1">
            Edit today's points before midnight. Changes are saved automatically.
        </p>
    </div>
    <div class="card-body mt-4">
        <div id="scoreEditorContainer" class="overflow-x-auto">
            <p class="text-gray-400">Carregando painel de edição...</p>
        </div>
    </div>
</div>

<script>
function loadScoreEditor() {
    fetch('mentoria_score_edit_api.php?action=load')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('scoreEditorContainer').innerHTML = `<p class="text-red-400">${data.error || 'Erro ao carregar.'}</p>`;
                return;
            }
            renderScoreEditor(data.students, data.today);
        })
        .catch(err => {
            document.getElementById('scoreEditorContainer').innerHTML = `<p class="text-red-400">Erro de conexão: ${err.message}</p>`;
        });
}

function renderScoreEditor(students, todayDate) {
    if (students.length === 0) {
        document.getElementById('scoreEditorContainer').innerHTML = '<p class="text-gray-400">Nenhuma atividade registrada hoje ainda.</p>';
        return;
    }

    let html = `
    <table class="w-full text-left border-collapse text-sm text-gray-300">
        <thead>
            <tr class="border-b border-gray-700">
                <th class="py-2 px-3">Student</th>
                <th class="py-2 px-3 text-center">🖥️ Class<br><small class="text-gray-500">(20 pts)</small></th>
                <th class="py-2 px-3 text-center">🎤 Pronun<br><small class="text-gray-500">(5 pts)</small></th>
                <th class="py-2 px-3 text-center">📸 Desafio<br><small class="text-gray-500">(5 pts)</small></th>
                <th class="py-2 px-3 text-center">🎵 Music<br><small class="text-gray-500">(4 pts)</small></th>
                <th class="py-2 px-3 text-center">🎮 Games<br><small class="text-gray-500">(2 pts)</small></th>
                <th class="py-2 px-3 text-center">📖 Vocab<br><small class="text-gray-500">(1 pt)</small></th>
            </tr>
        </thead>
        <tbody>`;

    students.forEach(s => {
        const jid = s.jid;
        html += `
            <tr class="border-b border-gray-800 hover:bg-gray-800 transition">
                <td class="py-3 px-3 font-semibold text-gray-200">
                    ${s.name}
                </td>
                <td class="py-3 px-3 text-center">
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input type="checkbox" class="sr-only peer" ${s.class_attended ? 'checked' : ''} 
                             onchange="toggleAttendance('${jid}', this.checked)">
                      <div class="w-9 h-5 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                    </label>
                </td>
                <td class="py-3 px-3 text-center">
                    <input type="number" min="0" class="w-12 bg-gray-900 border border-gray-700 text-center rounded p-1 text-white" 
                           value="${s.audios}" onchange="editActivity('${jid}', 'audios', this.value)">
                </td>
                <td class="py-3 px-3 text-center">
                    <input type="number" min="0" class="w-12 bg-gray-900 border border-gray-700 text-center rounded p-1 text-white" 
                           value="${s.desafio}" onchange="editActivity('${jid}', 'desafio', this.value)">
                </td>
                <td class="py-3 px-3 text-center">
                    <input type="number" min="0" class="w-12 bg-gray-900 border border-gray-700 text-center rounded p-1 text-white" 
                           value="${s.music}" onchange="editActivity('${jid}', 'music', this.value)">
                </td>
                <td class="py-3 px-3 text-center">
                    <input type="number" min="0" class="w-12 bg-gray-900 border border-gray-700 text-center rounded p-1 text-white" 
                           value="${s.games}" onchange="editActivity('${jid}', 'games', this.value)">
                </td>
                <td class="py-3 px-3 text-center">
                    <input type="number" min="0" class="w-12 bg-gray-900 border border-gray-700 text-center rounded p-1 text-white" 
                           value="${s.vocab}" onchange="editActivity('${jid}', 'vocab', this.value)">
                </td>
            </tr>`;
    });

    html += `</tbody></table>`;
    document.getElementById('scoreEditorContainer').innerHTML = html;
}

function toggleAttendance(memberJid, isAttending) {
    fetch('mentoria_score_edit_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle_attendance', member_jid: memberJid, attended: isAttending })
    })
    .then(res => res.json())
    .then(data => {
        if(!data.success) alert('Erro: ' + data.error);
    });
}

function editActivity(memberJid, type, value) {
    fetch('mentoria_score_edit_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'edit_activity', member_jid: memberJid, type: type, value: parseInt(value) || 0 })
    })
    .then(res => res.json())
    .then(data => {
        if(!data.success) alert('Erro: ' + data.error);
    });
}

// Load on init
document.addEventListener('DOMContentLoaded', loadScoreEditor);
</script>
