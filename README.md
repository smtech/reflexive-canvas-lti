# ReflexiveCanvasLTI

[![Latest Version](https://img.shields.io/packagist/v/smtech/reflexive-canvas-lti.svg)](https://packagist.org/packages/smtech/reflexive-canvas-lti)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/smtech/reflexive-canvas-lti/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/smtech/reflexive-canvas-lti/?branch=master)

LTI tool provider class that includes the \"reflexive\" reach back into Canvas via the API.

[Full documentation in repository.](https://smtech.github.io/reflexive-canvas-lti/namespaces/smtech.ReflexiveCanvasLTI.html)

This is one of two packages that are meant to facilitate and accelerate LTI development in PHP, allowing for built-in "reflexive" access to the Canvas APIs from the LTI Tool Provider.

  1. This package, `smtech/reflexive-canvas-lti` is the "plain vanilla" version. That is: it's pure object-oriented goodness with no GUI to add any idiosyncratic flavoring to the process. That makes it a touch more limited, but also (one hopes) more reusable in the grand scheme of things.
  2. The other package, [`smtech/stmarks-reflexive-canvas-lti`](https://github.com/smtech/stmarks-reflexive-canvas-lti) provides a bunch of handy GUI add-ons, including Smarty templating (via [`smtech/stmarkssmarty`](https://github.com/smtech/stmarks-bootstrapsmarty), the St. Mark's-styled extension of my generic [`battis/bootstrapsmarty`](https://github.com/battis/bootstrapsmarty) package that provides easy access to Bootstrap within Smarty templates). Additionally, this package, as it provides a GUI inherently, also provides some handy interactive control panels:
      - [Toolbox::interactiveGetAccessToken()](https://smtech.github.io/stmarks-reflexive-canvas-lti/classes/smtech.StMarksReflexiveCanvasLTI.Toolbox.html#method_interactiveGetAccessToken) is pretty much what it sounds like: it handles acquiring an API access token from Canvas interactively (providing a well-configured `config.xml`, of course).
      - [Toolbox::interactiveConsumersControlPanel()](https://smtech.github.io/stmarks-reflexive-canvas-lti/classes/smtech.StMarksReflexiveCanvasLTI.Toolbox.html#method_interactiveConsumersControlPanel) provides an interactive control panel for managing LTI tool consumer keys and secrets.

## How to choose?

It comes down to whether you like building web pages the way I like building web pages (striving for real Model-View-Controller separation, to make my code easier to read/understand), or if you have another paradigm that you prefer. If you like my way, I would recommend extending `smtech/stmarks-reflexive-canvas-lti` to including branding appropriate for your own institution and using that. If not, or if you are trying to avoid GUI-entanglements, this "plain vanilla" package fits the bill.

## Sample code

In the `examples` directory you will find two example tool providers:

  1. [`tool-provider`](https://github.com/smtech/reflexive-canvas-lti/tree/master/examples/tool-provider) shows an example of an LTI Tool Provider constructed with the "plain vanilla" package (this one).
  2. [`stmarks-tool-provider`](https://github.com/smtech/reflexive-canvas-lti/tree/master/examples/stmarks-tool-provider) shows the same example, tweaked to use `smtech/stmarks-reflexive-canvas-lti` instead -- which I think is cleaner, easier and simpler. But I'm biased.

## Composer Fiddly-bits

_Nota bene:_ Because Stephen Vickers hasn't yet published his `LTI_Tool_Provider` via [Composer](http://getcomposer.org), I _think_ the best way to incorporate his code is to replicate the following within your own `composer.json` file. (That way, when `smtech/reflexive-canvas-lti` depends on `spvsoftwareproducts/LTI_Tool_Provider`, Composer will know what we're all taking about.)

Also: to download the `spvsoftwareproducts/LTI_Tool_Provider` package from its central repository, you _have_ to allow non-HTTPS downloads, which is a bit of a security hole. Not the end of the world, but not Best Practices, either.

```JSON
"config": {
    "secure-http": false
},
"repositories": [
    {
        "type": "package",
        "package": {
            "name": "spvsoftwareproducts/LTI_Tool_Provider",
            "version": "2.5.01",
            "dist": {
                "url": "http://projects.oscelot.org/gf/download/frsrelease/956/6025/LTI_Tool_Provider-2.5.01.zip",
                "type": "zip"
            },
            "license": "LGPL-3.0",
            "authors": [
                {
                    "name": "Stephen Vickers"
                }
            ],
            "homepage": "http://spvsoftwareproducts.com/php/lti_tool_provider/",
            "autoload": {
                "classmap": [""]
            }
        }
    }
]
```

And also:

```JSON
"require": {
    "smtech/reflexive-canvas-lti": "~1.0"
}
```
