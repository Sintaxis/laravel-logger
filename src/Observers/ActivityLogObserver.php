<?php

namespace CrudLog\Logger\Observers;

use CrudLog\Logger\Events\ModelLoggableEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ActivityLogObserver
{
    private ?array $config = null;
    private ?array $modelConfig = null;

    private function getModelConfig(Model $model): ?array
    {
        if (is_null($this->config)) {
            $this->config = app('crudlog.config');
        }

        if (empty($this->config) || ($this->config['implicit']['enabled'] ?? false) !== true) {
            return null;
        }

        $modelClass = get_class($model);
        
        foreach ($this->config['implicit']['tracked_models'] ?? [] as $cfg) {
            if (($cfg['name'] ?? null) === $modelClass) {
                return $cfg;
            }
        }

        return null;
    }

    public function created(Model $model): void
    {
        $this->modelConfig = $this->getModelConfig($model);
        if ($this->shouldLog('created')) {
            $this->logEvent('created', $model);
        }
    }

    public function updated(Model $model): void
    {
        $this->modelConfig = $this->getModelConfig($model);
        if (!$this->shouldLog('updated')) {
            return;
        }

        $originalChanges = array_intersect_key($model->getOriginal(), $model->getChanges());
        
        $filteredOldValues = $this->filterAttributes($originalChanges);
        $filteredNewValues = $this->filterAttributes($model->getChanges());

        if (empty($filteredOldValues) && empty($filteredNewValues)) {
            return;
        }

        $payload = ['old' => $filteredOldValues];
        $this->logEvent('updated', $model, $payload);
    }

    public function deleted(Model $model): void
    {
        $this->modelConfig = $this->getModelConfig($model);
        if ($this->shouldLog('deleted')) {
            $this->logEvent('deleted', $model);
        }
    }

    protected function shouldLog(string $action): bool
    {
        if (is_null($this->modelConfig)) {
            return false;
        }
        $eventsToLog = $this->modelConfig['events'] ?? [];
        return in_array($action, $eventsToLog);
    }

    protected function filterAttributes(array $attributes): array
    {
        if (is_null($this->modelConfig)) {
            return [];
        }

        $allowedAttributes = $this->modelConfig['attributes'] ?? [];

        if (in_array('*', $allowedAttributes)) {
            return $attributes;
        }

        return Arr::only($attributes, $allowedAttributes);
    }

    protected function logEvent(string $action, Model $model, array $payload = []): void
    {
        $payload['filtered_attributes'] = $this->filterAttributes($model->toArray());
        
        if ($action === 'updated') {
            $payload['filtered_new_values'] = $this->filterAttributes($model->getChanges());
        }

        event(new ModelLoggableEvent(
            action: $action,
            loggable: $model,
            payload: $payload
        ));
    }
}