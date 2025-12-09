<?php
// Navbar component mejorado
?>
<div class="navbar navbar-expand-lg bg-white border-bottom shadow-sm sticky-top">
    <div class="container-fluid px-4">
        <!-- Brand Logo -->
        <div class="brand d-flex align-items-center">
            <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="#">
                <div class="brand-icon d-flex align-items-center justify-content-center" 
                     style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px;">
                    <i class="fas fa-cube text-white"></i>
                </div>
                <div class="d-flex flex-column">
                    <span class="fw-bold fs-5 text-dark" style="letter-spacing: -0.5px;">DevPanel Pro</span>
                    <small class="text-muted" style="font-size: 11px; margin-top: -5px;">Development Dashboard</small>
                </div>
            </a>
        </div>
        
        <!-- User Actions -->
        <div class="nav-actions d-flex align-items-center gap-3">
            <!-- Notifications (opcional) -->
            <div class="position-relative">
                <button class="btn btn-outline-secondary btn-sm p-2 rounded-circle" 
                        type="button" 
                        style="width: 40px; height: 40px;">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                          style="font-size: 10px; padding: 2px 5px;">
                        3
                    </span>
                </button>
            </div>
            
            <!-- User Dropdown -->
            <div class="position-relative">
                <button type="button" 
                        class="user-button btn d-flex align-items-center gap-2 p-2" 
                        id="userDropdownBtn"
                        style="
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            border: none;
                            border-radius: 12px;
                            padding: 8px 16px;
                            transition: all 0.3s ease;
                            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
                        ">
                    <div class="user-avatar d-flex align-items-center justify-content-center" 
                         style="
                            width: 32px;
                            height: 32px;
                            background: rgba(255, 255, 255, 0.2);
                            border-radius: 50%;
                            backdrop-filter: blur(10px);
                         ">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="d-flex flex-column align-items-start">
                        <span class="fw-medium" style="font-size: 14px; line-height: 1.2;">
                            <?php echo $auth ? h($_SESSION['auth']) : 'Cuenta'; ?>
                        </span>
                        <small style="font-size: 11px; opacity: 0.9; line-height: 1;">
                            <?php echo $auth ? 'Sesión activa' : 'No autenticado'; ?>
                        </small>
                    </div>
                    <i class="fas fa-chevron-down ms-1" style="font-size: 12px; transition: transform 0.3s ease;"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown shadow-lg" 
                     id="userDropdown" 
                     style="
                        position: absolute;
                        top: 100%;
                        right: 0;
                        margin-top: 12px;
                        width: 320px;
                        background: white;
                        border-radius: 16px;
                        border: 1px solid rgba(0, 0, 0, 0.08);
                        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.12);
                        opacity: 0;
                        visibility: hidden;
                        transform: translateY(-10px);
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        z-index: 1050;
                        overflow: hidden;
                     ">
                    <?php if(!$auth): ?>
                    <!-- Tabs para Login/Registro -->
                    <div class="tabs d-flex" style="background: #f8fafc; border-bottom: 1px solid #e5e7eb;">
                        <div class="tab flex-fill text-center py-3 fw-medium" 
                             data-tab="loginTab"
                             style="
                                cursor: pointer;
                                color: #6b7280;
                                transition: all 0.2s ease;
                                border-bottom: 3px solid transparent;
                             ">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </div>
                        <div class="tab flex-fill text-center py-3 fw-medium" 
                             data-tab="registerTab"
                             style="
                                cursor: pointer;
                                color: #6b7280;
                                transition: all 0.2s ease;
                                border-bottom: 3px solid transparent;
                             ">
                            <i class="fas fa-user-plus me-2"></i>Registro
                        </div>
                    </div>
                    
                    <!-- Login Tab -->
                    <div id="loginTab" class="tab-content p-4">
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label class="form-label fw-medium text-dark mb-2" style="font-size: 14px;">
                                    <i class="fas fa-user me-1"></i>Usuario
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background: #f8fafc; border-color: #e5e7eb;">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           name="username" 
                                           class="form-control" 
                                           placeholder="nombre@ejemplo.com"
                                           required
                                           style="border-color: #e5e7eb; padding: 10px 12px;">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-medium text-dark mb-2" style="font-size: 14px;">
                                    <i class="fas fa-lock me-1"></i>Contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background: #f8fafc; border-color: #e5e7eb;">
                                        <i class="fas fa-key text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           id="login_password" 
                                           name="password" 
                                           class="form-control" 
                                           placeholder="••••••••"
                                           required
                                           style="border-color: #e5e7eb; padding: 10px 12px;">
                                    <button type="button" 
                                            class="btn btn-outline-secondary password-toggle" 
                                            data-target="login_password"
                                            style="border-color: #e5e7eb;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text text-end mt-1" style="font-size: 12px;">
                                    <a href="#" class="text-decoration-none" style="color: #667eea;">¿Olvidaste tu contraseña?</a>
                                </div>
                            </div>
                            <button class="btn btn-primary w-100 py-2 fw-medium" 
                                    type="submit"
                                    style="
                                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                        border: none;
                                        border-radius: 10px;
                                        padding: 12px;
                                        transition: transform 0.2s ease;
                                    ">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                        </form>
                    </div>
                    
                    <!-- Register Tab -->
                    <div id="registerTab" class="tab-content p-4" style="display: none;">
                        <form method="post">
                            <input type="hidden" name="action" value="register">
                            <div class="mb-3">
                                <label class="form-label fw-medium text-dark mb-2" style="font-size: 14px;">
                                    <i class="fas fa-user me-1"></i>Nuevo usuario
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background: #f8fafc; border-color: #e5e7eb;">
                                        <i class="fas fa-user-plus text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           name="username" 
                                           class="form-control" 
                                           placeholder="nombre@ejemplo.com"
                                           required
                                           style="border-color: #e5e7eb; padding: 10px 12px;">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-medium text-dark mb-2" style="font-size: 14px;">
                                    <i class="fas fa-lock me-1"></i>Nueva contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background: #f8fafc; border-color: #e5e7eb;">
                                        <i class="fas fa-key text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           id="register_password" 
                                           name="password" 
                                           class="form-control" 
                                           placeholder="••••••••"
                                           required
                                           style="border-color: #e5e7eb; padding: 10px 12px;">
                                    <button type="button" 
                                            class="btn btn-outline-secondary password-toggle" 
                                            data-target="register_password"
                                            style="border-color: #e5e7eb;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text" style="font-size: 12px;">
                                    <i class="fas fa-info-circle me-1"></i>Mínimo 8 caracteres
                                </div>
                            </div>
                            <button class="btn btn-outline-primary w-100 py-2 fw-medium" 
                                    type="submit"
                                    style="
                                        border-color: #667eea;
                                        color: #667eea;
                                        border-radius: 10px;
                                        padding: 12px;
                                        transition: all 0.2s ease;
                                    ">
                                <i class="fas fa-user-plus me-2"></i>Crear Cuenta
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <!-- Usuario Logueado -->
                    <div class="p-4">
                        <!-- User Info -->
                        <div class="d-flex align-items-center mb-4">
                            <div class="user-avatar-large d-flex align-items-center justify-content-center me-3"
                                 style="
                                    width: 64px;
                                    height: 64px;
                                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                    border-radius: 50%;
                                    color: white;
                                    font-size: 24px;
                                 ">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold text-dark"><?php echo h($_SESSION['auth']); ?></h5>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill" 
                                          style="background: #d1fae5; color: #065f46; font-size: 11px; padding: 4px 8px;">
                                        <i class="fas fa-circle me-1" style="font-size: 8px;"></i>En línea
                                    </span>
                                    <span class="text-muted" style="font-size: 12px;">
                                        <i class="fas fa-clock me-1"></i><?php echo date('H:i'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Menu Items -->
                        <div class="mb-3">
                            <a href="#" class="d-flex align-items-center py-2 px-3 text-decoration-none rounded-2 mb-1"
                               style="color: #4b5563; transition: all 0.2s ease; background: #f8fafc;">
                                <i class="fas fa-user me-3" style="color: #667eea;"></i>
                                <span>Mi perfil</span>
                            </a>
                            <a href="#" class="d-flex align-items-center py-2 px-3 text-decoration-none rounded-2 mb-1"
                               style="color: #4b5563; transition: all 0.2s ease;">
                                <i class="fas fa-cog me-3" style="color: #667eea;"></i>
                                <span>Configuración</span>
                            </a>
                            <a href="#" class="d-flex align-items-center py-2 px-3 text-decoration-none rounded-2 mb-1"
                               style="color: #4b5563; transition: all 0.2s ease;">
                                <i class="fas fa-shield-alt me-3" style="color: #667eea;"></i>
                                <span>Seguridad</span>
                            </a>
                            <div class="border-top my-3"></div>
                            <form method="post">
                                <input type="hidden" name="action" value="logout">
                                <button class="btn btn-outline-danger w-100 py-2 d-flex align-items-center justify-content-center"
                                        type="submit"
                                        style="border-radius: 10px; border-color: #ef4444; color: #ef4444;">
                                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                                </button>
                            </form>
                        </div>
                        
                        <!-- Footer -->
                        <div class="text-center mt-3 pt-3 border-top" style="font-size: 12px;">
                            <div class="text-muted mb-1">Último acceso: Hoy</div>
                            <div class="text-muted">
                                <i class="fas fa-globe-americas me-1"></i>
                                <?php echo $_SERVER['REMOTE_ADDR'] ?? 'IP no disponible'; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos adicionales para el navbar */
.navbar {
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.98) !important;
    height: 72px;
    transition: all 0.3s ease;
}

