<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$BASE_URI = 'https://api.telenote.site';
$USER_AGENT = 'telenote_wp/1.0';

function _get_client()
{
    return new WP_Http();
}

function _retry($url, $params)
{
    $max_retries = 5;
    $counter = 1;
    $client = _get_client();
    while($counter < $max_retries)
    {
        $response = $client->request($url, $params);
        if ($response instanceof WP_Error) {
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
 * Отправить сообщение
 * 
 * Вернет json {"message_id": "uuid4", "status": "NEW"}
 */
function telenote_api_post_message($api_token, $sid, $uid, $text)
{
    global $BASE_URI, $USER_AGENT;
    $time = strval(time());
    $payload = array(
        'sid' => $sid,
        'uid' => $uid,
        'text' => $text,
        'time' => $time,
    );
    $i_key = telenote_utils_generate_random_string(32);
    $body = array(
        'i_key' => $i_key,
        'payload' => $payload,
    );

    $response = _retry(
        $BASE_URI . '/message-api/v1/messages/',
        [
            'method' => 'POST',
            'body' => json_encode($body),
            'headers' => [
                'User-Agent' => $USER_AGENT,
                'Accept' => 'application/json',
                'Authorization' => 'Token ' . $api_token,
                'Content-Type' => 'application/json',
            ],
        ],
    );
    return $response;
}

/**
 * Получить id связи (пользователь перешел по ссылке для связвания и стартовал работу с телеграм ботом)
 * 
 * Вернет json {"link_id": "123"} или {"link_id": null} если связь не создана
 */
function telenote_api_get_deep_linking_id($api_token, $sid, $uid)
{
    global $BASE_URI, $USER_AGENT;
    $params = array(
        'sid' => $sid,
        'uid' => $uid,
    );
    $query_params = http_build_query($params);

    $response = _retry(
        $BASE_URI . '/message-api/v1/user-info/deep_linking_id/?' . $query_params,
        [
            'method' => 'GET',
            'headers' => [
                'User-Agent' => $USER_AGENT,
                'Accept' => 'application/json',
                'Authorization' => 'Token ' . $api_token,
                'Content-Type' => 'application/json',
            ],
        ],
    );

    return $response;
}

/**
 * Получить ссылку для связывания
 * 
 * Вернет json {"deep_link": "some_link"}
 */
function telenote_api_get_deep_link($api_token, $sid, $uid)
{
    global $BASE_URI, $USER_AGENT;
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
    $response = _retry(
        $BASE_URI . '/message-api/v1/user-info/deep-link/',
        [
            'method' => 'POST',
            'body' => json_encode($body),
            'headers' => [
                'User-Agent' => $USER_AGENT,
                'Accept' => 'application/json',
                'Authorization' => 'Token ' . $api_token,
                'Content-Type' => 'application/json',
            ],
        ],
    );
    return $response;
}

/**
 * Получить информацию об аккаунте пользователя в сервисе Telenote
 * 
 * Вернет json {"balance": "0.00"}
 */
function telenote_api_get_account_info($api_token, $project_alias)
{
    global $BASE_URI, $USER_AGENT;

    $response = _retry(
        $BASE_URI . '/message-api/v1/user-info/?project_alias=' . $project_alias,
        [
            'method' => 'GET',
            'headers' => [
                'User-Agent' => $USER_AGENT,
                'Accept' => 'application/json',
                'Authorization' => 'Token ' . $api_token,
                'Content-Type' => 'application/json',
            ],
        ],
    );

    return $response;
}
