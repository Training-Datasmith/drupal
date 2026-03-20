<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
/**
 * Generates and validates CSRF tokens.
 *
 * @see \Drupal\Tests\Core\Access\CsrfTokenGeneratorTest
 */
class Csrf_Token_Generator
{
    /**
     * Constructs the token generator.
     *
     * @param \Drupal\Core\PrivateKey $privateKey
     *   The private key service.
     * @param \Drupal\Core\Session\MetadataBag $sessionMetadata
     *   The session metadata bag.
     */
    public function __construct(protected \Drupal\Core\Private_Key $private_key, protected \Drupal\Core\Session\Metadata_Bag $session_metadata)
    {
    }
    /**
     * Generates a token based on $value, the user session, and the private key.
     *
     * The generated token is based on the session of the current user. Normally,
     * anonymous users do not have a session, so the generated token will be
     * different on every page request. To generate a token for users without a
     * session, manually start a session prior to calling this function.
     *
     * @param string $value
     *   (optional) An additional value to base the token on.
     *
     * @return string
     *   A 43-character URL-safe token for validation, based on the token seed,
     *   the hash salt provided by Settings::getHashSalt(), and the
     *   'drupal_private_key' configuration variable.
     *
     * @see \Drupal\Core\Site\Settings::getHashSalt()
     * @see \Symfony\Component\HttpFoundation\Session\SessionInterface::start()
     */
    public function get($value = '')
    {
        $seed = $this->session_metadata->get_csrf_token_seed();
        if (empty($seed)) {
            $seed = Crypt::random_bytes_base64();
            $this->session_metadata->set_csrf_token_seed($seed);
        }
        return $this->compute_token($seed, $value);
    }
    /**
     * Validates a token based on $value, the user session, and the private key.
     *
     * @param string $token
     *   The token to be validated.
     * @param string $value
     *   (optional) An additional value to base the token on.
     *
     * @return bool
     *   TRUE for a valid token, FALSE for an invalid token.
     */
    public function validate($token, $value = '')
    {
        $seed = $this->session_metadata->get_csrf_token_seed();
        if (empty($seed)) {
            return false;
        }
        $value = $this->compute_token($seed, $value);
        // PHP 8.0 strictly type hints for hash_equals. Maintain BC until we can
        // enforce scalar type hints on this method.
        if (!is_string($token)) {
            return false;
        }
        return hash_equals($value, $token);
    }
    /**
     * Generates a token based on $value, the token seed, and the private key.
     *
     * @param string $seed
     *   The per-session token seed.
     * @param string $value
     *   (optional) An additional value to base the token on.
     *
     * @return string
     *   A 43-character URL-safe token for validation, based on the token seed,
     *   the hash salt provided by Settings::getHashSalt(), and the site private
     *   key.
     *
     * @see \Drupal\Core\Site\Settings::getHashSalt()
     */
    protected function compute_token(string $seed, $value = ''): string
    {
        return Crypt::hmac_base64($value, $seed . $this->private_key->get() . Settings::get_hash_salt());
    }
}