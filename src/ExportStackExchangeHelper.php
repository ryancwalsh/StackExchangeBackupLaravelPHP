<?php

namespace ryancwalsh\StackExchangeBackupLaravel;

use Cache;
use GuzzleHttp\Client as HttpClient;
use Log;
use Storage;

class ExportStackExchangeHelper {

    const API_URL = 'https://api.stackexchange.com'; #https://api.stackexchange.com/docs
    const SESSION_MINS = 60;
    const ACCESS_TOKEN_CACHE_KEY = 'stackapps_access_token';

    protected $client_id;
    protected $client_secret;
    protected $code;
    protected $filename_prefix;
    protected $key;
    protected $redirect_uri;
    protected $version_prefix;

    public function __construct() {
        $this->key = config('stackapps.key');
        $this->version_prefix = config('stackapps.version_prefix');
        $this->client_id = config('stackapps.client_id');
        $this->redirect_uri = config('stackapps.redirect_uri');
        $this->client_secret = config('stackapps.client_secret');
    }

    public function setFilenamePrefix($filename_prefix) {
        $this->filename_prefix = $filename_prefix;
        return $this;
    }

    public function setCode($code) {
        $this->code = $code;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getOauthUrl() {
        return 'https://stackoverflow.com/oauth?' . http_build_query(['client_id' => $this->client_id, 'redirect_uri' => $this->redirect_uri, 'scope' => 'private_info']); //https://api.stackexchange.com/docs/authentication
    }

    /**
     * 
     * @return string
     */
    public function getAccessToken() {
        $accessTokenResponseJsonString = Cache::remember(self::ACCESS_TOKEN_CACHE_KEY, self::SESSION_MINS, function () {//https://laravel.com/docs/5.6/cache#retrieving-items-from-the-cache
                    $url = 'https://stackoverflow.com/oauth/access_token/json'; //https://api.stackexchange.com/docs/authentication
                    $payload = [
                        'form_params' => [
                            'client_id' => $this->client_id,
                            'client_secret' => $this->client_secret,
                            'code' => $this->code,
                            'redirect_uri' => $this->redirect_uri
                        ]
                    ]; //https://stackoverflow.com/a/34411797/470749
                    //Log::debug('payload for getAccessToken: ' . json_encode($payload));
                    $httpClient = new HttpClient();
                    $response = $httpClient->post($url, $payload);
                    $accessTokenResponseJsonString = $response->getBody()->getContents();
                    //Log::debug('$accessTokenResponseJsonString = ' . $accessTokenResponseJsonString);
                    return $accessTokenResponseJsonString;
                });
        $accessTokenArray = json_decode($accessTokenResponseJsonString, true);
        return $accessTokenArray['access_token'];
    }

    /**
     * 
     * @param string $uri
     * @param array $options
     * @param int $cacheMinutes
     * @return string
     */
    public function get($uri = '', array $options = [], $cacheMinutes = self::SESSION_MINS) {
        $cacheKey = sha1($uri . json_encode($options));
        $response = Cache::remember($cacheKey, $cacheMinutes, function () use ($uri, $options) {//https://laravel.com/docs/5.6/cache#retrieving-items-from-the-cache                    
                    $params = array_merge($options, ['code' => $this->code, 'access_token' => $this->getAccessToken(), 'key' => $this->key]);
                    //Log::debug(json_encode($params));
                    $fullUrl = self::API_URL . $this->version_prefix . $uri . '?' . http_build_query($params);
                    //Log::debug($fullUrl);
                    $httpClient = new HttpClient();
                    $response = $httpClient->request('get', $fullUrl);
                    return $response->getBody()->getContents();
                });
        return $response;
    }

    /**
     * 
     * @return array
     */
    public function getMyAssociatedSites() {
        //https://api.stackexchange.com/docs/sites ( https://api.stackexchange.com/docs/types/site )
        $responseJson = $this->get('/me/associated', ['pagesize' => 100], 60 * 12); //https://api.stackexchange.com/docs/me-associated-users
        $this->saveToStorage('my_sites.json', json_decode($responseJson, true));
        $mySites = json_decode($responseJson, true);
        return $mySites['items'];
    }

    /**
     * 
     * @param string $endpoint
     * @param string $site
     * @param string $sort
     */
    public function saveJsonFromApi($endpoint, $site, $sort) {
        $site_param = $this->getSiteApiParam($site);
        $page = 0;
        do {
            $page++;
            $responseJson = $this->get('/me/' . $endpoint, [
                'site' => $site_param,
                'pagesize' => 100,
                'page' => $page,
                'sort' => $sort,
                'order' => 'desc',
                'filter' => 'withbody'//https://api.stackexchange.com/docs/filters
                    ], 60);
            //Log::debug($responseJson);
            $filename = $this->clean($site['site_name']) . '/' . $endpoint . '/page_' . str_pad($page, 4, '0', STR_PAD_LEFT) . '.json';
            $this->saveToStorage($filename, json_decode($responseJson, true));
            $responseArray = json_decode($responseJson, true);
        } while ($responseArray['has_more']); //https://api.stackexchange.com/docs/paging
    }

    /**
     * Each of these methods operates on a single site at a time, identified by the site parameter. This parameter can be the full domain name (ie. "stackoverflow.com"), or a short form identified by api_site_parameter on the site object.
     * 
     * @param array $site
     * @return string
     */
    public function getSiteApiParam($site) {
        $urlDetails = parse_url($site['site_url']); //http://php.net/manual/en/function.parse-url.php
        return $site['api_site_parameter'] ?? $urlDetails['host'];
    }

    /**
     * 
     * @param string $filename
     * @param string $data
     */
    public function saveToStorage($filename, $data) {
        $filename = $this->filename_prefix . $filename;
        Storage::disk('local')->put($filename, json_encode($data));
        //Storage::disk('s3')->put($filename, json_encode($data));//TODO: Save to AWS S3
    }

    /**
     * 
     * @param string $siteName
     * @return string
     */
    public function clean($siteName) {
        $slightlyCleanerStr = str_replace([' Stack Exchange', '&amp;', ' '], ['', 'and', '_'], $siteName);
        return preg_replace('/[^A-Za-z0-9\-\_]/', '', $slightlyCleanerStr); //https://stackoverflow.com/a/14114419/470749
    }

}
