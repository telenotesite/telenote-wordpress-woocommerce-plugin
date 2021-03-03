<?php
namespace telenote;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Utils
{
    function starts_with($haystack, $needle)
    {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }

    function ends_with($haystack, $needle)
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }

    function generate_random_string($length = 10)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function is_response_ok_or_created($response)
    {
        $status_code = $response['response']['code'];
        return \in_array($status_code, [\WP_Http::OK, \WP_Http::CREATED]);
    }

    function is_response_not_ok($response)
    {
        return ! $this->is_response_ok_or_created($response);
    }
}
