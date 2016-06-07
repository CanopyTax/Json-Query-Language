<?php namespace CanopyTax\Test;

use Illuminate\Database\Eloquent\Model;

class Mammal extends Model
{
    public $table = 'bobs';
    public function getSql()
    {
        $builder = $this->getBuilder();
        $sql = $builder->toSql();
        foreach($builder->getBindings() as $binding)
        {
            $value = is_numeric($binding) ? $binding : "'".$binding."'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        return $sql;
    }
}
