<?php
declare(strict_types=1);
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Cache;

use Cake\Core\InstanceConfigTrait;
use Psr\SimpleCache\CacheInterface;

/**
 * Storage engine for CakePHP caching
 *
 * @mixin \Cake\Core\InstanceConfigTrait
 */
abstract class CacheEngine implements CacheInterface, CacheEngineInterface
{
    use InstanceConfigTrait;

    /**
     * The default cache configuration is overridden in most cache adapters. These are
     * the keys that are common to all adapters. If overridden, this property is not used.
     *
     * - `duration` Specify how long items in this cache configuration last.
     * - `groups` List of groups or 'tags' associated to every key stored in this config.
     *    handy for deleting a complete group from cache.
     * - `prefix` Prefix appended to all entries. Good for when you need to share a keyspace
     *    with either another cache config or another application.
     * - `warnOnWriteFailures` Some engines, such as ApcuEngine, may raise warnings on
     *    write failures.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'duration' => 3600,
        'groups' => [],
        'prefix' => 'cake_',
        'warnOnWriteFailures' => true,
    ];

    /**
     * Contains the compiled string with all group
     * prefixes to be prepended to every key in this cache engine
     *
     * @var string
     */
    protected $_groupPrefix;

    /**
     * Initialize the cache engine
     *
     * Called automatically by the cache frontend. Merge the runtime config with the defaults
     * before use.
     *
     * @param array $config Associative array of parameters for the engine
     * @return bool True if the engine has been successfully initialized, false if not
     */
    public function init(array $config = []): bool
    {
        $this->setConfig($config);

        if (!empty($this->_config['groups'])) {
            sort($this->_config['groups']);
            $this->_groupPrefix = str_repeat('%s_', count($this->_config['groups']));
        }
        if (!is_numeric($this->_config['duration'])) {
            $this->_config['duration'] = strtotime($this->_config['duration']) - time();
        }

        return true;
    }

    /**
     * Ensure the validity of the given cache key.
     *
     * @param string $key Key to check.
     * @return void
     * @throws \Cake\Cache\InvalidArgumentException When the key is not valid.
     */
    protected function ensureValidKey($key)
    {
        if (!is_string($key) || strlen($key) === 0) {
            throw new InvalidArgumentException('A cache key must be a non-empty string.');
        }
    }

    /**
     * Ensure the validity of the given cache keys.
     *
     * @param mixed $keys The keys to check.
     * @return void
     * @throws \Cake\Cache\InvalidArgumentException When the keys are not valid.
     */
    protected function ensureValidKeys($keys)
    {
        if (!is_array($keys) && !($keys instanceof \Traversable)) {
            throw new InvalidArgumentException('A cache key set must be either an array or a Traversable.');
        }

        foreach ($keys as $key) {
            $this->ensureValidKey($key);
        }
    }

    /**
     * Write data for many keys into cache
     *
     * @param array $data An array of data to be stored in the cache
     * @return array of bools for each key provided, true if the data was successfully cached, false on failure
     * @deprecated To be removed soon
     */
    public function writeMany(array $data): array
    {
        $return = [];
        foreach ($data as $key => $value) {
            $return[$key] = $this->set($key, $value);
        }

        return $return;
    }

