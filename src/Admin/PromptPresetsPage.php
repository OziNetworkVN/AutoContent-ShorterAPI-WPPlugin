<?php

namespace Ozi\AutoContent\Admin;

use Ozi\AutoContent\Repositories\PromptPresetRepository;
use Ozi\AutoContent\Support\Capabilities;

class PromptPresetsPage
{
    private $presets;

    public function __construct(PromptPresetRepository $presets)
    {
        $this->presets = $presets;
    }

    /** Handles form POST via admin-post.php hook */
    public function handlePost(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('Unauthorized', 'ozi-acwp'));
        }

        check_admin_referer('ozi_acwp_preset_save', 'ozi_acwp_preset_nonce');

        $action = sanitize_key(wp_unslash($_POST['preset_action'] ?? ''));

        if ($action === 'delete') {
            $id = sanitize_text_field(wp_unslash($_POST['preset_id'] ?? ''));
            if ($id !== '') {
                $this->presets->delete($id);
            }
        } elseif (in_array($action, ['create', 'update'], true)) {
            $id = sanitize_text_field(wp_unslash($_POST['preset_id'] ?? ''));
            if ($id === '') {
                $id = uniqid('preset_', true);
            }
            $this->presets->save([
                'id'            => $id,
                'name'          => sanitize_text_field(wp_unslash($_POST['preset_name'] ?? '')),
                'system_prompt' => sanitize_textarea_field(wp_unslash($_POST['preset_system'] ?? '')),
                'user_template' => sanitize_textarea_field(wp_unslash($_POST['preset_user'] ?? '')),
            ]);
        }

        wp_redirect(add_query_arg(
            ['page' => 'ozi-ai-content-prompts', 'saved' => 1],
            admin_url('admin.php')
        ));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('You do not have permission.', 'ozi-acwp'));
        }

        $editing  = null;
        $addNew   = isset($_GET['add_new']);

        if (isset($_GET['edit'])) {
            $editing = $this->presets->find(sanitize_text_field(wp_unslash($_GET['edit'])));
        }

        $all = $this->presets->all();
        $tokens = ['{{source_content}}', '{{image_context}}', '{{language}}', '{{site_context}}', '{{output_schema}}'];
        ?>
        <div class="wrap">
            <h1>
                Prompt Presets
                <?php if (!$editing && !$addNew) : ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'ozi-ai-content-prompts', 'add_new' => 1], admin_url('admin.php'))); ?>" class="page-title-action">Add New</a>
                <?php endif; ?>
            </h1>

            <?php if (isset($_GET['saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Saved successfully.</p></div>
            <?php endif; ?>

            <?php if ($editing || $addNew) :
                $preset = $editing ?? ['id' => '', 'name' => '', 'system_prompt' => '', 'user_template' => ''];
                ?>
                <h2><?php echo $editing ? 'Edit Preset' : 'New Preset'; ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('ozi_acwp_preset_save', 'ozi_acwp_preset_nonce'); ?>
                    <input type="hidden" name="action" value="ozi_save_preset">
                    <input type="hidden" name="preset_action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                    <input type="hidden" name="preset_id" value="<?php echo esc_attr($preset['id']); ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="preset_name">Name</label></th>
                            <td><input type="text" id="preset_name" name="preset_name" class="regular-text" value="<?php echo esc_attr($preset['name']); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="preset_system">System prompt</label></th>
                            <td>
                                <textarea id="preset_system" name="preset_system" class="large-text code" rows="8"><?php echo esc_textarea($preset['system_prompt']); ?></textarea>
                                <p class="description">Leave blank to use the built-in default.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="preset_user">User prompt template</label></th>
                            <td>
                                <textarea id="preset_user" name="preset_user" class="large-text code" rows="12"><?php echo esc_textarea($preset['user_template']); ?></textarea>
                                <p class="description">
                                    Available tokens:
                                    <?php foreach ($tokens as $token) : ?>
                                        <code><?php echo esc_html($token); ?></code>
                                    <?php endforeach; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button($editing ? 'Update Preset' : 'Save Preset'); ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'ozi-ai-content-prompts'], admin_url('admin.php'))); ?>">Cancel</a>
                </form>

            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:20%">Name</th>
                            <th>System prompt (preview)</th>
                            <th style="width:15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all)) : ?>
                            <tr>
                                <td colspan="3">
                                    No presets yet.
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'ozi-ai-content-prompts', 'add_new' => 1], admin_url('admin.php'))); ?>">Add one</a>.
                                </td>
                            </tr>
                        <?php else : foreach ($all as $preset) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($preset['name']); ?></strong></td>
                                <td><small><?php echo esc_html(mb_substr($preset['system_prompt'], 0, 150) . (mb_strlen($preset['system_prompt']) > 150 ? '…' : '')); ?></small></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'ozi-ai-content-prompts', 'edit' => $preset['id']], admin_url('admin.php'))); ?>">Edit</a>
                                    &nbsp;|&nbsp;
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Delete this preset?')">
                                        <?php wp_nonce_field('ozi_acwp_preset_save', 'ozi_acwp_preset_nonce'); ?>
                                        <input type="hidden" name="action" value="ozi_save_preset">
                                        <input type="hidden" name="preset_action" value="delete">
                                        <input type="hidden" name="preset_id" value="<?php echo esc_attr($preset['id']); ?>">
                                        <button type="submit" class="button-link" style="color:#b32d2e">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
