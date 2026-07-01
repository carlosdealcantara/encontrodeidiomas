const { default: makeWASocket, useMultiFileAuthState, Browsers, makeCacheableSignalKeyStore } = require('@whiskeysockets/baileys');
const pino = require('pino');

async function start() {
    console.log('Inicializando...');
    const { state, saveCreds } = await useMultiFileAuthState('./auth_info_baileys');
    
    const sock = makeWASocket({
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' }))
        },
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: Browsers.macOS('Chrome'),
        mobile: false,
        getMessage: async () => ({ conversation: '' })
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect } = update;
        if (connection === 'close') {
            console.log('Conexão fechada:', lastDisconnect?.error?.output?.statusCode);
        } else if (connection === 'open') {
            console.log('=============================================');
            console.log('✅ CONECTADO COM SUCESSO NO WINDOWS LOCAL!');
            console.log('A sessão foi validada e salva.');
            console.log('=============================================');
            setTimeout(() => process.exit(0), 4000);
        }
    });

    if (!sock.authState.creds.registered) {
        const phoneNumber = process.argv[2];
        if (!phoneNumber) {
            console.error('Número de telefone não fornecido.');
            process.exit(1);
        }
        console.log(`Solicitando código para ${phoneNumber}...`);
        setTimeout(async () => {
            try {
                let code = await sock.requestPairingCode(phoneNumber);
                code = code?.match(/.{1,4}/g)?.join('-') || code;
                console.log('\n=============================================');
                console.log('📱 SEU CÓDIGO DE PAREAMENTO É:', code);
                console.log('Digite no WhatsApp do celular (Aparelhos Conectados -> Vincular com Número)');
                console.log('=============================================\n');
            } catch (err) {
                console.error('Erro ao pedir codigo:', err);
            }
        }, 2000);
    } else {
        console.log('Sessão local já existe, aguardando conexão...');
    }
}

start();
