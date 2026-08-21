<?php

namespace Voyager\Database\Instrument;

use Voyager\Broadcasting\InteractsWithSockets;
use Voyager\Broadcasting\PrivateChannel;
use Voyager\Contracts\Broadcasting\ShouldBroadcast;
use Voyager\Queue\Concerns\SerializesModels;
use Voyager\NutsAndBolts\Collection as BaseCollection;

class BroadcastableModelEventOccurred implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * The model instance corresponding to the event.
     *
     * @var \Voyager\Database\Instrument\Model
     */
    public Model $model;

    /**
     * The event name (created, updated, etc.).
     *
     * @var string
     */
    protected string $event;

    /**
     * The channels that the event should be broadcast on.
     *
     * @var array
     */
    protected array $channels = [];

    /**
     * The queue connection that should be used to queue the broadcast job.
     *
     * @var string
     */
    public string $connection;

    /**
     * The queue that should be used to queue the broadcast job.
     *
     * @var string
     */
    public $queue;

    /**
     * Indicates whether the job should be dispatched after all database transactions have committed.
     *
     * @var bool|null
     */
    public $afterCommit;

    /**
     * Create a new event instance.
     *
     * @param  \Voyager\Database\Instrument\Model  $model
     * @param  string  $event
     */
    public function __construct($model, $event)
    {
        $this->model = $model;
        $this->event = $event;
    }

    /**
     * The channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        $channels = empty($this->channels)
            ? ($this->model->broadcastOn($this->event) ?: [])
            : $this->channels;

        return (new BaseCollection($channels))
            ->map(fn ($channel) => $channel instanceof Model ? new PrivateChannel($channel) : $channel)
            ->all();
    }

    /**
     * The name the event should broadcast as.
     *
     * @return string
     */
    public function broadcastAs()
    {
        $default = class_basename($this->model).ucfirst($this->event);

        return method_exists($this->model, 'broadcastAs')
            ? ($this->model->broadcastAs($this->event) ?: $default)
            : $default;
    }

    /**
     * Get the data that should be sent with the broadcasted event.
     *
     * @return array|null
     */
    public function broadcastWith()
    {
        return method_exists($this->model, 'broadcastWith')
            ? $this->model->broadcastWith($this->event)
            : null;
    }

    /**
     * Manually specify the channels the event should broadcast on.
     *
     * @param  array  $channels
     * @return $this
     */
    public function onChannels(array $channels)
    {
        $this->channels = $channels;

        return $this;
    }

    /**
     * Determine if the event should be broadcast synchronously.
     *
     * @return bool
     */
    public function shouldBroadcastNow()
    {
        return $this->event === 'deleted' &&
               ! method_exists($this->model, 'bootSoftDeletes');
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function event()
    {
        return $this->event;
    }
}
