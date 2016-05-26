<?php
namespace Canopy\JQL;

use Canopy\JQL\Exceptions\JQLException;
use ReflectionClass;
use stdClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JQL
{
    /** @var Model */
    protected $model;

    /** @var Builder */
    protected $query;

    /** @var array */
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

    /** @var array */
    protected $joinedModels = [];

    /** @var array */
    protected $approvedModels = [];

    /** @var array */
    protected $modelMapping = [];

    /**
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->modelName = $this->getModelName();
        $this->table = isset($this->model->table) ? $this->model->table : snake_case(str_plural($this->getModelName()));
        $this->query = $model->query();
    }

    /**
     * @param string $json
     * @return Builder
     */
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
     * @return Builder
     * @throws JQLException
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
                    // Validate operator is allowed
                    if (!isset($this->operatorMap[$item->operator]) ||
                        in_array($item->operator, ['beginswith', 'endswith', 'contains'])
                    ) {
                        throw new JQLException($item->operator . ": Not currently defined");
                    }

                    $query = $this->buildQueryOperation(
                        $query,
                        $whery,
                        $item->field,
                        $this->operatorMap[$item->operator],
                        $item->value
                    );
                }
            }

            $count++;
        }

        return $query;
    }

    /**
     * @param Builder $query
     * @param string $whery
     * @param string $field
     * @param string $operator
     * @param string|int|bool $value
     * @return Builder
     */
    private function buildQueryOperation($query, $whery, $field, $operator, $value)
    {
        list($model, $field, $table) = $this->convertToModelNameAndField($field);

        if ($model != $this->modelName) {
            if (!in_array($model, $this->joinedModels)) {
                $this->query->join($table, $table.'.id', '=', $this->table.'.'.str_singular($table).'_id');
                $this->individualQuery($query, $whery, $table, $field, $operator, $value);
                $this->joinedModels[] = $model;
                return $query;
            }
        }

        return $this->individualQuery($query, $whery, $table, $field, $operator, $value);
    }

    /**
     * @param Builder $query
     * @param string $whery
     * @param string $table
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return Builder
     */
    private function individualQuery($query, $whery, $table, $field, $operator, $value)
    {
        if ($operator == 'in') {
            return $query->{$whery.'In'}($table.'.'.$field, $value);
        }
        return $query->$whery($table.'.'.$field, $operator, $value);
    }

    /**
     * @param string $field
     * @return array
     * @throws JQLException
     */
    private function convertToModelNameAndField($field)
    {
        $explosions = explode('.', $field);
        if (count($explosions) == 1) {
            $table = $this->table;
            $model = $this->modelName;
            $field = $explosions[0];
        } elseif (count($explosions) == 2) {
            $table = $explosions[0];
            $modelTable = snake_case(str_plural($this->modelName));
            $model = studly_case(str_singular($table));
            if ($table == $modelTable) {
                $model = $this->modelName;
                $table = $this->table;
            }
            $field = $explosions[1];
        } else {
            throw new JQLException('Format must be model.field, eg: mammals.speed');
        }
        return [$model, $field, $table];
    }

    /**
     * Return the name of $this->model.
     *
     * @return string
     */
    public function getModelName()
    {
        $reflection = new ReflectionClass($this->model);

        return $reflection->getShortName();
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

    /**
     * @return array
     */
    public function getApprovedModels()
    {
        return $this->approvedModels;
    }

    /**
     * @param array $approvedModels
     */
    public function setApprovedModels(array $approvedModels)
    {
        $this->approvedModels = $approvedModels;
    }
}
