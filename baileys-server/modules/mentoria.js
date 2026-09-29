/**
 * Módulo da Mentoria (exclusivo para alunos pagantes).
 * Chamado por bot.js somente quando isMentoriaGroup === true.
 *
 * EXTENSÃO MULTI-IDIOMA:
 * Para adicionar um novo idioma (ex: 'fr'):
 *   1. Adicione uma entrada em STRINGS_I18N abaixo.
 *   2. Adicione aliases em COMMAND_ALIASES (se quiser comandos no idioma).
 *   3. Crie mentoria_config_fr.json na VPS. Bot.js detecta automaticamente.
 */

// ─── I18N: STRINGS LOCALIZADAS ──────────────────────────────────────────────────────
// Adicionar novo idioma = nova entrada neste objeto. Os valores de 'en' são
// usados como fallback automático para qualquer lang não mapeado.
const STRINGS_I18N = {
    en: {
        // buildSessionsBlock
        practice:          'Students Practice',
        practiceSubtitle:  'Students only — no teacher',
        teacherClass:      'Teacher Class',
        noOne:             'No one yet.',
        quorumNeed2:       '⚠️ 2 students needed — be the first!',
        quorumNeed1:       '⚠️ 1 more student needed to confirm this session.',
        quorumOk:          '✅ Quorum reached! Session is confirmed.',
        // !attend / !unattend — desambiguação de sessões
        multipleSessions:  '❓ We have *multiple sessions today!*\n\nPlease specify which one:\n\n{options}\nWhich one are you {verb}?',
        verbJoining:       'joining',
        verbLeaving:       'leaving',
        labelPractice:     '🗣️ *!attend {N}* / *!attend {N}* — Students Practice',
        labelTeacher:      '👨‍🏫 *!attend {N}* — Teacher Class',
        labelPracticeUn:   '🗣️ *!unattend {N}* — Students Practice',
        labelTeacherUn:    '👨‍🏫 *!unattend {N}* — Teacher Class',
        // !attend — respostas
        attendOk:          '✅ Attendance confirmed for @{name}!\n\n📅 *Today\'s Schedule — {date}*\n{sessionsBlock}',
        attendLateGood:    '⏰ The deadline has passed, @{name}.\n\n✅ *Good news:* The class is confirmed and will happen anyway!\n{sessionsBlock}',
        attendLateBad:     '⏰ The deadline has passed, @{name}.\n\n❌ *Bad news:* The session was already cancelled due to lack of attendees.',
        attendErrServer:   '⚠️ Error reaching the server to confirm: {err}',
        attendErrGeneric:  '❌ {msg}',
        // !unattend — respostas
        unattendOk:        '❎ Attendance cancelled for @{name}.\n\n📅 *Today\'s Schedule — {date}*\n{sessionsBlock}',
        sessionCancelled:  '🚨 *SESSION CANCELLED*\n\nSince there are no more students confirmed, today\'s session is now cancelled.',
        unattendErrGeneric: '❌ {msg}',
        // !list
        listFallback:      '📋 *Today\'s Schedule — {date}*\n{attendees}',
        listErrGeneric:    '❌ {msg}',
        // !streaks
        streakLeaderboard: '🏆 *All-Time Streak Records*\n\n{allTimeList}\n🔥 *Active Streaks Today*\n\n{activeList}',
        streakNoRecords:   'No records yet.\n',
        streakNoActive:    'No active streaks right now.\n',
        streakDays:        'days',
        // admin award (!N)
        award1:  '👌 Way to go! Good effort. (+{pts} pt{s})',
        award5:  '🎉 Great job! Keep it going! (+{pts} pts)',
        award10: '🔥 Awesome work! You\'re on fire! (+{pts} pts)',
        award15: '⚡ Outstanding! Pure energy! (+{pts} pts)',
        award20: '🚀 Stellar! Taking it to the next level! (+{pts} pts)',
        // milestone streak
        milestone: '🎉 *MILESTONE REACHED!* 🏆\nCongratulations {name}! You just hit a *{streak}-day streak*! 🔥\n\n📊 *Your Challenge Stats:*\n• Current Streak: {streak} days\n• Personal Record: {longest_streak} days\n• Total Days Completed: {total_completions} days\n\nKeep building the habit! 🚀',
    },
    es: {
        practice:          'Práctica de Estudiantes',
        practiceSubtitle:  'Solo estudiantes — sin profesor',
        teacherClass:      'Clase con Profesor',
        noOne:             'Nadie todavía.',
        quorumNeed2:       '⚠️ Se necesitan 2 estudiantes — ¡sé el primero!',
        quorumNeed1:       '⚠️ Se necesita 1 estudiante más para confirmar la sesión.',
        quorumOk:          '✅ ¡Quórum alcanzado! La sesión está confirmada.',
        multipleSessions:  '❓ ¡Tenemos *varias sesiones hoy!*\n\nEspecifica a cuál deseas ir:\n\n{options}\n¿A cuál {verb}?',
        verbJoining:       'te unes',
        verbLeaving:       'te retiras',
        labelPractice:     '🗣️ *!attend {N}* / *!confirmar {N}* — Práctica de Estudiantes',
        labelTeacher:      '👨‍🏫 *!attend {N}* / *!confirmar {N}* — Clase con Profesor',
        labelPracticeUn:   '🗣️ *!unattend {N}* / *!cancelar {N}* — Práctica de Estudiantes',
        labelTeacherUn:    '👨‍🏫 *!unattend {N}* / *!cancelar {N}* — Clase con Profesor',
        attendOk:          '✅ ¡Asistencia confirmada para @{name}!\n\n📅 *Agenda de Hoy — {date}*\n{sessionsBlock}',
        attendLateGood:    '⏰ El plazo ha finalizado, @{name}.\n\n✅ *Buenas noticias:* ¡La clase está confirmada y se realizará de todas formas!\n{sessionsBlock}',
        attendLateBad:     '⏰ El plazo ha finalizado, @{name}.\n\n❌ *Aviso:* La sesión fue cancelada por falta de quórum.',
        attendErrServer:   '⚠️ Error al contactar el servidor: {err}',
        attendErrGeneric:  '❌ {msg}',
        unattendOk:        '❎ Asistencia cancelada para @{name}.\n\n📅 *Agenda de Hoy — {date}*\n{sessionsBlock}',
        sessionCancelled:  '🚨 *SESIÓN CANCELADA*\n\nYa no hay estudiantes confirmados, la sesión de hoy ha sido cancelada.',
        unattendErrGeneric: '❌ {msg}',
        listFallback:      '📋 *Agenda de Hoy — {date}*\n{attendees}',
        listErrGeneric:    '❌ {msg}',
        streakLeaderboard: '🏆 *Récords de Racha Histórica*\n\n{allTimeList}\n🔥 *Rachas Activas Hoy*\n\n{activeList}',
        streakNoRecords:   'Aún no hay registros.\n',
        streakNoActive:    'Sin rachas activas ahora.\n',
        streakDays:        'días',
        award1:  '👌 ¡Muy bien! Buen esfuerzo. (+{pts} pt{s})',
        award5:  '🎉 ¡Excelente trabajo! ¡Sigue así! (+{pts} pts)',
        award10: '🔥 ¡Increíble trabajo! ¡Estás en racha! (+{pts} pts)',
        award15: '⚡ ¡Impresionante! ¡Pura energía! (+{pts} pts)',
        award20: '🚀 ¡Extraordinario! ¡Alcanzando el siguiente nivel! (+{pts} pts)',
        milestone: '🎉 *¡META ALCANZADA!* 🏆\n¡Felicidades {name}! ¡Alcanzaste una racha de *{streak} días*! 🔥\n\n📊 *Tus Estadísticas del Desafío:*\n• Racha actual: {streak} días\n• Récord personal: {longest_streak} días\n• Total de días completados: {total_completions} días\n\n¡Sigue construyendo el hábito! 🚀',
    },
    // Para adicionar francês no futuro:
    // fr: { practice: 'Pratique étudiants', teacherClass: 'Cours avec professeur', ... }
};

