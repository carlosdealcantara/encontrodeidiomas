const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const path = require('path');

console.log('Iniciando navegador Chrome local...');
console.log('Uma janela do Chrome vai abrir. NAO feche ela.');
console.log('');

const client = new Client({
    authStrategy: new LocalAuth({
        dataPath: path.join(__dirname, 'wwebjs_auth')
    }),
    puppeteer: {
        headless: false,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    }
});

client.on('qr', (qr) => {
    console.log('');
    console.log('=== ESCANEIE O QR CODE ABAIXO NO SEU WHATSAPP ===');
    qrcode.generate(qr, { small: true });
    console.log('No celular: WhatsApp > Menu (3 pontos) > Aparelhos Conectados > Conectar um aparelho');
    console.log('');
});

client.on('authenticated', () => {
    console.log('');
    console.log('[INFO] Autenticado! Aguardando confirmacao final...');
});

client.on('auth_failure', (msg) => {
    console.error('[ERRO] Falha na autenticacao:', msg);
    process.exit(1);
});

client.on('ready', () => {
    console.log('');
    console.log('============================================');
    console.log('SUCESSO! WhatsApp conectado localmente!');
    console.log('A sessao foi salva na pasta: wwebjs_auth/');
    console.log('Pode fechar esta janela do Chrome agora.');
    console.log('============================================');
    console.log('');
    console.log('PROXIMO PASSO: Execute o comando de transferencia');
    console.log('da pasta wwebjs_auth para a VPS (Fase 2 do plano).');
    
    setTimeout(() => {
        client.destroy();
        process.exit(0);
    }, 5000);
});

client.initialize();
