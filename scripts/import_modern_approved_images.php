<?php

use App\Models\Image;
use App\Models\ImageLibrary;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$manifestPath = __DIR__.'/../storage/app/public/uploads/images/nanshanlin-modern-approved-v1/manifest.json';
if (! is_file($manifestPath)) {
    fwrite(STDERR, "Manifest not found: {$manifestPath}\n");
    exit(1);
}

$items = json_decode((string) file_get_contents($manifestPath), true);
if (! is_array($items) || $items === []) {
    fwrite(STDERR, "Manifest is empty or invalid.\n");
    exit(1);
}

$library = DB::transaction(function () use ($items): ImageLibrary {
    $library = ImageLibrary::query()->updateOrCreate(
        ['name' => '南山林现代装修效果图配图库 v1'],
        [
            'description' => '用于南山林 GEOFlow 文章生成的第一批现代装修效果图配图库。来源为 Wikimedia Commons 开放授权图片，已按南山林现代设计风格做人工初筛，适合木饰面、背景墙、护墙板、门墙一体化等文章过渡使用。',
            'used_task_count' => 0,
        ]
    );

    Image::query()->where('library_id', (int) $library->id)->delete();

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        $filePath = (string) ($item['file_path'] ?? '');
        $relative = preg_replace('#^storage/#', '', $filePath);
        $absolute = __DIR__.'/../storage/app/public/'.$relative;
        if ($filePath === '' || ! is_file($absolute)) {
            fwrite(STDERR, "Skip missing file: {$filePath}\n");

            continue;
        }

        $filename = (string) ($item['filename'] ?? basename($absolute));
        Image::query()->create([
            'library_id' => (int) $library->id,
            'filename' => $filename,
            'original_name' => (string) ($item['original_name'] ?? $filename),
            'file_name' => $filename,
            'file_path' => $filePath,
            'file_size' => (int) filesize($absolute),
            'mime_type' => (string) ($item['mime_type'] ?? 'image/jpeg'),
            'width' => (int) ($item['width'] ?? 1200),
            'height' => (int) ($item['height'] ?? 675),
            'tags' => (string) ($item['tags'] ?? '南山林,现代装修效果图,护墙板,GEO'),
            'used_count' => 0,
            'usage_count' => 0,
        ]);
    }

    $library->image_count = Image::query()->where('library_id', (int) $library->id)->count();
    $library->save();

    return $library;
});

echo sprintf(
    "Imported image library #%d: %s (%d images)\n",
    (int) $library->id,
    (string) $library->name,
    (int) $library->image_count
);
