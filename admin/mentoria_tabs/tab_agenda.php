<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div class="header-title">
        <h2><i class="fas fa-calendar-alt"></i> Agenda de Classes</h2>
        <p>Defina os horários dos encontros semanais no Google Meet.</p>
    </div>
    <button class="btn btn-success" style="display: flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 12px; font-weight: 600;" onclick="openModal()">
        <i class="fas fa-plus"></i> Novo Horário
    </button>
</div>

<style>
    .table-container { background: var(--card-bg); padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-main); }
    th { background: rgba(0,0,0,0.1); color: var(--text-dim); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; }
    .status-active { color: #10b981; font-weight: 600; }
    .status-inactive { color: var(--text-dim); }
    
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .modal.active { display: flex; }
    .modal-content { background: var(--card-bg); padding: 30px; border-radius: 15px; width: 100%; max-width: 500px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
    
    /* Action Buttons Style (Padronizado) */
    .actions-cell { display: flex; gap: 8px; align-items: center; }
    .action-btn { width: 35px; height: 35px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); cursor: pointer; background: transparent; }
    .btn-edit-action { color: #38bdf8; }
    .btn-edit-action:hover { background: #38bdf8; color: white; border-color: #38bdf8; }
    .btn-toggle-on { color: #10b981; }
    .btn-toggle-on:hover { background: #10b981; color: white; border-color: #10b981; }
    .btn-toggle-off { color: #94a3b8; }
    .btn-toggle-off:hover { background: #94a3b8; color: white; border-color: #94a3b8; }
    .btn-delete-action { color: #ef4444; }
    .btn-delete-action:hover { background: #ef4444; color: white; border-color: #ef4444; }
</style>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Dia da Semana</th>
                <th>Horário (BRT)</th>
                <th>Link da Class</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($schedules)): ?>
            <tr><td colspan="5" style="text-align: center;">Nenhum horário cadastrado.</td></tr>
            <?php else: ?>
                <?php foreach ($schedules as $s): 
                    $meet_code = str_replace(['https://meet.google.com/', 'http://meet.google.com/'], '', $s['meet_link']);
                    $meet_code = trim($meet_code, '/');
                ?>
                <tr>
                    <td><strong><?php echo $days[$s['day_of_week']]; ?></strong></td>
                    <td><?php echo date('H:i', strtotime($s['start_time'])); ?></td>
                    <td><span style="color: var(--text-dim);">🔗</span> <a href="<?php echo htmlspecialchars($s['meet_link']); ?>" target="_blank" style="color: var(--accent-blue); font-family: monospace; font-size: 1.15rem; letter-spacing: 1px;"><?php echo htmlspecialchars($meet_code); ?></a></td>
                    <td>
                        <?php if ($s['is_active']): ?>
                            <span class="status-active"><i class="fas fa-check-circle"></i> Ativo</span>
                        <?php else: ?>
                            <span class="status-inactive"><i class="fas fa-times-circle"></i> Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <button type="button" class="action-btn btn-edit-action" title="Editar" onclick='editSchedule(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8"); ?>)'><i class="fas fa-edit"></i></button>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action_schedule" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                            <input type="hidden" name="status" value="<?php echo $s['is_active'] ? 0 : 1; ?>">
                            <input type="hidden" name="tab" value="agenda">
                            <button type="submit" class="action-btn <?php echo $s['is_active'] ? 'btn-toggle-off' : 'btn-toggle-on'; ?>" title="<?php echo $s['is_active'] ? 'Desativar' : 'Ativar'; ?>">
                                <i class="fas <?php echo $s['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este horário?');">
                            <input type="hidden" name="action_schedule" value="delete">
                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                            <input type="hidden" name="tab" value="agenda">
                            <button type="submit" class="action-btn btn-delete-action" title="Excluir"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Form -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle">Adicionar Horário</h2>
        <form method="POST">
            <input type="hidden" name="action_schedule" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <input type="hidden" name="tab" value="agenda">
            
            <div class="form-group">
                <label>Dia da Semana</label>
                <select name="day_of_week" id="day_of_week" required class="input-modern" style="width:100%; padding: 10px; margin-top:5px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:var(--white);">
                    <?php foreach ($days as $num => $nome): ?>
                        <option value="<?php echo $num; ?>"><?php echo $nome; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label>Horário de Início (BRT)</label>
                <input type="time" name="start_time" id="start_time" required class="input-modern" style="width:100%; padding: 10px; margin-top:5px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:var(--white);">
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label>Link do Google Meet</label>
                <input type="text" name="meet_link" id="meet_link" placeholder="https://meet.google.com/xyz-abcd-123" required class="input-modern" style="width:100%; padding: 10px; margin-top:5px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:var(--white);">
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="flex: 1; padding: 12px; font-weight:bold;">Salvar</button>
                <button type="button" class="btn btn-danger" style="flex: 1; padding: 12px; font-weight:bold; background:var(--danger); border:none;" onclick="closeModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalTitle').textContent = 'Adicionar Horário';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('day_of_week').value = '1';
        document.getElementById('start_time').value = '';
        document.getElementById('meet_link').value = '';
        document.getElementById('scheduleModal').classList.add('active');
    }
    
    function editSchedule(schedule) {
        document.getElementById('modalTitle').textContent = 'Editar Horário';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = schedule.id;
        document.getElementById('day_of_week').value = schedule.day_of_week;
        document.getElementById('start_time').value = schedule.start_time.substring(0, 5);
        document.getElementById('meet_link').value = schedule.meet_link;
        document.getElementById('scheduleModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('scheduleModal').classList.remove('active');
    }
</script>
