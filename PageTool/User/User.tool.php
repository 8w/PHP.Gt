<?php
use Psr\Log\LoggerInterface;
use RoadTest\OAuth\Authenticator;
use RoadTest\User\Entity\AuthenticatedAppUser;
use RoadTest\User\Entity\User;
use RoadTest\User\Exception\InvalidUUIDException;
use RoadTest\Utility\Logger\LoggerFactory;

class User_PageTool extends PageTool
{
    /** @var  LoggerInterface */
    private static $logger;

    /**
     * User PageTool is used to provide authorisation within your application,
     * along with anonymous users for applications that don't require signing up but
     * do require persistent storage. Anonymous users can then be converted into
     * full users by regular authorisation.
     *
     * If authorisation is not required in your application,
     * getUser() is called to get reference to a database user, authorised or
     * simply anonymous. This is a less-strict version of checkAuth().
     */

    public function go($api, $dom, $template, $tool)
    {
    }

    /**
     * Loads the user according to the contents of the User cookie, or creates a
     * new user if one doesn't exist.
     *
     * @param Authenticator $auth
     *
     * @return User        The user details, identified or anonymous.
     */
    public function getUser(Authenticator $auth): User
    {
        $logger = self::getLogger();
        /** @var User $user */
        $user = $this->getUserFromCache();

        if ($user === null) {
            // Ensure there is a UUID tracking cookie set.
            $uuid = self::track();

            $user = $this->loadAuthenticatedUser($auth, $uuid);
            if ($user === null) {
                $user = $this->loadAnonymousUser($uuid);
            }
            if ($user === null) {
                $user = $this->createNewAnonymousUser($uuid);
            }
            $this->cacheUser($user);
        } else {
            $logger->debug("Loaded user " . $user->getId() . " from session");
        }

        $this->markUserActive($user);
        return $user;
    }

    /**
     * @param string $uuid The user's UUID
     *
     * @return User|null The user or null if there is no user with that UUID
     */
    private function loadAnonymousUser(string $uuid)
    {
        $logger = self::getLogger();
        $logger->debug("Attempting to load anonymous user with UUID $uuid");
        // load the user from the DB, if they exist
        /** @noinspection PhpIllegalArrayKeyTypeInspection */
        /** @var User_Api $db */
        $db = $this->_api[$this];
        $dbUser = $db->getByUuid(["uuid" => $uuid]);

        if ($dbUser->hasResult) {
            $dbUser = $dbUser->result[0];
            $logger->debug("Anonymous user {$dbUser['ID']} loaded from database");

            if ($dbUser["isIdentified"] != false) {
                $logger->warning("User " .
                    $dbUser['ID']
                    . " is not authenticated but has isIdentified == true");
            }
            return new User($dbUser["ID"], $dbUser["uuid"], $dbUser["dateTimeLastActive"]);

        } else {
            $logger->debug("No anonymous user found with UUID $uuid");
            return null;
        }
    }

    /**
     * Checks the given Auth object for authentication. If there is no
     * authentication, the method will return null. If there is
     * authentication, the authenticated details will be mapped to the user's
     * database record which in turn will be loaded and returned.
     *
     * @param Authenticator $auth The authentication interface
     * @param string        $uuid The user's UUID
     *
     * @return AuthenticatedAppUser|null A user array - or null if there is no authenticated user
     * @throws InvalidUUIDException
     */
    private function loadAuthenticatedUser(Authenticator $auth, string $uuid)
    {
        $logger = self::getLogger();
        if ($auth->isAuthenticated() === false) {
            return null;
        }

        // The user is authenticated to an OAuth provider.
        // The database will be checked for existing user matching OAuth data...
        // ... if there is no match, one will be stored.
        $resourceOwnerId = $auth->getResourceOwnerId();
        $oauth_uuid = $auth->getAuthenticatedProvider() . $resourceOwnerId;

        $logger->debug("Attempting to load authenticated user with OAuthUID $oauth_uuid");
        /** @noinspection PhpIllegalArrayKeyTypeInspection */
        /** @var User_Api $userDB */
        $userDB = $this->_api[$this];
        $existingOAuthUser = $userDB->getByOAuthUuid([
            "oauth_uuid" => $oauth_uuid,
        ]);

        $user = null;
        if ($existingOAuthUser->hasResult) {
            $dbUser = $existingOAuthUser->result[0];
            $user = new AuthenticatedAppUser(
                $dbUser["ID"],
                $dbUser["uuid"],
                $dbUser["dateTimeLastActive"],
                $dbUser["oauthUuid"],
                $dbUser["oauthProviderName"],
                new DateTime($dbUser["dateTimeIdentified"]));

            $logger->debug("Existing OAuth user ({$user->getId()}) loaded from db");
            if ($user->getUuid() !== self::track()) {
                // update the cookie to match the logged-in user
                self::setTrackingCookie($user->getUuid());
            }
        } else {
            // Store the missing OAuth records once the user ID is found.
            $dbUser = $userDB->getByUuid([
                "uuid" => self::track(),
            ]);

            if ($dbUser->hasResult) {
                $dbUser = $dbUser->result[0];
            } else {
                throw new InvalidUUIDException($uuid);
            }

            $logger->debug("No existing OAuth records found for user "
                . $dbUser["ID"]
                . " - creating new");

            $userDB->linkOAuth([
                "FK_User" => $dbUser["ID"],
                "oauth_uuid" => $oauth_uuid,
                "oauth_name" => $auth->getAuthenticatedProvider(),
            ]);

            $user = new AuthenticatedAppUser(
                $dbUser["ID"],
                $dbUser["uuid"],
                $dbUser["dateTimeLastActive"],
                $oauth_uuid,
                $auth->getAuthenticatedProvider(),
                new DateTime());
        }

        return $user;
    }

