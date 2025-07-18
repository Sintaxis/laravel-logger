<?php

namespace CrudLog\Logger\Observers;

use CrudLog\Logger\Events\ModelLoggableEvent;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->logEvent('created', $model);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $payload = ['old' => array_intersect_key($model->getOriginal(), $model->getChanges())];
        $this->logEvent('updated', $model, $payload);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->logEvent('deleted', $model);
    }

    /**
     * Helper method to dispatch the logging event.
     */
    protected function logEvent(string $action, Model $model, array $payload = []): void
    {
        // Fire an event to decouple the observer from the HTTP call logic.
        event(new ModelLoggableEvent(
            action: $action,
            loggable: $model,
            payload: $payload
        ));
    }
}