<?php

namespace Mabdulmonem\CrudMaker\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mabdulmonem\CrudMaker\Services\Http\ControllerGeneration;
use Mabdulmonem\CrudMaker\Services\Http\EnumGeneration;
 use Mabdulmonem\CrudMaker\Services\Http\RequestGeneration;
use Mabdulmonem\CrudMaker\Services\Http\ResourceGeneration;
use Mabdulmonem\CrudMaker\Services\Http\RoutesGeneration;
use Mabdulmonem\CrudMaker\Services\Models\MigrationGeneration;
use Mabdulmonem\CrudMaker\Services\Models\ModelGenerations;
 use Illuminate\Support\Facades\Schema;
 use Illuminate\Support\Facades\DB;


class CrudMaker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud
                            {name}
                            {route? : The route file path for updating the route with this new crud routes}
                            {model? : The model the is exists before}
                            {path=Dashboard\Admin\ : The namespace path for the CRUD}
                            {--t|translated? : Include a translations table}
                            ';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a CRUD setup including migration with optional translations table.';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        list($lowerName, $name, $lowerPluralized, $pluralized) = $this->getCrudName();

        if (!str_ends_with($this->argument('path'), '\\')) {
            $this->error('Namespace must end with "\"');
        }

        $namespace = $this->argument('path') . $pluralized;
        // Prepare an array to store column names and types
        $columns = [];

        if ($this->argument('model')){

         // Laravel Types Mapping
         $laravelTypes = [
         'string' => 'string',
         'integer' => 'integer',
         'boolean' => 'boolean',
         'text' => 'text',
         'time' => 'date',
         'date' => 'date',
         'timestamp' => 'timestamp',
         'float' => 'float',
         'decimal' => 'decimal',
         'uuid' => 'uuid',
         'longtext' => 'longtext',
         'foreignId' => 'foreignId',
         'image' => 'string',
         'name' => 'string',
         'video' => 'string',
         'file' => 'string',
         'images' => 'longtext',
         'videos' => 'longtext',
         'files' => 'longtext',
         'array' => 'longtext',
         ];

         // Get the model name from the command argument
         $modelClass = $this->argument('model');
         $model = app("\App\Models\\{$modelClass}"); // Dynamically instantiate the model

         // Get the table name associated with the model
         $tableName = $model->getTable();

         // Get the columns with their data types using Doctrine DBAL
         $columns = DB::getDoctrineSchemaManager()->listTableColumns($tableName);

         // Initialize an array to store processed columns
         $processedColumns = [];

         // Iterate over the columns
         foreach ($columns as $column) {
         // Exclude common columns like 'id', 'created_at', 'deleted_at', 'updated_at'
         if (!in_array($column->getName(), ['id', 'created_at', 'deleted_at', 'updated_at'])) {
         $columnName = $column->getName();
         $columnType = $column->getType()->getName();

         // If the column type exists in the $laravelTypes array, replace it with the corresponding Laravel type
         if (array_key_exists($columnType, $laravelTypes)) {
         $migrationType = $laravelTypes[$columnType];
         } else {
         // If type is not found, keep the original type
         $migrationType = $columnType;
         }

         // Add the processed column to the array
         $processedColumns[] = [
         'name' => $columnName,
         'type' => $migrationType
         ];
         }
         }

         // Output the final columns with the updated types
         $this->info("Processed Columns:");
         foreach ($processedColumns as $col) {
         $this->line("- Name: {$col['name']}, Type: {$col['type']}");

         }

        }else{
            $columns = $this->getColumns();

            if ($this->hasOption('translated')) {
            // $this->info('Please enter translations table column');
            $translatedColumns = $this->getColumns("Enter the name of the translations table column (or type 'done' to finish)");
            }
        }

        foreach((array_filter($columns, fn($column) => $column['type'] == 'enum') ?? []) as $enum){
            EnumGeneration::build(
                $this,
                $enum['name'],
                $enum['enum_values']
            );
        }

        //create migration

         if (!$this->argument('model')){

        MigrationGeneration::build(
            $this,
            $lowerPluralized,
            $lowerName,
            $columns,
            $translatedColumns ?? null,
        );
        //models
        ModelGenerations::build(
            $this,
            $name,
            $lowerName,
            $columns,
            $translatedColumns ?? null,
        );

    }
        //create resource
        ResourceGeneration::build(
            $this,
            $name,
            $namespace,
            $columns,
            $translatedColumns ?? null
        );

        //create requests
        RequestGeneration::build(
            $this,
            $columns,
            $translatedColumns ?? null,
            $name,
            $lowerName,
            $namespace
        );
        //create controller
        ControllerGeneration::build(
            $this,
            $pluralized,
            $lowerName,
            $name,
            $namespace,
            [
                'main' => $columns,
                'translated' => $translatedColumns ?? null
            ]
        );

        //append route
        RoutesGeneration::build(
            $this,
            $lowerPluralized,
            $lowerName,
            "$namespace\\{$pluralized}Controller"
        );
    }

    public function getCrudName(): array
    {
        $name = $this->argument('name');

        // Standardize the name format
        $name = Str::of($name)
            ->trim() // Remove leading/trailing spaces
            ->replace(['-', '_'], ' ') // Replace dashes and underscores with spaces
            ->lower() // Convert to lowercase
            ->replace(' ', '_') // Replace spaces with underscores
            ->__toString(); // Convert to string

        return [
            $name, // `test_admin`
            Str::studly($name), // `TestAdmin`
            Str::plural($name), // `test_admins`
            Str::studly(Str::plural($name)), // `TestAdmins`
        ];
    }

    public function getColumns($message = "Enter the name of the database column (or type 'done' to finish)"): array
    {
    $columns = [];
    // Map selected type to Laravel migration types
    $laravelTypes = [
    'string' => 'string',
    'integer' => 'integer',
    'boolean' => 'boolean',
    'text' => 'text',
    'time' => 'date',
    'date' => 'date',
    'timestamp' => 'timestamp',
    'float' => 'float',
    'decimal' => 'decimal',
    'uuid' => 'uuid',
    'longtext' => 'longtext',
    'foreignId' => 'foreignId',
    'image' => 'string',
    'name' => 'string',
    'video' => 'string',
    'file' => 'string',
    'images' => 'longtext',
    'videos' => 'longtext',
    'files' => 'longtext',
    'array' => 'longtext',
    // 'enum' => 'enum'
    ];

    do {
    $column = $this->ask($message);

    if (strtolower($column) !== 'done' && $column) {
    // Ask for column type if not predefined
    if (!array_key_exists($column, $laravelTypes)) {
    $type = $this->choice(
    "Select the type for column '$column'",
    ['string', 'integer', 'boolean', 'text', 'longtext', 'date', 'timestamp', 'float', 'decimal', 'foreignId', 'uuid',
    'image', 'video', 'file', 'images', 'videos', 'files', 'array', 'enum'],
    0
    );

    // If the type is 'enum', ask for values
    if ($type === 'enum') {
    $enumValues = [];
    do {
    $enumValue = $this->ask("Enter a value for the 'enum' type (or type 'done' to finish):");
    if ($enumValue && !in_array($enumValue, $enumValues)) {
    $enumValues[] = $enumValue;
    $this->info("Added enum value: $enumValue");
    } elseif (strtolower($enumValue) === 'done' && count($enumValues) === 0) {
    $this->error("You must add at least one enum value before finishing.");
    }
    } while (strtolower($enumValue) !== 'done' || count($enumValues) < 1);
    $columns[]=[
         'name'=> $column,
        'type' => 'enum',
        'is_enum' => true,
        'enum_values' => $enumValues
        ];

        $migrationType = 'enum';
        } else {
        // Regular column types
        $columns[] = [
        'name' => $column,
        'type' => $migrationType = $laravelTypes[$type],
        'is_media' => in_array($type, ['image', 'file', 'video']),
        'is_list_media' => in_array($type, ['images', 'files', 'videos']),
        'media_type' => in_array($type, ['images', 'files', 'videos', 'image', 'file', 'video']) ? $type : null,
        'is_array' => $type == 'array'
        ];
        }

        } else {
        // If the column name exists in the predefined types, add it directly
        $columns[] = [
        'name' => $column,
        'type' => $migrationType = $laravelTypes[$column],
        'is_media' => in_array($column, ['image', 'file', 'video']),
        'is_list_media' => in_array($column, ['images', 'files', 'videos']),
        'media_type' => in_array($column, ['images', 'files', 'videos', 'image', 'file', 'video']) ? str_replace('s',
        '', $column) : null,
        'is_array' => $column == 'array'
        ];
        }

        $this->info("Column '$column' of type '$migrationType' added.");

        // Ask if the user wants to edit or remove the column
        $action = $this->choice(
        "Do you want to edit or remove any column?",
        ['Edit', 'Remove', 'Continue'],
        2
        );

        if ($action === 'Edit') {
        // Edit column
        $columnToEdit = $this->choice("Which column do you want to edit?", array_column($columns, 'name'));
        $index = array_search($columnToEdit, array_column($columns, 'name'));

        if ($index !== false) {
        // Edit column name
        $newName = $this->ask("Enter the new name for column '$columnToEdit' (leave blank to keep the same):");
        if ($newName) {
        $columns[$index]['name'] = $newName;
        }

        // Edit column type
        $newType = $this->choice("Select the new type for column '$columnToEdit'", ['string', 'integer', 'boolean',
        'text',
        'longtext', 'date', 'timestamp', 'float', 'decimal', 'foreignId', 'uuid', 'image', 'video', 'file', 'images',
        'videos', 'files', 'array', 'enum'], 0);
        $columns[$index]['type'] = $laravelTypes[$newType];
        $this->info("Column '$columnToEdit' has been updated to '$newName' with type '$newType'.");
        }
        } elseif ($action === 'Remove') {
        // Remove column
        $columnToRemove = $this->choice("Which column do you want to remove?", array_column($columns, 'name'));
        $columns = array_filter($columns, fn($col) => $col['name'] !== $columnToRemove);
        $columns = array_values($columns); // Re-index the array
        $this->info("Column '$columnToRemove' has been removed.");
        }
        } elseif (strtolower($column) === 'done' && count($columns) < 1) { $this->error("You must add at least one
            column before finishing.");
            $column = null; // Prevent loop exit
            }
            } while ($column === null || strtolower($column) !== 'done');

            $this->info("You have entered the following columns:");
            foreach ($columns as $col) {
            $this->line("- Name: {$col['name']}, Type: {$col['type']}" . (isset($col['is_enum']) ? ", Enum Values: " .
            implode(', ', $col['enum_values']) : ''));
            }

            return $columns;
            }



    public function indentCode($lines, $space = false)
    {
        if ($space) {
            return implode("\n       ", $lines);
        }
        return implode("\n            ", $lines);
    }


    public function convertToPascalCase(string $columnName): string
    {
        // Remove the '_id' suffix
        $name = str_replace('_id', '', $columnName);

        // Convert the name to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($name))));
    }


}
