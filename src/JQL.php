<?php
namespace CanopyTax\JQL;

use CanopyTax\JQL\Exceptions\JQLDecodeException;
use CanopyTax\JQL\Exceptions\JQLException;
use CanopyTax\JQL\Exceptions\JQLValidationException;
use Illuminate\Database\Query\Expression;
use ReflectionClass;
use stdClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use DB;

class JQL
{
    /** @var Model */
    protected $mainModel;

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
        'beginswith' => 'like',
        'between' =>  'between',
        'endswith' => 'endswith',
        'contains' => 'contains',
        'in' => 'in',
        'nin' => 'not in',
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
     * ['table1' => ['table_1.id', 'main_table.table_1_id],
     *           'table_2' => ['main_table.id', 'table_2.main_table_id]]
     *
     * @var array
     */
    protected $tableMap = [];

    /**
     * Mapping of field aliases to where in the database they reference.
     * An entry only exists if the alias does not match how it is stored in the database.
     * All fields in this mapping are treated as raw SQL to allow complex queries.
     * ['Record.A' => ['main_table', 'main_table.field_1', 'search_term'],
     *        'Record.B' => ['table_2', 'table_2.json_data->>\'b\'', '{{search_term}}/1000]]
     *
     * @var array
     */
    protected $fieldMap = [];

    /*
     * Mapping of fields and their potential ovveride paterns.
     *
     * //below function is a placeholder, a special todo that would be nice.
     * ['fieldname' => ['operator' => ['field' =>  'fieldname', 'operator' =>  'newOperator', 'value' =>  'newValue'|funciton(field, operator, value) ]]]
     */
    protected $fieldOverrideMap = [];

    /**
     * @param Model $mainModel
     * @param string $mainModelAlias
     */
    public function __construct(Model $mainModel, $mainModelAlias = null)
    {
        $this->setMainModel($mainModel, $mainModelAlias);
    }

    /**
     * @param string|object $json
     * @return Builder
     * @throws JQLDecodeException
     * @throws JQLException
     * @throws JQLValidationException
     */
    public function convertToFluent($json)
    {
        if (!is_object($json)) {
            if (!is_string($json)) {
                throw new JQLException('JSON string or object was expected');
            }
            $json = json_decode($json);
            if (is_null($json)) {
                throw new JQLDecodeException();
            }
        }

        if (!isset($json->jql)) {
            throw new JQLValidationException('Missing jql property of JSON');
        }
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

        $bindings = $query->getBindings();
        $newBindings = [];
        foreach ($bindings as $binding) {
            if (!is_object($binding) && !is_null($binding) && $binding != 'null') {
                $newBindings[] = $binding;
            }

        }
        $query->setBindings($newBindings);

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
        list($table, $field, $modelFieldAlias) = $this->convertToRealValues($modelFieldAlias, $operatorAlias);
        $joinType = (is_null($value)) ? 'left' : 'inner';
        list($field, $value, $operatorAlias, $bindings) = $this->overrideKeys($modelFieldAlias, $value, $operatorAlias, $field);
        if (!empty($bindings)) {
            /** @var \Illuminate\Database\Query\Builder $query */
            $query->addBinding($bindings, $whery);
        } elseif ($operatorAlias === 'beginswith') {
            // Strings should always be used with beginswith
            $value .= '%';
        }
        $operator = $this->operatorMap[$operatorAlias];

        $this->joinTableIfNeeded($table, $joinType);
        return $this->individualQuery($query, $whery, $field, $operator, $value);
    }

    public function overrideKeys($modelFieldAlias, $value, $operatorAlias, $field) {
        $bindings = [];
        $newField = \DB::raw($field);
        $newOperator = $operatorAlias;
        $newValues = $value;
        if (array_key_exists($modelFieldAlias, $this->fieldOverrideMap)) {
            if (
                array_key_exists($overrideOperator = $operatorAlias, $this->fieldOverrideMap[$modelFieldAlias]) ||
                array_key_exists($overrideOperator = 'any', $this->fieldOverrideMap[$modelFieldAlias])
            ) {
                $override = $this->fieldOverrideMap[$modelFieldAlias][$overrideOperator];

                // Field
                if (array_key_exists('field', $override)) {
                    $fieldOverride = $override['field'];
                    if (
                        is_array($override['field']) && (
                            ( !is_array($value) && array_key_exists($key = (is_null($value) ? 'null' : (is_bool($value) ? boolval($value) : $value)), $override['field'])) ||
                            array_key_exists($key = 'any', $override['field'])
                        )
                    ) {
                        $fieldOverride = $override['field'][$key];
                    }
                    if ((strpos($fieldOverride, ' and ') !== false || strpos($fieldOverride, ' AND ') !== false)) {
                        if (is_null($value)) {
                            $fieldOverride = preg_replace("/^(?:(.*)\s=\s.*)AND/i", "$1 is null AND", $fieldOverride);
                        }
                    }
                    $newField = \DB::raw(str_replace('{{field}}', $this->fieldMap[$modelFieldAlias][1], $fieldOverride));

                }

                // Operator
                if (array_key_exists('operator', $override)) {
                    $newOperator = $override['operator'];
                    list($eTable, $eField) = explode('.', $modelFieldAlias);
                    if (!isset($this->approvedOperators[$eTable]) ||
                        !isset($this->approvedOperators[$eTable][$eField]) ||
                        !in_array($newOperator, $this->approvedOperators[$eTable][$eField])
                    ) {
                        throw new JQLValidationException($newOperator.': Not allowed');
                    }
                }

                // Value
                if (array_key_exists('value', $override)) {
                    if (is_array($value)) {
                        $newValues = [];
                        foreach ($value as $item) {
                            list($newValue, $value) = $this->replaceValue($override, $value);
                            $newValues[] = $newValue;
                            $bindings[] = $item;
                        }
                    } else {
                        list($newValues, $value) = $this->replaceValue($override, $value);
                        $bindings[] = $value;
                    }
                }
            }
        }
        return [$newField, $newValues, $newOperator, $bindings];
    }

    /**
     * @param $override
     * @param $value
     * @return array
     */
    public function replaceValue($override, $value) {
        $newValues = \DB::raw(str_replace('{{value}}', '?', $override['value']));
        if (is_array($override['value'])) {
            $value = is_null($value) ? 'null' : $value;
            if (
                !is_array($value) &&
                array_key_exists($replacer = strtolower($value), $override['value']) ||
                array_key_exists($replacer = 'any', $override['value'])
            ) {
                $newValues = \DB::raw(str_replace('{{value}}', '?', $override['value'][$replacer]));
            }
        }
        return [$newValues, $value];
    }

    /**
     * @param string $table
     * @param string $joinType
     * @return mixed
     */
    private function joinTableIfNeeded($table, $joinType = 'inner')
    {
        if (!in_array($table, $this->joinedTables)) {
            $this->joinedTables[] = $table;

            if (isset($this->tableMap[$table][2])) {
                $this->joinTableIfNeeded($this->tableMap[$table][2], $joinType);
            }

            /* @var \Illuminate\Database\Query\Builder $query */
            $query = $this->query;
            $query->join(
                $table,
                $this->tableMap[$table][0],
                '=',
                $this->tableMap[$table][1],
                $joinType
            );

        }
    }

    /**
     * @param Builder|\Illuminate\Database\Query\Builder $query
     * @param string $whery
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return Builder
     */
    private function individualQuery($query, $whery, $field, $operator, $value)
    {
        /** @var \Illuminate\Database\Query\Expression $value */
        if (is_null($value) || $value === "is null" || ($value instanceof Expression && $value->getValue() === "is null")) {
            $boolean = $whery == 'orWhere' ? 'or' : 'and';
            if (in_array($operator, ['in', 'nin'])) {
                $operator = ($operator === 'in') ? '=' : '!=';
            }
            return $query->whereNull($field, $boolean, $operator != '=');
        } else {
            switch ($operator) {
                case 'in':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $query->{$whery . 'In'}($field, $value);

                    return $query;
                case 'not in':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $query->{$whery . 'NotIn'}($field, $value);

                    return $query;
                case 'between':
                    $query->$whery(function (Builder $query) use ($field, $value) {
                        $query->where($field, '>', $value[0]);
                        $query->where($field, '<', $value[1]);
                    });
                    return $query;
            }
        }

        if (is_bool($value)) {
            $value = ($value) ? 'true' : 'false';
        }

        return $query->$whery($field, $operator, $value);
    }

    /**
     * @param string $modelFieldAlias
     * @param string $operatorAlias
     * @return array
     * @throws JQLException
     */
    private function convertToRealValues($modelFieldAlias, $operatorAlias)
    {
        $explosions = explode('.', $modelFieldAlias);
        if (count($explosions) == 1) {
            $modelAlias = $this->mainModelAlias;
            $fieldAlias = $explosions[0];
        } elseif (count($explosions) == 2) {
            $modelAlias = $explosions[0];
            $fieldAlias = $explosions[1];
        } else {
            throw new JQLValidationException('Format must be model.field, eg: mammals.speed');
        }

        if (!isset($this->approvedOperators[$modelAlias]) ||
            !isset($this->approvedOperators[$modelAlias][$fieldAlias])
        ) {
            throw new JQLValidationException($modelFieldAlias.': Not allowed');
        }

        if (!in_array($operatorAlias, $this->approvedOperators[$modelAlias][$fieldAlias])) {
            throw new JQLValidationException($modelFieldAlias.': Operator "'.$operatorAlias.'" not allowed');
        }

        $modelFieldAlias = $modelAlias.'.'.$fieldAlias;
        $table = $modelAlias;
        if (isset($this->fieldMap[$modelFieldAlias])) {
            $table = $this->fieldMap[$modelFieldAlias][0];
            $field = $this->fieldMap[$modelFieldAlias][1];
        } else {
            $field = '`'.$modelAlias.'`.`'.$fieldAlias.'`';
        }

        if (!isset($this->tableMap[$table]) && $table !== $this->mainModel->getTable()) {
            throw new JQLException($modelFieldAlias.': Could not find way to join table');
        }

        return [$table, $field, $modelFieldAlias];
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
        $this->query = $mainModel->newQueryWithoutScopes();
        $this->mainModelAlias = is_null($mainModelAlias) ? $reflection->getShortName() : $mainModelAlias;
        $this->joinedTables = [$mainModel->getTable()];
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
     * Set mapping of field aliases to where in the database they reference
     * ['Record.A' => ['main_table', 'main_table.field_1'], 'Record.B' => ['table_2', 'table_2.json_data->>\'b\'']]
     *
     * @param array $fieldMap
     * @return $this
     */
    public function setFieldMap(array $fieldMap)
    {
        $this->fieldMap = $fieldMap;
        return $this;
    }

    /**
     * @return array
     */
    public function getFieldMap()
    {
        return $this->fieldMap;
    }

    /**
     * @return array
     */
    public function getFieldOverrideMap()
    {
        return $this->fieldOverrideMap;
    }

    /**
     * @param array $fieldOverrideMap
     * @return $this
     */
    public function setFieldOverrideMap($fieldOverrideMap)
    {
        $this->fieldOverrideMap = $fieldOverrideMap;
        return $this;
    }
}
