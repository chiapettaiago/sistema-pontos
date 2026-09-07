# PontoFácil

Aplicação web em PHP para controle de ponto, gestão de funcionários, relatórios, solicitações e autenticação por reconhecimento facial.

## Recursos

- Login por e-mail e senha ou reconhecimento facial com FaceAPI.js.
- Cadastro de funcionários, filiais, cargos e departamentos.
- Registro de ponto, extratos, escalas, solicitações e relatórios.
- Perfis de acesso: funcionário, supervisor, gestor, administrador e super administrador.
- Rotas sem extensão `.php` e proteção centralizada de páginas autenticadas.
- Tema claro/escuro, interface responsiva e navegação lateral recolhível.

## Requisitos

- Apache com `mod_rewrite` habilitado.
- PHP 8.1+ com PDO MySQL.
- MySQL/MariaDB 10.6+.
- Navegador recente com acesso à câmera para o reconhecimento facial.
- Opcional: Podman para iniciar o banco local em container.

## Instalação

1. Disponibilize o projeto no diretório servido pelo Apache. Exemplo:

   ```text
   /var/www/projects/public/sistema-pontos
   ```

2. Copie o arquivo de ambiente e preencha as credenciais do banco:

   ```bash
   cp .env.example .env
   ```

3. Ajuste o `.env`. A aplicação lê a conexão somente desse arquivo:

   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=ponto
   DB_USER=ponto
   DB_PASS=uma-senha-segura
   DB_CHARSET=utf8mb4
   DB_CONNECT_TIMEOUT=5
   ```

4. Garanta que o Apache possa ler o `.env` e escrever nas pastas de upload:

   ```bash
   setfacl -m u:apache:r-- .env
   setfacl -m u:apache:rwx uploads uploads/funcionarios
   setfacl -d -m u:apache:rwx uploads/funcionarios
   ```

5. Abra a aplicação em:

   ```text
   http://localhost/sistema-pontos/
   ```

## Banco MySQL local com Podman

O script abaixo cria o container `ponto-mysql`, usando as credenciais já definidas no `.env` e o backup inicial do projeto:

```bash
php scripts/start-local-mysql.php
```

Verifique o estado do banco:

```bash
podman inspect --format '{{.State.Health.Status}}' ponto-mysql
```

Também existe um `compose.yaml` para ambientes que tenham um provedor Compose instalado. O projeto não exige Docker Compose para funcionar.

## Reconhecimento facial

1. Entre com e-mail e senha.
2. Acesse **Meu cadastro** ou **Funcionários** e abra o cadastro facial do funcionário.
3. Autorize a câmera, capture o rosto e clique em **Salvar Cadastro**.
4. Saia do sistema e use **Reconhecer e entrar** na tela inicial.

O reconhecimento usa modelos locais em `assets/models`; não depende de um serviço externo. A foto de perfil não substitui a biometria: é necessário concluir a captura facial para gravar o descritor de reconhecimento.

Para a câmera funcionar, use `localhost` durante o desenvolvimento ou HTTPS em outros domínios e conceda a permissão solicitada pelo navegador.

## Rotas principais

| Rota | Descrição |
| --- | --- |
| `/` | Entrada da aplicação; encaminha ao login ou dashboard. |
| `/login` | Login facial e login por e-mail/senha. |
| `/modules/ponto/ponto` | Registro de ponto. |
| `/modules/funcionarios` | Gestão de funcionários. |
| `/modules/funcionarios/cadastro_facial?id={id}` | Cadastro da biometria facial. |
| `/logout` | Encerra a sessão. |

URLs antigas com `.php` são redirecionadas para a rota correspondente sem extensão quando acessadas pelo navegador.

## Estrutura resumida

```text
api/        Endpoints JSON, incluindo login facial
assets/     CSS, JavaScript, ícones e modelos FaceAPI
config/     Adaptadores e configurações de banco
includes/   Autenticação, roteamento e layout compartilhado
modules/    Telas e funcionalidades do sistema
scripts/    Utilitários de desenvolvimento
uploads/    Fotos de funcionários e capturas
```

## Diagnóstico rápido

| Situação | Verificação |
| --- | --- |
| Serviço de banco indisponível | Confira as chaves do `.env` e o healthcheck do container. |
| Erro 500 | Consulte o log do PHP-FPM/Apache e confirme se o Apache lê o `.env`. |
| Foto não salva | Verifique permissão de escrita do Apache em `uploads/funcionarios`. |
| Tela facial não inicia | Confirme acesso à câmera, arquivos em `assets/models` e use `Ctrl+F5`. |
| Rota retorna 404 | Acesse pela URL com o prefixo `/sistema-pontos`. |

## Segurança

- Não versione o arquivo `.env`.
- Troque senhas padrão antes de usar fora do desenvolvimento.
- Mantenha `includes/` e `config/` sem acesso público direto.
- Use HTTPS em ambientes publicados, especialmente para a câmera e dados biométricos.
