<?php

namespace Ozi\AutoContent\Admin;

use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Support\Capabilities;

class SettingsPage
{
    private $settings;

    /**
     * Suggested models per provider.
     * key   = model ID (inserted into the input)
     * value = short description shown as hint
     */
    private static function providerModels(): array
    {
        return [
            'gemini' => [
                'gemini-3-flash-preview'      => '⭐ Recommended · Smart + fast · $0.50/M in · $3/M out · 1M ctx',
                'gemini-3.1-pro-preview'      => 'Frontier reasoning · Complex agentic tasks',
                'gemini-3.1-flash-lite-preview'=> 'Budget · Ultra-fast · Best cost/token',
                'gemini-2.5-flash'            => 'Stable GA · Fast (deprecated ~Jun 2026)',
                'gemini-2.5-pro'              => 'Stable GA · High quality (deprecated ~Jun 2026)',
                'gemma-4-27b-it'              => 'Open-weight 27B · via Gemini API · instruction-tuned',
                'gemma-4-26b-a4b-it'          => 'Open-weight 26B MoE · lighter · via Gemini API',
            ],
            'grok'   => [
                'grok-3-mini'      => '⭐ Recommended · Fast · Cheapest xAI option',
                'grok-3'           => 'Most capable Grok · Full reasoning',
                'grok-3-fast'      => 'Grok 3 · Low-latency variant',
                'grok-3-mini-fast' => 'Ultra-fast budget · Low-latency',
            ],
            'openai' => [
                'gpt-4o-mini'  => '⭐ Recommended · Fast · $0.15/M in · $0.60/M out',
                'gpt-4.1'      => 'Latest stable · High quality · Best instruction following',
                'gpt-4.1-mini' => 'Fast · Cost-efficient GPT-4.1 variant',
                'gpt-4.1-nano' => 'Ultra-budget · Fastest OpenAI option',
                'gpt-4o'       => 'Multimodal · Balanced quality/speed',
                'o4-mini'      => 'Reasoning model · Complex problems · Cost-efficient',
            ],
        ];
    }

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    public function register()
    {
        register_setting(
            'ozi_acwp_settings_group',
            SettingsRepository::OPTION_KEY,
            [$this->settings, 'sanitize']
        );
    }