.brand-icon {
    transition: transform 0.3s ease;
}

.brand-icon:hover {
    transform: rotate(15deg);
}

.user-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3) !important;
}

.user-button.active {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%) !important;
}

.user-button.active i.fa-chevron-down {
    transform: rotate(180deg);
}

.dropdown.show {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) !important;
}

.tab.active {
    color: #667eea !important;
    border-bottom-color: #667eea !important;
    background: white;
}

.tab:hover:not(.active) {
    color: #4b5563 !important;
    background: rgba(0, 0, 0, 0.02);
}

.tab-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-control:focus {
    border-color: #667eea !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
}

.btn-outline-primary:hover {
    background: #667eea !important;
    color: white !important;
}

.user-avatar-large {
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.badge {
    font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
    .navbar {
        height: 64px;
        padding: 0 16px;
    }
    
    .dropdown {
        width: calc(100vw - 32px) !important;
        right: 16px !important;
        position: fixed !important;
    }
    
    .brand .d-flex.flex-column span {
        font-size: 1.1rem;
    }
    
    .brand .d-flex.flex-column small {
        display: none;
    }
}
</style>

<script>
// JavaScript para manejar el dropdown
document.addEventListener('DOMContentLoaded', function() {
    const userButton = document.getElementById('userDropdownBtn');
    const dropdown = document.getElementById('userDropdown');
    const chevron = userButton?.querySelector('.fa-chevron-down');
    
    if (userButton && dropdown) {
        // Toggle dropdown
        userButton.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            userButton.classList.toggle('active');
            
            if (chevron) {
                chevron.style.transform = dropdown.classList.contains('show') 
                    ? 'rotate(180deg)' 
                    : 'rotate(0deg)';
            }
        });
        
        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !userButton.contains(e.target)) {
                dropdown.classList.remove('show');
                userButton.classList.remove('active');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
        });
        
        // Manejo de tabs
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Actualizar tabs activos
                tabs.forEach(t => {
                    t.classList.remove('active');
                    const contentId = t.getAttribute('data-tab');
                    const content = document.getElementById(contentId);
                    if (content) content.style.display = 'none';
                });
                
                this.classList.add('active');
                const activeContent = document.getElementById(tabId);
                if (activeContent) activeContent.style.display = 'block';
            });
        });
        
        // Password toggle
        document.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }
});
</script>