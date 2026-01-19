<?php
/**
 * Gerenciador de Taxa de Câmbio Automática
 * Integração com API do Banco Central do Brasil
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Exchange_Rate_Manager {

    private static $table_name = 'dsbc_exchange_rates';

    /**
     * Inicializa o gerenciador
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'create_table' ] );
        add_action( 'ds_update_exchange_rate', [ __CLASS__, 'update_from_bcb' ] );
        
        // Agenda atualização diária se não existir
        if ( ! wp_next_scheduled( 'ds_update_exchange_rate' ) ) {
            wp_schedule_event( time(), 'daily', 'ds_update_exchange_rate' );
        }
    }

    /**
     * Cria tabela para histórico de taxas
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            rate decimal(10,4) NOT NULL,
            source varchar(50) NOT NULL DEFAULT 'bcb',
            date_created datetime NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY date_created (date_created),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Busca cotação do BCB
     */
    public static function fetch_bcb_rate() {
        $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
        $business_day = self::get_business_day( $yesterday );
        
        $bcb_date = date( 'm-d-Y', strtotime( $business_day ) );
        $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarDia(dataCotacao=@dataCotacao)?@dataCotacao='{$bcb_date}'&\$format=json";
        
        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'WordPress/DS-Backgamom-Credits'
            ]
        ] );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( empty( $data['value'] ) ) {
            return false;
        }
        
        $cotacao = $data['value'][0];
        return [
            'rate' => floatval( $cotacao['cotacaoVenda'] ),
            'date' => $business_day,
            'raw_data' => $cotacao
        ];
    }

    /**
     * Encontra dia útil anterior
     */
    private static function get_business_day( $date ) {
        $timestamp = strtotime( $date );
        
        while ( true ) {
            $weekday = date( 'N', $timestamp ); // 1=segunda, 7=domingo
            
            if ( $weekday <= 5 ) { // Segunda a sexta
                return date( 'Y-m-d', $timestamp );
            }
            
            $timestamp = strtotime( '-1 day', $timestamp );
        }
    }

    /**
     * Atualiza taxa do BCB
     */
    public static function update_from_bcb() {
        $bcb_data = self::fetch_bcb_rate();
        
        if ( ! $bcb_data ) {
            error_log( 'DS Credits: Falha ao buscar cotação do BCB' );
            return false;
        }
        
        return self::save_rate( $bcb_data['rate'], 'bcb', true );
    }

    /**
     * Salva nova taxa
     */
    public static function save_rate( $rate, $source = 'manual', $set_active = true ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // Desativa taxa anterior se necessário
        if ( $set_active ) {
            $wpdb->update(
                $table_name,
                [ 'is_active' => 0 ],
                [ 'is_active' => 1 ]
            );
        }
        
        // Insere nova taxa
        $result = $wpdb->insert(
            $table_name,
            [
                'rate' => $rate,
                'source' => $source,
                'date_created' => current_time( 'mysql' ),
                'is_active' => $set_active ? 1 : 0
            ],
            [ '%f', '%s', '%s', '%d' ]
        );
        
        if ( $result && $set_active ) {
            // Atualiza configuração do plugin
            DS_Credit_Converter::update_exchange_rate( $rate );
            
            // Log da atualização
            error_log( "DS Credits: Taxa atualizada para {$rate} (fonte: {$source})" );
        }
        
        return $result !== false;
    }

    /**
     * Obtém histórico de taxas
     */
    public static function get_rates_history( $limit = 50, $offset = 0, $date_from = null, $date_to = null ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where = '1=1';
        $params = [];
        
        if ( $date_from ) {
            $where .= ' AND date_created >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ( $date_to ) {
            $where .= ' AND date_created <= %s';
            $params[] = $date_to . ' 23:59:59';
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE $where ORDER BY date_created DESC LIMIT %d OFFSET %d",
            array_merge( $params, [ $limit, $offset ] )
        );
        
        return $wpdb->get_results( $sql );
    }

    /**
     * Conta total de registros
     */
    public static function count_rates( $date_from = null, $date_to = null ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where = '1=1';
        $params = [];
        
        if ( $date_from ) {
            $where .= ' AND date_created >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ( $date_to ) {
            $where .= ' AND date_created <= %s';
            $params[] = $date_to . ' 23:59:59';
        }
        
        if ( empty( $params ) ) {
            return $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
        }
        
        $sql = $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE $where", $params );
        return $wpdb->get_var( $sql );
    }

    /**
     * Obtém taxa ativa atual
     */
    public static function get_active_rate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        return $wpdb->get_row(
            "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY date_created DESC LIMIT 1"
        );
    }

    /**
     * Força atualização manual
     */
    public static function force_update() {
        return self::update_from_bcb();
    }
}

// Inicializa o gerenciador
DS_Exchange_Rate_Manager::init();