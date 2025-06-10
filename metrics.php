<?php
// metrics.php
header('Content-Type: application/json');

// Определяем, на какой ОС мы работаем
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// Задаём постоянную — TDP вашего CPU в ваттах (например, 65 Вт)
define('HOST_TDP', 65);
// Пусть idle_power будет 10% от TDP
define('HOST_IDLE_POWER', HOST_TDP * 0.1); 

if ($isWindows) {
    // CPU Load %
    $cpuRaw = shell_exec('wmic cpu get loadpercentage');
    $cpuPct = 0;
    if (preg_match('/LoadPercentage\s+(\d+)/i', $cpuRaw, $m)) {
        $cpuPct = (int)$m[1];
    }

    // Memory usage %
    $memRaw = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
    $freeKB = 0;
    $totalKB = 0;
    if (preg_match('/FreePhysicalMemory=(\d+)/i', $memRaw, $mFree)) {
        $freeKB = (int)$mFree[1];
    }
    if (preg_match('/TotalVisibleMemorySize=(\d+)/i', $memRaw, $mTot)) {
        $totalKB = (int)$mTot[1];
    }
    $usedMemPct = $totalKB > 0
                  ? round((($totalKB - $freeKB) / $totalKB) * 100, 2)
                  : 0;

    // Расчёт мощности host в ваттах: пусть idle = HOST_IDLE_POWER, а при 100% = HOST_TDP
    $variablePart = HOST_TDP - HOST_IDLE_POWER;
    $hostPower    = HOST_IDLE_POWER + $variablePart * ($cpuPct / 100.0);
    $hostPower    = round($hostPower, 2);

    echo json_encode([
        'cpu' => $cpuPct,
        'mem' => $usedMemPct,
        'host_power' => $hostPower
    ]);
    exit;
} else {
    // Linux/macOS
    $load = sys_getloadavg()[0];
    $cores = (int)trim(shell_exec('nproc'));
    $cores = $cores < 1 ? 1 : $cores;
    $cpuPct = round(($load / $cores) * 100, 2);
    if ($cpuPct > 100) { $cpuPct = 100; }

    $meminfo = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+) kB/', $meminfo, $mTotal);
    preg_match('/MemAvailable:\s+(\d+) kB/', $meminfo, $mAvail);
    $totalKB = isset($mTotal[1]) ? (int)$mTotal[1] : 0;
    $availKB = isset($mAvail[1]) ? (int)$mAvail[1] : 0;
    $usedMemPct = $totalKB > 0
                  ? round((($totalKB - $availKB) / $totalKB) * 100, 2)
                  : 0;

    $variablePart = HOST_TDP - HOST_IDLE_POWER;
    $hostPower    = HOST_IDLE_POWER + $variablePart * ($cpuPct / 100.0);
    $hostPower    = round($hostPower, 2);

    echo json_encode([
        'cpu' => $cpuPct,
        'mem' => $usedMemPct,
        'host_power' => $hostPower
    ]);
    exit;
}
