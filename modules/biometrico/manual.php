<?php
// modules/biometrico/manual.php - Manual de cadastro de biometria facial
$pageTitle = 'Manual de cadastro facial';
$activePage = 'biometrico_manual';
require_once '../../includes/auth_check.php';
requireAdmin();

// A rota entrega o manual em PDF para visualização ou download no navegador.
$manualPdf = __DIR__ . '/manual-facial.pdf';
if (is_file($manualPdf)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="manual-cadastro-facial.pdf"');
    header('Content-Length: ' . (string) filesize($manualPdf));
    readfile($manualPdf);
    exit;
}

require_once '../../includes/header.php';
?>

<style>
.manual-hero {
    background: linear-gradient(135deg, #0f172a 0%, #123b4a 54%, #059669 100%);
    border-radius: 24px;
    padding: 32px;
    color: #fff;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.manual-hero::after {
    content: '\f2bd';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    right: 34px;
    bottom: -28px;
    font-size: 150px;
    color: rgba(255,255,255,.1);
}
.manual-hero h1 { font-size: clamp(25px, 4vw, 36px); margin: 0 0 8px; font-weight: 800; }
.manual-hero p { max-width: 650px; margin: 0; color: rgba(255,255,255,.84); font-size: 16px; }
.manual-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(250px, 1fr); gap: 22px; align-items: start; }
.manual-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 18px; padding: 24px; box-shadow: var(--shadow-sm); }
.manual-card h2, .manual-card h3 { color: var(--text-primary); margin: 0 0 16px; font-weight: 800; }
.manual-card h2 { font-size: 21px; }
.manual-card h3 { font-size: 17px; }
.manual-card p { color: var(--text-secondary); line-height: 1.6; }
.manual-steps { display: grid; gap: 18px; }
.manual-step { display: grid; grid-template-columns: 42px 1fr; gap: 14px; }
.step-number { width: 38px; height: 38px; border-radius: 50%; background: #d1fae5; color: #047857; display: grid; place-items: center; font-weight: 800; }
.manual-step h3 { margin: 2px 0 5px; }
.manual-step p { margin: 0; }
.manual-list { margin: 0; padding-left: 20px; color: var(--text-secondary); line-height: 1.8; }
.manual-list li + li { margin-top: 4px; }
.manual-callout { border-radius: 14px; padding: 16px; margin-top: 18px; display: flex; gap: 12px; line-height: 1.5; }
.manual-callout i { margin-top: 3px; }
.manual-callout.tip { background: #ecfdf5; color: #065f46; }
.manual-callout.warning { background: #fff7ed; color: #9a3412; }
.manual-check { list-style: none; padding: 0; margin: 0; }
.manual-check li { display: flex; gap: 10px; color: var(--text-secondary); line-height: 1.5; padding: 9px 0; border-bottom: 1px solid var(--border-color); }
.manual-check li:last-child { border-bottom: 0; }
.manual-check i { color: #10b981; margin-top: 4px; }
.manual-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
.manual-faq details { border-bottom: 1px solid var(--border-color); padding: 13px 0; }
.manual-faq details:last-child { border-bottom: 0; }
.manual-faq summary { cursor: pointer; color: var(--text-primary); font-weight: 700; }
.manual-faq p { margin: 9px 0 0; font-size: 14px; }
@media (max-width: 820px) { .manual-grid { grid-template-columns: 1fr; } .manual-hero { padding: 25px; } .manual-hero::after { right: -10px; font-size: 110px; } }
</style>

<div class="manual-hero" aria-labelledby="manual-title">
    <h1 id="manual-title"><i class="fas fa-face-smile"></i> Manual: cadastro facial</h1>
    <p>Siga este passo a passo para registrar ou atualizar a biometria facial de um funcionário e permitir o registro de ponto por reconhecimento facial.</p>
</div>

<div class="manual-grid">
    <main>
        <section class="manual-card" aria-labelledby="steps-title">
            <h2 id="steps-title"><i class="fas fa-list-check"></i> Como cadastrar</h2>
            <div class="manual-steps">
                <div class="manual-step"><div class="step-number">1</div><div><h3>Prepare o ambiente</h3><p>Use um computador ou celular com câmera, permita o acesso à câmera no navegador e escolha um local bem iluminado, sem luz forte atrás do funcionário.</p></div></div>
                <div class="manual-step"><div class="step-number">2</div><div><h3>Abra o cadastro facial</h3><p>No menu lateral, acesse <strong>Biometria &gt; Cadastrar facial</strong>. Localize o funcionário ativo e clique em <strong>Cadastrar</strong> ou <strong>Atualizar</strong>.</p></div></div>
                <div class="manual-step"><div class="step-number">3</div><div><h3>Posicione o funcionário</h3><p>Peça que ele fique de frente para a câmera, com o rosto centralizado, olhos abertos e expressão neutra. Retire bonés, máscaras e óculos escuros.</p></div></div>
                <div class="manual-step"><div class="step-number">4</div><div><h3>Faça a captura</h3><p>Aguarde a orientação da tela e mantenha o rosto parado até a captura ser concluída. Não feche a página nem troque de aba durante o processo.</p></div></div>
                <div class="manual-step"><div class="step-number">5</div><div><h3>Confirme o resultado</h3><p>Verifique a mensagem de sucesso. Ao voltar para a lista, o funcionário deverá aparecer com o status <strong>Facial cadastrada</strong>. Um novo cadastro substitui o anterior.</p></div></div>
            </div>
            <div class="manual-actions">
                <a href="<?php echo htmlspecialchars($baseUrl . '/modules/biometrico/cadastrar?tipo=facial', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-camera"></i> Ir para cadastrar facial</a>
                <a href="<?php echo htmlspecialchars($baseUrl . '/modules/biometrico/index', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar para biometria</a>
            </div>
        </section>

        <section class="manual-card manual-faq" style="margin-top:22px;" aria-labelledby="faq-title">
            <h2 id="faq-title"><i class="fas fa-circle-question"></i> Se algo não funcionar</h2>
            <details><summary>A câmera não aparece ou está bloqueada</summary><p>Clique no ícone de câmera/cadeado na barra de endereço, permita a câmera e recarregue a página. Feche outros aplicativos que possam estar usando a câmera.</p></details>
            <details><summary>A captura não reconhece o rosto</summary><p>Melhore a iluminação, limpe a lente, aproxime o rosto e retire acessórios que o cubram. Tente novamente olhando diretamente para a câmera.</p></details>
            <details><summary>O status continua como pendente</summary><p>Confira se a mensagem de sucesso foi exibida e atualize a lista. Se persistir, verifique a conexão e repita o cadastro do funcionário.</p></details>
        </section>
    </main>

    <aside>
        <section class="manual-card">
            <h2><i class="fas fa-clipboard-check"></i> Antes de começar</h2>
            <ul class="manual-check">
                <li><i class="fas fa-circle-check"></i><span>Funcionário presente e identificado</span></li>
                <li><i class="fas fa-circle-check"></i><span>Câmera funcionando e autorizada</span></li>
                <li><i class="fas fa-circle-check"></i><span>Rosto visível e sem obstruções</span></li>
                <li><i class="fas fa-circle-check"></i><span>Iluminação uniforme e frontal</span></li>
                <li><i class="fas fa-circle-check"></i><span>Conexão estável com o sistema</span></li>
            </ul>
            <div class="manual-callout tip"><i class="fas fa-lightbulb"></i><span>Para obter um bom reconhecimento no ponto, cadastre a face nas mesmas condições em que o funcionário normalmente registra o ponto.</span></div>
        </section>
        <section class="manual-card" style="margin-top:22px;">
            <h2><i class="fas fa-shield-halved"></i> Uso responsável</h2>
            <p>Faça o cadastro com a presença e ciência do funcionário. Mantenha os dados biométricos protegidos e atualize o cadastro somente quando necessário.</p>
            <div class="manual-callout warning"><i class="fas fa-triangle-exclamation"></i><span>Não cadastre outra pessoa no perfil do funcionário. O cadastro incorreto pode impedir o registro de ponto.</span></div>
        </section>
    </aside>
</div>

<?php require_once '../../includes/footer.php'; ?>
