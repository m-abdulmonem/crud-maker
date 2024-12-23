<?php

namespace  Mabdulmonem\CrudMaker\Services\Http;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RequestGeneration
{

    private static array $rules = [
        'string' => 'string',
        'integer' => 'integer',
        'boolean' => 'boolean',
        'text' => 'string',
        'time' => 'date_format:H:i',
        'date' => 'date',
        'timestamp' => 'date',
        'float' => 'numeric',
        'decimal' => 'numeric',
        'longtext' => 'string',
        'image' => 'attachment_exists:image',
        'video' => 'attachment_exists:video',
        'file' => 'attachment_exists:file',
        'images' => 'array',
        'videos' => 'array',
        'files' => 'array',
        'array' => 'array',
    ];

    public static function build(Command $command, array $columns, ?array $translated, string $name, string $lowerName, string $namespace)
    {
        if (!File::isDirectory($path = base_path("app/Http/Requests/Api/{$namespace}"))) {

            File::makeDirectory($path, 0755, true);
        }

        if (File::isFile("$path/{$name}Request.php")){
            $command->warn('Resource file already exists!');
            return false;
        }

        if (!$command->hasOption('translated')) {
            extract(self::rulesNotTranslations($command,$columns));
        } else {
            extract(self::rulesTranslations($command,$columns,$translated));
        }

        File::put(
            $path = "$path/{$name}Request.php",
            str_replace(
                [
                    '{{CRUD_PATH}}',
                    '{{CRUD_NAME}}',
                    '{{RULES}}',
                    '{{ATTRS}}',
                    '{{CRUD_LOWER_NAME}}',
                ],
                [
                    $namespace,
                    $name,
                    $finalRules,
                    $finalAttrs,
                    $lowerName
                ],
                File::get(base_path('stubs/request.stub'))
            )
        );

        $command->info("Resource file created: $path,");

    }

    private static function getColumns($columns): array
    {
        $rules = [];
        $attrs = [];
        foreach ($columns as $column) {
            if ($column['type'] == 'uuid') {
                continue;
            }
            if ($column['type'] == 'foreignId') {
                $rules[] = "'{$column['name']}' => '\$status|exists:" . self::getTableNameFromForeignId($column['name']) . ",id',";
            }
            if ($column['is_media']) {
                $rules[] = "'{$column['name']}' => '\$status|" . self::$rules[$column['media_type']] . "',";
            }
            if ($column['is_list_media']) {
                $rules[] = "'{$column['name']}.*' => '\$status|" . self::$rules[$column['media_type']] . "',";
            } else {
                $rules[] = "'{$column['name']}' => '\$status|" . self::$rules[$column['type']] . "',";
            }

            $attrs[] = "'{$column['name']}' => __('" . ucfirst(str_replace('_', ' ', $column['name'])) . "'),";
        }
        return [
            'rules' => $rules,
            'attrs' => $attrs,
        ];
    }

    private static function getTableNameFromForeignId(string $columnName): string
    {
        // Remove the `_id` suffix from the column name
        $baseName = str_replace('_id', '', $columnName);

        // Return the pluralized form of the base name (assuming Laravel's convention)
        return Str::plural($baseName);
    }

    private static function getTranslationsColumns($translated): array
    {
        $rules = [];
        $attrs = [];
        foreach ($translated ?? [] as $column) {
            $rules[] = "'{$column['name']}' => '\$status|" . self::$rules[$column['type']] . "',";
            $attrs[] = "'{$column['name']}' => __('" . ucfirst(str_replace('_', ' ', $column['name'])) . " in :local',locale: \$locale),";
        }
        return [
            'rules' => $rules,
            'attrs' => $attrs,
        ];
    }

    private  static function rulesNotTranslations($command, $columns): array
    {
        $attrs = self::getColumns($columns);
        $finalRules = <<<EOT
       return [
            {$command->indentCode($attrs['rules'])}
        ];
EOT;

        $finalAttrs = <<<EOT
       return [
            {$command->indentCode($attrs['attrs'])}
        ];
EOT;

        return [
            'finalRules' => $finalRules,
            'finalAttrs' => $finalAttrs,
        ];
    }

    private static function rulesTranslations($command, $columns, $translated)
    {
        $translations = self::getTranslationsColumns($translated);
        $attrs = self::getColumns($columns);
        $finalRules = <<<EOT
        \$data  = [
            {$command->indentCode($attrs['rules'])}
        ];
        foreach (config('translatable.locales') as \$locale) {
            {$command->indentCode($translations['rules'])}
        }

        return \$data;
EOT;
        $finalAttrs = <<<EOT
        \$data  = [
            {$command->indentCode($attrs['attrs'])}
        ];
        foreach (config('translatable.locales') as \$locale) {
            {$command->indentCode($translations['attrs'])}
        }

        return \$data;
EOT;

        return [
            'finalRules' => $finalRules,
            'finalAttrs' => $finalAttrs,
        ];
    }

}
