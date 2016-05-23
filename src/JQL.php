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
                    $query->where(
                        function ($query) use ($item) {
                            $this->parseJQL($item->OR, $query, 'OR');
                        }
                    );
                }
            } else {
                if ($binder == 'OR') {
                    if (is_array($item)) {
                        $query->orWhere(
                            function ($query) use ($item) {
                                $this->parseJQL($item, $query);
                            }
                        );
                    } else {
                        $query->orWhere($item->field, $this->operatorMap[$item->operator], $item->value);
                    }
                } else {
                    if (is_array($item)) {
                        $query->where(
                            function ($query) use ($item) {
                                $this->parseJQL($item, $query);
                            }
                        );
                    } else {
                        $query->where($item->field, $this->operatorMap[$item->operator], $item->value);
                    }
                }
            }

            $count++;
        }
        return $query;
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