// Helper: retorna as strings do lang solicitado com fallback automático para 'en'
function t(lang, key) {
    return (STRINGS_I18N[lang] && STRINGS_I18N[lang][key] !== undefined)
        ? STRINGS_I18N[lang][key]
        : STRINGS_I18N['en'][key];
}

// ─── ALIASES DE COMANDO POR IDIOMA ───────────────────────────────────────────
// Alias -> comando canônico (em inglês). Os alunos podem digitar qualquer um;
// o comando canônico é o que o código executa. É uma forma de imersão no idioma.
// Adicionar novo idioma = nova entrada neste objeto.
const COMMAND_ALIASES = {
    es: {
        '!confirmar': '!attend',
        '!cancelar':  '!unattend',
        '!lista':     '!list',
        '!rachas':    '!streaks',
    },
    // fr: { '!assister': '!attend', '!annuler': '!unattend', '!liste': '!list', '!s\u00e9ries': '!streaks' },
};

// Normaliza o texto para o comando canônico, preservando possíveis números após o alias
function normalizeCommand(rawText, lang) {
    const aliases = COMMAND_ALIASES[lang] || {};
    for (const [alias, canonical] of Object.entries(aliases)) {
        if (rawText.toLowerCase().startsWith(alias)) {
            return canonical + rawText.slice(alias.length);
        }
    }
    return rawText;
}

