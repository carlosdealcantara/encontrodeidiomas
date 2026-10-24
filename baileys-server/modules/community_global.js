/**
 * Módulo da Comunidade Global.
 * Chamado por bot.js somente quando isCommunityGroup === true.
 */

const BASE_API_URL  = 'https://dev.viaEi.com/bot_whatsapp';
const QUEUE_API_URL = `${BASE_API_URL}/community_welcome_queue_api.php?token=83x9aZ2pLQw1`;
const WELCOME_API_URL = `${BASE_API_URL}/community_welcome_api.php?token=83x9aZ2pLQw1`;

/**
 * Trata mensagens em grupos da Comunidade Global.
 * Activity logging é feito pelo bot.js antes de chegar aqui.
 * Reservado para futuros comandos exclusivos da comunidade.
 *
 * @param {object} ctx
 */
async function handleMessage(ctx) {
    // Futuros comandos da Comunidade Global entram aqui.
    // Não adicionar lógica da Mentoria aqui.
}

// Fila em memória — agrupa participantes que chegam no mesmo intervalo
const pendingWelcomes = {};

/**
 * Registra um participante na fila persistente (DB) via API.
 * @param {string} groupJid
 * @param {string} participantJid
 * @param {number} delaySeconds - tempo estimado até o envio
 */
async function enqueueInDB(groupJid, participantJid, delaySeconds) {
    const urls = [
        `${QUEUE_API_URL}&action=enqueue`,
        `${QUEUE_API_URL.replace('https://dev.viaEi.com', 'https://viaEi.com')}&action=enqueue`
    ];
    for (const url of urls) {
        try {
            const body = new URLSearchParams({
                group_jid:       groupJid,
                participant_jid: participantJid,
                delay_seconds:   String(delaySeconds),
            });
            const res = await fetch(url, { method: 'POST', body });
            const rawText = await res.text();
            const data = JSON.parse(rawText);
            if (data.skipped) {
                console.log(`[COMMUNITY-WELCOME] DB queue: ${participantJid} já estava pendente, ignorado.`);
            } else {
                console.log(`[COMMUNITY-WELCOME] DB queue: ${participantJid} registrado (id=${data.id}).`);
            }
            return data.id ?? null;
        } catch (err) {
            console.warn(`[COMMUNITY-WELCOME] Falha ao registrar na fila DB via ${url}:`, err.message);
        }
    }
    return null;
}

/**
 * Marca IDs da fila como 'sent' ou 'failed'.
 * @param {number[]} ids
 * @param {'sent'|'failed'} status
 */
async function updateQueueStatus(ids, status) {
    if (!ids || ids.length === 0) return;
    const urls = [
        `${QUEUE_API_URL}&action=mark_${status}`,
        `${QUEUE_API_URL.replace('https://dev.viaEi.com', 'https://viaEi.com')}&action=mark_${status}`
    ];
    for (const url of urls) {
        try {
            const body = new URLSearchParams({ ids: ids.join(',') });
            await fetch(url, { method: 'POST', body });
            return;
        } catch (err) {
            console.warn(`[COMMUNITY-WELCOME] Falha ao marcar fila como ${status} via ${url}:`, err.message);
        }
    }
}

/**
 * Monta e envia a mensagem de boas-vindas para os participantes agrupados.
 * Retorna true em caso de sucesso, false em caso de erro.
 *
 * @param {object} sock
 * @param {string} groupJid
 * @param {string[]} participants
 * @param {object} communityConfig
 * @param {number[]} queueIds - IDs na tabela de fila para atualizar status
 */
