<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function telenote_utils_starts_with($haystack, $needle) {
    return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
}

function telenote_utils_ends_with($haystack, $needle) {
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}

function telenote_utils_generate_random_string($length = 10) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function telenote_utils_get_link_id($user_id)
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

    $response = telenote_api_get_deep_linking_id($api_token, $project_alias, strval($user_id));

    if ($response['response']['code'] !== 200) {
        return null;
    }

    $link_info = json_decode($response['body']);
    $link_id = $link_info->link_id;

    return $link_id;
}

function telenote_utils_get_deep_link($api_token, $user_id)
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
    $response = telenote_api_get_deep_link($api_token, $project_alias, strval($user_id));
    $status_code = $response['response']['code'];

    if ($status_code !== 200 && $status_code !== 201) {
        return null;
    }

    $response_data = json_decode($response['body']);
    $deep_link = $response_data->deep_link;

    return $deep_link;
}

function telenote_utils_call_api_account_info()
{
    $api_token = get_option('telenote_api_token');
    
    if (empty($api_token)) {
        return null;
    }

    $project_alias = get_option('telenote_project_alias');

    if (empty($project_alias)) {
        return null;
    }

    $response = telenote_api_get_account_info($api_token, $project_alias);

    return $response;
}

function telenote_utils_get_account_info()
{
    $response = telenote_utils_call_api_account_info();

    if ($response['response']['code'] !== 200) {
        return null;
    }

    $body = json_decode($response['body']);
    $account_info = array(
        'balance' => $body->balance,
    );

    return $account_info;
}

function telenote_utils_get_balance()
{
    $account_info = telenote_utils_get_account_info();
    
    if (empty($account_info)) {
        return '';
    }

    return $account_info['balance'];
}
