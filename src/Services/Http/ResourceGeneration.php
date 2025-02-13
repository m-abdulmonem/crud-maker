<?php

namespace  Mabdulmonem\CrudMaker\Services\Http;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mabdulmonem\CrudMaker\Helpers\Helper;

class ResourceGeneration
{
    public static function build(Command $command, string $name, string $namespace, array $columns, ?array $translatedColumns = null): void
    {
        if (!File::isDirectory($path = base_path("app/Http/Resources/Api/{$namespace}"))) {

            File::makeDirectory($path, 0755, true);
        }

        if (File::isFile("$path/{$name}Resource.php")) {
            $command->warn("Resource already exists!");
        }

        File::put(
            $path = "$path/{$name}Resource.php",
            str_replace(
                [
                    '{{CRUD_PATH}}',
                    '{{CRUD_NAME}}',
                    '{{COLUMNS}}',
                    '{{TRANSLATIONS}}',
                ],
                [
                    $namespace,
                    $name,
                    self::getResourceAttrs($command, $columns),
                    self::getResourceTranslationsAttrs($command, $columns, $translatedColumns),
                ],
                                Helper::getStub('resource')

                // File::get(base_path('stubs/resource.stub'))
            )
        );

        $command->info("Resource file created: $path,");
    }

    private static function getResourceAttrs($command, $columns): ?string
    {
        if ($command->hasOption('translated')) {
            return null;
        }
        $attrs = [];

        foreach ($columns as $column) {
            $attrs[] = "'{$column['name']}' => \$this->{$column['name']},";
        }


        $data = <<<EOT
        return [
            {$command->indentCode($attrs)}
        ];
EOT;

        return $data;
    }

    private static function getResourceTranslationsAttrs($command, $columns, $translatedColumns)
    {
        if (!$command->hasOption('translated')) {
            return null;
        }
        $attrs = [
            "'id' => \$this->id,"
        ];
        $translations = [];

        foreach ($columns as $column) {
            if ($column['type'] == 'boolean') {
                $attrs[] = "'{$column['name']}' => (boolean)\$this->{$column['name']},";
            }
            elseif ($column['type'] == 'timestamp') {
                $attrs[] = "'{$column['name']}' => \$this->{$column['name']}?->format('Y-m-d H:i'),";
            }
            elseif ($column['type'] == 'date') {
                $attrs[] = "'{$column['name']}' => \$this->{$column['name']}?->format('Y-m-d'),";
            }
            elseif ($column['type'] == 'time') {
                $attrs[] = "'{$column['name']}' => \$this->{$column['name']}?->format('H:i'),";
            }
            elseif ($column['type'] == 'integer') {
                $attrs[] = "'{$column['name']}' => (int)\$this->{$column['name']},";
            }
            elseif ($column['type'] == 'foreignId') {
                $name = str_replace('_id', '', $column['name']);
                $resource = $command->convertToPascalCase($name);
                $attrs[] = "'$name' => \$this->{$name} ? Simple{$resource}Resource::make(\$this->{$name}) : null,";
            } else {

                $attrs[] = "'{$column['name']}' => \$this->{$column['name']},";
            }
        }
        foreach ($translatedColumns ?? [] as $column) {
            $translations[] = "\$data[\$locale]['{$column['name']}'] = \$this->translate(\$locale)?->{$column['name']};";
        }


        $a = <<<EOT
        \$data  = [
            {$command->indentCode($attrs)}
        ];
        foreach (config('translatable.locales') as \$locale) {
            {$command->indentCode($translations)}
        }

        return \$data;
EOT;

        return $a;
    }

}
