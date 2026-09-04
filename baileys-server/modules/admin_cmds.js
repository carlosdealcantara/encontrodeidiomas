const fs = require('fs');
const path = require('path');
const { downloadMediaMessage } = require('@whiskeysockets/baileys');

/**
 * Comandos globais — funcionam em qualquer grupo (Mentoria ou Community).
 * Chamado por bot.js antes do roteamento por módulo.
 *
 * ctx: { sock, msg, groupJid, globalText, globalRealMsg,
 *        isMasterAdmin, msgId, processedMessageIds, dataDir }
 */
async function handle(ctx) {
    const { sock, msg, groupJid, globalText, globalRealMsg, isMasterAdmin, msgId, processedMessageIds, dataDir } = ctx;

    // === PÍLULAS DE INGLÊS: CAPTURA DE ÁUDIO VIA COMANDO (GLOBAL) ===
    if (isMasterAdmin && !processedMessageIds.has(msgId) && globalText.toLowerCase() === '!pill') {
        const rawQuoted = globalRealMsg?.extendedTextMessage?.contextInfo?.quotedMessage;
        const unwrappedQuoted = rawQuoted?.ephemeralMessage?.message || rawQuoted?.viewOnceMessage?.message || rawQuoted;

        const quotedAudio = unwrappedQuoted?.audioMessage || unwrappedQuoted?.pttMessage;

        if (quotedAudio) {
            processedMessageIds.add(msgId);
            console.log(`[PÍLULAS] Comando !pill detectado no grupo ${groupJid}. Baixando áudio...`);
            try {
                await sock.sendMessage(groupJid, { react: { text: '💊', key: msg.key } });

                const fakeMsg = { key: msg.key, message: unwrappedQuoted };
                const buffer = await downloadMediaMessage(
                    fakeMsg,
                    'buffer',
                    {},
                    { logger: sock.logger, reuploadRequest: sock.updateMediaMessage }
                );
                const fileName = `pilula_${Date.now()}.ogg`;
                const audiosDir = path.join(dataDir, 'audios_pilulas');
                if (!fs.existsSync(audiosDir)) fs.mkdirSync(audiosDir, { recursive: true });
                const filePath = path.join(audiosDir, fileName);
                fs.writeFileSync(filePath, buffer);
                console.log(`[PÍLULAS] Áudio salvo em: ${filePath}`);

                const res = await fetch('https://dev.viaEi.com/bot_whatsapp/api_pilulas_webhook.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        apikey: 'SenhaMeetups2026',
                        action: 'new_audio',
                        audio_path: `baileys-server/data/audios_pilulas/${fileName}`
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
                await sock.sendMessage(groupJid, { react: { text: '📖', key: msg.key } });

                const fakeMsgWord = { key: msg.key, message: unwrappedQuotedWord };
                const bufferWord = await downloadMediaMessage(
                    fakeMsgWord,
                    'buffer',
                    {},
                    { logger: sock.logger, reuploadRequest: sock.updateMediaMessage }
                );

                const fileNameWord = `word_${wordNumber}_${Date.now()}.ogg`;
                const ebookAudiosDir = path.join(dataDir, 'audios_ebook');
                if (!fs.existsSync(ebookAudiosDir)) fs.mkdirSync(ebookAudiosDir, { recursive: true });
                const filePathWord = path.join(ebookAudiosDir, fileNameWord);
                fs.writeFileSync(filePathWord, bufferWord);
                console.log(`[E-BOOK] Áudio salvo em: ${filePathWord}`);

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
                    await sock.sendMessage(groupJid, { text: `✅ Palavra *#${wordNumber}* ${actionLabel} com sucesso!` }, { quoted: msg });
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
}

module.exports = { handle };
