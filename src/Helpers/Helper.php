<?php
namespace  Mabdulmonem\CrudMaker\Helpers;

use Illuminate\Support\Facades\File;



class Helper {



    public static function getStub(string $name): string
    {
        $path = file_exists(base_path("maker_stubs/vendor/mabdulmonem/crud-maker/$name.stub"))
        ? base_path("maker_stubs/vendor/mabdulmonem/crud-maker/$name.stub")
        : __DIR__ . "/../../maker_stubs/$name.stub";

        return File::get($path);
    }

}


