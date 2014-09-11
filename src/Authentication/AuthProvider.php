<?php
/**
 * @file
 * Contains \Drupal\thruway\Authentication.
 */

namespace Drupal\thruway\Authentication;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\UserAuthInterface;
use Thruway\Authentication\AbstractAuthProviderClient;

class AuthProvider extends AbstractAuthProviderClient
{
    private $authKey;

    private $methodName;

    private $configFactory;

    /**
     * The user auth service.
     *
     * @var \Drupal\user\UserAuthInterface
     */
    protected $userAuth;

    private $flood;

    private $entityManager;

    private $thruwaySettings;

    function __construct(
        ConfigFactoryInterface $configFactory,
        UserAuthInterface $user_auth,
        FloodInterface $flood,
        EntityManagerInterface $entityManager
    ) {
        $this->configFactory = $configFactory;
        $this->userAuth = $user_auth;
        $this->flood = $flood;
        $this->entityManager = $entityManager;
        $this->thruwaySettings = \Drupal::config('thruway.settings');


        $options = $this->thruwaySettings->get('options');
        $this->authKey = $options["auth_key"];

        $loop = \Drupal::service('thruway.loop');
        parent::__construct([$options["realm"]], $loop);
        $this->methodName = "drupal.{$options["realm"]}";

    }


    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    public function getSession()
    {

        return $this->session;
    }


    /**
     * Mostly copied from the basic_auth module
     *
     * @param $loginInfo
     * @param null $extra
     * @return array|\Drupal\Core\Entity\EntityInterface|null
     */
    public function processAuthenticate($loginInfo, $extra = null)
    {
        if (is_string($loginInfo) && $loginInfo == strtolower("anonymous")) {
            return $this->processAnonymous();
        }

        if (is_string($loginInfo)) {
            return $this->processToken($loginInfo);
        }

        if (isset($loginInfo['user']) && isset($loginInfo['pass'])) {
            $username = $loginInfo['user'];
            $password = $loginInfo['pass'];
        } else {
            return array("FAILURE");
        }

//        $flood_config = $this->configFactory->get('user.flood');
//
//        // Flood protection: this is very similar to the user login form code.
//        // @see \Drupal\user\Form\UserLoginForm::validateAuthentication()
//        // Do not allow any login from the current user's IP if the limit has been
//        // reached. Default is 50 failed attempts allowed in one hour. This is
//        // independent of the per-user limit to catch attempts from one IP to log
//        // in to many different user accounts.  We have a reasonably high limit
//        // since there may be only one apparent IP for all users at an institution.
//        if ($this->flood->isAllowed(
//            'thruway_auth.failed_login_ip',
//            $flood_config->get('ip_limit'),
//            $flood_config->get('ip_window')
//        )
//        ) {
        $accounts = $this->entityManager->getStorage('user')->loadByProperties(
            array('name' => $username, 'status' => 1)
        );
        $account = reset($accounts);
        if ($account) {
//                if ($flood_config->get('uid_only')) {
//                    // Register flood events based on the uid only, so they apply for any
//                    // IP address. This is the most secure option.
//                    $identifier = $account->id();
//                } else {
//                    // The default identifier is a combination of uid and Session IP address. This
//                    // is less secure but more resistant to denial-of-service attacks that
//                    // could lock out all users with public user names.
//                    $identifier = $account->id() . '-' . $this->getSession()->getSessionId();
//                }
//                // Don't allow login if the limit for this user has been reached.
//                // Default is to allow 5 failed attempts every 6 hours.
//                if ($this->flood->isAllowed(
//                    'thruway_auth.failed_login_user',
//                    $flood_config->get('user_limit'),
//                    $flood_config->get('user_window'),
//                    $identifier
//                )
//                ) {
            $uid = $this->userAuth->authenticate($username, $password);
            if ($uid) {
//                        $this->flood->clear('thruway_auth.failed_login_user', $identifier);

                return [
                    "SUCCESS",
                    [
                        "authid" => $this->entityManager->getStorage('user')->load(
                            $uid
                        )->getEmail()
                    ]
                ];

            }
//                    else {
//                        // Register a per-user failed login event.
//                        $this->flood->register(
//                            'thruway_auth.failed_login_user',
//                            $flood_config->get('user_window'),
//                            $identifier
//                        );
//                    }
//                }
//            }
        }
        // Always register an IP-based failed login event.
//        $this->flood->register('basic_auth.failed_login_ip', $flood_config->get('ip_window'));
        return array("FAILURE");

    }

    protected function processToken($token)
    {
        $key = Settings::get('hash_salt');

        $user = \JWT::decode($token, $key);

        $accounts = $this->entityManager->getStorage('user')->loadByProperties(
            array('mail' => $user->mail, 'uid' => $user->uid, 'status' => 1)
        );

        $account = reset($accounts);
        if ($account) {
            return [
                "SUCCESS",
                ["authid" => $user->mail]
            ];
        }


        return array("FAILURE");
    }

    protected function processAnonymous()
    {

        if ($this->thruwaySettings->get('authentication')['allow_anonymous'] !== true) {
            return array("FAILURE");
        }

        $accounts = $this->entityManager->getStorage('user')->loadByProperties(
            array('uid' => 0)
        );

        $account = reset($accounts);
        if ($account) {
            return [
                "SUCCESS",
                ["authid" => "anonymous"]
            ];
        }


        return array("FAILURE");
    }

    public function onSessionStart($session, $transport)
    {

        parent::onSessionStart($session, $transport);

        $loop = $this->getLoop();

        $loop->addPeriodicTimer(30, function () use ($session, $loop) {
            $this->getLogger()->info("Sending a Ping from auth provider\n");
            $session->ping(5);
            $loop->tick();
        });
    }

} 