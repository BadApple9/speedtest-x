<?php

namespace SleekDB\Traits;

use SleekDB\Exceptions\EmptyConditionException;
use SleekDB\Exceptions\EmptyFieldNameException;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\InvalidOrderException;

/**
   * Coditions trait.
   */
  trait ConditionTrait {

    /**
     * Select specific fields or exclude fields with - (minus) prepended
     * @param string[] $fieldNames
     * @return $this
     * @throws InvalidArgumentException
     */
    public function select($fieldNames){
      $errorMsg = "if select is used an array containing strings with fieldNames has to be given";
      if(!is_array($fieldNames)) throw new InvalidArgumentException($errorMsg);
      foreach ($fieldNames as $fieldName){
        if(empty($fieldName)) continue;
        if(!is_string($fieldName)) throw new InvalidArgumentException($errorMsg);
        $this->fieldsToSelect[] = $fieldName;
      }
      return $this;
    }

    /**
     * @param string[] $fieldNames
     * @return $this
     * @throws InvalidArgumentException
     */
    public function except($fieldNames){
      $errorMsg = "if except is used an array containing strings with fieldNames has to be given";
      if(!is_array($fieldNames)) throw new InvalidArgumentException($errorMsg);
      foreach ($fieldNames as $fieldName){
        if(empty($fieldName)) continue;
        if(!is_string($fieldName)) throw new InvalidArgumentException($errorMsg);
        $this->fieldsToExclude[] = $fieldName;
      }
      return $this;
    }

    /**
     * Add conditions to filter data.
     * @param string $fieldName
     * @param string $condition
     * @param mixed $value
     * @return $this
     * @throws EmptyConditionException
     * @throws EmptyFieldNameException
     */
    public function where( $fieldName, $condition, $value ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name in where condition can not be empty.' );
      if ( empty( $condition ) ) throw new EmptyConditionException( 'The comparison operator can not be empty.' );
      // Append the condition into the conditions variable.
      $this->conditions[] = [
        'fieldName' => $fieldName,
        'condition' => trim( $condition ),
        'value'     => $value
      ];
      return $this;
    }


    /**
     * @param string $fieldName
     * @param array $values
     * @return $this
     * @throws EmptyFieldNameException
     */
    public function in ( $fieldName, $values = [] ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name for in clause can not be empty.' );
      $values = (array) $values;
      $this->in[] = [
        'fieldName' => $fieldName,
        'value'     => $values
      ];
      return $this;
    }

    /**
     * @param string $fieldName
     * @param array $values
     * @return $this
     * @throws EmptyFieldNameException
     */
    public function notIn ( $fieldName, $values = [] ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name for notIn clause can not be empty.' );
      $values = (array) $values;
      $this->notIn[] = [
        'fieldName' => $fieldName,
        'value'     => $values
      ];
      return $this;
    }

    /**
     * Add or-where conditions to filter data.
     * @param string|array|mixed $condition,... (string fieldName, string condition, mixed value) OR ([string fieldName, string condition, mixed value],...)
     * @return $this
     * @throws EmptyConditionException
     * @throws EmptyFieldNameException
     * @throws InvalidArgumentException
     */
    public function orWhere( $condition ) {
      $args = func_get_args();
      foreach ($args as $key => $arg){
        if($key > 0) throw new InvalidArgumentException("Allowed: (string fieldName, string condition, mixed value) OR ([string fieldName, string condition, mixed value],...)");
        if(is_array($arg)){
          // parameters given as arrays for an "or where" with "and" between each condition
          $this->orWhereWithAnd($args);
          break;
        }
        if(count($args) === 3 && is_string($arg) && is_string($args[1])){
          // parameters given as (string fieldName, string condition, mixed value) for a single "or where"
          $this->singleOrWhere($arg, $args[1], $args[2]);
          break;
        }
      }

      return $this;
    }

    /**
     * Add or-where conditions to filter data.
     * @param string $fieldName
     * @param string $condition
     * @param mixed $value
     * @return $this
     * @throws EmptyConditionException
     * @throws EmptyFieldNameException
     */
    private function singleOrWhere( $fieldName, $condition, $value ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name in orWhere condition can not be empty.' );
      if ( empty( $condition ) ) throw new EmptyConditionException( 'The comparison operator can not be empty.' );
      // Append the condition into the orConditions variable.
      $this->orConditions[] = [
        'fieldName' => $fieldName,
        'condition' => trim( $condition ),
        'value'     => $value
      ];
      return $this;
    }

    /**
     * @param array $conditions
     * @return $this
     * @throws EmptyConditionException
     * @throws InvalidArgumentException
     */
    private function orWhereWithAnd($conditions){

      if(!(count($conditions) > 0)){
        throw new EmptyConditionException("You need to specify a where clause");
      }

      foreach ($conditions as $key => $condition){

        if(!is_array($condition)){
          throw new InvalidArgumentException("The where clause has to be an array");
        }

        // the user can pass the conditions as an array or a map
        if(count($condition) === 3 && array_key_exists(0, $condition) && array_key_exists(1, $condition)
          && array_key_exists(2, $condition)){

          // user passed the condition as an array

          $this->orConditionsWithAnd[] = [
            "fieldName" => $condition[0],
            "condition" => trim($condition[1]),
            "value" => $condition[2]
          ];
        } else {

          // user passed the condition as a map

          if(!array_key_exists("fieldName", $condition) || empty($condition["fieldName"])){
            throw new InvalidArgumentException("fieldName is required in where clause");
          }
          if(!array_key_exists("condition", $condition) || empty($condition["condition"])){
            throw new InvalidArgumentException("condition is required in where clause");
          }
          if(!array_key_exists("value", $condition)){
            throw new InvalidArgumentException("value is required in where clause");
          }

          $this->orConditionsWithAnd[] = [
            "fieldName" => $condition["fieldName"],
            "condition" => trim($condition["condition"]),
            "value" => $condition["value"]
          ];

        }
      }

      return $this;

    }

    /**
     * Set the amount of data record to skip.
     * @param int $skip
     * @return $this
     */
    public function skip( $skip = 0 ) {
      if ( $skip === false ) $skip = 0;
      $this->skip = (int) $skip;
      return $this;
    }

    /**
     * Set the amount of data record to limit.
     * @param int $limit
     * @return $this
     */
    public function limit( $limit = 0 ) {
      if ( $limit === false ) $limit = 0;
      $this->limit = (int) $limit;
      return $this;
    }

    /**
     * Set the sort order.
     * @param string $order "asc" or "desc"
     * @param string $orderBy
     * @return $this
     * @throws InvalidOrderException
     */
    public function orderBy( $order, $orderBy = '_id' ) {
      // Validate order.
      $order = strtolower( $order );
      if ( ! in_array( $order, [ 'asc', 'desc' ] ) ) throw new InvalidOrderException( 'Invalid order found, please use "asc" or "desc" only.' );
      $this->orderBy = [
        'order' => $order,
        'field' => $orderBy
      ];
      return $this;
    }

    /**
     * Do a fulltext like search against more than one field.
     * @param string|array $field one fieldName or multiple fieldNames as an array
     * @param string $keyword
     * @return $this
     * @throws EmptyFieldNameException
     */
    public function search( $field, $keyword) {
      if ( empty( $field ) ) throw new EmptyFieldNameException( 'Cant perform search due to no field name was provided' );
      if ( ! empty( $keyword ) ) $this->searchKeyword = [
        'field'   => (array) $field,
        'keyword' => $keyword
      ];
      return $this;
    }

    /**
     * Re-generate the cache for the query.
     * @return $this
     */
    public function makeCache() {
      $this->makeCache = true;
      $this->useCache  = false;
      return $this;
    }

    /**
     * Re-use existing cache of the query, if doesnt exists
     * then would make new cache.
     * @return $this
     */
    public function useCache() {
      $this->useCache  = true;
      $this->makeCache = false;
      return $this;
    }

    /**
     * Delete cache for the current query.
     * @return $this
     */
    public function deleteCache() {
      $this->_deleteCache();
      return $this;
    }

    /**
     * Delete all cache of the current store.
     * @return $this
     */
    public function deleteAllCache() {
      $this->_emptyAllCache();
      return $this;
    }

    /**
     * Keep the active query conditions.
     * @return $this
     */
    public function keepConditions () {
      $this->shouldKeepConditions = true;
      return $this;
    }

  }
  