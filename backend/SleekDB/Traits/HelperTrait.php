<?php

  namespace SleekDB\Traits;

  use SleekDB\Exceptions\ConditionNotAllowedException;
  use SleekDB\Exceptions\IdNotAllowedException;
  use SleekDB\Exceptions\IOException;
  use SleekDB\Exceptions\InvalidConfigurationException;
  use SleekDB\Exceptions\EmptyStoreNameException;
  use SleekDB\Exceptions\IndexNotFoundException;
  use SleekDB\Exceptions\JsonException;
  use SleekDB\Exceptions\EmptyFieldNameException;
  use SleekDB\Exceptions\InvalidDataException;


  /**
   * Collections of method that helps to manage the data.
   * All methods in this trait should be private.
   *
   */
  trait HelperTrait {

    /**
     * @param array $conf
     * @throws IOException
     * @throws InvalidConfigurationException
     */
    private function init( $conf ) {
      // Check for valid configurations.
      if( empty( $conf ) OR !is_array( $conf ) ) throw new InvalidConfigurationException( 'Invalid configurations was found.' );
      // Check if the 'data_directory' was provided.
      if ( !isset( $conf[ 'data_directory' ] ) ) throw new InvalidConfigurationException( '"data_directory" was not provided in the configurations.' );
      // Check if data_directory is empty.
      if ( empty( $conf[ 'data_directory' ] ) ) throw new InvalidConfigurationException( '"data_directory" cant be empty in the configurations.' );
      // Prepare the data directory.
      $dataDir = trim( $conf[ 'data_directory' ] );
      // Handle directory path ending.
      if ( substr( $dataDir, -1 ) !== '/' ) $dataDir = $dataDir . '/';
      // Check if the data_directory exists.
      if ( !file_exists( $dataDir ) ) {
        // The directory was not found, create one.
        if ( !mkdir( $dataDir, 0777, true ) ) throw new IOException( 'Unable to create the data directory at ' . $dataDir );
      }
      // Check if PHP has write permission in that directory.
      if ( !is_writable( $dataDir ) ) throw new IOException( 'Data directory is not writable at "' . $dataDir . '." Please change data directory permission.' );
      // Finally check if the directory is readable by PHP.
      if ( !is_readable( $dataDir ) ) throw new IOException( 'Data directory is not readable at "' . $dataDir . '." Please change data directory permission.' );
      // Set the data directory.
      $this->dataDirectory = $dataDir;
      // Set auto cache settings.
      $autoCache = true;
      if ( isset( $conf[ 'auto_cache' ] ) ) $autoCache = $conf[ 'auto_cache' ];
      $this->initAutoCache( $autoCache );
      // Set timeout.
      $timeout = 120;
      if ( isset( $conf[ 'timeout' ] ) ) {
        if ( !empty( $conf[ 'timeout' ] ) ) $timeout = (int) $conf[ 'timeout' ];
      }
      set_time_limit( $timeout );
      // Control when to keep or delete the active query conditions. Delete conditions by default.
      $this->shouldKeepConditions = false; 
    } // End of init()

    /**
     * Init data that SleekDB required to operate.
     */
    private function initVariables() {
      if(!$this->shouldKeepConditions) {
        // Set empty results
        $this->results = [];
        // Set a default limit
        $this->limit = 0;
        // Set a default skip
        $this->skip = 0;
        // Set default conditions
        $this->conditions = [];
        // Or conditions
        $this->orConditions = [];
        // In clause conditions
        $this->in = [];
        // notIn clause conditions 
        $this->notIn = [];
        // Set default group by value
        $this->orderBy = [
          'order' => false,
          'field' => '_id'
        ];
        // Set the default search keyword as an empty string.
        $this->searchKeyword = '';
        // Disable make cache by default.
        $this->makeCache = false;
        // Control when to keep or delete the active query conditions. Delete conditions by default.
        $this->shouldKeepConditions = false;
        // specific fields to select
        $this->fieldsToSelect = [];
        $this->fieldsToExclude = [];

        $this->orConditionsWithAnd = [];
      }
    } // End of initVariables()

    /**
     * Initialize the auto cache settings.
     * @param bool $autoCache
     */
    private function initAutoCache ( $autoCache = true ) {
      // Decide the cache status.
      if ( $autoCache === true ) {
        $this->useCache = true;
        // A flag that is used to check if cache should be empty
        // while create a new object in a store.
        $this->deleteCacheOnCreate = true;
      } else {
        $this->useCache = false;
        // A flag that is used to check if cache should be empty 
        // while create a new object in a store.
        $this->deleteCacheOnCreate = false;
      }
    }

    /**
     * Method to boot a store.
     * @throws EmptyStoreNameException
     * @throws IOException
     */
    private function bootStore() {
      $store = trim( $this->storeName );
      // Validate the store name.
      if ( !$store || empty( $store ) ) throw new EmptyStoreNameException( 'Invalid store name was found' );
      // Prepare store name.
      if ( substr( $store, -1 ) !== '/' ) $store = $store . '/';
      // Store directory path.
      $this->storePath = $this->dataDirectory . $store;
      // Check if the store exists.
      if ( !file_exists( $this->storePath ) ) {
        // The directory was not found, create one with cache directory.
        if ( !mkdir( $this->storePath, 0777, true ) ) throw new IOException( 'Unable to create the store path at ' . $this->storePath );
        // Create the cache directory.
        if ( !mkdir( $this->storePath . 'cache', 0777, true ) ) throw new IOException( 'Unable to create the store\'s cache directory at ' . $this->storePath . 'cache' );
        // Create the data directory.
        if ( !mkdir( $this->storePath . 'data', 0777, true ) ) throw new IOException( 'Unable to create the store\'s data directory at ' . $this->storePath . 'data' );
        // Create the store counter file.
        if ( !file_put_contents( $this->storePath . '_cnt.sdb', '0' ) ) throw new IOException( 'Unable to create the system counter for the store! Please check write permission' );
      }
      // Check if PHP has write permission in that directory.
      if ( !is_writable( $this->storePath ) ) throw new IOException( 'Store path is not writable at "' . $this->storePath . '." Please change store path permission.' );
      // Finally check if the directory is readable by PHP.
      if ( !is_readable( $this->storePath ) ) throw new IOException( 'Store path is not readable at "' . $this->storePath . '." Please change store path permission.' );
    }

    // Returns a new and unique store object ID, by calling this method it would also
    // increment the ID system-wide only for the store.
    private function getStoreId() {
      $counter = 1; // default (first) id
      $counterPath = $this->storePath . '_cnt.sdb';
      if ( file_exists( $counterPath ) ) {
        $fp = fopen($counterPath, 'r+');
        for($retries = 10; $retries > 0; $retries--) {
          flock($fp, LOCK_UN);
          if (flock($fp, LOCK_EX) === false) {
            sleep(1);
          } else {
            $counter = (int) fgets($fp);
            $counter++;
            rewind($fp);
            fwrite($fp, (string) $counter);
            break;
          }
        }
        flock($fp, LOCK_UN);
        fclose($fp);
      }
      return $counter;
    }

    /**
     * Return the last created store object ID.
     * @return int
     */
    private function getLastStoreId() {
      $counterPath = $this->storePath . '_cnt.sdb';
      if ( file_exists( $counterPath ) ) {
        return (int) file_get_contents( $counterPath );
      }
      return 0;
    }

    /**
     * Get a store by its system id. "_id"
     * @param $id
     * @return array|mixed
     */
    private function getStoreDocumentById( $id ) {
      $store = $this->storePath . 'data/' . $id . '.json';
      if ( file_exists( $store ) ) {
        $data = json_decode( file_get_contents( $store ), true );
        if ( $data !== false ) return $data;
      }
      return [];
    }

    /**
     * @param string $file
     * @return mixed
     */
    private function getDocumentByPath ( $file ) {
      return @json_decode( @file_get_contents( $file ), true );
    }

    /**
     * @param string $condition
     * @param mixed $fieldValue value of current field
     * @param mixed $value value to check
     * @throws ConditionNotAllowedException
     * @return bool
     */
    private function verifyWhereConditions ( $condition, $fieldValue, $value ) {
      // Check the type of rule.
      if ( $condition === '=' ) {
        // Check equal.
        return ( $fieldValue == $value );
      } else if ( $condition === '!=' ) {
        // Check not equal.
        return ( $fieldValue != $value );
      } else if ( $condition === '>' ) {
        // Check greater than.
        return ( $fieldValue > $value );
      } else if ( $condition === '>=' ) {
        // Check greater equal.
        return ( $fieldValue >= $value );
      } else if ( $condition === '<' ) {
        // Check less than.
        return ( $fieldValue < $value );
      } else if ( $condition === '<=' ) {
        // Check less equal.
        return ( $fieldValue <= $value );
      } else if (strtolower($condition) === 'like'){
          $value = str_replace('%', '(.)*', $value);
          $pattern = "/^".$value."$/i";
          return (preg_match($pattern, $fieldValue) === 1);
      }
      throw new ConditionNotAllowedException('condition '.$condition.' is not allowed');
    }

    /**
     * @return array
     * @throws IndexNotFoundException
     * @throws ConditionNotAllowedException
     * @throws EmptyFieldNameException
     * @throws InvalidDataException
     */
    private function findStoreDocuments() {
      $found = [];
      // Start collecting and filtering data.
      $storeDataPath = $this->storePath . 'data/';
      if( $handle = opendir($storeDataPath) ) {
        while ( false !== ($entry = readdir($handle)) ) {
          if ($entry != "." && $entry != "..") {
            $file = $storeDataPath . $entry;
            $data = $this->getDocumentByPath( $file );
            $document = false;
            if ( ! empty( $data ) ) {
              // Filter data found.
              if ( empty( $this->conditions ) ) {
                // Append all data of this store.
                $document = $data;
              } else {
                // Append only passed data from this store.
                $storePassed = true;
                // Iterate each conditions.
                foreach ( $this->conditions as $condition ) {
                  if ( $storePassed === true ) {
                    // Check for valid data from data source.
                    $validData = true;
                    $fieldValue = '';
                    try {
                      $fieldValue = $this->getNestedProperty( $condition[ 'fieldName' ], $data );
                    } catch( \Exception $e ) {
                      $validData   = false;
                      $storePassed = false;
                    }
                    if( $validData === true ) {
                      $storePassed = $this->verifyWhereConditions( $condition[ 'condition' ], $fieldValue, $condition[ 'value' ] );
                    }
                  }
                }
                // Check if current store is updatable or not.
                if ( $storePassed === true ) {
                  // Append data to the found array.
                  $document = $data;
                } else {
                  // Check if a or-where condition will allow this document.
                  foreach ( $this->orConditions as $condition ) {
                    // Check for valid data from data source.
                    $validData = true;
                    $fieldValue = '';
                    try {
                      $fieldValue = $this->getNestedProperty( $condition[ 'fieldName' ], $data );
                    } catch( \Exception $e ) {
                      $validData   = false;
                      $storePassed = false;
                    }
                    if( $validData === true ) {
                      $storePassed = $this->verifyWhereConditions( $condition[ 'condition' ], $fieldValue, $condition[ 'value' ] );
                      if( $storePassed ) {
                        // Append data to the found array.
                        $document = $data;
                        break;
                      }
                    }
                  }
                }
                // Check if current store is updatable or not.
                if ( $storePassed === true ) {
                  // Append data to the found array.
                  $document = $data;
                } else if(count($this->orConditionsWithAnd) > 0) {
                  // Check if a all conditions will allow this document.
                  $allConditionMatched = true;
                  foreach ( $this->orConditionsWithAnd as $condition ) {
                    // Check for valid data from data source.
                    $validData = true;
                    $fieldValue = '';
                    try {
                      $fieldValue = $this->getNestedProperty( $condition[ 'fieldName' ], $data );
                    } catch( \Exception $e ) {
                      $validData   = false;
                    }
                    if( $validData === true ) {
                      $storePassed = $this->verifyWhereConditions( $condition[ 'condition' ], $fieldValue, $condition[ 'value' ] );
                      if($storePassed) continue;
                    }

                    // if data was invalid or store did not pass
                    $allConditionMatched = false;
                    break;
                  }
                  if( $allConditionMatched === true ) {
                    // Append data to the found array.
                    $document = $data;
                  }
                }
              } // Completed condition checks.
    
              // IN clause.
              if( $document && !empty($this->in) ) {
                foreach ( $this->in as $inClause) {
                  $validData = true;
                  $fieldValue = '';
                  try {
                    $fieldValue = $this->getNestedProperty( $inClause[ 'fieldName' ], $data );
                  } catch( \Exception $e ) {
                    $validData = false;
                    $document = false;
                    break;
                  }
                  if( $validData === true ) {
                    if( !in_array( $fieldValue, $inClause[ 'value' ] ) ) {
                      $document = false;
                      break;
                    }
                  }
                }
              }
    
              // notIn clause.
              if ( $document && !empty($this->notIn) ) {
                foreach ( $this->notIn as $notInClause) {
                  $validData = true;
                  $fieldValue = '';
                  try {
                    $fieldValue = $this->getNestedProperty( $notInClause[ 'fieldName' ], $data );
                  } catch( \Exception $e ) {
                    $validData = false;
                    break;
                  }
                  if( $validData === true ) {
                    if( in_array( $fieldValue, $notInClause[ 'value' ] ) ) {
                      $document = false;
                      break;
                    }
                  }
                }
              }
    
              // Check if there is any document appendable.
              if( $document ) {
                $found[] = $document;
              }
            }
          }
        }
        closedir( $handle );
      }

      if ( count( $found ) > 0 ) {
        // Check do we need to sort the data.
        if ( $this->orderBy[ 'order' ] !== false ) {
          // Start sorting on all data.
          $found = $this->sortArray( $this->orderBy[ 'field' ], $found, $this->orderBy[ 'order' ] );
        }
        // If there was text search then we would also sort the result by search ranking.
        if ( ! empty( $this->searchKeyword ) ) {
          $found = $this->performSearch( $found );
        }
        // Skip data
        if ( $this->skip > 0 ) $found = array_slice( $found, $this->skip );
        // Limit data.
        if ( $this->limit > 0 ) $found = array_slice( $found, 0, $this->limit );
      }

      if(count($found) > 0){
        if(count($this->fieldsToSelect) > 0){
          $found = $this->applyFieldsToSelect($found);
        }
        if(count($this->fieldsToExclude) > 0){
          $found = $this->applyFieldsToExclude($found);
        }
      }

      return $found;
    }

    /**
     * @param array $found
     * @return array
     */
    private function applyFieldsToSelect($found){
      if(!(count($found) > 0) || !(count($this->fieldsToSelect) > 0)){
        return $found;
      }
      foreach ($found as $key => $item){
        $newItem = [];
        $newItem['_id'] = $item['_id'];
        foreach ($this->fieldsToSelect as $fieldToSelect){
          if(array_key_exists($fieldToSelect, $item)){
            $newItem[$fieldToSelect] = $item[$fieldToSelect];
          }
        }
        $found[$key] = $newItem;
      }
      return $found;
    }

    /**
     * @param array $found
     * @return array
     */
    private function applyFieldsToExclude($found){
      if(!(count($found) > 0) || !(count($this->fieldsToExclude) > 0)){
        return $found;
      }
      foreach ($found as $key => $item){
        foreach ($this->fieldsToExclude as $fieldToExclude){
          if(array_key_exists($fieldToExclude, $item)){
            unset($item[$fieldToExclude]);
          }
        }
        $found[$key] = $item;
      }
      return $found;
    }


    /**
     * Writes an object in a store.
     * @param $storeData
     * @return array
     * @throws IOException
     * @throws JsonException
     * @throws IdNotAllowedException
     */
    private function writeInStore( $storeData ) {
      // Cast to array
      $storeData = (array) $storeData;
      // Check if it has _id key
      if ( isset( $storeData[ '_id' ] ) ) throw new IdNotAllowedException( 'The _id index is reserved by SleekDB, please delete the _id key and try again' );
      $id = $this->getStoreId();
      // Add the system ID with the store data array.
      $storeData[ '_id' ] = $id;
      // Prepare storable data
      $storableJSON = json_encode( $storeData );
      if ( $storableJSON === false ) throw new JsonException( 'Unable to encode the data array, 
        please provide a valid PHP associative array' );
      // Define the store path
      $storePath = $this->storePath . 'data/' . $id . '.json';
      if ( ! file_put_contents( $storePath, $storableJSON ) ) {
        throw new IOException( "Unable to write the object file! Please check if PHP has write permission." );
      }
      return $storeData;
    }

    /**
     * Sort store objects.
     * @param $field
     * @param $data
     * @param string $order
     * @return array
     * @throws IndexNotFoundException
     * @throws EmptyFieldNameException
     * @throws InvalidDataException
     */
    private function sortArray( $field, $data, $order = 'ASC' ) {
      $dryData = [];
      // Check if data is an array.
      if( is_array( $data ) ) {
        // Get value of the target field.
        foreach ( $data as $value ) {
          $dryData[] = $this->getNestedProperty( $field, $value );
        }
      }
      // Descide the order direction.
      if ( strtolower( $order ) === 'asc' ) asort( $dryData );
      else if ( strtolower( $order ) === 'desc' ) arsort( $dryData );
      // Re arrange the array.
      $finalArray = [];
      foreach ( $dryData as $key => $value) {
        $finalArray[] = $data[ $key ];
      }
      return $finalArray;
    }

    /**
     * Get nested properties of a store object.
     * @param string $fieldName
     * @param array $data
     * @return array|mixed
     * @throws EmptyFieldNameException
     * @throws IndexNotFoundException
     * @throws InvalidDataException
     */
    private function getNestedProperty($fieldName, $data ) {
      if( !is_array( $data ) ) throw new InvalidDataException('data has to be an array');
      if(empty( $fieldName )) throw new EmptyFieldNameException('fieldName is not allowed to be empty');

      // Dive deep step by step.
      foreach(explode( '.', $fieldName ) as $i ) {
        // If the field do not exists then insert an empty string.
        if ( ! isset( $data[ $i ] ) ) {
          $data = '';
          throw new IndexNotFoundException( '"'.$i.'" index was not found in the provided data array' );
        }
        // The index is valid, collect the data.
        $data = $data[ $i ];
      }
      return $data;
    }

    /**
     * Do a search in store objects. This is like a doing a full-text search.
     * @param array $data
     * @return array
     */
    private function performSearch($data = [] ) {
      if ( empty( $data ) ) return $data;
      $nodesRank = [];
      // Looping on each store data.
      foreach ($data as $key => $value) {
        // Looping on each field name of search-able fields.
        foreach ($this->searchKeyword[ 'field' ] as $field) {
          try {
            $nodeValue = $this->getNestedProperty( $field, $value );
            // The searchable field was found, do comparison against search keyword.
            similar_text( strtolower($nodeValue), strtolower($this->searchKeyword['keyword']), $perc );
            if ( $perc > 50 ) {
              // Check if current store object already has a value, if so then add the new value.
              if ( isset( $nodesRank[ $key ] ) ) $nodesRank[ $key ] += $perc;
              else $nodesRank[ $key ] = $perc;
            }
          } catch ( \Exception $e ) {
            continue;
          }
        }
      }
      if ( empty( $nodesRank ) ) {
        // No matched store was found against the search keyword.
        return [];
      }
      // Sort nodes in descending order by the rank.
      arsort( $nodesRank );
      // Map original nodes by the rank.
      $nodes = [];
      foreach ( $nodesRank as $key => $value ) {
        $nodes[] = $data[ $key ];
      }
      return $nodes;
    }
    
  }
  