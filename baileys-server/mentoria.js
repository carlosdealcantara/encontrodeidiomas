const fs = require('fs');
const path = require('path');
const { downloadMediaMessage } = require('@whiskeysockets/baileys');

let sock = null;
let dataDir = '';

// Deduplication: prevents counting the same WhatsApp message event twice
// (Baileys sometimes fires 2 events for media messages)
let processedMessageIds = new Set();
let processedIdsDate = '';

// Cache for group admins to avoid fetching metadata on every message
let groupAdminsCache = {};
let groupAdminsCacheTime = {};


// Helper to get today's date in YYYY-MM-DD for BRT
function getTodayDate() {
    // Current time in Brazil
    const formatter = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo' });
    return formatter.format(new Date()); // Returns YYYY-MM-DD
}

function getActivityFile() {
    return path.join(dataDir, 'activity_log.json');
}

function getConfigFile() {
    return path.join(dataDir, 'mentoria_config.json');
}

function getConfigBackupFile() {
    return path.join(dataDir, 'mentoria_config.backup.json');
}

function loadConfig() {
    try {
        const file = getConfigFile();
        if (fs.existsSync(file)) {
            const config = JSON.parse(fs.readFileSync(file, 'utf8'));
            // ⚠️ SAFETY CHECK: if groups is empty but backup exists, auto-restore
            const groupCount = Object.keys(config.groups || {}).length;
            const groupsWithJid = Object.values(config.groups || {}).filter(g => g.jid && g.jid.trim() !== '').length;
            if (groupsWithJid === 0) {
                const backupFile = getConfigBackupFile();
                if (fs.existsSync(backupFile)) {
                    const backup = JSON.parse(fs.readFileSync(backupFile, 'utf8'));
                    const backupGroupCount = Object.values(backup.groups || {}).filter(g => g.jid && g.jid.trim() !== '').length;
                    if (backupGroupCount > 0) {
                        console.warn(`[CONFIG] ⚠️ GRUPOS AUSENTES no config principal (${groupCount} entradas, 0 com JID). Restaurando ${backupGroupCount} grupos do backup automaticamente!`);
                        config.groups = backup.groups;
                        // Re-save the restored config
                        fs.writeFileSync(file, JSON.stringify(config, null, 2));
                    }
                } else {
                    console.warn('[CONFIG] ⚠️ ATENÇÃO: Nenhum grupo cadastrado e nenhum backup disponível. O bot vai IGNORAR todas as mensagens! Acesse o painel admin > Mensagens e Grupos > Salvar Configurações.');
                }
            }
            return config;
        }
    } catch (e) {
        console.error('Error loading mentoria config:', e);
    }
    // Default fallback
    return {
        admin_jid: "556192666148@s.whatsapp.net",
        groups: {},
        templates: {}
    };
}

function saveConfig(config) {
    fs.writeFileSync(getConfigFile(), JSON.stringify(config, null, 2));
    // ✅ Always keep a backup of the last config that had groups configured
    const groupsWithJid = Object.values(config.groups || {}).filter(g => g.jid && g.jid.trim() !== '').length;
    if (groupsWithJid > 0) {
        fs.writeFileSync(getConfigBackupFile(), JSON.stringify(config, null, 2));
        console.log(`[CONFIG] Backup salvo com ${groupsWithJid} grupos configurados.`);
    }
}

function loadActivity() {
    try {
        const file = getActivityFile();
        if (fs.existsSync(file)) {
            return JSON.parse(fs.readFileSync(file, 'utf8'));
        }
    } catch (e) {
        console.error('Error loading activity:', e);
    }
    return {};
}

function saveActivity(data) {
    fs.writeFileSync(getActivityFile(), JSON.stringify(data, null, 2));
}

// === WRITE QUEUE SYSTEM ===
const writeQueue = [];
let isSaving = false;

