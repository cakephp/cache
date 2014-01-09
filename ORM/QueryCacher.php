<?php
/**
 * PHP Version 5.4
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 3.0.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\ORM;

use Cake\Cache\Cache;
use Cake\Cache\CacheEngine;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use RuntimeException;

/**
 * Handles caching queries and loading results from the cache.
 *
 * Used by Cake\ORM\Query internally.
 *
 * @see Cake\ORM\Query::cache() for the public interface.
 */
class QueryCacher {

/**
 * Constructor.
 *
 * @param string|Closure $key
 * @param string|CacheEngine $config
 * @throws RuntimeException
 */
	public function __construct($key, $config) {
		if (!is_string($key) && !is_callable($key)) {
			throw new RuntimeException('Cache keys must be strings or callables.');
		}
		$this->_key = $key;

		if (!is_string($config) && !($config instanceof CacheEngine)) {
			throw new RuntimeException('Cache configs must be strings or CacheEngine instances.');
		}
		$this->_config = $config;
	}

/**
 * Load the cached results from the cache or run the query.
 *
 * @param Query $query The query the cache read is for.
 * @return ResultSet|null Either the cached results or null.
 */
	public function fetch(Query $query) {
		$key = $this->_resolveKey($query);
		$storage = $this->_resolveCacher();
		$result = $storage->read($key);
		if (empty($result)) {
			return null;
		}
		return $result;
	}

/**
 * Store the result set into the cache.
 *
 * @param Query $query The query the cache read is for.
 * @param ResultSet The result set to store.
 * @return void
 */
	public function store(Query $query, ResultSet $results) {
		$key = $this->_resolveKey($query);
		$storage = $this->_resolveCacher();
		return $storage->write($key, $results);
	}

/**
 * Get/generate the cache key.
 *
 * @param Query $query
 * @return string
 * @throws RuntimeException
 */
	protected function _resolveKey($query) {
		if (is_string($this->_key)) {
			return $this->_key;
		}
		$func = $this->_key;
		$key = $func($query);
		if (!is_string($key)) {
			$msg = sprintf('Cache key functions must return a string. Got %s.', var_export($key, true));
			throw new RuntimeException($msg);
		}
		return $key;
	}

/**
 * Get the cache engine.
 *
 * @return Cake\Cache\CacheEngine
 */
	protected function _resolveCacher() {
		if (is_string($this->_config)) {
			return Cache::engine($this->_config);
		}
		return $this->_config;
	}

}
