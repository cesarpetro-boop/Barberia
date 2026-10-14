<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';

if (estaLogueado()) {
    $u = usuarioActual();
    if ($u) {
        audit(pdo(), 'logout', 'usuarios', $u['id'], 'Cerró sesión');
    }
}
logout();
redirect('login.php');