async function processQueue() {
    if (isSaving || writeQueue.length === 0) return;
    isSaving = true;
    
    // Extrai todos os eventos atualmente na fila de uma vez (batch)
    const events = writeQueue.splice(0, writeQueue.length);
    
    try {
        const data = loadActivity();
        
        for (const event of events) {
            const { groupJid, senderJid, senderName, type, date } = event;
            
            if (!data[date]) data[date] = {};
            if (!data[date][groupJid]) data[date][groupJid] = {};
            if (!data[date][groupJid][senderJid]) {
                data[date][groupJid][senderJid] = {
                    name: senderName,
                    messages: 0,
                    reactions_given: 0,
                    images_sent: 0,
                    audios_sent: 0,
                    first_message_at: new Date().toISOString(),
                    last_message_at: new Date().toISOString(),
                };
            }

            if (type === 'message') {
                data[date][groupJid][senderJid].messages += 1;
            } else if (type === 'reaction') {
                data[date][groupJid][senderJid].reactions_given += 1;
            } else if (type === 'image') {
                data[date][groupJid][senderJid].images_sent = (data[date][groupJid][senderJid].images_sent || 0) + 1;
                data[date][groupJid][senderJid].messages += 1; // Soma também nas mensagens globais (Word Slingers)
            } else if (type === 'audio') {
                data[date][groupJid][senderJid].audios_sent = (data[date][groupJid][senderJid].audios_sent || 0) + 1;
                data[date][groupJid][senderJid].messages += 1; // Soma também nas mensagens globais (Word Slingers)
            }
            
            // Auto-correção: se antes gravou Desconhecido e agora veio o nome real, atualiza
            if (data[date][groupJid][senderJid].name === 'Desconhecido' && senderName && senderName !== 'Desconhecido') {
                data[date][groupJid][senderJid].name = senderName;
            }
            
            data[date][groupJid][senderJid].last_message_at = new Date().toISOString();
        }
        
        saveActivity(data);
    } catch (e) {
        console.error('Error processing write queue:', e);
    } finally {
        isSaving = false;
        // Se novos eventos chegaram enquanto salvávamos, processa-os
        if (writeQueue.length > 0) {
            setTimeout(processQueue, 0);
        }
    }
}
// =========================


function logActivity(groupJid, senderJid, senderName, type) {
    const date = getTodayDate();
    writeQueue.push({ groupJid, senderJid, senderName, type, date });
    processQueue();
}

