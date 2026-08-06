<?php
// corregir-migrations.php
// Reconstrói a tabela migrations baseando-se nas tabelas que EXISTEM no banco.
// SEGURO: não apaga dados de produção, apenas o registo interno de migrations.

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    fwrite(STDERR, "ERRO: ficheiro .env não encontrado em " . $envFile . "\n");
    exit(1);
}

function envGet($env, $key, $default = null)
{
    foreach (explode("\n", $env) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#')) continue;
        if (preg_match("/^DB_" . preg_quote($key, '/') . "\s*=\s*(.*)$/", $linha, $m)) {
            $valor = trim($m[1]);
            // remove aspas
            if ((str_starts_with($valor, '"') && str_ends_with($valor, '"'))
                || (str_starts_with($valor, "'") && str_ends_with($valor, "'"))) {
                $valor = substr($valor, 1, -1);
            }
            return $valor;
        }
    }
    return $default;
}

$env = file_get_contents($envFile);

$host     = envGet($env, 'HOST', '127.0.0.1');
$port     = envGet($env, 'PORT', '3306');
$database = envGet($env, 'DATABASE');
$user     = envGet($env, 'USERNAME');
$pass     = envGet($env, 'PASSWORD', '');

if (!$database || !$user) {
    fwrite(STDERR, "ERRO: DB_DATABASE ou DB_USERNAME ausentes no .env\n");
    exit(1);
}

echo "=== Conexão ao banco ===\n";
echo "Host: $host:$port\nBanco: $database\nUsuário: $user\n\n";

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 1. Backup da tabela migrations (se não existir backup)
$backupTable = 'migrations_backup_' . date('Ymd_His');
echo "A criar backup: $backupTable\n";
$pdo->exec("CREATE TABLE `$backupTable` AS SELECT * FROM migrations");

// 2. Lista de ficheiros de migration
$migrationDir = __DIR__ . '/database/migrations';
$ficheiros = glob($migrationDir . '/*.php');
sort($ficheiros);

echo "\n=== Ficheiros de migration: " . count($ficheiros) . " ===\n";

// 3. Tabelas existentes
$tabelasExistentes = [];
foreach ($pdo->query('SHOW TABLES') as $row) {
    $tabelasExistentes[] = $row[0];
}
$tabelasSet = array_flip($tabelasExistentes);

echo "Tabelas existentes no banco: " . count($tabelasExistentes) . "\n\n";

// 4. Determinar aplicadas vs pendentes
$aplicadas = [];
$pendentes = [];

foreach ($ficheiros as $ficheiro) {
    $nome = basename($ficheiro, '.php');
    $conteudo = file_get_contents($ficheiro);

    // Tabelas criadas por esta migration (Schema::create('tabela'...))
    preg_match_all("/Schema::create\(\s*['\"]([^'\"]+)['\"]/", $conteudo, $matches);
    $tabelasCriadas = $matches[1];

    if (empty($tabelasCriadas)) {
        // Migration que apenas altera tabelas — assume aplicada
        $aplicadas[] = $nome;
        continue;
    }

    $todasExistem = true;
    foreach ($tabelasCriadas as $t) {
        if (!isset($tabelasSet[$t])) {
            $todasExistem = false;
            break;
        }
    }

    if ($todasExistem) {
        $aplicadas[] = $nome;
    } else {
        $pendentes[] = $nome;
    }
}

echo "=== Aplicadas (tabelas existem): " . count($aplicadas) . " ===\n";
echo "=== Pendentes (tabelas NÃO existem): " . count($pendentes) . " ===\n";
foreach ($pendentes as $p) {
    echo "  - $p\n";
}

// 5. Confirmação
echo "\nIsto vai APAGAR e reconstruir a tabela migrations (registo interno, NÃO os seus dados).\n";
echo "Digite SIM para continuar: ";
$confirm = trim(fgets(STDIN));
if ($confirm !== 'SIM' && $confirm !== 'sim') {
    echo "Abortado. Nenhuma alteração feita. Backup em: $backupTable\n";
    exit(0);
}

// 6. Reconstruir
echo "\nA reconstruir tabela migrations...\n";
$pdo->exec('TRUNCATE TABLE migrations');

$stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
$batch = 1;
$pdo->beginTransaction();
foreach ($aplicadas as $m) {
    $stmt->execute([$m, $batch]);
}
$pdo->commit();

echo "✓ Tabela migrations reconstruída com " . count($aplicadas) . " registos (batch $batch).\n";
echo "✓ Backup da tabela anterior em: $backupTable\n";
echo "\nAgora rode: php artisan migrate --force\n";
