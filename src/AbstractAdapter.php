<?php

namespace Obullo\Auth;

use Obullo\Auth\CredentialsInterface as Credentials;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Abstract Adapater
 *
 * @copyright 2016 Obullo
 * @license   http://opensource.org/licenses/MIT MIT license
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * Combine credentials with real column names
     *
     * @param array $credentials id & password data
     *
     * @return boolean
     */
    public function checkCredentials(Credentials $credentials)
    {
        $identity = $credentials->getIdentityValue();
        $password = $credentials->getPasswordValue();

        if (empty($identity) || empty($password)) {
            return false;
        }
        return true;
    }

    /**
     * Regenerate the session id
     *
     * @param bool $deleteOldSession whether to delete old session id
     *
     * @return string session_id
     */
    public function regenerateSessionId($deleteOldSession = true)
    {
        session_regenerate_id((bool) $deleteOldSession);
        $sid = session_id(); // new session_id

        return $sid;
    }

    /**
     * Returns to microtime value
     *
     * @return int
     */
    public function getMicrotime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * This method attempts to validate that
     * the record in the resultset is indeed a record that matched the
     * identity provided to this adapter.
     *
     * @return AuthResult
     */
    abstract protected function validateResult();
}
