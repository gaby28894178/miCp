<?php
session_start();

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$messages = [];

if (!isset($_SESSION['users'])) { 
    $_SESSION['users'] = ['admin' => password_hash('admin123', PASSWORD_DEFAULT)]; 
}

if (!isset($_SESSION['projects'])) {
    $_SESSION['projects'] = [];
}

// Manejo de acciones simples (consola y generación de script Python)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'run_command') {
        $command = trim($_POST['command'] ?? '');
        $os = $_POST['os'] ?? 'linux';
        $_SESSION['console_os'] = $os;
        $allowed_prefixes = ['ls', 'pwd', 'whoami', 'date', 'uptime', 'df -h', 'free -h', 'dir'];
        $is_allowed = false;
        foreach ($allowed_prefixes as $pref) { if (strpos($command, $pref) === 0) { $is_allowed = true; break; } }
        if ($command === '') { $messages[] = 'Ingrese un comando.'; }
        elseif (!$is_allowed) { $messages[] = 'Comando no permitido.'; }
        else {
            $output = shell_exec($command . ' 2>&1');
            $prompt = ($os === 'windows') ? 'C:\\>' : '$ ';
            $_SESSION['console_output'] = ($_SESSION['console_output'] ?? "DevPanel Pro Console v1.0\n==========================================\n") . $prompt . $command . "\n" . ($output ?? '') . "\n";
            $_SESSION['last_section'] = 'section-console';
            $messages[] = 'Comando ejecutado';
        }
    } elseif ($action === 'generate_python_cms') {
        $db_user = trim($_POST['py_db_user'] ?? 'admin');
        $db_pass = trim($_POST['py_db_pass'] ?? 'Admin123!');
        $linux_host = trim($_POST['py_linux_host'] ?? 'localhost');
        $messages[] = generate_python_cms_script($db_user, $db_pass, $linux_host);
        $_SESSION['last_section'] = 'section-scripts';
    } elseif ($action === 'create_site') {
        $type = $_POST['site_type'] ?? 'wordpress';
        $folder = trim($_POST['folder_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_user = trim($_POST['db_user'] ?? 'root');
        $db_pass = $_POST['db_pass'] ?? '';
        $db_port = intval($_POST['db_port'] ?? 3306);
        $create_db = isset($_POST['create_db']);
        $use_default = isset($_POST['use_default_connection']);
        $project_id = $_POST['project_id'] ?? '';
        if ($use_default) { $db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_port = 3306; }
        if ($db_name === '') { $db_name = preg_replace('/[^A-Za-z0-9_$]/', '_', $folder !== '' ? $folder : ('wp_' . bin2hex(random_bytes(3)))); }
        if ($folder === '') { $messages[] = 'Ingrese nombre de sitio.'; }
        else {
            try {
                $base_override = null;
                if ($project_id !== '' && isset($_SESSION['projects'][$project_id]) && isset($_SESSION['projects'][$project_id]['path'])) {
                    $base_override = $_SESSION['projects'][$project_id]['path'];
                }
                $resultMsg = create_site($type, $folder, $db_host, $db_name, $db_user, $db_pass, $db_port, $create_db, $admin_email, $base_override);
                $messages[] = $resultMsg;
                // Registrar en proyecto seleccionado si existe
                if ($project_id !== '' && isset($_SESSION['projects'][$project_id])) {
                    if (!isset($_SESSION['projects'][$project_id]['sites'])) { $_SESSION['projects'][$project_id]['sites'] = []; }
                    $_SESSION['projects'][$project_id]['sites'][] = [
                        'type' => $type,
                        'name' => $folder,
                        'path' => ($base_override ?: default_base_path()) . DIRECTORY_SEPARATOR . $folder,
                        'url' => site_url_from_path(($base_override ?: default_base_path()) . DIRECTORY_SEPARATOR . $folder),
                        'db' => $db_name,
                        'created_at' => date('Y-m-d H:i')
                    ];
                    if ($create_db) {
                        if (!isset($_SESSION['projects'][$project_id]['databases'])) { $_SESSION['projects'][$project_id]['databases'] = []; }
                        $_SESSION['projects'][$project_id]['databases'][] = [
                            'type' => 'mysql',
                            'name' => $db_name,
                            'host' => $db_host,
                            'created_at' => date('Y-m-d H:i')
                        ];
                    }
                }
                if ($create_db) {
                    if (!isset($_SESSION['databases'])) { $_SESSION['databases'] = []; }
                    $_SESSION['databases'][] = [
                        'type' => 'mysql',
                        'name' => $db_name,
                        'host' => $db_host,
                        'created_at' => date('Y-m-d H:i')
                    ];
                }
                $_SESSION['last_section'] = 'section-cms';
            } catch (Throwable $e) { $messages[] = 'Error: ' . $e->getMessage(); }
        }
    } elseif ($action === 'view_script') {
        $name = trim($_POST['script_name'] ?? '');
        $scriptsRoot = __DIR__ . DIRECTORY_SEPARATOR . 'scripts';
        $full = realpath($scriptsRoot . DIRECTORY_SEPARATOR . $name);
        if ($name === '' || $full === false || strpos($full, realpath($scriptsRoot)) !== 0 || !is_file($full)) { $messages[] = 'Script no encontrado.'; }
        else {
            $_SESSION['script_view_file'] = $name;
            $_SESSION['script_view_content'] = file_get_contents($full);
            $_SESSION['last_section'] = 'section-scripts';
            $messages[] = 'Script cargado';
        }
    } elseif ($action === 'run_script') {
        $name = trim($_POST['script_name'] ?? '');
        $scriptsRoot = __DIR__ . DIRECTORY_SEPARATOR . 'scripts';
        $full = realpath($scriptsRoot . DIRECTORY_SEPARATOR . $name);
        if ($name === '' || $full === false || strpos($full, realpath($scriptsRoot)) !== 0 || !is_file($full)) { $messages[] = 'Script no encontrado.'; }
        else {
            $cmd = (PHP_OS_FAMILY === 'Windows') ? 'py -3' : 'python3';
            $output = shell_exec($cmd . ' ' . escapeshellarg($full) . ' 2>&1');
            $_SESSION['script_run_file'] = $name;
            $_SESSION['script_run_output'] = $output ?? '';
            $_SESSION['last_section'] = 'section-scripts';
            $messages[] = 'Script ejecutado';
        }
    } elseif ($action === 'create_project') {
        $name = trim($_POST['project_name'] ?? '');
        $folder = trim($_POST['project_folder'] ?? '');
        $type = trim($_POST['project_type'] ?? 'custom');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') { $messages[] = 'Ingrese nombre de proyecto.'; }
        else {
            if ($folder === '') { $folder = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)); }
            $base = default_base_path();
            $path = $base . DIRECTORY_SEPARATOR . $folder;
            ensure_dir($path);
            $id = bin2hex(random_bytes(8));
            $_SESSION['projects'][$id] = [
                'name' => $name,
                'folder' => $folder,
                'type' => $type,
                'description' => $desc,
                'path' => $path,
                'databases' => [],
                'sites' => [],
                'created_by' => $_SESSION['auth'] ?? 'Invitado',
                'created_at' => date('Y-m-d H:i')
            ];
            $_SESSION['last_section'] = 'section-projects';
            $messages[] = 'Proyecto creado en ' . $path . '.';
        }
    } elseif ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') { $messages[] = 'Ingrese usuario y contraseña.'; }
        elseif (isset($_SESSION['users'][$username]) && password_verify($password, $_SESSION['users'][$username])) {
            $_SESSION['auth'] = $username;
            $messages[] = 'Sesión iniciada.';
        } else { $messages[] = 'Credenciales inválidas.'; }
        $_SESSION['last_section'] = 'section-dashboard';
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') { $messages[] = 'Ingrese usuario y contraseña.'; }
        elseif (isset($_SESSION['users'][$username])) { $messages[] = 'El usuario ya existe.'; }
        else {
            $_SESSION['users'][$username] = password_hash($password, PASSWORD_DEFAULT);
            $messages[] = 'Usuario registrado.';
        }
        $_SESSION['last_section'] = 'section-dashboard';
    } elseif ($action === 'logout') {
        unset($_SESSION['auth']);
        $messages[] = 'Sesión cerrada.';
        $_SESSION['last_section'] = 'section-dashboard';
    }
}

