<?php
class DB extends \Illuminate\Support\Facades\DB
{
    /**
     * Get a new raw query expression.
     *
     * @param mixed $value
     * @return \Illuminate\Database\Query\Expression
     * @static
     */
    public static function raw($value)
    {
        return new \Illuminate\Database\Query\Expression($value);
    }
}
