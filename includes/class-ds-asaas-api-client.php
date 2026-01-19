<?php
/**
 * Cliente da API Asaas
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DS_Asaas_API_Client {

    /**
     * URL base da API Asaas.
     *
     * @var string
     */
    private $api_base_url;

    /**
     * Chave da API.
     *
     * @var string
     */
    private $api_key;

    /**
     * Construtor.
     *
     * @param string $api_key Chave da API Asaas.
     * @param bool $is_sandbox Se o modo sandbox está ativo.
     */
    public function __construct( $api_key, $is_sandbox = false ) {
        $this->api_key = $api_key;
        $this->api_base_url = $is_sandbox ? 'https://sandbox.asaas.com/api/v3' : 'https://www.asaas.com/api/v3';
    }

    /**
     * Realiza uma requisição GET para a API.
     *
     * @param string $endpoint Endpoint da API.
     * @param array $params Parâmetros da requisição.
     * @return array|WP_Error Corpo da resposta decodificado ou erro.
     */
    private function get( $endpoint, $params = [] ) {
        $url = $this->api_base_url . $endpoint;
        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }

        $response = wp_remote_get( $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'access_token' => $this->api_key,
            ],
        ] );

        return $this->process_response( $response );
    }

    /**
     * Realiza uma requisição POST para a API.
     *
     * @param string $endpoint Endpoint da API.
     * @param array $data Corpo da requisição.
     * @return array|WP_Error Corpo da resposta decodificado ou erro.
     */
    private function post( $endpoint, $data = [] ) {
        $response = wp_remote_post( $this->api_base_url . $endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'access_token' => $this->api_key,
            ],
            'body'    => json_encode( $data ),
        ] );

        return $this->process_response( $response );
    }

    /**
     * Processa a resposta da API.
     *
     * @param array|WP_Error $response Resposta do wp_remote_get/post.
     * @return array|WP_Error Corpo da resposta decodificado ou erro.
     */
    private function process_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code < 200 || $http_code >= 300 ) {
            $error_message = isset( $data['errors'][0]['description'] ) ? $data['errors'][0]['description'] : 'Erro desconhecido na API Asaas.';
            return new WP_Error( 'asaas_api_error', $error_message, [ 'status' => $http_code, 'response' => $data ] );
        }

        return $data;
    }

    /**
     * Busca um cliente no Asaas pelo CPF/CNPJ.
     *
     * @param string $cpf_cnpj CPF ou CNPJ do cliente.
     * @return array|WP_Error Resposta da API.
     */
    public function get_customer_by_cpf( $cpf_cnpj ) {
        return $this->get( '/customers', [ 'cpfCnpj' => $cpf_cnpj ] );
    }

    /**
     * Busca um cliente no Asaas pelo email.
     *
     * @param string $email Email do cliente.
     * @return array|WP_Error Resposta da API.
     */
    public function get_customer_by_email( $email ) {
        return $this->get( '/customers', [ 'email' => $email ] );
    }

    /**
     * Busca um cliente no Asaas pelo ID.
     *
     * @param string $customer_id ID do cliente.
     * @return array|WP_Error Resposta da API.
     */
    public function get_customer( $customer_id ) {
        return $this->get( '/customers/' . $customer_id );
    }

    /**
     * Cria um novo cliente no Asaas.
     *
     * @param array $customer_data Dados do cliente.
     * @return array|WP_Error Resposta da API.
     */
    public function create_customer( $customer_data ) {
        return $this->post( '/customers', $customer_data );
    }

    /**
     * Cria uma nova cobrança no Asaas.
     *
     * @param array $payment_data Dados da cobrança.
     * @return array|WP_Error Resposta da API.
     */
    public function create_payment( $payment_data ) {
        return $this->post( '/payments', $payment_data );
    }
}