    /**
     * Create a new (anonymous) user.
     *
     * @param string $uuid The UUID to associate with the user
     *
     * @return User The newly created user
     */
    private function createNewAnonymousUser(string $uuid): User
    {
        $logger = self::getLogger();
        /** @noinspection PhpIllegalArrayKeyTypeInspection */
        /** @var User_Api $db */
        $db = $this->_api[$this];
        $result = $db->addAnon(["uuid" => $uuid]);

        $user = new User($result->lastInsertID, $uuid, new DateTime());
        $logger->debug("Created new user in db: {$user->getId()}");
        return $user;
    }

    /**
     * Increments the activity indicator in the user table, and sets the last
     * active dateTime to now().
     *
     * @param User $user The user to mark
     *
     * @return void nothing - user is passed by reference
     */
    private function markUserActive(User $user)
    {
        /** @noinspection PhpIllegalArrayKeyTypeInspection */
        /** @var User_Api $userDB */
        $userDB = $this->_api[$this];
        $userDB->setActive(["ID" => $user->getId()]);

        $user->setActive();
    }

    private function cacheUser(User $user)
    {
        Session::set("PhpGt.User", $user);
    }

    /**
     * @return User|null
     */
    private function getUserFromCache()
    {
        return Session::get("PhpGt.User");
    }

    /**
     * Removes tracking of the current user so they're "forgotten" in this session and a new
     * user is created on the next page call.
     */
    public static function unAuth()
    {
        self::getLogger()->debug("unAuthing user - removing PhpGt_User_PageTool cookie " .
            "and PhpGt.User session object");
        self::removeTrackingCookie();
        self::clearCache();
    }

    /**
     * Get the user's UUID (creating a new one if there isn't one associated with this user
     * already)
     *
     * @return string The tracking UUID.
     */
    public static function track()
    {
        if (empty($_COOKIE["PhpGt_User_PageTool"])) {
            $uuid = self::generateSalt();
            self::setTrackingCookie($uuid);
        }

        return $_COOKIE["PhpGt_User_PageTool"];
    }

    /**
     * Creates a new UUID for tracking a (new) user.
     *
     * @return string The UUID.
     */
    private static function generateSalt()
    {
        return hash("sha512", uniqid(APPSALT, true));
    }

    /**
     * Set (or overwrite) the user tracking cookie
     *
     * @param string $uuid The user's UUID
     *
     * @throws HttpError If setcookie fails
     */
    private static function setTrackingCookie(string $uuid)
    {
        $logger = self::getLogger();

        $expires = strtotime("+105 weeks");
        // if we're in production, only allow the cookie over https.  (Can't always
        // do it otherwise the built-in server won't work, and we do want it to)
        $secureOnly = (\App_Config::isProduction() === true);
        if (!setcookie("PhpGt_User_PageTool", $uuid, $expires, "/", "", $secureOnly, true)) {
            throw new HttpError(500,
                "Error generating tracking cookie in User PageTool.");
        }
        $_COOKIE["PhpGt_User_PageTool"] = $uuid;
        $logger->debug("Tracking cookie set/updated for UUID {$uuid}");
    }

    private static function removeTrackingCookie()
    {
        $logger = self::getLogger();

        $_COOKIE["PhpGt_User_PageTool"] = null;
        setcookie("PhpGt_User_PageTool", 0, 1, "/");

        $logger->debug("Tracking cookie removed");
    }

    /**
     * @return LoggerInterface
     */
    private static function getLogger(): LoggerInterface
    {
        if (self::$logger == null) {
            self::$logger = LoggerFactory::get(self::class);
        }

        return self::$logger;
    }

    /** @return void */
    public static function clearCache()
    {
        Session::delete("PhpGt.User");
    }
}#
