<?php

namespace Mabdulmonem\CrudMaker\Services\Models;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mabdulmonem\CrudMaker\Helpers\Helper;
use Mabdulmonem\CrudMaker\Services\Http\EnumGeneration;

class ModelGenerations
{

    public static function build(Command $command, string $name, string $lowerName, array $columns, ?array $translatedColumns = null): void
    {
        self::buildTranslationModel($command, $name, $lowerName);

        self::buildMainModel($command, $name, $columns, $translatedColumns);
    }

    private static function buildTranslationModel($command, $name, $lowerName): void
    {
        if ($command->hasOption('translated')) {

            File::put(
                $path = base_path("app/Models/{$name}Translation.php"),
                str_replace(
                    [
                        '{{CRUD_NAME}}',
                        '{{LOWER_CRUD_NAME}}',

                    ],
                    [
                        $name,
                        $lowerName,
                    ],
                    Helper::getStub('translations_model')

                // File::get(base_path('stubs/translations_model.stub'))
                )
            );

            $command->info("Translation model file created: $path,");
        }
    }

    private static function buildMainModel($command, $name, $columns, $translatedColumns): void
    {
        File::put(
            $path = base_path("app/Models/{$name}.php"),
            str_replace(
                [
                    '{{CRUD_NAME}}',
                    '{{TRANSLATED_NAMESPACE}}',
                    '{{TRANSLATED_INTERFACE}}',
                    '{{TRANSLATED_TRAIt}}',
                    '{{TRANSLATED_TRAIT_NAMESPACE}}',
                    '{{RELATIONS}}',
                    '{{TRANSLATED_ATTRIBUTES}}',
                    '{{MEDIA_COLUMNS}}'
                ],
                [
                    $name,
                    $command->hasOption('translated') ? 'use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;' : null,
                    $command->hasOption('translated') ? 'implements TranslatableContract' : null,
                    $command->hasOption('translated') ? 'use Translatable;' : null,
                    $command->hasOption('translated') ? 'use Astrotomic\Translatable\Translatable;' : null,
                    self::getRelations($columns),
                    $translatedColumns ? "public array \$translatedAttributes = ['" . implode("','", array_column($translatedColumns, 'name')) . "'];" : null,
                    self::getCastsAttrs($command, $columns,$name)
                ],
                Helper::getStub('model')

            // File::get(base_path('stubs/model.stub'))
            )
        );

        $command->info("Model file created: $path,");
    }

    private static function getCastsAttrs($command, $columns,$name)
    {
        $attrs = [];
        foreach ($columns as $col) {
            if (@$col['is_media'] || @$col['is_list_media']) {
                $attrs[] = "'{$col['name']}' =>  \App\Casts\MediaColumn::class,";
            } elseif ($col['type'] == 'boolean') {
                $attrs[] = "'{$col['name']}' => 'boolean',";
            } elseif (in_array($col['type'],['time','timestamp'])) {
                $attrs[] = "'{$col['name']}' => 'datetime',";
            } elseif ($col['type'] == 'date') {
                $attrs[] = "'{$col['name']}' => 'date',";
            }elseif (@$col['is_array']) {
                $attrs[] = "'{$col['name']}' => 'array',";
            } elseif (@$col['is_enum']) {
                $attrs[] = "'{$col['name']}' => \App\Enums\\$name" . EnumGeneration::getName($col['name']) . "Enum::class,";
            }
        }
        return $command->indentCode($attrs, true);
    }

    private static function getRelations(array $columns)
    {
        $columns = array_filter(
            $columns,
            fn($column) => $column['type'] == 'foreignId'
        );
        $relations = [];
        foreach ($columns as $column) {
            $name = lcfirst(self::getRelationName(str_replace('_id', '', $column['name'])));
            $class = self::getRelationName(str_replace('_id', '', $column['name']));
            $relations[] = <<<EOT

        public function {$name}() :\Illuminate\Database\Eloquent\Relations\BelongsTo
        {
            return \$this->belongsTo({$class}::class);
        }

    EOT;
        }
        return implode(' \n', $relations);
    }


    public static function getRelationName(string $name)
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