async function handleMessages({ messages, type }) {
    if (type !== 'notify') return;
    if (!sock) return;
    
    const config = loadConfig();
    const adminJid = config.admin_jid || "556192666148@s.whatsapp.net";
    // Support for multiple excluded admin JIDs (configured as array in config)
    const extraAdminJids = config.extra_admin_jids || [];
    const excludedJids = new Set([adminJid, ...extraAdminJids]);

    // Reset deduplication Set daily
    const currentDate = getTodayDate();
    if (currentDate !== processedIdsDate) {
        processedMessageIds = new Set();
        processedIdsDate = currentDate;
    }

    for (const msg of messages) {
        const groupJid = msg.key.remoteJid;
        if (!groupJid?.endsWith('@g.us')) continue; // Only groups

        // === BROAD DEBUG LOG (temporary) ===
        {
            const rawTypes = Object.keys(msg.message || {});
            const rawMsg = msg.message?.ephemeralMessage?.message || msg.message?.viewOnceMessageV2?.message || msg.message?.viewOnceMessage?.message || msg.message;
            const rawText = rawMsg?.conversation || rawMsg?.extendedTextMessage?.text || '';
            const participant = msg.key.participant || 'n/a';
            if (rawText.length > 0) {
                console.log(`[DEBUG-ALL] group: ${groupJid}, fromMe: ${msg.key.fromMe}, participant: ${participant}, text: '${rawText}'`);
            }
        }

        // Extract basic info for global commands like !pill
        const participantJid = (msg.key.participant || msg.key.remoteJid).replace(/:\d+@/, '@');
        const isMasterAdmin = msg.key.fromMe || excludedJids.has(participantJid) || participantJid === '217230939836567@lid';
        const msgId = msg.key.id;

        const globalRealMsg = msg.message?.ephemeralMessage?.message ||
                        msg.message?.viewOnceMessageV2?.message ||
                        msg.message?.viewOnceMessage?.message ||
                        msg.message;
        const globalInnerDoc = globalRealMsg?.documentWithCaptionMessage?.message?.documentMessage || globalRealMsg?.documentMessage;
        let globalText = globalRealMsg?.conversation || globalRealMsg?.extendedTextMessage?.text || globalRealMsg?.imageMessage?.caption || globalInnerDoc?.caption || '';
        globalText = globalText.replace(/^[*_~`]+|[*_~`]+$/g, '').trim();

        // DEBUG: Log everything if text contains '!pill'
        if (globalText.toLowerCase().includes('pill') || globalText.toLowerCase().includes('pílula') || globalText.toLowerCase().includes('pilula')) {
            console.log(`[DEBUG-PILL] msgId: ${msgId}, fromMe: ${msg.key.fromMe}, participant: ${msg.key.participant}, group: ${groupJid}, isMasterAdmin: ${isMasterAdmin}, text: '${globalText}', rawMsgTypes:`, Object.keys(msg.message || {}));
            console.log(`[DEBUG-PILL-FULL]`, JSON.stringify(msg.message, null, 2));
        }

        // === PÍLULAS DE INGLÊS: CAPTURA DE ÁUDIO VIA COMANDO (GLOBAL) ===
        if (isMasterAdmin && !processedMessageIds.has(msgId) && globalText.toLowerCase() === '!pill') {
            const rawQuoted = globalRealMsg?.extendedTextMessage?.contextInfo?.quotedMessage;
            const unwrappedQuoted = rawQuoted?.ephemeralMessage?.message || rawQuoted?.viewOnceMessage?.message || rawQuoted;
            
            const quotedAudio = unwrappedQuoted?.audioMessage || unwrappedQuoted?.pttMessage;
            
            if (quotedAudio) {
                processedMessageIds.add(msgId);
                console.log(`[PÍLULAS] Comando !pill detectado no grupo ${groupJid}. Baixando áudio...`);
                try {
                    // React with a pill emoji to give immediate feedback
                    await sock.sendMessage(groupJid, { react: { text: '💊', key: msg.key } });

                    // Create a fake WAMessage to pass to downloadMediaMessage
                    const fakeMsg = {
                        key: msg.key,
                        message: unwrappedQuoted
                    };
                    const buffer = await downloadMediaMessage(
                        fakeMsg, 
                        'buffer', 
                        { }, 
                        { logger: sock.logger, reuploadRequest: sock.updateMediaMessage }
                    );
                    const fileName = `pilula_${Date.now()}.ogg`;
                    const audiosDir = path.join(dataDir, 'audios_pilulas');
                    if (!fs.existsSync(audiosDir)) {
                        fs.mkdirSync(audiosDir, { recursive: true });
                    }
                    const filePath = path.join(audiosDir, fileName);
                    fs.writeFileSync(filePath, buffer);
                    console.log(`[PÍLULAS] Áudio salvo em: ${filePath}`);

                    // Chamar a API PHP para cadastrar o rascunho
                    const res = await fetch('https://dev.viaEi.com/bot_whatsapp/api_pilulas_webhook.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            apikey: 'SenhaMeetups2026',
                            action: 'new_audio',
                            audio_path: `baileys-server/data/audios_pilulas/${fileName}` // Caminho relativo para o PHP
                        })
                    });
                    
                    const data = await res.json();
                    if (data.success) {
                        console.log(`[PÍLULAS] Rascunho criado com sucesso no banco (ID: ${data.id})`);
                        await sock.sendMessage(groupJid, { text: `✅ Pílula capturada e salva como rascunho no painel admin! (ID: ${data.id})` }, { quoted: msg });
                    } else {
                        console.error(`[PÍLULAS] Erro retornado pela API PHP:`, data);
                        await sock.sendMessage(groupJid, { text: `❌ Falha ao salvar no painel admin.` }, { quoted: msg });
                    }
                } catch (err) {
                    console.error('[PÍLULAS] Erro ao processar áudio do Admin:', err);
                    await sock.sendMessage(groupJid, { text: `❌ Erro interno ao tentar baixar o áudio.` }, { quoted: msg });
                }
            }
        }

        // === E-BOOK: CAPTURA DE ÁUDIO VIA COMANDO !wordN (GLOBAL) ===
        const wordCmdMatch = globalText.match(/^!word(\d+)$/i);
        if (isMasterAdmin && !processedMessageIds.has(msgId) && wordCmdMatch) {
            const wordNumber = parseInt(wordCmdMatch[1], 10);
            const rawQuotedWord = globalRealMsg?.extendedTextMessage?.contextInfo?.quotedMessage;
            const unwrappedQuotedWord = rawQuotedWord?.ephemeralMessage?.message || rawQuotedWord?.viewOnceMessage?.message || rawQuotedWord;

            const quotedAudioWord = unwrappedQuotedWord?.audioMessage || unwrappedQuotedWord?.pttMessage;

            if (quotedAudioWord) {
                processedMessageIds.add(msgId);
                console.log(`[E-BOOK] Comando !word${wordNumber} detectado no grupo ${groupJid}. Baixando áudio...`);
                try {
                    // Reação imediata com emoji de livro
                    await sock.sendMessage(groupJid, { react: { text: '📖', key: msg.key } });

                    const fakeMsgWord = {
                        key: msg.key,
                        message: unwrappedQuotedWord
                    };
                    const bufferWord = await downloadMediaMessage(
                        fakeMsgWord,
                        'buffer',
                        {},
                        { logger: sock.logger, reuploadRequest: sock.updateMediaMessage }
                    );

                    const fileNameWord = `word_${wordNumber}_${Date.now()}.ogg`;
                    const ebookAudiosDir = path.join(dataDir, 'audios_ebook');
                    if (!fs.existsSync(ebookAudiosDir)) {
                        fs.mkdirSync(ebookAudiosDir, { recursive: true });
                    }
                    const filePathWord = path.join(ebookAudiosDir, fileNameWord);
                    fs.writeFileSync(filePathWord, bufferWord);
                    console.log(`[E-BOOK] Áudio salvo em: ${filePathWord}`);

                    // Chamar a API PHP para registrar a palavra
                    const resWord = await fetch('https://dev.viaEi.com/bot_whatsapp/api_ebook_webhook.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            apikey: 'SenhaMeetups2026',
                            action: 'register_word',
                            word_number: wordNumber,
                            audio_path: `baileys-server/data/audios_ebook/${fileNameWord}`
                        })
                    });

                    const dataWord = await resWord.json();
                    if (dataWord.success) {
                        const actionLabel = dataWord.is_update ? 'atualizada' : 'registrada';
                        console.log(`[E-BOOK] Palavra #${wordNumber} ${actionLabel} com sucesso no banco (ID: ${dataWord.id})`);
                        await sock.sendMessage(groupJid, {
                            text: `✅ Palavra *#${wordNumber}* ${actionLabel} com sucesso!`
                        }, { quoted: msg });
                    } else {
                        console.error(`[E-BOOK] Erro retornado pela API PHP:`, dataWord);
                        await sock.sendMessage(groupJid, { text: `❌ Falha ao registrar a palavra #${wordNumber} no painel admin.` }, { quoted: msg });
                    }
                } catch (err) {
                    console.error('[E-BOOK] Erro ao processar áudio:', err);
                    await sock.sendMessage(groupJid, { text: `❌ Erro interno ao tentar baixar o áudio da palavra #${wordNumber}.` }, { quoted: msg });
                }
            }
        }

        // Check if group is one of the configured ones, ignore others
        const allowedGroups = Object.values(config.groups || {}).map(g => g.jid);
        if (!allowedGroups.includes(groupJid)) continue;

        // Ignore phantom/system messages in groups that lack a participant
        if (!msg.key.fromMe && !msg.key.participant) continue;

        // FIX 1: Skip protocol messages (message deletions = REVOKE, edits = MESSAGE_EDIT)
        // These are meta-events from WhatsApp and should never count as activity.
        if (msg.message?.protocolMessage || msg.message?.editedMessage) continue;

        const botJid = sock.user.id.split(':')[0] + '@s.whatsapp.net';
        const senderJid = msg.key.participant || (msg.key.fromMe ? botJid : msg.key.remoteJid);
        const senderName = msg.pushName || (msg.key.fromMe ? 'Eu (Admin)' : 'Desconhecido');
        if (senderName === 'Encontro de Idiomas' || senderName === 'Eu (Admin)') continue; // Ignora o bot oficial e o admin local

        // Desembrulhar qualquer tipo de envelope (efêmero, view-once, document-with-caption)
        // NOTA: documentWithCaptionMessage contém a mensagem diretamente em .message,
        // NÃO num sub-campo, então ele já é aberto pelo último fallback abaixo.
        const realMsg = msg.message?.ephemeralMessage?.message ||
                        msg.message?.viewOnceMessageV2?.message ||
                        msg.message?.viewOnceMessage?.message ||
                        msg.message;

        if (!realMsg) continue;
        
        // Prevent premature deduplication of empty/stub messages
        const msgTypes = Object.keys(realMsg);
        const hasContent = msgTypes.some(t => t !== 'senderKeyDistributionMessage' && t !== 'messageContextInfo');
        if (!hasContent) continue;

        // Imagem pode chegar em vários formatos conforme versão do WhatsApp / dispositivo:
        // 1. imageMessage        — foto clássica
        // 2. documentMessage     — arquivo enviado; quando mimeType começa com 'image/' é uma foto salva como doc
        // 3. documentWithCaptionMessage — mesmo que acima, mas com legenda
        // 4. viewOnceMessage (já desembrulhado acima) — visualização única
        // 5. albumMessage        — álbum de múltiplas fotos (WhatsApp novo)
        const innerDoc = realMsg?.documentWithCaptionMessage?.message?.documentMessage
                         || realMsg?.documentMessage;
        const isVisual = !!(realMsg?.imageMessage ||
                            (innerDoc && (innerDoc.mimetype || '').startsWith('image/')) ||
                            realMsg?.videoMessage ||
                            realMsg?.albumMessage);

        let text = realMsg?.conversation || realMsg?.extendedTextMessage?.text ||
                     realMsg?.imageMessage?.caption ||
                     innerDoc?.caption || '';
        
        // Remove WhatsApp formatting at start/end (e.g. `!attend`, *!attend*) so commands still work
        text = text.replace(/^[*_~`]+|[*_~`]+$/g, '').trim();

        const cleanSenderJid = senderJid.replace(/:\d+@/, '@');

        // Determine if sender is a group admin
        let isGroupAdmin = false;
        try {
            const now = Date.now();
            if (!groupAdminsCache[groupJid] || (now - groupAdminsCacheTime[groupJid] > 3600000)) { // 1 hour cache
                const metadata = await sock.groupMetadata(groupJid);
                const admins = metadata.participants.filter(p => p.admin === 'admin' || p.admin === 'superadmin').map(p => p.id.replace(/:\d+@/, '@'));
                groupAdminsCache[groupJid] = new Set(admins);
                groupAdminsCacheTime[groupJid] = now;
            }
            isGroupAdmin = groupAdminsCache[groupJid].has(cleanSenderJid);
        } catch (e) {
            console.error('Error fetching group admins:', e);
        }

        const isAdmin = msg.key.fromMe || excludedJids.has(cleanSenderJid) || cleanSenderJid === '217230939836567@lid' || isGroupAdmin;
        // msgId already declared above (line ~204)

        if (!isAdmin && !processedMessageIds.has(msgId)) {
            processedMessageIds.add(msgId);

            const msgTypes = Object.keys(realMsg || {});

            if (realMsg?.reactionMessage || msg.message?.reactionMessage) {
                logActivity(groupJid, senderJid, senderName, 'reaction');
            } else if (realMsg?.audioMessage || realMsg?.pttMessage) {
                logActivity(groupJid, senderJid, senderName, 'audio');
            } else if (isVisual) {
                logActivity(groupJid, senderJid, senderName, 'image');
                // Log diagnóstico para acompanhamento
                const desafioGrp = config.groups?.desafio?.jid;
                if (groupJid === desafioGrp) {
                    console.log('[DESAFIO-IMG]', senderName, '| tipos:', msgTypes.join(','), '| visual=true');
                }
            } else {
                logActivity(groupJid, senderJid, senderName, 'message');
                // Log diagnóstico temporário para qualquer tipo não-texto no Desafio
                const desafioGrp = config.groups?.desafio?.jid;
                if (groupJid === desafioGrp) {
                    console.log('[DESAFIO-MISS]', senderName, '| tipos msg:', msgTypes.join(','));
                }
            }
        }

        // (Pill command logic was moved to the top of handleMessages to be global)

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
                            if (gData.jid === groupJid) {
                                groupKey = key;
                                break;
                            }
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
                            
                            if (points >= 20) {
                                reactEmoji = '🚀';
                                replyMsg = `🚀 Stellar! Taking it to the next level! (+${points} pts)`;
                            } else if (points >= 15) {
                                reactEmoji = '⚡';
                                replyMsg = `⚡ Outstanding! Pure energy! (+${points} pts)`;
                            } else if (points >= 10) {
                                reactEmoji = '🔥';
                                replyMsg = `🔥 Awesome work! You're on fire! (+${points} pts)`;
                            } else if (points >= 5) {
                                reactEmoji = '🎉';
                                replyMsg = `🎉 Great job! Keep it going! (+${points} pts)`;
                            }
                            
                            // React to the student's message
                            await sock.sendMessage(groupJid, { react: { text: reactEmoji, key: { remoteJid: groupJid, fromMe: false, id: msg.message.extendedTextMessage.contextInfo.stanzaId, participant: quotedParticipant } } });
                            
                            // Send reply message in English
                            await sock.sendMessage(groupJid, { text: replyMsg, mentions: [quotedParticipant] });
                        }
                    } catch (err) {
                        console.error('Error awarding points:', err);
                    }
                }
            }
        }

        // === STREAK SYSTEM (Desafio Group — runs independently of activity logging) ===
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
                        
                        // Reagir à mensagem original em vez de enviar texto
                        await sock.sendMessage(groupJid, { react: { text: '🎉', key: msg.key } });
                        
                        // Se atingiu milestone, envia o relatório
                        if (data.is_milestone) {
                            // Ignoramos o template antigo da config para forçar o novo design detalhado
                            let msTemplate = `🎉 *MILESTONE REACHED!* 🏆\nCongratulations {name}! You just hit a *{streak}-day streak*! 🔥\n\n📊 *Your Challenge Stats:*\n• Current Streak: {streak} days\n• Personal Record: {longest_streak} days\n• Total Days Completed: {total_completions} days\n\nKeep building the habit! 🚀`;
                            
                            let milestoneMsg = msTemplate
                                .replace('{name}', nameToUse)
                                .replace(/{streak}/g, data.streak)
                                .replace('{longest_streak}', data.longest_streak)
                                .replace('{total_completions}', data.total_completions);
                                
                            setTimeout(async () => {
                                await sock.sendMessage(groupJid, { text: milestoneMsg });
                            }, 2000);
                        }
                    }
                } catch (err) {
                    console.error('Error calling streak API:', err);
                }
            }
        }
            
        // Helper: builds the unified sessions block for the confirmation message
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
                    summary.attendees.forEach((name, i) => block += `  ${i+1}. ${name}\n`);
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
                                let label = s.session_type === 'student_practice' ? '🗣️ *!attend ' + (idx + 1) + '* — Students Practice' : '👨‍🏫 *!attend ' + (idx + 1) + '* — Teacher Class';
                                optionsTxt += label + '\n';
                            });
                        } else {
                            optionsTxt = "👨‍🏫 *!attend 1* — Teacher Class\n🗣️ *!attend 2* — Students Practice\n";
                        }

                        await sock.sendMessage(groupJid, { 
                            text: `❓ We have *multiple sessions today!*\n\nPlease specify which one:\n\n${optionsTxt}\nWhich one are you joining?`,
                            mentions: [senderJid]
                        });
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
                                let label = s.session_type === 'student_practice' ? '🗣️ *!unattend ' + (idx + 1) + '* — Students Practice' : '👨‍🏫 *!unattend ' + (idx + 1) + '* — Teacher Class';
                                optionsTxt += label + '\n';
                            });
                        } else {
                            optionsTxt = "👨‍🏫 *!unattend 1* — Teacher Class\n🗣️ *!unattend 2* — Students Practice\n";
                        }

                        await sock.sendMessage(groupJid, { 
                            text: `❓ We have *multiple sessions today!*\n\nPlease specify which one:\n\n${optionsTxt}\nWhich one are you leaving?`,
                            mentions: [senderJid]
                        });
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
                    console.error("!unattend error:", err);
                }
            }
        } else if (text.startsWith('!list')) {
            const ourClassesGroup = config.groups?.our_classes?.jid;
            if (groupJid === ourClassesGroup) {
                try {
                    const res = await fetch('https://dev.viaEi.com/bot_whatsapp/class_api.php', {
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
                        
                        let msg = config.templates?.class_status || `📋 *Today's Schedule — {date}*\n{attendees}`;
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
                        } else {
                            allTimeList = 'No records yet.\n';
                        }

                        if (data.active && data.active.length > 0) {
                            data.active.forEach((item, i) => {
                                const name = item.member_name || item.member_jid.split('@')[0];
                                activeList += `${i+1}. @${name} — ${item.current_streak} days\n`;
                            });
                        } else {
                            activeList = 'No active streaks right now.\n';
                        }

                        let msgTemplate = config.templates?.streak_leaderboard || `🏆 *All-Time Streak Records*\n\n{allTimeList}\n🔥 *Active Streaks Today*\n\n{activeList}`;
                        let replyMsg = msgTemplate.replace('{allTimeList}', allTimeList).replace('{activeList}', activeList);

                        // Coletar as menções necessárias
                        let mentions = [];
                        if (data.allTime) data.allTime.forEach(m => mentions.push(m.member_jid));
                        if (data.active) data.active.forEach(m => mentions.push(m.member_jid));
                        mentions = [...new Set(mentions)]; // Remover duplicatas

                        await sock.sendMessage(groupJid, { 
                            text: replyMsg,
                            mentions: mentions
                        });
                    }
                } catch (err) {
                    console.error('Error fetching streaks list:', err);
                }
            }
        }
    }
}

