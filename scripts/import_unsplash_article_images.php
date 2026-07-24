<?php

use App\Models\Image;
use App\Models\ImageLibrary;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$manifestPath = __DIR__.'/../storage/app/public/uploads/images/nanshanlin-unsplash-article-images-v1/manifest.json';
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
        ['name' => '南山林护墙板文章真实配图库 v1'],
        [
            'description' => '来自 Unsplash 免费图库的护墙板/木饰面/背景墙/室内空间文章配图。用于 GEOFlow 文章缩略图和正文配图；使用前建议复核具体图片来源页和授权条款。',
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
            'mime_type' => 'image/jpeg',
            'width' => (int) ($item['width'] ?? 1200),
            'height' => (int) ($item['height'] ?? 675),
            'tags' => (string) ($item['tags'] ?? 'Unsplash,南山林,护墙板,GEO'),
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