// ─── HELPERS INTERNOS ──────────────────────────────────────────────────────

function formatSessionTime(startTime) {
    let tParts = startTime.split(':');
    let h = parseInt(tParts[0]);
    let ampm = 'AM';
    if (h >= 12) { ampm = 'PM'; if (h > 12) h -= 12; }
    if (h === 0) h = 12;
    let mStr = tParts[1] === '00' ? '' : ':' + tParts[1];
    return `${h}${mStr} ${ampm}`;
}

/**
 * Constrói o bloco de sessões do dia com strings localizadas.
 * @param {Array}  dailySummary - dados retornados pela API
 * @param {string} lang         - código do idioma ('en', 'es', ...)
 */
function buildSessionsBlock(dailySummary, lang = 'en') {
    if (!dailySummary || dailySummary.length === 0) return '';
    let block = '';
    dailySummary.forEach(summary => {
        let isPractice = summary.session_type === 'student_practice';
        let tStr = formatSessionTime(summary.start_time);

        if (isPractice) {
            block += `\n\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501`;
            block += `\n\ud83d\udde3\ufe0f *${t(lang, 'practice')} \u2014 ${tStr}*`;
            block += `\n_${t(lang, 'practiceSubtitle')}_`;
        } else {
            block += `\n\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501`;
            block += `\n\ud83d\udc68\u200d\ud83c\udfeb *${t(lang, 'teacherClass')} \u2014 ${tStr}*`;
        }
        block += `\n`;

        let count = summary.attendees ? summary.attendees.length : 0;
        if (count > 0) {
            summary.attendees.forEach((name, i) => block += `  ${i + 1}. ${name}\n`);
        } else {
            block += `  _${t(lang, 'noOne')}_\n`;
        }

        if (isPractice) {
            if (count === 0)      block += `  _${t(lang, 'quorumNeed2')}_\n`;
            else if (count === 1) block += `  _${t(lang, 'quorumNeed1')}_\n`;
            else                  block += `  _${t(lang, 'quorumOk')}_\n`;
        }
    });
    block += `\n\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501`;
    return block;
}

// ─── HANDLER DE MENSAGENS ────────────────────────────────────────────────────

/**
 * Trata mensagens em grupos da Mentoria.
 *
 * @param {object} ctx
 * @param {object} ctx.sock
 * @param {object} ctx.msg
 * @param {string} ctx.groupJid
 * @param {string} ctx.senderJid
 * @param {string} ctx.senderName
 * @param {string} ctx.text          - texto limpo da mensagem
 * @param {object} ctx.realMsg       - mensagem desembrulhada
 * @param {boolean} ctx.isVisual
 * @param {boolean} ctx.isAdmin
 * @param {boolean} ctx.isGroupAdmin
 * @param {boolean} ctx.isGlobalAdmin
 * @param {string} ctx.msgId
 * @param {object} ctx.config        - mentoria config já carregado
 * @param {string} ctx.lang          - código do idioma resolvido pelo bot.js ('en', 'es', ...)
 */
