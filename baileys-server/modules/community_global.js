/**
 * Módulo da Comunidade Global.
 * Chamado por bot.js somente quando isCommunityGroup === true.
 */

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

const pendingWelcomes = {};

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
            timer: null
        };
    }

    // Adiciona os novos participantes à fila
    participants.forEach(p => pendingWelcomes[groupJid].participants.add(p));

    // Se já há um temporizador rodando, apenas avisa que foi adicionado à fila e retorna
    if (pendingWelcomes[groupJid].timer) {
        console.log(`[COMMUNITY-WELCOME] Adicionando ${participants.length} pessoa(s) à fila de welcome do grupo ${groupJid}.`);
        return;
    }

    // Se não há temporizador, inicia um novo com o delay aleatório
    const delayMs = Math.floor(Math.random() * (180000 - 60000 + 1)) + 60000;
    console.log(`[COMMUNITY-WELCOME] Iniciando delay de ${Math.round(delayMs / 1000)}s para agrupar e enviar welcome para ${groupJid}...`);

    pendingWelcomes[groupJid].timer = setTimeout(async () => {
        // Extrai todos os participantes acumulados
        const currentParticipants = Array.from(pendingWelcomes[groupJid].participants);
        
        // Limpa a fila e o timer para os próximos que entrarem
        pendingWelcomes[groupJid].participants.clear();
        pendingWelcomes[groupJid].timer = null;

        if (currentParticipants.length === 0) return;

        console.log(`[COMMUNITY-WELCOME] Disparando welcome agrupado para ${currentParticipants.length} participante(s) no grupo ${groupJid}...`);

        try {
            const apiUrl = `https://dev.viaEi.com/bot_whatsapp/community_welcome_api.php?token=83x9aZ2pLQw1&group_jid=${encodeURIComponent(groupJid)}`;
            const res = await fetch(apiUrl);
            const data = await res.json();

            if (!data.enabled) {
                console.log(`[COMMUNITY-WELCOME] Welcome disabled for group ${groupJid} (API returned enabled=false)`);
                return;
            }

            const isEnglishGroup = data.is_english_group;
            const introTarget    = data.intro_target;
            const introEn        = data.intro_en;
            const questions      = data.questions || [];

            // Build mentions list (used in intro placeholder)
            const mentionsList = currentParticipants.map(jid => `@${jid.split('@')[0]}`).join(' ');

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

            await sock.sendMessage(groupJid, { text: finalMsg, mentions: currentParticipants });
            console.log(`[COMMUNITY-WELCOME] ✅ Sent grouped welcome in group ${groupJid}`);

        } catch (err) {
            console.error(`[COMMUNITY-WELCOME] ❌ Error sending grouped welcome message in group ${groupJid}:`, err);
        }
    }, delayMs);
}

module.exports = { handleMessage, handleParticipant };
