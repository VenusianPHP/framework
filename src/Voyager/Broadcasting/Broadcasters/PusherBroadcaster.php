<?php

namespace Voyager\Broadcasting\Broadcasters;

use Voyager\Broadcasting\BroadcastException;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Collection;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PusherBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /**
     * The Pusher SDK instance.
     *
     * @var \Pusher\Pusher
     */
    protected $pusher;

    /**
     * Indicates if JSONP callbacks are allowed on authorization.
     *
     * @var bool
     */
    protected $allowJsonp = false;

    /**
     * Create a new broadcaster instance.
     *
     * @param  \Pusher\Pusher  $pusher
     */
    public function __construct(Pusher $pusher, bool $allowJsonp = false)
    {
        $this->pusher = $pusher;
        $this->allowJsonp = $allowJsonp;
    }

    /**
     * Resolve the authenticated user payload for an incoming connection request.
     *
     * See: https://pusher.com/docs/channels/library_auth_reference/auth-signatures/#user-authentication
     * See: https://pusher.com/docs/channels/server_api/authenticating-users/#response
     *
     * @param  \Voyager\Http\Request  $request
     * @return array|null
     */
    public function resolveAuthenticatedUser($request)
    {
        if (! $user = parent::resolveAuthenticatedUser($request)) {
            return;
        }

        if (method_exists($this->pusher, 'authenticateUser')) {
            return $this->pusher->authenticateUser($request->socket_id, $user);
        }

        $settings = $this->pusher->getSettings();
        $encodedUser = json_encode($user);
        $decodedString = "{$request->socket_id}::user::{$encodedUser}";

        $auth = $settings['auth_key'].':'.hash_hmac(
            'sha256', $decodedString, $settings['secret']
        );

        return [
            'auth' => $auth,
            'user_data' => $encodedUser,
        ];
    }

    // Laravel authenticates an incoming request for a channel here.
    // Receiving requests is out of scope for this port, so the auth surface
    // is cut and only the broadcast path remains.

    /**
     * Decode the given Pusher response.
     *
     * @param  \Voyager\Http\Request  $request
     * @param  mixed  $response
     * @return array
     */
    protected function decodePusherResponse($request, $response)
    {
        if (! $request->input('callback', false) || ! $this->allowJsonp) {
            return json_decode($response, true);
        }

        return response()->json(json_decode($response, true))
            ->withCallback($request->callback);
    }

    /**
     * Broadcast the given event.
     *
     * @param  array  $channels
     * @param  string  $event
     * @param  array  $payload
     * @return void
     *
     * @throws \Voyager\Broadcasting\BroadcastException
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $socket = Arr::pull($payload, 'socket');

        $parameters = $socket !== null ? ['socket_id' => $socket] : [];

        $channels = new Collection($this->formatChannels($channels));

        try {
            $channels->chunk(100)->each(function ($channels) use ($event, $payload, $parameters) {
                $this->pusher->trigger($channels->toArray(), $event, $payload, $parameters);
            });
        } catch (ApiErrorException $e) {
            throw new BroadcastException(
                sprintf('Pusher error: %s.', $e->getMessage())
            );
        }
    }

    /**
     * Get the Pusher SDK instance.
     *
     * @return \Pusher\Pusher
     */
    public function getPusher()
    {
        return $this->pusher;
    }

    /**
     * Set the Pusher SDK instance.
     *
     * @param  \Pusher\Pusher  $pusher
     * @return void
     */
    public function setPusher($pusher)
    {
        $this->pusher = $pusher;
    }
}
