<?php

namespace ryancwalsh\StackExchangeBackupLaravel;

use Cache;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExportStackExchangeCommand extends Command {

    const CODE_CACHE_KEY = 'stackAppsCode';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exportStackExchange {--code=} {--flushCache}';

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
        if ($this->option('flushCache')) {
            Cache::flush();
        }
        $code = $this->option('code') ?? Cache::get(self::CODE_CACHE_KEY);
        if (!$code) {
            $url = $this->exportStackExchangeHelper->getOauthUrl();
            $this->info('Visit ' . $url);
            $code = $this->ask('Then, the browser will bounce to a new URL. Copy the value of the `code` parameter in the URL. Paste it here:');
            Cache::put(self::CODE_CACHE_KEY, $code, $this->exportStackExchangeHelper::SESSION_MINS);
            Cache::forget($this->exportStackExchangeHelper::ACCESS_TOKEN_CACHE_KEY);
            $this->info('Code now saved to cache for ' . $this->exportStackExchangeHelper::SESSION_MINS . ' minutes: ' . Cache::get(self::CODE_CACHE_KEY));
        }
        $this->exportStackExchangeHelper->setCode($code);
        $this->info(Carbon::now() . ' Starting.');
        $this->exportStackExchangeHelper->setFilenamePrefix('StackExchange/' . Carbon::now()->format(\App\Helpers\ExtraTools::FILENAME_SAFE_FORMAT) . '/');
        $mySites = $this->exportStackExchangeHelper->getMyAssociatedSites();
        $this->showListOfMySites($mySites);
        $this->info('===');
        $this->exportEachSite($mySites);
        $this->info(Carbon::now() . ' Finished.');
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
        foreach ($mySites as $site) {
            $this->showDetailsOfThisSite($site);
            $sitesToExclude = []; //['Stack Overflow', 'Server Fault', 'Super User'];
            if (!in_array($site['site_name'], $sitesToExclude)) {
                foreach (['answers' => 'activity', 'questions' => 'activity', 'comments' => 'creation', 'mentioned' => 'creation', 'favorites' => 'creation'] as $endpoint => $sort) {
                    $this->exportStackExchangeHelper->saveJsonFromApi($endpoint, $site, $sort);
                }
            }
        }
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
