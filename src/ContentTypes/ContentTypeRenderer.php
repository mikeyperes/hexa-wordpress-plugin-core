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
        $registered = function_exists( 'post_type_exists' ) && post_type_exists( $post['key'] );
        $external = 'external' === $definition['registration_mode'];
        $supports = implode( ', ', (array) ( $post['args']['supports'] ?? [] ) ) ?: 'Default';
        $taxonomies = implode( ', ', array_map( 'esc_html', array_column( $definition['taxonomies'], 'key' ) ) );

        ob_start();
        ?>
        <article class="hpc-content-type-card">
            <header class="hpc-content-type-card-header">
                <h3><?php echo esc_html( $post['plural'] ); ?></h3>
                <p class="hpc-content-type-status-line">
                    <span class="hpc-content-type-registration <?php echo $registered ? 'is-registered' : 'is-unregistered'; ?>">
                        <span aria-hidden="true"></span><?php echo $registered ? 'Registered' : 'Not registered'; ?>
                    </span>
                    <span class="hpc-content-type-key">Post type: <span class="hpc-code"><?php echo esc_html( $post['key'] ); ?></span></span>
                </p>
            </header>
            <div class="hpc-content-type-form" data-content-type-id="<?php echo esc_attr( $definition['id'] ); ?>">
                <div class="hpc-content-type-primary">
                    <?php echo CoreUi::toggle( 'enabled', ! empty( $definition['enabled'] ), $external ? 'Enable this plugin integration' : 'Enable ' . $post['plural'], [ 'class' => 'hpc-content-type-enabled' ] ); ?>
                    <?php if ( '' !== $definition['description'] ) : ?>
                        <p><?php echo esc_html( $definition['description'] ); ?></p>
                    <?php endif; ?>
                    <?php if ( $external ) : ?><p class="hpc-small">The post type is registered by another plugin. This switch controls only this integration.</p><?php endif; ?>
                </div>

                <?php if ( $external ) : ?>
                    <input class="hpc-content-type-slug" type="hidden" value="<?php echo esc_attr( $post['rewrite_slug'] ); ?>">
                    <input class="hpc-content-type-singular" type="hidden" value="<?php echo esc_attr( $post['singular'] ); ?>">
                    <input class="hpc-content-type-plural" type="hidden" value="<?php echo esc_attr( $post['plural'] ); ?>">
                <?php else : ?>
                    <section class="hpc-content-type-section">
                        <h4>Labels and URL</h4>
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
                    </section>
                <?php endif; ?>

                <section class="hpc-content-type-section hpc-content-type-acf">
                    <h4>Custom fields</h4>
                    <?php if ( empty( $definition['field_groups'] ) ) : ?>
                        <p class="hpc-small">No field groups are defined.</p>
                    <?php else : ?>
                        <?php foreach ( $definition['field_groups'] as $group ) : ?>
                            <div class="hpc-content-type-acf-item">
                                <?php echo CoreUi::toggle( 'field_group_' . $group['id'], ! empty( $group['enabled'] ), $group['label'], [ 'class' => 'hpc-content-type-field-toggle', 'data' => [ 'field-group-id' => $group['id'] ] ] ); ?>
                                <?php echo CoreUi::inline_details( 'View fields', $this->field_group_details( $group ) ); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <details class="hpc-content-type-technical">
                    <summary>Technical details</summary>
                    <dl>
                        <div><dt>Owner</dt><dd><?php echo esc_html( $definition['owner'] ?: 'Host plugin' ); ?></dd></div>
                        <div><dt>Post type key</dt><dd><span class="hpc-code"><?php echo esc_html( $post['key'] ); ?></span></dd></div>
                        <div><dt>Archive</dt><dd><?php echo ! empty( $post['args']['has_archive'] ) ? 'Enabled' : 'Disabled'; ?></dd></div>
                        <div><dt>Supports</dt><dd><?php echo esc_html( $supports ); ?></dd></div>
                        <?php if ( $external ) : ?><div><dt>Registration</dt><dd>Owned by another plugin</dd></div><?php endif; ?>
                        <?php if ( '' !== $taxonomies ) : ?><div><dt>Taxonomies</dt><dd><?php echo $taxonomies; ?></dd></div><?php endif; ?>
                    </dl>
                </details>

                <div class="hpc-actions hpc-actions-bottom">
                    <button type="button" class="hpc-button hpc-content-type-save">Save changes</button>
                    <span class="hpc-content-type-status" aria-live="polite"></span>
                </div>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $group */
    private function field_group_details( array $group ): string {
        $html = '' !== $group['description'] ? '<p>' . esc_html( $group['description'] ) . '</p>' : '';
        if ( '' !== $group['group_key'] ) {
            $html .= '<p><strong>ACF group:</strong> <span class="hpc-code">' . esc_html( $group['group_key'] ) . '</span></p>';
        }
        if ( $group['fields'] ) {
            $html .= '<p><strong>Fields:</strong></p><ul class="hpc-list"><li>' . implode( '</li><li>', array_map( 'esc_html', $group['fields'] ) ) . '</li></ul>';
        }
        if ( $group['dependencies'] ) {
            $html .= '<p><strong>Dependencies:</strong> ' . esc_html( implode( ', ', $group['dependencies'] ) ) . '</p>';
        }
        return '' !== $html ? $html : '<p>No additional field metadata supplied.</p>';
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) {
            return '';
        }
        $done = true;
        return <<<'HTML'
