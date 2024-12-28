<?php

namespace  Mabdulmonem\CrudMaker\Services\Http;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mabdulmonem\CrudMaker\Helpers\Helper;

class ControllerGeneration
{

    public static function build(Command $command, string $pluralized, string $lowerName, string $name, string $namespace, array $columns)
    {
        // if (!File::exists($stubPath = base_path('stubs/controller.stub'))) {
            // $command->error("Stub file not found at: $stubPath");
            // return Command::FAILURE;
        // }

        if (!File::isDirectory($path = base_path("app/Http/Controllers/Api/{$namespace}"))) {

            File::makeDirectory($path, 0755, true);
        }

        if (File::isFile($path . "/{$pluralized}Controller.php")){
            $command->warn("Controller file already exists at: {$path}");
        }

        File::put(
            $path = $path . "/{$pluralized}Controller.php",
            str_replace(
                [
                    '{{CRUD_PATH}}',
                    '{{PLURALIZED_CRUD_NAME}}',
                    '{{CRUD_NAME}}',
                    '{{LOWER_CRUD_NAME}}',
                    '{{CRUD_COLUMNS}}',

                ],
                [
                    $namespace,
                    $pluralized,
                    $name,
                    $lowerName,
                    self::getSearchColumns($command,$columns)
                ],
                Helper::getStub('controller')
                //File::get($stubPath)
            )
        );

        $command->info("Controller file created: $path,");
    }

    private static function getSearchColumns($command, $columns)
    {
        $searchColumns = [];
        foreach ($columns as $key => $column) {
            if ($key == 'translated') {
                foreach ($column ?? [] as $col) {
                    $searchColumns[] = "'{$col['name']}' => 'translatable',";
                }
            } else {
                foreach ($column as $col) {
                    $searchColumns[] = "'{$col['name']}',";
                }
            }

        }
        return $command->indentCode($searchColumns, true);
    }


}
