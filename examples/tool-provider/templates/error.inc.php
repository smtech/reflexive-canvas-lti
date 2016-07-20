<!DOCTYPE html>
<html>
    <head>
        <title>Error</title>
    </head>
    <body>
        <h1>Error</h1>

        <pre><?= (empty($message) ? 'Something bad happened.' : print_r($message, true)) ?></pre>
    </body>
</html>
<?php exit ?>
