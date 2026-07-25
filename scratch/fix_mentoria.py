import os

filepath = r'c:\Users\sorla\Projetos\encontrodeidiomas\baileys-server\mentoria.js'

with open(filepath, 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_code = """        // Helper: builds the unified sessions block for the confirmation message
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
                    block += `\\n━━━━━━━━━━━━━━━━━━`;
                    block += `\\n🗣️ *Students Practice — ${tStr}*`;
                    block += `\\n_Students only — no teacher_`;
                } else {
                    block += `\\n━━━━━━━━━━━━━━━━━━`;
                    block += `\\n👨‍🏫 *Teacher Class — ${tStr}*`;
                }
                block += `\\n`;

                let count = summary.attendees ? summary.attendees.length : 0;
                if (count > 0) {
                    summary.attendees.forEach((name, i) => block += `  ${i+1}. ${name}\\n`);
                } else {
                    block += `  _No one yet._\\n`;
                }

                if (isPractice) {
                    if (count === 0) {
                        block += `  _⚠️ 2 students needed — be the first!_\\n`;
                    } else if (count === 1) {
                        block += `  _⚠️ 1 more student needed to confirm this session._\\n`;
                    } else {
                        block += `  _✅ Quorum reached! Session is confirmed._\\n`;
                    }
                }
            });
            block += `\\n━━━━━━━━━━━━━━━━━━`;
            return block;
        }

        // Check for commands (e.g. !attend in Our Classes)
        if (text.startsWith('!attend')) {
            const parts = text.split(' ');
            let schedulePosition = null;
            if (parts.length > 1) {
                schedulePosition = parseInt(parts[1]);
            }

            // If it's the Our Classes group, log the booking
            const ourClassesGroup = config.groups?.our_classes?.jid;
            if (groupJid === ourClassesGroup) {
                try {
                    const reqBody = {
                        action: 'attend',
                        group_jid: groupJid,
                        member_jid: senderJid,
                        member_name: senderName
                    };
                    if (schedulePosition && !isNaN(schedulePosition)) {
                        reqBody.schedule_position = schedulePosition;
                    }

                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/class_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(reqBody)
                    });
                    const data = await res.json();
                    
                    if (!data.success && data.reason === 'multiple_sessions_require_id') {
                        await sock.sendMessage(groupJid, { 
                            text: `❓ We have *multiple sessions today!*\\n\\nPlease specify which one:\\n\\n👨‍🏫 *!attend 1* — Teacher Class\\n🗣️ *!attend 2* — Students Practice\\n\\nWhich one are you joining?`,
                            mentions: [senderJid]
                        });
                        return;
                    }

                    let dStr = data.class_date_en || data.class_date;
                    let sessionsBlock = buildSessionsBlock(data.daily_summary);

                    if (data.success) {
                        await sock.sendMessage(groupJid, { react: { text: '✅', key: msg.key } });
                        
                        let tpl = config.templates?.daily_summary_header || `✅ Attendance confirmed for @{name}!\\n\\n📅 *Today's Schedule — {date}*\\n{sessionsBlock}`;
                        let msgTxt = tpl
                            .replace('{name}', senderJid.split('@')[0])
                            .replace('{date}', dStr)
                            .replace('{sessionsBlock}', sessionsBlock)
                            .replace('{listText}', sessionsBlock);
                        
                        await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });
                    } else if (data.reason === 'deadline_passed') {
                        let msgTxt = data.class_confirmed 
                            ? (config.templates?.attend_late_good || `⏰ The deadline has passed, @{name}.\\n\\n✅ *Good news:* The class is confirmed and will happen anyway!\\n{sessionsBlock}`)
                            : (config.templates?.attend_late_bad || `⏰ The deadline has passed, @{name}.\\n\\n❌ *Bad news:* The session was already cancelled due to lack of attendees.`);
                        
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
            if (parts.length > 1) {
                schedulePosition = parseInt(parts[1]);
            }
            
            const ourClassesGroup = config.groups?.our_classes?.jid;
            if (groupJid === ourClassesGroup) {
                try {
                    const reqBody = {
                        action: 'unattend',
                        group_jid: groupJid,
                        member_jid: senderJid
                    };
                    if (schedulePosition && !isNaN(schedulePosition)) {
                        reqBody.schedule_position = schedulePosition;
                    }
                    
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/class_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(reqBody)
                    });
                    const data = await res.json();
                    
                    if (!data.success && data.reason === 'multiple_sessions_require_id') {
                        await sock.sendMessage(groupJid, { 
                            text: `❓ We have *multiple sessions today!*\\n\\nPlease specify which one:\\n\\n👨‍🏫 *!unattend 1* — Teacher Class\\n🗣️ *!unattend 2* — Students Practice\\n\\nWhich one are you leaving?`,
                            mentions: [senderJid]
                        });
                        return;
                    }
                    
                    if (data.success) {
                        let dStr = data.class_date_en || data.class_date;
                        let sessionsBlock = buildSessionsBlock(data.daily_summary);

                        await sock.sendMessage(groupJid, { react: { text: '❎', key: msg.key } });

                        let tpl = config.templates?.daily_summary_header || `❎ Attendance cancelled for @{name}.\\n\\n📅 *Today's Schedule — {date}*\\n{sessionsBlock}`;
                        let msgTxt = tpl
                            .replace('✅ Attendance confirmed for', '❎ Attendance cancelled for')
                            .replace('{name}', senderJid.split('@')[0])
                            .replace('{date}', dStr)
                            .replace('{sessionsBlock}', sessionsBlock)
                            .replace('{listText}', sessionsBlock);

                        await sock.sendMessage(groupJid, { text: msgTxt, mentions: [senderJid] });
                        
                        if (data.cancelled_now) {
                            let msgCancel = config.templates?.unattend_cancelled_now || `🚨 *SESSION CANCELLED*\\n\\nSince there are no more students confirmed, today's session is now cancelled.`;
                            await sock.sendMessage(groupJid, { text: msgCancel });
                        }
                    } else {
                        await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error cancelling registration.'}` });
                    }
                } catch (err) {
                    console.error("!unattend error:", err);
                }
            }
        } else if (text.startsWith('!list')) {
            const ourClassesGroup = config.groups?.our_classes?.jid;
            if (groupJid === ourClassesGroup) {
                try {
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/class_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'list',
                            group_jid: groupJid,
                            member_jid: senderJid
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        let dStr = data.class_date_en || data.class_date;
                        let sessionsBlock = buildSessionsBlock(data.daily_summary);
                        
                        let msg = config.templates?.class_status || `📋 *Today's Schedule — {date}*\\n{attendees}`;
                        msg = msg
                            .replace('{class_info}', dStr)
                            .replace('{class_date}', dStr)
                            .replace('{date}', dStr)
                            .replace('{attendees}', sessionsBlock.trim())
                            .replace('{deadline_info}', '');

                        await sock.sendMessage(groupJid, { text: msg });
                    } else {
                        await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error fetching status.'}` });
                    }
                } catch (err) {
                    console.error("!list error:", err);
                }
            }
"""

start_idx = -1
end_idx = -1

for i, line in enumerate(lines):
    if line.strip() == "// Check for commands (e.g. !attend in Our Classes)":
        start_idx = i
    if line.strip() == "} else if (text.startsWith('!streaks')) {":
        end_idx = i - 1

if start_idx != -1 and end_idx != -1:
    new_lines = lines[:start_idx] + [new_code] + lines[end_idx+1:]
    with open(filepath, 'w', encoding='utf-8') as f:
        f.writelines(new_lines)
    print("File modified successfully.")
else:
    print(f"Indices not found. start: {start_idx}, end: {end_idx}")