async function handleMessage(ctx) {
    const { sock, msg, groupJid, senderJid, senderName, realMsg, isVisual, isAdmin, isGroupAdmin, isGlobalAdmin, msgId, config } = ctx;
    const lang = ctx.lang || 'en';

    // Normaliza aliases do idioma para os comandos canônicos em inglês
    // Ex.: '!confirmar 1' (ES) → '!attend 1'; '!rachas' (ES) → '!streaks'
    const text = normalizeCommand(ctx.text, lang);

    // === ADMIN SCORING COMMANDS (!number) ===
    if (isAdmin && msg.message?.extendedTextMessage?.contextInfo?.quotedMessage) {
        const cmdMatch = text.match(/^\s*!\s*(\d+)\s*$/);
        if (cmdMatch) {
            console.log(`[SCORING] Admin ${senderName} issued command ${ctx.text}`);
            const points = parseInt(cmdMatch[1], 10);
            if (points <= 0) return;

            const quotedParticipant = msg.message.extendedTextMessage.contextInfo.participant;
            const botJid = sock.user.id.split(':')[0] + '@s.whatsapp.net';

            if (quotedParticipant && quotedParticipant !== botJid && quotedParticipant !== senderJid) {
                try {
                    let groupKey = 'unknown';
                    for (const [key, gData] of Object.entries(config.groups || {})) {
                        if (gData.jid === groupJid) { groupKey = key; break; }
                    }

                    const res = await fetch('https://dev.viaEi.com/bot_whatsapp/mentoria_award_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ group_jid: groupJid, group_key: groupKey, member_jid: quotedParticipant, points })
                    });
                    const data = await res.json();

                    if (data.success) {
                        let reactEmoji = '🙌';
                        let s = points !== 1 ? 's' : '';
                        let replyMsg = t(lang, 'award1').replace('{pts}', points).replace('{s}', s);

                        if (points >= 20)      { reactEmoji = '🚀'; replyMsg = t(lang, 'award20').replace('{pts}', points); }
                        else if (points >= 15) { reactEmoji = '⚡'; replyMsg = t(lang, 'award15').replace('{pts}', points); }
                        else if (points >= 10) { reactEmoji = '🔥'; replyMsg = t(lang, 'award10').replace('{pts}', points); }
                        else if (points >= 5)  { reactEmoji = '🎉'; replyMsg = t(lang, 'award5').replace('{pts}', points); }

                        await sock.sendMessage(groupJid, { react: { text: reactEmoji, key: { remoteJid: groupJid, fromMe: false, id: msg.message.extendedTextMessage.contextInfo.stanzaId, participant: quotedParticipant } } });
                        await sock.sendMessage(groupJid, { text: replyMsg, mentions: [quotedParticipant] });
                    }
                } catch (err) {
                    console.error('Error awarding points:', err);
                }
            }
        }
    }

    // === STREAK SYSTEM (Desafio Group) ===
    if (isVisual && !isAdmin) {
        const desafioGroup = config.groups?.desafio?.jid;
        if (groupJid === desafioGroup) {
            try {
                const res = await fetch('https://dev.viaEi.com/bot_whatsapp/mentoria_desafio_streak_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ member_jid: senderJid, member_name: senderName })
                });
                const data = await res.json();

                if (data.success && !data.already_computed) {
                    const nameToUse = (senderName && senderName !== 'Desconhecido') ? senderName.split(' ')[0] : senderJid.split('@')[0];
                    await sock.sendMessage(groupJid, { react: { text: '🎉', key: msg.key } });

                    if (data.is_milestone) {
                        let milestoneMsg = t(lang, 'milestone')
                            .replace('{name}', nameToUse)
                            .replace(/{streak}/g, data.streak)
                            .replace('{longest_streak}', data.longest_streak)
                            .replace('{total_completions}', data.total_completions);
                        setTimeout(async () => { await sock.sendMessage(groupJid, { text: milestoneMsg }); }, 2000);
                    }
                }
            } catch (err) {
                console.error('Error calling streak API:', err);
            }
        }
    }

    // === INTERACTIVE COMMANDS ===
    // Obs.: 'text' já foi normalizado acima (ex.: !confirmar → !attend)

    if (text.startsWith('!attend')) {
        const parts = text.split(' ');
        let schedulePosition = null;
        if (parts.length > 1) schedulePosition = parseInt(parts[1]);

        const ourClassesGroup = config.groups?.our_classes?.jid;
        if (groupJid === ourClassesGroup) {
            try {
                const reqBody = { action: 'attend', group_jid: groupJid, member_jid: senderJid, member_name: senderName };
                if (schedulePosition && !isNaN(schedulePosition)) reqBody.schedule_position = schedulePosition;

                const res = await fetch('https://dev.viaEi.com/bot_whatsapp/class_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(reqBody)
                });
                const data = await res.json();

                if (!data.success && data.reason === 'multiple_sessions_require_id') {
                    let optionsTxt = '';
                    if (data.schedules && data.schedules.length > 0) {
                        data.schedules.forEach((s, idx) => {
                            let lbl = s.session_type === 'student_practice'
                                ? t(lang, 'labelPractice').replace(/{N}/g, idx + 1)
                                : t(lang, 'labelTeacher').replace(/{N}/g, idx + 1);
                            optionsTxt += lbl + '\n';
                        });
                    } else {
                        optionsTxt  = t(lang, 'labelTeacher').replace(/{N}/g, 1) + '\n';
                        optionsTxt += t(lang, 'labelPractice').replace(/{N}/g, 2) + '\n';
                    }
                    const prompt = t(lang, 'multipleSessions')
                        .replace('{options}', optionsTxt)
                        .replace('{verb}', t(lang, 'verbJoining'));
                    await sock.sendMessage(groupJid, { text: prompt, mentions: [senderJid] });
                    return;
                }

                let dStr = data.class_date_en || data.class_date;
                let sessionsBlock = buildSessionsBlock(data.daily_summary, lang);

                if (data.success) {
                    await sock.sendMessage(groupJid, { react: { text: '✅', key: msg.key } });
                    let tpl = config.templates?.daily_summary_header || t(lang, 'attendOk');
                    let msgTxt = tpl
                        .replace('{name}', senderJid.split('@')[0])
                        .replace('{date}', dStr)
                        .replace('{sessionsBlock}', sessionsBlock)
                        .replace('{listText}', sessionsBlock);
                    await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });
                } else if (data.reason === 'deadline_passed') {
                    let msgTxt = data.class_confirmed
                        ? (config.templates?.attend_late_good || t(lang, 'attendLateGood'))
                        : (config.templates?.attend_late_bad  || t(lang, 'attendLateBad'));
                    msgTxt = msgTxt
                        .replace('{name}', senderJid.split('@')[0])
                        .replace('{sessionsBlock}', sessionsBlock)
                        .replace('{listText}', sessionsBlock);
                    await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });
                } else {
                    await sock.sendMessage(groupJid, { text: t(lang, 'attendErrGeneric').replace('{msg}', data.message || '?') });
                }
            } catch (err) {
                await sock.sendMessage(groupJid, { text: t(lang, 'attendErrServer').replace('{err}', err.message) });
            }
        }

    } else if (text.startsWith('!unattend')) {
        const parts = text.split(' ');
        let schedulePosition = null;
        if (parts.length > 1) schedulePosition = parseInt(parts[1]);

        const ourClassesGroup = config.groups?.our_classes?.jid;
        if (groupJid === ourClassesGroup) {
            try {
                const reqBody = { action: 'unattend', group_jid: groupJid, member_jid: senderJid };
                if (schedulePosition && !isNaN(schedulePosition)) reqBody.schedule_position = schedulePosition;

                const res = await fetch('https://dev.viaEi.com/bot_whatsapp/class_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(reqBody)
                });
                const data = await res.json();

                if (!data.success && data.reason === 'multiple_sessions_require_id') {
                    let optionsTxt = '';
                    if (data.schedules && data.schedules.length > 0) {
                        data.schedules.forEach((s, idx) => {
                            let lbl = s.session_type === 'student_practice'
                                ? t(lang, 'labelPracticeUn').replace(/{N}/g, idx + 1)
                                : t(lang, 'labelTeacherUn').replace(/{N}/g, idx + 1);
                            optionsTxt += lbl + '\n';
                        });
                    } else {
                        optionsTxt  = t(lang, 'labelTeacherUn').replace(/{N}/g, 1) + '\n';
                        optionsTxt += t(lang, 'labelPracticeUn').replace(/{N}/g, 2) + '\n';
                    }
                    const prompt = t(lang, 'multipleSessions')
                        .replace('{options}', optionsTxt)
                        .replace('{verb}', t(lang, 'verbLeaving'));
                    await sock.sendMessage(groupJid, { text: prompt, mentions: [senderJid] });
                    return;
                }

                if (data.success) {
                    let dStr = data.class_date_en || data.class_date;
                    let sessionsBlock = buildSessionsBlock(data.daily_summary, lang);

                    await sock.sendMessage(groupJid, { react: { text: '❎', key: msg.key } });
                    let tpl = config.templates?.daily_summary_header || t(lang, 'unattendOk');
                    // O template daily_summary_header usa a frase de confirmação; para !unattend trocamos
                    let msgTxt = tpl
                        .replace('✅ Attendance confirmed for',   '❎ Attendance cancelled for')
                        .replace('✅ ¡Asistencia confirmada para', '❎ Asistencia cancelada para')
                        .replace('{name}', senderJid.split('@')[0])
                        .replace('{date}', dStr)
                        .replace('{sessionsBlock}', sessionsBlock)
                        .replace('{listText}', sessionsBlock);
                    await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });

                    if (data.cancelled_now) {
                        let msgCancel = config.templates?.unattend_cancelled_now || t(lang, 'sessionCancelled');
                        await sock.sendMessage(groupJid, { text: msgCancel });
                    }
                } else {
                    await sock.sendMessage(groupJid, { text: t(lang, 'unattendErrGeneric').replace('{msg}', data.message || '?') });
                }
            } catch (err) {
                console.error('!unattend error:', err);
            }
        }

    } else if (text.startsWith('!list')) {
        const ourClassesGroup = config.groups?.our_classes?.jid;
        if (groupJid === ourClassesGroup) {
            try {
                const res = await fetch('https://dev.viaEi.com/bot_whatsapp/class_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'list', group_jid: groupJid, member_jid: senderJid })
                });
                const data = await res.json();
                if (data.success) {
                    let dStr = data.class_date_en || data.class_date;
                    let sessionsBlock = buildSessionsBlock(data.daily_summary, lang);

                    let listTpl = config.templates?.class_status || t(lang, 'listFallback');
                    let listText = listTpl
                        .replace('{class_info}', dStr)
                        .replace('{class_date}', dStr)
                        .replace('{date}', dStr)
                        .replace('{attendees}', sessionsBlock.trim())
                        .replace('{deadline_info}', '');
                    await sock.sendMessage(groupJid, { text: listText });
                } else {
                    await sock.sendMessage(groupJid, { text: t(lang, 'listErrGeneric').replace('{msg}', data.message || '?') });
                }
            } catch (err) {
                console.error('!list error:', err);
            }
        }

    } else if (text.startsWith('!streaks')) {
        const desafioGroup = config.groups?.desafio?.jid;
        if (groupJid === desafioGroup) {
            try {
                const res = await fetch('https://dev.viaEi.com/bot_whatsapp/mentoria_desafio_streak_list_api.php');
                const data = await res.json();
                if (data.success) {
                    const days = t(lang, 'streakDays');
                    let allTimeList = '';
                    let activeList  = '';
                    const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];

                    if (data.allTime && data.allTime.length > 0) {
                        data.allTime.forEach((item, i) => {
                            const name = item.member_name || item.member_jid.split('@')[0];
                            allTimeList += `${medals[i] || '🏅'} @${name} — ${item.longest_streak} ${days}\n`;
                        });
                    } else { allTimeList = t(lang, 'streakNoRecords'); }

                    if (data.active && data.active.length > 0) {
                        data.active.forEach((item, i) => {
                            const name = item.member_name || item.member_jid.split('@')[0];
                            activeList += `${i + 1}. @${name} — ${item.current_streak} ${days}\n`;
                        });
                    } else { activeList = t(lang, 'streakNoActive'); }

                    let msgTemplate = config.templates?.streak_leaderboard || t(lang, 'streakLeaderboard');
                    let replyMsg = msgTemplate.replace('{allTimeList}', allTimeList).replace('{activeList}', activeList);

                    let mentions = [];
                    if (data.allTime) data.allTime.forEach(m => mentions.push(m.member_jid));
                    if (data.active)  data.active.forEach(m => mentions.push(m.member_jid));
                    mentions = [...new Set(mentions)];

                    await sock.sendMessage(groupJid, { text: replyMsg, mentions });
                }
            } catch (err) {
                console.error('Error fetching streaks list:', err);
            }
        }
    }
}

// ─── HANDLER DE PARTICIPANTES ────────────────────────────────────────────────

/**
 * Trata entrada de novo participante no The Lounge (welcome legado da Mentoria).
 */
async function handleParticipant(sock, groupJid, participants, config) {
    const theLoungeJid = config.groups?.the_lounge?.jid;
    if (groupJid !== theLoungeJid || !config.templates?.welcome) return;

    for (const participantJid of participants) {
        const name = participantJid.split('@')[0];
        const text = config.templates.welcome
            .replace('@{name}', `@${name}`)
            .replace('{name}', name);
        await sock.sendMessage(groupJid, { text, mentions: [participantJid] });
        console.log(`[MENTORIA-WELCOME] Sent to ${participantJid} in The Lounge`);
    }
}

module.exports = { handleMessage, handleParticipant };
