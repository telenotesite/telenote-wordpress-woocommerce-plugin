<?php
namespace telenote;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ApiClient
{
    const BASE_URI = 'https://api.telenote.site';
    const USER_AGENT = 'telenote_wp/1.0';

    /**
     * @var \telenote\Utils
     */
    private $_utils;

    function __construct()
    {
        $this->_utils = new \telenote\Utils();
    }

    function _get_http_client()
    {
        return new \WP_Http();
    }

    function _retry($url, $params)
    {
        $max_retries = 5;
        $counter = 1;
        $http_client = $this->_get_http_client();
        while($counter < $max_retries)
        {
            $response = $http_client->request($url, $params);
            if ($response instanceof \WP_Error) {
                $counter++;
                continue;
            }
            $status_code = $response['response']['code'];
            if ($status_code >= 200 && $status_code < 500) {
                break;
            }
            $counter++;
        }

        return $response;
    }

    /**
     * Send a message
     *
     * Returns json {"message_id": "uuid4", "status": "NEW"}
     */
    function post_message($api_token, $sid, $uid, $text)
    {
        $time = strval(time());
        $payload = array(
            'sid' => $sid,
            'uid' => $uid,
            'text' => \stripslashes($text),
            'time' => $time,
        );
        $i_key = $this->_utils->generate_random_string(32);
        $body = array(
            'i_key' => $i_key,
            'payload' => $payload,
        );

        $response = $this->_retry(
            self::BASE_URI . '/message-api/v1/messages/',
            [
                'method' => 'POST',
                'body' => json_encode($body),
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $api_token,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        return $response;
    }

    /**
     * Get the link id (the user followed the link for linking and started working with the telegram bot)
     *
     * Returns json {"link_id": "123"} or {"link_id": null} if no link is created
     */
    function get_deep_linking_id($api_token, $sid, $uid)
    {
        $params = array(
            'sid' => $sid,
            'uid' => $uid,
        );
        $query_params = http_build_query($params);

        $response = $this->_retry(
            self::BASE_URI . '/message-api/v1/user-info/deep_linking_id/?' . $query_params,
            [
                'method' => 'GET',
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $api_token,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        return $response;
    }

    /**
     * Get a secure linking link
     *
     * Returns json {"deep_link": "some_link"}
     */
    function get_deep_link($api_token, $sid, $uid)
    {
        $payload = array(
            'api_token' => $api_token,
            'project_alias' => $sid,
            'current_user_id' => $uid,
        );
        $i_key = md5(json_encode($payload));
        $body = array(
            'i_key' => $i_key,
            'payload' => $payload,
        );
        $response = $this->_retry(
            self::BASE_URI . '/message-api/v1/user-info/deep-link/',
            [
                'method' => 'POST',
                'body' => json_encode($body),
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $api_token,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        return $response;
    }

    /**
     * Retrieve information about a user account in the Telenote service
     *
     * Returns json {"balance": "0.00"}
     */
    function get_account_info($api_token, $project_alias)
    {
        $response = $this->_retry(
            self::BASE_URI . '/message-api/v1/user-info/?project_alias=' . $project_alias,
            [
                'method' => 'GET',
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $api_token,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        return $response;
    }
}
