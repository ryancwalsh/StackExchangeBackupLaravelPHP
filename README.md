# StackExchangeBackupLaravel
Allows you to export JSON files of your most important data (questions, answers, comments, favorites) from each of your Stack Exchange sites (Stack Overflow, Super User, Server Fault, etc).

## Installation

 1. I think this project requires PHP 7.3 or later, so be sure that your system complies.
 1. Sign up at https://stackapps.com/apps/oauth/register to receive a Client ID, Client Secret, and Key. (It's free, easy, and fast.)
 1. Create a Laravel project, and make sure that it works: https://laravel.com/docs/5.8/installation#installing-laravel
 1. Add this package into your Laravel project: `vagrant@vboxHomestead:~/Code/MyLaravelProject$ composer require ryancwalsh/stack-exchange-backup-laravel:dev-master`
 1. Run `php artisan vendor:publish`, and if it gives you a choice, choose to publish from this package.
 1. Edit your Laravel project's `.env` file to have your own StackApps values. A non-working sample is below.
 1. Run `php artisan exportStackExchange`. (Note that `php artisan exportStackExchange --flushCache` is an available option to clear the cached access code value.)
 1. Following the instructions in the terminal, you'll use your browser to visit a URL that will provide you with a temporary access token to paste into the terminal.
 1. Finished! The JSON files will appear in your `/storage/app/StackExchange` folder.

```
# These are sample .env values:
STACKAPPS_CLIENT_ID=12227
STACKAPPS_CLIENT_SECRET=ydxGSDFHDF4DtZqCesr)yJIw((
STACKAPPS_KEY=JuWsTgfG2CqjdghkhdgBkQ((
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
