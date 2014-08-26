<?php
/**
 * Created by PhpStorm.
 * User: daviddan
 * Date: 8/18/14
 * Time: 10:31 PM
 */

namespace Drupal\thruway\Plugin\thruway;

use Drupal\Core\Site\Settings;
use Drupal\thruway\Annotation\Thruway;
use Drupal\thruway\Annotation\ThruwayResource;

/**
 * Represents entities as resources.
 *
 * @ThruwayResource(id = "utils")
 *
 */
class Utils
{

    /**
     * @Thruway(name = "generateToken", type="procedure")
     *
     * @param $user
     * @param $pass
     * @return string
     */
    public function generateToken($user, $pass, $device = "unknown")
    {
        $entityManager = \Drupal::service('entity.manager');
        $userAuth = \Drupal::service('user.auth');

        $accounts = $entityManager->getStorage('user')->loadByProperties(
            array('name' => $user, 'status' => 1)
        );
        $account = reset($accounts);

        /* @var $account \Drupal\user\Entity\User */
        if ($account) {
            $uid = $userAuth->authenticate($user, $pass);
            if ($uid) {
//
                $key = Settings::get('hash_salt');
                $token = array(
                    "uid" => $account->id(),
                    "mail" => $account->getEmail()
            );

                return \JWT::encode($token, $key);

            }
        }

        return false;
    }

} 