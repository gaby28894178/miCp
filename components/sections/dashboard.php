<?php
// Dashboard view component
?>
<div class="card">
    <div class="card-title">Dashboard Principal</div>
    <?php if(!$auth): ?>
    <div class="empty-state">
        <i class="fas fa-lock"></i>
        <h3>No has iniciado sesión</h3>
        <p>Inicia sesión o regístrate para acceder a todas las funciones del panel.</p>
    </div>
    <?php else: ?>
    <div class="dashboard-grid">
        <div class="project-card">
            <div class="project-header">
                <div class="project-title">Bienvenido, <?php echo h($current_user); ?>!</div>
            </div>
            <div class="project-info">
                <p>Gestiona tus proyectos, bases de datos, sitios web y servidores desde este panel unificado.</p>
            </div>
            <div class="project-stats">
                <div class="stat">
                    <div class="stat-value"><?php echo count($_SESSION['projects']); ?></div>
                    <div class="stat-label">Proyectos</div>
                </div>
                <div class="stat">
                    <div class="stat-value">
                        <?php 
                        $total_dbs = 0;
                        foreach($_SESSION['projects'] as $project) {
                            $total_dbs += count($project['databases']);
                        }
                        echo $total_dbs;
                        ?>
                    </div>
                    <div class="stat-label">Bases</div>
                </div>
                <div class="stat">
                    <div class="stat-value">
                        <?php 
                        $total_sites = 0;
                        foreach($_SESSION['projects'] as $project) {
                            $total_sites += count($project['sites']);
                        }
                        echo $total_sites;
                        ?>
                    </div>
                    <div class="stat-label">Sitios</div>
                </div>
            </div>
        </div>
        <div class="project-card">
            <div class="project-header">
                <div class="project-title">Acciones Rápidas</div>
            </div>
            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                <button class="primary btn-icon" onclick="showSection('section-new-project')">
                    <i class="fas fa-plus"></i> Crear Proyecto
                </button>
                <button class="secondary btn-icon" onclick="showSection('section-db')">
                    <i class="fas fa-database"></i> Nueva Base de Datos
                </button>
                <button class="secondary btn-icon" onclick="showSection('section-install')">
                    <i class="fas fa-cloud-download-alt"></i> Instalar WordPress
                </button>
                <button class="secondary btn-icon" onclick="showSection('section-console')">
                    <i class="fas fa-terminal"></i> Consola de Comandos
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
