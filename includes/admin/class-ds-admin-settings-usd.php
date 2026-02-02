<?php
/**
 * Interface de Configurações USD
 * 
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Settings_USD {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_ds_update_exchange_rate', [ $this, 'ajax_update_exchange_rate' ] );
    }

    /**
     * Registra configurações
     */
    public function register_settings() {
        register_setting( 'ds_credits_usd_settings', 'ds_backgamom_credits_settings' );
    }

    /**
     * Página de configurações
     */
    public function settings_page() {
        $settings = get_option( 'ds_backgamom_credits_settings', [] );
        $current_rate = DS_Credit_Converter::get_exchange_rate();
        ?>
        <div class="wrap">
            <h1>⚙️ Configurações do Sistema USD</h1>
            
            <div class="notice notice-info">
                <p><strong>Sistema USD:</strong> Todos os créditos são baseados em dólares americanos (1 crédito = US$ 1,00). A taxa de câmbio é usada apenas para conversão nos pagamentos brasileiros.</p>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; max-width: 1200px;">
                
                <!-- Configurações Principais -->
                <div class="card">
                    <h2>💱 Taxa de Câmbio USD/BRL</h2>
                    
                    <form method="post" action="options.php" id="exchange-rate-form">
                        <?php settings_fields( 'ds_credits_usd_settings' ); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Taxa Atual</th>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 1.2em; font-weight: bold;">US$ 1,00 =</span>
                                        <input type="number" 
                                               name="ds_backgamom_credits_settings[exchange_rate]" 
                                               value="<?php echo esc_attr( $current_rate ); ?>" 
                                               step="0.0001" 
                                               min="1" 
                                               max="20" 
                                               style="width: 120px; font-size: 1.2em; font-weight: bold;"
                                               id="exchange_rate_input">
                                        <span style="font-size: 1.2em; font-weight: bold;">BRL</span>
                                    </div>
                                    <p class="description">Taxa de conversão de dólares para reais. Exemplo: 5.3798 significa que US$ 1,00 = R$ 5,3798</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Atualização Automática</th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="ds_backgamom_credits_settings[auto_update_rate]" 
                                               value="1" 
                                               <?php checked( ! empty( $settings['auto_update_rate'] ) ); ?>>
                                        Atualizar taxa automaticamente (em desenvolvimento)
                                    </label>
                                    <p class="description">Quando ativo, a taxa será atualizada automaticamente via API externa.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( 'Salvar Configurações' ); ?>
                    </form>
                </div>

                <!-- Calculadora e Informações -->
                <div>
                    <div class="card">
                        <h3>🧮 Calculadora de Conversão</h3>
                        
                        <div style="margin-bottom: 15px;">
                            <label>Créditos USD:</label>
                            <input type="number" id="calc_usd" value="10" min="0" step="0.01" style="width: 100%;" oninput="calculateUsdToBrl()">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>Valor BRL:</label>
                            <input type="number" id="calc_brl" min="0" step="0.01" style="width: 100%;" oninput="calculateBrlToUsd()" placeholder="Digite valor em reais">
                        </div>
                        
                        <div style="font-size: 0.9em; color: #666; margin-top: 10px;">
                            💡 Digite em qualquer campo para conversão automática
                        </div>
                    </div>

                    <div class="card">
                        <h3>📊 Exemplos de Conversão</h3>
                        <div id="conversion-examples">
                            <!-- Preenchido via JavaScript -->
                        </div>
                    </div>

                    <div class="card">
                        <h3>ℹ️ Informações</h3>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>Taxa mínima: R$ 1,00</li>
                            <li>Taxa máxima: R$ 20,00</li>
                            <li>Precisão: 4 casas decimais</li>
                            <li>Atualização: Manual ou automática</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Histórico de Alterações -->
            <div class="card" style="max-width: 1200px; margin-top: 20px;">
                <h3>📈 Histórico de Alterações da Taxa</h3>
                <?php $this->display_rate_history(); ?>
            </div>
        </div>

        <script>
        function calculateUsdToBrl() {
            const usd = parseFloat(document.getElementById('calc_usd').value) || 0;
            const rate = parseFloat(document.getElementById('exchange_rate_input').value) || <?php echo $current_rate; ?>;
            const brl = usd * rate;
            
            document.getElementById('calc_brl').value = brl.toFixed(2);
        }

        function calculateBrlToUsd() {
            const brl = parseFloat(document.getElementById('calc_brl').value) || 0;
            const rate = parseFloat(document.getElementById('exchange_rate_input').value) || <?php echo $current_rate; ?>;
            const usd = brl / rate;
            
            document.getElementById('calc_usd').value = usd.toFixed(4);
        }

        function calculateConversion() {
            calculateUsdToBrl();
        }

        function updateExamples() {
            const rate = parseFloat(document.getElementById('exchange_rate_input').value) || <?php echo $current_rate; ?>;
            const examples = [1, 5, 10, 25, 50, 100];
            let html = '<table style="width: 100%; font-size: 0.9em;">';
            
            examples.forEach(usd => {
                const brl = usd * rate;
                html += `<tr>
                    <td>${usd} créditos</td>
                    <td>R$ ${brl.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>`;
            });
            
            html += '</table>';
            document.getElementById('conversion-examples').innerHTML = html;
        }

        // Atualizar exemplos quando taxa mudar
        document.getElementById('exchange_rate_input').addEventListener('input', function() {
            updateExamples();
            calculateUsdToBrl();
        });

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateExamples();
            calculateUsdToBrl();
        });
        </script>

        <style>
        .card { 
            background: #fff; 
            border: 1px solid #ccd0d4; 
            box-shadow: 0 1px 1px rgba(0,0,0,.04); 
            padding: 20px; 
            margin-bottom: 20px;
        }
        .card h2, .card h3 { 
            margin-top: 0; 
            color: #23282d; 
        }
        #conversion-examples table {
            border-collapse: collapse;
        }
        #conversion-examples td {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        #conversion-examples td:first-child {
            font-weight: bold;
        }
        #conversion-examples td:last-child {
            text-align: right;
            color: #2271b1;
        }
        </style>
        <?php
    }

    /**
     * Alias para compatibilidade
     */
    public function render_page() {
        $this->settings_page();
    }

    /**
     * Exibe histórico de alterações da taxa
     */
    private function display_rate_history() {
        $history = get_option( 'ds_exchange_rate_history', [] );
        
        if ( empty( $history ) ) {
            echo '<p>Nenhuma alteração registrada ainda.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Data</th>';
        echo '<th>Taxa Anterior</th>';
        echo '<th>Nova Taxa</th>';
        echo '<th>Usuário</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ( array_reverse( array_slice( $history, -10 ) ) as $entry ) {
            echo '<tr>';
            echo '<td>' . date( 'd/m/Y H:i', strtotime( $entry['date'] ) ) . '</td>';
            echo '<td>R$ ' . number_format( $entry['old_rate'], 4, ',', '.' ) . '</td>';
            echo '<td>R$ ' . number_format( $entry['new_rate'], 4, ',', '.' ) . '</td>';
            echo '<td>' . esc_html( $entry['user'] ) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    /**
     * AJAX para atualizar taxa
     */
    public function ajax_update_exchange_rate() {
        check_ajax_referer( 'ds_update_rate' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão' );
        }
        
        $new_rate = floatval( $_POST['rate'] );
        
        if ( $new_rate < 1 || $new_rate > 20 ) {
            wp_send_json_error( 'Taxa inválida' );
        }
        
        $old_rate = DS_Credit_Converter::get_exchange_rate();
        
        if ( DS_Credit_Converter::update_exchange_rate( $new_rate ) ) {
            // Registrar no histórico
            $history = get_option( 'ds_exchange_rate_history', [] );
            $history[] = [
                'date' => current_time( 'mysql' ),
                'old_rate' => $old_rate,
                'new_rate' => $new_rate,
                'user' => wp_get_current_user()->display_name
            ];
            update_option( 'ds_exchange_rate_history', $history );
            
            wp_send_json_success( 'Taxa atualizada com sucesso' );
        } else {
            wp_send_json_error( 'Erro ao atualizar taxa' );
        }
    }
}