async function sendGroupedWelcome(sock, groupJid, participants, communityConfig, queueIds = []) {
    try {
        const apiUrl = `${WELCOME_API_URL}&group_jid=${encodeURIComponent(groupJid)}`;
        let res, data;
        let lastErr = null;
        const urlsToTry = [
            apiUrl,
            apiUrl.replace('https://dev.viaEi.com', 'https://viaEi.com')
        ];

        for (const targetUrl of urlsToTry) {
            try {
                res = await fetch(targetUrl);
                const rawText = await res.text();
                data = JSON.parse(rawText);
                lastErr = null;
                break;
            } catch (parseErr) {
                lastErr = parseErr;
                console.warn(`[COMMUNITY-WELCOME] Falha ao obter JSON de ${targetUrl}:`, parseErr.message);
                await new Promise(r => setTimeout(r, 2000));
            }
        }

        if (!data) {
            throw new Error(`Falha em todas as tentativas de consulta à welcome API: ${lastErr?.message}`);
        }

        if (!data.enabled) {
            console.log(`[COMMUNITY-WELCOME] Welcome disabled for group ${groupJid} (API returned enabled=false)`);
            await updateQueueStatus(queueIds, 'sent'); // Considera encerrado sem envio
            return true;
        }

        const isEnglishGroup = data.is_english_group;
        const introTarget    = data.intro_target;
        const introEn        = data.intro_en;
        const questions      = data.questions || [];

        // Build mentions list (used in intro placeholder)
        const mentionsList = participants.map(jid => `@${jid.split('@')[0]}`).join(' ');

        // Build intro text
        let introText = isEnglishGroup ? introEn : introTarget;
        introText = introText.replace('{mentions}', mentionsList);

        // Build questions block
        let questionsText = '';
        questions.forEach((q, i) => {
            if (isEnglishGroup) {
                questionsText += `${i + 1}. ${q.en}\n`;
            } else {
                if (q.target && q.target !== q.en) {
                    questionsText += `${i + 1}. *${q.target}*\n_${q.en}_\n\n`;
                } else {
                    questionsText += `${i + 1}. ${q.en}\n`;
                }
            }
        });

        // Assemble final message using general template
        const tplGeneral = communityConfig.templates?.community_welcome_general ||
            '{intro_text}\n\nWe\'d love to get to know you! Tell us:\n\n{questions_text}';

        const finalMsg = tplGeneral
            .replace('{intro_text}', introText.trim())
            .replace('{questions_text}', questionsText.trim());

        await sock.sendMessage(groupJid, { text: finalMsg, mentions: participants });
        console.log(`[COMMUNITY-WELCOME] ✅ Sent grouped welcome in group ${groupJid} for ${participants.length} participant(s)`);
        await updateQueueStatus(queueIds, 'sent');
        return true;

    } catch (err) {
        console.error(`[COMMUNITY-WELCOME] ❌ Error sending grouped welcome message in group ${groupJid}:`, err);
        await updateQueueStatus(queueIds, 'failed');
        return false;
    }
}

/**
 * Trata entrada de novo participante em grupo da Comunidade Global.
 * Agrupa múltiplos participantes que entrarem no mesmo intervalo de tempo.
 *
 * @param {object} sock            - instância Baileys
 * @param {string} groupJid        - JID do grupo onde alguém entrou
 * @param {string[]} participants  - JIDs dos novos participantes
 * @param {object} communityConfig - config já carregado pelo bot.js
 */
