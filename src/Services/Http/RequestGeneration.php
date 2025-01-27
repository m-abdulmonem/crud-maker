<?php

namespace  Mabdulmonem\CrudMaker\Services\Http;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mabdulmonem\CrudMaker\Helpers\Helper;

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
        'enum' => 'enum'
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
            extract(self::rulesNotTranslations($command,$columns,$name));
        } else {
            extract(self::rulesTranslations($command,$columns,$translated,$name));
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
                    '{{POSTMAN}}'

                ],
                [
                    $namespace,
                    $name,
                    $finalRules,
                    $finalAttrs,
                    $lowerName,
                    self::getPostmanKeys($command,$columns,$translated,$name)
                ],
                Helper::getStub('request')
                //File::get(base_path('stubs/request.stub'))
            )
        );

        $command->info("Resource file created: $path,");

    }

    private static function getColumns($columns,$name): array
    {
        $rules = [];
        $attrs = [];
        $postman = [];
        foreach ($columns as $column) {
            if ($column['type'] == 'uuid') {
                continue;
            }
            elseif ($column['type'] == 'foreignId') {
                $rules[] = "'{$column['name']}' => ".'"'."\$status|exists:" .
                self::getTableNameFromForeignId($column['name'],$name) . ",id".'"'.",";
            }
            elseif (@$column['is_media']) {
                $rules[] = "'{$column['name']}' => ".'"'."\$status|" . self::$rules[$column['media_type']] .'"'.",";
            }
            elseif (@$column['is_list_media']) {
                $rules[] = "'{$column['name']}.*' => ".'"'."\$status|" . self::$rules[$column['media_type']] .
                "".'"'.",";
            }elseif(@$column['type'] == 'enum'){
                $rules[] = "'{$column['name']}' => ".'"'."\$status|in:".'" . '  ."\App\Enums\\$name".Str::studly($column['name'])."Enum::join()" .",";
            }
            else {
                $rules[] = "'{$column['name']}' => ".'"'."\$status|" . @self::$rules[$column['type']] .'"'.",";
            }

            $attrs[] = "'{$column['name']}' => __('" . ucfirst(str_replace('_', ' ', $column['name'])) . "'),";
            $postman[] = "//{$column['name']}:";

        }
        return [
            'rules' => $rules,
            'attrs' => $attrs,
            'postman' => $postman
        ];
    }

    private static function getTableNameFromForeignId(string $columnName,$name): string
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
        $postman = [];
        foreach ($translated ?? [] as $column) {
            $rules[] = "\$data[".'"'."\$locale.{$column['name']}".'"'."] = '\$status|" . self::$rules[$column['type']] . "';";
            $attrs[] = "\$data[".'"'."\$locale][{$column['name']}".'"'."] = __('" . ucfirst(str_replace('_', ' ', $column['name'])) . " in :local',locale: \$locale);";
           foreach (config('translatable.locales') as $locale){
               $postman[] = "//{$locale}[{$column['name']}]:";
           }
        }
        return [
            'rules' => $rules,
            'attrs' => $attrs,
            'postman' => $postman
        ];
    }

    private  static function rulesNotTranslations($command, $columns,$name): array
    {
        $attrs = self::getColumns($columns,$name);
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

    private static function rulesTranslations($command, $columns, $translated,$name)
    {
        $translations = self::getTranslationsColumns($translated);
        $attrs = self::getColumns($columns,$name);
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

    private static function getPostmanKeys($command, array $columns, ?array $translated,$name): string
    {
        $translations = self::getTranslationsColumns($translated);
        $attrs = self::getColumns($columns,$name);
        return <<<EOT
     {$command->indentCode($attrs['postman'])}
     {$command->indentCode($translations['postman'])}
EOT;
    }

}
