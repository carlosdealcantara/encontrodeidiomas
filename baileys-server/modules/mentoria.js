/**
 * Módulo da Mentoria (exclusivo para alunos pagantes).
 * Chamado por bot.js somente quando isMentoriaGroup === true.
 */

// ─── HELPERS INTERNOS ────────────────────────────────────────────────────────

function formatSessionTime(startTime) {
    let tParts = startTime.split(':');
    let h = parseInt(tParts[0]);
    let ampm = 'AM';
    if (h >= 12) { ampm = 'PM'; if (h > 12) h -= 12; }
    if (h === 0) h = 12;
    let mStr = tParts[1] === '00' ? '' : ':' + tParts[1];
    return `${h}${mStr} ${ampm}`;
}

function buildSessionsBlock(dailySummary) {
    if (!dailySummary || dailySummary.length === 0) return '';
    let block = '';
    dailySummary.forEach(summary => {
        let isPractice = summary.session_type === 'student_practice';
        let tStr = formatSessionTime(summary.start_time);

        if (isPractice) {
            block += `\n━━━━━━━━━━━━━━━━━━`;
            block += `\n🗣️ *Students Practice — ${tStr}*`;
            block += `\n_Students only — no teacher_`;
        } else {
            block += `\n━━━━━━━━━━━━━━━━━━`;
            block += `\n👨‍🏫 *Teacher Class — ${tStr}*`;
        }
        block += `\n`;

        let count = summary.attendees ? summary.attendees.length : 0;
        if (count > 0) {
            summary.attendees.forEach((name, i) => block += `  ${i + 1}. ${name}\n`);
        } else {
            block += `  _No one yet._\n`;
        }

        if (isPractice) {
            if (count === 0) {
                block += `  _⚠️ 2 students needed — be the first!_\n`;
            } else if (count === 1) {
                block += `  _⚠️ 1 more student needed to confirm this session._\n`;
            } else {
                block += `  _✅ Quorum reached! Session is confirmed._\n`;
            }
        }
    });
    block += `\n━━━━━━━━━━━━━━━━━━`;
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
 */
async function handleMessage(ctx) {
    const { sock, msg, groupJid, senderJid, senderName, text, realMsg, isVisual, isAdmin, isGroupAdmin, isGlobalAdmin, msgId, config } = ctx;

    // === ADMIN SCORING COMMANDS (!number) ===
    if (isAdmin && msg.message?.extendedTextMessage?.contextInfo?.quotedMessage) {
        const cmdMatch = text.match(/^\s*!\s*(\d+)\s*$/);
        if (cmdMatch) {
            console.log(`[SCORING] Admin ${senderName} issued command ${text}`);
            const points = parseInt(cmdMatch[1], 10);
            if (points <= 0) return; // Ignore !0 or negative

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
                        body: JSON.stringify({
                            group_jid: groupJid,
                            group_key: groupKey,
                            member_jid: quotedParticipant,
                            points: points
                        })
                    });
                    const data = await res.json();

                    if (data.success) {
                        let reactEmoji = '🙌';
                        let replyMsg = `🙌 Way to go! Good effort. (+${points} pt${points !== 1 ? 's' : ''})`;

                        if (points >= 20)      { reactEmoji = '🚀'; replyMsg = `🚀 Stellar! Taking it to the next level! (+${points} pts)`; }
                        else if (points >= 15) { reactEmoji = '⚡'; replyMsg = `⚡ Outstanding! Pure energy! (+${points} pts)`; }
                        else if (points >= 10) { reactEmoji = '🔥'; replyMsg = `🔥 Awesome work! You're on fire! (+${points} pts)`; }
                        else if (points >= 5)  { reactEmoji = '🎉'; replyMsg = `🎉 Great job! Keep it going! (+${points} pts)`; }

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
                        let msTemplate = `🎉 *MILESTONE REACHED!* 🏆\nCongratulations {name}! You just hit a *{streak}-day streak*! 🔥\n\n📊 *Your Challenge Stats:*\n• Current Streak: {streak} days\n• Personal Record: {longest_streak} days\n• Total Days Completed: {total_completions} days\n\nKeep building the habit! 🚀`;
                        let milestoneMsg = msTemplate
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
    if (text.startsWith('!attend')) {
        const parts = text.split(' ');
        let schedulePosition = null;
        if (parts.length > 1) schedulePosition = parseInt(parts[1]);

        const ourClassesGroup = config.groups?.our_classes?.jid;
        if (groupJid === ourClassesGroup) {
            try {
                const reqBody = {
                    action: 'attend',
                    group_jid: groupJid,
                    member_jid: senderJid,
                    member_name: senderName
                };
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
                            let label = s.session_type === 'student_practice'
                                ? '🗣️ *!attend ' + (idx + 1) + '* — Students Practice'
                                : '👨‍🏫 *!attend ' + (idx + 1) + '* — Teacher Class';
                            optionsTxt += label + '\n';
                        });
                    } else {
                        optionsTxt = "👨‍🏫 *!attend 1* — Teacher Class\n🗣️ *!attend 2* — Students Practice\n";
                    }
                    await sock.sendMessage(groupJid, { text: `❓ We have *multiple sessions today!*\n\nPlease specify which one:\n\n${optionsTxt}\nWhich one are you joining?`, mentions: [senderJid] });
                    return;
                }

                let dStr = data.class_date_en || data.class_date;
                let sessionsBlock = buildSessionsBlock(data.daily_summary);

                if (data.success) {
                    await sock.sendMessage(groupJid, { react: { text: '✅', key: msg.key } });
                    let tpl = config.templates?.daily_summary_header || `✅ Attendance confirmed for @{name}!\n\n📅 *Today's Schedule — {date}*\n{sessionsBlock}`;
                    let msgTxt = tpl
                        .replace('{name}', senderJid.split('@')[0])
                        .replace('{date}', dStr)
                        .replace('{sessionsBlock}', sessionsBlock)
                        .replace('{listText}', sessionsBlock);
                    await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });
                } else if (data.reason === 'deadline_passed') {
                    let msgTxt = data.class_confirmed
                        ? (config.templates?.attend_late_good || `⏰ The deadline has passed, @{name}.\n\n✅ *Good news:* The class is confirmed and will happen anyway!\n{sessionsBlock}`)
                        : (config.templates?.attend_late_bad || `⏰ The deadline has passed, @{name}.\n\n❌ *Bad news:* The session was already cancelled due to lack of attendees.`);
                    msgTxt = msgTxt
                        .replace('{name}', senderJid.split('@')[0])
                        .replace('{sessionsBlock}', sessionsBlock)
                        .replace('{listText}', sessionsBlock);
                    await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });
                } else {
                    await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error confirming registration.'}` });
                }
            } catch (err) {
                await sock.sendMessage(groupJid, { text: `⚠️ Error reaching the server to confirm: ${err.message}` });
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
                            let label = s.session_type === 'student_practice'
                                ? '🗣️ *!unattend ' + (idx + 1) + '* — Students Practice'
                                : '👨‍🏫 *!unattend ' + (idx + 1) + '* — Teacher Class';
                            optionsTxt += label + '\n';
                        });
                    } else {
                        optionsTxt = "👨‍🏫 *!unattend 1* — Teacher Class\n🗣️ *!unattend 2* — Students Practice\n";
                    }
                    await sock.sendMessage(groupJid, { text: `❓ We have *multiple sessions today!*\n\nPlease specify which one:\n\n${optionsTxt}\nWhich one are you leaving?`, mentions: [senderJid] });
                    return;
                }

                if (data.success) {
                    let dStr = data.class_date_en || data.class_date;
                    let sessionsBlock = buildSessionsBlock(data.daily_summary);

                    await sock.sendMessage(groupJid, { react: { text: '❎', key: msg.key } });
                    let tpl = config.templates?.daily_summary_header || `❎ Attendance cancelled for @{name}.\n\n📅 *Today's Schedule — {date}*\n{sessionsBlock}`;
                    let msgTxt = tpl
                        .replace('✅ Attendance confirmed for', '❎ Attendance cancelled for')
                        .replace('{name}', senderJid.split('@')[0])
                        .replace('{date}', dStr)
                        .replace('{sessionsBlock}', sessionsBlock)
                        .replace('{listText}', sessionsBlock);
                    await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });

                    if (data.cancelled_now) {
                        let msgCancel = config.templates?.unattend_cancelled_now || `🚨 *SESSION CANCELLED*\n\nSince there are no more students confirmed, today's session is now cancelled.`;
                        await sock.sendMessage(groupJid, { text: msgCancel });
                    }
                } else {
                    await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error cancelling registration.'}` });
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
                    let sessionsBlock = buildSessionsBlock(data.daily_summary);

                    let listTpl = config.templates?.class_status || `📋 *Today's Schedule — {date}*\n{attendees}`;
                    let listText = listTpl
                        .replace('{class_info}', dStr)
                        .replace('{class_date}', dStr)
                        .replace('{date}', dStr)
                        .replace('{attendees}', sessionsBlock.trim())
                        .replace('{deadline_info}', '');
                    await sock.sendMessage(groupJid, { text: listText });
                } else {
                    await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error fetching status.'}` });
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
                    let allTimeList = '';
                    let activeList = '';
                    const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];

                    if (data.allTime && data.allTime.length > 0) {
                        data.allTime.forEach((item, i) => {
                            const name = item.member_name || item.member_jid.split('@')[0];
                            allTimeList += `${medals[i] || '🏅'} @${name} — ${item.longest_streak} days\n`;
                        });
                    } else { allTimeList = 'No records yet.\n'; }

                    if (data.active && data.active.length > 0) {
                        data.active.forEach((item, i) => {
                            const name = item.member_name || item.member_jid.split('@')[0];
                            activeList += `${i + 1}. @${name} — ${item.current_streak} days\n`;
                        });
                    } else { activeList = 'No active streaks right now.\n'; }

                    let msgTemplate = config.templates?.streak_leaderboard || `🏆 *All-Time Streak Records*\n\n{allTimeList}\n🔥 *Active Streaks Today*\n\n{activeList}`;
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
