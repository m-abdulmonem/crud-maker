<?php

namespace Mabdulmonem\CrudMaker\Services\Http;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mabdulmonem\CrudMaker\Helpers\Helper;


class EnumGeneration
{

    public static function build(Command $command, string $columnName, array $cases,string $crudName)
    {

        if (!File::isDirectory($path = base_path("app/Enums"))) {
            File::makeDirectory($path, 0755, true);
        }
        $name = Str::studly($columnName);
        if (File::isFile($path . "/{$crudName}{$name}Enum.php")) {
            $command->warn("Enum file already exists at: {$path}");
        }

        File::put(
            $path = $path . "/{$crudName}{$name}Enum.php",
            str_replace(
                [
                    '{{CRUD_NAME}}',
                    '{{CASES}}',
                    '{{ENUM_TYPE}}'
                ],
                [
                    $crudName.self::getName($columnName),
                    self::getColumns($command, $cases),
                    'string'
                ],
                Helper::getStub('enum')
            //File::get($stubPath)
            )
        );

        $command->info("Enum file created: $path,");

    }

    private static function getColumns($command, $cases)
    {
        $convertedCases = [];
        foreach ($cases as $case) {
            $convertedCases[] = "case " . ucfirst($case) . " = '$case'";
        }
        return $command->indentCode($convertedCases, true);
    }


    public static function getName(string $name)
    {
        // Standardize the name format
        $name = Str::of($name)
            ->trim() // Remove leading/trailing spaces
            ->replace(['-', '_'], ' ') // Replace dashes and underscores with spaces
            ->lower() // Convert to lowercase
            ->replace(' ', '_') // Replace spaces with underscores
            ->__toString(); // Convert to string

        return Str::studly($name);
    }

}
