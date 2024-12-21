<?php
namespace  Mabdulmonem\CrudMaker\Services\Models;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModelGenerations
{

    public static function build(Command $command, string $name, string $lowerName, array $columns, ?array $translatedColumns = null): void
    {
        self::buildTranslationModel($command, $name, $lowerName);

        self::buildMainModel($command, $name,$columns,$translatedColumns);
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
                    File::get(base_path('stubs/translations_model.stub'))
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
                    self::getRelations(),
                    $translatedColumns ? "public array \$translatedAttributes = ['" . implode("','", array_column($translatedColumns, 'name')) . "'];" : null,
                    self::getCastsAttrs($command,$columns)
                ],
                File::get(base_path('stubs/model.stub'))
            )
        );

        $command->info("Model file created: $path,");
    }

    private function getCastsAttrs($command, $columns)
    {
        $attrs = [];
        foreach ($columns as $col) {
            if ($col['is_media'] || $col['is_list_media']) {
                $attrs[] = "'{$col['name']}' =>  \App\Casts\MediaColumn::class,";
            }
            if ($col['is_array']) {
                $attrs[] = "'{$col['name']}' => 'array',";
            }
        }
        return $command->indentCode($attrs, true);
    }

    private static function getRelations()
    {
        return null;
    }


}
