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

/**
 * Trata entrada de novo participante em grupo da Comunidade Global.
 * Busca intro e perguntas na API PHP e envia mensagem de boas-vindas.
 *
 * @param {object} sock            - instância Baileys
 * @param {string} groupJid        - JID do grupo onde alguém entrou
 * @param {string[]} participants  - JIDs dos novos participantes
 * @param {object} communityConfig - config já carregado pelo bot.js
 */
async function handleParticipant(sock, groupJid, participants, communityConfig) {
    const communityGroupEntry = Object.values(communityConfig.groups || {}).find(g => g.jid === groupJid);
    if (!communityGroupEntry || !communityGroupEntry.welcome_enabled) return;

    console.log(`[COMMUNITY-WELCOME] New participant(s) in community group: ${groupJid} — fetching welcome content...`);

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
        const mentionsList = participants.map(jid => `@${jid.split('@')[0]}`).join(' ');

        // Build intro text (Portuguese/other: target translation; English group: original)
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

        // Assemble final message using general template (or fallback)
        const tplGeneral = communityConfig.templates?.community_welcome_general ||
            '{intro_text}\n\nWe\'d love to get to know you! Tell us:\n\n{questions_text}';

        const finalMsg = tplGeneral
            .replace('{intro_text}', introText.trim())
            .replace('{questions_text}', questionsText.trim());

        await sock.sendMessage(groupJid, { text: finalMsg, mentions: participants });

        console.log(`[COMMUNITY-WELCOME] ✅ Sent welcome to ${participants.length} participant(s) in group ${groupJid}`);

    } catch (err) {
        console.error(`[COMMUNITY-WELCOME] ❌ Error sending welcome message in group ${groupJid}:`, err);
    }
}

module.exports = { handleMessage, handleParticipant };
