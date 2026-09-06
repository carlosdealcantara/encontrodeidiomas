<?php
require_once 'config.php';
$page_title = "Política de Privacidade";
require_once 'includes/header.php';
?>

<div class="container mt-5 pt-5 pb-5">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <h1 class="mb-4">Política de Privacidade</h1>
            <p class="text-muted">Última atualização: <?php echo date('d/m/Y'); ?></p>

            <section class="mt-4">
                <h2>1. Introdução</h2>
                <p>O <strong>Encontro de Idiomas</strong> (também referido internamente como "Ei Odysee Worker") tem o compromisso de proteger a privacidade e os dados pessoais de seus usuários. Esta Política de Privacidade explica como coletamos, usamos, armazenamos e protegemos as informações quando você utiliza nossos serviços, em conformidade com as exigências do Google e leis de proteção de dados aplicáveis.</p>
            </section>

            <section class="mt-4">
                <h2>2. Uso de Dados do Google (Google Workspace / Drive API)</h2>
                <p>Nossas ferramentas de automação interna (Ei Odysee Worker) utilizam a API do Google Drive para gerenciar gravações de encontros online. O uso e a transferência de informações recebidas das APIs do Google para qualquer outro aplicativo obedecerão à <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank">Política de Dados de Usuário dos Serviços de API do Google</a>, incluindo os requisitos de <i>Uso Limitado</i>.</p>
                
                <h4>Como acessamos e usamos os dados:</h4>
                <ul>
                    <li><strong>Escopo de Acesso:</strong> Solicitamos acesso ao Google Drive (<code>https://www.googleapis.com/auth/drive</code>) <strong>exclusivamente de contas administrativas do próprio projeto</strong>. Não solicitamos nem acessamos dados do Google Drive de usuários comuns ou visitantes do site.</li>
                    <li><strong>Finalidade:</strong> O acesso ao Drive é utilizado estritamente para automatizar o download de gravações de vídeo e arquivos de texto das reuniões (gerados pelo Google Meet) e movê-los para as pastas de arquivo adequadas.</li>
                    <li><strong>Armazenamento e Compartilhamento:</strong> Não armazenamos os arquivos de vídeo em nossos servidores de forma permanente (apenas durante o processamento temporário para backup e publicação) e <strong>não compartilhamos</strong> seus dados pessoais ou dados do Google Drive com terceiros para fins publicitários ou comerciais.</li>
                </ul>
            </section>

            <section class="mt-4">
                <h2>3. Coleta de Dados Gerais</h2>
                <p>Para o funcionamento da plataforma Encontro de Idiomas (inscrição em eventos, recebimento de links e notificações), coletamos as seguintes informações fornecidas voluntariamente pelos usuários:</p>
                <ul>
                    <li>Nome</li>
                    <li>Endereço de E-mail</li>
                    <li>Número de Telefone (WhatsApp)</li>
                    <li>Nível de fluência nos idiomas de interesse</li>
                </ul>
                <p>Esses dados são utilizados exclusivamente para organização dos encontros, controle de presença e comunicação direta referente aos eventos que o usuário demonstrou interesse.</p>
            </section>

            <section class="mt-4">
                <h2>4. Segurança das Informações</h2>
                <p>Adotamos medidas de segurança técnicas e organizacionais adequadas para proteger os dados pessoais contra acesso, alteração, divulgação ou destruição não autorizados. Os dados são armazenados em servidores seguros e o acesso às integrações de API (como o Google Drive) é protegido por tokens de autenticação criptografados.</p>
            </section>

            <section class="mt-4">
                <h2>5. Contato</h2>
                <p>Se você tiver alguma dúvida sobre esta Política de Privacidade ou sobre como lidamos com seus dados, entre em contato conosco através do e-mail: <strong>encontrodeidiomas@gmail.com</strong>.</p>
            </section>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
