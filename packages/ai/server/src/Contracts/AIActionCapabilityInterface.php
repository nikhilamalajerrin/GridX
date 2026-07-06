<?php

namespace GridX\Ai\Contracts;

use GridX\Ai\Models\AiTask;

interface AIActionCapabilityInterface extends AICapabilityInterface
{
    public function shouldPreview(AiTask $task): bool;

    public function inputSchema(): array;

    public function preview(AiTask $task, array $input = []): array;

    public function apply(AiTask $task, array $preview = [], array $input = []): array;
}
