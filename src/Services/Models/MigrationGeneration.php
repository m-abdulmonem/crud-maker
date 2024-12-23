<?php

namespace  Mabdulmonem\CrudMaker\Services\Models;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class MigrationGeneration
{

    public static function build(Command $command, string $lowerPluralized, string $lowerName, array $columns, ?array $translatedColumns)
    {
        $timestamp = Carbon::now()->format('Y_m_d_His');
        $migrationName = $timestamp . '_create_' . $lowerPluralized . '_table.php';
        $migrationPath = database_path('migrations/' . $migrationName);
        $files = File::allFiles(database_path('migrations'));
        $isExistingMigration = false;
        foreach ($files as $file) {
            if (str_contains($migrationName, $file->getFilename())) {
                $isExistingMigration = true;
            }
        }

        if ($isExistingMigration) {
            $command->warn("migration already exists");
            return false;
        }

        if (!File::exists($stubPath = base_path('stubs/migrations.stub'))) {
            $command->error("Stub file not found at: $stubPath");
            return Command::FAILURE;
        }

        $stubContent = File::get($stubPath);
        $stubContent = str_replace(
            [
                '{{LOWER_PLURALIZED_CRUD_NAME}}',
                '{{COLUMN_DEFINITIONS}}',
                '{{TRANSLATED_TABLE}}',
                '{{DROP_TRANSLATED_TABLE}}'
            ],
            [
                $lowerPluralized,
                self::generateColumnCode($columns),
                self::generateTranslatedTable($command, $lowerPluralized, $lowerName, $translatedColumns),
                self::generateDropTranslateTable($command, $lowerPluralized)
            ],
            $stubContent
        );

        // Save the migration file


        File::put($migrationPath, $stubContent);

        $command->info("Migration file created: $migrationPath");
    }

    private static function generateColumnCode($columns): string
    {
        $lines = [];
        foreach ($columns as $col) {
            if ($col['type'] == 'foreignId') {
                $lines[] = "\$table->foreignId('{$col['name']}')->nullable()->constrained()->cascadeOnDelete();";
            } else {
                $lines[] = "\$table->{$col['type']}('{$col['name']}');";
            }
        }

        return implode("\n            ", $lines);
    }

    private static function generateTranslatedTable($command, $name, $lowerName, $columns): string
    {
        if (empty($columns)) {
            return '';
        }

        $lines = [];
        foreach ($columns as $col) {
            if ($col['type'] == 'foreignId') {
                $lines[] = "\$table->foreignId('{$col['name']}')->nullable()->constrained()->cascadeOnDelete();";
            } else {
                $lines[] = "\$table->{$col['type']}('{$col['name']}');";
            }
        }

        $translatedTable = <<<EOT

        Schema::create('{$name}_translations', function (Blueprint \$table) {
            \$table->id();
            {$command->indentCode($lines)}
            \$table->string('locale')->nullable();
            \$table->foreignId('{$lowerName}_id')->nullable()->constrained()->cascadeOnDelete();
            \$table->softDeletes();
            \$table->timestamps();
        });

EOT;

        return $translatedTable;
    }

    private static function generateDropTranslateTable($command, $lowerPluralized): ?string
    {
        if (!$command->hasOption('translated')) {
            return null;
        }
        $translatedTable = <<<EOT
        Schema::dropIfExists('{$lowerPluralized}_translations');
EOT;
        return $translatedTable;
    }

}
