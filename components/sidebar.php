<?php
// Sidebar component
?>
<div class="sidebar bg-white border-end">
    <div class="card">
        <div class="card-title">Navegación</div>
        <ul class="side-menu list-group list-group-flush">
            <li class="active list-group-item" data-section="section-dashboard">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </li>
            <li class="list-group-item" data-section="section-projects">
                <i class="fas fa-folder"></i> Mis Proyectos
            </li>
            <li class="list-group-item" data-section="section-new-project">
                <i class="fas fa-plus-circle"></i> Nuevo Proyecto
            </li>
            <li class="list-group-item" data-section="section-install">
                <i class="fas fa-cloud-download-alt"></i> Instalar CMS
            </li>
            <li class="list-group-item" data-section="section-flask">
                <i class="fas fa-fire"></i> Crear App Flask
            </li>
            <li class="list-group-item" data-section="section-db">
                <i class="fas fa-database"></i> Bases de Datos
            </li>
            <li class="list-group-item" data-section="section-nosql">
                <i class="fas fa-json"></i> Almacenes JSON
            </li>
            <li class="list-group-item" data-section="section-scripts">
                <i class="fas fa-file-code"></i> Scripts
            </li>
        </ul>
    </div>
    <?php if($auth && !empty($_SESSION['projects'])): ?>
    <div class="card">
        <div class="card-title">Proyectos Recientes</div>
        <ul class="side-menu list-group list-group-flush">
            <?php 
            $recent_projects = array_slice($_SESSION['projects'], -3, 3, true);
            foreach($recent_projects as $project): ?>
            <li class="list-group-item" onclick="showProject('<?php echo $project['id']; ?>')">
                <i class="fas fa-folder"></i> <?php echo h($project['name']); ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

