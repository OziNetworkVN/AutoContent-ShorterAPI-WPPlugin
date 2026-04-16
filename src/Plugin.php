<?php

namespace Ozi\AutoContent;

use Ozi\AutoContent\Admin\DeskPage;
use Ozi\AutoContent\Admin\HistoryPage;
use Ozi\AutoContent\Admin\Menu;
use Ozi\AutoContent\Admin\MetaBox;
use Ozi\AutoContent\Admin\PromptPresetsPage;
use Ozi\AutoContent\Admin\SettingsPage;
use Ozi\AutoContent\Http\AjaxController;
use Ozi\AutoContent\Providers\ProviderManager;
use Ozi\AutoContent\Repositories\PostMetaRepository;
use Ozi\AutoContent\Repositories\PromptPresetRepository;
use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Services\DraftService;
use Ozi\AutoContent\Services\ImageImporter;
use Ozi\AutoContent\Services\PromptManager;
use Ozi\AutoContent\Services\ResponseValidator;
use Ozi\AutoContent\Services\ShortenerClient;
use Ozi\AutoContent\Support\Logger;

class Plugin
{
    public static function boot()
    {
        $settings        = new SettingsRepository();
        $logger          = new Logger();
        $promptManager   = new PromptManager($settings);
        $promptPresets   = new PromptPresetRepository();
        $providerManager = new ProviderManager($settings, $promptManager, $logger);
        $validator       = new ResponseValidator();
        $postMeta        = new PostMetaRepository();
        $draftService    = new DraftService($postMeta);
        $shortenerClient = new ShortenerClient($settings, $logger);
        $imageImporter   = new ImageImporter($logger);

        $settingsPage  = new SettingsPage($settings);
        $presetsPage   = new PromptPresetsPage($promptPresets);
        $historyPage   = new HistoryPage();
        $deskPage      = new DeskPage($settings, $providerManager, $promptPresets);
        $menu          = new Menu($deskPage, $settingsPage, $presetsPage, $historyPage);
        $metaBox       = new MetaBox($postMeta, $shortenerClient, $settings);
        $ajax          = new AjaxController(
            $providerManager, $validator, $draftService,
            $shortenerClient, $postMeta, $settings, $logger,
            $promptPresets, $imageImporter
        );

        add_action('admin_menu',            [$menu,          'register']);
        add_action('admin_init',            [$settingsPage,  'register']);
        add_action('add_meta_boxes',        [$metaBox,       'register']);
        add_action('admin_enqueue_scripts', [static::class,  'enqueueAdminAssets']);
        add_action('admin_post_ozi_save_preset',          [$presetsPage, 'handlePost']);
        add_action('wp_ajax_ozi_generate_draft',          [$ajax, 'generateDraft']);
        add_action('wp_ajax_ozi_create_shortlink',        [$ajax, 'createShortlink']);
        add_action('wp_ajax_ozi_check_connection',        [$ajax, 'checkConnection']);
        add_action('wp_ajax_ozi_metabox_create_shortlink',[$ajax, 'metaboxCreateShortlink']);
    }

    public static function enqueueAdminAssets()
    {
        wp_enqueue_style('ozi-acwp-admin', OZI_ACWP_PLUGIN_URL . 'assets/admin.css', [], OZI_ACWP_VERSION);
    }
}
