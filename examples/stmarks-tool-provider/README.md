# Tool provider

### An example using `StMarksReflexiveCanvasLTI`

This example sets up a simple Tool Provider that can be added as an external app in Canvas. When accessed via the course or account navigation menus, the Tool Provider will display the current user's profile (as provided via the Canvas API).

### Quick Start

  1. Run `composer install` in this directory to load dependencies. ([Composer](http://getcomposer.org) is awesome!)
  2. Create `config.xml` based on `config.example.xml` to provide your own MySQL and Canvas API credentials.
  3. Point your browser at `https://your-server/path-to-this-directory?action=install` (Yes, it needs to be an SSL connection -- Canvas gets grumpy if it isn't. Check out [Let's Encrypt](http://letsencrypt.org) for a free SSL certificate).
  4. Install the app in Canvas "by URL", using the provided key, shared secret and configuration XML URL.
  5. Uncomment the `RedirectMatch 404 /consumers.php` line in .htaccess to block future (undesired) access to consumers.php.

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

Canvas API authentication credentials. While these could be generated dynamically, you can also [generate them manually](https://community.canvaslms.com/docs/DOC-3013). While the simple [tool-provider](https://github.com/smtech/reflexive-canvas-lti/tree/master/examples/tool-provider) example uses a specific API URL and token, this example demonstrates the use of a Canvas Developer key and secret to facilitate OAuth authentication during the install process to acquire an API Token for this app.

### App Structure

###### `common.inc.php`

Loads composer autoloader, starts the PHP session (more on that below!) and preconfigures some reasonable defaults for the Smarty templating system.

###### `consumers.php`

A handy external endpoint to trigger the interactive consumers control panel built into `smtech/stmarks-reflexive-canvas-lti`. Also easy to lock up by uncommenting `RedirectMatch 404 /consumers.php` in `.htaccess` (makes the file look like a 404 error).

###### `index.php`

The brains of the operation ("Wot'll we do tonight, Brain?"). Determines if access is via unauthenticated direct web browsing (e.g. installation, consumer configuration) or LTI-authenticated and handles session management and intialization. Processes `action` parameter to generate configuration XML for LTI installation, tool provider installation, etc. Displays the `home.tpl` template if LTI-authenticated to show user profile.
