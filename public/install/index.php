<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);
if (!is_file($basePath . '/src/bootstrap.php')) {
    $basePath = dirname(__DIR__, 2);
}
require $basePath . '/src/bootstrap.php';
require BASE_PATH . '/src/Install.php';

Security::headers();
header('Cache-Control: no-store');

Installer::startSession();

$installed = Installer::isInstalled();
$draft = Installer::draft();
$error = '';
$installCompleted = false;
$installRemoved = false;
$installedCompany = '';
$action = (string) ($_POST['action'] ?? '');
$step = max(1, min(3, (int) ($_GET['step'] ?? 1)));

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Installer::checkCsrf();

        if ($action === 'db') {
            $db = Installer::testDatabase($_POST);
            $draft = Installer::draft();
            $draft['db'] = $db;
            unset($draft['admin']);
            Installer::saveDraft($draft);
            header('Location: /install/?step=2', true, 303);
            exit;
        }

        if ($action === 'admin') {
            if (empty($draft['db'])) {
                header('Location: /install/?step=1', true, 303);
                exit;
            }
            $name = clean_string($_POST['admin_name'] ?? '', 120);
            $email = text_lower(clean_string($_POST['admin_email'] ?? '', 190));
            $password = string_value($_POST['admin_password'] ?? '');
            $confirm = string_value($_POST['admin_password_confirm'] ?? '');
            $appName = clean_string($_POST['app_name'] ?? '', 80);

            $fields = [];
            if ($name === '') {
                $fields[] = 'El nombre del administrador es obligatorio.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fields[] = 'Introduce un correo válido para el administrador.';
            }
            $policyError = Security::passwordPolicyError($password, $email, $name);
            if ($policyError !== null) {
                $fields[] = $policyError;
            }
            if ($password !== $confirm) {
                $fields[] = 'Las contraseñas no coinciden.';
            }
            if ($fields !== []) {
                throw new InstallerException(implode(' ', $fields));
            }

            $draft['app_name'] = $appName !== '' ? $appName : 'Mi CRM';
            $draft['admin'] = [
                'name' => $name,
                'email' => $email,
                'password_hash' => Security::hashPassword($password),
            ];
            Installer::saveDraft($draft);
            header('Location: /install/?step=3', true, 303);
            exit;
        }

        if ($action === 'install') {
            if (empty($draft['db']) || empty($draft['admin'])) {
                header('Location: /install/?step=1', true, 303);
                exit;
            }
            $installedCompany = (string) ($draft['app_name'] ?? 'Mi CRM');
            Installer::install($draft['db'], $draft['admin'], $installedCompany);
            Installer::clearDraft();
            $installCompleted = true;
            $installRemoved = Installer::removeInstallerDirectory(__DIR__);
        }

        throw new InstallerException('Acción de instalación no válida.');
    } catch (InstallerException $e) {
        $error = $e->getMessage();
        $draft = Installer::draft();
    } catch (Throwable $e) {
        error_log('[CRM install] ' . $e->getMessage());
        $error = 'No se pudo completar la instalación. Revisa los permisos de config/ y data/ e inténtalo de nuevo.';
        $draft = Installer::draft();
    }
}

$hasDb = !empty($draft['db']);
$hasAdmin = !empty($draft['admin']);
if ($step === 2 && !$hasDb) {
    $step = 1;
}
if ($step === 3 && (!$hasDb || !$hasAdmin)) {
    $step = $hasDb ? 2 : 1;
}

$driver = (string) ($draft['db']['driver'] ?? 'sqlite');
$sqlitePath = (string) ($draft['db']['path'] ?? Installer::defaultSqlitePath());
$sqliteDisplay = str_starts_with($sqlitePath, BASE_PATH . DIRECTORY_SEPARATOR)
    ? substr($sqlitePath, strlen(BASE_PATH) + 1)
    : $sqlitePath;

$csrf = Installer::csrf();
$appNameValue = install_input('app_name', (string) ($draft['app_name'] ?? ''));
$adminNameValue = install_input('admin_name', (string) ($draft['admin']['name'] ?? ''));
$adminEmailValue = install_input('admin_email', (string) ($draft['admin']['email'] ?? ''));
$sqliteDisplay = install_input('sqlite_path', $sqliteDisplay);

function install_input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? $value : $default;
}

