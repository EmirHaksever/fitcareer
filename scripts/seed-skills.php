<?php

declare(strict_types=1);

use App\Models\Skill;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$names = [
    'JavaScript', 'TypeScript', 'React', 'Vue.js', 'PHP', 'Laravel',
    'Python', 'Java', 'SQL', 'Git', 'Docker', 'AWS', 'Node.js',
    'HTML', 'CSS', 'Product Management', 'Agile', 'Scrum',
    'Flutter', 'Dart', 'Firebase', 'Supabase', 'REST API',
];

foreach ($names as $name) {
    Skill::firstOrCreate(
        ['slug' => Str::slug($name)],
        ['name' => $name, 'category' => 'Technology'],
    );
}

echo 'Seeded '.Skill::count()." skills\n";
