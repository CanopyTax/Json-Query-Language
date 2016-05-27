<?php
namespace CanopyTax\JQL;

use CanopyTax\JQL\Exceptions\JQLException;
use CanopyTax\JQL\Exceptions\JQLValidationException;
use ReflectionClass;
use stdClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JQL
{
    /** @var Model */
    protected $mainModel;

    /** @var string */
    protected $mainModelName;

    /** @var string */
    protected $mainModelAlias;

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

    /**
     * names of tables that have been joined
     *
     * @var array
     */
    protected $joinedTables = [];

    /**
     * ['modelAlias' => ['fieldAlias' => ['operator']]]
     *
     * @var array
     */
    protected $approvedOperators = [];

    /**
     * Mapping of how all the tables join back to the main table
     * ['table1' => ['table_1.id', 'main_table.table_1_id], 'table_2' => ['main_table.id', 'table_2.main_table_id]]
     *
     * @var array
     */
    protected $tableMap = [];

    /**
     * Mapping of field aliases to where in the database they come from
     * ['Record.A' => ['main_table', 'main_table.field_1'], 'Record.B' => ['table_2', 'table_2.json_data->>\'b\'']]
     *
     * @var array
     */
    protected $fieldMap = [];

    /**
     * @param Model $mainModel
     * @param string $mainModelAlias
     */
    public function __construct(Model $mainModel, $mainModelAlias = null)
    {
        $this->setMainModel($mainModel, $mainModelAlias);
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
                        in_array($item->operator, ['endswith', 'contains'])
                    ) {
                        throw new JQLValidationException($item->operator . ": Not currently defined");
                    }

                    $query = $this->buildQueryOperation(
                        $query,
                        $whery,
                        $item->field,
                        $item->operator,
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
     * @param string $modelFieldAlias
     * @param string $operatorAlias
     * @param string|int|bool $value
     * @return Builder
     * @throws JQLValidationException
     */
    private function buildQueryOperation($query, $whery, $modelFieldAlias, $operatorAlias, $value)
    {
        list($field, $table, $modelAlias, $fieldAlias) = $this->convertToModelNameAndField($modelFieldAlias);

        if (!in_array($operatorAlias, $this->approvedOperators[$modelAlias][$fieldAlias])) {
            throw new JQLValidationException($modelFieldAlias.': Operator "'.$operatorAlias.'" Not allowed');
        }

        $operator = $this->operatorMap[$operatorAlias];

        if ($table != $this->mainModel->getTable()) {
            if (!in_array($table, $this->joinedTables)) {
                $this->query->join(
                    $table,
                    $table.'.id',
                    '=',
                    $this->mainModel->getTable().'.'.str_singular($table).'_id'
                );
                $this->individualQuery($query, $whery, $table, $field, $operator, $value);
                $this->joinedTables[] = $table;
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
     * @param string $modelFieldAlias
     * @return array
     * @throws JQLException
     */
    private function convertToModelNameAndField($modelFieldAlias)
    {
        $explosions = explode('.', $modelFieldAlias);
        if (count($explosions) == 1) {
            $table = $this->mainModel->getTable();
            $modelAlias = $this->mainModelAlias;
            $fieldAlias = $explosions[0];
            $field = $explosions[0];
        } elseif (count($explosions) == 2) {
            $modelAlias = $explosions[0];
            $fieldAlias = $explosions[1];
            $table = $explosions[0];
            $modelTable = snake_case(str_plural($this->mainModelName));
            if ($table == $modelTable) {
                $table = $this->mainModel->getTable();
            }
            $field = $explosions[1];
        } else {
            throw new JQLValidationException('Format must be model.field, eg: mammals.speed');
        }

        if (!isset($this->approvedOperators[$modelAlias]) ||
            !isset($this->approvedOperators[$modelAlias][$fieldAlias])
        ) {
            throw new JQLValidationException($modelFieldAlias.': Not allowed');
        }
        return [$field, $table, $modelAlias, $fieldAlias];
    }

    /**
     * @param Model $mainModel
     * @param string $mainModelAlias
     * @return $this
     */
    public function setMainModel($mainModel, $mainModelAlias = null)
    {
        $this->mainModel = $mainModel;
        $reflection = new ReflectionClass($mainModel);
        $this->mainModelName = $reflection->getShortName();
        $this->query = $mainModel->query();
        $this->mainModelAlias = is_null($mainModelAlias) ? $this->mainModelName : $mainModelAlias;
        return $this;
    }

    /**
     * @return Model
     */
    public function getMainModel()
    {
        return $this->mainModel;
    }

    /**
     * Return the name of $this->model.
     *
     * @return string
     */
    public function getMainModelName()
    {
        return $this->mainModelName;
    }

    /**
     * @return string
     */
    public function getMainModelAlias()
    {
        return $this->mainModelAlias;
    }

    /**
     * Create a whitelist of what operators on what fields on what models are approved
     *
     * @param array $approvedOperators ['modelAlias' => ['fieldAlias' => ['operator']]]
     * @return $this
     */
    public function setApprovedOperators(array $approvedOperators)
    {
        $this->approvedOperators = $approvedOperators;
        return $this;
    }

    /**
     * @return array
     */
    public function getApprovedOperators()
    {
        return $this->approvedOperators;
    }

    /**
     * Set mapping of how all tables being queried join back to the main table
     * ['table1' => ['table_1.id', 'main_table.table_1_id], 'table_2' => ['main_table.id', 'table_2.main_table_id]]
     *
     * @param array $tableMap
     * @return $this
     */
    public function setTableMap(array $tableMap)
    {
        $this->tableMap = $tableMap;
        return $this;
    }

    /**
     * @return array
     */
    public function getTableMap()
    {
        return $this->tableMap;
    }

    /**
     * @param array $fieldMap
     * @return $this
     */
    public function setFieldMap(array $fieldMap)
    {
        $this->fieldMap = $fieldMap;
        return $this;
    }

    public function getFieldMap()
    {
        return $this->fieldMap;
    }
}
