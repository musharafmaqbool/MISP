<?php
App::uses('MysqlExtended', 'Model/Datasource/Database');

/**
 * Overrides the default MySQL database implementation to support the following features:
 * - Set query hints to optimize queries
 */
class MysqlObserverExtended extends MysqlExtended
{
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> 64c0e5251 (fix: [dbo support check] added)
    public $supports = [
        'indexHints' => true,
        'ignoreIndexHints' => true,
        'reverseJoin' => true,
        'straightJoin' => true,
        'insertMulti' => true,
    ];
<<<<<<< HEAD
=======
=======

>>>>>>> 64c0e5251 (fix: [dbo support check] added)
    /**
     * Builds and generates a JOIN condition from an array. Handles final clean-up before conversion.
     *
     * @param array $join An array defining a JOIN condition in a query.
     * @return string An SQL JOIN condition to be used in a query.
     * @see DboSource::renderJoinStatement()
     * @see DboSource::buildStatement()
     */
    public function buildJoinStatement($join, $reversed_alias = null, $reversed_table = null) {
        $data = array_merge(array(
            'type' => null,
            'alias' => null,
            'table' => 'join_table',
            'conditions' => '',
        ), $join);

        if (!empty($reversed_alias)) {
            $data['alias'] = $this->name($reversed_alias);
        } else if (!empty($data['alias'])) {
            $data['alias'] = $this->alias . $this->name($data['alias']);
        }
        if (!empty($data['conditions'])) {
            $data['conditions'] = trim($this->conditions($data['conditions'], true, false));
        }
        if (!empty($reversed_table)) {
            $data['table'] = $reversed_table;
		} else if (!empty($data['table']) && (!is_string($data['table']) || strpos($data['table'], '(') !== 0)) {
			$data['table'] = $this->fullTableName($data['table']);
		}
		return $this->renderJoinStatement($data);
	}

    /**
<<<<<<< HEAD
     * Builds an SQL statement.
     *
     * This is merely a convenient wrapper to DboSource::buildStatement().
     *
     * @param Model $Model The model to build an association query for.
     * @param array $queryData An array of queryData information containing keys similar to Model::find().
     * @return string String containing an SQL statement.
     * @see DboSource::buildStatement()
     * @see DboSource::buildAssociationQuery()
     */
    public function buildAssociationQuery(Model $Model, $queryData)
    {
        $queryData = $this->_scrubQueryData($queryData);

        return $this->buildStatement(
            array(
                'fields' => $this->prepareFields($Model, $queryData),
                'table' => $this->fullTableName($Model),
                'alias' => $Model->alias,
                'limit' => $queryData['limit'],
                'offset' => $queryData['offset'],
                'joins' => $queryData['joins'],
                'conditions' => $queryData['conditions'],
                'order' => $queryData['order'],
                'group' => $queryData['group'],
                'having' => $queryData['having'],
                'lock' => $queryData['lock'],
                'forceIndexHint' => $queryData['forceIndexHint'] ?? null,
                'ignoreIndexHint' => $queryData['ignoreIndexHint'] ?? null,
            ),
            $Model
        );
    }

    /**
     * Renders a final SQL statement by putting together the component parts in the correct order
     * 
     * Edit: Added support for query hints
     *
     * @param string $type type of query being run. e.g select, create, update, delete, schema, alter.
     * @param array $data Array of data to insert into the query.
     * @return string|null Rendered SQL expression to be run, otherwise null.\
     * @see DboSource::renderStatement()
     */
    public function renderStatement($type, $data)
    {
        if ($type === 'select') {
            extract($data);
            $having = !empty($having) ? " $having" : '';
            $lock = !empty($lock) ? " $lock" : '';
            return rtrim("SELECT {$fields} FROM {$table} {$alias} {$indexHint} {$ignoreIndexHint} {$joins} {$conditions} {$group}{$having} {$order} {$limit}{$lock}");
        }
        return parent::renderStatement($type, $data);
    }

    /**
     * Builds the force index hint for the query
     * 
     * @param string|null $forceIndexHint INDEX hint
     * @return string
     */
    private function __buildIndexHint($forceIndexHint = null): ?string
    {
        return isset($forceIndexHint) ? ('FORCE INDEX (' . $forceIndexHint . ')') : null;
    }

        /**
     * Builds the ignore index hint for the query
     * 
     * @param string|null $ignoreIndexHint INDEX hint
     * @return string
     */
    private function __buildIgnoreIndexHint($ignoreIndexHint = null): ?string
    {
        return isset($ignoreIndexHint) ? ('IGNORE INDEX (' . $ignoreIndexHint . ')') : null;
    }
>>>>>>> 129ab6ec1 (chg: [objects restsearch] speed up attempt)

    /**
=======
>>>>>>> f862af33a (new: [database handler rework] Unified extended and observer extended)
     * - Do not call microtime when not necessary
     * - Count query count even when logging is disabled
     *
     * @param string $sql
     * @param array $options
     * @param array $params
     * @return mixed
     */
    public function execute($sql, $options = [], $params = [])
    {
        $log = $options['log'] ?? $this->fullDebug;
        if (Configure::read('Plugin.Benchmarking_enable')) {
            $log = true;
        }
        $comment = sprintf(
            '%s%s%s',
            empty(Configure::read('CurrentUserId')) ? '' : sprintf(
                '[User: %s] ',
                intval(Configure::read('CurrentUserId'))
            ),
            empty(Configure::read('CurrentController')) ? '' : preg_replace('/[^a-zA-Z0-9_]/', '', Configure::read('CurrentController')) . ' :: ',
            empty(Configure::read('CurrentAction')) ? '' : preg_replace('/[^a-zA-Z0-9_]/', '', Configure::read('CurrentAction'))
        );
        $sql = '/* ' . $comment . ' */ ' . $sql;
        if ($log) {
            $t = microtime(true);
            $this->_result = $this->_execute($sql, $params);
            $this->took = round((microtime(true) - $t) * 1000);
            $this->numRows = $this->affected = $this->lastAffected();
            $this->logQuery($sql, $params);
        } else {
            $this->_result = $this->_execute($sql, $params);
            $this->_queriesCnt++;
        }

        return $this->_result;
    }
}