<style>
.hpc-content-types{max-width:960px}
.hpc-content-types-intro{border-bottom:1px solid var(--hpc-line);margin:0 0 20px;padding:0 2px 18px}
.hpc-content-types-intro h3{font-size:22px;margin:0 0 6px}
.hpc-content-types-intro p{color:var(--hpc-muted);font-size:14px;margin:0;max-width:720px}
.hpc-content-type-list{display:flex;flex-direction:column;gap:18px}
.hpc-content-type-card{background:#fff;border:1px solid var(--hpc-line);border-radius:10px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden}
.hpc-content-type-card-header{border-bottom:1px solid var(--hpc-line);padding:20px 24px}
.hpc-content-type-card-header h3{font-size:20px;line-height:1.3;margin:0 0 7px}
.hpc-content-type-status-line{align-items:center;color:var(--hpc-muted);display:flex;flex-wrap:wrap;font-size:13px;gap:8px 14px;margin:0}
.hpc-content-type-registration{align-items:center;display:inline-flex;font-weight:700;gap:6px}
.hpc-content-type-registration>span{background:#c2410c;border-radius:50%;display:block;height:7px;width:7px}
.hpc-content-type-registration.is-registered{color:#197a3e}
.hpc-content-type-registration.is-registered>span{background:#22a05a}
.hpc-content-type-key{overflow-wrap:anywhere}
.hpc-content-type-form{padding:0 24px 24px}
.hpc-content-type-primary{background:#f8fafc;border-bottom:1px solid var(--hpc-line);margin:0 -24px;padding:18px 24px}
.hpc-content-type-primary>p{color:var(--hpc-muted);font-size:13px;margin:8px 0 0;max-width:720px}
.hpc-content-type-section{border-bottom:1px solid var(--hpc-line);padding:22px 0}
.hpc-content-type-section h4{font-size:15px;margin:0 0 16px}
.hpc-content-type-field{display:block;max-width:720px}
.hpc-content-type-field+.hpc-content-type-field{margin-top:16px}
.hpc-content-type-field input{box-sizing:border-box;width:100%}
.hpc-content-type-field small{color:var(--hpc-muted);display:block;font-size:12px;font-weight:400;margin-top:5px}
.hpc-content-type-acf-item{border:1px solid var(--hpc-line);border-radius:8px;padding:15px 16px}
.hpc-content-type-acf-item+.hpc-content-type-acf-item{margin-top:10px}
.hpc-content-type-acf-item .hpc-inline-details{margin:10px 0 0 58px}
.hpc-content-type-technical{border-bottom:1px solid var(--hpc-line);padding:18px 0}
.hpc-content-type-technical>summary{color:#34435a;cursor:pointer;font-size:13px;font-weight:700;list-style-position:inside}
.hpc-content-type-technical dl{margin:14px 0 0;max-width:720px}
.hpc-content-type-technical dl div{border-top:1px solid #edf0f4;padding:10px 0}
.hpc-content-type-technical dt{color:var(--hpc-muted);font-size:11px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
.hpc-content-type-technical dd{margin:4px 0 0;overflow-wrap:anywhere}
.hpc-content-type-status{color:var(--hpc-muted);font-size:13px}
.hpc-content-type-form.is-saving{opacity:.7;pointer-events:none}
.hpc-content-type-form.is-error .hpc-content-type-status{color:var(--hpc-red)}
@media(max-width:782px){.hpc-content-type-card-header{padding:17px 18px}.hpc-content-type-form{padding:0 18px 20px}.hpc-content-type-primary{margin:0 -18px;padding:16px 18px}.hpc-content-type-acf-item .hpc-inline-details{margin-left:0}}
</style>
<script>(function(){if(window.hexaContentTypesReady)return;window.hexaContentTypesReady=true;document.addEventListener('click',function(event){var button=event.target.closest('.hpc-content-type-save');if(!button)return;var form=button.closest('.hpc-content-type-form');var root=button.closest('[data-hpc-content-types]');if(!form||!root)return;var status=form.querySelector('.hpc-content-type-status');var body=new URLSearchParams();body.set('action',root.dataset.action||'');body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');body.set('content_type_id',form.dataset.contentTypeId||'');body.set('enabled',form.querySelector('.hpc-content-type-enabled input').checked?'1':'0');body.set('rewrite_slug',form.querySelector('.hpc-content-type-slug').value||'');body.set('singular',form.querySelector('.hpc-content-type-singular').value||'');body.set('plural',form.querySelector('.hpc-content-type-plural').value||'');form.querySelectorAll('.hpc-content-type-field-toggle input:checked').forEach(function(input){body.append('enabled_field_groups[]',input.dataset.fieldGroupId||'')});form.classList.add('is-saving');form.classList.remove('is-error');if(status)status.textContent='Saving...';fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(response){return response.json()}).then(function(payload){if(!payload||!payload.success)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Save failed');if(status)status.textContent=payload.data.message||'Saved.';}).catch(function(error){form.classList.add('is-error');if(status)status.textContent=error.message||'Unable to save.';}).finally(function(){form.classList.remove('is-saving')});});})();</script>
HTML;
    }
}
