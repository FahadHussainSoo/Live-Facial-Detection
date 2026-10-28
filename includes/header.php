<?php
/**
 * Reusable Header
 * Expects an optional $pageTitle variable to be set before including this file.
 */
if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | <?php echo htmlspecialchars(APP_NAME); ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons (optional, useful for UI polish) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">

    <?php if (isset($extraHeadHTML)) { echo $extraHeadHTML; } ?>
</head>
<body>
