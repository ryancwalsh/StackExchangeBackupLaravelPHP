<?php

namespace ryancwalsh\StackExchangeBackupLaravel;

use Cache;
use GuzzleHttp\Client as HttpClient;
use Log;
use Storage;

class ExportStackExchangeHelper {

    const API_URL = 'https://api.stackexchange.com'; #https://api.stackexchange.com/docs
    const SESSION_SECONDS = 60 * 60;
    const ACCESS_TOKEN_CACHE_KEY = 'stackapps_access_token';
    const APP_FOLDER = 'app/';
    const SE_FOLDER = 'StackExchange/';
    const DOT_ZIP = '.zip';

    protected $client_id;
    protected $client_secret;
    protected $code;
    protected $key;
    protected $moment_string;
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
    public function getAccessTokenJson() {
        $accessTokenResponseJsonString = Cache::remember(self::ACCESS_TOKEN_CACHE_KEY, self::SESSION_SECONDS, function () {//https://laravel.com/docs/5.6/cache#retrieving-items-from-the-cache
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
        //Log::debug($accessTokenResponseJsonString);
        return $accessTokenResponseJsonString;
    }

    /**
     * @param string $accessTokenResponseJsonString
     * @return string
     */
    public function getAccessTokenFromJson($accessTokenResponseJsonString) {
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
    public function get($uri = '', array $options = [], $cacheMinutes = self::SESSION_SECONDS) {
        $cacheKey = sha1($uri . json_encode($options));
        $response = Cache::remember($cacheKey, $cacheMinutes, function () use ($uri, $options) {//https://laravel.com/docs/5.6/cache#retrieving-items-from-the-cache      
                    $accessTokenResponseJsonString = $this->getAccessTokenJson();
                    $accessToken = $this->getAccessTokenFromJson($accessTokenResponseJsonString);
                    $params = array_merge($options, ['code' => $this->code, 'access_token' => $accessToken, 'key' => $this->key]);
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
        Log::debug('getMyAssociatedSites');
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
            $responseJson = $this->getJsonFromApi($endpoint, $site_param, $page, $sort);
            $filename = $this->clean($site['site_name']) . '/' . $endpoint . '/page_' . str_pad($page, 4, '0', STR_PAD_LEFT) . '.json';
            $this->saveToStorage($filename, json_decode($responseJson, true));
            $responseArray = json_decode($responseJson, true);
        } while ($responseArray['has_more']); //https://api.stackexchange.com/docs/paging
    }

    /**
     * 
     * @param string $endpoint
     * @param string $site_param
     * @param int $page
     * @param string $sort
     * @return string
     */
    public function getJsonFromApi($endpoint, $site_param, $page, $sort) {
        $responseJson = retry(2, function () use ($endpoint, $site_param, $page, $sort) {//https://laravel.com/docs/6.0/helpers#method-retry
            $options = [
                'site' => $site_param,
                'pagesize' => 100,
                'page' => $page,
                'sort' => $sort,
                'order' => 'desc',
                'filter' => 'withbody'//https://api.stackexchange.com/docs/filters
            ];
            return $this->get('/me/' . $endpoint, $options, 60);
        }, 100); //see https://github.com/ryancwalsh/StackExchangeBackupLaravelPHP/issues/11
        //Log::debug($responseJson);
        return $responseJson;
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
        $fullFilename = self::SE_FOLDER . $this->getMomentString() . '/' . $filename;
        Storage::disk('local')->put($fullFilename, json_encode($data));
        $this->writeToOuput('Saved to ' . $fullFilename);
    }

    /**
     * After exportEachSite, zip all the json files, and copy that json file to S3
     */
    public function archiveToS3() {
        $this->writeToOuput(__FUNCTION__);
        $this->zipTheJsonFiles();
        $this->copyZipFileToS3();
    }

    /**
     * @return string
     */
    public function getMomentZipFileName() {
        return $this->getMomentString() . self::DOT_ZIP;
    }

    /**
     * @return string
     */
    public function getZipFileNameInStoragePathFolder() {
        return storage_path(self::APP_FOLDER . self::SE_FOLDER . $this->getMomentZipFileName());
    }

    protected function zipTheJsonFiles() {
        $this->writeToOuput(__FUNCTION__);
        $this->createZipFileFromFolder(storage_path(self::APP_FOLDER . self::SE_FOLDER . $this->getMomentString()), $this->getZipFileNameInStoragePathFolder());
    }

    protected function copyZipFileToS3() {
        $remoteFilename = self::SE_FOLDER . $this->getMomentZipFileName();
        $this->writeToOuput(__FUNCTION__ . ' ' . $remoteFilename);
        $localZipFile = $this->getZipFileNameInStoragePathFolder();
        $data = file_get_contents($localZipFile);
        try {
            Storage::disk('s3')->put($remoteFilename, $data);
            $this->writeToOuput('Saved to AWS S3' . $remoteFilename);
        } catch (\Exception $e) {
            $error = 'There was a problem trying to write to AWS S3.';
            $this->writeErrorToOuput('ERROR: ' . $error . ' Check the logs. ' . $remoteFilename);
            Log::error($error . ' ' . $e);
        }
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
        Log::debug($text);
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

    /**
     * @see https://stackoverflow.com/a/1334949/470749
     * 
     * @param string $folderPath
     * @param string $destinationZipFileName
     * @return bool
     * @throws \Exception
     */
    public function createZipFileFromFolder($folderPath, $destinationZipFileName) {
        $this->writeToOuput(__FUNCTION__ . ': ' . $folderPath . ' to ' . $destinationZipFileName);
        if (!extension_loaded('zip') || !file_exists($folderPath)) {
            throw new \Exception(__FUNCTION__ . ' could not start.');
        }

        $zip = new \ZipArchive();
        if (!$zip->open($destinationZipFileName, \ZIPARCHIVE::CREATE)) {//be careful if using OVERWRITE as opposed to CREATE
            throw new \Exception(__FUNCTION__ . ' could not begin writing zip file.');
        }

        $folderPathCleaned = str_replace('\\', '/', realpath($folderPath));

        if (is_dir($folderPathCleaned) === true) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folderPathCleaned), \RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $fileSlashesFixed = str_replace('\\', '/', $file);

                // Ignore "." and ".." folders
                if (in_array(substr($fileSlashesFixed, strrpos($fileSlashesFixed, '/') + 1), array('.', '..')))
                    continue;

                $fileRealPath = realpath($fileSlashesFixed);

                if (is_dir($fileRealPath) === true) {
                    $zip->addEmptyDir(str_replace($folderPathCleaned . '/', '', $fileRealPath . '/'));
                } else if (is_file($fileRealPath) === true) {
                    $zip->addFromString(str_replace($folderPathCleaned . '/', '', $fileRealPath), file_get_contents($fileRealPath));
                }
            }
        } else if (is_file($folderPathCleaned) === true) {
            $zip->addFromString(basename($folderPathCleaned), file_get_contents($folderPathCleaned));
        }

        return $zip->close();
    }

    /**
     * 
     * @return string
     */
    public function getMomentString() {
        return $this->moment_string;
    }

    /**
     * 
     * @param string $moment_string
     * @return $this
     */
    public function setMomentString($moment_string) {
        $this->moment_string = $moment_string;
        return $this;
    }

}
