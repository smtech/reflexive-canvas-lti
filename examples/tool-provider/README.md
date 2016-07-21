# Tool provider

### An example using `ReflexiveCanvasLTI`

This example sets up a simple Tool Provider that can be added as an external app in Canvas. When accessed via the course navigation menu, the Tool Provider will display the current user's profile (as provided via the Canvas API).

### Quick Start

  1. Run `composer install` in this directory to load dependencies. ([Composer](http://getcomposer.org) is awesome!)
  2. Create `config.xml` based on `config.example.xml` to provide your own MySQL and Canvas API credentials.
  3. Point your browser at `https://your-server/path-to-this-directory?action=reset` (Yes, it needs to be an SSL connection -- Canvas gets grumpy if it isn't. Check out [Let's Encrypt](http://letsencrypt.org) for a free SSL certificate).
  4. Install the app in Canvas "by URL", using the provided key, shared secret and configuration XML URL.

### LTI Configuration via `config.xml`

The `config.example.xml` file in this directory (see below for more details on other files) contains the basic information required for the LTI Tool Provider to function. The app actually refers to `config.xml` where you will, presumably, store your _real_ credentials.

###### `tool`

This section of the document describes the functioning of the tool itself.

  - `name`, `id`, `description` should be obvious. (Ideally, the ID is globally unique, BTW.)
  - `authenticate` is the path (relative to `config.xml`) that will handle authenticating tool consumers.
  - `handlers` is a list of request-type/script-handler pairings. At least one of `base`, `launch`, `content-item`, `configure`, `dashboard`, `error` must be provided (and `base` will be automagically generated based on the first provided pairing, if it is not provided). `launch` is the request-type for apps _launched_ from course or account navigation and displayed within an `iframe`.
  - `log` path (relative to `config.xml`) of a log file for this tool.

###### `mysql`

MySQL authentication credentials.

###### `canvas`

Canvas API authentication credentials. While these could be generated dynamically, you can also [generate them manually](https://community.canvaslms.com/docs/DOC-3013).

### App Structure

I've made some attempt to modularize the structure of this example marginally clearer, while simultaneously trying to limit dependence on any other packages.

  - `.htaccess` blocks browser access to a variety of file extensions that the hoi polloi should not be accessing.
  - `common.inc.php` is the preamble to all of the script files (i.e. it loads the Composer autoloader).
  - `composer.json` describes the dependencies of this (example) project for [Composer](http://getcomposer.org). For the example to run, you either need to tweak `common.inc.php` to load _your_ Composer autoloader, or you should run `composer install` in this directory to load the required dependencies.
  - `config.xml` contains configuration and credentials for the tool provider (our app). Of particular note are the need for a valid MySQL login and a valid Canvas API URL and API Token.
  - `index.php` manages LTI Tool Provider overhead, including authenticating tool consumers, as well as providing other handy functions via a variety of values to the GET `action` parameter:
    - `index.php?action=reset` resets the LTI Tool Provider configuration from the current contents of `config.xml`
    - `index.php?action=config` provides a simple XML configuration file for use by Canvas an LTI Tool Consumer to create a "placement" of our Tool Provider... oy. Y'know when you go to install a new LTI app and you can do it by URL? _This_ is the URL that you use, along with the key and shared secret.
  - `tool.php` is the actual "app" -- in this case, all it does is look up the current user's profile via the Canvas API and then dump it to the screen.
  - 'LICENSE.md' and 'README.md' should be self-evident, since you're reading this.
  - `actions/` are snippets of PHP to perform discrete actions
    - `common.inc.php` is, again, the preamble that loads the Composer autoloader.
    - `config.inc.php` displays the LTI Tool Provider Configuration XML (for use by Tool Consumers during the installation process).
    - `launch.inc.php` routes initial authentication requests from the Tool Consumer into the `ReflexiveCanvasLTI` authentication engine (really a thin wrapper for Steven Vickers' _very convenient_ `LTI_Tool_Provider`).
    - `reset.inc.php` resets the configuration of the `Toolbox` from `config.xml`, recaching data
  - `templates/` are rudimentary templates for generating HTML:
    - `common.inc.php` is, again, the preamble that loads the Composer autoloader.
    - `error.inc.php` displays errors.
    - `reset-summary.inc.php` displays connection credentials for configuring a placement in Canvas
    - `user-profile.inc.php` looks up the current user's profile via the Canvas API.
