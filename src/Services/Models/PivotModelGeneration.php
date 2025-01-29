<?php

namespace Mabdulmonem\CrudMaker\Services\Models;

use Illuminate\Support\Str;

class PivotModelGeneration
{

    public static function build($command,$columns,$name)
    {

        foreach (self::getPivotColumns($columns) as $column){
            $input = $column['name'];//"spicifcations => key,value";

            $parts = explode('=>', $input, 2);
            $key = trim($parts[0]);
            $values = trim($parts[1] ?? ''); // Handle cases where '=>' might not exist

            $valueArray = array_map('trim', explode(',', $values));

            $names = [Str::singular($name), $key];
            sort($names, SORT_STRING | SORT_FLAG_CASE);
            $pivotTableName = strtolower(join('_',$names));
            $pivotColumns = $valueArray;
        }
    }

    private static function getPivotColumns($columns): array
    {
        return array_filter(
            $columns,
            fn ($i) => $i['type'] == 'pivot'
        );
    }

}
