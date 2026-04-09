<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}
// Verificamos que los datos lleguen por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. Limpiamos las variables
    $id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
    tenant_inmob_asegurar_esquema($conexion);
    if ($id > 0 && !tenant_inmob_usuario_id_visible($conexion, $id)) {
        die('Sin permiso');
    }
    $apellido  = mysqli_real_escape_string($conexion, trim($_POST['apellido'] ?? ''));
    $dni       = mysqli_real_escape_string($conexion, trim($_POST['dni'] ?? ''));
    $cuit      = mysqli_real_escape_string($conexion, trim($_POST['cuit'] ?? ''));
    $domicilio = mysqli_real_escape_string($conexion, trim($_POST['domicilio'] ?? ''));
    $email     = mysqli_real_escape_string($conexion, strtolower(trim($_POST['email'] ?? '')));
    $celular   = mysqli_real_escape_string($conexion, trim($_POST['celular'] ?? ''));
    $consorcio = mysqli_real_escape_string($conexion, strtoupper(trim($_POST['consorcio'] ?? '')));
    $consorcio_sql = $consorcio === '' ? "NULL" : "'$consorcio'";

    if ($id > 0 && !empty($apellido)) {
        // 4. Ejecutamos el UPDATE con todos los campos
        $sql = "UPDATE usuarios SET apellido = '$apellido', dni = '$dni', cuit = '$cuit', domicilio = '$domicilio', email = '$email', celular = '$celular', consorcio = $consorcio_sql WHERE id = $id";

        if (mysqli_query($conexion, $sql)) {
            // Éxito: Redirigimos al index
            header("Location: index.php");
            exit;
        } else {
            // Error de SQL: Mostramos el error exacto de MySQL
            die("Error de base de datos: " . mysqli_error($conexion));
        }
    } else {
        die("Error: Datos incompletos. Debe indicar Apellido.");
    }
} else {
    die("Acceso no permitido.");
}
?>