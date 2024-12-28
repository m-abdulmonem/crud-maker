<?php

namespace  Mabdulmonem\CrudMaker\Services\Http;

use Illuminate\Console\Command;

class RoutesGeneration
{

    public static function build(Command $command, string $lowerPluralized, string $lowerName, string $controller)
    {

        if ($command->argument('route') == null) {
            return false;
        }
           $content = self::existingContent($command);
        if (! file_exists($content['path'])){
            $command->error("{$content['path']}  file not found");
            return false;
        }

        self::saveAtRoute($command, $lowerPluralized, $lowerName, $controller, $content);
    }

    private static function saveAtRoute($command, $lowerPluralized, $lowerName, $controller, $content): void
    {
//       $content = self::existingContent($command);

        $position = strpos(
            $content['content'],
            $routeGroupLine = 'Route::middleware([\'auth:api\'])->group(function () {'
        ); // Find where the auth group starts

        if ($position !== false) {

            $position = $position + strlen($routeGroupLine);

            $updatedContent = substr_replace(
                $content['content'],
                self::stub($lowerPluralized, $lowerName, $controller),
                $position,
                0
            );

            // Save the updated content back to the file
            file_put_contents($content['path'], $updatedContent);

            $command->info("Routes successfully added after the auth middleware group in {$content['path']}");
        } else {
            $command->error("Authenticated route group not found in {$content['path']}");
        }
    }

    private static function existingContent($command)
    {
        $extension = !str_contains($command->argument('route'), '.php') ? '.php' : null;

        return [
            'path' => $routeFilePath = base_path("routes/{$command->argument('route')}{$extension}"),
            'content' => $content = file_get_contents($routeFilePath)
        ];
    }

    private static function stub($lowerPluralized, $lowerName, $controller)
    {
        $routeContent = <<<EOT


/** $lowerPluralized Routes **/
Route::group([], function () {
    Route::prefix('{$lowerPluralized}')->controller(\App\Http\Controllers\Api\\{$controller}::class)->group(function () {
        Route::post('{$lowerName}/status', 'updateStatus');
    });
    Route::apiResource('{$lowerPluralized}', \App\Http\Controllers\Api\\{$controller}::class);
});
EOT;
        return $routeContent;
    }
}
