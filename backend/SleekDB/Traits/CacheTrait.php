<?php

  namespace SleekDB\Traits;

  /**
   * Methods required to perform the cache mechanishm.
   */
  trait CacheTrait {
    
    /**
     * Make cache deletes the old cache if exists then creates a new cache file.
     * returns the data.
     * @return array
     */
    private function reGenerateCache() {
      $token  = $this->getCacheToken();
      $result = $this->findStoreDocuments();
      // Write the cache file.
      file_put_contents( $this->getCachePath( $token ), json_encode( $result ) );
      // Reset cache flags to avoid future queries on the same object of the store.
      $this->resetCacheFlags();
      // Return the data.
      return $result;
    }

    /**
     * Use cache will first check if the cache exists, then re-use it.
     * If cache dosent exists then call makeCache and return the data.
     * @return array
     */
    private function useExistingCache() {
      $token = $this->getCacheToken();
      // Check if cache file exists.
      if ( file_exists( $this->getCachePath( $token ) ) ) {
        // Reset cache flags to avoid future queries on the same object of the store.
        $this->resetCacheFlags();
        // Return data from the found cache file.
        return json_decode( file_get_contents( $this->getCachePath( $token ) ), true );
      } else {
        // Cache file was not found, re-generate the cache and return the data.
        return $this->reGenerateCache();
      }
    }

    /**
     * This method would make a unique token for the current query.
     * We would use this hash token as the id/name of the cache file.
     * @return string
     */
    private function getCacheToken() {
      $query = json_encode( [
        'store' => $this->storePath,
        'limit' => $this->limit,
        'skip' => $this->skip,
        'conditions' => $this->conditions,
        'orConditions' => $this->orConditions,
        'in' => $this->in,
        'notIn' => $this->notIn,
        'order' => $this->orderBy,
        'search' => $this->searchKeyword,
        'fieldsToSelect' => $this->fieldsToSelect,
        'fieldsToExclude' => $this->fieldsToExclude,
        'orConditionsWithAnd' => $this->orConditionsWithAnd,
      ] );
      return md5( $query );
    }

    /**
     * Reset the cache flags so the next database query dosent messedup.
     */
    private function resetCacheFlags() {
      $this->makeCache = false;
      $this->useCache  = false;
    }

    /**
     * Returns the cache directory absolute path for the current store.
     * @param string $token
     * @return string
     */
    private function getCachePath( $token ) {
      return $this->storePath . 'cache/' . $token . '.json';
    }

    /**
     * Delete a single cache file for current query.
     */
    private function _deleteCache() {
      $token = $this->getCacheToken();
      unlink( $this->getCachePath( $token ) );
    }

    /**
     * Delete all cache for current store.
     */
    private function _emptyAllCache() {
      array_map( 'unlink', glob( $this->storePath . "cache/*" ) );
    }

  }
  