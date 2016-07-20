# ReflexiveCanvasLTI

[![Latest Version](https://img.shields.io/packagist/v/smtech/reflexive-canvas-lti.svg)](https://packagist.org/packages/smtech/rreflexive-canvas-lti)

LTI tool provider class that includes the \"reflexive\" reach back into Canvas via the API.

[Full documentation in repository.](https://htmlpreview.github.io/?https://raw.githubusercontent.com/smtech/reflexive-canvas-lti/master/doc/namespaces/smtech.ReflexiveCanvasLTI.html)

See [Tool Provider](https://github.com/smtech/reflexive-canvas-lti/tree/master/examples/tool-provider) for an example of usage.

## Caveat

This is currently a work in progress, aiming to replace [starter-canvas-api-via-lti](https://github.com/smtech/starter-canvas-api-via-lti), which I am finding tempermental to use and burdensome to maintain in its current architecture. Plus also, `starter-canvas-api-via-lti` suffers the misfortune of litterin my project directories with files not actually directly related to my project, and I find that distracting and annoying.

## Composer Fiddly-bits

_Nota bene:_ Because Stephen Vickers hasn't yet published his `LTI_Tool_Provider` via [Composer](http://getcomposer.org), I _think_ the best way to incorporate his code is to replicate the following within your own `composer.json` file. (That way, when `smtech/reflexive-canvas-lti` depends on `spvsoftwareproducts/LTI_Tool_Provider`, Composer will know what we're all taking about.)

```JSON
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
                "psr-4": {
                    "" : ""
                }
            }
        }
    }
]
```

And also:

```JSON
"require": {
    "smtech/reflexive-canvas-lti": "dev-master"
}
```
