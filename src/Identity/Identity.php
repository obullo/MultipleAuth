<?php

namespace Obullo\Auth\Identity;

use Interop\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * User Identity
 *
 * @copyright 2016 Obullo
 * @license   http://opensource.org/licenses/MIT MIT license
 */
class Identity extends AbstractIdentity
{
    /**
     * Provider
     *
     * @var object
     */
    protected $provider;

    /**
     * Request
     *
     * @var object
     */
    protected $request;

    /**
     * Storage
     *
     * @var object
     */
    protected $storage;

     /**
     * Container
     *
     * @var object
     */
    protected $container;

    /**
     * Constructor
     *
     * @param array  $params parameters
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->provider  = $container->get('Auth:Provider');
        $this->storage   = $container->get('Auth:Storage');

        $this->initialize();
    }
    
    /**
     * Set http request
     *
     * @param object $request request
     *
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Initializer
     *
     * @return void
     */
    public function initialize()
    {
        if ($this->storage->getCredentials()) {
            /**
             * We need extend the cache TTL of current user,
             * thats why we need update last activity for each page request.
             * Otherwise permanent storage TTL will be expired because of user has no activity.
             */
            $this->storage->update('__lastActivity', time());
            return;
        }
    }
    
    /**
     * Returns true if user has recaller cookie (__rm).
     *
     * @return false|string token
     */
    public function hasRecallerCookie($token = false)
    {
        $cookie = $this->container->get('Auth.RECALLER_COOKIE');
        $rm     = $cookie['name'];

        $cookies = $this->request->getCookieParams();
        $token   = (empty($cookies[$rm])) ? $token : $cookies[$rm];

        if ($this->validateRecaller($token)) { // Remember the user if cookie exists
            if ($this->check()) {
                return false;
            }
            return $token;
        }
        return false;
    }

    /**
     * Check user has identity
     *
     * Its ok if returns to true otherwise false
     *
     * @return boolean
     */
    public function check()
    {
        if ($this->get('__isAuthenticated') == 1) {
            return true;
        }
        return false;
    }

    /**
     * Opposite of check() function
     *
     * @return boolean
     */
    public function guest()
    {
        if ($this->check()) {
            return false;
        }
        return true;
    }

    /**
     * Validate recaller cookie value
     *
     * @param string $token value
     *
     * @return string|boolean false
     */
    public function validateRecaller($token)
    {
        if ($this->guest() && ! empty($token) && ctype_alnum($token) && strlen($token) == 32) {
            return $token;
        }
        return false;
    }

    /**
     * Returns to 1 if user authenticated on temporary memory block otherwise 0.
     *
     * @return boolean
     */
    public function isTemporary()
    {
        return (bool)$this->get('__isTemporary');
    }

    /**
     * Set time to live
     *
     * @param int $ttl expire
     *
     * @return void
     */
    public function expire($ttl)
    {
        if ($this->check()) {
            $this->storage->update('__expire', time() + $ttl);
        }
    }

    /**
     * Check identity is expired
     *
     * @return boolean
     */
    public function isExpired()
    {
        if ($this->get('__expire') == false) {
            return false;
        }
        if ($this->get('__expire') < time()) {
            return true;
        }
        return false;
    }

    /**
     * Move permanent identity to temporary block
     *
     * @return void
     */
    public function makeTemporary($expire = 300)
    {
        $this->storage->makeTemporary($expire);
    }

    /**
     * Move temporary identity to permanent block
     *
     * @return void
     */
    public function makePermanent()
    {
        $this->storage->makePermanent();
    }

    /**
     * Checks new identity data available in storage.
     *
     * @return boolean
     */
    public function exists()
    {
        if ($this->get('__isAuthenticated') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Returns to unix microtime value.
     *
     * @return string
     */
    public function getTime()
    {
        return (int)$this->get('__time');
    }

    /**
     * Returns to remember token
     *
     * @return integer
     */
    public function getRememberToken()
    {
        return $this->get($this->provider->getRememberTokenColumn());
    }

    /**
     * Returns to login id of user, its an unique id for each browsers e.g: 87060e89.
     *
     * @return string|false
     */
    public function getLoginId()
    {
        if (! $this->exists()) {
            return false;
        }
        return empty($_SESSION['Auth_LoginId']) ? false : $_SESSION['Auth_LoginId'];
    }

    /**
     * Sets authority of user to "0" don't touch to cached data
     *
     * @return void
     */
    public function logout()
    {
        if ($this->check()) {
            $this->updateRememberToken();
            $this->storage->update('__isAuthenticated', 0);
            
            // Do not remove identifier otherwise we can't get
            // user data using $this->user->identity->getArray().
        }
    }

    /**
     * Destroy permanent identity
     *
     * @return void
     */
    public function destroy()
    {
        if ($this->guest()) {
            return;
        }
        $this->updateRememberToken();
        $this->storage->deleteCredentials();
        $this->removeSessionIdentifiers();
    }

    /**
     * Remove identifiers from session
     *
     * @return void
     */
    protected function removeSessionIdentifiers()
    {
        unset($_SESSION['Auth_LoginId']);
        unset($_SESSION['Auth_Identifier']);
    }

    /**
     * Update temporary credentials
     *
     * @param string $key key
     * @param string $val value
     *
     * @return void
     */
    public function updateTemporary($key, $val)
    {
        if ($this->check()) {
            return;
        }
        $this->storage->updateTemporary($key, $val);
    }

    /**
     * Destroy temporary identity of unauthorized user
     *
     * @return void
     */
    public function destroyTemporary()
    {
        if ($this->check()) {
            return;
        }
        $this->updateRememberToken();
        $this->storage->deleteCredentials();
        $this->removeSessionIdentifiers();
    }

    /**
     * Remove recaller cookie
     *
     * @return void
     */
    public function forgetMe()
    {
        $this->container->get('Auth:RecallerToken')->remove();
    }

    /**
     * Update remember token if it exists in the memory and browser header
     *
     * @return int|boolean
     */
    public function updateRememberToken()
    {
        if ($this->getRememberMe() == 1) {  // If user checked rememberMe option

            $tokenValue = $this->container->get('Auth:RecallerToken')->create();
            $this->provider->updateRememberToken($tokenValue, $this->getIdentifier());

            $rememberColumn = $this->provider->getRememberTokenColumn();
            $this->set($rememberColumn, $tokenValue);
            return;
        }
    }
}
