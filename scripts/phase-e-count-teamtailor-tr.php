<?php
$files = [
    'phase-e-dfds-teamtailor.json' => 'dfds',
    'phase-e-ticimax-teamtailor.json' => 'ticimax',
];
foreach ($files as $file => $name) {
    $j = json_decode(file_get_contents(__DIR__.'/../storage/'.$file), true);
    $items = $j['items'] ?? [];
    $tr = 0;
    $ist = 0;
    foreach ($items as $it) {
        $b = json_encode($it, JSON_UNESCAPED_UNICODE);
        if (preg_match('/istanbul|türkiye|turkey|ankara|izmir/i', $b)) {
            $tr++;
            if (preg_match('/istanbul/i', $b)) {
                $ist++;
            }
        }
    }
    echo "$name total=".count($items)." tr=$tr ist=$ist\n";
}
