<?php
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
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Test\TestCase\Controller\Component;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\Controller\Component\AuthenticationComponent;
use Authentication\Identity;
use Authentication\IdentityInterface;
use Authentication\Test\TestCase\AuthenticationTestCase as TestCase;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\ORM\Entity;
use TestApp\Authentication\InvalidAuthenticationService;

/**
 * Authentication component test.
 */
class AuthenticationComponentTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->identityData = new Entity([
            'username' => 'florian',
            'profession' => 'developer'
        ]);

        $this->identity = new Identity($this->identityData);

        $this->service = new AuthenticationService([
            'identifiers' => [
                'Authentication.Password'
            ],
            'authenticators' => [
                'Authentication.Session',
                'Authentication.Form'
            ]
        ]);

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/'],
            [],
            ['username' => 'mariano', 'password' => 'password']
        );

        $this->response = new Response();
    }

    /**
     * testGetAuthenticationService
     *
     * @return void
     */
    public function testGetAuthenticationService()
    {
        $service = new AuthenticationService();
        $request = $this->request->withAttribute('authentication', $service);
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $result = $component->getAuthenticationService();
        $this->assertSame($service, $result);
    }

    /**
     * testGetAuthenticationServiceMissingServiceAttribute
     *
     * @expectedException \Exception
     * @expectedExceptionMessage The request object does not contain the required `authentication` attribute
     * @return void
     */
    public function testGetAuthenticationServiceMissingServiceAttribute()
    {
        $controller = new Controller($this->request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $component->getAuthenticationService();
    }

    /**
     * testGetAuthenticationServiceInvalidServiceObject
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Authentication service does not implement Authentication\AuthenticationServiceInterface
     * @return void
     */
    public function testGetAuthenticationServiceInvalidServiceObject()
    {
        $request = $this->request->withAttribute('authentication', new InvalidAuthenticationService());
        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $component->getAuthenticationService();
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testGetIdentity()
    {
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getIdentity();
        $this->assertInstanceOf(IdentityInterface::class, $result);
        $this->assertEquals('florian', $result->username);
    }

    /**
     * testGetIdentity with custom attribute
     *
     * @eturn void
     */
    public function testGetIdentityWithCustomAttribute()
    {
        $this->request = $this->request->withAttribute('customIdentity', $this->identity);
        $this->request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($this->request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry, [
            'identityAttribute' => 'customIdentity'
        ]);

        $result = $component->getIdentity();
        $this->assertInstanceOf(IdentityInterface::class, $result);
        $this->assertEquals('florian', $result->username);
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testSetIdentity()
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->setIdentity($this->identityData);
        $result = $component->getIdentity();
        $this->assertSame($this->identityData, $result->getOriginalData());
    }

    /**
     * testGetIdentity
     *
     * @eturn void
     */
    public function testGetIdentityData()
    {
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getIdentityData('profession');
        $this->assertEquals('developer', $result);
    }

    /**
     * testGetMissingIdentityData
     *
     * @eturn void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The identity has not been found.
     */
    public function testGetMissingIdentityData()
    {
        $request = $this->request->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $component->getIdentityData('profession');
    }

    /**
     * testGetResult
     *
     * @return void
     */
    public function testGetResult()
    {
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);
        $this->assertNull($component->getResult());
    }

    /**
     * testLogout
     *
     * @return void
     */
    public function testLogout()
    {
        $result = null;
        EventManager::instance()->on('Authentication.logout', function (Event $event) use (&$result) {
            $result = $event;
        });

        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $this->assertEquals('florian', $controller->request->getAttribute('identity')->username);
        $component->logout();
        $this->assertNull($controller->request->getAttribute('identity'));
        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals('Authentication.logout', $result->getName());
    }

    /**
     * test getLoginRedirect
     *
     * @eturn void
     */
    public function testGetLoginRedirect()
    {
        $this->service->setConfig('queryParam', 'redirect');
        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service)
            ->withQueryParams(['redirect' => 'ok/path?value=key']);

        $controller = new Controller($request, $this->response);
        $registry = new ComponentRegistry($controller);
        $component = new AuthenticationComponent($registry);

        $result = $component->getLoginRedirect();
        $this->assertSame('/ok/path?value=key', $result);
    }

    /**
     * testAfterIdentifyEvent
     *
     * @return void
     */
    public function testAfterIdentifyEvent()
    {
        $result = null;
        EventManager::instance()->on('Authentication.afterIdentify', function (Event $event) use (&$result) {
            $result = $event;
        });

        $this->service->authenticate(
            $this->request,
            $this->response
        );

        $request = $this->request
            ->withAttribute('identity', $this->identity)
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');
        $controller->startupProcess();

        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals('Authentication.afterIdentify', $result->getName());
        $this->assertNotEmpty($result->getData());
        $this->assertInstanceOf(AuthenticatorInterface::class, $result->getData('provider'));
        $this->assertInstanceOf(IdentityInterface::class, $result->getData('identity'));
        $this->assertInstanceOf(AuthenticationServiceInterface::class, $result->getData('service'));
    }

    /**
     * test unauthenticated actions methods
     *
     * @return void
     */
    public function testUnauthenticatedActions()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');

        $controller->Authentication->allowUnauthenticated(['view']);
        $this->assertSame(['view'], $controller->Authentication->getUnauthenticatedActions());

        $controller->Authentication->allowUnauthenticated(['add', 'delete']);
        $this->assertSame(['add', 'delete'], $controller->Authentication->getUnauthenticatedActions());

        $controller->Authentication->addUnauthenticatedActions(['index']);
        $this->assertSame(['add', 'delete', 'index'], $controller->Authentication->getUnauthenticatedActions());

        $controller->Authentication->addUnauthenticatedActions(['index', 'view']);
        $this->assertSame(
            ['add', 'delete', 'index', 'view'],
            $controller->Authentication->getUnauthenticatedActions(),
            'Should contain unique set.'
        );
    }

    /**
     * test unauthenticated actions ok
     *
     * @return void
     */
    public function testUnauthenticatedActionsOk()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');

        $controller->Authentication->allowUnauthenticated(['view']);
        $controller->startupProcess();
        $this->assertTrue(true, 'No exception should be raised');
    }

    /**
     * test unauthenticated actions mismatched action
     *
     * @return void
     */
    public function testUnauthenticatedActionsMismatchAction()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);
        $controller->Authentication->allowUnauthenticated(['index', 'add']);
        $controller->startupProcess();
    }

    /**
     * test unauthenticated actions ok
     *
     * @return void
     */
    public function testUnauthenticatedActionsNoActionsFails()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication');

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionCode(401);
        $controller->startupProcess();
    }

    /**
     * test disabling requireidentity via settings
     *
     * @return void
     */
    public function testUnauthenticatedActionsDisabledOptions()
    {
        $request = $this->request
            ->withParam('action', 'view')
            ->withAttribute('authentication', $this->service);

        $controller = new Controller($request, $this->response);
        $controller->loadComponent('Authentication.Authentication', [
            'requireIdentity' => false
        ]);

        // Mismatched actions would normally cause an error.
        $controller->Authentication->allowUnauthenticated(['index', 'add']);
        $controller->startupProcess();
        $this->assertTrue(true, 'No exception should be raised as require identity is off.');
    }
}
