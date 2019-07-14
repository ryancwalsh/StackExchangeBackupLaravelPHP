# StackExchangeBackupLaravel

Allows you to export JSON files of your most important data (questions, answers, comments, favorites) from each of your Stack Exchange sites (Stack Overflow, Super User, Server Fault, etc).

## Installation

There is no need to clone this repository, the installation works with _PHP composer_. It will install the _Laravel_ framework and add this project to the app.

 1. This is only tested on PHP 7.3, so be sure that your system complies.
 1. Sign up at https://stackapps.com/apps/oauth/register to receive a Client ID, Client Secret, and Key (it's free, easy, and fast). Note: use `stackexchange.com` for `OAuth Domain` in the setup.
 1. Create a [Laravel project](https://laravel.com/docs/5.8/installation#installing-laravel) for this app, and make sure that it works:
        
        mkdir ~/Code/Laravel
        cd ~/Code/Laravel
        composer global require laravel/installer
        # on Ubuntu 18.04 and Debian 9+:
        echo 'export PATH="$PATH:$HOME/.config/composer/vendor/bin"' >> ~/.bashrc
        # on other systems this might be
        # echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc
        # apply PATH:
        source ~/.bashrc
        # create the laravel project for this app:
        laravel new se_backup_project
 1. Add this package into your Laravel project in the parent folder of the app folder:
        
        cd se_backup_project/
        composer require ryancwalsh/stack-exchange-backup-laravel:dev-master --prefer-source
 1. Run _artisan_, and choose to publish the "Provider" from this package: `ryancwalsh\StackExchangeBackupLaravel\ExportStackExchangeServiceProvider`
        
        php artisan vendor:publish
 1. Edit your Laravel project's `.env` file to have your own StackApps values. A non-working sample is below.
 1. Run
 
        php artisan exportStackExchange
    (Note that `php artisan exportStackExchange --flushCache` is the option to clear the cached access codevalue.)
 1. Following the instructions in the terminal, you'll use your browser to visit a URL that will provide you with a temporary access token to paste into the terminal.  
    (Note: you'll find the token in the `code` part of the URL of the page showing "Authorizing Application" like `https://stackexchange.com/oauth/login_success?code=TLBxyz...`)
 1. Finished! The JSON files will appear in your `/storage/app/StackExchange` folder.

```
# These are sample .env values:
STACKAPPS_CLIENT_ID=<your Client Id>
STACKAPPS_CLIENT_SECRET=<your Client Secret>
STACKAPPS_KEY=<your Key>
# optional, in case you want to use AWS:
AWS_ACCESS_KEY_ID=AKIAmb3mbn56mn6
AWS_SECRET_ACCESS_KEY=jl234k5jl23k45j23lj5
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=xyz
```

## If You Use This Package, Let Me Know!

**This is the first package that I've ever made, and I'm super curious if anyone will ever use it. If you do try it out, I'd love for you to [open an issue](https://github.com/ryancwalsh/StackExchangeBackupLaravelPHP/issues/new) to say hi** (and of course to tell me any suggestions you have).

___

### If You Want To Get Creative...

Visit https://api.stackexchange.com/docs to read the docs, and you can modify `ExportStackExchangeHelper.php` to do whatever you want. The Stack Exchange API is great.

### Other Resources That Helped Me

 - I made this README.md file using https://stackedit.io/app.
 - https://help.github.com/articles/splitting-a-subfolder-out-into-a-new-repository/