function ensure_dir($path) { if (!is_dir($path)) { mkdir($path, 0777, true); } }

function generate_python_cms_script($db_user, $db_pass, $linux_host) {
    $scriptsDir = __DIR__ . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'python';
    ensure_dir($scriptsDir);
    $file = $scriptsDir . DIRECTORY_SEPARATOR . 'install_cms.py';
    $py = <<<'PY'
import os
import platform
import subprocess
import shutil

DB_USER = '__DB_USER__'
DB_PASS = '__DB_PASS__'
DB_WP = 'wordpress'
DB_MOODLE = 'moodle'

def instalar_linux():
    print('📦 Instalando en Linux...')
    subprocess.run('apt update && apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql unzip wget curl', shell=True)
    sql = f"""
DROP DATABASE IF EXISTS {DB_WP};
DROP DATABASE IF EXISTS {DB_MOODLE};
DROP USER IF EXISTS '{DB_USER}'@'localhost';
CREATE DATABASE {DB_WP} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE {DB_MOODLE} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '{DB_USER}'@'localhost' IDENTIFIED BY '{DB_PASS}';
GRANT ALL PRIVILEGES ON {DB_WP}.* TO '{DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON {DB_MOODLE}.* TO '{DB_USER}'@'localhost';
FLUSH PRIVILEGES;
"""
    subprocess.run(f"mysql -u root -e \"{sql}\"", shell=True)
    wp_dir = '/var/www/html/tienda/wordpress'
    os.makedirs(wp_dir, exist_ok=True)
    subprocess.run('wget -q https://wordpress.org/latest.zip -O /tmp/wordpress.zip', shell=True)
    subprocess.run('unzip -q /tmp/wordpress.zip -d /tmp/', shell=True)
    shutil.move('/tmp/wordpress', wp_dir)
    subprocess.run('rm -f /tmp/wordpress.zip', shell=True)
    wp_config = os.path.join(wp_dir, 'wp-config.php')
    shutil.copy(os.path.join(wp_dir, 'wp-config-sample.php'), wp_config)
    with open(wp_config, 'r') as f:
        content = f.read()
    content = content.replace('database_name_here', DB_WP).replace('username_here', DB_USER).replace('password_here', DB_PASS)
    with open(wp_config, 'w') as f:
        f.write(content)
    md_dir = '/var/www/html/cursos/moodle'
    os.makedirs(md_dir, exist_ok=True)
    subprocess.run('wget -q https://download.moodle.org/latest.zip -O /tmp/moodle.zip', shell=True)
    subprocess.run('unzip -q /tmp/moodle.zip -d /tmp/', shell=True)
    shutil.move('/tmp/moodle', md_dir)
    subprocess.run('rm -f /tmp/moodle.zip', shell=True)
    moodle_config = os.path.join(md_dir, 'config.php')
    with open(moodle_config, 'w') as f:
        f.write(f"""<?php
$CFG = new stdClass();
$CFG->dbtype    = 'mariadb';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = '{DB_MOODLE}';
$CFG->dbuser    = '{DB_USER}';
$CFG->dbpass    = '{DB_PASS}';
$CFG->prefix    = 'mdl_';
$CFG->wwwroot   = '__LINUX_WWWROOT__';
$CFG->dataroot  = '/var/moodledata';
$CFG->directorypermissions = 0777;
require_once(__DIR__ . '/lib/setup.php');
""")
    subprocess.run('chown -R www-data:www-data /var/www/html /var/moodledata', shell=True)
    subprocess.run('chmod -R 755 /var/www/html', shell=True)
    subprocess.run('chmod -R 777 /var/moodledata', shell=True)
    subprocess.run('systemctl restart apache2', shell=True)
    print('✅ Instalación Linux finalizada.')

def instalar_windows():
    print('📦 Instalando en Windows (XAMPP)...')
    htdocs = r'C:\\xampp\\htdocs'
    wp_dir = os.path.join(htdocs, 'tienda', 'wordpress')
    os.makedirs(wp_dir, exist_ok=True)
    subprocess.run(f"powershell -Command \"Invoke-WebRequest 'https://wordpress.org/latest.zip' -OutFile wordpress.zip; Expand-Archive wordpress.zip -DestinationPath {os.path.join(htdocs,'tienda')}; Remove-Item wordpress.zip\"", shell=True)
    wp_config = os.path.join(wp_dir, 'wp-config.php')
    shutil.copy(os.path.join(wp_dir, 'wp-config-sample.php'), wp_config)
    with open(wp_config, 'r') as f:
        content = f.read()
    content = content.replace('database_name_here', DB_WP).replace('username_here', DB_USER).replace('password_here', DB_PASS)
    with open(wp_config, 'w') as f:
        f.write(content)
    md_dir = os.path.join(htdocs, 'cursos', 'moodle')
    os.makedirs(md_dir, exist_ok=True)
    subprocess.run(f"powershell -Command \"Invoke-WebRequest 'https://download.moodle.org/latest.zip' -OutFile moodle.zip; Expand-Archive moodle.zip -DestinationPath {os.path.join(htdocs,'cursos')}; Remove-Item moodle.zip\"", shell=True)
    moodle_config = os.path.join(md_dir, 'config.php')
    with open(moodle_config, 'w') as f:
        f.write(f"""<?php
$CFG = new stdClass();
$CFG->dbtype    = 'mariadb';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = '{DB_MOODLE}';
$CFG->dbuser    = '{DB_USER}';
$CFG->dbpass    = '{DB_PASS}';
$CFG->prefix    = 'mdl_';
$CFG->wwwroot   = 'http://localhost/cursos/moodle';
$CFG->dataroot  = 'C:/xampp/moodledata';
$CFG->directorypermissions = 0777;
require_once(__DIR__ . '/lib/setup.php');
""")
    mysql = r'C:\\xampp\\mysql\\bin\\mysql.exe'
    sql = f"""
DROP DATABASE IF EXISTS {DB_WP};
DROP DATABASE IF EXISTS {DB_MOODLE};
DROP USER IF EXISTS '{DB_USER}'@'localhost';
CREATE DATABASE {DB_WP};
CREATE DATABASE {DB_MOODLE};
CREATE USER '{DB_USER}'@'localhost' IDENTIFIED BY '{DB_PASS}';
GRANT ALL PRIVILEGES ON {DB_WP}.* TO '{DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON {DB_MOODLE}.* TO '{DB_USER}'@'localhost';
FLUSH PRIVILEGES;
"""
    subprocess.run(f'"{mysql}" -u root -e "{sql}"', shell=True)
    print('✅ Instalación Windows finalizada.')

if platform.system() == 'Linux':
    instalar_linux()
elif platform.system() == 'Windows':
    instalar_windows()
else:
    print('⚠️ Sistema operativo no soportado.')
PY;
    $linux_wwwroot = 'http://' . ($linux_host !== '' ? $linux_host : 'localhost') . '/cursos/moodle';
    $py = str_replace(['__DB_USER__','__DB_PASS__','__LINUX_WWWROOT__'], [addslashes($db_user), addslashes($db_pass), addslashes($linux_wwwroot)], $py);
    file_put_contents($file, $py);
    return 'Script Python generado en ' . $file . '. Ejecuta: Linux → sudo python3 scripts/python/install_cms.py | Windows → python scripts\\python\\install_cms.py';
}

