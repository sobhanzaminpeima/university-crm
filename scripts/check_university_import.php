<?php

declare(strict_types=1);

use App\Models\University;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo 'universities='.University::query()->where('tenant_id', 1)->count().PHP_EOL;
echo 'programs='.DB::table('university_programs')->where('tenant_id', 1)->count().PHP_EOL;

$names = University::query()->where('tenant_id', 1)->latest('id')->limit(10)->pluck('name');
foreach ($names as $name) {
    echo $name.PHP_EOL;
}
