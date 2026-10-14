<?php
/**
 * Autenticación y roles de usuario.
 * Actores: administrador (acceso total) y barbero.
 */

/** Inicia sesión */
function login($usuario) {
    $_SESSION['user_id'] = (int)$usuario['id'];
    $_SESSION['user_rol'] = $usuario['rol'];
    $_SESSION['user_nombre'] = $usuario['nombre'];
    $_SESSION['user_barbero_id'] = $usuario['barbero_id'] ? (int)$usuario['barbero_id'] : null;
}

/** Cierra sesión */
function logout() {
    $_SESSION = [];
    session_destroy();
}

/** Usuario actual como array (o null) */
function usuarioActual() {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null && (int)$cache['id'] === (int)$_SESSION['user_id']) return $cache;
    try {
        $pdo = pdo();
        $st = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
        $st->execute([(int)$_SESSION['user_id']]);
        $cache = $st->fetch() ?: false;
    } catch (Exception $ex) {
        $cache = false;
    }
    return $cache ?: null;
}

/** Rol actual */
function rolActual() {
    return $_SESSION['user_rol'] ?? null;
}

/** ¿Está logueado? */
function estaLogueado() {
    return !empty($_SESSION['user_id']);
}

/**
 * Exige que el usuario esté logueado (y opcionalmente con alguno de los roles).
 * - El administrador TIENE TODOS LOS PRIVILEGIOS: supera cualquier filtro de roles.
 * - Los privilegios se releen de la BD en cada petición: desactivar un usuario
 *   o cambiarle el rol surte efecto inmediato, sin esperar a un nuevo login.
 */
function requerir_login(...$roles) {
    if (!estaLogueado()) {
        redirect('login.php');
    }
    $u = usuarioActual();
    if ($u === null) {
        logout();               // cuenta eliminada o desactivada
        redirect('login.php');
    }
    $_SESSION['user_rol'] = $u['rol'];
    $_SESSION['user_barbero_id'] = $u['barbero_id'] ? (int)$u['barbero_id'] : null;

    if ($roles && esRol('admin')) {
        return; // admin: acceso total, sin importar qué roles exija la página
    }
    if ($roles && !in_array(rolActual(), $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta página.');
    }
}

/** Verdadero si el usuario es del rol indicado */
function esRol($rol) {
    return rolActual() === $rol;
}

/**
 * Para barberos: fuerza el filtro a su propio barbero_id.
 * Devuelve el barbero_id que debe verse, o 0 para "todos".
 */
function barberoSegunRol() {
    if (esRol('barbero')) {
        return (int)($_SESSION['user_barbero_id'] ?? 0);
    }
    return 0;
}
