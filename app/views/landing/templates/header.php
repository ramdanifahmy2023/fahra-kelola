<!DOCTYPE html>
<html lang="id" data-theme="cupcake">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $data['judul'] ?? app_name; ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= images; ?>/favicon.svg">
  <link rel="apple-touch-icon" href="<?= images; ?>/favicon.svg">
  <meta name="theme-color" content="#a3a3a3">
  
  <!-- CSS Tailwind & DaisyUI -->
  <link rel="stylesheet" href="<?= assets; ?>/css/style.css">
  
  <!-- Google Material Symbols -->
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
</head>
<body class="min-h-screen bg-base-200 flex flex-col">
