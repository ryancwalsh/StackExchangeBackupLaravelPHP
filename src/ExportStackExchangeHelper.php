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

    /**
     *
     * @var \Symfony\Component\Console\Style\OutputStyle 
     */
    protected $consoleOutput;

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
     * @param \Symfony\Component\Console\Style\OutputStyle $consoleOutput
     * @return $this
     */
    public function setConsoleOutput($consoleOutput) {
        $this->consoleOutput = $consoleOutput;
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
            $responseArray = json_decode($responseJson, true);
            $this->saveToStorage($filename, $responseArray);
            foreach($responseArray['items'] as $item){
              $url='';
              if(!isset($item['question_id'])){
                if($endpoint=="comments"){
                  $url=$site["site_url"].'/a/'.$item['post_id'];
                  #$this->writeToOuput($endpoint.": ".$url);
                } else if($endpoint=="mentioned"){
                  # mentions are not needed, cause they are usually replies to own q,a or comments
                  # $this->writeToOuput($endpoint.": ".$site["site_url"].'/a/'.$item['post_id']);
                } else {
                  #$this->writeToOuput("$endpoint has no question id and no post_id\n"; var_dump($filename));;die
                }
              }else{
                $url=$site["site_url"].'/questions/'.$item['question_id'];
                # $this->writeToOuput($endpoint.": ".$url);
              }
              if($url){
                $decodedURL=$this->doShortURLDecode($url);
                $this->appendToStorage("urls.html", '<a href="'.$decodedURL.'">'.$url.'</a>');
                echo '.';
                sleep(1); // otherwise, you get rate limited on SE
                // $this->writeToOuput('saved '.$decodedURL);
              }
            }
        } while ($responseArray['has_more']); //https://api.stackexchange.com/docs/paging
    }

    /**
     * reads the final URL for redirects
     * @param  string $url short URL
     * @return string      final URL
     */
    public function doShortURLDecode($url) {
        $ch = @curl_init($url);
        @curl_setopt($ch, CURLOPT_HEADER, TRUE);
        @curl_setopt($ch, CURLOPT_NOBODY, TRUE);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $response = @curl_exec($ch);
        // clean the response of any strange special escape characters, that can occur in the curl output:
        $cleanresponse= preg_replace('/[^A-Za-z0-9\- _,.:\n\/]/', '', $response);
        preg_match('/Location: (.*)[\n\r]/', $cleanresponse, $a);
        if (!isset($a[1])) {
          echo '-';
          return $url;
        }
        return parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).$a[1];
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
        Log::debug('saveToStorage ' . $filename);
        $filename = $this->filename_prefix . $filename;
        Storage::disk('local')->put($filename, json_encode($data));
        $this->writeToOuput("\nSaved to " . $filename);
        echo "resolving URLS";
        try {
            Storage::disk('s3')->put($filename, json_encode($data));
            $this->writeToOuput('Saved to AWS S3' . $filename);
        } catch (\Exception $e) {
            $error = 'There was a problem trying to write to AWS S3.';
            $this->writeErrorToOuput('ERROR: ' . $error . ' Check the logs. ' . $filename);
            Log::error($error . ' ' . $e);
        }
    }

    /**
     * appends plain data to a file
     * @param  string $filename
     * @param  string $data
     */
    public function appendToStorage($filename, $data) {
        $filename = $this->filename_prefix . $filename;
        Storage::disk('local')->append($filename, $data);
        #$this->writeToOuput('Added '.$data.' to ' . $filename);
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

    /**
     * 
     * @param string $text
     * @return $this
     */
    public function writeToOuput($text) {
        if ($this->consoleOutput) {
            $this->consoleOutput->writeln($text);
        }
        return $this;
    }

    /**
     * 
     * @param string $text
     * @return $this
     */
    public function writeErrorToOuput($text) {
        if ($this->consoleOutput) {
            $style = new \Symfony\Component\Console\Formatter\OutputFormatterStyle('white', 'red', ['bold']); //white text on red background
            $this->consoleOutput->getFormatter()->setStyle('error', $style);
            $this->consoleOutput->writeln('<error>' . $text . '</error>');
        }
        return $this;
    }

}
