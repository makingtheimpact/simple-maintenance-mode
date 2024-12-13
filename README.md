# Simple-Maintenance-Mode
Simple WordPress plugin to hide the website from the public while it is being worked on or built.

## Description
This lightweight plugin makes it easy make your website hidden from public access using a page redirect that takes the user to a page of your choosing. 

You can choose from 3 modes: 
1. Online (default)
2. Coming Soon - for new websites that are under construction
3. Maintenance Mode - for existing websites undergoing changes

### Features: 
* Unique preview link - view the website while hidden from public
* Page selector - choose the page you want users to be redirected to or display default content
* Copyable bypass URL - copy the URL to the clipboard so you can easily share it with others
* 12 hour cookie expiration - the bypass URL will create a cookie on the device that expires after 12 hours. So long as the unique key remains unchanged, the link can be used repeatedly, each time setting a cookie with the 12 hour expiration. 
* Countdown timer - display a countdown timer to the date you want the website to be live
* Background images and customizable coming soon/maintenance mode page
* Preview coming soon/maintenance mode page

### How It Works: 
**Online Mode:** When in online mode, the website is fully accessible. 

**Coming Soon Mode:** When set to Coming Soon, the website becomes hidden from the public. They will not be able to access any of the pages of the website, except the one that you specify them to be redirected to in the settings. 

**Maintenance Mode:** Much like Coming Soon mode, in maintenance mode, the website is hidden and inaccessible to the public. Users will be redirected to a page that you specify in the settings. 

While in the Coming Soon or Maintenance modes, the login page will remain accessible so that admins can still access the website. 

If you or another user want to preview the website without logging in as an administrator, you can use the link the plugin generates that has a unique key that lets you bypass the redirect and access the website normally. 

The special link creates a cookie on the device that expires after 12 hours. So long as the unique key remains unchanged, the link can be used repeatedly, each time setting a cookie with the 12 hour expiration. 

## Installation
This plugin is only compatible with WordPress. To install it on your WordPress website, follow the directions below:

1. Upload `simple-maintenance-mode` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure the plugin settings as needed.

## Frequently Asked Questions
**It won't let me access the login page. What do I do?**
In some cases, if you have a unique setup where the login is different from the default, it may create a situation where the login page is no longer accessible. 

If this happens to you, you can use FTP or access the file manager of the hosting to rename the directory for this plugin and it should disable it. Failing that, you can try deleting it. 

You can submit an issue on GitHub or contact me with details about the login page setup you have so that it can be fixed in the next update. 

**I disabled the coming soon/maintenance mode, but the website is still redirecting (or vice versa). What do I do?**
Chances are this issue has to do with caching. If you have a caching plugin on your website, be sure to clear the cache each time you change the mode. 

If the issue is isolated to one or a few users, the issue is likely that the browser has cached the files. Try clearing the browser's cache and accessing the website again. If the issue persists, try another web browser or device and see if the behavior continues. 

If problems persist, you can report the issue on GitHub or contact me.

## Screenshots
Coming soon...

## Changelog

### 1.0.2
- Added copyable bypass URL
- Added 12 hour cookie expiration
- Added countdown timer
- Added background images and customizable coming soon/maintenance mode page

### 1.0.1
- Bug fixes and improvements

### 1.0.0
- Initial release of the plugin.

## Upgrade Notice

### 1.0.2
- Improved version of the plugin with more features and bug fixes.

### 1.0.1
- Bug fixes and improvements.

### 1.0.0
- First release of the plugin, no upgrade notices.

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License
This plugin is open-sourced software licensed under the [GPLv2](https://www.gnu.org/licenses/gpl-2.0.html) license.