    public function render()
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ozi-acwp'));
        }

        $settings = $this->settings->all();
        $models   = self::providerModels();
        ?>
        <div class="wrap">
            <h1>AI Content Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ozi_acwp_settings_group'); ?>
                <table class="form-table" role="presentation">

                    <!-- Default provider -->
                    <tr>
                        <th scope="row"><label for="ozi_default_provider">Default provider</label></th>
                        <td>
                            <select id="ozi_default_provider" name="<?php echo esc_attr(SettingsRepository::OPTION_KEY); ?>[default_provider]">
                                <?php foreach (['grok' => 'Grok (xAI)', 'gemini' => 'Gemini (Google)', 'openai' => 'OpenAI'] as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['default_provider'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <!-- Gemini -->
                    <tr><td colspan="2"><hr><h2 style="margin:0">🔵 Gemini (Google)</h2></td></tr>
                    <?php $this->renderTextField($settings, 'provider_gemini_api_base', 'API base URL'); ?>
                    <?php $this->renderSecretField($settings, 'provider_gemini_api_key', 'API key', 'Get key at aistudio.google.com'); ?>
                    <?php $this->renderModelField($settings, 'gemini', $models['gemini']); ?>
                    <?php $this->renderConnectionRow('gemini', 'Test connection'); ?>

                    <!-- Grok -->
                    <tr><td colspan="2"><hr><h2 style="margin:0">⚫ Grok (xAI)</h2></td></tr>
                    <?php $this->renderTextField($settings, 'provider_grok_api_base', 'API base URL'); ?>
                    <?php $this->renderSecretField($settings, 'provider_grok_api_key', 'API key', 'Get key at console.x.ai'); ?>
                    <?php $this->renderModelField($settings, 'grok', $models['grok']); ?>
                    <?php $this->renderConnectionRow('grok', 'Test connection'); ?>

                    <!-- OpenAI -->
                    <tr><td colspan="2"><hr><h2 style="margin:0">🟢 OpenAI</h2></td></tr>
                    <?php $this->renderTextField($settings, 'provider_openai_api_base', 'API base URL'); ?>
                    <?php $this->renderSecretField($settings, 'provider_openai_api_key', 'API key', 'Get key at platform.openai.com'); ?>
                    <?php $this->renderModelField($settings, 'openai', $models['openai']); ?>
                    <?php $this->renderConnectionRow('openai', 'Test connection'); ?>

                    <!-- Shortener -->
                    <tr><td colspan="2"><hr><h2 style="margin:0">🔗 oziShortener</h2></td></tr>
                    <?php $this->renderTextField($settings, 'shortener_api_base', 'API base URL'); ?>
                    <?php $this->renderSecretField($settings, 'shortener_api_key', 'API key', ''); ?>
                    <?php $this->renderConnectionRow('shortener', 'Test connection'); ?>
                    <?php $this->renderTextarea($settings, 'shortener_allowed_hostnames', 'Allowed hostnames', 'One hostname per line'); ?>
                    <?php $this->renderTextField($settings, 'default_shortener_hostname', 'Default hostname'); ?>

                    <!-- General -->
                    <tr><td colspan="2"><hr><h2 style="margin:0">⚙️ General</h2></td></tr>
                    <?php $this->renderTextField($settings, 'default_language', 'Default language', 'e.g. vi, en'); ?>
                    <?php $this->renderTextarea($settings, 'default_site_context', 'Site context', 'Describe your site audience and niche'); ?>
                    <tr>
                        <th scope="row">Prompt presets</th>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'ozi-ai-content-prompts'], admin_url('admin.php'))); ?>" class="button">Manage Prompt Presets →</a>
                            <p class="description">Create and manage multiple system/user prompt pairs. Select which to use from the AI Content Desk.</p>
                        </td>
                    </tr>

                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php $this->renderConnectionScript(); ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------

    private function renderTextField(array $settings, string $key, string $label, string $hint = '')
    {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input
                    class="regular-text"
                    type="text"
                    id="<?php echo esc_attr($key); ?>"
                    name="<?php echo esc_attr(SettingsRepository::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
                    value="<?php echo esc_attr($settings[$key] ?? ''); ?>"
                >
                <?php if ($hint) : ?>
                    <p class="description"><?php echo esc_html($hint); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function renderSecretField(array $settings, string $key, string $label, string $hint = '')
    {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input
                    class="regular-text"
                    type="password"
                    id="<?php echo esc_attr($key); ?>"
                    name="<?php echo esc_attr(SettingsRepository::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
                    value="<?php echo esc_attr($settings[$key] ?? ''); ?>"
                    autocomplete="new-password"
                >
                <?php if ($hint) : ?>
                    <p class="description"><?php echo esc_html($hint); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function renderModelField(array $settings, string $provider, array $models)
    {
        $key    = 'provider_' . $provider . '_model';
        $listId = 'ozi-models-' . $provider;
        $inputId = esc_attr($key);
        ?>
        <tr>
            <th scope="row"><label for="<?php echo $inputId; ?>">Model</label></th>
            <td>
                <input
                    class="regular-text"
                    type="text"
                    id="<?php echo $inputId; ?>"
                    name="<?php echo esc_attr(SettingsRepository::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
                    value="<?php echo esc_attr($settings[$key] ?? ''); ?>"
                    list="<?php echo esc_attr($listId); ?>"
                    autocomplete="off"
                    placeholder="Type or click a model below"
                >
                <datalist id="<?php echo esc_attr($listId); ?>">
                    <?php foreach ($models as $modelId => $desc) : ?>
                        <option value="<?php echo esc_attr($modelId); ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <div style="margin-top:8px;line-height:2">
                    <?php foreach ($models as $modelId => $desc) : ?>
                        <a
                            href="#"
                            class="ozi-model-chip"
                            data-target="<?php echo esc_attr($inputId); ?>"
                            data-model="<?php echo esc_attr($modelId); ?>"
                            style="display:inline-block;margin:2px 4px 2px 0;padding:2px 8px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:3px;text-decoration:none;color:#1d2327;font-size:12px;font-family:monospace"
                        ><?php echo esc_html($modelId); ?></a>
                        <span style="color:#50575e;font-size:12px"><?php echo esc_html($desc); ?></span><br>
                    <?php endforeach; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    private function renderTextarea(array $settings, string $key, string $label, string $hint = '')
    {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <textarea
                    class="large-text code"
                    rows="5"
                    id="<?php echo esc_attr($key); ?>"
                    name="<?php echo esc_attr(SettingsRepository::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
                ><?php echo esc_textarea($settings[$key] ?? ''); ?></textarea>
                <?php if ($hint) : ?>
                    <p class="description"><?php echo esc_html($hint); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function renderConnectionRow(string $target, string $label)
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <button
                    class="button ozi-acwp-check-connection"
                    type="button"
                    data-target="<?php echo esc_attr($target); ?>"
                >Check Connection</button>
                <span class="ozi-acwp-check-result" data-target="<?php echo esc_attr($target); ?>"></span>
            </td>
        </tr>
        <?php
    }

    private function renderConnectionScript()
    {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Model chip click → fill input
            document.querySelectorAll('.ozi-model-chip').forEach(function (chip) {
                chip.addEventListener('click', function (e) {
                    e.preventDefault();
                    var input = document.getElementById(chip.getAttribute('data-target'));
                    if (input) {
                        input.value = chip.getAttribute('data-model');
                        // Brief visual feedback
                        chip.style.background = '#d7e8f5';
                        chip.style.borderColor = '#2271b1';
                        setTimeout(function () {
                            chip.style.background = '#f0f0f1';
                            chip.style.borderColor = '#c3c4c7';
                        }, 800);
                    }
                });
            });

            // Check connection buttons
            document.querySelectorAll('.ozi-acwp-check-connection').forEach(function (button) {
                button.addEventListener('click', async function () {
                    var target = button.getAttribute('data-target');
                    var result = document.querySelector('.ozi-acwp-check-result[data-target="' + target + '"]');

                    button.disabled = true;
                    if (result) {
                        result.textContent = ' Checking…';
                        result.style.color = '#50575e';
                    }

                    var body = new URLSearchParams({
                        action: 'ozi_check_connection',
                        nonce:  '<?php echo esc_js(wp_create_nonce('ozi_acwp_check_connection')); ?>',
                        target: target,
                    });

                    try {
                        var res     = await fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: body.toString(),
                        });
                        var payload = await res.json();
                        if (result) {
                            result.textContent = ' ' + (payload.data?.message || 'Unknown response');
                            result.style.color  = payload.success ? '#0a7f37' : '#b32d2e';
                        }
                    } catch (err) {
                        if (result) {
                            result.textContent = ' ' + err.message;
                            result.style.color  = '#b32d2e';
                        }
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        });
        </script>
        <?php
    }
}