function default_base_path() {
    if (PHP_OS_FAMILY === 'Windows') { return dirname(__DIR__); }
    return '/var/www/html';
}

function get_wp_salts() {
    return "define('AUTH_KEY', '" . bin2hex(random_bytes(16)) . "');\n"
         . "define('SECURE_AUTH_KEY', '" . bin2hex(random_bytes(16)) . "');\n"
         . "define('LOGGED_IN_KEY', '" . bin2hex(random_bytes(16)) . "');\n"
         . "define('NONCE_KEY', '" . bin2hex(random_bytes(16)) . "');\n"
         . "define('AUTH_SALT', '" . bin2hex(random_bytes(16)) . "');\n"
         . "define('SECURE_AUTH_SALT', '" . bin2hex(random_bytes(16)) . "');\n"
         . "define('LOGGED_IN_SALT', '" . bin2hex(random_bytes(16)) . "');\n"
         . "define('NONCE_SALT', '" . bin2hex(random_bytes(16)) . "');\n";
}

function download_wordpress($destDir) {
    ensure_dir($destDir);
    $zipPath = $destDir . DIRECTORY_SEPARATOR . 'wordpress.zip';
    $wpUrl = 'https://wordpress.org/latest.zip';
    $ok = false;
    $data = @file_get_contents($wpUrl);
    if ($data !== false) {
        file_put_contents($zipPath, $data);
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($destDir);
                $zip->close();
                $ok = true;
            }
        }
        @unlink($zipPath);
    }
    if (!$ok) {
        if (PHP_OS_FAMILY === 'Windows') {
            $ps = 'powershell -Command "Try { Invoke-WebRequest \'' . $wpUrl . '\' -OutFile wordpress.zip; Expand-Archive wordpress.zip -DestinationPath ' . escapeshellarg($destDir) . '; Remove-Item wordpress.zip -Force; exit 0 } Catch { exit 1 }"';
            $out = shell_exec($ps);
            $ok = is_dir($destDir . DIRECTORY_SEPARATOR . 'wordpress');
        } else {
            $cmd = 'wget -q ' . escapeshellarg($wpUrl) . ' -O ' . escapeshellarg($zipPath) . ' && unzip -q ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($destDir) . ' && rm -f ' . escapeshellarg($zipPath);
            $out = shell_exec($cmd);
            $ok = is_dir($destDir . DIRECTORY_SEPARATOR . 'wordpress');
        }
    }
    return $ok;
}

function create_database_if_needed($db_host, $db_user, $db_pass, $db_name, $db_port) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, '', $db_port);
    if ($mysqli->connect_error) { throw new RuntimeException('Conexión MySQL falló: ' . $mysqli->connect_error); }
    $dbEsc = preg_replace('/[^A-Za-z0-9_$]/', '_', $db_name);
    if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbEsc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        throw new RuntimeException('No se pudo crear base de datos: ' . $mysqli->error);
    }
    $mysqli->close();
    return $dbEsc;
}

function setup_wordpress_min($sitePath, $db_host, $db_name, $db_user, $db_pass, $admin_email) {
    $root = $sitePath . DIRECTORY_SEPARATOR . 'wordpress';
    if (!is_dir($root)) { $root = $sitePath; }
    $configPath = $root . DIRECTORY_SEPARATOR . 'wp-config.php';
    $salts = get_wp_salts();
    $content = "<?php\n";
    $content .= "define('DB_NAME', '" . addslashes($db_name) . "');\n";
    $content .= "define('DB_USER', '" . addslashes($db_user) . "');\n";
    $content .= "define('DB_PASSWORD', '" . addslashes($db_pass) . "');\n";
    $content .= "define('DB_HOST', '" . addslashes($db_host) . "');\n";
    $content .= "define('DB_CHARSET', 'utf8mb4');\n";
    $content .= "define('DB_COLLATE', '');\n";
    $content .= $salts;
    $content .= "\n$" . "table_prefix = 'wp_';\n";
    if ($admin_email !== '') { $content .= "define('ADMIN_EMAIL', '" . addslashes($admin_email) . "');\n"; }
    $content .= "define('WP_DEBUG', false);\n";
    $content .= "if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');\n";
    $content .= "require_once ABSPATH . 'wp-settings.php';\n";
    file_put_contents($configPath, $content);
}

function site_url_from_path($sitePath) {
    if (PHP_OS_FAMILY === 'Windows') {
        $htdocs = dirname(__DIR__);
        $rel = str_replace('\\', '/', str_replace($htdocs . DIRECTORY_SEPARATOR, '', $sitePath));
        $wpRoot = is_dir($sitePath . DIRECTORY_SEPARATOR . 'wordpress') ? '/wordpress' : '';
        return 'http://localhost/' . trim($rel, '/') . $wpRoot . '/';
    }
    $wpRoot = is_dir($sitePath . DIRECTORY_SEPARATOR . 'wordpress') ? '/wordpress' : '';
    return 'http://localhost/' . trim(str_replace('/var/www/html/', '', $sitePath), '/') . $wpRoot . '/';
}

