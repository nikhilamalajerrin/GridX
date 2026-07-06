<?php

namespace GridX\Ai\Services;

use GridX\Ai\Contracts\AIContextCapabilityInterface;
use GridX\Ai\Models\AiTask;
use GridX\Ai\Support\AiCapabilityRegistry;

class AiContextResolver
{
    public function __construct(protected AiCapabilityRegistry $registry)
    {
    }

    public function resolve(AiTask $task): array
    {
        $context = [];

        foreach ($this->registry->all() as $capability) {
            if (!$capability instanceof AIContextCapabilityInterface) {
                continue;
            }

            if (!$capability->shouldResolve($task)) {
                continue;
            }

            try {
                $result = $capability->resolve($task);
            } catch (\Throwable $e) {
                $result = [
                    'error' => [
                        'message' => $e->getMessage(),
                        'type'    => get_class($e),
                    ],
                ];
            }

            $context[] = [
                'key'          => $capability->key(),
                'label'        => $capability->label(),
                'module'       => $capability->module(),
                'type'         => $capability->type(),
                'mode'         => $capability->mode(),
                'preview_only' => $capability->previewOnly(),
                'result'       => $result,
            ];
        }

        return $context;
    }
}