    /**
     * Read multiple keys from the cache
     *
     * @param array $keys An array of identifiers for the data
     * @return array For each cache key (given as the array key) the cache data associated or false if the data doesn't
     * exist, has expired, or if there was an error fetching it
     * @deprecated To be removed soon
     */
    public function readMany(array $keys): array
    {
        $return = [];
        foreach ($keys as $key) {
            $return[$key] = $this->get($key);
        }

        return $return;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     * @return array A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     * @throws \Cake\Cache\InvalidArgumentException If $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null): array
    {
        $this->ensureValidKeys($keys);

        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *   the driver supports TTL then the library may set a default value
     *   for it or let the driver take care of that.
     * @return bool True on success and false on failure.
     * @throws \Cake\Cache\InvalidArgumentException If $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $this->ensureValidKeys(array_keys($values));

        if ($ttl !== null) {
            $restore = $this->getConfig('duration');
            $this->setConfig('duration', $ttl);
        }
        try {
            $return = [];
            foreach ($values as $key => $value) {
                $success = $this->set($key, $value);
                if ($success === false) {
                    return false;
                }
            }

            return true;
        } finally {
            if (isset($restore)) {
                $this->setConfig('duration', $restore);
            }
        }

        return false;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     * @return bool True if the items were successfully removed. False if there was an error.
     * @throws \Cake\Cache\InvalidArgumentException If $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys): bool
    {
        $this->ensureValidKeys($keys);

        foreach ($keys as $key) {
            $result = $this->delete($key);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     * @return bool
     * @throws \Cake\Cache\InvalidArgumentException If the $key string is not a legal value.
     */
    public function has($key)
    {
        return $this->get($key) !== null;
    }

    /**
     * Fetches the value for a given key from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     * @throws \Cake\Cache\InvalidArgumentException If the $key string is not a legal value.
     */
    abstract public function get($key, $default = null);

    /**
     * Persists data in the cache, uniquely referenced by the given key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *   the driver supports TTL then the library may set a default value
     *   for it or let the driver take care of that.
     * @return bool True on success and false on failure.
     * @throws \Cake\Cache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function set($key, $value, $ttl = null);

    /**
     * Increment a number under the key and return incremented value
     *
     * @param string $key Identifier for the data
     * @param int $offset How much to add
     * @return bool|int New incremented value, false otherwise
     */
    abstract public function increment(string $key, int $offset = 1);

    /**
     * Decrement a number under the key and return decremented value
     *
     * @param string $key Identifier for the data
     * @param int $offset How much to subtract
     * @return bool|int New incremented value, false otherwise
     */
    abstract public function decrement(string $key, int $offset = 1);

    /**
     * Delete a key from the cache
     *
     * @param string $key Identifier for the data
     * @return bool True if the value was successfully deleted, false if it didn't exist or couldn't be removed
     */
    abstract public function delete($key);

    /**
     * Delete all keys from the cache
     *
     * @return bool True if the cache was successfully cleared, false otherwise
     */
    abstract public function clear();

    /**
     * Deletes keys from the cache
     *
     * @param array $keys An array of identifiers for the data
     * @return array For each provided cache key (given back as the array key) true if the value was successfully deleted,
     * false if it didn't exist or couldn't be removed
     * @deprecated To be removed soon
     */
    public function deleteMany(array $keys): array
    {
        $return = [];
        foreach ($keys as $key) {
            $return[$key] = $this->delete($key);
        }

        return $return;
    }

    /**
     * Add a key to the cache if it does not already exist.
     *
     * Defaults to a non-atomic implementation. Subclasses should
     * prefer atomic implementations.
     *
     * @param string $key Identifier for the data.
     * @param mixed $value Data to be cached.
     * @return bool True if the data was successfully cached, false on failure.
     */
    public function add(string $key, $value): bool
    {
        $cachedValue = $this->get($key);
        if ($cachedValue === null) {
            return $this->set($key, $value);
        }

        return false;
    }

    /**
     * Clears all values belonging to a group. Is up to the implementing engine
     * to decide whether actually delete the keys or just simulate it to achieve
     * the same result.
     *
     * @param string $group name of the group to be cleared
     * @return bool
     */
    public function clearGroup(string $group): bool
    {
        return false;
    }

    /**
     * Does whatever initialization for each group is required
     * and returns the `group value` for each of them, this is
     * the token representing each group in the cache key
     *
     * @return array
     */
    public function groups(): array
    {
        return $this->_config['groups'];
    }

    /**
     * Generates a safe key for use with cache engine storage engines.
     *
     * @param string $key the key passed over
     * @return bool|string string key or false
     */
    public function key(string $key)
    {
        if (!$key) {
            return false;
        }

        $prefix = '';
        if ($this->_groupPrefix) {
            $prefix = md5(implode('_', $this->groups()));
        }

        $key = preg_replace(
            '/[\s]+/',
            '_',
            strtolower(trim(str_replace([DIRECTORY_SEPARATOR, '/', '.'], '_', (string)$key)))
        );

        return $prefix . $key;
    }

    /**
     * Generates a safe key, taking account of the configured key prefix
     *
     * @param string $key the key passed over
     * @return mixed string $key or false
     * @throws \Cake\Cache\InvalidArgumentException If key's value is empty
     */
    protected function _key(string $key)
    {
        $key = $this->key($key);
        if ($key === false) {
            throw new InvalidArgumentException('An empty value is not valid as a cache key');
        }

        return $this->_config['prefix'] . $key;
    }

    /**
     * Cache Engines may trigger warnings if they encounter failures during operation,
     * if option warnOnWriteFailures is set to true.
     *
     * @param string $message The warning message.
     * @return void
     */
    protected function warning(string $message): void
    {
        if ($this->getConfig('warnOnWriteFailures') !== true) {
            return;
        }

        triggerWarning($message);
    }
}
