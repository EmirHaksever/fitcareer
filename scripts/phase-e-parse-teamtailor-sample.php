<?php
$j = json_decode(file_get_contents(__DIR__.'/../storage/phase-e-dfds-teamtailor.json'), true);
echo 'top keys: '.implode(', ', array_keys($j))."\n";
$jobs = $j['items'] ?? $j['jobs'] ?? $j;
if (isset($jobs['data'])) {
    $jobs = $jobs['data'];
}
$jobList = array_values(array_filter(is_array($jobs) ? $jobs : [], 'is_array'));
echo 'job count: '.count($jobList)."\n";
if ($jobList) {
    $first = $jobList[0];
    echo 'first keys: '.implode(', ', array_slice(array_keys($first), 0, 20))."\n";
    echo 'title: '.($first['title'] ?? '?')."\n";
    echo 'location: '.json_encode($first['location'] ?? null)."\n";
    echo 'locations: '.json_encode($first['locations'] ?? null)."\n";
}
$tr = 0;
foreach ($jobList as $job) {
    $blob = json_encode($job);
    if (preg_match('/istanbul|türkiye|turkey|ankara|izmir/i', $blob)) {
        $tr++;
    }
}
echo "turkey keyword jobs: $tr\n";
