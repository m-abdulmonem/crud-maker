<?php

namespace Mabdulmonem\CrudMaker\Services\Managers;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ColumnManager
{

    public function __construct(private readonly  Command $command)
    {

    }

    /**
     * @throws \Exception
     */
    public function __call($method, $parameters)
    {
        if (! method_exists($this, $method)) {
            return $this->command->$method(...$parameters);
        }
        throw new \Exception("Call to undefined method $method");
    }

    protected array $laravelTypes = [
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

    public function getColumns($message = "Enter the name of the database column (or type 'done' to finish)"): array
    {
        $columns = [];

        do {
            $column = $this->ask($message);

            if (strtolower($column) !== 'done' && $column) {
                if (!array_key_exists($column, $this->laravelTypes) || str_contains($column, '_id')) {
                    $type = $this->askForColumnType($column);

                    if ($type === 'pivot') {
                        $this->handlePivotTableColumns($columns);
                    } elseif ($type === 'enum') {
                        $this->handleEnumColumn($columns, $column);
                    } else {
                        $this->handleRegularColumn($columns, $column, $type);
                    }
                } else {
                    $this->handlePredefinedColumn($columns, $column);
                }

                $this->info("Column '$column' of type '{$columns[array_key_last($columns)]['type']}' added.");

                $this->handleColumnEditOrRemove($columns);
            } elseif (strtolower($column) === 'done' && count($columns) < 1) {
                $this->error("You must add at least one column before finishing.");
                $column = null; // Prevent loop exit
            }
        } while ($column === null || strtolower($column) !== 'done');

        $this->displayColumns($columns);

        return $columns;
    }

    protected function askForColumnType($column): string
    {
        return $this->choice(
            "Select the type for column '$column'",
            ['string', 'integer', 'boolean', 'text', 'longtext', 'date', 'timestamp', 'float', 'decimal', 'foreignId', 'uuid',
                'image', 'video', 'file', 'images', 'videos', 'files', 'array', 'enum', 'pivot'],
            0
        );
    }

    protected function handlePivotTableColumns(&$columns)
    {
        $firstTable = $this->ask("Enter the name of the first table for the pivot relationship:");
        $firstTable = strtolower(Str::plural($firstTable));

        $secondTable = $this->ask("Enter the name of the second table for the pivot relationship:");
        $secondTable = strtolower(Str::plural($secondTable));

        $columns[] = [
            'name' => $firstTable . '_id',
            'type' => 'foreignId',
            'is_foreign' => true,
            'references' => 'id',
            'on' => $firstTable,
            'onDelete' => 'cascade'
        ];

        $columns[] = [
            'name' => $secondTable . '_id',
            'type' => 'foreignId',
            'is_foreign' => true,
            'references' => 'id',
            'on' => $secondTable,
            'onDelete' => 'cascade'
        ];

        if ($this->confirm("Do you want to add timestamps to the pivot table?", true)) {
            $columns[] = ['name' => 'created_at', 'type' => 'timestamp', 'nullable' => true];
            $columns[] = ['name' => 'updated_at', 'type' => 'timestamp', 'nullable' => true];
        }

        $this->info("Pivot table columns for '$firstTable' and '$secondTable' have been added.");
    }

    protected function handleEnumColumn(&$columns, $column)
    {
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

        $columns[] = [
            'name' => $column,
            'type' => 'enum',
            'is_enum' => true,
            'enum_values' => $enumValues
        ];
    }

    protected function handleRegularColumn(&$columns, $column, $type)
    {
        $columns[] = [
            'name' => $column,
            'type' => $this->laravelTypes[$type],
            'is_media' => in_array($type, ['image', 'file', 'video']),
            'is_list_media' => in_array($type, ['images', 'files', 'videos']),
            'media_type' => in_array($type, ['images', 'files', 'videos', 'image', 'file', 'video']) ? $type : null,
            'is_array' => $type == 'array'
        ];
    }

    protected function handlePredefinedColumn(&$columns, $column)
    {
        $columns[] = [
            'name' => $column,
            'type' => $this->laravelTypes[$column],
            'is_media' => in_array($column, ['image', 'file', 'video']),
            'is_list_media' => in_array($column, ['images', 'files', 'videos']),
            'media_type' => in_array($column, ['images', 'files', 'videos', 'image', 'file', 'video']) ? str_replace('s', '', $column) : null,
            'is_array' => $column == 'array'
        ];
    }

    protected function handleColumnEditOrRemove(&$columns)
    {
        $action = $this->choice(
            "Do you want to edit or remove any column?",
            ['Edit', 'Remove', 'Continue'],
            2
        );

        if ($action === 'Edit') {
            $this->editColumn($columns);
        } elseif ($action === 'Remove') {
            $this->removeColumn($columns);
        }
    }

    protected function editColumn(&$columns)
    {
        $columnToEdit = $this->choice("Which column do you want to edit?", array_column($columns, 'name'));
        $index = array_search($columnToEdit, array_column($columns, 'name'));

        if ($index !== false) {
            $newName = $this->ask("Enter the new name for column '$columnToEdit' (leave blank to keep the same):");
            if ($newName) {
                $columns[$index]['name'] = $newName;
            }

            $newType = $this->choice("Select the new type for column '$columnToEdit'", array_keys($this->laravelTypes), 0);
            $columns[$index]['type'] = $this->laravelTypes[$newType];
            $this->info("Column '$columnToEdit' has been updated to '$newName' with type '$newType'.");
        }
    }

    protected function removeColumn(&$columns)
    {
        $columnToRemove = $this->choice("Which column do you want to remove?", array_column($columns, 'name'));
        $columns = array_filter($columns, fn($col) => $col['name'] !== $columnToRemove);
        $columns = array_values($columns); // Re-index the array
        $this->info("Column '$columnToRemove' has been removed.");
    }

    protected function displayColumns($columns)
    {
        $this->info("You have entered the following columns:");
        foreach ($columns as $col) {
            $this->line("- Name: {$col['name']}, Type: {$col['type']}" . (isset($col['is_enum']) ? ", Enum Values: " . implode(', ', $col['enum_values']) : ''));
        }
    }
}
