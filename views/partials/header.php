<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Lang;

$lang = Lang::current();
$dir = Lang::dir();
$active ??= '';
$pageTitle ??= t('app.name');
$query = $_GET;
$query['lang'] = $lang === 'ar' ? 'en' : 'ar';
$toggleUrl = (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') . '?' . http_build_query($query);
$bootstrapCss = $dir === 'rtl' ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css';
$clientConfig = [
    'csrf' => Csrf::token(),
    'lang' => $lang,
    'dir' => $dir,
    'strings' => Lang::all(),
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(t('app.name')) ?></title>
    <meta name="description" content="<?= e(t('app.tagline')) ?>">
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&amp;family=Inter:wght@400;500;600;700&amp;display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/<?= e($bootstrapCss) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/print.css" media="print">
</head>
<body class="page-<?= e($active) ?>">
<a class="skip-link" href="#main"><?= e(t('nav.skip')) ?></a>
<header class="site-header">
    <nav class="navbar navbar-expand-md" aria-label="<?= e(t('nav.main')) ?>">
        <div class="container">
            <a class="navbar-brand" href="/">
                <span class="brand-mark" aria-hidden="true">B</span>
                <span><?= e(t('app.name')) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="<?= e(t('nav.toggle')) ?>">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">
                    <li class="nav-item">
                        <a class="nav-link<?= $active === 'shop' ? ' active' : '' ?>" href="/"<?= $active === 'shop' ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-bag" aria-hidden="true"></i> <?= e(t('nav.shop')) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $active === 'payments' ? ' active' : '' ?>" href="/payments.php"<?= $active === 'payments' ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-receipt" aria-hidden="true"></i> <?= e(t('nav.payments')) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $active === 'logs' ? ' active' : '' ?>" href="/logs.php"<?= $active === 'logs' ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-terminal" aria-hidden="true"></i> <?= e(t('nav.logs')) ?>
                        </a>
                    </li>
                </ul>
                <a class="btn btn-sm btn-lang" href="<?= e($toggleUrl) ?>" hreflang="<?= $lang === 'ar' ? 'en' : 'ar' ?>">
                    <i class="bi bi-translate" aria-hidden="true"></i> <?= e(t('nav.switch_lang')) ?>
                </a>
            </div>
        </div>
    </nav>
</header>
<main id="main" class="py-4 py-lg-5">
