<?php

namespace Ozi\AutoContent\Repositories;

class PromptPresetRepository
{
    public const OPTION_KEY = 'ozi_acwp_prompt_presets';

    public function all(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        return is_array($saved) ? $saved : [];
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $preset) {
            if (($preset['id'] ?? '') === $id) {
                return $preset;
            }
        }
        return null;
    }

    public function save(array $preset): void
    {
        $all = $this->all();
        foreach ($all as $i => $existing) {
            if (($existing['id'] ?? '') === $preset['id']) {
                $all[$i] = $preset;
                update_option(self::OPTION_KEY, $all);
                return;
            }
        }
        $all[] = $preset;
        update_option(self::OPTION_KEY, $all);
    }

    public function delete(string $id): void
    {
        $filtered = array_values(array_filter($this->all(), static function ($p) use ($id) {
            return ($p['id'] ?? '') !== $id;
        }));
        update_option(self::OPTION_KEY, $filtered);
    }

    public function makeNew(string $name = '', string $system = '', string $userTemplate = ''): array
    {
        return [
            'id'            => uniqid('preset_', true),
            'name'          => $name,
            'system_prompt' => $system,
            'user_template' => $userTemplate,
        ];
    }
}
