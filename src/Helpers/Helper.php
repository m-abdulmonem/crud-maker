<?php
namespace  Mabdulmonem\CrudMaker\Helpers;

use Illuminate\Support\Facades\File;



class Helper {



    public static function getStub(string $name)
    {
        $path = file_exists(base_path("stubs/vendor/mabdulmonem/crud-maker/$name.stub"))
        ? base_path("stubs/vendor/mabdulmonem/crud-maker/$name.stub")
        : __DIR__ . "/../../stubs/$name.stub";

        return File::get($path);
    }

}


