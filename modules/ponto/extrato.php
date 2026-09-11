<?php
// modules/ponto/extrato.php - Meu Extrato (COM STATUS CORRIGIDO)
session_start();

// Verificar se está logado
if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Meu Extrato';
$activePage = 'extrato';

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

if (!$funcionario_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

if (!$funcionario_id) {
    echo "<div style='text-align: center; padding: 50px;'>
            <h2>Perfil não encontrado</h2>
            <p>Contacte o administrador.</p>
            <a href='../../logout.php'>Sair</a>
          </div>";
    exit;
}

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

// Parâmetros de filtro. O formulário envia mês e ano em campos separados.
$mes_atual = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano_atual = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mes_atual = ($mes_atual >= 1 && $mes_atual <= 12) ? $mes_atual : (int) date('m');
$ano_atual = ($ano_atual >= 2000 && $ano_atual <= (int) date('Y') + 1) ? $ano_atual : (int) date('Y');

// Buscar cada batida individualmente para exibir horário, origem e localização.
$query = "SELECT id, tipo, data_hora, latitude, longitude, origem
          FROM pontos
          WHERE funcionario_id = :id
            AND MONTH(data_hora) = :mes
            AND YEAR(data_hora) = :ano
          ORDER BY data_hora DESC";

$stmt = $db->prepare($query);
$stmt->execute([
    ':id' => $funcionario_id,
    ':mes' => $mes_atual,
    ':ano' => $ano_atual
]);
$pontos = $stmt->fetchAll();

require_once '../../includes/header.php';
?>
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-list-alt me-2 text-primary"></i>Meu Extrato de Ponto</h1>
        <p class="text-muted">Histórico completo dos seus registros</p>
    </div>
</div>

<!-- Filtros -->
<div class="card pf-table-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-sm-4 col-lg-3">
                <label class="form-label">Mês</label>
                <select name="mes" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($mes_atual == $m) ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-sm-4 col-lg-2">
                <label class="form-label">Ano</label>
                <select name="ano" class="form-select">
                    <?php for ($a = date('Y'); $a >= date('Y')-3; $a--): ?>
                    <option value="<?php echo $a; ?>" <?php echo ($ano_atual == $a) ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card pf-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Horário</th>
                        <th class="d-none d-md-table-cell">Origem</th>
                        <th>Endereço da batida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pontos as $ponto): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($ponto['data_hora'])); ?></td>
                        <td>
                            <?php
                            $tipoMap = ['entrada'=>['Entrada','success'],'saida_almoco'=>['Saída Almoço','warning'],'volta_almoco'=>['Volta Almoço','info'],'saida'=>['Saída','danger']];
                            $t = $tipoMap[$ponto['tipo']] ?? [ucfirst($ponto['tipo']),'secondary'];
                            ?>
                            <span class="badge bg-<?php echo $t[1]; ?>"><?php echo $t[0]; ?></span>
                        </td>
                        <td class="fw-semibold"><?php echo date('H:i', strtotime($ponto['data_hora'])); ?></td>
                        <td class="d-none d-md-table-cell text-muted"><?php echo htmlspecialchars(($ponto['origem'] ?? 'web') === 'totem' ? 'Quiosque' : ($ponto['origem'] ?? 'web')); ?></td>
                        <td>
                            <?php if (is_numeric($ponto['latitude']) && is_numeric($ponto['longitude'])): ?>
                                <?php $coordenadas = $ponto['latitude'] . ',' . $ponto['longitude']; ?>
                                <div class="endereco-batida"
                                     data-latitude="<?php echo htmlspecialchars((string) $ponto['latitude'], ENT_QUOTES, 'UTF-8'); ?>"
                                     data-longitude="<?php echo htmlspecialchars((string) $ponto['longitude'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="button" class="btn btn-sm btn-outline-primary consultar-endereco">
                                        <i class="fas fa-map-marker-alt me-1"></i>Consultar endereço
                                    </button>
                                    <a class="d-block small mt-1" href="https://www.google.com/maps?q=<?php echo rawurlencode($coordenadas); ?>" target="_blank" rel="noopener noreferrer">Ver no mapa</a>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Não informada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pontos)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-clock fa-2x d-block mb-2 opacity-25"></i>Nenhum registro neste período
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.consultar-endereco').forEach(button => {
    button.addEventListener('click', async () => {
        const container = button.closest('.endereco-batida');
        const latitude = container.dataset.latitude;
        const longitude = container.dataset.longitude;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Localizando';

        try {
            const url = new URL('https://nominatim.openstreetmap.org/reverse');
            url.search = new URLSearchParams({format: 'jsonv2', lat: latitude, lon: longitude, zoom: '18', addressdetails: '1'});
            const response = await fetch(url, {headers: {'Accept-Language': 'pt-BR,pt'}});
            if (!response.ok) throw new Error('Falha ao consultar endereço');
            const result = await response.json();
            if (!result.display_name) throw new Error('Endereço não encontrado');

            button.replaceWith(document.createTextNode(result.display_name));
        } catch (error) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-rotate-right me-1"></i>Tentar novamente';
            button.title = error.message;
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
