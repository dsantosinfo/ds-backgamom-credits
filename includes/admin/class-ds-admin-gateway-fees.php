<?php
/**
 * Administração de Taxas dos Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Gateway_Fees {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
    }

    public function render_page() {
        if ( isset( $_GET['message'] ) ) {
            echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>💳 Taxas dos Gateways de Pagamento</h1>
            
            <div class="notice notice-info">
                <p><strong>Como funciona:</strong> Configure taxas adicionais (ou descontos) que serão aplicadas no checkout baseadas no gateway selecionado.</p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field( 'ds_gateway_fees_save', 'ds_gateway_fees_nonce' ); ?>
                
                <?php
                $gateways = DS_Gateway_Fees::get_available_gateways();
                $all_configs = get_option( 'dsbc_gateway_fees', [] );
                
                foreach ( $gateways as $gateway_id => $gateway_title ) :
                    $config = $all_configs[$gateway_id] ?? [
                        'enabled' => false,
                        'label' => '',
                        'percentage' => 0,
                        'fixed' => 0,
                        'taxable' => false,
                        'show_in_title' => true
                    ];
                ?>
                
                <div class="postbox" style="margin-bottom: 20px;">
                    <h2 class="hndle"><?php echo esc_html( $gateway_title ); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th>Ativar Taxa</th>
                                <td>
                                    <input type="checkbox" name="gateways[<?php echo $gateway_id; ?>][enabled]" value="1" <?php checked( $config['enabled'] ); ?>>
                                    <label>Aplicar taxa/desconto para este gateway</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Rótulo</th>
                                <td>
                                    <input type="text" name="gateways[<?php echo $gateway_id; ?>][label]" value="<?php echo esc_attr( $config['label'] ); ?>" placeholder="Ex: PIX, Cartão">
                                    <p class="description">Nome que aparecerá na taxa (ex: "Taxa PIX")</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Taxa Percentual (%)</th>
                                <td>
                                    <input type="number" name="gateways[<?php echo $gateway_id; ?>][percentage]" value="<?php echo esc_attr( $config['percentage'] ); ?>" step="0.01" placeholder="0.00">
                                    <p class="description">Percentual sobre o valor total (use valores negativos para desconto)</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Taxa Fixa (R$)</th>
                                <td>
                                    <input type="number" name="gateways[<?php echo $gateway_id; ?>][fixed]" value="<?php echo esc_attr( $config['fixed'] ); ?>" step="0.01" placeholder="0.00">
                                    <p class="description">Valor fixo em reais (use valores negativos para desconto)</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Opções</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="gateways[<?php echo $gateway_id; ?>][taxable]" value="1" <?php checked( $config['taxable'] ); ?>>
                                        Taxa tributável (aplicar impostos sobre a taxa)
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="gateways[<?php echo $gateway_id; ?>][show_in_title]" value="1" <?php checked( $config['show_in_title'] ); ?>>
                                        Mostrar taxa no título do gateway
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ( $config['enabled'] ) : ?>
                        <div style="background: #f0f8ff; border-left: 4px solid #0073aa; padding: 10px; margin-top: 15px;">
                            <strong>Exemplo:</strong>
                            <?php
                            $example_total = 100;
                            $example_fee = 0;
                            if ( $config['percentage'] ) {
                                $example_fee += ( $example_total * $config['percentage'] ) / 100;
                            }
                            if ( $config['fixed'] ) {
                                $example_fee += $config['fixed'];
                            }
                            
                            if ( $example_fee != 0 ) {
                                $final_total = $example_total + $example_fee;
                                $fee_text = $example_fee > 0 ? 'Taxa' : 'Desconto';
                                echo "Carrinho R$ 100,00 → {$fee_text}: R$ " . number_format( abs($example_fee), 2, ',', '.' ) . " → Total: R$ " . number_format( $final_total, 2, ',', '.' );
                            } else {
                                echo "Nenhuma taxa configurada";
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php endforeach; ?>
                
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                    <h4>💡 Dicas de Configuração</h4>
                    <ul>
                        <li><strong>PIX:</strong> Geralmente desconto (ex: -2% para incentivar)</li>
                        <li><strong>Cartão:</strong> Taxa para cobrir custos (ex: +3.5%)</li>
                        <li><strong>Boleto:</strong> Taxa fixa (ex: +R$ 2,50)</li>
                        <li><strong>Valores negativos:</strong> Criam descontos</li>
                        <li><strong>Combinação:</strong> Pode usar percentual + fixo juntos</li>
                    </ul>
                </div>

                <?php submit_button( 'Salvar Configurações de Taxas' ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_form_submission() {
        if ( ! isset( $_POST['ds_gateway_fees_nonce'] ) || ! wp_verify_nonce( $_POST['ds_gateway_fees_nonce'], 'ds_gateway_fees_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $gateways_config = [];
        
        if ( isset( $_POST['gateways'] ) && is_array( $_POST['gateways'] ) ) {
            foreach ( $_POST['gateways'] as $gateway_id => $config ) {
                $gateways_config[$gateway_id] = [
                    'enabled'       => isset( $config['enabled'] ),
                    'label'         => sanitize_text_field( $config['label'] ?? '' ),
                    'percentage'    => floatval( $config['percentage'] ?? 0 ),
                    'fixed'         => floatval( $config['fixed'] ?? 0 ),
                    'taxable'       => isset( $config['taxable'] ),
                    'show_in_title' => isset( $config['show_in_title'] ),
                ];
            }
        }

        update_option( 'dsbc_gateway_fees', $gateways_config );
        
        wp_redirect( add_query_arg( 'message', 'saved', $_SERVER['REQUEST_URI'] ) );
        exit;
    }
}