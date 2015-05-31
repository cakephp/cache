<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Auth\Storage;

use Cake\Core\InstanceConfigTrait;
use Cake\Network\Request;

/**
 * Session based persistent storage for authenticated user record.
 */
class SessionStorage implements StorageInterface
{

    use InstanceConfigTrait;

    /**
     * User record.
     *
     * Stores user record array if fetched from session or false if session
     * does not have user record.
     *
     * @var array|bool
     */
    protected $_user;

    /**
     * Session object.
     *
     * @var \Cake\Network\Session
     */
    protected $_session;

    /**
     * Default configuration for this class.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'key' => 'Auth.User'
    ];

    /**
     * Constructor.
     *
     * @param \Cake\Network\Request $request Request instance.
     * @param array $config Configuration list.
     */
    public function __construct(Request $request, array $config = [])
    {
        $this->_session = $request->session();
        $this->config($config);
    }

    /**
     * Read user record from session.
     *
     * @return array|null User record if available else null.
     */
    public function read()
    {
        if ($this->_user !== null) {
            return $this->_user ?: null;
        }

        $this->_user = $this->_session->read($this->_config['key']) ?: false;
        return $this->_user;
    }

    /**
     * Write user record to session.
     *
     * The session id is also renewed to help mitigate issues with session replays.
     *
     * @param array $user User record.
     * @return void
     */
    public function write(array $user)
    {
        $this->_user = $user;

        $this->_session->renew();
        $this->_session->write($this->_config['key'], $user);
    }

    /**
     * Delete user record from session.
     *
     * The session id is also renewed to help mitigate issues with session replays.
     *
     * @return void
     */
    public function delete()
    {
        $this->_user = false;

        $this->_session->delete($this->_config['key']);
        $this->_session->renew();
    }

    /**
     * {@inheritDoc}
     */
    public function redirectUrl($url = null)
    {
        if ($url === null) {
            return $this->_session->read('Auth.redirect');
        }

        if ($url === false) {
            $this->_session->delete('Auth.redirect');
            return;
        }

        $this->_session->write('Auth.redirect', $url);
    }
}