function create_site($type, $folder, $db_host, $db_name, $db_user, $db_pass, $db_port, $create_db, $admin_email, $base_path = null) {
    $base = $base_path ?: default_base_path();
    ensure_dir($base);
    $sitePath = $base . DIRECTORY_SEPARATOR . $folder;
    ensure_dir($sitePath);
    if ($create_db) { create_database_if_needed($db_host, $db_user, $db_pass, $db_name, $db_port); }
    if ($type === 'wordpress') {
        $ok = download_wordpress($sitePath);
        if (!$ok) { throw new RuntimeException('No se pudo descargar WordPress.'); }
        setup_wordpress_min($sitePath, $db_host, $db_name, $db_user, $db_pass, $admin_email);
        $url = site_url_from_path($sitePath);
        $_SESSION['last_site_url'] = $url;
        return 'WordPress instalado en ' . $sitePath . ' y configurado con la base ' . $db_name . '. URL: ' . $url;
    } elseif ($type === 'moodle') {
        // Placeholder: aquí se podría descargar Moodle similar a WP
        return 'Moodle preparado en ' . $sitePath . ' (descarga/configuración mínima pendiente).';
    }
    return 'Tipo de sitio no reconocido.';
}

// Resto de tus funciones PHP (las mantengo igual)...

?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevPanel Pro - Dashboard de Desarrollo</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --sidebar-bg: #111827;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            padding: 0;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .sidebar-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: white;
        }
        
        .sidebar-header .subtitle {
            font-size: 0.875rem;
            opacity: 0.9;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .nav-container {
            padding: 1rem 0;
        }
        
        .nav-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            font-weight: 600;
            margin-top: 1rem;
        }
        
        .nav-link-custom {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            gap: 0.75rem;
        }
        
        .nav-link-custom:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-primary);
            border-left-color: var(--primary-color);
        }
        
        .nav-link-custom.active {
            background: rgba(99, 102, 241, 0.15);
            color: var(--text-primary);
            border-left-color: var(--primary-color);
        }
        
        .nav-link-custom i {
            width: 20px;
            font-size: 1.1rem;
        }
        
        .badge-popular {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            margin-left: auto;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Custom Cards */
        .custom-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .custom-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border-color: var(--primary-color);
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .icon-bg-primary { background: rgba(99, 102, 241, 0.2); color: var(--primary-color); }
        .icon-bg-success { background: rgba(16, 185, 129, 0.2); color: var(--success-color); }
        .icon-bg-warning { background: rgba(245, 158, 11, 0.2); color: var(--warning-color); }
        .icon-bg-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger-color); }
        
        /* Forms */
        .form-control-custom {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
        
        .form-control-custom:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-color);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
        }
        
        /* Buttons */
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
            color: white;
        }
        
        /* Tables */
        .table-custom {
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table-custom th {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .table-custom td {
            border-color: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
        }
        
        /* Tabs */
        .nav-tabs-custom {
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .nav-tabs-custom .nav-link {
            color: var(--text-secondary);
            background: transparent;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px 8px 0 0;
            margin-right: 0.5rem;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            border-bottom: 3px solid var(--primary-color);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Dashboard Stats */
        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }
        
        /* Progress bars */
        .progress-custom {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            height: 10px;
            overflow: hidden;
        }
        
        .progress-custom .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1050;
            background: var(--primary-color);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: none;
        }
        
        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        /* Console styling */
        .console-container {
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #333;
        }
        
        .console-header {
            background: #2d2d2d;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #444;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .console-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #fff;
            font-weight: 500;
        }
        
        .console-output {
            padding: 1rem;
            font-family: 'Courier New', monospace;
            color: #0f0;
            background: #000;
            min-height: 200px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="bi bi-list"></i>
    </button>
    
    <nav class="navbar navbar-dark bg-dark sticky-top" style="z-index: 1100;">
        <div class="container-fluid">
            <a class="navbar-brand" href="#dashboard" data-section="dashboard">
                <i class="bi bi-code-slash"></i> DevPanel Pro
            </a>
            <div class="d-flex align-items-center gap-2">
                <?php if(isset($_SESSION['auth'])): ?>
                    <span class="text-white small"><i class="bi bi-person-circle me-1"></i><?php echo h($_SESSION['auth']); ?></span>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#authModal">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h1><i class="bi bi-code-slash"></i> DevPanel Pro</h1>
            <div class="subtitle">Development Dashboard</div>
        </div>
        
        <div class="nav-container">
            <div class="nav-title">Navigation</div>
            
            <nav class="nav flex-column">
                <a class="nav-link-custom active" href="#dashboard" data-section="dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                
                <a class="nav-link-custom" href="#projects" data-section="projects">
                    <i class="bi bi-folder"></i>
                    <span>Mis Proyectos</span>
                </a>
                
                <a class="nav-link-custom" href="#new-project" data-section="new-project">
                    <i class="bi bi-plus-circle"></i>
                    <span>Nuevo Proyecto</span>
                </a>
                
                <a class="nav-link-custom" href="#cms" data-section="cms">
                    <i class="bi bi-cloud-arrow-down"></i>
                    <span>Instalar CMS</span>
                    <span class="badge-popular">POPULAR</span>
                </a>
                
                <a class="nav-link-custom" href="#flask" data-section="flask">
                    <i class="bi bi-braces"></i>
                    <span>Crear App Flask</span>
                </a>
                
                <a class="nav-link-custom" href="#databases" data-section="databases">
                    <i class="bi bi-database"></i>
                    <span>Bases de Datos</span>
                </a>
                
                <a class="nav-link-custom" href="#console" data-section="console">
                    <i class="bi bi-terminal"></i>
                    <span>Consola</span>
                </a>
                
                <a class="nav-link-custom" href="#scripts" data-section="scripts">
                    <i class="bi bi-file-earmark-code"></i>
                    <span>Scripts</span>
                </a>
                
                <a class="nav-link-custom" href="#json-store" data-section="json-store">
                    <i class="bi bi-file-earmark-code"></i>
                    <span>Almacén JSON</span>
                </a>
            </nav>
            
            <?php if(isset($_SESSION['auth'])): ?>
            <div class="nav-title mt-4">Usuario</div>
            <div class="px-3 py-2">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-person text-white"></i>
                    </div>
                    <div class="ms-2">
                        <div class="text-white fw-bold"><?php echo h($_SESSION['auth']); ?></div>
                        <small class="text-muted">Conectado</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Messages -->
        <?php if(!empty($messages)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <?php foreach($messages as $message): ?>
                        <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> fade-in">
                            <i class="bi bi-<?php echo strpos($message, 'Error') !== false ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
                            <?php echo h($message); ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if(!empty($_SESSION['last_site_url'])): ?>
                        <div class="alert alert-success fade-in">
                            <i class="bi bi-link-45deg me-2"></i>
                            URL del sitio: <a href="<?php echo h($_SESSION['last_site_url']); ?>" target="_blank"><?php echo h($_SESSION['last_site_url']); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Dashboard Section -->
        <div id="section-dashboard" class="section active">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
                    <p class="text-muted">Panel de control de desarrollo y gestión de proyectos</p>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="custom-card stat-card">
                        <div class="stat-number"><?php echo count($_SESSION['projects']); ?></div>
                        <div class="stat-label">Proyectos</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="custom-card stat-card">
                        <div class="stat-number"><?php echo isset($_SESSION['auth']) ? '1' : '0'; ?></div>
                        <div class="stat-label">Sesión Activa</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="custom-card stat-card">
                        <div class="stat-number"><?php echo count($_SESSION['users']); ?></div>
                        <div class="stat-label">Usuarios</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="custom-card stat-card">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Disponibilidad</div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">Acciones Rápidas</h4>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="custom-card">
                        <div class="card-icon icon-bg-primary">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <h5>Nuevo Proyecto</h5>
                        <p class="text-muted mb-3">Crea un nuevo proyecto desde cero</p>
                        <a href="#new-project" class="btn btn-sm btn-gradient" data-section="new-project">Crear</a>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="custom-card">
                        <div class="card-icon icon-bg-success">
                            <i class="bi bi-cloud-arrow-down"></i>
                        </div>
                        <h5>Instalar CMS</h5>
                        <p class="text-muted mb-3">WordPress, Moodle y más</p>
                        <a href="#cms" class="btn btn-sm btn-gradient" data-section="cms">Instalar</a>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="custom-card">
                        <div class="card-icon icon-bg-warning">
                            <i class="bi bi-database"></i>
                        </div>
                        <h5>Base de Datos</h5>
                        <p class="text-muted mb-3">MySQL, SQLite, MongoDB</p>
                        <a href="#databases" class="btn btn-sm btn-gradient" data-section="databases">Crear</a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Projects -->
            <div class="row">
                <div class="col-12">
                    <div class="custom-card">
                        <h4 class="mb-3">Proyectos Recientes</h4>
                        <?php if(empty($_SESSION['projects'])): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-folder-x text-muted" style="font-size: 3rem;"></i>
                                <p class="mt-3 text-muted">No hay proyectos creados todavía</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Tipo</th>
                                            <th>Creado por</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($_SESSION['projects'], -5) as $project): ?>
                                            <tr>
                                                <td><?php echo h($project['name']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo h($project['type']); ?></span></td>
                                                <td><?php echo h($project['created_by']); ?></td>
                                                <td><?php echo h($project['created_at']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Ver</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Projects Section -->
        <div id="section-projects" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-folder me-2"></i>Mis Proyectos</h2>
                    <p class="text-muted">Administra todos tus proyectos de desarrollo</p>
                </div>
            </div>
            
            <div class="custom-card">
                <?php if(empty($_SESSION['projects'])): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-folder-x text-muted" style="font-size: 4rem;"></i>
                        <h4 class="mt-3">No hay proyectos</h4>
                        <p class="text-muted">Comienza creando tu primer proyecto</p>
                        <a href="#new-project" class="btn btn-gradient mt-2" data-section="new-project">
                            <i class="bi bi-plus-circle me-2"></i>Crear Proyecto
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Base de Datos</th>
                                    <th>Sitios</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($_SESSION['projects'] as $id => $project): ?>
                                    <tr>
                                        <td><code><?php echo substr($id, 0, 8); ?>...</code></td>
                                        <td><?php echo h($project['name']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo h($project['type']); ?></span></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo count($project['databases']); ?> DB</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo count($project['sites']); ?> Sites</span>
                                        </td>
                                        <td><?php echo h($project['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php 
                                                    $siteUrl = '';
                                                    if (!empty($project['sites'])) {
                                                        $lastSite = end($project['sites']);
                                                        $siteUrl = $lastSite['url'] ?? '';
                                                    }
                                                ?>
                                                <?php if($siteUrl !== ''): ?>
                                                    <a href="<?php echo h($siteUrl); ?>" target="_blank" class="btn btn-outline-primary">
                                                        Ver sitio
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-secondary" disabled title="Sin sitio">
                                                        Ver sitio
                                                    </button>
                                                <?php endif; ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="project_id" value="<?php echo h($id); ?>">
                                                    <input type="hidden" name="action" value="delete_project">
                                                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('¿Eliminar este proyecto?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- New Project Section -->
        <div id="section-new-project" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-plus-circle me-2"></i>Nuevo Proyecto</h2>
                    <p class="text-muted">Crea un nuevo proyecto de desarrollo</p>
                </div>
            </div>
            
            <?php if(!isset($_SESSION['auth'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Debes iniciar sesión para crear proyectos
                </div>
            <?php else: ?>
                <div class="custom-card">
                    <form method="post">
                        <input type="hidden" name="action" value="create_project">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Proyecto</label>
                                <input type="text" class="form-control form-control-custom" name="project_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Carpeta del Proyecto</label>
                                <input type="text" class="form-control form-control-custom" name="project_folder">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Proyecto</label>
                            <select class="form-select form-control-custom" name="project_type">
                                <option value="wordpress">WordPress</option>
                                <option value="moodle">Moodle</option>
                                <option value="custom">Personalizado</option>
                                <option value="flask">Flask</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control form-control-custom" rows="3" name="description"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-gradient">
                            <i class="bi bi-plus-circle me-2"></i>Crear Proyecto
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['auth'])): ?>
                <div class="custom-card mt-4">
                    <h5 class="mb-3"><i class="bi bi-journal-text me-2"></i>Bases creadas recientemente</h5>
                    <?php $dbs = $_SESSION['databases'] ?? []; ?>
                    <?php if(empty($dbs)): ?>
                        <div class="text-muted">No hay bases registradas aún.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Host</th>
                                        <th>Creado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach(array_reverse($dbs) as $db): ?>
                                        <tr>
                                            <td><code><?php echo h($db['name']); ?></code></td>
                                            <td><span class="badge bg-success"><?php echo h($db['type']); ?></span></td>
                                            <td><?php echo h($db['host']); ?></td>
                                            <td><?php echo h($db['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- CMS Installation Section -->
        <div id="section-cms" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-cloud-arrow-down me-2"></i>Instalar CMS</h2>
                    <p class="text-muted">Instala sistemas de gestión de contenido</p>
                </div>
            </div>
            
            <?php if(!isset($_SESSION['auth'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Debes iniciar sesión para instalar CMS
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="custom-card text-center">
                            <div class="card-icon icon-bg-primary mx-auto">
                                <i class="bi bi-wordpress"></i>
                            </div>
                            <h4>WordPress</h4>
                            <p class="text-muted">El CMS más popular del mundo</p>
                            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#wordpressModal">
                                Instalar
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="custom-card text-center">
                            <div class="card-icon icon-bg-success mx-auto">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                            <h4>Moodle</h4>
                            <p class="text-muted">Plataforma de aprendizaje</p>
                            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#moodleModal">
                                Instalar
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="custom-card text-center">
                            <div class="card-icon icon-bg-warning mx-auto">
                                <i class="bi bi-gear"></i>
                            </div>
                            <h4>Personalizado</h4>
                            <p class="text-muted">Tu propio proyecto</p>
                            <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#customModal">
                                Crear
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- WordPress Modal -->
                <div class="modal fade" id="wordpressModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Instalar WordPress</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form method="post" id="wordpressForm">
                                    <input type="hidden" name="action" value="create_site">
                                    <input type="hidden" name="site_type" value="wordpress">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nombre del Sitio</label>
                                            <input type="text" class="form-control form-control-custom" name="folder_name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email del Administrador</label>
                                            <input type="email" class="form-control form-control-custom" name="admin_email">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Configuración de Base de Datos</label>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="create_db" id="createDb" checked>
                                            <label class="form-check-label" for="createDb">Crear nueva base de datos</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="use_default_connection" id="useDefault">
                                            <label class="form-check-label" for="useDefault">Usar conexión por defecto (localhost/root)</label>
                                        </div>
                                    </div>
                                    
                                    <div class="row" id="dbFields">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nombre de Base de Datos</label>
                                            <input type="text" class="form-control form-control-custom" name="db_name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Usuario</label>
                                            <input type="text" class="form-control form-control-custom" name="db_user" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Contraseña</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control form-control-custom" name="db_pass" id="wpDbPass">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('wpDbPass')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Host</label>
                                            <input type="text" class="form-control form-control-custom" name="db_host" value="localhost">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Seleccionar Proyecto (Opcional)</label>
                                        <select class="form-select form-control-custom" name="project_id">
                                            <option value="">Ninguno</option>
                                            <?php foreach($_SESSION['projects'] as $id => $project): ?>
                                                <option value="<?php echo h($id); ?>"><?php echo h($project['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" form="wordpressForm" class="btn btn-gradient">Instalar WordPress</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Agregar más modales según sea necesario -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="custom-card">
                            <h5><i class="bi bi-filetype-py me-2"></i>Generar Script Python (WP + Moodle)</h5>
                            <p class="text-muted">Crea un script Python que instala WordPress y Moodle automáticamente en Linux o Windows.</p>
                            <form method="post" class="row g-3">
                                <input type="hidden" name="action" value="generate_python_cms">
                                <div class="col-md-4">
                                    <label class="form-label">Usuario BD</label>
                                    <input type="text" class="form-control form-control-custom" name="py_db_user" value="admin">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contraseña BD</label>
                                    <input type="text" class="form-control form-control-custom" name="py_db_pass" value="Admin123!">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Host/IP Linux para wwwroot</label>
                                    <input type="text" class="form-control form-control-custom" name="py_linux_host" placeholder="192.168.0.10" value="<?php echo h($_SERVER['SERVER_ADDR'] ?? 'localhost'); ?>">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="bi bi-code-square me-2"></i>Generar Script
                                    </button>
                                    <div class="small mt-2">Se guardará en <code>scripts/python/install_cms.py</code>. Ejecuta: Linux → <code>sudo python3 scripts/python/install_cms.py</code>, Windows → <code>python scripts\python\install_cms.py</code>.</div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
        
        <!-- Databases Section -->
        <div id="section-databases" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-database me-2"></i>Bases de Datos</h2>
                    <p class="text-muted">Crea y gestiona bases de datos</p>
                </div>
            </div>
            
            <?php if(!isset($_SESSION['auth'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Debes iniciar sesión para crear bases de datos
                </div>
            <?php else: ?>
                <div class="custom-card">
                    <ul class="nav nav-tabs-custom" id="dbTabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mysqlTab">MySQL</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sqliteTab">SQLite</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mongodbTab">MongoDB</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content pt-4">
                        <!-- MySQL Tab -->
                        <div class="tab-pane fade show active" id="mysqlTab">
                            <form method="post">
                                <input type="hidden" name="action" value="create_database">
                                <input type="hidden" name="db_type" value="mysql">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nombre de Base de Datos</label>
                                        <input type="text" class="form-control form-control-custom" name="db_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Host</label>
                                        <input type="text" class="form-control form-control-custom" name="db_host" value="localhost">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Usuario</label>
                                        <input type="text" class="form-control form-control-custom" name="db_user" value="root">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Contraseña</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control form-control-custom" name="db_pass" id="mysqlPass">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('mysqlPass')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Puerto</label>
                                        <input type="number" class="form-control form-control-custom" name="db_port" value="3306">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="use_default_connection" id="mysqlDefault">
                                        <label class="form-check-label" for="mysqlDefault">Usar conexión por defecto (localhost/root)</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Tablas a crear (separadas por comas)</label>
                                    <input type="text" class="form-control form-control-custom" name="tables" placeholder="users, products, orders">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Seleccionar Proyecto (Opcional)</label>
                                    <select class="form-select form-control-custom" name="project_id">
                                        <option value="">Ninguno</option>
                                        <?php foreach($_SESSION['projects'] as $id => $project): ?>
                                            <option value="<?php echo h($id); ?>"><?php echo h($project['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient">
                                    <i class="bi bi-plus-circle me-2"></i>Crear Base de Datos
                                </button>
                            </form>
                        </div>
                        
                        <!-- SQLite Tab -->
                        <div class="tab-pane fade" id="sqliteTab">
                            <form method="post">
                                <input type="hidden" name="action" value="create_database">
                                <input type="hidden" name="db_type" value="sqlite">
                                
                                <div class="mb-3">
                                    <label class="form-label">Nombre de Archivo</label>
                                    <input type="text" class="form-control form-control-custom" name="db_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Ruta del Archivo (opcional)</label>
                                    <input type="text" class="form-control form-control-custom" name="sqlite_path" placeholder="/ruta/a/tu/directorio">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Seleccionar Proyecto (Opcional)</label>
                                    <select class="form-select form-control-custom" name="project_id">
                                        <option value="">Ninguno</option>
                                        <?php foreach($_SESSION['projects'] as $id => $project): ?>
                                            <option value="<?php echo h($id); ?>"><?php echo h($project['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient">
                                    <i class="bi bi-plus-circle me-2"></i>Crear Base de Datos SQLite
                                </button>
                            </form>
                        </div>
                        
                        <!-- MongoDB Tab -->
                        <div class="tab-pane fade" id="mongodbTab">
                            <form method="post">
                                <input type="hidden" name="action" value="create_database">
                                <input type="hidden" name="db_type" value="mongodb">
                                
                                <div class="mb-3">
                                    <label class="form-label">Nombre de Base de Datos</label>
                                    <input type="text" class="form-control form-control-custom" name="db_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">URI de Conexión MongoDB</label>
                                    <input type="text" class="form-control form-control-custom" name="mongo_uri" value="mongodb://localhost:27017">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nombre de Colección Inicial</label>
                                    <input type="text" class="form-control form-control-custom" name="mongo_collection" value="init">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Seleccionar Proyecto (Opcional)</label>
                                    <select class="form-select form-control-custom" name="project_id">
                                        <option value="">Ninguno</option>
                                        <?php foreach($_SESSION['projects'] as $id => $project): ?>
                                            <option value="<?php echo h($id); ?>"><?php echo h($project['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient">
                                    <i class="bi bi-plus-circle me-2"></i>Crear Base de Datos MongoDB
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Console Section -->
        <div id="section-console" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-terminal me-2"></i>Consola del Sistema</h2>
                    <p class="text-muted">Ejecuta comandos del sistema</p>
                </div>
            </div>
            
            <?php if(!isset($_SESSION['auth'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Debes iniciar sesión para usar la consola
                </div>
            <?php else: ?>
                <div class="custom-card">
                    <div class="console-container">
                        <div class="console-header">
                            <div class="console-title">
                                <i class="bi bi-terminal me-2"></i>
                                Terminal - DevPanel Pro
                            </div>
                            <div>
                                <span class="badge bg-success"><?php echo php_uname('s'); ?></span>
                            </div>
                        </div>
                        
                        <div class="console-output" id="consoleOutput">
                            <?php 
                            $consoleOutput = $_SESSION['console_output'] ?? "DevPanel Pro Console v1.0\nSistema: " . php_uname('s') . "\nUsuario: " . ($_SESSION['auth'] ?? 'Invitado') . "\n\nEscribe 'help' para ver comandos disponibles.\n==========================================\n";
                            echo nl2br(h($consoleOutput));
                            ?>
                        </div>
                        
                        <div class="p-3 border-top border-secondary">
                            <form method="post" id="consoleForm">
                                <input type="hidden" name="action" value="run_command">
                                <input type="hidden" name="os" id="osType" value="<?php echo h($_SESSION['console_os'] ?? 'linux'); ?>">
                                
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-white border-secondary">
                                        $
                                    </span>
                                    <input type="text" class="form-control form-control-custom border-secondary" 
                                           name="command" id="commandInput" 
                                           placeholder="Escribe un comando..." autocomplete="off">
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="bi bi-play-fill"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="mb-3">Comandos Rápidos</h5>
                        <div class="row g-2">
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-info" onclick="setCommand('ls -la')">
                                    <i class="bi bi-list"></i> ls -la
                                </button>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-info" onclick="setCommand('pwd')">
                                    <i class="bi bi-folder"></i> pwd
                                </button>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-info" onclick="setCommand('whoami')">
                                    <i class="bi bi-person"></i> whoami
                                </button>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-info" onclick="setCommand('date')">
                                    <i class="bi bi-calendar"></i> date
                                </button>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-info" onclick="setCommand('clear')">
                                    <i class="bi bi-eraser"></i> clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Scripts Section -->
        <div id="section-scripts" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-file-earmark-code me-2"></i>Scripts</h2>
                    <p class="text-muted">Visualiza y gestiona scripts generados</p>
                </div>
            </div>

            <?php if(!isset($_SESSION['auth'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Debes iniciar sesión para ver scripts
                </div>
            <?php else: ?>
                <div class="custom-card">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-info">Directorio</span>
                            <code>scripts</code>
                        </div>
                        <a href="#cms" class="btn btn-sm btn-outline-primary" data-section="cms">
                            <i class="bi bi-tools me-2"></i>Generar más scripts
                        </a>
                    </div>

                    <?php 
                        $scriptsDir = __DIR__ . DIRECTORY_SEPARATOR . 'scripts';
                        $files = [];
                        if (is_dir($scriptsDir)) {
                            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scriptsDir, FilesystemIterator::SKIP_DOTS));
                            foreach ($it as $fi) {
                                if ($fi->isFile()) {
                                    $rel = substr($fi->getPathname(), strlen($scriptsDir) + 1);
                                    $files[] = $rel;
                                }
                            }
                        }
                    ?>

                    <?php if(empty($files)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-x text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">No hay scripts</h4>
                            <p class="text-muted">Genera el primero desde la sección CMS</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Archivo</th>
                                        <th>Tamaño</th>
                                        <th>Modificado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($files as $fname): $full = $scriptsDir . DIRECTORY_SEPARATOR . $fname; ?>
                                        <tr>
                                            <td><code><?php echo h($fname); ?></code></td>
                                            <td><?php echo number_format(filesize($full)); ?> bytes</td>
                                            <td><?php echo date('Y-m-d H:i', filemtime($full)); ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="view_script">
                                                    <input type="hidden" name="script_name" value="<?php echo h($fname); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </button>
                                                </form>
                                                <?php if(strtolower(pathinfo($fname, PATHINFO_EXTENSION)) === 'py'): ?>
                                                <form method="post" class="d-inline ms-2">
                                                    <input type="hidden" name="action" value="run_script">
                                                    <input type="hidden" name="script_name" value="<?php echo h($fname); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-play"></i> Ejecutar
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if(!empty($_SESSION['script_view_content'])): ?>
                    <div class="custom-card mt-4">
                        <h5 class="mb-3"><i class="bi bi-eye me-2"></i>Contenido: <code><?php echo h($_SESSION['script_view_file']); ?></code></h5>
                        <pre class="console-output" style="min-height: 300px; max-height: 600px; color: #ddd; background:#111; white-space: pre;"><?php echo h($_SESSION['script_view_content']); ?></pre>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($_SESSION['script_run_output'])): ?>
                    <div class="custom-card mt-4">
                        <h5 class="mb-3"><i class="bi bi-terminal me-2"></i>Salida: <code><?php echo h($_SESSION['script_run_file']); ?></code></h5>
                        <pre class="console-output" style="min-height: 200px; max-height: 500px; color: #0f0; background:#000; white-space: pre;"><?php echo h($_SESSION['script_run_output']); ?></pre>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Flask Section -->
        <div id="section-flask" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-braces me-2"></i>Crear App Flask</h2>
                    <p class="text-muted">Genera una aplicación Flask con estructura básica</p>
                </div>
            </div>
            
            <?php if(!isset($_SESSION['auth'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Debes iniciar sesión para crear apps Flask
                </div>
            <?php else: ?>
                <div class="custom-card">
                    <form method="post">
                        <input type="hidden" name="action" value="create_flask_site">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre de Carpeta</label>
                                <input type="text" class="form-control form-control-custom" name="flask_folder" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ruta Base (opcional)</label>
                                <input type="text" class="form-control form-control-custom" name="base_path" placeholder="/var/www/html/sites">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Páginas a crear (separadas por comas)</label>
                            <input type="text" class="form-control form-control-custom" name="pages" value="home,about,contact,admin">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tema de Color</label>
                                <select class="form-select form-control-custom" name="theme">
                                    <option value="violet">Violeta</option>
                                    <option value="teal">Verde Azulado</option>
                                    <option value="blue">Azul</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="use_bootstrap" id="useBootstrap" checked>
                                    <label class="form-check-label" for="useBootstrap">Incluir Bootstrap 5</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-gradient">
                            <i class="bi bi-braces me-2"></i>Crear App Flask
                        </button>
                    </form>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="custom-card">
                            <h5><i class="bi bi-info-circle me-2"></i>Estructura Flask</h5>
                            <pre class="bg-dark p-3 rounded text-white">
/flask-app
├── app.py
├── requirements.txt
├── static/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
└── templates/
    ├── base.html
    ├── index.html
    └── [otras páginas].html</pre>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="custom-card">
                            <h5><i class="bi bi-lightning me-2"></i>Características</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Estructura MVC básica</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Sistema de plantillas</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Archivos estáticos organizados</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Requisitos listos (requirements.txt)</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Navegación automática</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- JSON Store Section -->
        <div id="section-json-store" class="section" style="display: none;">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold"><i class="bi bi-file-earmark-code me-2"></i>Almacén JSON</h2>
                    <p class="text-muted">Crea almacenes de datos basados en JSON</p>
                </div>
            </div>
            
            <?php if(!isset($_SESSION['auth'])): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Debes iniciar sesión para crear almacenes JSON
                </div>
            <?php else: ?>
                <div class="custom-card">
                    <form method="post">
                        <input type="hidden" name="action" value="create_json_store">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Almacén</label>
                                <input type="text" class="form-control form-control-custom" name="store_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ruta (opcional)</label>
                                <input type="text" class="form-control form-control-custom" name="store_path" placeholder="/ruta/a/tu/directorio">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-gradient">
                            <i class="bi bi-plus-circle me-2"></i>Crear Almacén JSON
                        </button>
                    </form>
                </div>
                
                <div class="custom-card mt-4">
                    <h5><i class="bi bi-folder me-2"></i>Estructura del Almacén</h5>
                    <pre class="bg-dark p-3 rounded text-white">
/almacen-json
├── metadata.json
├── data.json
├── users.json
├── products.json
└── logs.json</pre>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Auth Modal -->
        <?php if(!isset($_SESSION['auth'])): ?>
            <div class="modal fade" id="authModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Iniciar Sesión</h5>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs-custom" id="authTabs">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#loginTab">Iniciar Sesión</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#registerTab">Registrarse</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content pt-4">
                                <div class="tab-pane fade show active" id="loginTab">
                                    <form method="post" id="loginForm">
                                        <input type="hidden" name="action" value="login">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Usuario</label>
                                            <input type="text" class="form-control form-control-custom" name="username" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Contraseña</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control form-control-custom" name="password" required id="loginPassword">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('loginPassword')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">Credenciales por defecto: admin / admin123</small>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="tab-pane fade" id="registerTab">
                                    <form method="post" id="registerForm">
                                        <input type="hidden" name="action" value="register">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Usuario</label>
                                            <input type="text" class="form-control form-control-custom" name="username" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Contraseña</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control form-control-custom" name="password" required id="registerPassword">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('registerPassword')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div class="w-100 d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="closeAuthModal()">Cancelar</button>
                                <div>
                                    <button type="submit" form="loginForm" class="btn btn-gradient" id="loginBtn">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                                    </button>
                                    <button type="submit" form="registerForm" class="btn btn-outline-primary" id="registerBtn" style="display: none;">
                                        <i class="bi bi-person-plus me-2"></i>Registrarse
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // Section navigation
        document.querySelectorAll('.nav-link-custom, [data-section]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Close mobile menu
                document.getElementById('sidebar').classList.remove('show');
                
                const sectionId = this.getAttribute('data-section') || this.getAttribute('href').substring(1);
                showSection(sectionId);
                
                // Update active nav link
                document.querySelectorAll('.nav-link-custom').forEach(function(navLink) {
                    navLink.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
        
        // Show section function
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(function(section) {
                section.style.display = 'none';
            });
            
            // Show target section
            const targetSection = document.getElementById('section-' + sectionId);
            if (targetSection) {
                targetSection.style.display = 'block';
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Focus on first input if any
                const firstInput = targetSection.querySelector('input, textarea, select');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 300);
                }
            }
        }
        
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Set command in console input
        function setCommand(command) {
            const input = document.getElementById('commandInput');
            if (input) {
                input.value = command;
                input.focus();
            }
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.classList.add('fade');
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
        
        // Auth modal handling
        <?php if(!isset($_SESSION['auth'])): ?>
            // Show auth modal on page load
            document.addEventListener('DOMContentLoaded', function() {
                const authModal = new bootstrap.Modal(document.getElementById('authModal'));
                authModal.show();
            });
            
            // Switch between login/register tabs
            document.querySelectorAll('#authTabs button').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    const target = this.getAttribute('data-bs-target');
                    
                    if (target === '#loginTab') {
                        document.getElementById('loginBtn').style.display = 'inline-block';
                        document.getElementById('registerBtn').style.display = 'none';
                    } else {
                        document.getElementById('loginBtn').style.display = 'none';
                        document.getElementById('registerBtn').style.display = 'inline-block';
                    }
                });
            });
            
            // Prevent closing auth modal
            function closeAuthModal() {
                // Can't close without login
                return false;
            }
        <?php endif; ?>
        
        // Handle console form submission
        const consoleForm = document.getElementById('consoleForm');
        if (consoleForm) {
            consoleForm.addEventListener('submit', function(e) {
                const command = document.getElementById('commandInput').value.trim();
                
                if (command.toLowerCase() === 'help') {
                    e.preventDefault();
                    const output = document.getElementById('consoleOutput');
                    output.innerHTML += '\n\nComandos disponibles:\n' +
                        '----------------------------------------\n' +
                        'ls, pwd, whoami, date      - Comandos básicos\n' +
                        'clear                      - Limpiar consola\n' +
                        'help                       - Mostrar esta ayuda\n' +
                        '----------------------------------------\n';
                    output.scrollTop = output.scrollHeight;
                    document.getElementById('commandInput').value = '';
                } else if (command.toLowerCase() === 'clear') {
                    e.preventDefault();
                    document.getElementById('consoleOutput').innerHTML = 'Consola limpiada.<br>==========================================';
                    document.getElementById('commandInput').value = '';
                }
            });
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Handle use default connection checkbox
        const useDefaultCheckbox = document.getElementById('useDefault');
        const dbFields = document.getElementById('dbFields');
        
        if (useDefaultCheckbox && dbFields) {
            useDefaultCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    dbFields.style.opacity = '0.5';
                    dbFields.querySelectorAll('input').forEach(function(input) {
                        input.disabled = true;
                    });
                } else {
                    dbFields.style.opacity = '1';
                    dbFields.querySelectorAll('input').forEach(function(input) {
                        input.disabled = false;
                    });
                }
            });
            
            // Trigger change on load
            useDefaultCheckbox.dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>

<?php
// Tus funciones PHP aquí (mantenidas igual que en tu código original)
// ... [todas tus funciones PHP existentes]
?>
