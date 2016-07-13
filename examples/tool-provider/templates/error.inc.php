<!DOCTYPE html>
<html>
    <head>
        <title>Error</title>
    </head>
    <body>
        <h1>Error</h1>

        <p><?= (empty($message) ? 'Something bad happened.' : $message) ?>
    </body>
</html>

<?php exit ?>
