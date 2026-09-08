<?php
$pageTitle = 'Alterar minha senha';
$activePage = 'perfil';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
redirectIfNotLoggedIn();

$erro = '';
$sucesso = '';

function perfilSenhaConfere(string $digitada, string $salva): bool
{
    if (password_verify($digitada, $salva)) {
        return true;
    }

    $salva = trim($salva);
    if (strlen($salva) === 32 && ctype_xdigit($salva)) {
        return hash_equals(strtolower($salva), md5($digitada));
    }

    return hash_equals($salva, $digitada);
}

function perfilContaAtual(PDO $db): array
{
    $tipoLogin = (string) ($_SESSION['tipo_login'] ?? '');

    if ($tipoLogin === 'sistema') {
        return ['usuarios_sistema', (int) ($_SESSION['usuario_id'] ?? 0)];
    }

    return ['funcionarios', (int) ($_SESSION['funcionario_id'] ?? $_SESSION['usuario_id'] ?? 0)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();

    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');

    if ($senhaAtual === '' || $novaSenha === '' || $confirmacao === '') {
        $erro = 'Preencha os três campos.';
    } elseif (strlen($novaSenha) < 6) {
        $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif (!hash_equals($novaSenha, $confirmacao)) {
        $erro = 'A confirmação não corresponde à nova senha.';
    } else {
        try {
            [$tabela, $id] = perfilContaAtual($db);
            if ($id <= 0) {
                throw new RuntimeException('Não foi possível identificar a conta autenticada.');
            }

            $db->beginTransaction();
            $consulta = $db->prepare("SELECT senha FROM {$tabela} WHERE id = :id FOR UPDATE");
            $consulta->execute([':id' => $id]);
            $senhaSalva = $consulta->fetchColumn();

            if (!is_string($senhaSalva) || !perfilSenhaConfere($senhaAtual, $senhaSalva)) {
                $db->rollBack();
                $erro = 'A senha atual está incorreta.';
            } elseif (perfilSenhaConfere($novaSenha, $senhaSalva)) {
                $db->rollBack();
                $erro = 'Escolha uma senha diferente da atual.';
            } else {
                $atualiza = $db->prepare("UPDATE {$tabela} SET senha = :senha WHERE id = :id");
                $atualiza->execute([
                    ':senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
                    ':id' => $id,
                ]);
                $db->commit();

                logAcao($db, 'UPDATE', $tabela, $id, 'Alterou a própria senha');
                $sucesso = 'Senha alterada com sucesso.';
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Erro ao alterar a própria senha: ' . $e->getMessage());
            $erro = 'Não foi possível alterar a senha agora. Tente novamente.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.password-page{max-width:680px;margin:0 auto}.password-card{border:0;border-radius:20px;box-shadow:0 12px 36px rgba(42,31,47,.08)}
.password-icon{width:52px;height:52px;display:grid;place-items:center;border-radius:16px;background:rgba(117,0,159,.1);color:#75009f;font-size:22px}
.password-field{position:relative}.password-field .form-control{padding-right:48px;min-height:48px}.password-toggle{position:absolute;right:6px;top:31px;width:38px;height:38px;border:0;background:transparent;color:#777;border-radius:50%}.password-hint{font-size:.84rem;color:var(--bs-secondary-color)}
</style>

<div class="password-page py-3 py-md-4">
    <div class="card password-card">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="password-icon"><i class="fas fa-shield-halved"></i></div>
                <div>
                    <h1 class="h4 mb-1">Proteja sua conta</h1>
                    <p class="text-body-secondary mb-0">Confirme a senha atual e escolha uma nova.</p>
                </div>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-danger" role="alert"><i class="fas fa-circle-exclamation me-2"></i><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="alert alert-success" role="status"><i class="fas fa-circle-check me-2"></i><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <form method="post" id="passwordChangeForm" novalidate>
                <?= csrfField() ?>
                <div class="mb-3 password-field">
                    <label class="form-label" for="senhaAtual">Senha atual</label>
                    <input class="form-control" id="senhaAtual" name="senha_atual" type="password" autocomplete="current-password" required autofocus>
                    <button class="password-toggle" type="button" aria-label="Mostrar senha"><i class="far fa-eye"></i></button>
                </div>
                <div class="mb-3 password-field">
                    <label class="form-label" for="novaSenha">Nova senha</label>
                    <input class="form-control" id="novaSenha" name="nova_senha" type="password" minlength="6" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" aria-label="Mostrar senha"><i class="far fa-eye"></i></button>
                    <div class="password-hint mt-1">Use pelo menos 6 caracteres.</div>
                </div>
                <div class="mb-4 password-field">
                    <label class="form-label" for="confirmarSenha">Confirme a nova senha</label>
                    <input class="form-control" id="confirmarSenha" name="confirmar_senha" type="password" minlength="6" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" aria-label="Mostrar senha"><i class="far fa-eye"></i></button>
                    <div class="invalid-feedback">As senhas precisam ser iguais.</div>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit"><i class="fas fa-key me-2"></i>Alterar senha</button>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = button.parentElement.querySelector('input');
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
        button.querySelector('i').className = visible ? 'far fa-eye' : 'far fa-eye-slash';
    });
});

document.getElementById('passwordChangeForm').addEventListener('submit', function (event) {
    const nova = document.getElementById('novaSenha');
    const confirmar = document.getElementById('confirmarSenha');
    confirmar.setCustomValidity(nova.value === confirmar.value ? '' : 'As senhas não correspondem.');
    if (!this.checkValidity()) {
        event.preventDefault();
        this.classList.add('was-validated');
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
