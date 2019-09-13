<?php

namespace ryancwalsh\StackExchangeBackupLaravel;

use Cache;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;

class ExportStackExchangeCommand extends Command {

    const CODE_CACHE_KEY = 'stackAppsCode';
    const ENDPOINTS = ['answers' => 'activity', 'questions' => 'activity', 'comments' => 'creation', 'mentioned' => 'creation', 'favorites' => 'creation'];
    const FILENAME_SAFE_FORMAT = "Y-m-d_His_T";
    const SE_URL_BEGINNING = 'https://stackexchange.com/oauth/login_success?code=';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exportStackExchange {--code=} {--S3=true} {--forgetCache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Saves my most important StackExchange data for backup purposes';
    protected $exportStackExchangeHelper;

    public function __construct() {
        parent::__construct();
        $this->exportStackExchangeHelper = new ExportStackExchangeHelper();
    }

    /**
     * Execute the console command.
     *
     * @return array
     */
    public function handle() {
        $this->exportStackExchangeHelper->setConsoleOutput($this->getOutput());
        $this->handleForgetCacheOption();
        try {
            $this->handleStackExchangeCode();
            $this->info(Carbon::now() . ' Starting.');
            $this->exportStackExchangeHelper->setMomentString(Carbon::now()->format(self::FILENAME_SAFE_FORMAT));
            $mySites = $this->exportStackExchangeHelper->getMyAssociatedSites();
            $this->showListOfMySites($mySites);
            $this->info('===');
            $this->exportEachSite($mySites);
            if ($this->option('S3') === 'true') {//https://stackoverflow.com/a/7336873/470749
                $this->exportStackExchangeHelper->archiveToS3();
            } else {
                $this->info('(Skipping S3 upload due to S3=false option.)');
            }
        } catch (\Exception $e) {
            Log::error(__CLASS__ . ' ' . __FUNCTION__ . ' ' . $e);
            $this->error('Error. Check the Laravel log (probably at /storage/logs/laravel.log).');
        }
        $this->info(Carbon::now() . ' Finished.');
    }

    public function handleForgetCacheOption() {
        if ($this->option('forgetCache')) {
            Cache::forget(self::CODE_CACHE_KEY);
            Cache::forget($this->exportStackExchangeHelper::ACCESS_TOKEN_CACHE_KEY);
            $this->info('Cleared cache values.');
        }
    }

    public function handleStackExchangeCode() {
        $code = $this->option('code') ?? Cache::get(self::CODE_CACHE_KEY);
        if (!$code) {//If code wasn't provided via option and wasn't available from cache, explain the process for fetching a code in the browser.
            $url = $this->exportStackExchangeHelper->getOauthUrl();
            $this->info('Visit ' . $url);
            $codePasted = $this->ask('Then, the browser will bounce to a new URL. Copy the value of the `code` parameter in the URL. Paste it here:');
            $code = str_replace(self::SE_URL_BEGINNING, '', $codePasted); //just in case the user accidentally pasted the entire URL instead of just the `code` parameter.
            Log::debug('$code=' . $code);
            Cache::put(self::CODE_CACHE_KEY, $code, $this->exportStackExchangeHelper::SESSION_SECONDS);
            Cache::forget($this->exportStackExchangeHelper::ACCESS_TOKEN_CACHE_KEY);
            $this->info('Code now saved to cache for ' . $this->exportStackExchangeHelper::SESSION_SECONDS . ' minutes: ' . Cache::get(self::CODE_CACHE_KEY));
        }
        $this->exportStackExchangeHelper->setCode($code);
    }

    /**
     * Saves the JSON from each of these endpoints:
      https://api.stackexchange.com/docs/me-answers
      https://api.stackexchange.com/docs/me-questions
      https://api.stackexchange.com/docs/me-comments
      https://api.stackexchange.com/docs/me-mentioned
      https://api.stackexchange.com/docs/me-favorites
     * 
     * @param array $mySites
     */
    public function exportEachSite($mySites) {
        $bar = $this->output->createProgressBar(count($mySites)); //https://laravel.com/docs/6.0/artisan#writing-output
        $bar->start();
        foreach ($mySites as $site) {
            $this->showDetailsOfThisSite($site);
            $sitesToExclude = []; //['Stack Overflow', 'Server Fault', 'Super User'];
            if (!in_array($site['site_name'], $sitesToExclude)) {            
                foreach (self::ENDPOINTS as $endpoint => $sort) {
                    $this->exportStackExchangeHelper->saveJsonFromApi($endpoint, $site, $sort);
                }
            }
            $bar->advance();
        }
        $bar->finish();
    }

    /**
     * Displays a list of all the sites that will be scraped so that the user knows what sites have been found.
     * 
     * @param array $mySites
     */
    public function showListOfMySites($mySites) {
        foreach ($mySites as $site) {
            $this->showDetailsOfThisSite($site);
        }
    }

    /**
     * 
     * @param array $site
     */
    public function showDetailsOfThisSite($site) {
        $this->info(str_pad($site['site_name'], 70, ' ') . ' ' . str_pad($this->exportStackExchangeHelper->clean($site['site_name']), 40, ' ') . ' ' . $site['site_url']);
    }

}
