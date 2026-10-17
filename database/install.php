<?php
/**
 * Instala la base de datos: ejecuta schema.sql (crea la BD "soporte_ti") y seed.sql (datos de prueba)
 * con las credenciales de config/config.php.  Uso:   php database/install.php
 *
 * Alternativa sin consola: importar database/schema.sql y luego database/seed.sql desde phpMyAdmin.
 */
declare(strict_types=1);

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Falta config/config.php (copia config/config.example.php y ajústalo).\n");
    exit(1);
}
$db = (require $configFile)['db'];

/** Divide un script SQL simple en sentencias (sin ';' dentro de textos; los .sql del proyecto cumplen esto). */
function sentencias(string $sql): array
{
    $sinComentarios = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', explode(";\n", (string) $sinComentarios)), 'strlen'));
}

try {
    // Se conecta sin elegir BD: schema.sql la crea.
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
        $db['user'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    foreach (['schema.sql', 'seed.sql'] as $file) {
        foreach (sentencias((string) file_get_contents(__DIR__ . '/' . $file)) as $i => $sentencia) {
            try {
                $pdo->exec($sentencia);
            } catch (PDOException $e) {
                throw new RuntimeException("$file, sentencia " . ($i + 1) . ': ' . $e->getMessage(), 0, $e);
            }
        }
        echo "OK  $file\n";
    }
    $n = $pdo->query('SELECT COUNT(*) FROM soporte_ti.tickets')->fetchColumn();
    echo "Listo. Tickets cargados: $n\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error instalando la base de datos: ' . $e->getMessage() . "\n");
    exit(1);
}
