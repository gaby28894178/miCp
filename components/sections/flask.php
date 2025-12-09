<?php
$defaultBasePath = default_base_path();
?>
<div class="card">
    <div class="card-title"><i class="fas fa-fire"></i> Crear App Flask</div>
    <form method="post">
        <input type="hidden" name="action" value="create_flask_site">
        <div class="grid">
            <div class="form-group">
                <label>Nombre de Carpeta *</label>
                <input type="text" name="flask_folder" placeholder="mi-flask-app" required>
            </div>
            <div class="form-group">
                <label>Ruta Base de Instalación</label>
                <input type="text" name="base_path" placeholder="<?php echo h($defaultBasePath); ?>">
                <div class="small">Vacío = usa valor por defecto: <?php echo h($defaultBasePath); ?></div>
            </div>
        </div>
        <div class="grid">
            <div class="form-group">
                <label>Páginas (coma separadas)</label>
                <input type="text" name="pages" placeholder="home,about,contact" value="home,about,contact">
            </div>
            <div class="form-group">
                <label>Tema</label>
                <select name="theme">
                    <option value="violet">Violeta</option>
                    <option value="blue">Azul</option>
                    <option value="teal">Verde Agua</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="use_bootstrap" checked> Usar estilos Bootstrap en el frontend</label>
        </div>
        <button class="primary" type="submit">
            <i class="fas fa-play"></i> Generar App Flask
        </button>
    </form>
</div>
