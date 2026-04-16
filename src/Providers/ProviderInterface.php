<?php

namespace Ozi\AutoContent\Providers;

interface ProviderInterface
{
    public function key(): string;

    public function label(): string;

    public function defaultModel(): string;

    /** @param array|null $preset ['system_prompt' => '…', 'user_template' => '…'] */
    public function generate(array $payload, ?array $preset = null): array;

    public function testConnection(): array;
}
