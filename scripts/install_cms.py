import os
import platform
import subprocess
import shutil

DB_USER = 'admin'
DB_PASS = 'Admin123!'
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
$CFG->wwwroot   = 'http://::1/cursos/moodle';
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