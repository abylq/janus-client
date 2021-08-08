<?php

namespace RTippin\Janus\Tests\Plugins;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RTippin\Janus\Exceptions\JanusPluginException;
use RTippin\Janus\Janus;
use RTippin\Janus\Plugins\VideoRoom;
use RTippin\Janus\Tests\JanusTestCase;

class VideoRoomTest extends JanusTestCase
{
    private VideoRoom $videoRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->videoRoom = app(VideoRoom::class);
    }

    /** @test */
    public function it_returns_janus_instance()
    {
        $this->assertInstanceOf(Janus::class, $this->videoRoom->janus());
    }

    /** @test */
    public function it_can_disconnect()
    {
        Http::fake([
            self::Endpoint => Http::response(self::SuccessResponse),
        ]);

        $this->videoRoom->disconnect();

        Http::assertSent(function (Request $request) {
            return $request['janus'] === 'destroy';
        });
    }

    /** @test */
    public function it_can_make_calls_without_disconnecting()
    {
        Http::fake();

        $this->videoRoom->withoutDisconnect()->disconnect();

        Http::assertNothingSent();
    }

    /** @test */
    public function it_can_force_disconnect()
    {
        Http::fake([
            self::Endpoint => Http::response(self::SuccessResponse),
        ]);

        $this->videoRoom->withoutDisconnect()->disconnect(true);

        Http::assertSent(function (Request $request) {
            return $request['janus'] === 'destroy';
        });
    }

    /** @test */
    public function it_list_video_rooms()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'success',
                    'list' => [1, 2, 3],
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)) //message
                ->push(self::SuccessResponse), //disconnect
        ]);

        $this->assertSame([1, 2, 3], $this->videoRoom->list());
    }

    /** @test */
    public function it_throws_exception_if_invalid_list_response()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'error',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $this->expectException(JanusPluginException::class);

        $this->videoRoom->list();
    }

    /** @test */
    public function it_shows_room_exists()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'success',
                    'exists' => true,
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)) //message
                ->push(self::SuccessResponse), //disconnect
        ]);

        $this->assertTrue($this->videoRoom->exists(1234));
    }

    /** @test */
    public function it_shows_room_doesnt_exist()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'success',
                    'exists' => false,
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)) //message
                ->push(self::SuccessResponse), //disconnect
        ]);

        $this->assertFalse($this->videoRoom->exists(1234));
    }

    /** @test */
    public function it_throws_exception_if_invalid_room_exists_response()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'error',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $this->expectException(JanusPluginException::class);

        $this->videoRoom->exists(1234);
    }

    /** @test */
    public function it_creates_video_room_defaults()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'created',
                    'room' => 1234,
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $room = $this->videoRoom->withoutDisconnect()->create();
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertSame(1234, $room['room']);
        $this->assertTrue(strlen($room['pin']) === 6);
        $this->assertTrue(strlen($room['secret']) === 12);
        $this->assertSame('create', $payload['request']);
        $this->assertSame(2, $payload['publishers']);
        $this->assertSame('video-room-secret', $payload['admin_key']);
        $this->assertTrue(strlen($payload['description']) === 10);
    }

    /** @test */
    public function it_creates_video_room_overriding_defaults()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'created',
                    'room' => 1234,
                ],
            ],
        ];
        $params = [
            'description' => 'description',
            'publishers' => 4,
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $room = $this->videoRoom->withoutDisconnect()->create($params, false, false);
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertSame(1234, $room['room']);
        $this->assertNull($room['pin']);
        $this->assertNull($room['secret']);
        $this->assertSame(4, $payload['publishers']);
        $this->assertSame('description', $payload['description']);
    }

    /** @test */
    public function it_throws_exception_if_invalid_create_response()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'error',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $this->expectException(JanusPluginException::class);

        $this->videoRoom->create();
    }

    /** @test */
    public function it_edits_video_room()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'edited',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $edit = $this->videoRoom->withoutDisconnect()->edit(1234, ['edited' => true]);
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertTrue($edit);
        $this->assertSame('edit', $payload['request']);
        $this->assertSame(1234, $payload['room']);
        $this->assertTrue($payload['edited']);
        $this->assertEmpty($payload['secret']);
    }

    /** @test */
    public function it_edits_video_room_with_secret()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'edited',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $edit = $this->videoRoom->withoutDisconnect()->edit(1234, ['edited' => true], 'secret');
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertTrue($edit);
        $this->assertSame('secret', $payload['secret']);
    }

    /** @test */
    public function it_throws_exception_if_invalid_edit_response()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'error',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $this->expectException(JanusPluginException::class);

        $this->videoRoom->edit(1234, ['edited' => true]);
    }

    /** @test */
    public function it_sets_video_room_allowed()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'success',
                    'allowed' => [1, 2, 3],
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $allowed = $this->videoRoom->withoutDisconnect()->allowed(1234, 'add', ['token']);
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertSame('allowed', $payload['request']);
        $this->assertSame(1234, $payload['room']);
        $this->assertSame('add', $payload['action']);
        $this->assertEmpty($payload['secret']);
        $this->assertSame(['token'], $payload['allowed']);
        $this->assertSame([1, 2, 3], $allowed);
    }

    /** @test */
    public function it_sets_video_room_allowed_with_secret_and_no_allowed()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'success',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $allowed = $this->videoRoom->withoutDisconnect()->allowed(1234, 'enable', null, 'secret');
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertSame('enable', $payload['action']);
        $this->assertSame('secret', $payload['secret']);
        $this->assertNull($allowed);
    }

    /** @test */
    public function it_throws_exception_if_invalid_allowed_response()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'error',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $this->expectException(JanusPluginException::class);

        $this->videoRoom->allowed(1234, 'add');
    }

    /** @test */
    public function it_kicks_video_room_participant()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'success',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $kick = $this->videoRoom->withoutDisconnect()->kick(1234, 5678);
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertSame('kick', $payload['request']);
        $this->assertSame(1234, $payload['room']);
        $this->assertSame(5678, $payload['id']);
        $this->assertEmpty($payload['secret']);
        $this->assertTrue($kick);
    }

    /** @test */
    public function it_kicks_video_room_participant_with_secret()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'success',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $kick = $this->videoRoom->withoutDisconnect()->kick(1234, 5678, 'secret');
        $payload = $this->videoRoom->getPluginPayload('body');

        $this->assertSame('secret', $payload['secret']);
        $this->assertTrue($kick);
    }

    /** @test */
    public function it_throws_exception_if_invalid_kick_response()
    {
        $plugin = [
            'plugindata' => [
                'data' => [
                    'videoroom' => 'error',
                ],
            ],
        ];
        Http::fake([
            self::Endpoint => Http::sequence()
                ->push(self::SuccessResponse) //connect
                ->push(self::SuccessResponse) //attach
                ->push(array_merge(self::SuccessResponse, $plugin)), //message
        ]);

        $this->expectException(JanusPluginException::class);

        $this->videoRoom->kick(1234, 5678);
    }
}