$mysqlHost = install_input('mysql_host', (string) ($draft['db']['host'] ?? 'localhost'));
$mysqlPort = install_input('mysql_port', (string) ($draft['db']['port'] ?? '3306'));
$mysqlDatabase = install_input('mysql_database', (string) ($draft['db']['database'] ?? ''));
$mysqlUsername = install_input('mysql_username', (string) ($draft['db']['username'] ?? ''));
$driverValue = install_input('driver', $driver) === 'mysql' ? 'mysql' : 'sqlite';

if ($installCompleted) {
    $pageTitle = 'Instalación completada';
    $heading = '¡CRM instalado!';
    $subheading = 'La instalación ha terminado correctamente. Ya puedes acceder al CRM con el usuario administrador que has creado.';
} elseif ($installed) {
    $pageTitle = 'CRM ya instalado';
    $heading = 'El CRM ya está instalado';
    $subheading = 'La configuración ya existe en este servidor. Puedes acceder con tu usuario administrador.';
} else {
    $pageTitle = 'Instalación';
    $heading = 'Pon en marcha tu CRM';
    $subheading = 'Tres pasos: elige la base de datos, configura tu empresa y crea el usuario administrador.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#FBF9F5">
<title><?= e($pageTitle) ?> · GandiaTech CRM</title>
<link rel="icon" type="image/svg+xml" href="/app/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
<style>
    :root {
        --font-sans: "Instrument Sans", system-ui, "Segoe UI", Arial, sans-serif;
        --font-display: "Bricolage Grotesque", "Trebuchet MS", Arial, sans-serif;
        --color-cream: #fbf9f5;
        --color-ink: #1e2230;
        --color-muted: #5e6472;
        --color-line: #e9e4da;
        --color-accent: #ff5a36;
        --color-accent-dark: #c7381a;
        --color-accent-soft: #ffe6de;
        --color-sky: #dcebff;
        --color-mint: #d9f2e4;
        --color-sand: #fff0cf;
        --radius: 24px;
    }
    *, *:before, *:after { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
        background: var(--color-cream);
        color: var(--color-ink);
        font-family: var(--font-sans);
        font-size: 14px;
        -webkit-font-smoothing: antialiased;
    }
    a { color: inherit; }
    code { background: #f1efea; border-radius: 5px; padding: 1px 5px; font-size: 11px; }
    .install-shell { min-height: 100vh; padding: 32px 16px 56px; }
    .install-wrap { width: 100%; max-width: 780px; margin: 0 auto; }
    .install-brand { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 26px; }
    .install-brand img { display: block; height: 34px; width: auto; max-width: 200px; object-fit: contain; }
    .install-brand-text { font-family: var(--font-display); font-size: 20px; font-weight: 800; letter-spacing: -.02em; }
    .install-brand-text em { font-style: normal; color: var(--color-accent); }
    .install-badge { border-radius: 999px; background: #fff; border: 1px solid var(--color-line); padding: 6px 12px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--color-muted); }
    .install-card { background: #fff; border: 1px solid var(--color-line); border-radius: var(--radius); overflow: hidden; box-shadow: 0 18px 44px -30px #1e223059; }
    .install-head { padding: 26px 28px 20px; border-bottom: 1px solid var(--color-line); }
    .install-kicker { margin: 0; font-size: 10px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: var(--color-accent-dark); }
    .install-title { margin: 6px 0 0; font-family: var(--font-display); font-size: 28px; line-height: 1.1; font-weight: 700; }
    .install-sub { margin: 8px 0 0; font-size: 13px; line-height: 1.6; color: var(--color-muted); }
    .install-body { padding: 26px 28px 30px; }
    .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 26px; }
    .step { border: 1px solid var(--color-line); border-radius: 14px; padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--color-muted); background: var(--color-cream); }
    .step span { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 999px; margin-right: 6px; background: #fff; border: 1px solid var(--color-line); font-size: 10px; }
    .step.active { border-color: var(--color-accent); background: var(--color-accent-soft); color: var(--color-accent-dark); }
    .step.done { color: #1C6B45; background: var(--color-mint); border-color: #b8e2cd; }
    .field-label { display: block; margin: 0 0 6px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--color-muted); }
    .input {
        display: block; width: 100%; border: 1px solid var(--color-line); border-radius: 16px;
        background: #fff; color: var(--color-ink); padding: 11px 14px; font-family: inherit;
        font-size: 14px; line-height: 1.4; outline: none; transition: border-color .15s ease, box-shadow .15s ease;
    }
    .input:focus { border-color: var(--color-accent); box-shadow: 0 0 0 4px #ff5a3614; }
    .input::placeholder { color: #5e647299; }
    .install-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
    @media (min-width: 620px) { .install-grid { grid-template-columns: 1fr 1fr; } }
    .choice-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 620px) { .choice-grid { grid-template-columns: 1fr; } }
    .choice { display: block; cursor: pointer; border: 1px solid var(--color-line); border-radius: 18px; padding: 16px; background: #fff; transition: border-color .15s ease, background .15s ease; }
    .choice:hover { border-color: #ff5a3666; }
    .choice input { position: absolute; opacity: 0; pointer-events: none; }
    .choice-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; }
    .choice-title svg { width: 18px; height: 18px; color: var(--color-accent-dark); }
    .choice-desc { margin: 6px 0 0; font-size: 11.5px; line-height: 1.5; color: var(--color-muted); }
    .choice:has(input:checked) { border-color: var(--color-accent); background: #fff8f5; box-shadow: 0 0 0 4px #ff5a3614; }
    .install-error { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; border-radius: 14px; padding: 12px 14px; font-size: 12.5px; line-height: 1.5; margin-bottom: 18px; }
    .install-success { border: 1px solid #b8e2cd; background: #f1fbf6; color: #1C6B45; border-radius: 14px; padding: 12px 14px; font-size: 12.5px; line-height: 1.5; margin-bottom: 18px; }
    .install-warning { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 14px; padding: 12px 14px; font-size: 12.5px; line-height: 1.5; margin-bottom: 18px; }
    .install-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding-top: 22px; margin-top: 22px; border-top: 1px solid var(--color-line); }
    .install-note { border-radius: 16px; background: var(--color-cream); border: 1px solid var(--color-line); padding: 14px 16px; font-size: 12px; line-height: 1.6; color: var(--color-muted); }
    .summary { display: grid; gap: 10px; }
    .summary-row { display: flex; justify-content: space-between; gap: 16px; border-bottom: 1px dashed var(--color-line); padding-bottom: 10px; font-size: 13px; }
    .summary-row strong { text-align: right; }
    .done-icon { width: 52px; height: 52px; border-radius: 999px; background: var(--color-mint); color: #1C6B45; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        border: 1px solid transparent; border-radius: 999px; padding: 10px 18px;
        font-family: inherit; font-size: 13px; font-weight: 700; line-height: 1.2;
        text-decoration: none; cursor: pointer; transition: background .15s ease, border-color .15s ease;
    }
    .btn:disabled { opacity: .6; cursor: not-allowed; }
    .btn-primary { background: var(--color-accent); color: #fff; box-shadow: 0 8px 20px -12px #ff5a36e6; }
    .btn-primary:hover { background: var(--color-accent-dark); }
    .btn-secondary { background: #fff; border-color: var(--color-line); color: var(--color-ink); }
    .btn-secondary:hover { background: var(--color-cream); }
    @media (max-width: 520px) {
        .install-head, .install-body { padding-left: 20px; padding-right: 20px; }
        .steps { grid-template-columns: 1fr; }
        .install-actions { flex-direction: column; align-items: stretch; }
        .install-actions .btn { width: 100%; }
    }
</style>
</head>
<body>
<div class="install-shell">
    <div class="install-wrap">
        <div class="install-brand">
            <a href="/app/" style="text-decoration:none;display:inline-flex;align-items:center">
                <img src="/app/gandiatech-logo-dark.png" alt="GandiaTech" width="1724" height="399" onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
                <span class="install-brand-text" style="display:none">GandiaTech <em>CRM</em></span>
            </a>
            <span class="install-badge">Instalación</span>
        </div>

        <div class="install-card">
            <div class="install-head">
                <p class="install-kicker">GandiaTech CRM</p>
                <h1 class="install-title"><?= e($heading) ?></h1>
                <p class="install-sub"><?= e($subheading) ?></p>
            </div>
            <div class="install-body">
                <?php if ($installCompleted): ?>
                    <div class="done-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="26" height="26"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <div class="install-success" role="status">
                        Instalación completada para <strong><?= e($installedCompany) ?></strong>.
                    </div>
                    <?php if ($installRemoved): ?>
                        <p class="install-sub" style="margin-top:0">La carpeta <code>install/</code> se ha eliminado automáticamente por seguridad.</p>
                    <?php else: ?>
                        <div class="install-warning" role="alert">
                            El CRM ya está instalado, pero no se pudo eliminar la carpeta <code>install/</code>.
                            Bórrala manualmente desde el gestor de archivos o por FTP.
                        </div>
                    <?php endif; ?>
                    <div class="install-actions">
                        <span class="install-note">Guarda el usuario administrador en un lugar seguro.</span>
                        <a class="btn btn-primary" href="/app/">Acceder al CRM</a>
                    </div>
                <?php elseif ($installed): ?>
                    <div class="done-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="26" height="26"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <p class="install-sub" style="margin-top:0">Por seguridad, el instalador no vuelve a ejecutarse. Si necesitas reinstalar, elimina <code>config/config.php</code> y la base de datos actual.</p>
                    <div class="install-actions">
                        <span class="install-note">Instalación completada.</span>
                        <a class="btn btn-primary" href="/app/">Acceder al CRM</a>
                    </div>
                <?php else: ?>
                    <div class="steps" aria-label="Pasos de instalación">
                        <div class="step <?= $step === 1 ? 'active' : 'done' ?>"><span>1</span>Base de datos</div>
                        <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>"><span>2</span>Empresa y admin</div>
                        <div class="step <?= $step === 3 ? 'active' : '' ?>"><span>3</span>Instalar</div>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="install-error" role="alert"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                        <form method="post" action="/install/?step=1" id="db-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="db">
                            <p class="field-label">¿Dónde quieres guardar los datos?</p>
                            <div class="choice-grid">
                                <label class="choice">
                                    <input type="radio" name="driver" value="sqlite" <?= $driverValue === 'sqlite' ? 'checked' : '' ?>>
                                    <span class="choice-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg>
                                        SQLite · local
                                    </span>
                                    <p class="choice-desc">La opción más sencilla. Un único archivo dentro del CRM, sin configurar nada más.</p>
                                </label>
                                <label class="choice">
                                    <input type="radio" name="driver" value="mysql" <?= $driverValue === 'mysql' ? 'checked' : '' ?>>
                                    <span class="choice-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M2 12h20"/><path d="m4.9 4.9 14.2 14.2"/><path d="m19.1 4.9-14.2 14.2"/></svg>
                                        MySQL · hosting
                                    </span>
                                    <p class="choice-desc">Para alojamientos con MySQL o MariaDB. Usa los datos que te da tu proveedor.</p>
                                </label>
                            </div>

                            <div id="sqlite-fields" style="margin-top:20px;<?= $driverValue === 'mysql' ? 'display:none' : '' ?>">
                                <label class="field-label" for="sqlite-path">Archivo de la base de datos</label>
                                <input id="sqlite-path" name="sqlite_path" class="input" value="<?= e($sqliteDisplay) ?>" placeholder="data/crm.sqlite">
                                <p class="install-sub" style="margin-top:8px">Ruta relativa a la carpeta del CRM. Debe tener permisos de escritura.</p>
                            </div>

                            <div id="mysql-fields" style="margin-top:20px;<?= $driverValue === 'sqlite' ? 'display:none' : '' ?>">
                                <div class="install-grid">
                                    <div>
                                        <label class="field-label" for="mysql-host">Servidor</label>
                                        <input id="mysql-host" name="mysql_host" class="input" value="<?= e($mysqlHost) ?>" placeholder="localhost">
                                    </div>
                                    <div>
                                        <label class="field-label" for="mysql-port">Puerto</label>
                                        <input id="mysql-port" name="mysql_port" class="input" value="<?= e($mysqlPort) ?>" placeholder="3306">
                                    </div>
                                </div>
                                <div class="install-grid" style="margin-top:16px">
                                    <div>
                                        <label class="field-label" for="mysql-database">Base de datos</label>
                                        <input id="mysql-database" name="mysql_database" class="input" value="<?= e($mysqlDatabase) ?>" placeholder="gandiatech_crm">
                                    </div>
                                    <div>
                                        <label class="field-label" for="mysql-username">Usuario</label>
                                        <input id="mysql-username" name="mysql_username" class="input" value="<?= e($mysqlUsername) ?>" placeholder="usuario">
                                    </div>
                                </div>
                                <div style="margin-top:16px">
                                    <label class="field-label" for="mysql-password">Contraseña</label>
                                    <input id="mysql-password" name="mysql_password" type="password" class="input" value="" autocomplete="new-password" maxlength="500">
                                </div>
                                <p class="install-sub" style="margin-top:8px">La base de datos debe existir previamente en el hosting.</p>
                            </div>

                            <div class="install-actions">
                                <span class="install-note">Paso 1 de 3 · Podrás cambiarlo más adelante.</span>
                                <button type="submit" class="btn btn-primary">Probar conexión y continuar</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($step === 2): ?>
                        <form method="post" action="/install/?step=2">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="admin">
                            <div>
                                <label class="field-label" for="app-name">Nombre de la empresa</label>
                                <input id="app-name" name="app_name" class="input" maxlength="80" value="<?= e($appNameValue) ?>" placeholder="Mi empresa">
                                <p class="install-sub" style="margin-top:8px">Aparecerá en la cabecera de todas las vistas del CRM.</p>
                            </div>

                            <div class="install-grid" style="margin-top:18px">
                                <div>
                                    <label class="field-label" for="admin-name">Nombre del administrador</label>
                                    <input id="admin-name" name="admin_name" class="input" maxlength="120" value="<?= e($adminNameValue) ?>" required>
                                </div>
                                <div>
                                    <label class="field-label" for="admin-email">Correo del administrador</label>
                                    <input id="admin-email" name="admin_email" type="email" class="input" value="<?= e($adminEmailValue) ?>" required>
                                </div>
                            </div>

                            <div class="install-grid" style="margin-top:18px">
                                <div>
                                    <label class="field-label" for="admin-password">Contraseña</label>
                                    <input id="admin-password" name="admin_password" type="password" class="input" minlength="12" maxlength="72" autocomplete="new-password" required>
                                </div>
                                <div>
                                    <label class="field-label" for="admin-password-confirm">Repetir contraseña</label>
                                    <input id="admin-password-confirm" name="admin_password_confirm" type="password" class="input" minlength="12" maxlength="72" autocomplete="new-password" required>
                                </div>
                            </div>
                            <p class="install-sub" style="margin-top:8px">Mínimo 12 caracteres, con letras y al menos un número.</p>

                            <div class="install-actions">
                                <a class="btn btn-secondary" href="/install/?step=1">Volver</a>
                                <button type="submit" class="btn btn-primary">Continuar</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($step === 3): ?>
                        <div class="summary">
                            <div class="summary-row"><span>Base de datos</span><strong><?= $driver === 'mysql' ? 'MySQL / MariaDB' : 'SQLite' ?></strong></div>
                            <div class="summary-row"><span><?= $driver === 'mysql' ? 'Base de datos' : 'Archivo' ?></span><strong><?= e($driver === 'mysql' ? (string) ($draft['db']['database'] ?? '') : $sqliteDisplay) ?></strong></div>
                            <div class="summary-row"><span>Empresa</span><strong><?= e((string) ($draft['app_name'] ?? 'Mi CRM')) ?></strong></div>
                            <div class="summary-row"><span>Administrador</span><strong><?= e($adminEmailValue) ?></strong></div>
                        </div>
                        <div class="install-note" style="margin-top:20px">
                            Se crearán las tablas, los estados de leads y los ajustes iniciales. El correo saliente quedará vacío para que lo configures cuando quieras.
                        </div>
                        <form method="post" action="/install/?step=3">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="install">
                            <div class="install-actions">
                                <a class="btn btn-secondary" href="/install/?step=2">Volver</a>
                                <button type="submit" class="btn btn-primary">Instalar CRM</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$installed && !$installCompleted): ?>
            <p style="text-align:center;font-size:11px;color:var(--color-muted);margin-top:18px">
                La carpeta <code>install/</code> se elimina automáticamente al completar la instalación.
            </p>
        <?php endif; ?>
    </div>
</div>
<?php if (!$installed && $step === 1): ?>
<script>
    (function () {
        var radios = document.querySelectorAll('input[name="driver"]');
        var sqlite = document.getElementById('sqlite-fields');
        var mysql = document.getElementById('mysql-fields');
        function update() {
            var value = document.querySelector('input[name="driver"]:checked').value;
            sqlite.style.display = value === 'sqlite' ? '' : 'none';
            mysql.style.display = value === 'mysql' ? '' : 'none';
        }
        radios.forEach(function (radio) { radio.addEventListener('change', update); });
    })();
</script>
<?php endif; ?>
<script src="/app/password-tools.js" defer></script>
</body>
</html>
