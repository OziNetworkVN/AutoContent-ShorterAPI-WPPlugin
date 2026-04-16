<?php

namespace Ozi\AutoContent\Admin;

use Ozi\AutoContent\Admin\PromptPresetsPage;
use Ozi\AutoContent\Support\Capabilities;

class Menu
{
    private $deskPage;
    private $settingsPage;
    private $presetsPage;

    public function __construct(DeskPage $deskPage, SettingsPage $settingsPage, PromptPresetsPage $presetsPage)
    {
        $this->deskPage = $deskPage;
        $this->settingsPage = $settingsPage;
        $this->presetsPage = $presetsPage;
    }

    public function register()
    {
        add_menu_page(
            'AI Content Desk',
            'AI Content Desk',
            Capabilities::EDIT,
            'ozi-ai-content-desk',
            [$this->deskPage, 'render'],
            'dashicons-edit-page',
            58
        );

        add_submenu_page(
            'ozi-ai-content-desk',
            'AI Content Desk',
            'Desk',
            Capabilities::EDIT,
            'ozi-ai-content-desk',
            [$this->deskPage, 'render']
        );

        add_submenu_page(
            'ozi-ai-content-desk',
            'Prompt Presets',
            'Prompt Presets',
            Capabilities::MANAGE,
            'ozi-ai-content-prompts',
            [$this->presetsPage, 'render']
        );

        add_submenu_page(
            'ozi-ai-content-desk',
            'AI Content Settings',
            'Settings',
            Capabilities::MANAGE,
            'ozi-ai-content-settings',
            [$this->settingsPage, 'render']
        );
    }
}
