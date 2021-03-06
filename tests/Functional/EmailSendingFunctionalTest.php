<?php

namespace Outstack\Enveloper\Tests\Functional;

use Outstack\Components\SymfonySwiftMailerAssertionLibrary\SwiftMailerAssertionTrait;
use Outstack\Enveloper\Infrastructure\Delivery\DeliveryMethod\SwiftMailer\SwiftMailerInterface;
use Zend\Diactoros\Request;

class EmailSendingFunctionalTest extends AbstractApiTestCase
{
    use SwiftMailerAssertionTrait;

    protected $mailerSpy;

    public function setUp()
    {
        parent::setUp();

        $this->mailerSpy = self::$kernel->getContainer()->get(SwiftMailerInterface::class);
    }

    public function test_email_without_text_version()
    {
        $request = new Request(
            '/outbox',
            'POST',
            $this->convertToStream(json_encode([
                'template' => 'without-text-version',
                'parameters' => [
                    'name' => 'Bob',
                    'email' => 'bob@example.com'
                ]
            ]))
        );

        $response = $this->client->sendRequest($request);
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertCountSentMessages(1);
        $this->assertMessageSent(
            function(\Swift_Message $message) {
                return
                    1 === count($message->getTo()) &&
                    $this->doesToIncludeEmailAddress($message, 'bob@example.com') &&
                    $this->messageWasFromContact($message, 'test@example.com', 'Test Default Sender');
            }
        );
    }

    public function test_email_with_multiple_ccs()
    {
        $request = new Request(
            '/outbox',
            'POST',
            $this->convertToStream(json_encode([
                'template' => 'new-user-welcome',
                'parameters' => [
                    'user' => [
                        'handle' => 'bobtheuser',
                        'email' => 'bob@example.com',
                    ],
                    'administrators' => [
                        [
                            'name' => 'Jane',
                            'email' => 'janetheadmin@example.com'
                        ],
                        [
                            'name' => 'Sonia',
                            'email' => 'soniatheadmin@example.com'
                        ],
                    ]
                ]
            ]))
        );

        $response = $this->client->sendRequest($request);
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertCountSentMessages(1);
        $this->assertMessageSent(
            function(\Swift_Message $message) {
                return
                    2 === count($message->getCc()) &&
                    array_key_exists('janetheadmin@example.com', $message->getCc()) &&
                    array_key_exists('soniatheadmin@example.com', $message->getCc()) &&
                    $message->getCc()['janetheadmin@example.com'] == 'Jane' &&
                    $message->getCc()['soniatheadmin@example.com'] == 'Sonia';
            }
        );
    }

    public function test_debugging_email_sent()
    {
        $request = new Request(
            '/outbox',
            'POST',
            $this->convertToStream(json_encode([
                'template' => 'simplest-test-message',
                'parameters' => [
                    'name' => 'Bob',
                    'email' => 'bob@example.com'
                ]
            ]))
        );

        $response = $this->client->sendRequest($request);
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertCountSentMessages(1);
        $this->assertMessageSent(
            function(\Swift_Message $message) {
                return
                    1 === count($message->getTo()) &&
                    $this->doesToIncludeEmailAddress($message, 'bob@example.com') &&
                    $this->messageWasFromContact($message, 'test@example.com', 'Test Default Sender');
            }
        );
    }

    public function test_email_with_include()
    {
        $convertToStream = function($str) {
            $stream = fopen("php://temp", 'r+');
            fputs($stream, $str);
            rewind($stream);
            return $stream;
        };
        $request = new Request(
            '/outbox',
            'POST',
            $convertToStream(json_encode([
                'template' => 'template-with-include',
                'parameters' => (object) []
            ]))
        );

        $response = $this->client->sendRequest($request);
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertCountSentMessages(1);
        $this->assertMessageSent(
            function(\Swift_Message $message) {
                return
                    1 === count($message->getTo()) &&
                    false !== strpos($message->getBody(), 'Included file') &&
                    $this->doesToIncludeEmailAddress($message, 'test@example.com') &&
                    $this->messageWasFromContact($message, 'test@example.com', 'Test Default Sender');
            }
        );
    }

    public function test_mjml_email_sent()
    {
        $request = new Request(
            '/outbox',
            'POST',
            $this->convertToStream(json_encode([
                'template' => 'mjml-example',
                'parameters' => [
                    'name' => 'Bob',
                    'email' => 'bob@example.com'
                ]
            ]))
        );

        $response = $this->client->sendRequest($request);
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertCountSentMessages(1);
        $this->assertMessageSent(
            function(\Swift_Message $message) {
                return
                    false !== strpos($message->getBody(), '<!doctype html>') &&
                    1 === count($message->getTo()) &&
                    $this->doesToIncludeEmailAddress($message, 'bob@example.com') &&
                    $this->messageWasFromContact($message, 'test@example.com', 'Test Default Sender');
            }
        );
    }

}