<?php
namespace telenote;

class ApiUtils
{
    /**
     * @var \telenote\Utils
     */
    private $_utils;

    /**
     * @var \telenote\ApiClient
     */
    private $_apiClient;

    function __construct()
    {
        $this->_utils = new \telenote\Utils();
        $this->_apiClient = new \telenote\ApiClient();
    }

    function get_link_id($user_id)
    {
        if (empty($user_id)) {
            return null;
        }

        $api_token = get_option('telenote_api_token');

        if (empty($api_token)) {
            return null;
        }

        $project_alias = get_option('telenote_project_alias');

        if (empty($project_alias)) {
            return null;
        }

        $response = $this->_apiClient->get_deep_linking_id($api_token, $project_alias, strval($user_id));

        if ( $this->_utils->is_response_not_ok($response) ) {
            return null;
        }

        $link_info = json_decode($response['body']);
        $link_id = $link_info->link_id;

        return $link_id;
    }

    function get_deep_link($api_token, $user_id)
    {
        if (empty($api_token)) {
            return null;
        }

        if (empty($user_id)) {
            return null;
        }

        $project_alias = get_option('telenote_project_alias');

        if (empty($project_alias)) {
            return null;
        }

        # Get or create secure deep link from server
        $response = $this->_apiClient->get_deep_link($api_token, $project_alias, strval($user_id));

        if ( $this->_utils->is_response_not_ok($response) ) {
            return null;
        }

        $response_data = json_decode($response['body']);
        $deep_link = $response_data->deep_link;

        return $deep_link;
    }

    function get_deep_link_escaped($api_token, $user_id)
    {
        return esc_url($this->get_deep_link($api_token, $user_id));
    }

    function call_api_account_info()
    {
        $api_token = get_option('telenote_api_token');

        if (empty($api_token)) {
            return null;
        }

        $project_alias = get_option('telenote_project_alias');

        if (empty($project_alias)) {
            return null;
        }

        $response = $this->_apiClient->get_account_info($api_token, $project_alias);

        return $response;
    }

    function get_balance()
    {
        $response = $this->call_api_account_info();

        if ( $this->_utils->is_response_not_ok($response) ) {
            return null;
        }

        $body = json_decode($response['body']);
        $account_info = array(
            'balance' => $body->balance,
        );

        if (empty($account_info)) {
            return '';
        }

        return $account_info['balance'];
    }

    function get_balance_escaped()
    {
        return esc_html($this->get_balance());
    }
}