async function handleParticipants({ id, participants, action }) {
    if (!sock) return;
    if (action === 'add') {
        const config = loadConfig();
        const theLoungeJid = config.groups?.the_lounge?.jid;
        
        if (id === theLoungeJid && config.templates?.welcome) {
            for (const participantJid of participants) {
                const name = participantJid.split('@')[0];
                const text = config.templates.welcome.replace('@{name}', `@${name}`).replace('{name}', name);
                
                await sock.sendMessage(id, {
                    text,
                    mentions: [participantJid]
                });
                console.log(`[WELCOME] Welcome message sent to ${participantJid} in The Lounge`);
            }
        }
    }
}

function initRoutes(app, dir) {
    dataDir = dir;

    // GET /activity
    app.get('/activity', (req, res) => {
        const date = req.query.date || getTodayDate();
        const data = loadActivity();
        res.json(data[date] || {});
    });

    // GET /group-members
    app.get('/group-members', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const groupId = req.query.groupId;
            if (!groupId) return res.status(400).json({ error: 'Missing groupId' });
            
            const metadata = await sock.groupMetadata(groupId);
            res.json(metadata.participants || []);
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // GET /groups
    app.get('/groups', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const groups = await sock.groupFetchAllParticipating();
            const groupList = [];
            for (const id in groups) {
                groupList.push({
                    id: groups[id].id,
                    subject: groups[id].subject
                });
            }
            res.json(groupList);
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // POST /send-mention
    app.post('/send-mention', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const { to, message, mentions } = req.body;
            if (!to || !message) return res.status(400).json({ error: 'Missing to or message' });
            
            const result = await sock.sendMessage(to, { text: message, mentions: mentions || [] });
            res.json({ success: true, result });
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // POST /group-remove
    app.post('/group-remove', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const { groupId, participants } = req.body;
            if (!groupId || !participants || !Array.isArray(participants)) {
                return res.status(400).json({ error: 'Invalid parameters' });
            }
            
            const result = await sock.groupParticipantsUpdate(groupId, participants, 'remove');
            res.json({ success: true, result });
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // Config endpoints
    app.get('/mentoria-config', (req, res) => {
        res.json(loadConfig());
    });

    app.post('/mentoria-config', (req, res) => {
        saveConfig(req.body);
        res.json({ success: true });
    });

    // POST /mentoria-edit-activity
    app.post('/mentoria-edit-activity', (req, res) => {
        const { apikey, date, group_jid, member_jid, field, value } = req.body;
        if (apikey !== 'SenhaMeetups2026') return res.status(401).json({ error: 'Unauthorized' });
        
        try {
            const data = loadActivity();
            if (!data[date]) data[date] = {};
            if (!data[date][group_jid]) data[date][group_jid] = {};
            if (!data[date][group_jid][member_jid]) data[date][group_jid][member_jid] = { name: "Unknown" };
            
            data[date][group_jid][member_jid][field] = value;
            saveActivity(data);
            res.json({ success: true });
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });
}

function setSock(socket) {
    sock = socket;
}

module.exports = {
    initRoutes,
    setSock,
    handleMessages,
    handleParticipants
};
