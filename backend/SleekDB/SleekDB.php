<?php

  namespace SleekDB;

  use SleekDB\Exceptions\ConditionNotAllowedException;
  use SleekDB\Exceptions\EmptyFieldNameException;
  use SleekDB\Exceptions\EmptyStoreDataException;
  use SleekDB\Exceptions\EmptyStoreNameException;
  use SleekDB\Exceptions\IdNotAllowedException;
  use SleekDB\Exceptions\IndexNotFoundException;
  use SleekDB\Exceptions\InvalidConfigurationException;
  use SleekDB\Exceptions\InvalidDataException;
  use SleekDB\Exceptions\InvalidStoreDataException;
  use SleekDB\Exceptions\IOException;
  use SleekDB\Exceptions\JsonException;

  use SleekDB\Traits\HelperTrait;
  use SleekDB\Traits\CacheTrait;
  use SleekDB\Traits\ConditionTrait;

  // to provide usage without composer, we need to require the files
  require_once __DIR__ . "/Exceptions/ConditionNotAllowedException.php";
  require_once __DIR__ . "/Exceptions/EmptyFieldNameException.php";
  require_once __DIR__ . "/Exceptions/EmptyStoreNameException.php";
  require_once __DIR__ . "/Exceptions/IdNotAllowedException.php";
  require_once __DIR__ . "/Exceptions/IndexNotFoundException.php";
  require_once __DIR__ . "/Exceptions/InvalidConfigurationException.php";
  require_once __DIR__ . "/Exceptions/InvalidDataException.php";
  require_once __DIR__ . "/Exceptions/InvalidStoreDataException.php";
  require_once __DIR__ . "/Exceptions/IOException.php";
  require_once __DIR__ . "/Exceptions/JsonException.php";

  require_once __DIR__ . "/Traits/HelperTrait.php";
  require_once __DIR__ . "/Traits/CacheTrait.php";
  require_once __DIR__ . "/Traits/ConditionTrait.php";


  class SleekDB{

    use HelperTrait;
    use ConditionTrait;
    use CacheTrait;

    private $root = __DIR__;

    private $storeName;

    private $makeCache;
    private $useCache;

    private $deleteCacheOnCreate;

    private $storePath;

    private $dataDirectory;
    private $shouldKeepConditions;
    private $results;
    private $limit;
    private $skip;
    private $conditions;
    private $orConditions;
    private $in;
    private $notIn;
    private $orderBy;
    private $searchKeyword;

    private $fieldsToSelect = [];
    private $fieldsToExclude = [];
    private $orConditionsWithAnd = [];


    /**
     * SleekDB constructor.
     * Initialize the database.
     * @param string $dataDir
     * @param array $configurations
     * @throws IOException
     * @throws InvalidConfigurationException
     */
    function __construct( $dataDir = '', $configurations = [] ) {
      // Add data dir.
      $configurations[ 'data_directory' ] = $dataDir;
      // Initialize SleekDB
      $this->init( $configurations );
    }

    /**
     * Initialize the store.
     * @param string $storeName
     * @param string $dataDir
     * @param array $options
     * @return SleekDB
     * @throws EmptyStoreNameException
     * @throws IOException
     * @throws InvalidConfigurationException
     */
    public static function store( $storeName, $dataDir, $options = [] ) {
      if ( empty( $storeName ) ) throw new EmptyStoreNameException( 'Store name was not valid' );
      $_dbInstance = new SleekDB( $dataDir, $options );
      $_dbInstance->storeName = $storeName;
      // Boot store.
      $_dbInstance->bootStore();
      // Initialize variables for the store.
      $_dbInstance->initVariables();
      return $_dbInstance;
    }

    /**
     * Read store objects.
     * @return array
     * @throws ConditionNotAllowedException
     * @throws IndexNotFoundException
     * @throws EmptyFieldNameException
     * @throws InvalidDataException
     */
    public function fetch() {
      $fetchedData = null;
      // Check if data should be provided from the cache.
      if ( $this->makeCache === true ) {
        $fetchedData = $this->reGenerateCache(); // Re-generate cache.
      }
      else if ( $this->useCache === true ) {
        $fetchedData = $this->useExistingCache(); // Use existing cache else re-generate.
      }
      else {
        $fetchedData = $this->findStoreDocuments(); // Returns data without looking for cached data.
      }
      $this->initVariables(); // Reset state.
      return $fetchedData;
    }

    /**
     * Creates a new object in the store.
     * The object is a plaintext JSON document.
     * @param array $storeData
     * @return array
     * @throws EmptyStoreDataException
     * @throws IOException
     * @throws InvalidStoreDataException
     * @throws JsonException
     * @throws IdNotAllowedException
     */
    public function insert( $storeData ) {
      // Handle invalid data
      if ( empty( $storeData ) ) throw new EmptyStoreDataException( 'No data found to store' );
      // Make sure that the data is an array
      if ( ! is_array( $storeData ) ) throw new InvalidStoreDataException( 'Storable data must an array' );
      $storeData = $this->writeInStore( $storeData );
      // Check do we need to wipe the cache for this store.
      if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
      return $storeData;
    }

    /**
     * Creates multiple objects in the store.
     * @param $storeData
     * @return array
     * @throws EmptyStoreDataException
     * @throws IOException
     * @throws InvalidStoreDataException
     * @throws JsonException
     * @throws IdNotAllowedException
     */
    public function insertMany( $storeData ) {
      // Handle invalid data
      if ( empty( $storeData ) ) throw new EmptyStoreDataException( 'No data found to insert in the store' );
      // Make sure that the data is an array
      if ( ! is_array( $storeData ) ) throw new InvalidStoreDataException( 'Data must be an array in order to insert in the store' );
      // All results.
      $results = [];
      foreach ( $storeData as $key => $node ) {
        $results[] = $this->writeInStore( $node );
      }
      // Check do we need to wipe the cache for this store.
      if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
      return $results;
    }

    /**
     * @param $updatable
     * @return bool
     * @throws IndexNotFoundException
     * @throws ConditionNotAllowedException
     * @throws EmptyFieldNameException
     * @throws InvalidDataException
     */
    public function update($updatable ) {
      // Find all store objects.
      $storeObjects = $this->findStoreDocuments();
      // If no store object found then return an empty array.
      if ( empty( $storeObjects ) ) {
        $this->initVariables(); // Reset state.
        return false;
      }
      foreach ( $storeObjects as $data ) {
        foreach ($updatable as $key => $value ) {
          // Do not update the _id reserved index of a store.
          if( $key != '_id' ) {
            $data[ $key ] = $value;
          }
        }
        $storePath = $this->storePath . 'data/' . $data[ '_id' ] . '.json';
        if ( file_exists( $storePath ) ) {
          // Wait until it's unlocked, then update data.
          file_put_contents( $storePath, json_encode( $data ), LOCK_EX );
        }
      }
      // Check do we need to wipe the cache for this store.
      if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
      $this->initVariables(); // Reset state.
      return true;
    }

    /**
     * Deletes matched store objects.
     * @return bool
     * @throws IOException
     * @throws IndexNotFoundException
     * @throws ConditionNotAllowedException
     * @throws EmptyFieldNameException
     * @throws InvalidDataException
     */
    public function delete() {
      // Find all store objects.
      $storeObjects = $this->findStoreDocuments();
      if ( ! empty( $storeObjects ) ) {
        foreach ( $storeObjects as $data ) {
          if ( ! unlink( $this->storePath . 'data/' . $data[ '_id' ] . '.json' ) ) {
            $this->initVariables(); // Reset state.
            throw new IOException(
              'Unable to delete storage file! 
              Location: "'.$this->storePath . 'data/' . $data[ '_id' ] . '.json'.'"' 
            );
          }
        }
        // Check do we need to wipe the cache for this store.
        if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
        $this->initVariables(); // Reset state.
        return true;
      } else {
        // Nothing found to delete
        $this->initVariables(); // Reset state.
        return true;
        // throw new \Exception( 'Invalid store object found, nothing to delete.' );
      }
    }

    /**
     * Deletes a store and wipes all the data and cache it contains.
     * @return bool
     */
    public function deleteStore() {
      $it = new \RecursiveDirectoryIterator( $this->storePath, \RecursiveDirectoryIterator::SKIP_DOTS );
      $files = new \RecursiveIteratorIterator( $it, \RecursiveIteratorIterator::CHILD_FIRST );
      foreach( $files as $file ) {
        if ( $file->isDir() ) rmdir( $file->getRealPath() );
        else unlink( $file->getRealPath() );
      }
      return rmdir( $this->storePath );
    }

  }