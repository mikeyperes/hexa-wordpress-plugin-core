<?php

namespace Hexa\PluginCore\ContentTypes;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class ContentTypeRenderer {
    public function render( ContentTypeRegistry $registry, array $args = [] ): string {
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();
        $definitions = $registry->resolved_definitions();
        $title = (string) ( $args['title'] ?? 'Custom Post Types' );
        $description = (string) ( $args['description'] ?? 'Enable content types, control their public URL slugs and labels, and manage their related ACF structures.' );
        $ajax_url = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '';
        $nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( (string) $registry->config( 'nonce_action' ) ) : '';
        ob_start();
        ?>
        <?php echo $assets; ?>
        <?php echo $this->assets(); ?>
        <div class="hpc-ui hpc-content-types" data-hpc-content-types data-ajax-url="<?php echo esc_url( $ajax_url ); ?>" data-action="<?php echo esc_attr( (string) $registry->config( 'ajax_action' ) ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-nonce-field="<?php echo esc_attr( (string) $registry->config( 'nonce_field', 'nonce' ) ); ?>">
            <header class="hpc-content-types-intro">
                <h3><?php echo esc_html( $title ); ?></h3>
                <p><?php echo esc_html( $description ); ?></p>
            </header>
            <div class="hpc-content-type-list">
                <?php foreach ( $definitions as $definition ) : ?>
                    <?php echo $this->content_type_card( $definition ); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $definition */
    private function content_type_card( array $definition ): string {
        $post = $definition['post_type'];
        $external = 'external' === $definition['registration_mode'];
        $supports = implode( ', ', (array) ( $post['args']['supports'] ?? [] ) ) ?: 'Default';
        $taxonomies = implode( ', ', array_map( 'esc_html', array_column( $definition['taxonomies'], 'key' ) ) );
        $header_toggle = CoreUi::toggle(
            'enabled',
            ! empty( $definition['enabled'] ),
            $external ? 'Enable integration' : 'Enable',
            [
                'id'    => 'hpc-content-type-enabled-' . $definition['id'],
                'class' => 'hpc-content-type-enabled hpc-content-type-header-toggle',
            ]
        );

        ob_start();
        ?>
        <section class="hpc-content-type-block hpc-content-type-cpt-block">
            <header class="hpc-content-type-block-heading">
                <h4>CPT settings</h4>
                <?php if ( '' !== $definition['description'] ) : ?><p><?php echo esc_html( $definition['description'] ); ?></p><?php endif; ?>
                <?php if ( $external ) : ?><p>The post type is registered by another plugin. The header switch controls only this integration.</p><?php endif; ?>
            </header>

            <?php if ( $external ) : ?>
                <input class="hpc-content-type-slug" type="hidden" value="<?php echo esc_attr( $post['rewrite_slug'] ); ?>">
                <input class="hpc-content-type-singular" type="hidden" value="<?php echo esc_attr( $post['singular'] ); ?>">
                <input class="hpc-content-type-plural" type="hidden" value="<?php echo esc_attr( $post['plural'] ); ?>">
            <?php else : ?>
                <div class="hpc-content-type-cpt-fields">
                    <h5>Labels and URL</h5>
                    <label class="hpc-field hpc-content-type-field">
                        <span>URL slug</span>
                        <input class="hpc-content-type-slug" type="text" value="<?php echo esc_attr( $post['rewrite_slug'] ); ?>">
                        <small>The public URL segment. The internal post type key will not change.</small>
                    </label>
                    <label class="hpc-field hpc-content-type-field">
                        <span>Singular label</span>
                        <input class="hpc-content-type-singular" type="text" value="<?php echo esc_attr( $post['singular'] ); ?>">
                    </label>
                    <label class="hpc-field hpc-content-type-field">
                        <span>Plural label</span>
                        <input class="hpc-content-type-plural" type="text" value="<?php echo esc_attr( $post['plural'] ); ?>">
                    </label>
                </div>
            <?php endif; ?>

            <details class="hpc-content-type-technical">
                <summary>CPT technical details</summary>
                <dl>
                    <div><dt>Owner</dt><dd><?php echo esc_html( $definition['owner'] ?: 'Host plugin' ); ?></dd></div>
                    <div><dt>Post type key</dt><dd><span class="hpc-code"><?php echo esc_html( $post['key'] ); ?></span></dd></div>
                    <div><dt>Archive</dt><dd><?php echo ! empty( $post['args']['has_archive'] ) ? 'Enabled' : 'Disabled'; ?></dd></div>
                    <div><dt>Supports</dt><dd><?php echo esc_html( $supports ); ?></dd></div>
                    <?php if ( $external ) : ?><div><dt>Registration</dt><dd>Owned by another plugin</dd></div><?php endif; ?>
                    <?php if ( '' !== $taxonomies ) : ?><div><dt>Taxonomies</dt><dd><?php echo $taxonomies; ?></dd></div><?php endif; ?>
                </dl>
            </details>
        </section>
        <?php
        $cpt_body = (string) ob_get_clean();
        $cpt_card = CoreUi::collapsible(
            [
                'title'       => $post['plural'],
                'body_html'   => $cpt_body,
                'meta_html'   => $header_toggle,
                'open'        => false,
                'query_state' => false,
                'class'       => 'hpc-content-type-parent',
            ]
        );

        ob_start();
        ?>
        <div class="hpc-content-type-form" data-content-type-id="<?php echo esc_attr( $definition['id'] ); ?>">
            <?php echo $cpt_card; ?>

            <section class="hpc-content-type-acf-siblings" aria-label="<?php echo esc_attr( 'ACF field groups for ' . $post['plural'] ); ?>">
                <header class="hpc-content-type-acf-siblings-heading">
                    <h4>ACF field groups for <?php echo esc_html( $post['plural'] ); ?></h4>
                    <p>These ACF groups belong to the <span class="hpc-code"><?php echo esc_html( $post['key'] ); ?></span> CPT.</p>
                </header>

                <?php if ( empty( $definition['field_groups'] ) ) : ?>
                    <p class="hpc-content-type-empty">No ACF field groups are attached to this CPT.</p>
                <?php else : ?>
                    <div class="hpc-content-type-acf-list">
                        <?php foreach ( $definition['field_groups'] as $group ) : ?>
                            <?php echo $this->field_group_section( $group, (string) $post['key'] ); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="hpc-actions hpc-actions-bottom hpc-content-type-actions">
                <button type="button" class="hpc-button hpc-content-type-save">Save CPT and ACF settings</button>
                <span class="hpc-content-type-status" aria-live="polite"></span>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $group */
    private function field_group_section( array $group, string $post_type_key ): string {
        $field_count = count( (array) $group['fields'] );
        $header_toggle = CoreUi::toggle(
            'field_group_' . $group['id'],
            ! empty( $group['enabled'] ),
            'Enable',
            [
                'id'    => 'hpc-content-type-acf-enabled-' . $post_type_key . '-' . $group['id'],
                'class' => 'hpc-content-type-field-toggle hpc-content-type-header-toggle',
                'data'  => [ 'field-group-id' => $group['id'] ],
            ]
        );

        ob_start();
        ?>
        <div class="hpc-content-type-acf-body">
            <?php if ( '' !== $group['description'] ) : ?><p class="hpc-content-type-acf-description"><?php echo esc_html( $group['description'] ); ?></p><?php endif; ?>

            <section class="hpc-content-type-acf-details">
                <h5>Group details</h5>
                <dl>
                    <div><dt>ACF group key</dt><dd><span class="hpc-code"><?php echo esc_html( $group['group_key'] ?: 'Not supplied' ); ?></span></dd></div>
                    <div><dt>Attached CPT</dt><dd><span class="hpc-code"><?php echo esc_html( $post_type_key ); ?></span></dd></div>
                    <div><dt>Field count</dt><dd><?php echo esc_html( (string) $field_count ); ?></dd></div>
                    <div><dt>Dependencies</dt><dd><?php echo esc_html( $group['dependencies'] ? implode( ', ', $group['dependencies'] ) : 'None declared' ); ?></dd></div>
                </dl>
            </section>

            <section class="hpc-content-type-field-inventory">
                <h5>Imported fields <span><?php echo esc_html( (string) $field_count ); ?></span></h5>
                <?php if ( $group['fields'] ) : ?>
                    <ol>
                        <?php foreach ( $group['fields'] as $index => $field ) : ?>
                            <li><span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo esc_html( $field ); ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php else : ?>
                    <p>No field inventory was supplied by the host plugin.</p>
                <?php endif; ?>
            </section>
        </div>
        <?php
        $body = (string) ob_get_clean();

        return CoreUi::detail_card(
            [
                'title'     => $group['label'],
                'body_html' => $body,
                'meta_html' => $header_toggle,
                'open'      => false,
                'class'     => 'hpc-content-type-acf-group',
            ]
        );
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) {
            return '';
        }
        $done = true;
        return <<<'HTML'
<style>
.hpc-content-types{max-width:1100px}
.hpc-content-types-intro{border-bottom:1px solid var(--hpc-line);margin:0 0 20px;padding:0 2px 18px}
.hpc-content-types-intro h3{font-size:22px;margin:0 0 6px}
.hpc-content-types-intro p{color:var(--hpc-muted);font-size:14px;margin:0;max-width:780px}
.hpc-content-type-list{display:flex;flex-direction:column;gap:14px}
.hpc-section.hpc-content-type-parent{border-color:#ccd6e3;margin:0}
.hpc-section.hpc-content-type-parent>summary{padding:18px 20px}
.hpc-section.hpc-content-type-parent>summary .hpc-section-title{font-size:16px}
.hpc-section.hpc-content-type-parent[open]>summary{background:#f4f7fb;border-bottom:1px solid var(--hpc-line)}
.hpc-section.hpc-content-type-parent>.hpc-section-body{border-top:0;padding:0}
.hpc-content-type-header-toggle{font-size:12px;font-weight:700;gap:7px;white-space:nowrap}
.hpc-content-type-header-toggle .hpc-toggle-label{font-size:12px}
.hpc-content-type-form{min-width:0}
.hpc-content-type-block{padding:24px 0}
.hpc-content-type-parent .hpc-content-type-block{padding:24px}
.hpc-content-type-block-heading{margin:0 0 18px;max-width:780px}
.hpc-content-type-block-heading h4{font-size:17px;margin:0 0 7px}
.hpc-content-type-block-heading p{color:var(--hpc-muted);font-size:13px;line-height:1.55;margin:0}
.hpc-content-type-cpt-fields{margin-top:22px;max-width:780px}
.hpc-content-type-cpt-fields h5,.hpc-content-type-acf-details h5,.hpc-content-type-field-inventory h5{font-size:14px;margin:0 0 14px}
.hpc-content-type-field{display:block;max-width:780px}
.hpc-content-type-field+.hpc-content-type-field{margin-top:16px}
.hpc-content-type-field input{box-sizing:border-box;width:100%}
.hpc-content-type-field small{color:var(--hpc-muted);display:block;font-size:12px;font-weight:400;margin-top:5px}
.hpc-content-type-technical{border-top:1px solid var(--hpc-line);margin-top:22px;max-width:780px;padding:16px 0 0}
.hpc-content-type-technical>summary{color:#34435a;cursor:pointer;font-size:13px;font-weight:700;list-style-position:inside}
.hpc-content-type-technical dl{margin:14px 0 0}
.hpc-content-type-technical dl div{border-top:1px solid #edf0f4;padding:10px 0}
.hpc-content-type-technical dt{color:var(--hpc-muted);font-size:11px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
.hpc-content-type-technical dd{margin:4px 0 0;overflow-wrap:anywhere}
.hpc-content-type-acf-siblings{border-left:3px solid #d9e2ef;margin:10px 0 0 24px;padding:10px 0 0 18px}
.hpc-content-type-acf-siblings-heading{margin:0 0 12px}
.hpc-content-type-acf-siblings-heading h4{font-size:13px;margin:0 0 4px}
.hpc-content-type-acf-siblings-heading p{color:var(--hpc-muted);font-size:12px;margin:0}
.hpc-content-type-acf-list{display:flex;flex-direction:column;gap:10px}
.hpc-detail-card.hpc-content-type-acf-group{background:#fff;border-color:#d5dde8;margin:0}
.hpc-detail-card.hpc-content-type-acf-group>summary{padding:14px 16px}
.hpc-detail-card.hpc-content-type-acf-group>summary .hpc-detail-card-title{font-size:13px}
.hpc-detail-card.hpc-content-type-acf-group[open]>summary{background:#f8fafc}
.hpc-detail-card.hpc-content-type-acf-group>.hpc-detail-card-body{padding:18px}
.hpc-content-type-acf-description{color:var(--hpc-muted);font-size:13px;line-height:1.55;margin:0;max-width:780px}
.hpc-content-type-acf-details{margin-top:20px;max-width:780px}
.hpc-content-type-acf-details dl{border:1px solid #e1e7ef;border-radius:8px;margin:0;overflow:hidden}
.hpc-content-type-acf-details dl div{padding:11px 13px}
.hpc-content-type-acf-details dl div+div{border-top:1px solid #e8edf3}
.hpc-content-type-acf-details dt{color:var(--hpc-muted);font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.hpc-content-type-acf-details dd{font-size:13px;margin:4px 0 0;overflow-wrap:anywhere}
.hpc-content-type-field-inventory{margin-top:20px;max-width:780px}
.hpc-content-type-field-inventory h5{align-items:center;display:flex;gap:8px}
.hpc-content-type-field-inventory h5 span{background:#e9eef8;border-radius:999px;color:#43536b;font-size:10px;padding:4px 7px}
.hpc-content-type-field-inventory ol{border:1px solid #e1e7ef;border-radius:8px;list-style:none;margin:0;overflow:hidden;padding:0}
.hpc-content-type-field-inventory li{align-items:center;color:#34435a;display:flex;font-size:13px;gap:10px;margin:0;padding:9px 12px}
.hpc-content-type-field-inventory li+li{border-top:1px solid #edf1f5}
.hpc-content-type-field-inventory li>span{color:#94a3b8;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:10px;width:20px}
.hpc-content-type-field-inventory>p,.hpc-content-type-empty{color:var(--hpc-muted);font-size:13px;margin:0}
.hpc-content-type-actions{margin:12px 0 0 24px!important;padding-top:14px!important}
.hpc-content-type-status{color:var(--hpc-muted);font-size:13px}
.hpc-content-type-form.is-saving{opacity:.7;pointer-events:none}
.hpc-content-type-form.is-error .hpc-content-type-status{color:var(--hpc-red)}
@media(max-width:782px){.hpc-section.hpc-content-type-parent>summary{align-items:flex-start;padding:15px 16px}.hpc-content-type-parent .hpc-content-type-block{padding:20px 16px}.hpc-content-type-acf-siblings{margin-left:8px;padding-left:10px}.hpc-content-type-actions{margin-left:8px!important}.hpc-detail-card.hpc-content-type-acf-group>summary{align-items:flex-start}.hpc-detail-card.hpc-content-type-acf-group>summary .hpc-detail-card-side{flex-wrap:wrap}.hpc-detail-card.hpc-content-type-acf-group>.hpc-detail-card-body{padding:15px}}
</style>
<script>(function(){if(window.hexaContentTypesReady)return;window.hexaContentTypesReady=true;document.addEventListener('click',function(event){var toggle=event.target.closest('.hpc-content-type-header-toggle');if(!toggle)return;var details=toggle.closest('details');if(!details)return;var wasOpen=details.open;window.setTimeout(function(){details.open=wasOpen},0)},true);document.addEventListener('click',function(event){var button=event.target.closest('.hpc-content-type-save');if(!button)return;var form=button.closest('.hpc-content-type-form');var root=button.closest('[data-hpc-content-types]');if(!form||!root)return;var status=form.querySelector('.hpc-content-type-status');var body=new URLSearchParams();body.set('action',root.dataset.action||'');body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');body.set('content_type_id',form.dataset.contentTypeId||'');body.set('enabled',form.querySelector('.hpc-content-type-enabled input').checked?'1':'0');body.set('rewrite_slug',form.querySelector('.hpc-content-type-slug').value||'');body.set('singular',form.querySelector('.hpc-content-type-singular').value||'');body.set('plural',form.querySelector('.hpc-content-type-plural').value||'');form.querySelectorAll('.hpc-content-type-field-toggle input:checked').forEach(function(input){body.append('enabled_field_groups[]',input.dataset.fieldGroupId||'')});form.classList.add('is-saving');form.classList.remove('is-error');if(status)status.textContent='Saving...';fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(response){return response.json()}).then(function(payload){if(!payload||!payload.success)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Save failed');if(status)status.textContent=payload.data.message||'Saved.';}).catch(function(error){form.classList.add('is-error');if(status)status.textContent=error.message||'Unable to save.';}).finally(function(){form.classList.remove('is-saving')});});})();</script>
HTML;
    }
}
