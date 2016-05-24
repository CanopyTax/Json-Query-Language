<?php
namespace Canopy\JQL;

use stdClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JQL
{
    protected $model;
    protected $query;
    protected $operatorMap = [
        'lt' => '<',
        'gt' => '>',
        'lte' => '<=',
        'gte' => '>=',
        'eq' => '=',
        'ne' => '!=',
        'beginswith' => 'beginswith',
        'endswith' => 'endswith',
        'contains' => 'contains',
        'in' => 'in',
    ];

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->query = $model->query();
    }

    public function convertToFluent($json)
    {
        $json = json_decode($json);

        $query = $this->parseJQL($json->jql, $this->query);

        return $query;
    }

    /**
     * @param array $jql
     * @param Builder $query
     * @param string $binder
     * @return mixed
     */
    public function parseJQL($jql, $query, $binder = 'AND')
    {
        $count = 0;
        foreach ($jql as $item) {
            // If "OR" then iterate through and bind them through orWhere's
            if ($item instanceof stdClass && property_exists($item, 'OR')) {
                if ($count == 0) {
                    $this->parseJQL($item->OR, $query, 'OR');
                } else {
                    $whery = ($binder != 'OR') ? 'where' : 'orWhere';
                    $query->$whery(function ($query) use ($item) {
                        $this->parseJQL($item->OR, $query, 'OR');
                    });
                }
            } else {
                $whery = ($binder != 'OR') ? 'where' : 'orWhere';
                if (is_array($item)) {
                    $query->$whery(function ($query) use ($item) {
                        $this->parseJQL($item, $query);
                    });
                } else {
                    $query = $this->buildQueryOperation($query, $whery, $item->field, $this->operatorMap[$item->operator], $item->value);
                }
            }

            $count++;
        }

        return $query;
    }

    /**
     * @param $query
     * @param $whery
     * @param $field
     * @param $operator
     * @param $value
     * @return mixed
     * @throws \Exception
     */
    private function buildQueryOperation($query, $whery, $field, $operator, $value)
    {
        if (in_array($operator, ['beginswith', 'endswith', 'contains'])) {
            throw new \Exception($operator . ": Not currently defined");
        }

        list($model, $field, $table) = $this->convertToModelNameAndField($field);

        if ($model != $this->getModelName()) {

//            return $this->makeJoinQuery($query, $whery, $table, $field, $operator, $value);
        }

        return  $this->individualQuery($query, $whery, $table, $field, $operator, $value);
    }

    private function makeJoinQuery($query, $whery, $table, $field, $operator, $value)
    {
        /** @var \Illuminate\Database\Query\Builder $query */
        $query->join($table, $table.'.id', '=', $this->getTableName().'.'.str_singular($table).'_id', function($query) use ($whery, $table, $field, $operator, $value) {
            $this->individualQuery($query, $whery, $table, $field, $operator, $value);
        });
        return $query;
    }

    private function individualQuery($query, $whery, $table, $field, $operator, $value)
    {
        $result = $query->$whery($table.'.'.$field, $operator, $value);
        return $result;
    }

    private function convertToModelNameAndField($field)
    {
        $explosions = explode('.', $field);
        if (count($explosions) == 1) {
            $table = snake_case(str_plural($this->getModelName()));
            $field = $explosions[0];
        } elseif (count($explosions) == 2) {
            $table = $explosions[0];
            $field = $explosions[1];
        } else {
            throw new \Exception('Format must be model.field, eg: mamals.speed');
        }
        $model = studly_case(str_singular($table));
        return [$model, $field, $table];
    }

    /**
     * Return the name of $this->model.
     *
     * @return string
     */
    public function getModelName()
    {
        $reflection = new \ReflectionClass($this->model);

        return $reflection->getShortName();
    }

    private function getTableName()
    {
        $table = snake_case(str_plural($this->getModelName()));
        return $table;
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param Model $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }
}