async function handleParticipant(sock, groupJid, participants, communityConfig) {
    const communityGroupEntry = Object.values(communityConfig.groups || {}).find(g => g.jid === groupJid);
    if (!communityGroupEntry || !communityGroupEntry.welcome_enabled) return;

    // Inicializa a fila para este grupo se não existir
    if (!pendingWelcomes[groupJid]) {
        pendingWelcomes[groupJid] = {
            participants: new Set(),
            queueIds:     [],
            timer:        null
        };
    }

    // Calcula o delay: se já há um timer rodando, usa o tempo restante desse timer
    const delayMs = pendingWelcomes[groupJid].timer
        ? 0                                                          // Já há um timer — apenas adiciona à fila
        : Math.floor(Math.random() * (180000 - 60000 + 1)) + 60000; // 1–3 minutos

    // Adiciona os novos participantes à fila em memória e registra no DB
    for (const p of participants) {
        pendingWelcomes[groupJid].participants.add(p);
        const dbId = await enqueueInDB(groupJid, p, Math.round(delayMs / 1000));
        if (dbId) pendingWelcomes[groupJid].queueIds.push(dbId);
    }

    // Se já há um temporizador rodando, apenas avisa e retorna
    if (pendingWelcomes[groupJid].timer) {
        console.log(`[COMMUNITY-WELCOME] Adicionando ${participants.length} pessoa(s) à fila de welcome do grupo ${groupJid}.`);
        return;
    }

    console.log(`[COMMUNITY-WELCOME] Iniciando delay de ${Math.round(delayMs / 1000)}s para agrupar e enviar welcome para ${groupJid}...`);

    pendingWelcomes[groupJid].timer = setTimeout(async () => {
        // Extrai todos os participantes acumulados
        const currentParticipants = Array.from(pendingWelcomes[groupJid].participants);
        const currentQueueIds     = [...pendingWelcomes[groupJid].queueIds];

        // Limpa a fila em memória para os próximos que entrarem
        pendingWelcomes[groupJid].participants.clear();
        pendingWelcomes[groupJid].queueIds = [];
        pendingWelcomes[groupJid].timer    = null;

        if (currentParticipants.length === 0) return;

        console.log(`[COMMUNITY-WELCOME] Disparando welcome agrupado para ${currentParticipants.length} participante(s) no grupo ${groupJid}...`);
        await sendGroupedWelcome(sock, groupJid, currentParticipants, communityConfig, currentQueueIds);
    }, delayMs);
}

/**
 * Recupera pendências da fila persistente e reenvia boas-vindas.
 * Deve ser chamado logo após a conexão WhatsApp ser estabelecida.
 *
 * @param {object} sock
 * @param {object} communityConfig
 */
async function recoverPendingWelcomes(sock, communityConfig) {
    try {
        console.log('[COMMUNITY-WELCOME] Verificando fila de boas-vindas pendentes...');
        const urls = [
            `${QUEUE_API_URL}&action=pending&min_age_minutes=10`,
            `${QUEUE_API_URL.replace('https://dev.viaEi.com', 'https://viaEi.com')}&action=pending&min_age_minutes=10`
        ];
        let data = null;
        for (const url of urls) {
            try {
                const res = await fetch(url);
                const rawText = await res.text();
                data = JSON.parse(rawText);
                break;
            } catch (err) {
                console.warn(`[COMMUNITY-WELCOME] Falha ao verificar fila pendente via ${url}:`, err.message);
            }
        }

        if (!data || !data.success || !data.items || data.items.length === 0) {
            console.log('[COMMUNITY-WELCOME] Nenhuma boas-vinda pendente encontrada.');
            return;
        }

        console.log(`[COMMUNITY-WELCOME] 🔄 ${data.items.length} entrada(s) pendente(s) encontradas. Reagendando...`);

        // Agrupa por grupo para evitar múltiplas mensagens no mesmo grupo
        const byGroup = {};
        for (const item of data.items) {
            if (!byGroup[item.group_jid]) byGroup[item.group_jid] = { participants: [], ids: [] };
            byGroup[item.group_jid].participants.push(item.participant_jid);
            byGroup[item.group_jid].ids.push(item.id);
        }

        // Dispara com delay escalonado entre grupos (5s) para não saturar
        let groupDelay = 5000;
        for (const [groupJid, group] of Object.entries(byGroup)) {
            const communityGroupEntry = Object.values(communityConfig.groups || {}).find(g => g.jid === groupJid);
            if (!communityGroupEntry || !communityGroupEntry.welcome_enabled) {
                console.log(`[COMMUNITY-WELCOME] Grupo ${groupJid} não configurado, descartando pendências.`);
                await updateQueueStatus(group.ids, 'sent');
                continue;
            }

            setTimeout(async () => {
                console.log(`[COMMUNITY-WELCOME] 🔄 Reenviando welcome para ${group.participants.length} participante(s) no grupo ${groupJid}`);
                await sendGroupedWelcome(sock, groupJid, group.participants, communityConfig, group.ids);
            }, groupDelay);

            groupDelay += 5000;
        }
    } catch (err) {
        console.error('[COMMUNITY-WELCOME] Erro ao recuperar fila pendente:', err.message);
    }
}

module.exports = { handleMessage, handleParticipant, recoverPendingWelcomes };
