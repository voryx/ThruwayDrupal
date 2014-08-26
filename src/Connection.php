<?php
/**
 * @file
 * Definition of Drupal\thruway\Connection.
 */

namespace Drupal\thruway;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Drupal\Core\Session\SessionManager;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Drupal\user\Entity\User;
use React\EventLoop\Factory;
use Symfony\Component\Serializer\Serializer;
use Thruway\ClientSession;
use Thruway\Transport\PawlTransportProvider;


class Connection extends \Thruway\Connection
{

    /* The Thruway annotation class */
    const ANNOTATION_CLASS = "\\Drupal\\thruway\\Annotation\\Thruway";

    /* @var $pluginManager ResourcePluginManager */
    private $pluginManager;

    /* @var $serializer Serializer */
    private $serializer;

    /* @var $annotationReader AnnotationReader */
    private $annotationReader;

    /* @var string */
    private $resources;

    /* @var ClientSession */
    private $session;

    private $options;

    function __construct()
    {
        $this->options = \Drupal::config('thruway.settings')->get('options');

        $loop = \Drupal::service('thruway.loop');
        parent::__construct($this->options, $loop);

        $this->pluginManager = \Drupal::service('plugin.manager.thruway');
        $this->serializer = \Drupal::service('serializer');
        $this->annotationReader = new AnnotationReader();
        $this->resources = \Drupal::config('thruway.settings')->get('resources');


        //Register the Thruway annotation
        AnnotationRegistry::registerFile(
            \Drupal::service('module_handler')->getModuleList()['thruway']->getPath() . "/src/Annotation/Thruway.php"
        );

        $this->on('open', [$this, 'onSessionStart']);
    }


    /**
     * @param ClientSession $session
     */
    public function onSessionStart(ClientSession $session)
    {

        try {
            $this->session = $session;

            if ($this->resources
                && $enabled = array_intersect_key($this->pluginManager->getDefinitions(), $this->resources)
            ) {

                foreach ($enabled as $key => $resourceInfo) {
                    if ("thruway" === $resourceInfo['provider']) {
                        $resourceInfo['bundles'] = $this->resources[$key];
                        $this->handleResource($resourceInfo);
                    }
                }
            }

        } catch (\Exception $e) {
            //@todo handle exception
            print_r($e->getMessage());
        }
    }

    /**
     * @param $resourceInfo
     */
    private function handleResource($resourceInfo)
    {
        try {
            $reflectionClass = new \ReflectionClass($resourceInfo['class']);
            $methods = $reflectionClass->getMethods();

            //Create an instance of the resource
            $resourceInstance = $this->pluginManager->getInstance(['id' => $resourceInfo['id']]);

            //Look through the methods and check to see if there are any subscribe or procedure annotations
            foreach ($methods as $method) {
                $this->handleMethod($method, $resourceInstance, $resourceInfo);
            }

        } catch (\Exception $e) {
            //@todo handle exception
            print_r($e->getMessage());
        }
    }

    /**
     * @param $method
     * @param $resourceInstance
     * @param $resourceInfo
     */
    private function handleMethod($method, $resourceInstance, $resourceInfo)
    {

        try {
            /* @var $methodAnnotation \Drupal\thruway\Annotation\Thruway */
            $methodAnnotation = $this->annotationReader->getMethodAnnotation($method, static::ANNOTATION_CLASS);
            $namePrefix = str_replace(':', '.', $resourceInfo['id']);
            if ($methodAnnotation->type === "procedure") {

                //If there is no bundle set for this resource, then use the prefix again.  Yeah, I know this is kind of stupid.
                if (!isset($resourceInfo['bundles'])) {
                    $resourceInfo['bundles'] = [$namePrefix];
                }

                foreach ($resourceInfo['bundles'] as $bundle) {
                    $this->register($namePrefix, $bundle, $methodAnnotation, $resourceInfo, $resourceInstance, $method);
                }
            } elseif ($methodAnnotation->type === "subscribe") {
                foreach ($resourceInfo['bundles'] as $bundle) {
                    $this->subscribe(
                        $namePrefix,
                        $bundle,
                        $methodAnnotation,
                        $resourceInfo,
                        $resourceInstance,
                        $method
                    );
                }
            }

        } catch (\Exception $e) {
            //@todo handle exception
            print_r($e->getMessage());
        }
    }


    /**
     * Register all of the calls that we found with procedure annotations
     *
     * @param $namePrefix
     * @param $bundle
     * @param $methodAnnotation
     * @param $resourceInfo
     * @param $resourceInstance
     * @param $method
     */
    private function register($namePrefix, $bundle, $methodAnnotation, $resourceInfo, $resourceInstance, $method)
    {
        $this->session->register(
            "{$namePrefix}.{$bundle}.{$methodAnnotation->name}",
            function ($args, $kwargs, $details) use ($resourceInfo, $resourceInstance, $method) {

                try {
                    //@todo get login from thruway auth then login user in and make sure that they have permission to make this call

                    $email = $details["authid"];
                    $this->loginUser($email);

                    if ($args[0]
                        && isset($args[0]['type'])
                        && isset($args[0]['type'][0])
                        && isset($args[0]['type'][0]['target_id'])
                        && $resourceInfo['serialization_class']
                    ) {
                        //temp hack
                        $args[0]['type'] = $args[0]['type'][0]['target_id'];
                        $args[0] = entity_create("node", $args[0]);
                    }

                    return call_user_func_array([$resourceInstance, $method->getName()], $args);

                } catch (\Exception $e) {
                    //@todo handle exception
                    print_r($e->getMessage());

                }

                //Should never get here
                return false;
            },
            ['discloseCaller' => true]
        );
    }

    /**
     * Subscribe to all methods with subscribe annotations
     * @todo this still needs to be implemented
     *
     * @param $namePrefix
     * @param $bundle
     * @param $methodAnnotation
     * @param $resourceInfo
     * @param $resourceInstance
     * @param $method
     */
    private function subscribe($namePrefix, $bundle, $methodAnnotation, $resourceInfo, $resourceInstance, $method)
    {
        $this->session->subscribe(
            "{$namePrefix}.{$bundle}.{$methodAnnotation->name}",
            function ($args) use ($resourceInfo, $resourceInstance, $method) {

            }
        );
    }

    /**
     * @param $email
     */
    private function loginUser($email)
    {

        $accounts = \Drupal::service('entity.manager')->getStorage('user')->loadByProperties(
            array('mail' => $email, 'status' => 1)
        );
        $user = reset($accounts);

        /* @var $user User */
//        $user = User::load($uid);

        if (!$user) {
            $user = User::load(0);
        }

        \Drupal::currentUser()->setAccount($user);
    }

    /**
     *  {@inheritdoc}
     */
    public function open($startAuth = false)
    {

        if ($startAuth) {
            $authProvider = \Drupal::service('thruway.auth');
            $authProvider->addTransportProvider(new PawlTransportProvider($this->options['url']));
            $authProvider->start(false);
        }
        $this->getClient()->start();
    }

    public function getSession()
    {
        return $this->session;
    }
}