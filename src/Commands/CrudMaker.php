<?php

namespace Mabdulmonem\CrudMaker\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mabdulmonem\CrudMaker\Services\Http\ControllerGeneration;
use Mabdulmonem\CrudMaker\Services\Http\RequestGeneration;
use Mabdulmonem\CrudMaker\Services\Http\ResourceGeneration;
use Mabdulmonem\CrudMaker\Services\Http\RoutesGeneration;
use Mabdulmonem\CrudMaker\Services\Models\MigrationGeneration;
use Mabdulmonem\CrudMaker\Services\Models\ModelGenerations;

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

        $columns = $this->getColumns();


        if ($this->hasOption('translated')) {
//            $this->info('Please enter translations table column');
            $translatedColumns = $this->getColumns("Enter the name of the translations table column (or type 'done' to finish)");
        }

        //create migration

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
            'video' => 'string',
            'file' => 'string',
            'images' => 'longtext',
            'videos' => 'longtext',
            'files' => 'longtext',
            'array' => 'longtext',
        ];

        do {
            $column = $this->ask($message);

            //  $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            if (strtolower($column) !== 'done' && $column) {

                if (!array_key_exists($column, $laravelTypes)) {
                    $type = $this->choice(
                        "Select the type for column '$column'",
                        ['string', 'integer', 'boolean', 'text', 'longtext', 'date', 'timestamp', 'float', 'decimal', 'foreignId', 'uuid', 'image', 'video', 'file', 'images', 'videos', 'files', 'array'],
                        0
                    );
                    $columns[] = [
                        'name' => $column,
                        'type' => $migrationType = $laravelTypes[$type],
                        'is_media' => in_array($type, ['image', 'file', 'video']),
                        'is_list_media' => in_array($type, ['images', 'files', 'videos']),
                        'media_type' => in_array($type, ['images', 'files', 'videos', 'image', 'file', 'video']) ? $type : null,
                        'is_array' => $type == 'array'
                    ];
                } else {
                    $columns[] = [
                        'name' => $column,
                        'type' => $migrationType = $laravelTypes[$column],
                        'is_media' => in_array($column, ['image', 'file', 'video']),
                        'is_list_media' => in_array($column, ['images', 'files', 'videos']),
                        'media_type' => in_array($column, ['images', 'files', 'videos', 'image', 'file', 'video']) ? str_replace('s', '', $column) : null,
                        'is_array' => $column == 'array'
                    ];
                }


                $this->info("Column '$column' of type '$migrationType' added.");
            } elseif (strtolower($column) === 'done' && count($columns) < 1) {
                $this->error("You must add at least one column before finishing.");
                $column = null; // Prevent loop exit
            }
        } while ($column === null || strtolower($column) !== 'done');

        $this->info("You have entered the following columns:");
        foreach ($columns as $col) {
            $this->line("- Name: {$col['name']}, Type: {$col['type']}");
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
